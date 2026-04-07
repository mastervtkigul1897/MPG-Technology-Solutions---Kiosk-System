<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Auth
{
    /** Cached only when column exists (true), so we re-check after DB migrations without restarting PHP. */
    private static ?bool $modulePermissionsColumnExists = null;
    private static ?bool $tenantBranchColumnsReady = null;

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
        $sql = $hasModsCol
            ? 'SELECT id, name, email, password, role, tenant_id, module_permissions, email_verified_at FROM users WHERE id = ? LIMIT 1'
            : 'SELECT id, name, email, password, role, tenant_id, email_verified_at FROM users WHERE id = ? LIMIT 1';
        $st = $pdo->prepare($sql);
        $st->execute([(int) $id]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (! $u) {
            return null;
        }
        $raw = $u['module_permissions'] ?? null;
        unset($u['module_permissions']);
        $u['modules'] = $hasModsCol
            ? StaffModules::normalizeCashierModules(is_string($raw) ? $raw : null)
            : StaffModules::normalizeCashierModules(null);

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
    }

    public static function logout(): void
    {
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

    /**
     * True when the user's tenant has a subscription end date strictly before today (calendar date).
     * No end date = not expired. Super admin and non-tenant users: false.
     */
    public static function isTenantSubscriptionExpired(?array $user): bool
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
        $st = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([(int) $tid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row || $row['license_expires_at'] === null || $row['license_expires_at'] === '') {
            return false;
        }
        $expiryDate = date('Y-m-d', strtotime((string) $row['license_expires_at']));

        return $expiryDate < date('Y-m-d');
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
        $expiredBranchId = 0;
        $expiredBranchName = '';
        if ($sessionTenantId > 0) {
            $st = $pdo->prepare(
                'SELECT id, name, license_expires_at
                 FROM tenants
                 WHERE id = ? AND branch_group_id = ? AND is_active = 1
                 LIMIT 1'
            );
            $st->execute([$sessionTenantId, $groupId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (! self::isExpiryValueExpired((string) ($row['license_expires_at'] ?? ''))) {
                    return (int) $row['id'];
                }
                $expiredBranchId = (int) ($row['id'] ?? 0);
                $expiredBranchName = trim((string) ($row['name'] ?? ''));
            }
        }

        $st = $pdo->prepare(
            'SELECT id, name, license_expires_at
             FROM tenants
             WHERE branch_group_id = ? AND is_active = 1
             ORDER BY is_main_branch DESC, id ASC
             LIMIT 1'
        );
        $st->execute([$groupId]);
        $preferred = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (is_array($preferred) && ! self::isExpiryValueExpired((string) ($preferred['license_expires_at'] ?? ''))) {
            $preferredId = (int) ($preferred['id'] ?? 0);
            if ($preferredId > 0) {
                $_SESSION['active_tenant_id'] = $preferredId;

                return $preferredId;
            }
        } elseif (is_array($preferred)) {
            $expiredBranchId = (int) ($preferred['id'] ?? 0);
            $expiredBranchName = trim((string) ($preferred['name'] ?? ''));
        }

        $st = $pdo->prepare(
            'SELECT id
             FROM tenants
             WHERE branch_group_id = ? AND is_active = 1
               AND (license_expires_at IS NULL OR DATE(license_expires_at) >= CURDATE())
             ORDER BY is_main_branch DESC, id ASC
             LIMIT 1'
        );
        $st->execute([$groupId]);
        $fallback = (int) ($st->fetchColumn() ?: 0);
        if ($fallback > 0) {
            if ($expiredBranchId > 0) {
                $prevNoticeId = (int) ($_SESSION['branch_expired_notice']['branch_id'] ?? 0);
                if ($prevNoticeId !== $expiredBranchId) {
                    $_SESSION['branch_expired_notice'] = [
                        'branch_id' => $expiredBranchId,
                        'branch_name' => $expiredBranchName !== '' ? $expiredBranchName : ('Branch #'.$expiredBranchId),
                        'message' => 'Branch subscription expired. Please renew this branch.',
                        'at' => date('Y-m-d H:i:s'),
                    ];
                }
            }
            $_SESSION['active_tenant_id'] = $fallback;

            return $fallback;
        }

        $_SESSION['active_tenant_id'] = (int) ($preferred['id'] ?? $baseTenantId);

        return (int) ($_SESSION['active_tenant_id'] ?? $baseTenantId);
    }

    private static function isExpiryValueExpired(string $licenseExpiresAt): bool
    {
        $value = trim($licenseExpiresAt);
        if ($value === '') {
            return false;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return false;
        }
        $expiryDate = date('Y-m-d', $ts);

        return $expiryDate < date('Y-m-d');
    }
}
