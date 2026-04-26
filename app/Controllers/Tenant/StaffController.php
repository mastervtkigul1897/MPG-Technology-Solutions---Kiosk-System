<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\StaffModules;
use App\Core\LaundrySchema;
use PDO;

/**
 * Store owners (tenant_admin) manage cashiers for their tenant only.
 */
final class StaffController
{
    private static ?bool $hasUsersDayRateColumn = null;
    private static ?bool $hasUsersFoldingFeeColumn = null;
    private static ?bool $hasUsersStaffTypeColumn = null;
    private static ?bool $hasUsersOvertimeRateColumn = null;
    private static ?bool $hasUsersWorkDaysCsvColumn = null;
    private static ?bool $hasUsersWorkingHoursColumn = null;
    private static ?bool $hasUsersCommissionEligibleColumn = null;

    private static function hasUsersDayRateColumn(PDO $pdo): bool
    {
        if (self::$hasUsersDayRateColumn !== null) {
            return self::$hasUsersDayRateColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'day_rate'");
            self::$hasUsersDayRateColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersDayRateColumn = false;
        }

        return self::$hasUsersDayRateColumn;
    }

    private static function hasUsersFoldingFeeColumn(PDO $pdo): bool
    {
        if (self::$hasUsersFoldingFeeColumn !== null) {
            return self::$hasUsersFoldingFeeColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'folding_fee_per_load'");
            self::$hasUsersFoldingFeeColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersFoldingFeeColumn = false;
        }

        return self::$hasUsersFoldingFeeColumn;
    }

    private static function hasUsersStaffTypeColumn(PDO $pdo): bool
    {
        if (self::$hasUsersStaffTypeColumn !== null) {
            return self::$hasUsersStaffTypeColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'staff_type'");
            self::$hasUsersStaffTypeColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersStaffTypeColumn = false;
        }

        return self::$hasUsersStaffTypeColumn;
    }

    private static function hasUsersOvertimeRateColumn(PDO $pdo): bool
    {
        if (self::$hasUsersOvertimeRateColumn !== null) {
            return self::$hasUsersOvertimeRateColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'overtime_rate_per_hour'");
            self::$hasUsersOvertimeRateColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersOvertimeRateColumn = false;
        }

        return self::$hasUsersOvertimeRateColumn;
    }

    private static function hasUsersWorkDaysCsvColumn(PDO $pdo): bool
    {
        if (self::$hasUsersWorkDaysCsvColumn !== null) {
            return self::$hasUsersWorkDaysCsvColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'work_days_csv'");
            self::$hasUsersWorkDaysCsvColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersWorkDaysCsvColumn = false;
        }

        return self::$hasUsersWorkDaysCsvColumn;
    }

    private static function hasUsersWorkingHoursColumn(PDO $pdo): bool
    {
        if (self::$hasUsersWorkingHoursColumn !== null) {
            return self::$hasUsersWorkingHoursColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'working_hours_per_day'");
            self::$hasUsersWorkingHoursColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersWorkingHoursColumn = false;
        }

        return self::$hasUsersWorkingHoursColumn;
    }

    private static function hasUsersCommissionEligibleColumn(PDO $pdo): bool
    {
        if (self::$hasUsersCommissionEligibleColumn !== null) {
            return self::$hasUsersCommissionEligibleColumn;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'commission_eligible'");
            self::$hasUsersCommissionEligibleColumn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasUsersCommissionEligibleColumn = false;
        }

        return self::$hasUsersCommissionEligibleColumn;
    }

    private static function ensureUsersWorkDaysCsvColumn(PDO $pdo): void
    {
        if (self::hasUsersWorkDaysCsvColumn($pdo)) {
            return;
        }
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `work_days_csv` VARCHAR(64) NOT NULL DEFAULT '1,2,3,4,5,6,7' AFTER `overtime_rate_per_hour`");
        } catch (\Throwable) {
            try {
                // Fallback for older schemas that do not yet have overtime_rate_per_hour.
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `work_days_csv` VARCHAR(64) NOT NULL DEFAULT '1,2,3,4,5,6,7'");
            } catch (\Throwable) {
                // handled by guards in save/update paths
            }
        } finally {
            // Always re-check after attempted schema repair.
            self::$hasUsersWorkDaysCsvColumn = null;
        }
    }

    private static function ensureUsersPayrollColumns(PDO $pdo): void
    {
        if (! self::hasUsersWorkingHoursColumn($pdo)) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `working_hours_per_day` DECIMAL(6,2) NOT NULL DEFAULT 8.00 AFTER `work_days_csv`");
            } catch (\Throwable) {
            } finally {
                self::$hasUsersWorkingHoursColumn = null;
            }
        }
        if (! self::hasUsersCommissionEligibleColumn($pdo)) {
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `commission_eligible` TINYINT(1) NOT NULL DEFAULT 0 AFTER `working_hours_per_day`");
            } catch (\Throwable) {
            } finally {
                self::$hasUsersCommissionEligibleColumn = null;
            }
        }
    }

    private static function ensureUsersOvertimeRateColumn(PDO $pdo): void
    {
        if (self::hasUsersOvertimeRateColumn($pdo)) {
            return;
        }
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `overtime_rate_per_hour` DECIMAL(16,4) NOT NULL DEFAULT 0");
        } catch (\Throwable) {
            // Keep graceful fallback for legacy databases.
        } finally {
            self::$hasUsersOvertimeRateColumn = null;
        }
    }

    private static function ensureUsersStaffTypeColumn(PDO $pdo): void
    {
        if (self::hasUsersStaffTypeColumn($pdo)) {
            return;
        }
        try {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `staff_type` VARCHAR(20) NOT NULL DEFAULT 'full_time' AFTER `role`");
            self::$hasUsersStaffTypeColumn = null;
        } catch (\Throwable) {
            // Keep graceful handling below when column still missing.
        }
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        self::ensureUsersStaffTypeColumn($pdo);
        self::ensureUsersOvertimeRateColumn($pdo);
        self::ensureUsersWorkDaysCsvColumn($pdo);
        self::ensureUsersPayrollColumns($pdo);
        $hasModsCol = Auth::hasModulePermissionsColumn();
        $hasDayRateCol = self::hasUsersDayRateColumn($pdo);
        $hasFoldingFeeCol = self::hasUsersFoldingFeeColumn($pdo);
        $hasStaffTypeCol = self::hasUsersStaffTypeColumn($pdo);
        $hasOvertimeRateCol = self::hasUsersOvertimeRateColumn($pdo);
        $hasWorkDaysCsvCol = self::hasUsersWorkDaysCsvColumn($pdo);
        $hasWorkingHoursCol = self::hasUsersWorkingHoursColumn($pdo);
        $hasCommissionEligibleCol = self::hasUsersCommissionEligibleColumn($pdo);
        $staffTypeSelect = $hasStaffTypeCol ? 'staff_type' : "'full_time' AS staff_type";
        $overtimeRateSelect = $hasOvertimeRateCol ? 'overtime_rate_per_hour' : '0 AS overtime_rate_per_hour';
        $workDaysSelect = $hasWorkDaysCsvCol ? 'work_days_csv' : "'1,2,3,4,5,6,7' AS work_days_csv";
        $workingHoursSelect = $hasWorkingHoursCol ? 'working_hours_per_day' : '8.00 AS working_hours_per_day';
        $commissionEligibleSelect = $hasCommissionEligibleCol ? 'commission_eligible' : '0 AS commission_eligible';
        if ($hasModsCol) {
            $sql = ($hasDayRateCol && $hasFoldingFeeCol)
                ? "SELECT id, name, email, role, {$staffTypeSelect}, day_rate, {$overtimeRateSelect}, {$workDaysSelect}, {$workingHoursSelect}, {$commissionEligibleSelect}, folding_fee_per_load, module_permissions, created_at FROM users WHERE tenant_id = ?
                 ORDER BY CASE WHEN role = 'tenant_admin' THEN 0 ELSE 1 END, name"
                : "SELECT id, name, email, role, {$staffTypeSelect}, 350 AS day_rate, {$overtimeRateSelect}, {$workDaysSelect}, {$workingHoursSelect}, {$commissionEligibleSelect}, 10 AS folding_fee_per_load, module_permissions, created_at FROM users WHERE tenant_id = ?
                 ORDER BY CASE WHEN role = 'tenant_admin' THEN 0 ELSE 1 END, name";
        } else {
            $sql = ($hasDayRateCol && $hasFoldingFeeCol)
                ? "SELECT id, name, email, role, {$staffTypeSelect}, day_rate, {$overtimeRateSelect}, {$workDaysSelect}, {$workingHoursSelect}, {$commissionEligibleSelect}, folding_fee_per_load, created_at FROM users WHERE tenant_id = ?
                 ORDER BY CASE WHEN role = 'tenant_admin' THEN 0 ELSE 1 END, name"
                : "SELECT id, name, email, role, {$staffTypeSelect}, 350 AS day_rate, {$overtimeRateSelect}, {$workDaysSelect}, {$workingHoursSelect}, {$commissionEligibleSelect}, 10 AS folding_fee_per_load, created_at FROM users WHERE tenant_id = ?
                 ORDER BY CASE WHEN role = 'tenant_admin' THEN 0 ELSE 1 END, name";
        }
        $st = $pdo->prepare($sql);
        $st->execute([$tenantId]);
        $staff = $st->fetchAll(PDO::FETCH_ASSOC);
        $freeRestricted = Auth::isTenantFreePlanRestricted($user);
        $freeStaffLimit = (int) (Auth::freePlanLimits()['staff'] ?? 2);
        $allowedCashierIdSet = [];
        if ($freeRestricted) {
            try {
                $stAllowed = $pdo->prepare(
                    'SELECT id
                     FROM users
                     WHERE tenant_id = ? AND role = ?
                     ORDER BY created_at ASC, id ASC
                     LIMIT '.$freeStaffLimit
                );
                $stAllowed->execute([$tenantId, 'cashier']);
                $allowedCashierIds = array_map(
                    static fn (array $r): int => (int) ($r['id'] ?? 0),
                    $stAllowed->fetchAll(PDO::FETCH_ASSOC) ?: []
                );
                $allowedCashierIdSet = array_flip(array_filter($allowedCashierIds, static fn (int $id): bool => $id > 0));
            } catch (\Throwable) {
                $allowedCashierIdSet = [];
            }
        }
        $currentBranchExpiry = null;
        try {
            $stExpiry = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
            $stExpiry->execute([$tenantId]);
            $currentBranchExpiry = $stExpiry->fetchColumn();
        } catch (\Throwable) {
            $currentBranchExpiry = null;
        }
        $hasOwnerInCurrentList = false;
        foreach ($staff as $i => $row) {
            if (($row['role'] ?? '') === 'tenant_admin') {
                $hasOwnerInCurrentList = true;
            }
            if (($row['role'] ?? '') === 'cashier') {
                $raw = $hasModsCol ? ($row['module_permissions'] ?? null) : null;
                $staffType = $this->normalizeStaffType((string) ($row['staff_type'] ?? 'full_time'));
                $staff[$i]['staff_type'] = $staffType;
                if ($this->isTimeOnlyStaffType($staffType)) {
                    $staff[$i]['modules'] = [];
                } else {
                    $staff[$i]['modules'] = StaffModules::normalizeCashierModules(is_string($raw) ? $raw : null);
                }
            } else {
                $staff[$i]['staff_type'] = 'owner';
                $staff[$i]['modules'] = [];
            }
            $staff[$i]['free_limited_restricted'] = $freeRestricted
                && (($row['role'] ?? '') === 'cashier')
                && ! isset($allowedCashierIdSet[(int) ($row['id'] ?? 0)]);
            $staff[$i]['owner_shared'] = false;
            $staff[$i]['subscription_expired'] = (($row['role'] ?? '') === 'tenant_admin')
                ? $this->isExpiredDate($currentBranchExpiry)
                : false;
            unset($staff[$i]['module_permissions']);
        }

        // Branch setup can use one shared owner login for all branches.
        // If this branch has no local tenant_admin row, show the group owner in the staff list.
        if (! $hasOwnerInCurrentList) {
            $owner = null;
            if (Auth::hasTenantBranchColumns()) {
                $stOwner = $pdo->prepare(
                    'SELECT u.id, u.name, u.email, u.role, u.created_at, tu.license_expires_at
                     FROM users u
                     INNER JOIN tenants tu ON tu.id = u.tenant_id
                     INNER JOIN tenants tc ON tc.id = ?
                     WHERE u.role = ? AND tu.branch_group_id = tc.branch_group_id
                     ORDER BY tu.is_main_branch DESC, u.id ASC
                     LIMIT 1'
                );
                $stOwner->execute([$tenantId, 'tenant_admin']);
                $owner = $stOwner->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                $stOwner = $pdo->prepare(
                    'SELECT id, name, email, role, created_at
                     FROM users
                     WHERE tenant_id = ? AND role = ?
                     ORDER BY id ASC
                     LIMIT 1'
                );
                $stOwner->execute([$tenantId, 'tenant_admin']);
                $owner = $stOwner->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if (is_array($owner)) {
                $owner['modules'] = [];
                $owner['owner_shared'] = true;
                $owner['free_limited_restricted'] = false;
                $owner['subscription_expired'] = $this->isExpiredDate($owner['license_expires_at'] ?? null);
                unset($owner['license_expires_at']);
                array_unshift($staff, $owner);
            }
        }

        $activateCommission = false;
        $activateOtIncentives = false;
        $payrollCutoffDays = 15;
        $payrollHoursPerDay = 8.0;
        try {
            $activateCommission = $this->getBranchBoolConfig($pdo, $tenantId, 'activate_commission', false);
            $activateOtIncentives = $this->getBranchBoolConfig($pdo, $tenantId, 'activate_ot_incentives', false);
            $payrollCutoffDays = $this->getBranchIntConfig($pdo, $tenantId, 'payroll_cutoff_days', 15);
            $payrollHoursPerDay = $this->getBranchFloatConfig($pdo, $tenantId, 'payroll_hours_per_day', 8.0);
        } catch (\Throwable) {
            $activateCommission = false;
            $activateOtIncentives = false;
            $payrollCutoffDays = 15;
            $payrollHoursPerDay = 8.0;
        }

        return view_page('Staff', 'tenant.staff.index', [
            'staff' => $staff,
            'module_labels' => StaffModules::LABELS,
            'optional_module_keys' => StaffModules::optionalModuleKeys(),
            'required_baseline_labels' => array_map(
                static fn (string $k): string => StaffModules::LABELS[$k],
                StaffModules::REQUIRED_BASELINE
            ),
            'module_permissions_available' => $hasModsCol,
            'activate_commission' => $activateCommission,
            'activate_ot_incentives' => $activateOtIncentives,
            'payroll_cutoff_days' => max(1, min(31, $payrollCutoffDays)),
            'payroll_hours_per_day' => max(1.0, min(24.0, $payrollHoursPerDay)),
            'premium_trial_browse_lock' => false,
            'free_staff_limit' => $freeRestricted ? $freeStaffLimit : null,
        ]);
    }

    public function updateBranchSettings(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            session_flash('errors', ['Invalid tenant.']);
            return redirect(url('/tenant/staff'));
        }

        $activateCommission = $request->boolean('activate_commission');
        $activateOtIncentives = $request->boolean('activate_ot_incentives');
        $payrollCutoffDays = max(1, min(31, (int) $request->input('payroll_cutoff_days', 15)));
        $payrollHoursPerDay = max(1.0, min(24.0, (float) $request->input('payroll_hours_per_day', 8)));
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $this->persistBranchBoolConfig($pdo, $tenantId, 'activate_commission', $activateCommission);
            $this->persistBranchBoolConfig($pdo, $tenantId, 'activate_ot_incentives', $activateOtIncentives);
            $this->persistBranchIntConfig($pdo, $tenantId, 'payroll_cutoff_days', $payrollCutoffDays);
            $this->persistBranchFloatConfig($pdo, $tenantId, 'payroll_hours_per_day', $payrollHoursPerDay);
            session_flash('success', 'Staff settings updated.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(url('/tenant/staff'));
    }

    private function isExpiredDate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $ts = strtotime((string) $value);
        if ($ts === false) {
            return false;
        }

        return date('Y-m-d', $ts) < date('Y-m-d');
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        if (Auth::isTenantFreePlanRestricted($user)) {
            $limits = Auth::freePlanLimits();
            $cashierCount = Auth::tenantCashierCount($user);
            if ($cashierCount >= (int) ($limits['staff'] ?? 1)) {
                session_flash('errors', ['Free Mode limit reached: only 1 staff account plus the store owner is allowed.']);
                return redirect(url('/tenant/staff'));
            }
        }
        $tenantId = (int) $user['tenant_id'];

        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');
        $dayRate = max(0.0, (float) $request->input('day_rate', 350));
        $overtimeRatePerHour = max(0.0, (float) $request->input('overtime_rate_per_hour', 0));
        $workingHoursPerDay = max(1.0, min(24.0, (float) $request->input('working_hours_per_day', 8)));
        $commissionEligible = $request->boolean('commission_eligible') ? 1 : 0;
        $foldingFeePerLoad = 0.0;
        $staffType = $this->normalizeStaffType((string) $request->input('staff_type', 'full_time'));
        $workDaysCsv = $this->workDaysCsvFromRequest($request);
        $modulesRaw = $request->input('modules', []);
        $optionalPicked = is_array($modulesRaw) ? StaffModules::sanitizeRequested($modulesRaw) : [];
        $modules = $this->isTimeOnlyStaffType($staffType)
            ? []
            : StaffModules::mergeRequiredBaseline($optionalPicked);

        $errors = [];
        if ($name === '' || strlen($name) > 255) {
            $errors[] = 'Name is required.';
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        self::ensureUsersStaffTypeColumn($pdo);
        self::ensureUsersOvertimeRateColumn($pdo);
        self::ensureUsersWorkDaysCsvColumn($pdo);
        self::ensureUsersPayrollColumns($pdo);
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors[] = 'That email is already registered.';
        }

        if ($errors !== []) {
            session_flash('errors', $errors);

            return redirect(url('/tenant/staff'));
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');
        $hasDayRateCol = self::hasUsersDayRateColumn($pdo);
        $hasFoldingFeeCol = self::hasUsersFoldingFeeColumn($pdo);
        $hasStaffTypeCol = self::hasUsersStaffTypeColumn($pdo);
        $hasOvertimeRateCol = self::hasUsersOvertimeRateColumn($pdo);
        $hasWorkDaysCsvCol = self::hasUsersWorkDaysCsvColumn($pdo);
        $hasWorkingHoursCol = self::hasUsersWorkingHoursColumn($pdo);
        $hasCommissionEligibleCol = self::hasUsersCommissionEligibleColumn($pdo);
        if (! $hasWorkDaysCsvCol || ! $hasWorkingHoursCol || ! $hasCommissionEligibleCol) {
            session_flash('errors', ['Working days column is missing in database. Please run latest schema update/migrations, then try again.']);

            return redirect(url('/tenant/staff'));
        }
        if (Auth::hasModulePermissionsColumn()) {
            $modulesJson = json_encode($modules, JSON_UNESCAPED_UNICODE);
            if ($hasDayRateCol && $hasFoldingFeeCol && $hasStaffTypeCol && $hasOvertimeRateCol && $hasWorkDaysCsvCol && $hasWorkingHoursCol && $hasCommissionEligibleCol) {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, staff_type, tenant_id, day_rate, overtime_rate_per_hour, work_days_csv, working_hours_per_day, commission_eligible, folding_fee_per_load, module_permissions, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$name, $email, $hash, $staffType, $tenantId, $dayRate, $overtimeRatePerHour, $workDaysCsv, $workingHoursPerDay, $commissionEligible, $foldingFeePerLoad, $modulesJson, $now, $now, $now]);
            } elseif ($hasDayRateCol && $hasFoldingFeeCol) {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, tenant_id, day_rate, folding_fee_per_load, module_permissions, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$name, $email, $hash, $tenantId, $dayRate, $foldingFeePerLoad, $modulesJson, $now, $now, $now]);
            } else {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, tenant_id, module_permissions, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?, ?)'
                )->execute([$name, $email, $hash, $tenantId, $modulesJson, $now, $now, $now]);
            }
        } else {
            if ($hasDayRateCol && $hasFoldingFeeCol && $hasStaffTypeCol && $hasOvertimeRateCol && $hasWorkDaysCsvCol && $hasWorkingHoursCol && $hasCommissionEligibleCol) {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, staff_type, tenant_id, day_rate, overtime_rate_per_hour, work_days_csv, working_hours_per_day, commission_eligible, folding_fee_per_load, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$name, $email, $hash, $staffType, $tenantId, $dayRate, $overtimeRatePerHour, $workDaysCsv, $workingHoursPerDay, $commissionEligible, $foldingFeePerLoad, $now, $now, $now]);
            } elseif ($hasDayRateCol && $hasFoldingFeeCol) {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, tenant_id, day_rate, folding_fee_per_load, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?, ?, ?)'
                )->execute([$name, $email, $hash, $tenantId, $dayRate, $foldingFeePerLoad, $now, $now, $now]);
            } else {
                $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, tenant_id, email_verified_at, created_at, updated_at)
                     VALUES (?, ?, ?, \'cashier\', ?, ?, ?, ?)'
                )->execute([$name, $email, $hash, $tenantId, $now, $now, $now]);
            }
        }

        $newId = (int) $pdo->lastInsertId();
        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'staff',
            'create_cashier',
            $request,
            sprintf('Created cashier: %s (%s)', $name, $email),
            ['new_user_id' => $newId, 'email' => $email, 'modules' => Auth::hasModulePermissionsColumn() ? $modules : []]
        );

        session_flash('status', 'Cashier account created.');

        return redirect(url('/tenant/staff'));
    }

    public function updateDayRate(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $targetId = (int) $id;
        $dayRate = max(0.0, (float) $request->input('day_rate', 350));
        $overtimeRatePerHour = max(0.0, (float) $request->input('overtime_rate_per_hour', 0));
        $workingHoursPerDay = max(1.0, min(24.0, (float) $request->input('working_hours_per_day', 8)));
        $commissionEligible = $request->boolean('commission_eligible') ? 1 : 0;
        $staffType = $this->normalizeStaffType((string) $request->input('staff_type', 'full_time'));
        $workDaysCsv = $this->workDaysCsvFromRequest($request);

        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        self::ensureUsersStaffTypeColumn($pdo);
        self::ensureUsersOvertimeRateColumn($pdo);
        self::ensureUsersWorkDaysCsvColumn($pdo);
        self::ensureUsersPayrollColumns($pdo);
        if (! self::hasUsersDayRateColumn($pdo)) {
            session_flash('errors', ['Database is missing payroll column. Please run: ALTER TABLE users ADD COLUMN day_rate DECIMAL(16,4) NOT NULL DEFAULT 350;']);

            return redirect(url('/tenant/staff'));
        }
        $staffTypeSelect = self::hasUsersStaffTypeColumn($pdo) ? 'staff_type' : "'full_time' AS staff_type";
        $st = $pdo->prepare("SELECT id, name, role, {$staffTypeSelect} FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
        $st->execute([$targetId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            session_flash('errors', ['Staff not found.']);

            return redirect(url('/tenant/staff'));
        }
        if (($row['role'] ?? '') !== 'cashier') {
            session_flash('errors', ['Store owner settings cannot be updated from this page.']);

            return redirect(url('/tenant/staff'));
        }

        $hasStaffTypeCol = self::hasUsersStaffTypeColumn($pdo);
        $hasWorkDaysCsvCol = self::hasUsersWorkDaysCsvColumn($pdo);
        $hasWorkingHoursCol = self::hasUsersWorkingHoursColumn($pdo);
        $hasCommissionEligibleCol = self::hasUsersCommissionEligibleColumn($pdo);
        if (! $hasStaffTypeCol) {
            session_flash('errors', ['Staff type column is missing in database. Please run latest schema migration/update, then try again.']);

            return redirect(url('/tenant/staff'));
        }
        if (! $hasWorkDaysCsvCol || ! $hasWorkingHoursCol || ! $hasCommissionEligibleCol) {
            session_flash('errors', ['Working days column is missing in database. Please run latest schema migration/update, then try again.']);

            return redirect(url('/tenant/staff'));
        }
        $modulesPatch = '';
        $overtimePatch = self::hasUsersOvertimeRateColumn($pdo) ? ', overtime_rate_per_hour = ?' : '';
        $workDaysPatch = self::hasUsersWorkDaysCsvColumn($pdo) ? ', work_days_csv = ?' : '';
        $workingHoursPatch = self::hasUsersWorkingHoursColumn($pdo) ? ', working_hours_per_day = ?' : '';
        $commissionEligiblePatch = self::hasUsersCommissionEligibleColumn($pdo) ? ', commission_eligible = ?' : '';
        $hasOvertime = self::hasUsersOvertimeRateColumn($pdo);
        $hasWorkDays = self::hasUsersWorkDaysCsvColumn($pdo);
        $params = [$dayRate, $staffType];
        if ($hasOvertime) {
            $params[] = $overtimeRatePerHour;
        }
        if ($hasWorkDays) {
            $params[] = $workDaysCsv;
        }
        if ($hasWorkingHoursCol) {
            $params[] = $workingHoursPerDay;
        }
        if ($hasCommissionEligibleCol) {
            $params[] = $commissionEligible;
        }
        $params[] = $targetId;
        $params[] = $tenantId;
        if ($this->isTimeOnlyStaffType($staffType) && Auth::hasModulePermissionsColumn()) {
            $modulesPatch = ', module_permissions = ?';
            array_splice($params, -2, 0, [json_encode([], JSON_UNESCAPED_UNICODE)]);
        }
        $pdo->prepare("UPDATE users SET day_rate = ?, staff_type = ?{$overtimePatch}{$workDaysPatch}{$workingHoursPatch}{$commissionEligiblePatch}{$modulesPatch}, updated_at = NOW() WHERE id = ? AND tenant_id = ?")
            ->execute($params);

        session_flash('status', 'Compensation settings updated.');

        return redirect(url('/tenant/staff'));
    }

    public function updateModules(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $targetId = (int) $id;

        if (! Auth::hasModulePermissionsColumn()) {
            session_flash('errors', ['Per-staff modules require the database column module_permissions. Run the SQL in database/add_user_module_permissions.sql on your database, then refresh.']);

            return redirect(url('/tenant/staff'));
        }

        $modulesRaw = $request->input('modules', []);
        $optionalPicked = is_array($modulesRaw) ? StaffModules::sanitizeRequested($modulesRaw) : [];
        $modules = StaffModules::mergeRequiredBaseline($optionalPicked);

        $pdo = App::db();
        $staffTypeSelect = self::hasUsersStaffTypeColumn($pdo) ? 'staff_type' : "'full_time' AS staff_type";
        $st = $pdo->prepare("SELECT id, role, name, email, {$staffTypeSelect} FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
        $st->execute([$targetId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row || ($row['role'] ?? '') !== 'cashier') {
            session_flash('errors', ['Cashier not found.']);

            return redirect(url('/tenant/staff'));
        }
        if ($this->isTimeOnlyStaffType($this->normalizeStaffType((string) ($row['staff_type'] ?? 'full_time')))) {
            session_flash('errors', ['Utility/Driver accounts are time-in/time-out only. Module settings are disabled.']);

            return redirect(url('/tenant/staff'));
        }

        $cashierName = trim((string) ($row['name'] ?? ''));
        $cashierEmail = strtolower(trim((string) ($row['email'] ?? '')));

        $json = json_encode($modules, JSON_UNESCAPED_UNICODE);
        $pdo->prepare('UPDATE users SET module_permissions = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ? AND role = ?')
            ->execute([$json, $targetId, $tenantId, 'cashier']);

        if ($cashierEmail !== '') {
            $desc = $cashierName !== ''
                ? sprintf('Updated modules for cashier %s (%s)', $cashierName, $cashierEmail)
                : sprintf('Updated modules for cashier %s', $cashierEmail);
        } else {
            $desc = sprintf('Updated modules for cashier user #%d', $targetId);
        }

        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'staff',
            'update_cashier_modules',
            $request,
            $desc,
            [
                'cashier_user_id' => $targetId,
                'cashier_email' => $cashierEmail !== '' ? $cashierEmail : null,
                'cashier_name' => $cashierName !== '' ? $cashierName : null,
                'modules' => $modules,
            ]
        );

        session_flash('status', 'Staff modules updated.');

        return redirect(url('/tenant/staff'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $targetId = (int) $id;

        if ($targetId === (int) $user['id']) {
            session_flash('errors', ['You cannot remove your own account.']);

            return redirect(url('/tenant/staff'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id, role, name, email FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$targetId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            session_flash('errors', ['User not found.']);

            return redirect(url('/tenant/staff'));
        }
        if (($row['role'] ?? '') !== 'cashier') {
            session_flash('errors', ['You can only remove cashier accounts.']);

            return redirect(url('/tenant/staff'));
        }

        $pdo->prepare('DELETE FROM users WHERE id = ? AND tenant_id = ? AND role = ?')
            ->execute([$targetId, $tenantId, 'cashier']);

        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'staff',
            'remove_cashier',
            $request,
            sprintf('Removed cashier: %s (%s)', (string) ($row['name'] ?? ''), (string) ($row['email'] ?? '')),
            ['removed_user_id' => $targetId]
        );

        session_flash('status', 'Cashier removed.');

        return redirect(url('/tenant/staff'));
    }

    private function getBranchBoolConfig(PDO $pdo, int $tenantId, string $column, bool $default): bool
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasBranchConfigColumn($pdo, $column)) {
            return $default;
        }
        try {
            $st = $pdo->prepare('SELECT `'.$column.'` FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $value = $st->fetchColumn();
            if ($value === false) {
                return $default;
            }
            return (int) $value === 1;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function persistBranchBoolConfig(PDO $pdo, int $tenantId, string $column, bool $enabled): void
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasBranchConfigColumn($pdo, $column)) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, `'.$column.'`, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `'.$column.'` = VALUES(`'.$column.'`), updated_at = NOW()'
        )->execute([$tenantId, $enabled ? 1 : 0]);
    }

    private function getBranchIntConfig(PDO $pdo, int $tenantId, string $column, int $default): int
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasBranchConfigColumn($pdo, $column)) {
            return $default;
        }
        try {
            $st = $pdo->prepare('SELECT `'.$column.'` FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $value = $st->fetchColumn();
            if ($value === false) {
                return $default;
            }
            return (int) $value;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function getBranchFloatConfig(PDO $pdo, int $tenantId, string $column, float $default): float
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasBranchConfigColumn($pdo, $column)) {
            return $default;
        }
        try {
            $st = $pdo->prepare('SELECT `'.$column.'` FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $value = $st->fetchColumn();
            if ($value === false) {
                return $default;
            }
            return (float) $value;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function persistBranchIntConfig(PDO $pdo, int $tenantId, string $column, int $value): void
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasBranchConfigColumn($pdo, $column)) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, `'.$column.'`, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `'.$column.'` = VALUES(`'.$column.'`), updated_at = NOW()'
        )->execute([$tenantId, $value]);
    }

    private function persistBranchFloatConfig(PDO $pdo, int $tenantId, string $column, float $value): void
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column) || ! $this->hasBranchConfigColumn($pdo, $column)) {
            return;
        }
        $pdo->prepare(
            'INSERT INTO laundry_branch_configs (tenant_id, `'.$column.'`, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `'.$column.'` = VALUES(`'.$column.'`), updated_at = NOW()'
        )->execute([$tenantId, $value]);
    }

    private function hasBranchConfigColumn(PDO $pdo, string $column): bool
    {
        if (! preg_match('/^[a-z_]+$/', $column)) {
            return false;
        }
        try {
            $st = $pdo->prepare(
                'SELECT 1
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = "laundry_branch_configs"
                   AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $st->execute([$column]);
            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeStaffType(string $raw): string
    {
        $v = strtolower(trim($raw));
        if (in_array($v, ['utility', 'driver', 'part_timer', 'full_time'], true)) {
            return $v;
        }

        return 'full_time';
    }

    private function isTimeOnlyStaffType(string $staffType): bool
    {
        return in_array($staffType, ['utility', 'driver'], true);
    }

    private function workDaysCsvFromRequest(Request $request): string
    {
        $raw = $request->input('work_days', []);
        if (! is_array($raw)) {
            return '1,2,3,4,5,6,7';
        }
        $days = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n >= 1 && $n <= 7) {
                $days[] = $n;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);

        return $days === [] ? '1,2,3,4,5,6,7' : implode(',', $days);
    }
}
