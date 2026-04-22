<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\LaundrySchema;
use App\Core\Request;
use App\Core\Response;
use App\Services\BranchService;

final class BranchController
{
    private function ensureTenantAdmin(array $u): ?Response
    {
        if (($u['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden.', 403);
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            return new Response('Forbidden.', 403);
        }

        return null;
    }

    private function ensureMainBranchTenantAdmin(array $u): ?Response
    {
        $baseGate = $this->ensureTenantAdmin($u);
        if ($baseGate instanceof Response) {
            return $baseGate;
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $svc = new BranchService();
        $tenant = $svc->getTenant($tenantId);
        if (! $tenant || ! (bool) ($tenant['is_main_branch'] ?? false)) {
            return new Response('Forbidden.', 403);
        }

        return null;
    }

    public function index(Request $request): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $svc = new BranchService();
        $activeTenant = $svc->getTenant($tenantId);
        $isMainBranchContext = (bool) ($activeTenant['is_main_branch'] ?? false);
        $root = $svc->getTenant($svc->getGroupRootTenantId($tenantId));
        $branches = $svc->listBranches($tenantId);

        return view_page('Branch Management', 'tenant.branches.index', [
            'root_tenant' => $root,
            'current_tenant_id' => $tenantId,
            'branches' => $branches,
            'branch_limit' => $svc->getBranchLimit($tenantId),
            'clone_defaults' => [],
            'premium_trial_browse_lock' => false,
            'machine_assignment_enabled' => $this->isMachineAssignmentEnabled($tenantId),
            'fold_service_amount' => $this->getFoldServiceAmount($tenantId),
            'fold_commission_target' => $this->getFoldCommissionTarget($tenantId),
            'payroll_cutoff_days' => $this->getPayrollCutoffDays($tenantId),
            'payroll_hours_per_day' => $this->getPayrollHoursPerDay($tenantId),
            'activate_commission' => $this->getBoolConfig($tenantId, 'activate_commission', false),
            'daily_load_quota' => $this->getIntConfig($tenantId, 'daily_load_quota', 0),
            'commission_rate_per_load' => $this->getFloatConfig($tenantId, 'commission_rate_per_load', 0.0),
            'activate_ot_incentives' => $this->getBoolConfig($tenantId, 'activate_ot_incentives', false),
            'is_main_branch_context' => $isMainBranchContext,
        ]);
    }

    public function store(Request $request): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureMainBranchTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $svc = new BranchService();
        try {
            $newId = $svc->createBranch(
                $tenantId,
                (string) $request->input('name'),
                (string) $request->input('slug'),
                (int) $request->input('source_tenant_id', $tenantId),
                $this->extractCloneOptions($request)
            );
            $this->log($request, 'branch_create_self_service', 'Store owner created branch', [
                'tenant_id' => $tenantId,
                'new_tenant_id' => $newId,
            ]);
            session_flash('status', 'Branch created successfully.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(url('/dashboard'));
    }

    public function toggleActive(Request $request, string $branchId): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureMainBranchTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $targetId = (int) $branchId;
        $active = $request->boolean('active');
        $svc = new BranchService();
        try {
            $svc->toggleBranchActive($tenantId, $targetId, $active);
            $this->log($request, 'branch_toggle_self_service', 'Store owner toggled branch status', [
                'tenant_id' => $tenantId,
                'branch_id' => $targetId,
                'active' => $active,
            ]);
            session_flash('status', $active ? 'Branch opened.' : 'Branch closed.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('tenant.branches.index'));
    }

    public function setMain(Request $request, string $branchId): Response
    {
        $u = Auth::user();
        if (! $u || ($u['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden.', 403);
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $targetId = (int) $branchId;
        $svc = new BranchService();
        try {
            $svc->setMainBranch($tenantId, $targetId);
            // After changing main branch, switch active context to the new main
            // so branch management page remains accessible.
            session_set('active_tenant_id', $targetId);
            $this->log($request, 'branch_set_main_self_service', 'Store owner set main branch', [
                'tenant_id' => $tenantId,
                'branch_id' => $targetId,
            ]);
            session_flash('status', 'Main branch updated.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('tenant.branches.index'));
    }

    public function updateLaundryConfig(Request $request): Response
    {
        $u = Auth::user();
        if (! $u) {
            return new Response('Forbidden.', 403);
        }
        $gate = $this->ensureTenantAdmin($u);
        if ($gate instanceof Response) {
            return $gate;
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $enabled = $request->boolean('machine_assignment_enabled');
        $foldServiceAmount = max(0.0, (float) $request->input('fold_service_amount', 0));
        $foldCommissionTarget = strtolower(trim((string) $request->input('fold_commission_target', 'staff')));
        $payrollCutoffDays = max(1, min(31, (int) $request->input('payroll_cutoff_days', 15)));
        $payrollHoursPerDay = max(1.0, min(24.0, (float) $request->input('payroll_hours_per_day', 8)));
        $activateCommission = $request->boolean('activate_commission');
        $dailyLoadQuota = max(0, (int) $request->input('daily_load_quota', 0));
        $commissionRatePerLoad = max(0.0, (float) $request->input('commission_rate_per_load', 0));
        $activateOtIncentives = $request->boolean('activate_ot_incentives');
        if (! in_array($foldCommissionTarget, ['staff', 'branch'], true)) {
            $foldCommissionTarget = 'staff';
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $this->ensureLaundryBranchConfigColumns($pdo);
            $hasFoldAmount = $this->hasLaundryBranchConfigColumn($pdo, 'fold_service_amount');
            $hasFoldTarget = $this->hasLaundryBranchConfigColumn($pdo, 'fold_commission_target');
            $hasCutoffDays = $this->hasLaundryBranchConfigColumn($pdo, 'payroll_cutoff_days');
            $hasHoursPerDay = $this->hasLaundryBranchConfigColumn($pdo, 'payroll_hours_per_day');
            $hasActivateCommission = $this->hasLaundryBranchConfigColumn($pdo, 'activate_commission');
            $hasDailyQuota = $this->hasLaundryBranchConfigColumn($pdo, 'daily_load_quota');
            $hasCommissionRate = $this->hasLaundryBranchConfigColumn($pdo, 'commission_rate_per_load');
            $hasActivateOt = $this->hasLaundryBranchConfigColumn($pdo, 'activate_ot_incentives');
            $insertColumns = ['tenant_id', 'machine_assignment_enabled'];
            $insertValues = ['?', '?'];
            $updateParts = ['machine_assignment_enabled = VALUES(machine_assignment_enabled)'];
            $params = [$tenantId, $enabled ? 1 : 0];
            if ($hasFoldAmount) {
                $insertColumns[] = 'fold_service_amount';
                $insertValues[] = '?';
                $updateParts[] = 'fold_service_amount = VALUES(fold_service_amount)';
                $params[] = $foldServiceAmount;
            }
            if ($hasFoldTarget) {
                $insertColumns[] = 'fold_commission_target';
                $insertValues[] = '?';
                $updateParts[] = 'fold_commission_target = VALUES(fold_commission_target)';
                $params[] = $foldCommissionTarget;
            }
            if ($hasCutoffDays) {
                $insertColumns[] = 'payroll_cutoff_days';
                $insertValues[] = '?';
                $updateParts[] = 'payroll_cutoff_days = VALUES(payroll_cutoff_days)';
                $params[] = $payrollCutoffDays;
            }
            if ($hasHoursPerDay) {
                $insertColumns[] = 'payroll_hours_per_day';
                $insertValues[] = '?';
                $updateParts[] = 'payroll_hours_per_day = VALUES(payroll_hours_per_day)';
                $params[] = $payrollHoursPerDay;
            }
            if ($hasActivateCommission) {
                $insertColumns[] = 'activate_commission';
                $insertValues[] = '?';
                $updateParts[] = 'activate_commission = VALUES(activate_commission)';
                $params[] = $activateCommission ? 1 : 0;
            }
            if ($hasDailyQuota) {
                $insertColumns[] = 'daily_load_quota';
                $insertValues[] = '?';
                $updateParts[] = 'daily_load_quota = VALUES(daily_load_quota)';
                $params[] = $activateCommission ? $dailyLoadQuota : 0;
            }
            if ($hasCommissionRate) {
                $insertColumns[] = 'commission_rate_per_load';
                $insertValues[] = '?';
                $updateParts[] = 'commission_rate_per_load = VALUES(commission_rate_per_load)';
                $params[] = $activateCommission ? $commissionRatePerLoad : 0.0;
            }
            if ($hasActivateOt) {
                $insertColumns[] = 'activate_ot_incentives';
                $insertValues[] = '?';
                $updateParts[] = 'activate_ot_incentives = VALUES(activate_ot_incentives)';
                $params[] = $activateOtIncentives ? 1 : 0;
            }
            $sql = sprintf(
                'INSERT INTO laundry_branch_configs (%s, created_at, updated_at)
                 VALUES (%s, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE %s, updated_at = NOW()',
                implode(', ', $insertColumns),
                implode(', ', $insertValues),
                implode(', ', $updateParts)
            );
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $this->log($request, 'branch_laundry_config_update', 'Store owner updated branch laundry config', [
                'tenant_id' => $tenantId,
                'machine_assignment_enabled' => $enabled,
                'fold_service_amount' => $foldServiceAmount,
                'fold_commission_target' => $foldCommissionTarget,
                'payroll_cutoff_days' => $payrollCutoffDays,
                'payroll_hours_per_day' => $payrollHoursPerDay,
                'activate_commission' => $activateCommission,
                'daily_load_quota' => $dailyLoadQuota,
                'commission_rate_per_load' => $commissionRatePerLoad,
                'activate_ot_incentives' => $activateOtIncentives,
            ]);
            if (! $hasFoldAmount || ! $hasFoldTarget || ! $hasCutoffDays || ! $hasHoursPerDay || ! $hasActivateCommission || ! $hasDailyQuota || ! $hasCommissionRate || ! $hasActivateOt) {
                session_flash('errors', ['Branch config was partially updated, but some payroll/commission columns are missing in DB. Run latest migrations/export schema to fully enable all settings.']);
            } else {
                session_flash('status', 'Branch laundry config updated.');
            }
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(route('tenant.branches.index'));
    }

    public function switch(Request $request): Response
    {
        $u = Auth::user();
        if (! $u || ($u['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden.', 403);
        }
        $tenantId = (int) ($u['tenant_id'] ?? 0);
        $targetId = (int) $request->input('branch_id', 0);
        $svc = new BranchService();
        try {
            $target = $svc->getTenant($targetId);
            if (! $target) {
                throw new \RuntimeException('Branch not found.');
            }
            $actorRoot = $svc->getGroupRootTenantId($tenantId);
            $targetRoot = $svc->getGroupRootTenantId($targetId);
            if ($actorRoot !== $targetRoot) {
                throw new \RuntimeException('Branch is outside your account group.');
            }
            if (! (bool) ($target['is_active'] ?? false)) {
                throw new \RuntimeException('Cannot switch to a closed branch.');
            }
            session_set('active_tenant_id', $targetId);
            $this->log($request, 'branch_switch_self_service', 'Store owner switched active branch context', [
                'tenant_id' => $tenantId,
                'target_branch_id' => $targetId,
            ]);
            session_flash('status', 'Active branch switched.');
        } catch (\Throwable $e) {
            session_flash('errors', [$e->getMessage()]);
        }

        return redirect(url('/dashboard'));
    }

    /** @return array{categories:bool,ingredients:bool,products:bool,requirements:bool} */
    private function extractCloneOptions(Request $request): array
    {
        $raw = $request->input('clone', []);
        $list = is_array($raw) ? array_map('strval', $raw) : [];

        return [
            'categories' => in_array('categories', $list, true),
            'ingredients' => in_array('ingredients', $list, true),
            'products' => in_array('products', $list, true),
            'requirements' => in_array('requirements', $list, true),
        ];
    }

    private function isMachineAssignmentEnabled(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return true;
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $st = $pdo->prepare('SELECT machine_assignment_enabled FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return true;
            }

            return (int) ($row['machine_assignment_enabled'] ?? 1) === 1;
        } catch (\Throwable) {
            return true;
        }
    }

    private function getFoldServiceAmount(int $tenantId): float
    {
        if ($tenantId < 1) {
            return 0.0;
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $st = $pdo->prepare('SELECT fold_service_amount FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return 0.0;
            }

            return max(0.0, (float) ($row['fold_service_amount'] ?? 0));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function getFoldCommissionTarget(int $tenantId): string
    {
        if ($tenantId < 1) {
            return 'staff';
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $st = $pdo->prepare('SELECT fold_commission_target FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            $v = strtolower(trim((string) ($row['fold_commission_target'] ?? 'staff')));

            return in_array($v, ['staff', 'branch'], true) ? $v : 'staff';
        } catch (\Throwable) {
            return 'staff';
        }
    }

    private function getPayrollCutoffDays(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 15;
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $st = $pdo->prepare('SELECT payroll_cutoff_days FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return max(1, min(31, (int) ($row['payroll_cutoff_days'] ?? 15)));
        } catch (\Throwable) {
            return 15;
        }
    }

    private function getPayrollHoursPerDay(int $tenantId): float
    {
        if ($tenantId < 1) {
            return 8.0;
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            $st = $pdo->prepare('SELECT payroll_hours_per_day FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return max(1.0, min(24.0, (float) ($row['payroll_hours_per_day'] ?? 8)));
        } catch (\Throwable) {
            return 8.0;
        }
    }

    private function ensureLaundryBranchConfigColumns(\PDO $pdo): void
    {
        if (! $this->hasLaundryBranchConfigColumn($pdo, 'fold_service_amount')) {
            try {
                $pdo->exec('ALTER TABLE laundry_branch_configs ADD COLUMN fold_service_amount DECIMAL(16,4) NOT NULL DEFAULT 10 AFTER machine_assignment_enabled');
            } catch (\Throwable) {
            }
        }
        if (! $this->hasLaundryBranchConfigColumn($pdo, 'fold_commission_target')) {
            try {
                $pdo->exec('ALTER TABLE laundry_branch_configs ADD COLUMN fold_commission_target VARCHAR(20) NOT NULL DEFAULT "staff" AFTER fold_service_amount');
            } catch (\Throwable) {
            }
        }
        if (! $this->hasLaundryBranchConfigColumn($pdo, 'payroll_cutoff_days')) {
            try {
                $pdo->exec('ALTER TABLE laundry_branch_configs ADD COLUMN payroll_cutoff_days INT NOT NULL DEFAULT 15 AFTER fold_commission_target');
            } catch (\Throwable) {
            }
        }
        if (! $this->hasLaundryBranchConfigColumn($pdo, 'payroll_hours_per_day')) {
            try {
                $pdo->exec('ALTER TABLE laundry_branch_configs ADD COLUMN payroll_hours_per_day DECIMAL(6,2) NOT NULL DEFAULT 8.00 AFTER payroll_cutoff_days');
            } catch (\Throwable) {
            }
        }
        foreach ([
            'activate_commission' => 'ALTER TABLE laundry_branch_configs ADD COLUMN activate_commission TINYINT(1) NOT NULL DEFAULT 0 AFTER payroll_hours_per_day',
            'daily_load_quota' => 'ALTER TABLE laundry_branch_configs ADD COLUMN daily_load_quota INT NOT NULL DEFAULT 0 AFTER activate_commission',
            'commission_rate_per_load' => 'ALTER TABLE laundry_branch_configs ADD COLUMN commission_rate_per_load DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER daily_load_quota',
            'activate_ot_incentives' => 'ALTER TABLE laundry_branch_configs ADD COLUMN activate_ot_incentives TINYINT(1) NOT NULL DEFAULT 0 AFTER commission_rate_per_load',
        ] as $column => $sql) {
            if (! $this->hasLaundryBranchConfigColumn($pdo, $column)) {
                try {
                    $pdo->exec($sql);
                } catch (\Throwable) {
                }
            }
        }
    }

    private function getBoolConfig(int $tenantId, string $column, bool $default): bool
    {
        return (bool) $this->getScalarConfig($tenantId, $column, $default ? 1 : 0);
    }

    private function getIntConfig(int $tenantId, string $column, int $default): int
    {
        return max(0, (int) $this->getScalarConfig($tenantId, $column, $default));
    }

    private function getFloatConfig(int $tenantId, string $column, float $default): float
    {
        return max(0.0, (float) $this->getScalarConfig($tenantId, $column, $default));
    }

    private function getScalarConfig(int $tenantId, string $column, mixed $default): mixed
    {
        if ($tenantId < 1 || ! preg_match('/^[a-z_]+$/', $column)) {
            return $default;
        }
        try {
            $pdo = App::db();
            LaundrySchema::ensure($pdo);
            if (! $this->hasLaundryBranchConfigColumn($pdo, $column)) {
                return $default;
            }
            $st = $pdo->prepare('SELECT `'.$column.'` FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);
            $v = $st->fetchColumn();

            return $v === false ? $default : $v;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function hasLaundryBranchConfigColumn(\PDO $pdo, string $column): bool
    {
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_branch_configs` LIKE '{$column}'");
            return $st !== false && $st->fetch(\PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string,mixed> $meta */
    private function log(Request $request, string $action, string $description, array $meta): void
    {
        $u = Auth::user();
        if (! $u) {
            return;
        }
        ActivityLogger::log(
            (int) ($u['tenant_id'] ?? 0),
            (int) $u['id'],
            (string) ($u['role'] ?? 'tenant_admin'),
            'branches',
            $action,
            $request,
            $description,
            $meta
        );
    }
}
