<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\LaundrySchema;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;
use App\Services\PasswordResetService;
use PDO;

final class AuthController
{
    private const FREE_PLAN_CODE = 'free_access';
    private const FREE_PLANS = ['trial', 'free', 'free_trial', self::FREE_PLAN_CODE];
    private static ?bool $usersLastLoginColumnExists = null;

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

    private static function seedLaundryBranchConfigDefaults(PDO $pdo, int $tenantId): void
    {
        if ($tenantId < 1) {
            return;
        }
        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'laundry_branch_configs'");
            if ($chk === false || $chk->fetch(PDO::FETCH_ASSOC) === false) {
                return;
            }
            $hasLaundryStatus = self::tableHasColumn($pdo, 'laundry_branch_configs', 'laundry_status_tracking_enabled');
            $hasTrackMovement = self::tableHasColumn($pdo, 'laundry_branch_configs', 'track_machine_movement');
            $hasMachineAssignment = self::tableHasColumn($pdo, 'laundry_branch_configs', 'machine_assignment_enabled');
            $hasDefaultDryingMinutes = self::tableHasColumn($pdo, 'laundry_branch_configs', 'default_drying_minutes');

            $insertColumns = ['tenant_id'];
            $insertValues = [$tenantId];
            $updateParts = [];

            if ($hasLaundryStatus) {
                $insertColumns[] = 'laundry_status_tracking_enabled';
                $insertValues[] = 1;
                $updateParts[] = 'laundry_status_tracking_enabled = VALUES(laundry_status_tracking_enabled)';
            }
            if ($hasTrackMovement) {
                $insertColumns[] = 'track_machine_movement';
                $insertValues[] = 1;
                $updateParts[] = 'track_machine_movement = VALUES(track_machine_movement)';
            }
            if ($hasMachineAssignment) {
                // Automatic ON by default, so manual stays OFF.
                $insertColumns[] = 'machine_assignment_enabled';
                $insertValues[] = 0;
                $updateParts[] = 'machine_assignment_enabled = VALUES(machine_assignment_enabled)';
            }
            if ($hasDefaultDryingMinutes) {
                $insertColumns[] = 'default_drying_minutes';
                $insertValues[] = 30;
                $updateParts[] = 'default_drying_minutes = VALUES(default_drying_minutes)';
            }
            if (self::tableHasColumn($pdo, 'laundry_branch_configs', 'kiosk_inclusion_autofill_mode')) {
                $insertColumns[] = 'kiosk_inclusion_autofill_mode';
                $insertValues[] = 'lock';
                $updateParts[] = 'kiosk_inclusion_autofill_mode = VALUES(kiosk_inclusion_autofill_mode)';
            }
            if (self::tableHasColumn($pdo, 'laundry_branch_configs', 'kiosk_fold_autofill_mode')) {
                $insertColumns[] = 'kiosk_fold_autofill_mode';
                $insertValues[] = 'free_fold';
                $updateParts[] = 'kiosk_fold_autofill_mode = VALUES(kiosk_fold_autofill_mode)';
            }
            if (self::tableHasColumn($pdo, 'laundry_branch_configs', 'kiosk_autofill_order_type_codes')) {
                $insertColumns[] = 'kiosk_autofill_order_type_codes';
                $insertValues[] = 'drop_off';
                $updateParts[] = 'kiosk_autofill_order_type_codes = VALUES(kiosk_autofill_order_type_codes)';
            }
            if (self::tableHasColumn($pdo, 'laundry_branch_configs', 'enable_bluetooth_print')) {
                $insertColumns[] = 'enable_bluetooth_print';
                $insertValues[] = 0;
                $updateParts[] = 'enable_bluetooth_print = VALUES(enable_bluetooth_print)';
            }
            if (self::tableHasColumn($pdo, 'laundry_branch_configs', 'pickup_sms_enabled')) {
                $insertColumns[] = 'pickup_sms_enabled';
                $insertValues[] = 0;
                $updateParts[] = 'pickup_sms_enabled = VALUES(pickup_sms_enabled)';
            }
            if (self::tableHasColumn($pdo, 'laundry_branch_configs', 'pickup_email_enabled')) {
                $insertColumns[] = 'pickup_email_enabled';
                $insertValues[] = 1;
                $updateParts[] = 'pickup_email_enabled = VALUES(pickup_email_enabled)';
            }
            if ($updateParts === []) {
                return;
            }

            $sql = sprintf(
                'INSERT INTO laundry_branch_configs (%s, created_at, updated_at)
                 VALUES (%s, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE %s, updated_at = NOW()',
                implode(', ', $insertColumns),
                implode(', ', array_fill(0, count($insertColumns), '?')),
                implode(', ', $updateParts)
            );
            $st = $pdo->prepare($sql);
            $st->execute($insertValues);
        } catch (\Throwable) {
            // Optional setup only; should not block successful registration.
        }
    }

    /** @return array{hash:string,label:string,token:string,user_agent:string,ip:string}|null */
    private static function registerDevicePayload(Request $request): ?array
    {
        $tokenRaw = trim((string) $request->input('device_token'));
        $labelRaw = trim((string) $request->input('device_name'));
        $platformRaw = trim((string) $request->input('device_platform'));
        $uaRaw = trim((string) $request->input('device_user_agent'));
        $ua = $uaRaw !== '' ? $uaRaw : trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ip = trim((string) $request->ip());

        $token = (string) preg_replace('/[^a-zA-Z0-9\-_]/', '', $tokenRaw);
        if ($token !== '' && strlen($token) > 160) {
            $token = substr($token, 0, 160);
        }
        $fallbackBase = strtolower(trim($ua.'|'.$platformRaw.'|'.$ip));
        if ($token === '' && $fallbackBase === '') {
            return null;
        }
        $hashSeed = $token !== '' ? ('token:'.$token) : ('fallback:'.$fallbackBase);
        $hash = hash('sha256', $hashSeed);

        $label = trim($labelRaw);
        if ($label === '' && $platformRaw !== '') {
            $label = $platformRaw.' device';
        }
        if ($label === '') {
            $label = 'Unknown device';
        }
        if (strlen($label) > 190) {
            $label = substr($label, 0, 190);
        }

        return [
            'hash' => $hash,
            'label' => $label,
            'token' => $token,
            'user_agent' => $ua !== '' ? $ua : 'unknown',
            'ip' => $ip !== '' ? $ip : '0.0.0.0',
        ];
    }

    /** @return array<string,mixed>|null */
    private static function existingFreePlanDevice(PDO $pdo, string $deviceHash): ?array
    {
        if ($deviceHash === '') {
            return null;
        }
        $inPlans = "'".implode("','", self::FREE_PLANS)."'";
        $st = $pdo->prepare(
            "SELECT td.tenant_id, td.device_label, t.name AS tenant_name, t.plan
             FROM tenant_trial_devices td
             INNER JOIN tenants t ON t.id = td.tenant_id
             WHERE td.device_hash = ?
               AND LOWER(TRIM(t.plan)) IN ({$inPlans})
             LIMIT 1"
        );
        $st->execute([$deviceHash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private static function saveTrialDevice(PDO $pdo, int $tenantId, int $userId, array $device): void
    {
        $st = $pdo->prepare(
            'INSERT INTO tenant_trial_devices
            (tenant_id, user_id, device_hash, device_token, device_label, user_agent, ip_address, last_seen_at, created_at, updated_at)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                tenant_id = VALUES(tenant_id),
                user_id = VALUES(user_id),
                device_token = VALUES(device_token),
                device_label = VALUES(device_label),
                user_agent = VALUES(user_agent),
                ip_address = VALUES(ip_address),
                last_seen_at = NOW(),
                updated_at = NOW()'
        );
        $st->execute([
            $tenantId,
            $userId,
            (string) ($device['hash'] ?? ''),
            (string) ($device['token'] ?? ''),
            (string) ($device['label'] ?? 'Unknown device'),
            (string) ($device['user_agent'] ?? 'unknown'),
            (string) ($device['ip'] ?? '0.0.0.0'),
        ]);
    }

    private static function hasUsersLastLoginColumn(PDO $pdo): bool
    {
        if (self::$usersLastLoginColumnExists === true) {
            return true;
        }
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'last_login_at'");
            $exists = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            if ($exists) {
                self::$usersLastLoginColumnExists = true;
            }

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?'
            );
            $st->execute([$table, $column]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function showLogin(Request $request): Response
    {
        return view_guest('Login', 'auth.login', ['status' => session_flash('status')]);
    }

    public function showForgotPassword(Request $request): Response
    {
        return view_guest('Forgot password', 'auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session_flash('errors', ['Please enter a valid email address.']);
            return redirect(url('/forgot-password'));
        }

        try {
            PasswordResetService::sendResetLink(App::db(), $email);
            session_flash('status', 'If that verified email exists, a password reset link has been sent.');
        } catch (\Throwable) {
            session_flash('errors', ['Password reset email could not be sent. Please check the mail settings.']);
        }

        return redirect(url('/forgot-password'));
    }

    public function showResetPassword(Request $request): Response
    {
        return view_guest('Reset password', 'auth.reset-password', [
            'email' => strtolower(trim((string) $request->query('email', ''))),
            'token' => trim((string) $request->query('token', '')),
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $token = trim((string) $request->input('token'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');

        $errors = [];
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '') {
            $errors[] = 'Password reset link is invalid or expired.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }
        if ($errors !== []) {
            session_flash('errors', $errors);
            return redirect(url('/reset-password?email='.rawurlencode($email).'&token='.rawurlencode($token)));
        }

        try {
            if (! PasswordResetService::resetPassword(App::db(), $email, $token, $password)) {
                session_flash('errors', ['Password reset link is invalid or expired. Please request a new one.']);
                return redirect(url('/forgot-password'));
            }
        } catch (\Throwable) {
            session_flash('errors', ['Password could not be reset. Please try again.']);
            return redirect(url('/forgot-password'));
        }

        session_flash('status', 'Password reset successfully. You can now log in.');
        return redirect(url('/login'));
    }

    public function login(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');

        $pdo = App::db();
        EmailVerificationService::ensureSchema($pdo);
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (! $user || ! password_verify($password, (string) $user['password'])) {
            ActivityLogger::log(
                null,
                0,
                'guest',
                'auth',
                'login_failed',
                $request,
                'Failed login attempt.',
                ['email' => $email !== '' ? $email : null]
            );
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
        if (self::hasUsersLastLoginColumn($pdo)) {
            try {
                $pdo->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1')
                    ->execute([(int) $user['id']]);
            } catch (\Throwable) {
                // Keep login successful even if telemetry update fails.
            }
        }

        $sessionUser = Auth::user();
        if (Auth::isEmailVerificationEnforced($sessionUser)) {
            session_flash('status', 'Email verification is now required to continue. Please verify your email address.');
            return redirect(url('/email/verification-notice'));
        }
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

        if (Auth::isTenantInactive($sessionUser)) {
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
        if (! $inactive) {
            return redirect(url('/dashboard'));
        }

        return view_subscription_screen('Store inactive', 'auth.subscription-ended', [
            'appOwnerEmail' => (string) (App::config('app_owner_email') ?? ''),
            'reason' => 'inactive',
        ]);
    }

    public function showRegister(Request $request): Response
    {
        return view_guest('Register', 'auth.register', [
            'register_upgrade_blocked' => (bool) session_flash('register_upgrade_blocked'),
            'register_existing_store' => (string) session_flash('register_existing_store'),
        ]);
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

        $device = self::registerDevicePayload($request);
        if (! is_array($device)) {
            session_flash('errors', ['Could not verify this device. Please refresh and try again.']);
            return redirect(url('/register'));
        }

        $pdo = App::db();
        EmailVerificationService::ensureSchema($pdo);
        try {
            $existingFree = self::existingFreePlanDevice($pdo, (string) ($device['hash'] ?? ''));
        } catch (\Throwable) {
            session_flash('errors', ['Could not verify existing device plan. Please try again.']);
            return redirect(url('/register'));
        }
        if (is_array($existingFree)) {
            $existingStore = trim((string) ($existingFree['tenant_name'] ?? ''));
            session_flash('errors', ['This device already has an existing Free plan account. Please upgrade now to continue.']);
            session_flash('register_upgrade_blocked', true);
            session_flash('register_existing_store', $existingStore);
            return redirect(url('/register'));
        }

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
            $tenantColumns = ['name', 'slug', 'plan', 'is_active'];
            $tenantValues = [$storeName, $slug, self::FREE_PLAN_CODE, 1];
            if (self::tableHasColumn($pdo, 'tenants', 'license_starts_at')) {
                $tenantColumns[] = 'license_starts_at';
                $tenantValues[] = $now;
            }
            if (self::tableHasColumn($pdo, 'tenants', 'license_expires_at')) {
                $tenantColumns[] = 'license_expires_at';
                $tenantValues[] = $trialEnd;
            }
            if (self::tableHasColumn($pdo, 'tenants', 'created_at')) {
                $tenantColumns[] = 'created_at';
                $tenantValues[] = $now;
            }
            if (self::tableHasColumn($pdo, 'tenants', 'updated_at')) {
                $tenantColumns[] = 'updated_at';
                $tenantValues[] = $now;
            }
            $tenantSql = 'INSERT INTO tenants ('.implode(', ', $tenantColumns).') VALUES ('.implode(', ', array_fill(0, count($tenantColumns), '?')).')';
            $st = $pdo->prepare($tenantSql);
            $st->execute($tenantValues);
            $tenantId = (int) $pdo->lastInsertId();
            try {
                self::updateTenantBranchDefaults($pdo, $tenantId);
            } catch (\Throwable) {
                // Optional setup only; should not block successful registration.
            }
            try {
                LaundrySchema::ensureDefaultInventoryForTenant($pdo, $tenantId);
            } catch (\Throwable) {
                // Optional setup only; should not block successful registration.
            }
            self::seedLaundryBranchConfigDefaults($pdo, $tenantId);

            $userColumns = ['name', 'email', 'password', 'role', 'tenant_id'];
            $userValues = [$name, $email, $pwHash, 'tenant_admin', $tenantId];
            if (self::tableHasColumn($pdo, 'users', 'email_verified_at')) {
                $userColumns[] = 'email_verified_at';
                $userValues[] = null;
            }
            if (self::tableHasColumn($pdo, 'users', 'created_at')) {
                $userColumns[] = 'created_at';
                $userValues[] = $now;
            }
            if (self::tableHasColumn($pdo, 'users', 'updated_at')) {
                $userColumns[] = 'updated_at';
                $userValues[] = $now;
            }
            $userSql = 'INSERT INTO users ('.implode(', ', $userColumns).') VALUES ('.implode(', ', array_fill(0, count($userColumns), '?')).')';
            $st = $pdo->prepare($userSql);
            $st->execute($userValues);
            $userId = (int) $pdo->lastInsertId();
            try {
                self::saveTrialDevice($pdo, $tenantId, $userId, $device);
            } catch (\Throwable) {
                // Device link telemetry is optional; keep account creation successful.
            }
            $pdo->commit();

            Auth::login($userId);
            try {
                EmailVerificationService::sendVerificationForUserId($pdo, $userId);
                session_flash('status', 'Your 7-day trial account was created. You can use the system now, but please verify your email within '.Auth::emailVerificationGraceDays().' days.');
            } catch (\Throwable) {
                session_flash('status', 'Your trial account was created. You can use the system now, but please verify your email within '.Auth::emailVerificationGraceDays().' days. Verification email could not be sent automatically; use the resend verification link from inside the app.');
            }

            return redirect(url('/dashboard'));
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Production safety net:
            // Some environments can partially persist inserts despite a later failure.
            // If tenant/user already exists for this fresh registration input, continue as success.
            try {
                $recoverUserSt = $pdo->prepare(
                    'SELECT id
                     FROM users
                     WHERE LOWER(email) = LOWER(?)
                     LIMIT 1'
                );
                $recoverUserSt->execute([$email]);
                $recoverUser = $recoverUserSt->fetch(PDO::FETCH_ASSOC) ?: null;

                $recoverTenantSt = $pdo->prepare(
                    'SELECT id
                     FROM tenants
                     WHERE LOWER(name) = LOWER(?)
                     LIMIT 1'
                );
                $recoverTenantSt->execute([$storeName]);
                $recoverTenant = $recoverTenantSt->fetch(PDO::FETCH_ASSOC) ?: null;

                if (is_array($recoverUser) && is_array($recoverTenant)) {
                    $recoveredUserId = (int) ($recoverUser['id'] ?? 0);
                    if ($recoveredUserId > 0) {
                        Auth::login($recoveredUserId);
                        session_flash('status', 'Your trial account was created. You can use the system now, but please verify your email within '.Auth::emailVerificationGraceDays().' days.');
                        return redirect(url('/dashboard'));
                    }
                }
            } catch (\Throwable) {
                // Keep original error below when recovery probe fails.
            }
            session_flash('errors', ['Could not create trial account. Please try again.']);
            return redirect(url('/register'));
        }
    }

    public function showVerificationNotice(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect(url('/login'));
        }
        if (($user['role'] ?? '') === 'super_admin' || ! empty($user['email_verified_at'])) {
            return redirect(url('/dashboard'));
        }

        return view_guest('Verify email', 'auth.verify-email', [
            'email' => strtolower(trim((string) ($user['email'] ?? ''))),
        ]);
    }

    public function resendVerification(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect(url('/login'));
        }
        if (($user['role'] ?? '') === 'super_admin' || ! empty($user['email_verified_at'])) {
            return redirect(url('/dashboard'));
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        try {
            EmailVerificationService::sendVerificationForUserId(App::db(), (int) ($user['id'] ?? 0));
            session_flash('status', 'A fresh verification link has been sent.');
        } catch (\Throwable) {
            session_flash('errors', ['Verification email could not be sent. Please check the mail settings.']);
        }

        return redirect(url('/email/verification-notice'));
    }

    public function verifyEmail(Request $request): Response
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $token = trim((string) $request->query('token', ''));
        if (EmailVerificationService::verify(App::db(), $email, $token)) {
            session_flash('status', 'Email verified. You can now use your account.');
            $user = Auth::user();
            if ($user) {
                return redirect(url('/dashboard'));
            }

            return redirect(url('/login'));
        }

        session_flash('errors', ['Verification link is invalid or has already been used. Please request a new one.']);
        return Auth::user() ? redirect(url('/email/verification-notice')) : redirect(url('/login'));
    }

    public function logout(Request $request): Response
    {
        Auth::logout();

        return redirect(url('/'));
    }
}
