<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class AuthController
{
    private static function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');
        return $slug !== '' ? $slug : 'store';
    }

    private static function uniqueTenantSlug(PDO $pdo, string $baseSlug): string
    {
        $slug = $baseSlug;
        $n = 2;
        while (true) {
            $st = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE slug = ?');
            $st->execute([$slug]);
            $exists = (int) $st->fetchColumn() > 0;
            if (! $exists) {
                return $slug;
            }
            $slug = $baseSlug.'-'.$n;
            $n++;
        }
    }

    private static function updateTenantBranchDefaults(PDO $pdo, int $tenantId): void
    {
        if ($tenantId < 1) {
            return;
        }
        $hasParent = false;
        $hasGroup = false;
        $hasMain = false;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'parent_tenant_id'");
            $hasParent = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            $chk = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'branch_group_id'");
            $hasGroup = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            $chk = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'is_main_branch'");
            $hasMain = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            $sets = [];
            if ($hasParent) {
                $sets[] = 'parent_tenant_id = IFNULL(parent_tenant_id, id)';
            }
            if ($hasGroup) {
                $sets[] = 'branch_group_id = IFNULL(branch_group_id, id)';
            }
            if ($hasMain) {
                $sets[] = 'is_main_branch = 1';
            }
            if ($sets !== []) {
                $sql = 'UPDATE tenants SET '.implode(', ', $sets).' WHERE id = ? LIMIT 1';
                $st = $pdo->prepare($sql);
                $st->execute([$tenantId]);
            }
        } catch (\Throwable) {
            // ignore optional branch fields if unsupported in target DB
        }
    }

    public function showLogin(Request $request): Response
    {
        return view_guest('Login', 'auth.login', ['status' => session_flash('status')]);
    }

    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $pdo = App::db();
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (! $user || ! password_verify($password, (string) $user['password'])) {
            session_flash('errors', ['Invalid credentials.']);

            return redirect(url('/login'));
        }

        $role = (string) ($user['role'] ?? '');
        $tid = $user['tenant_id'] ?? null;
        if (($role === 'tenant_admin' || $role === 'cashier') && ($tid === null || $tid === '' || (int) $tid === 0)) {
            session_flash('errors', ['This account is not assigned to a store. Contact the platform administrator.']);

            return redirect(url('/login'));
        }

        Auth::login((int) $user['id']);

        $tidRaw = $user['tenant_id'] ?? null;
        $tenantIdForLog = ($tidRaw !== null && $tidRaw !== '' && (int) $tidRaw > 0) ? (int) $tidRaw : null;
        ActivityLogger::log(
            $tenantIdForLog,
            (int) $user['id'],
            $role,
            'auth',
            'login',
            $request,
            sprintf('Successful login: %s (%s)', (string) ($user['name'] ?? ''), (string) ($user['email'] ?? '')),
            ['email' => (string) ($user['email'] ?? ''), 'role' => $role]
        );

        $sessionUser = Auth::user();
        if (Auth::isTenantInactive($sessionUser) || Auth::isTenantSubscriptionExpired($sessionUser)) {
            return redirect(url('/subscription-ended'));
        }

        return redirect(url('/dashboard'));
    }

    public function subscriptionEnded(Request $request): Response
    {
        $u = Auth::user();
        if (! $u) {
            return redirect(url('/login'));
        }
        if (($u['role'] ?? '') === 'super_admin') {
            return redirect(url('/dashboard'));
        }
        $inactive = Auth::isTenantInactive($u);
        $expired = Auth::isTenantSubscriptionExpired($u);
        if (! $inactive && ! $expired) {
            return redirect(url('/dashboard'));
        }

        $reason = $inactive ? 'inactive' : 'expired';
        $pageTitle = $inactive ? 'Store inactive' : 'Subscription ended';

        return view_subscription_screen($pageTitle, 'auth.subscription-ended', [
            'appOwnerEmail' => (string) (App::config('app_owner_email') ?? ''),
            'reason' => $reason,
        ]);
    }

    public function showRegister(Request $request): Response
    {
        return view_guest('Register', 'auth.register');
    }

    public function register(Request $request): Response
    {
        $storeName = trim((string) $request->input('store_name'));
        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $passwordConfirmation = (string) $request->input('password_confirmation');

        $errors = [];
        if ($storeName === '' || strlen($storeName) > 255) {
            $errors[] = 'Store name is required.';
        }
        if ($name === '' || strlen($name) > 255) {
            $errors[] = 'Name is required.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            session_flash('errors', $errors);
            return redirect(url('/register'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(?)');
        $st->execute([$email]);
        if ((int) $st->fetchColumn() > 0) {
            session_flash('errors', ['Email is already in use.']);
            return redirect(url('/register'));
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE LOWER(name) = LOWER(?)');
        $st->execute([$storeName]);
        if ((int) $st->fetchColumn() > 0) {
            session_flash('errors', ['Store name already exists. Please use a different store name.']);
            return redirect(url('/register'));
        }

        // Trial window: exactly 7×24h from the same instant as license_starts_at (anchored; do not use strtotime('+7 days') alone).
        $nowTs = time();
        $now = date('Y-m-d H:i:s', $nowTs);
        $trialEnd = date('Y-m-d H:i:s', strtotime('+7 days', $nowTs));
        $slug = self::uniqueTenantSlug($pdo, self::normalizeSlug($storeName));
        $pwHash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT INTO tenants (name, slug, plan, is_active, license_starts_at, license_expires_at, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?, NOW(), NOW())');
            $st->execute([$storeName, $slug, 'trial', $now, $trialEnd]);
            $tenantId = (int) $pdo->lastInsertId();
            self::updateTenantBranchDefaults($pdo, $tenantId);

            $st = $pdo->prepare('INSERT INTO users (name, email, password, role, tenant_id, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())');
            $st->execute([$name, $email, $pwHash, 'tenant_admin', $tenantId]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->commit();

            Auth::login($userId);
            session_flash('status', 'Welcome! Your 7-day trial is active until '.date('M j, Y', strtotime($trialEnd)).'.');

            return redirect(url('/dashboard'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            session_flash('errors', ['Could not create trial account. Please try again.']);
            return redirect(url('/register'));
        }
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return redirect(url('/'));
    }
}
