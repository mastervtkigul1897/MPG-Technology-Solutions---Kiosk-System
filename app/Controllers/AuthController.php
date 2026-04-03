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
        session_flash('errors', ['Self-registration is disabled. Ask your store owner to create a staff account for you.']);

        return redirect(url('/login'));
    }

    public function register(Request $request): Response
    {
        session_flash('errors', ['Self-registration is disabled. Ask your store owner to create a staff account for you.']);

        return redirect(url('/login'));
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return redirect(url('/'));
    }
}
