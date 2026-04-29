<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    private const FREE_PLAN_CODES = ['trial', 'free', 'free_trial', 'free_access'];
    private const FREE_TRIAL_DAYS = 7;
    private const EMAIL_VERIFICATION_GRACE_DAYS = 5;
    /** Cached only when column exists (true), so we re-check after DB migrations without restarting PHP. */
    private static ?bool $modulePermissionsColumnExists = null;
    private static ?bool $tenantBranchColumnsReady = null;
    private static ?bool $usersStaffTypeColumnExists = null;
    private static function hasUsersStaffTypeColumn(): bool
    {
        if (self::$usersStaffTypeColumnExists === true) {
            return true;
        }
        try {
            $pdo = App::db();
            $chk = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'staff_type'");
            $exists = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            if ($exists) {
                self::$usersStaffTypeColumnExists = true;
            }

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @var array<int,string> */
    private static array $tenantPlanCache = [];
    /** @var array<int,string> */
    private static array $tenantLicenseExpiresCache = [];
    /** @var array<int,array<string,mixed>> */
    private static array $tenantSubscriptionMetaCache = [];

    public static function hasModulePermissionsColumn(): bool
    {
        if (self::$modulePermissionsColumnExists === true) {
            return true;
        }
        try {
            $pdo = App::db();
            $chk = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'module_permissions'");
            $exists = $chk !== false && $chk->fetch(PDO::FETCH_ASSOC) !== false;
            if ($exists) {
                self::$modulePermissionsColumnExists = true;
            }

            return $exists;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hasTenantBranchColumns(): bool
    {
        if (self::$tenantBranchColumnsReady === true) {
            return true;
        }
        try {
            $pdo = App::db();
            $a = $pdo->query("SHOW COLUMNS FROM `tenants` LIKE 'branch_group_id'");
            $b = $pdo->query("SHOW COLUMNS FROM `tenants` LIKE 'is_main_branch'");
            $ok = $a !== false && $a->fetch(PDO::FETCH_ASSOC) !== false
                && $b !== false && $b->fetch(PDO::FETCH_ASSOC) !== false;
            if ($ok) {
                self::$tenantBranchColumnsReady = true;
            }

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function user(): ?array
    {
        // Force relogin when the calendar day changes (prevents idle timeouts during store hours).
        $loginDay = (string) ($_SESSION['login_day'] ?? '');
        if ($loginDay !== '' && $loginDay !== date('Y-m-d')) {
            self::logout();
            return null;
        }

        $id = $_SESSION['user_id'] ?? null;
        if (! $id) {
            return null;
        }
        $pdo = App::db();
        $hasModsCol = self::hasModulePermissionsColumn();
        $hasStaffTypeCol = self::hasUsersStaffTypeColumn();
        $staffTypeSelect = $hasStaffTypeCol ? 'staff_type' : "'full_time' AS staff_type";
        $sql = $hasModsCol
            ? "SELECT id, name, email, password, role, {$staffTypeSelect}, tenant_id, module_permissions, email_verified_at, created_at FROM users WHERE id = ? LIMIT 1"
            : "SELECT id, name, email, password, role, {$staffTypeSelect}, tenant_id, email_verified_at, created_at FROM users WHERE id = ? LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([(int) $id]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (! $u) {
            return null;
        }
        $raw = $u['module_permissions'] ?? null;
        unset($u['module_permissions']);
        $u['staff_type'] = self::normalizeStaffType((string) ($u['staff_type'] ?? 'full_time'));
        $u['modules'] = $hasModsCol
            ? StaffModules::normalizeCashierModules(is_string($raw) ? $raw : null)
            : StaffModules::normalizeCashierModules(null);
        if (($u['role'] ?? '') === 'cashier' && in_array($u['staff_type'], ['utility', 'driver'], true)) {
            // Utility/Driver are time-log only accounts.
            $u['modules'] = [];
        }

        // Tenant owner can switch active branch context using one login.
        if (($u['role'] ?? '') === 'tenant_admin' && ! empty($u['tenant_id'])) {
            $baseTenantId = (int) $u['tenant_id'];
            $u['base_tenant_id'] = $baseTenantId;
            if (self::hasTenantBranchColumns()) {
                $u['tenant_id'] = self::resolveActiveTenantId($baseTenantId);
            }
        }

        return $u;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_day'] = date('Y-m-d');
        unset($_SESSION['active_tenant_id']);
        try {
            $pdo = App::db();
            $pdo->prepare('UPDATE users SET is_online = 1, last_seen_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1')
                ->execute([$userId]);
        } catch (\Throwable) {
            // Keep login successful even when presence telemetry cannot be saved.
        }
    }

    public static function logout(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                $pdo = App::db();
                $pdo->prepare('UPDATE users SET is_online = 0, last_seen_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1')
                    ->execute([$userId]);
            } catch (\Throwable) {
                // Keep logout successful even when presence telemetry cannot be saved.
            }
        }
        unset($_SESSION['user_id']);
        unset($_SESSION['login_day']);
        unset($_SESSION['active_tenant_id']);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Cashier module access; tenant_admin always true for valid keys. */
    public static function canAccessModule(?array $user, string $module): bool
    {
        if (! $user || ! StaffModules::isValidKey($module)) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'tenant_admin') {
            return true;
        }
        if ($role !== 'cashier') {
            return false;
        }
        $list = $user['modules'] ?? [];

        return in_array($module, $list, true);
    }

    /** Store owner, or cashier with the given module (for CRUD UI/actions). */
    public static function tenantMayManage(array $user, string $module): bool
    {
        return ($user['role'] ?? '') === 'tenant_admin' || self::canAccessModule($user, $module);
    }

    private static function normalizeStaffType(string $raw): string
    {
        $v = strtolower(trim($raw));
        if (in_array($v, ['utility', 'driver', 'part_timer', 'full_time'], true)) {
            return $v;
        }

        return 'full_time';
    }

    /**
     * Subscription expiry no longer locks tenants out.
     * Expired paid subscriptions are handled by isTenantFreePlanRestricted().
     */
    public static function isTenantSubscriptionExpired(?array $user): bool
    {
        return false;
    }

    /**
     * True when the user's tenant exists and is toggled inactive (is_active = 0).
     * Super admin and non-tenant users: false.
     */
    public static function isTenantInactive(?array $user): bool
    {
        if (! $user) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'super_admin') {
            return false;
        }
        if ($role !== 'tenant_admin' && $role !== 'cashier') {
            return false;
        }
        $tid = $user['tenant_id'] ?? null;
        if ($tid === null || $tid === '' || (int) $tid === 0) {
            return false;
        }
        $pdo = App::db();
        $st = $pdo->prepare('SELECT is_active FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([(int) $tid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            return false;
        }

        return ! (bool) $row['is_active'];
    }

    public static function tenantPlan(?array $user): string
    {
        if (! $user) {
            return '';
        }
        $tid = (int) ($user['tenant_id'] ?? 0);
        if ($tid < 1) {
            return '';
        }
        if (isset(self::$tenantPlanCache[$tid])) {
            return self::$tenantPlanCache[$tid];
        }
        try {
            $pdo = App::db();
            $st = $pdo->prepare('SELECT plan FROM tenants WHERE id = ? LIMIT 1');
            $st->execute([$tid]);
            $plan = strtolower(trim((string) $st->fetchColumn()));
            self::$tenantPlanCache[$tid] = $plan;
            return $plan;
        } catch (\Throwable) {
            return '';
        }
    }

    public static function isTenantFreeTrial(?array $user): bool
    {
        $plan = self::tenantPlan($user);
        return in_array($plan, self::FREE_PLAN_CODES, true);
    }

    /** True while free-plan trial window (first 7 days) is still active. */
    public static function isTenantFreeTrialActive(?array $user): bool
    {
        if (! self::isTenantFreeTrial($user)) {
            return false;
        }
        $trialEndsAt = self::tenantFreeTrialEndsAt($user);
        if ($trialEndsAt === null) {
            return false;
        }

        return time() < $trialEndsAt;
    }

    /** True when a tenant should use the limited Free version. */
    public static function isTenantFreePlanRestricted(?array $user): bool
    {
        if (! $user) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'super_admin') {
            return false;
        }
        if ($role !== 'tenant_admin' && $role !== 'cashier') {
            return false;
        }
        $tid = (int) ($user['tenant_id'] ?? 0);
        if ($tid < 1) {
            return false;
        }
        $meta = self::tenantSubscriptionMeta($tid);
        if (! is_array($meta)) {
            return false;
        }
        if (self::isFreePlanName((string) ($meta['plan'] ?? ''))) {
            $trialEndsAt = self::tenantFreeTrialEndsAt($user);
            if ($trialEndsAt === null) {
                return true;
            }

            return time() >= $trialEndsAt;
        }

        return self::tenantSubscriptionEndReached($meta);
    }

    /** True only for paid/non-free tenant plans whose subscription end time has passed. */
    public static function isTenantPaidSubscriptionExpired(?array $user): bool
    {
        if (! $user) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'super_admin') {
            return false;
        }
        if ($role !== 'tenant_admin' && $role !== 'cashier') {
            return false;
        }
        $tid = (int) ($user['tenant_id'] ?? 0);
        if ($tid < 1) {
            return false;
        }
        $meta = self::tenantSubscriptionMeta($tid);
        if (! is_array($meta) || self::isFreePlanName((string) ($meta['plan'] ?? ''))) {
            return false;
        }

        return self::tenantSubscriptionEndReached($meta);
    }

    /**
     * Days remaining until tenant's license_expires_at (calendar-day based).
     * Returns null when not available (no user/tenant, no expiry date, or read fails).
     */
    public static function tenantDaysRemaining(?array $user): ?int
    {
        if (! $user) {
            return null;
        }
        $tid = (int) ($user['tenant_id'] ?? 0);
        if ($tid < 1) {
            return null;
        }
        $raw = self::$tenantLicenseExpiresCache[$tid] ?? null;
        if ($raw === null) {
            try {
                $pdo = App::db();
                $st = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
                $st->execute([$tid]);
                $raw = (string) $st->fetchColumn();
                self::$tenantLicenseExpiresCache[$tid] = $raw;
            } catch (\Throwable) {
                return null;
            }
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }
        $exp = date('Y-m-d', $ts);
        $today = date('Y-m-d');
        // date diff in days (expiry date inclusive -> if exp==today => 0 days remaining)
        $days = (int) floor((strtotime($exp) - strtotime($today)) / 86400);

        return max(0, $days);
    }

    public static function tenantFreeTrialDaysRemaining(?array $user): ?int
    {
        $endsAt = self::tenantFreeTrialEndsAt($user);
        if ($endsAt === null) {
            return null;
        }
        $exp = date('Y-m-d', $endsAt);
        $today = date('Y-m-d');
        $days = (int) floor((strtotime($exp) - strtotime($today)) / 86400);

        return max(0, $days);
    }

    private static function tenantFreeTrialEndsAt(?array $user): ?int
    {
        if (! $user) {
            return null;
        }
        $tid = (int) ($user['tenant_id'] ?? 0);
        if ($tid < 1) {
            return null;
        }
        $meta = self::tenantSubscriptionMeta($tid);
        if (! is_array($meta) || ! self::isFreePlanName((string) ($meta['plan'] ?? ''))) {
            return null;
        }
        $startsRaw = trim((string) ($meta['license_starts_at'] ?? ''));
        if ($startsRaw !== '') {
            $startTs = strtotime($startsRaw);
            if ($startTs !== false) {
                return strtotime('+'.self::FREE_TRIAL_DAYS.' days', $startTs) ?: null;
            }
        }

        $createdRaw = trim((string) ($meta['created_at'] ?? ''));
        if ($createdRaw !== '') {
            $createdTs = strtotime($createdRaw);
            if ($createdTs !== false) {
                return strtotime('+'.self::FREE_TRIAL_DAYS.' days', $createdTs) ?: null;
            }
        }

        $expiryRaw = trim((string) ($meta['license_expires_at'] ?? ''));
        if ($expiryRaw !== '') {
            $ts = strtotime($expiryRaw);
            if ($ts !== false) {
                return $ts;
            }
        }

        return null;
    }

    private static function tenantSubscriptionMeta(int $tenantId): ?array
    {
        if ($tenantId < 1) {
            return null;
        }
        if (isset(self::$tenantSubscriptionMetaCache[$tenantId])) {
            return self::$tenantSubscriptionMetaCache[$tenantId];
        }
        try {
            $pdo = App::db();
            $st = $pdo->prepare(
                'SELECT plan, license_starts_at, license_expires_at, created_at
                 FROM tenants
                 WHERE id = ? LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return null;
            }
            self::$tenantSubscriptionMetaCache[$tenantId] = $row;
            self::$tenantPlanCache[$tenantId] = strtolower(trim((string) ($row['plan'] ?? '')));
            self::$tenantLicenseExpiresCache[$tenantId] = (string) ($row['license_expires_at'] ?? '');

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $meta */
    private static function tenantSubscriptionEndReached(array $meta): bool
    {
        $expiryRaw = trim((string) ($meta['license_expires_at'] ?? ''));
        if ($expiryRaw === '') {
            return false;
        }
        $expiryTs = strtotime($expiryRaw);
        if ($expiryTs === false) {
            return false;
        }

        return time() >= $expiryTs;
    }

    private static function isFreePlanName(string $plan): bool
    {
        return in_array(strtolower(trim($plan)), self::FREE_PLAN_CODES, true);
    }

    /** @return array{staff:int,inventory_items:int,machines:int,washers:int,dryers:int} */
    public static function freePlanLimits(): array
    {
        return [
            'staff' => 1,
            'inventory_items' => PHP_INT_MAX,
            'machines' => 6,
            'washers' => 3,
            'dryers' => 3,
        ];
    }

    public static function tenantCashierCount(?array $user): int
    {
        if (! $user) {
            return 0;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return 0;
        }
        try {
            $pdo = App::db();
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND role = ?');
            $st->execute([$tenantId, 'cashier']);
            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function tenantLaundryInventoryCount(?array $user): int
    {
        if (! $user) {
            return 0;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return 0;
        }
        try {
            $pdo = App::db();
            $st = $pdo->prepare('SELECT COUNT(*) FROM laundry_inventory_items WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function tenantLaundryMachineCount(?array $user): int
    {
        if (! $user) {
            return 0;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return 0;
        }
        try {
            $pdo = App::db();
            $st = $pdo->prepare('SELECT COUNT(*) FROM laundry_machines WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            return (int) $st->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function isCashierWithinFreeLimit(?array $user): bool
    {
        if (! $user || ! self::isTenantFreePlanRestricted($user)) {
            return true;
        }
        if (($user['role'] ?? '') !== 'cashier') {
            return true;
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        if ($tenantId < 1 || $userId < 1) {
            return true;
        }
        $limit = (int) (self::freePlanLimits()['staff'] ?? 2);
        try {
            $pdo = App::db();
            $st = $pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE tenant_id = ? AND role = ?
                 ORDER BY created_at ASC, id ASC
                 LIMIT '.$limit
            );
            $st->execute([$tenantId, 'cashier']);
            $allowedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
            return in_array($userId, $allowedIds, true);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Attendance module is Premium-only after free 7-day premium trial.
     * Allowed when tenant is not in restricted Free mode.
     */
    public static function canUseAttendanceFeature(?array $user): bool
    {
        if (! $user) {
            return false;
        }
        $role = $user['role'] ?? '';
        if ($role === 'super_admin') {
            return true;
        }
        if ($role !== 'tenant_admin' && $role !== 'cashier') {
            return false;
        }

        return ! self::isTenantFreePlanRestricted($user);
    }

    public static function emailVerificationGraceDays(): int
    {
        return self::EMAIL_VERIFICATION_GRACE_DAYS;
    }

    public static function emailVerificationGraceEndsAt(?array $user): ?int
    {
        if (! $user) {
            return null;
        }
        if (($user['role'] ?? '') === 'super_admin') {
            return null;
        }
        if (! empty($user['email_verified_at'])) {
            return null;
        }
        $createdRaw = trim((string) ($user['created_at'] ?? ''));
        if ($createdRaw === '') {
            return null;
        }
        $createdTs = strtotime($createdRaw);
        if ($createdTs === false) {
            return null;
        }
        $endsAt = strtotime('+'.self::EMAIL_VERIFICATION_GRACE_DAYS.' days', $createdTs);
        if ($endsAt === false) {
            return null;
        }

        return $endsAt;
    }

    public static function isEmailVerificationEnforced(?array $user): bool
    {
        if (! $user) {
            return false;
        }
        if (($user['role'] ?? '') === 'super_admin' || ! empty($user['email_verified_at'])) {
            return false;
        }
        $graceEndsAt = self::emailVerificationGraceEndsAt($user);
        if ($graceEndsAt === null) {
            return true;
        }

        return time() >= $graceEndsAt;
    }

    public static function emailVerificationGraceDaysRemaining(?array $user): ?int
    {
        if (! $user) {
            return null;
        }
        if (($user['role'] ?? '') === 'super_admin' || ! empty($user['email_verified_at'])) {
            return null;
        }
        $graceEndsAt = self::emailVerificationGraceEndsAt($user);
        if ($graceEndsAt === null) {
            return 0;
        }
        $seconds = max(0, $graceEndsAt - time());

        return (int) ceil($seconds / 86400);
    }

    private static function resolveActiveTenantId(int $baseTenantId): int
    {
        $pdo = App::db();

        $st = $pdo->prepare('SELECT id, branch_group_id FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$baseTenantId]);
        $base = $st->fetch(PDO::FETCH_ASSOC);
        if (! $base) {
            return $baseTenantId;
        }
        $groupId = (int) ($base['branch_group_id'] ?? $baseTenantId);
        if ($groupId < 1) {
            $groupId = $baseTenantId;
        }

        $sessionTenantId = (int) ($_SESSION['active_tenant_id'] ?? 0);
        if ($sessionTenantId > 0) {
            $st = $pdo->prepare(
                'SELECT id
                 FROM tenants
                 WHERE id = ? AND branch_group_id = ? AND is_active = 1
                 LIMIT 1'
            );
            $st->execute([$sessionTenantId, $groupId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int) $row['id'];
            }
        }

        $st = $pdo->prepare(
            'SELECT id
             FROM tenants
             WHERE branch_group_id = ? AND is_active = 1
             ORDER BY is_main_branch DESC, id ASC
             LIMIT 1'
        );
        $st->execute([$groupId]);
        $preferred = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (is_array($preferred)) {
            $preferredId = (int) ($preferred['id'] ?? 0);
            if ($preferredId > 0) {
                $_SESSION['active_tenant_id'] = $preferredId;

                return $preferredId;
            }
        }

        $_SESSION['active_tenant_id'] = (int) ($preferred['id'] ?? $baseTenantId);

        return (int) ($_SESSION['active_tenant_id'] ?? $baseTenantId);
    }
}
