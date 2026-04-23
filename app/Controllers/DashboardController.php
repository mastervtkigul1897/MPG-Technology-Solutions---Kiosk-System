<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\LaundrySchema;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect(url('/login'));
        }

        if ($user['role'] === 'super_admin') {
            $pdo = App::db();
            $tenants = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();

            return view_page('Dashboard', 'dashboard', [
                'is_super' => true,
                'stats' => [
                    'tenants_count' => $tenants,
                ],
            ]);
        }

        if (($user['role'] ?? '') === 'cashier' && Auth::canAccessModule($user, 'pos')) {
            return redirect(route('tenant.staff-portal.index'));
        }

        $tid = $user['tenant_id'] ?? null;
        if (($user['role'] === 'tenant_admin' || $user['role'] === 'cashier')
            && ($tid === null || $tid === '' || (int) $tid === 0)) {
            Auth::logout();
            session_flash('errors', ['Your account is not assigned to a store. Contact the platform administrator.']);

            return redirect(url('/login'));
        }

        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        LaundrySchema::ensure($pdo);
        $this->ensureTimeLogPhotoColumns($pdo);
        $laundryStatusTrackingEnabled = $this->isLaundryStatusTrackingEnabled($pdo, $tenantId);
        $today = date('Y-m-d');
        $rangeStart = $today.' 00:00:00';
        $rangeEnd = $today.' 23:59:59';
        $salesToday = (float) $this->scalar(
            $pdo,
            'SELECT COALESCE(SUM(total_amount),0)
             FROM laundry_orders
             WHERE tenant_id = ?
               AND DATE(created_at) = ?
               AND COALESCE(is_void, 0) = 0
               AND status <> "void"
               AND (
                   status = "paid"
                   OR (status = "completed" AND payment_status = "paid")
               )',
            [$tenantId, $today]
        );
        $salesMonth = $salesToday;
        $salesTotal = $salesToday;
        $expensesToday = (float) $this->scalar($pdo, 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = "manual" AND created_at BETWEEN ? AND ?', [$tenantId, $rangeStart, $rangeEnd]);
        $refundsToday = 0.0;
        $discountsToday = 0.0;
        $foldServiceAmount = $this->getBranchFoldServiceAmount($pdo, $tenantId);
        $foldCommissionTarget = $this->getBranchFoldCommissionTarget($pdo, $tenantId);
        $foldOrdersToday = (int) $this->scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM laundry_orders
             WHERE tenant_id = ?
               AND include_fold_service = 1
               AND DATE(created_at) = ?
               AND COALESCE(is_void, 0) = 0
               AND status <> "void"
               AND COALESCE(is_free, 0) = 0
               AND COALESCE(is_reward, 0) = 0
               AND (
                   status = "paid"
                   OR (status = "completed" AND payment_status = "paid")
               )',
            [$tenantId, $today]
        );
        $foldAmountToday = $foldCommissionTarget === 'branch'
            ? ($foldServiceAmount * $foldOrdersToday)
            : 0.0;
        $loadStatusRows = $this->fetchLoadStatusSummary($pdo, $tenantId, $today, $laundryStatusTrackingEnabled);
        $serviceModeSummary = $this->fetchServiceModeSummary($pdo, $tenantId, $today);
        $machineCreditRows = $this->fetchMachineCreditBalances($pdo, $tenantId);
        $cashAvailable = ($salesToday + $foldAmountToday) - $expensesToday;
        $trendLabels = [];
        $trendSales = [];
        $trendExpenses = [];
        $trendLabels[] = date('M j', strtotime($today));
        $trendSales[] = $salesToday;
        $trendExpenses[] = $expensesToday;

        $stPayment = $pdo->prepare(
            'SELECT LOWER(TRIM(COALESCE(payment_method, "cash"))) AS payment_method, COALESCE(SUM(total_amount),0) AS total
             FROM laundry_orders
             WHERE tenant_id = ?
               AND DATE(created_at) = ?
               AND COALESCE(is_void, 0) = 0
               AND status <> "void"
               AND (
                   status = "paid"
                   OR (status = "completed" AND payment_status = "paid")
               )
             GROUP BY payment_method'
        );
        $stPayment->execute([$tenantId, $today]);
        $paymentBreakdown = [];
        foreach ($stPayment->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $paymentBreakdown[(string) ($row['payment_method'] ?? 'cash')] = (float) ($row['total'] ?? 0);
        }

        $stTop = $pdo->prepare(
            'SELECT c.name, COUNT(o.id) AS frequency, COALESCE(SUM(o.total_amount),0) AS total_spending
             FROM laundry_customers c
             LEFT JOIN laundry_orders o ON o.customer_id = c.id AND o.tenant_id = c.tenant_id
                AND COALESCE(o.is_void, 0) = 0
                AND o.status <> "void"
                AND (
                    o.status = "paid"
                    OR (o.status = "completed" AND o.payment_status = "paid")
                )
                AND o.created_at BETWEEN ? AND ?
             WHERE c.tenant_id = ?
             GROUP BY c.id
             ORDER BY frequency DESC, total_spending DESC, c.name ASC
             LIMIT 10'
        );
        $stTop->execute([$rangeStart, $rangeEnd, $tenantId]);
        $topCustomers = $stTop->fetchAll(PDO::FETCH_ASSOC);

        $stLow = $pdo->prepare(
            'SELECT name, stock_quantity, low_stock_threshold
             FROM laundry_inventory_items
             WHERE tenant_id = ? AND stock_quantity <= low_stock_threshold
             ORDER BY stock_quantity ASC
             LIMIT 10'
        );
        $stLow->execute([$tenantId]);
        $lowStockItems = $stLow->fetchAll(PDO::FETCH_ASSOC);

        $monthDay = date('m-d');
        $stBdayToday = $pdo->prepare('SELECT name, birthday FROM laundry_customers WHERE tenant_id = ? AND DATE_FORMAT(birthday, "%m-%d") = ? ORDER BY name');
        $stBdayToday->execute([$tenantId, $monthDay]);
        $birthdaysToday = $stBdayToday->fetchAll(PDO::FETCH_ASSOC);

        $currentUserId = (int) ($user['id'] ?? 0);
        $clockOpenSt = $pdo->prepare(
            'SELECT id, clock_in_at, clock_out_at
             FROM laundry_time_logs
             WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = ? AND clock_out_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $clockOpenSt->execute([$tenantId, $currentUserId, $today]);
        $clockOpen = $clockOpenSt->fetch(PDO::FETCH_ASSOC) ?: null;

        $clockRowsSt = $pdo->prepare(
            'SELECT tl.user_id, u.name AS staff_name, tl.clock_in_at, tl.clock_out_at, tl.clock_in_photo_path, tl.clock_out_photo_path, tl.note
             FROM laundry_time_logs tl
             INNER JOIN users u ON u.id = tl.user_id
             WHERE tl.tenant_id = ? AND DATE(tl.clock_in_at) = ?
             ORDER BY tl.clock_in_at ASC'
        );
        $clockRowsSt->execute([$tenantId, $today]);
        $clockRows = $clockRowsSt->fetchAll(PDO::FETCH_ASSOC);

        return view_page('Dashboard', 'dashboard', [
            'is_super' => false,
            'stats' => [
                'sales_today' => $salesToday,
                'sales_month' => $salesMonth,
                'sales_total' => $salesTotal,
                'expenses_today' => $expensesToday,
                'refunds_today' => $refundsToday,
                'discounts_today' => $discountsToday,
                'fold_amount_today' => $foldAmountToday,
                'fold_service_amount' => $foldServiceAmount,
                'fold_commission_target' => $foldCommissionTarget,
                'cash_available' => $cashAvailable,
            ],
            'top_customers' => $topCustomers,
            'low_stock_items' => $lowStockItems,
            'birthdays_today' => $birthdaysToday,
            'birthdays_this_month' => $birthdaysToday,
            'sales_trend' => [
                'labels' => $trendLabels,
                'sales' => $trendSales,
                'expenses' => $trendExpenses,
            ],
            'payment_breakdown' => $paymentBreakdown,
            'load_status_summary' => $loadStatusRows,
            'laundry_status_tracking_enabled' => $laundryStatusTrackingEnabled,
            'service_mode_summary' => $serviceModeSummary,
            'machine_credit_balances' => $machineCreditRows,
            'clock_open' => $clockOpen,
            'clock_rows_today' => $clockRows,
            'free_dashboard_limited' => Auth::isTenantFreePlanRestricted($user),
        ]);
    }

    /**
     * @return array<string,array{label:string,count:int,amount:float}>
     */
    private function fetchLoadStatusSummary(PDO $pdo, int $tenantId, string $today, bool $trackingEnabled): array
    {
        $summary = $trackingEnabled
            ? [
                'pending' => ['label' => 'Pending', 'count' => 0, 'amount' => 0.0],
                'washing_drying' => ['label' => 'Washing - Drying', 'count' => 0, 'amount' => 0.0],
                'open_ticket' => ['label' => 'Unpaid', 'count' => 0, 'amount' => 0.0],
                'paid' => ['label' => 'Paid', 'count' => 0, 'amount' => 0.0],
                'completed' => ['label' => 'Completed', 'count' => 0, 'amount' => 0.0],
            ]
            : [
                'open_ticket' => ['label' => 'Unpaid', 'count' => 0, 'amount' => 0.0],
                'paid' => ['label' => 'Paid', 'count' => 0, 'amount' => 0.0],
                'completed' => ['label' => 'Completed', 'count' => 0, 'amount' => 0.0],
            ];
        try {
            $st = $pdo->prepare(
                'SELECT
                    CASE
                        WHEN o.status = "pending" THEN "pending"
                        WHEN o.status IN ("washing_drying", "running") THEN "washing_drying"
                        WHEN o.status = "open_ticket" THEN "open_ticket"
                        WHEN o.status = "paid" THEN "paid"
                        WHEN o.status = "completed" AND LOWER(TRIM(COALESCE(o.payment_status, "unpaid"))) = "paid" THEN "completed"
                        WHEN o.status = "completed" THEN "open_ticket"
                        ELSE ""
                    END AS bucket,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(o.total_amount), 0) AS total
                 FROM laundry_orders o
                 WHERE o.tenant_id = ?
                   AND COALESCE(o.is_void, 0) = 0
                   AND o.status <> "void"
                   AND (
                        o.status IN ("pending", "washing_drying", "running", "open_ticket")
                        OR (o.status IN ("paid", "completed") AND DATE(o.created_at) = ?)
                   )
                 GROUP BY bucket'
            );
            $st->execute([$tenantId, $today]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $bucket = (string) ($row['bucket'] ?? '');
                if (! $trackingEnabled) {
                    if ($bucket === 'pending' || $bucket === 'washing_drying') {
                        $bucket = 'open_ticket';
                    }
                }
                if (! isset($summary[$bucket])) {
                    continue;
                }
                $summary[$bucket]['count'] = (int) ($row['cnt'] ?? 0);
                $summary[$bucket]['amount'] = (float) ($row['total'] ?? 0);
            }
        } catch (\Throwable) {
        }

        return $summary;
    }

    private function isLaundryStatusTrackingEnabled(PDO $pdo, int $tenantId): bool
    {
        if ($tenantId < 1) {
            return true;
        }
        try {
            $st = $pdo->prepare(
                'SELECT laundry_status_tracking_enabled
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return true;
            }

            return (int) ($row['laundry_status_tracking_enabled'] ?? 1) === 1;
        } catch (\Throwable) {
            // Fail-safe aligned with POS/load board behavior.
            return false;
        }
    }

    /**
     * @return array<string,array{label:string,count:int,items:list<string>,machines:list<string>}>
     */
    private function fetchServiceModeSummary(PDO $pdo, int $tenantId, string $today): array
    {
        $summary = [
            'free' => ['label' => 'Free', 'count' => 0, 'items' => [], 'machines' => []],
            'reward' => ['label' => 'Reward', 'count' => 0, 'items' => [], 'machines' => []],
        ];

        try {
            $st = $pdo->prepare(
                'SELECT
                    CASE
                        WHEN COALESCE(o.is_reward, 0) = 1 THEN "reward"
                        ELSE "free"
                    END AS mode_bucket,
                    d.name AS det_name,
                    f.name AS fab_name,
                    b.name AS bleach_name,
                    wm.machine_label AS washer_label,
                    dm.machine_label AS dryer_label,
                    lm.machine_label AS legacy_label
                 FROM laundry_orders
                 o
                 LEFT JOIN laundry_inventory_items d
                    ON d.tenant_id = o.tenant_id
                   AND d.id = o.inclusion_detergent_item_id
                 LEFT JOIN laundry_inventory_items f
                    ON f.tenant_id = o.tenant_id
                   AND f.id = o.inclusion_fabcon_item_id
                 LEFT JOIN laundry_inventory_items b
                    ON b.tenant_id = o.tenant_id
                   AND b.id = o.inclusion_bleach_item_id
                 LEFT JOIN laundry_machines wm
                    ON wm.tenant_id = o.tenant_id
                   AND wm.id = o.washer_machine_id
                 LEFT JOIN laundry_machines dm
                    ON dm.tenant_id = o.tenant_id
                   AND dm.id = o.dryer_machine_id
                 LEFT JOIN laundry_machines lm
                    ON lm.tenant_id = o.tenant_id
                   AND lm.id = o.machine_id
                 WHERE tenant_id = ?
                   AND DATE(o.created_at) = ?
                   AND COALESCE(o.is_void, 0) = 0
                   AND o.status <> "void"
                   AND (COALESCE(o.is_free, 0) = 1 OR COALESCE(o.is_reward, 0) = 1)'
            );
            $st->execute([$tenantId, $today]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $bucket = (string) ($row['mode_bucket'] ?? '');
                if (! isset($summary[$bucket])) {
                    continue;
                }
                $summary[$bucket]['count']++;

                foreach (['det_name', 'fab_name', 'bleach_name'] as $itemKey) {
                    $name = trim((string) ($row[$itemKey] ?? ''));
                    if ($name !== '' && ! in_array($name, $summary[$bucket]['items'], true)) {
                        $summary[$bucket]['items'][] = $name;
                    }
                }
                foreach (['washer_label', 'dryer_label', 'legacy_label'] as $machineKey) {
                    $label = trim((string) ($row[$machineKey] ?? ''));
                    if ($label !== '' && ! in_array($label, $summary[$bucket]['machines'], true)) {
                        $summary[$bucket]['machines'][] = $label;
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $summary;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchMachineCreditBalances(PDO $pdo, int $tenantId): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT id, machine_kind, machine_type, machine_code, machine_label, credit_required, credit_balance, status
                 FROM laundry_machines
                 WHERE tenant_id = ?
                 ORDER BY machine_kind ASC, machine_label ASC, machine_code ASC, id ASC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function timeIn(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') === 'super_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $today = date('Y-m-d');
        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        $this->ensureTimeLogPhotoColumns($pdo);
        $clockInPhotoPath = $this->persistAttendancePhoto($request->input('photo_data'), $tenantId, $userId, 'in');
        if ($clockInPhotoPath === null) {
            session_flash('errors', ['Photo is required for time in.']);

            return redirect(url('/dashboard'));
        }

        $st = $pdo->prepare(
            'SELECT id
             FROM laundry_time_logs
             WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = ? AND clock_out_at IS NULL
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId, $today]);
        if ($st->fetch(PDO::FETCH_ASSOC)) {
            session_flash('errors', ['You are already timed in today.']);

            return redirect(url('/dashboard'));
        }

        $pdo->prepare(
            'INSERT INTO laundry_time_logs (tenant_id, user_id, clock_in_at, clock_in_photo_path, created_at, updated_at)
             VALUES (?, ?, NOW(), ?, NOW(), NOW())'
        )->execute([$tenantId, $userId, $clockInPhotoPath]);

        session_flash('success', 'Time in recorded.');

        return redirect(url('/dashboard'));
    }

    public function timeOut(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') === 'super_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $userId = (int) ($user['id'] ?? 0);
        $today = date('Y-m-d');
        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        $this->ensureTimeLogPhotoColumns($pdo);
        $clockOutPhotoPath = $this->persistAttendancePhoto($request->input('photo_data'), $tenantId, $userId, 'out');
        if ($clockOutPhotoPath === null) {
            session_flash('errors', ['Photo is required for time out.']);

            return redirect(url('/dashboard'));
        }

        $st = $pdo->prepare(
            'SELECT id, clock_in_at
             FROM laundry_time_logs
             WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = ? AND clock_out_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId, $today]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            session_flash('errors', ['No active time-in found for today.']);

            return redirect(url('/dashboard'));
        }

        $clockIn = strtotime((string) ($row['clock_in_at'] ?? ''));
        $requiredHours = 8.0;
        $otActive = false;
        try {
            $cfg = $pdo->prepare(
                'SELECT COALESCE(u.working_hours_per_day, bc.payroll_hours_per_day, 8.00) AS hours_per_day,
                        COALESCE(bc.activate_ot_incentives, 0) AS activate_ot_incentives
                 FROM users u
                 LEFT JOIN laundry_branch_configs bc ON bc.tenant_id = u.tenant_id
                 WHERE u.tenant_id = ? AND u.id = ?
                 LIMIT 1'
            );
            $cfg->execute([$tenantId, $userId]);
            $cfgRow = $cfg->fetch(PDO::FETCH_ASSOC);
            if (is_array($cfgRow)) {
                $requiredHours = max(1.0, min(24.0, (float) ($cfgRow['hours_per_day'] ?? 8)));
                $otActive = (int) ($cfgRow['activate_ot_incentives'] ?? 0) === 1;
            }
        } catch (\Throwable) {
            $requiredHours = 8.0;
            $otActive = false;
        }
        $seconds = $clockIn !== false ? max(0, time() - $clockIn) : 0;
        $requiredSeconds = (int) round($requiredHours * 3600);
        $isIncomplete = $seconds > 0 && $seconds < $requiredSeconds;
        $otStatus = ($otActive && ($seconds - $requiredSeconds) >= 1800) ? 'pending' : 'none';
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        $note = $isIncomplete ? sprintf('Incomplete shift: %dh %dm (below %.2f hours).', $hours, $minutes, $requiredHours) : null;

        $pdo->prepare(
            'UPDATE laundry_time_logs
             SET clock_out_at = NOW(), clock_out_photo_path = ?, note = ?, overtime_status = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        )->execute([$clockOutPhotoPath, $note, $otStatus, (int) $row['id'], $tenantId]);

        session_flash('success', $isIncomplete ? 'Time out recorded with incomplete-hours note.' : 'Time out recorded.');

        return redirect(url('/dashboard'));
    }

    private function scalar(PDO $pdo, string $sql, array $params): mixed
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchColumn();
    }

    private function persistAttendancePhoto(mixed $raw, int $tenantId, int $userId, string $kind): ?string
    {
        $photo = trim((string) ($raw ?? ''));
        if ($photo === '') {
            return null;
        }
        if (! preg_match('#^data:image/(png|jpeg);base64,#i', $photo)) {
            return null;
        }
        $base64 = preg_replace('#^data:image/(png|jpeg);base64,#i', '', $photo);
        if (! is_string($base64) || $base64 === '') {
            return null;
        }
        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) < 512) {
            return null;
        }
        $ext = stripos($photo, 'data:image/jpeg') === 0 ? 'jpg' : 'png';
        $dir = dirname(__DIR__, 2).'/public/uploads/attendance';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            return null;
        }
        $name = sprintf('tenant%d-user%d-%s-%s.%s', $tenantId, $userId, $kind, date('YmdHis'), $ext);
        $fullPath = $dir.'/'.$name;
        if (@file_put_contents($fullPath, $binary) === false) {
            return null;
        }

        return 'uploads/attendance/'.$name;
    }

    private function ensureTimeLogPhotoColumns(PDO $pdo): void
    {
        $hasIn = false;
        $hasOut = false;
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_time_logs` LIKE 'clock_in_photo_path'");
            $hasIn = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            $hasIn = false;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_time_logs` LIKE 'clock_out_photo_path'");
            $hasOut = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            $hasOut = false;
        }
        if (! $hasIn) {
            try {
                $pdo->exec("ALTER TABLE `laundry_time_logs` ADD COLUMN `clock_in_photo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `clock_in_at`");
            } catch (\Throwable) {
            }
        }
        if (! $hasOut) {
            try {
                $pdo->exec("ALTER TABLE `laundry_time_logs` ADD COLUMN `clock_out_photo_path` VARCHAR(255) NULL DEFAULT NULL AFTER `clock_out_at`");
            } catch (\Throwable) {
            }
        }
    }

    private function getBranchFoldServiceAmount(PDO $pdo, int $tenantId): float
    {
        if ($tenantId < 1) {
            return 0.0;
        }
        try {
            $st = $pdo->prepare(
                'SELECT fold_service_amount
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! is_array($row)) {
                return 0.0;
            }

            return max(0.0, (float) ($row['fold_service_amount'] ?? 0));
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function getBranchFoldCommissionTarget(PDO $pdo, int $tenantId): string
    {
        if ($tenantId < 1) {
            return 'staff';
        }
        try {
            $st = $pdo->prepare(
                'SELECT fold_commission_target
                 FROM laundry_branch_configs
                 WHERE tenant_id = ?
                 LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $v = strtolower(trim((string) ($row['fold_commission_target'] ?? 'staff')));

            return in_array($v, ['staff', 'branch'], true) ? $v : 'staff';
        } catch (\Throwable) {
            return 'staff';
        }
    }
}
