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
            $hasBranchColumns = $this->tableHasColumns($pdo, 'tenants', ['is_main_branch']);
            $hasPlanColumn = $this->tableHasColumns($pdo, 'tenants', ['plan']);
            $hasLicenseStarts = $this->tableHasColumns($pdo, 'tenants', ['license_starts_at']);
            $hasEmailVerifiedAt = $this->tableHasColumns($pdo, 'users', ['email_verified_at']);

            $overallTotalShops = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
            $freeUsers = 0;
            $premiumUsers = 0;
            if ($hasPlanColumn) {
                $stPlanCounts = $pdo->query(
                    "SELECT
                        SUM(CASE WHEN LOWER(TRIM(plan)) IN ('trial','free','free_trial','free_access') THEN 1 ELSE 0 END) AS free_count,
                        SUM(CASE WHEN LOWER(TRIM(plan)) NOT IN ('trial','free','free_trial','free_access') THEN 1 ELSE 0 END) AS premium_count
                     FROM tenants"
                );
                $planCounts = $stPlanCounts !== false ? ($stPlanCounts->fetch(PDO::FETCH_ASSOC) ?: []) : [];
                $freeUsers = (int) ($planCounts['free_count'] ?? 0);
                $premiumUsers = (int) ($planCounts['premium_count'] ?? 0);
            }

            $monthCounts = [
                '1m' => 0,
                '3m' => 0,
                '6m' => 0,
                '12m' => 0,
            ];
            if ($hasPlanColumn) {
                $stMonthCounts = $pdo->query(
                    "SELECT
                        SUM(CASE WHEN LOWER(TRIM(plan)) = 'subscription_1m' THEN 1 ELSE 0 END) AS m1,
                        SUM(CASE WHEN LOWER(TRIM(plan)) = 'subscription_3m' THEN 1 ELSE 0 END) AS m3,
                        SUM(CASE WHEN LOWER(TRIM(plan)) = 'subscription_6m' THEN 1 ELSE 0 END) AS m6,
                        SUM(CASE WHEN LOWER(TRIM(plan)) = 'subscription_12m' THEN 1 ELSE 0 END) AS m12
                     FROM tenants"
                );
                $mc = $stMonthCounts !== false ? ($stMonthCounts->fetch(PDO::FETCH_ASSOC) ?: []) : [];
                $monthCounts = [
                    '1m' => (int) ($mc['m1'] ?? 0),
                    '3m' => (int) ($mc['m3'] ?? 0),
                    '6m' => (int) ($mc['m6'] ?? 0),
                    '12m' => (int) ($mc['m12'] ?? 0),
                ];
            }

            $totalMainBranches = 0;
            $totalSubBranches = 0;
            if ($hasBranchColumns) {
                $stBranchCounts = $pdo->query(
                    "SELECT
                        SUM(CASE WHEN COALESCE(is_main_branch, 0) = 1 THEN 1 ELSE 0 END) AS main_branches,
                        SUM(CASE WHEN COALESCE(is_main_branch, 0) = 0 THEN 1 ELSE 0 END) AS sub_branches
                     FROM tenants"
                );
                $bc = $stBranchCounts !== false ? ($stBranchCounts->fetch(PDO::FETCH_ASSOC) ?: []) : [];
                $totalMainBranches = (int) ($bc['main_branches'] ?? 0);
                $totalSubBranches = (int) ($bc['sub_branches'] ?? 0);
            } else {
                // Fallback for older schemas without explicit branch columns.
                $totalMainBranches = $overallTotalShops;
                $totalSubBranches = 0;
            }

            $totalVerifiedShops = 0;
            if ($hasEmailVerifiedAt) {
                $stVerified = $pdo->query(
                    "SELECT COUNT(DISTINCT tenant_id)
                     FROM users
                     WHERE role = 'tenant_admin'
                       AND tenant_id IS NOT NULL
                       AND email_verified_at IS NOT NULL"
                );
                $totalVerifiedShops = $stVerified !== false ? (int) $stVerified->fetchColumn() : 0;
            }

            $userColumns = ['id', 'name', 'email', 'email_verified_at', 'last_login_at', 'tenant_id'];
            $userRows = [];
            $availableCols = [];
            $showColumnsSt = $pdo->query('SHOW COLUMNS FROM users');
            if ($showColumnsSt !== false) {
                foreach ($showColumnsSt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $col) {
                    $field = (string) ($col['Field'] ?? '');
                    if ($field !== '') {
                        $availableCols[$field] = true;
                    }
                }
            }
            $selectParts = [
                'id',
                'name',
                'email',
                isset($availableCols['email_verified_at']) ? 'email_verified_at' : 'NULL AS email_verified_at',
                isset($availableCols['last_login_at']) ? 'last_login_at' : 'NULL AS last_login_at',
                isset($availableCols['tenant_id']) ? 'tenant_id' : 'NULL AS tenant_id',
                isset($availableCols['is_online']) ? 'is_online' : '0 AS is_online',
            ];
            $selectCols = implode(', ', $selectParts);
            $userRowsSt = $pdo->query("SELECT {$selectCols} FROM users ORDER BY id DESC LIMIT 500");
            $userRows = $userRowsSt !== false ? ($userRowsSt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            return view_page('Dashboard', 'dashboard', [
                'is_super' => true,
                'stats' => [
                    'overall_total_shops' => $overallTotalShops,
                    'free_shops' => $freeUsers,
                    'premium_shops' => $premiumUsers,
                    'one_month_plan_shops' => (int) ($monthCounts['1m'] ?? 0),
                    'three_month_plan_shops' => (int) ($monthCounts['3m'] ?? 0),
                    'six_month_plan_shops' => (int) ($monthCounts['6m'] ?? 0),
                    'twelve_month_plan_shops' => (int) ($monthCounts['12m'] ?? 0),
                    'total_main_branches' => $totalMainBranches,
                    'total_sub_branches' => $totalSubBranches,
                    'total_verified_shops' => $totalVerifiedShops,
                    'has_license_starts_at' => $hasLicenseStarts,
                ],
                'users_columns' => $userColumns,
                'users_rows' => $userRows,
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
        $dashboardSubscription = null;

        LaundrySchema::ensure($pdo);
        $this->ensureTimeLogPhotoColumns($pdo);
        $laundryStatusTrackingEnabled = $this->isLaundryStatusTrackingEnabled($pdo, $tenantId);
        $warningDays = (int) App::config('subscription_warning_days', 7);
        try {
            $stExp = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
            $stExp->execute([$tenantId]);
            $trow = $stExp->fetch(PDO::FETCH_ASSOC) ?: null;
            $expRaw = $trow['license_expires_at'] ?? null;
            if ($expRaw !== null && $expRaw !== '') {
                $expDate = date('Y-m-d', strtotime((string) $expRaw));
                $todayCheck = date('Y-m-d');
                $daysLeft = (int) floor((strtotime($expDate.' 00:00:00') - strtotime($todayCheck.' 00:00:00')) / 86400);
                if ($daysLeft >= 0 && $daysLeft <= $warningDays) {
                    $dashboardSubscription = [
                        'expires_label' => date('M j, Y', strtotime($expDate)),
                        'days_left' => $daysLeft,
                    ];
                }
            }
        } catch (\Throwable) {
            $dashboardSubscription = null;
        }
        $today = date('Y-m-d');
        $machineCreditFrom = trim((string) $request->query('machine_from', $today));
        $machineCreditTo = trim((string) $request->query('machine_to', $today));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $machineCreditFrom) !== 1) {
            $machineCreditFrom = $today;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $machineCreditTo) !== 1) {
            $machineCreditTo = $today;
        }
        if (strtotime($machineCreditFrom) > strtotime($machineCreditTo)) {
            [$machineCreditFrom, $machineCreditTo] = [$machineCreditTo, $machineCreditFrom];
        }
        $machineRangeStart = $machineCreditFrom.' 00:00:00';
        $machineRangeEnd = $machineCreditTo.' 23:59:59';
        $rangeStart = $today.' 00:00:00';
        $rangeEnd = $today.' 23:59:59';
        $inventoryOutTotals = $this->fetchInventoryOutTotals($pdo, $tenantId, $rangeStart, $rangeEnd);
        $inventoryOutBreakdown = $this->fetchInventoryOutBreakdown($pdo, $tenantId, $rangeStart, $rangeEnd);
        $inventoryLedgerRowsToday = $this->fetchInventoryLedgerRows($pdo, $tenantId, $rangeStart, $rangeEnd);
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
        $orderTypeTotalsToday = $this->fetchOrderTypeTotals($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineCreditRows = $this->fetchMachineCreditLedgerRows($pdo, $tenantId, $machineRangeStart, $machineRangeEnd);
        $cashAvailable = ($salesToday + $foldAmountToday) - $expensesToday;
        $trendLabels = [];
        $trendSales = [];
        $trendExpenses = [];
        $trendLabels[] = date('M j', strtotime($today));
        $trendSales[] = $salesToday;
        $trendExpenses[] = $expensesToday;

        $stPayment = $pdo->prepare(
            'SELECT
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "cash" THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "split_payment" THEN COALESCE(split_cash_amount, 0)
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "split" THEN COALESCE(split_cash_amount, 0)
                        ELSE 0
                    END
                ), 0) AS cash_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "card" THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("split_payment", "split")
                             AND LOWER(TRIM(COALESCE(split_online_method, ""))) = "card" THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS card_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "gcash" THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("split_payment", "split")
                             AND LOWER(TRIM(COALESCE(split_online_method, ""))) = "gcash" THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS gcash_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "paymaya" THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("split_payment", "split")
                             AND LOWER(TRIM(COALESCE(split_online_method, ""))) = "paymaya" THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS paymaya_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("online_banking", "online banking") THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("split_payment", "split")
                             AND LOWER(TRIM(COALESCE(split_online_method, ""))) = "online_banking" THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS online_banking_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) = "qr_payment" THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("split_payment", "split")
                             AND LOWER(TRIM(COALESCE(split_online_method, ""))) = "qr_payment" THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS qr_payment_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, "cash"))) IN ("split_payment", "split")
                            THEN COALESCE(split_cash_amount, 0) + COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS split_payment_total
             FROM laundry_orders
             WHERE tenant_id = ?
               AND DATE(created_at) = ?
               AND COALESCE(is_void, 0) = 0
               AND status <> "void"
               AND (
                   status = "paid"
                   OR (status = "completed" AND payment_status = "paid")
               )'
        );
        $stPayment->execute([$tenantId, $today]);
        $paymentBreakdown = [
            'cash' => 0.0,
            'card' => 0.0,
            'gcash' => 0.0,
            'paymaya' => 0.0,
            'online_banking' => 0.0,
            'qr_payment' => 0.0,
            'split_payment' => 0.0,
        ];
        $paymentRow = $stPayment->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($paymentRow !== []) {
            $paymentBreakdown['cash'] = (float) ($paymentRow['cash_total'] ?? 0);
            $paymentBreakdown['card'] = (float) ($paymentRow['card_total'] ?? 0);
            $paymentBreakdown['gcash'] = (float) ($paymentRow['gcash_total'] ?? 0);
            $paymentBreakdown['paymaya'] = (float) ($paymentRow['paymaya_total'] ?? 0);
            $paymentBreakdown['online_banking'] = (float) ($paymentRow['online_banking_total'] ?? 0);
            $paymentBreakdown['qr_payment'] = (float) ($paymentRow['qr_payment_total'] ?? 0);
            $paymentBreakdown['split_payment'] = (float) ($paymentRow['split_payment_total'] ?? 0);
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
                'inclusion_items_out_today' => (float) ($inventoryOutTotals['inclusion_qty'] ?? 0.0),
                'addon_items_out_today' => (float) ($inventoryOutTotals['addon_qty'] ?? 0.0),
                'total_items_out_today' => (float) ($inventoryOutTotals['total_qty'] ?? 0.0),
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
            'order_type_totals_today' => $orderTypeTotalsToday,
            'inclusion_items_out_rows_today' => (array) ($inventoryOutBreakdown['inclusion_rows'] ?? []),
            'addon_items_out_rows_today' => (array) ($inventoryOutBreakdown['addon_rows'] ?? []),
            'inventory_ledger_rows_today' => $inventoryLedgerRowsToday,
            'machine_credit_balances' => $machineCreditRows,
            'machine_credit_from' => $machineCreditFrom,
            'machine_credit_to' => $machineCreditTo,
            'clock_open' => $clockOpen,
            'clock_rows_today' => $clockRows,
            'free_dashboard_limited' => Auth::isTenantFreePlanRestricted($user),
            'can_use_attendance' => Auth::canUseAttendanceFeature($user),
            'dashboard_subscription' => $dashboardSubscription,
        ]);
    }

    public function superAdminDeleteUser(Request $request, string $id): Response
    {
        $actor = Auth::user();
        if (! $actor || ($actor['role'] ?? '') !== 'super_admin') {
            return new Response('Forbidden.', 403);
        }
        $targetUserId = (int) $id;
        if ($targetUserId < 1) {
            session_flash('errors', ['Invalid user ID.']);
            return redirect(url('/dashboard'));
        }
        $actorId = (int) ($actor['id'] ?? 0);
        if ($targetUserId === $actorId) {
            session_flash('errors', ['You cannot delete your own account.']);
            return redirect(url('/dashboard'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT id, role, email FROM users WHERE id = ? LIMIT 1');
        $st->execute([$targetUserId]);
        $target = $st->fetch(PDO::FETCH_ASSOC);
        if (! is_array($target)) {
            session_flash('errors', ['User not found.']);
            return redirect(url('/dashboard'));
        }
        if (strtolower(trim((string) ($target['role'] ?? ''))) === 'super_admin') {
            session_flash('errors', ['Super admin users cannot be deleted from this table.']);
            return redirect(url('/dashboard'));
        }

        try {
            $pdo->prepare('DELETE FROM users WHERE id = ? LIMIT 1')->execute([$targetUserId]);
            session_flash('status', 'User deleted successfully.');
        } catch (\Throwable) {
            session_flash('errors', ['Could not delete user.']);
        }

        return redirect(url('/dashboard'));
    }

    public function superAdminSmsQueueStore(Request $request): Response
    {
        $actor = Auth::user();
        if (! $actor || ($actor['role'] ?? '') !== 'super_admin') {
            return new Response('Forbidden.', 403);
        }

        $deviceId = trim((string) $request->input('device_id', ''));
        $phone = trim((string) $request->input('phone', ''));
        $message = trim((string) $request->input('message', ''));

        if (! preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $deviceId)) {
            session_flash('errors', ['Invalid Device ID. Use letters, numbers, underscore, or dash only.']);
            return redirect(route('super-admin.sms.index'));
        }
        if (! preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
            session_flash('errors', ['Invalid phone format. Use 10 to 15 digits, optional leading +.']);
            return redirect(route('super-admin.sms.index'));
        }
        if ($message === '') {
            session_flash('errors', ['Message is required.']);
            return redirect(route('super-admin.sms.index'));
        }
        if (mb_strlen($message) > 1000) {
            session_flash('errors', ['Message is too long. Maximum is 1000 characters.']);
            return redirect(route('super-admin.sms.index'));
        }

        $pdo = App::db();
        if (! $this->hasTable($pdo, 'sms_queue')) {
            session_flash('errors', ['SMS queue table is missing. Run storage migrations first.']);
            return redirect(route('super-admin.sms.index'));
        }
        try {
            $st = $pdo->prepare(
                'INSERT INTO sms_queue
                 (device_id, phone, message, status, retry_count, error_message, created_at, sent_at, updated_at)
                 VALUES (?, ?, ?, "pending", 0, NULL, NOW(), NULL, NOW())'
            );
            $st->execute([$deviceId, $phone, $message]);
            session_flash('status', 'SMS record created. Your phone app can now pull it.');
        } catch (\Throwable $e) {
            error_log('Super admin SMS queue insert failed: '.$e->getMessage());
            session_flash('errors', ['Could not create SMS record.']);
        }

        return redirect(route('super-admin.sms.index'));
    }

    public function superAdminSmsIndex(Request $request): Response
    {
        $actor = Auth::user();
        if (! $actor || ($actor['role'] ?? '') !== 'super_admin') {
            return new Response('Forbidden.', 403);
        }
        $smsQueueRows = [];
        $pdo = App::db();
        if ($this->hasTable($pdo, 'sms_queue')) {
            $smsQueueSt = $pdo->query(
                'SELECT id, device_id, phone, message, status, retry_count, created_at, sent_at
                 FROM sms_queue
                 ORDER BY id DESC
                 LIMIT 50'
            );
            $smsQueueRows = $smsQueueSt !== false ? ($smsQueueSt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        }
        $tenantRows = [];
        try {
            $tenantSt = $pdo->query('SELECT id, name FROM tenants ORDER BY name ASC, id ASC');
            $tenantRows = $tenantSt !== false ? ($tenantSt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Throwable) {
            $tenantRows = [];
        }

        return view_page('SMS', 'super-admin.sms.index', [
            'sms_queue_rows' => $smsQueueRows,
            'sms_tenants' => $tenantRows,
        ]);
    }

    public function superAdminSmsCreditsAssign(Request $request): Response
    {
        $actor = Auth::user();
        if (! $actor || ($actor['role'] ?? '') !== 'super_admin') {
            return new Response('Forbidden.', 403);
        }
        $tenantId = max(0, (int) $request->input('tenant_id', 0));
        $amount = max(0, (int) $request->input('credits', 0));
        if ($tenantId < 1 || $amount < 1) {
            session_flash('errors', ['Select a shop and enter credits greater than zero.']);
            return redirect(route('super-admin.sms.index'));
        }
        $pdo = App::db();
        if (! $this->hasTable($pdo, 'laundry_branch_configs')) {
            session_flash('errors', ['Branch config table is missing. Run storage migrations first.']);
            return redirect(route('super-admin.sms.index'));
        }
        try {
            $pdo->prepare(
                'INSERT INTO laundry_branch_configs (tenant_id, sms_extra_credits, created_at, updated_at)
                 VALUES (?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE sms_extra_credits = COALESCE(sms_extra_credits, 0) + VALUES(sms_extra_credits), updated_at = NOW()'
            )->execute([$tenantId, $amount]);
            session_flash('status', 'SMS credits assigned successfully.');
        } catch (\Throwable $e) {
            error_log('SMS credit assign failed: '.$e->getMessage());
            session_flash('errors', ['Could not assign SMS credits.']);
        }
        return redirect(route('super-admin.sms.index'));
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
            ]
            : [
                'open_ticket' => ['label' => 'Unpaid', 'count' => 0, 'amount' => 0.0],
                'paid' => ['label' => 'Paid', 'count' => 0, 'amount' => 0.0],
            ];
        try {
            $st = $pdo->prepare(
                'SELECT
                    CASE
                        WHEN o.status = "pending" THEN "pending"
                        WHEN o.status IN ("washing_drying", "running") THEN "washing_drying"
                        WHEN o.status = "open_ticket" THEN "open_ticket"
                        WHEN o.status = "paid" THEN "paid"
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
                        OR (o.status = "paid" AND DATE(o.created_at) = ?)
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

    /**
     * @return array{inclusion_qty:float,addon_qty:float,total_qty:float}
     */
    private function fetchInventoryOutTotals(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        $addonQty = (float) $this->scalar(
            $pdo,
            'SELECT COALESCE(SUM(ao.quantity), 0)
             FROM laundry_order_add_ons ao
             INNER JOIN laundry_orders o ON o.id = ao.order_id AND o.tenant_id = ao.tenant_id
             WHERE ao.tenant_id = ?
               AND o.created_at BETWEEN ? AND ?
               AND COALESCE(o.is_void, 0) = 0
               AND o.status <> "void"
               AND (
                   o.status = "paid"
                   OR (o.status = "completed" AND o.payment_status = "paid")
               )',
            [$tenantId, $rangeStart, $rangeEnd]
        );

        $totalQty = 0.0;
        if ($this->hasTable($pdo, 'laundry_order_inventory_movements')) {
            $totalQty = (float) $this->scalar(
                $pdo,
                'SELECT COALESCE(SUM(m.quantity), 0)
                 FROM laundry_order_inventory_movements m
                 INNER JOIN laundry_orders o ON o.id = m.order_id AND o.tenant_id = m.tenant_id
                 WHERE m.tenant_id = ?
                   AND m.direction = "deduct"
                   AND o.created_at BETWEEN ? AND ?
                   AND COALESCE(o.is_void, 0) = 0
                   AND o.status <> "void"
                   AND (
                       o.status = "paid"
                       OR (o.status = "completed" AND o.payment_status = "paid")
                   )',
                [$tenantId, $rangeStart, $rangeEnd]
            );
        } else {
            // Legacy fallback: count inclusion selections as qty when movement table is unavailable.
            $inclusionFallback = (float) $this->scalar(
                $pdo,
                'SELECT COALESCE(SUM(
                    (CASE WHEN COALESCE(o.inclusion_detergent_item_id, 0) > 0 THEN 1 ELSE 0 END)
                  + (CASE WHEN COALESCE(o.inclusion_fabcon_item_id, 0) > 0 THEN 1 ELSE 0 END)
                  + (CASE WHEN COALESCE(o.inclusion_bleach_item_id, 0) > 0 THEN 1 ELSE 0 END)
                ), 0)
                 FROM laundry_orders o
                 WHERE o.tenant_id = ?
                   AND o.created_at BETWEEN ? AND ?
                   AND COALESCE(o.is_void, 0) = 0
                   AND o.status <> "void"
                   AND (
                       o.status = "paid"
                       OR (o.status = "completed" AND o.payment_status = "paid")
                   )',
                [$tenantId, $rangeStart, $rangeEnd]
            );
            $totalQty = $inclusionFallback + $addonQty;
        }

        $inclusionQty = max(0.0, $totalQty - $addonQty);

        return [
            'inclusion_qty' => $inclusionQty,
            'addon_qty' => $addonQty,
            'total_qty' => $inclusionQty + $addonQty,
        ];
    }

    /**
     * @return array{inclusion_rows:list<array{item_name:string,qty_out:float}>,addon_rows:list<array{item_name:string,qty_out:float}>}
     */
    private function fetchInventoryOutBreakdown(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        $inclusionRows = [];
        $addonRows = [];
        try {
            $inclusionQueries = [
                ['col' => 'o.inclusion_detergent_item_id', 'fallback' => 'Detergent'],
                ['col' => 'o.inclusion_fabcon_item_id', 'fallback' => 'Fabric conditioner'],
                ['col' => 'o.inclusion_bleach_item_id', 'fallback' => 'Bleach'],
            ];
            $incMap = [];
            foreach ($inclusionQueries as $cfg) {
                $st = $pdo->prepare(
                    "SELECT COALESCE(NULLIF(TRIM(i.name), ''), ?) AS item_name,
                            COUNT(*) AS qty_out
                     FROM laundry_orders o
                     LEFT JOIN laundry_inventory_items i
                       ON i.id = {$cfg['col']} AND i.tenant_id = o.tenant_id
                     WHERE o.tenant_id = ?
                       AND o.created_at BETWEEN ? AND ?
                       AND {$cfg['col']} IS NOT NULL
                       AND {$cfg['col']} > 0
                       AND COALESCE(o.is_void, 0) = 0
                       AND o.status <> 'void'
                       AND (
                           o.status = 'paid'
                           OR (o.status = 'completed' AND o.payment_status = 'paid')
                       )
                     GROUP BY item_name"
                );
                $st->execute([(string) $cfg['fallback'], $tenantId, $rangeStart, $rangeEnd]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $name = trim((string) ($row['item_name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $incMap[$name] = ($incMap[$name] ?? 0.0) + max(0.0, (float) ($row['qty_out'] ?? 0));
                }
            }
            foreach ($incMap as $name => $qty) {
                $inclusionRows[] = ['item_name' => (string) $name, 'qty_out' => (float) $qty];
            }
            usort($inclusionRows, static fn (array $a, array $b): int => ($b['qty_out'] <=> $a['qty_out']) ?: strcmp((string) $a['item_name'], (string) $b['item_name']));

            $addonSt = $pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(ao.item_name), ''), 'Add-on item') AS item_name,
                        COALESCE(SUM(ao.quantity),0) AS qty_out
                 FROM laundry_order_add_ons ao
                 INNER JOIN laundry_orders o ON o.id = ao.order_id AND o.tenant_id = ao.tenant_id
                 WHERE ao.tenant_id = ?
                   AND o.created_at BETWEEN ? AND ?
                   AND COALESCE(o.is_void, 0) = 0
                   AND o.status <> 'void'
                   AND (
                       o.status = 'paid'
                       OR (o.status = 'completed' AND o.payment_status = 'paid')
                   )
                 GROUP BY item_name"
            );
            $addonSt->execute([$tenantId, $rangeStart, $rangeEnd]);
            foreach ($addonSt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $addonRows[] = [
                    'item_name' => (string) ($row['item_name'] ?? ''),
                    'qty_out' => max(0.0, (float) ($row['qty_out'] ?? 0)),
                ];
            }
            usort($addonRows, static fn (array $a, array $b): int => ($b['qty_out'] <=> $a['qty_out']) ?: strcmp((string) $a['item_name'], (string) $b['item_name']));
        } catch (\Throwable) {
        }

        return [
            'inclusion_rows' => $inclusionRows,
            'addon_rows' => $addonRows,
        ];
    }

    /**
     * @return list<array{item_name:string,opening:float,stock_in:float,stock_out:float,closing:float}>
     */
    private function fetchInventoryLedgerRows(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        $rows = [];
        try {
            $itemsSt = $pdo->prepare(
                'SELECT id, name, stock_quantity
                 FROM laundry_inventory_items
                 WHERE tenant_id = ?
                 ORDER BY name ASC'
            );
            $itemsSt->execute([$tenantId]);
            $items = $itemsSt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($items === []) {
                return [];
            }

            $signedRangeByItem = [];
            $signedAfterEndByItem = [];
            $inRangeByItem = [];
            $outRangeByItem = [];

            $purchaseRange = $pdo->prepare(
                'SELECT item_id,
                        COALESCE(SUM(quantity),0) AS signed_qty,
                        COALESCE(SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END),0) AS in_qty,
                        COALESCE(SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END),0) AS out_qty
                 FROM laundry_inventory_purchases
                 WHERE tenant_id = ?
                   AND purchased_at BETWEEN ? AND ?
                 GROUP BY item_id'
            );
            $purchaseRange->execute([$tenantId, $rangeStart, $rangeEnd]);
            foreach ($purchaseRange->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($itemId < 1) {
                    continue;
                }
                $signedRangeByItem[$itemId] = ($signedRangeByItem[$itemId] ?? 0.0) + (float) ($row['signed_qty'] ?? 0);
                $inRangeByItem[$itemId] = ($inRangeByItem[$itemId] ?? 0.0) + (float) ($row['in_qty'] ?? 0);
                $outRangeByItem[$itemId] = ($outRangeByItem[$itemId] ?? 0.0) + (float) ($row['out_qty'] ?? 0);
            }

            $purchaseAfter = $pdo->prepare(
                'SELECT item_id, COALESCE(SUM(quantity),0) AS signed_qty
                 FROM laundry_inventory_purchases
                 WHERE tenant_id = ?
                   AND purchased_at > ?
                 GROUP BY item_id'
            );
            $purchaseAfter->execute([$tenantId, $rangeEnd]);
            foreach ($purchaseAfter->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($itemId < 1) {
                    continue;
                }
                $signedAfterEndByItem[$itemId] = ($signedAfterEndByItem[$itemId] ?? 0.0) + (float) ($row['signed_qty'] ?? 0);
            }

            if ($this->hasTable($pdo, 'laundry_order_inventory_movements')) {
                $mvRange = $pdo->prepare(
                    'SELECT inventory_item_id,
                            COALESCE(SUM(CASE WHEN direction = "restore" THEN quantity WHEN direction = "deduct" THEN -quantity ELSE 0 END),0) AS signed_qty,
                            COALESCE(SUM(CASE WHEN direction = "restore" THEN quantity ELSE 0 END),0) AS in_qty,
                            COALESCE(SUM(CASE WHEN direction = "deduct" THEN quantity ELSE 0 END),0) AS out_qty
                     FROM laundry_order_inventory_movements
                     WHERE tenant_id = ?
                       AND created_at BETWEEN ? AND ?
                     GROUP BY inventory_item_id'
                );
                $mvRange->execute([$tenantId, $rangeStart, $rangeEnd]);
                foreach ($mvRange->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $itemId = (int) ($row['inventory_item_id'] ?? 0);
                    if ($itemId < 1) {
                        continue;
                    }
                    $signedRangeByItem[$itemId] = ($signedRangeByItem[$itemId] ?? 0.0) + (float) ($row['signed_qty'] ?? 0);
                    $inRangeByItem[$itemId] = ($inRangeByItem[$itemId] ?? 0.0) + (float) ($row['in_qty'] ?? 0);
                    $outRangeByItem[$itemId] = ($outRangeByItem[$itemId] ?? 0.0) + (float) ($row['out_qty'] ?? 0);
                }

                $mvAfter = $pdo->prepare(
                    'SELECT inventory_item_id,
                            COALESCE(SUM(CASE WHEN direction = "restore" THEN quantity WHEN direction = "deduct" THEN -quantity ELSE 0 END),0) AS signed_qty
                     FROM laundry_order_inventory_movements
                     WHERE tenant_id = ?
                       AND created_at > ?
                     GROUP BY inventory_item_id'
                );
                $mvAfter->execute([$tenantId, $rangeEnd]);
                foreach ($mvAfter->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $itemId = (int) ($row['inventory_item_id'] ?? 0);
                    if ($itemId < 1) {
                        continue;
                    }
                    $signedAfterEndByItem[$itemId] = ($signedAfterEndByItem[$itemId] ?? 0.0) + (float) ($row['signed_qty'] ?? 0);
                }
            }

            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                $current = max(0.0, (float) ($item['stock_quantity'] ?? 0));
                $rangeDelta = (float) ($signedRangeByItem[$itemId] ?? 0.0);
                $afterEndDelta = (float) ($signedAfterEndByItem[$itemId] ?? 0.0);
                $closing = max(0.0, $current - $afterEndDelta);
                $opening = max(0.0, $closing - $rangeDelta);
                $rows[] = [
                    'item_name' => (string) ($item['name'] ?? 'Item'),
                    'opening' => $opening,
                    'stock_in' => max(0.0, (float) ($inRangeByItem[$itemId] ?? 0.0)),
                    'stock_out' => max(0.0, (float) ($outRangeByItem[$itemId] ?? 0.0)),
                    'closing' => $closing,
                ];
            }
        } catch (\Throwable) {
            return [];
        }
        return $rows;
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?'
            );
            $st->execute([$table]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function isLaundryStatusTrackingEnabled(PDO $pdo, int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
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
                return false;
            }

            return (int) ($row['laundry_status_tracking_enabled'] ?? 0) === 1;
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
            'regular' => ['label' => 'Regular', 'count' => 0, 'items' => [], 'machines' => []],
            'free' => ['label' => 'Free', 'count' => 0, 'items' => [], 'machines' => []],
            'reward' => ['label' => 'Reward', 'count' => 0, 'items' => [], 'machines' => []],
        ];

        try {
            $st = $pdo->prepare(
                'SELECT
                    CASE
                        WHEN COALESCE(o.is_reward, 0) = 1 THEN "reward"
                        WHEN COALESCE(o.is_free, 0) = 1 THEN "free"
                        ELSE "regular"
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
                   AND o.status <> "void"'
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
    private function fetchMachineCreditLedgerRows(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        try {
            $currentSt = $pdo->prepare('SELECT COALESCE(machine_global_credit_balance, 0) FROM laundry_branch_configs WHERE tenant_id = ? LIMIT 1');
            $currentSt->execute([$tenantId]);
            $current = max(0.0, (float) ($currentSt->fetchColumn() ?: 0.0));
            if (! $this->hasTable($pdo, 'laundry_machine_global_credit_movements')) {
                return [[
                    'machine_label' => 'Overall credits',
                    'credit_required' => 1,
                    'opening' => $current,
                    'restock' => 0.0,
                    'usage' => 0.0,
                    'closing' => $current,
                ]];
            }
            $rangeSt = $pdo->prepare(
                'SELECT
                    COALESCE(SUM(CASE WHEN direction = "restock" THEN amount WHEN direction = "deduct" THEN -amount ELSE 0 END), 0) AS signed_amount,
                    COALESCE(SUM(CASE WHEN direction = "restock" THEN amount ELSE 0 END), 0) AS restock_amount,
                    COALESCE(SUM(CASE WHEN direction = "deduct" THEN amount ELSE 0 END), 0) AS usage_amount
                 FROM laundry_machine_global_credit_movements
                 WHERE tenant_id = ?
                   AND created_at BETWEEN ? AND ?'
            );
            $rangeSt->execute([$tenantId, $rangeStart, $rangeEnd]);
            $rangeRow = $rangeSt->fetch(PDO::FETCH_ASSOC) ?: [];
            $afterSt = $pdo->prepare(
                'SELECT COALESCE(SUM(CASE WHEN direction = "restock" THEN amount WHEN direction = "deduct" THEN -amount ELSE 0 END), 0) AS signed_amount
                 FROM laundry_machine_global_credit_movements
                 WHERE tenant_id = ?
                   AND created_at > ?'
            );
            $afterSt->execute([$tenantId, $rangeEnd]);
            $afterDelta = (float) ($afterSt->fetchColumn() ?: 0.0);
            $rangeDelta = (float) ($rangeRow['signed_amount'] ?? 0.0);
            $closing = max(0.0, $current - $afterDelta);
            $opening = max(0.0, $closing - $rangeDelta);
            return [[
                'machine_label' => 'Overall credits',
                'credit_required' => 1,
                'opening' => $opening,
                'restock' => max(0.0, (float) ($rangeRow['restock_amount'] ?? 0.0)),
                'usage' => max(0.0, (float) ($rangeRow['usage_amount'] ?? 0.0)),
                'closing' => $closing,
            ]];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{code:string,label:string,qty:float}>
     */
    private function fetchOrderTypeTotals(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        $rows = [];
        try {
            if ($this->hasTable($pdo, 'laundry_order_lines')) {
                $st = $pdo->prepare(
                    'SELECT
                        ot.code,
                        ot.label,
                        COALESCE(SUM(
                            CASE
                                WHEN o.id IS NOT NULL THEN COALESCE(ol.quantity, 0)
                                ELSE 0
                            END
                        ), 0) AS qty
                     FROM laundry_order_types ot
                     LEFT JOIN laundry_order_lines ol
                       ON ol.tenant_id = ot.tenant_id
                      AND ol.order_type_code = ot.code
                     LEFT JOIN laundry_orders o
                       ON o.id = ol.order_id
                      AND o.tenant_id = ol.tenant_id
                      AND o.created_at BETWEEN ? AND ?
                      AND COALESCE(o.is_void, 0) = 0
                      AND o.status <> "void"
                      AND (
                          o.status = "paid"
                          OR (o.status = "completed" AND o.payment_status = "paid")
                      )
                     WHERE ot.tenant_id = ?
                     GROUP BY ot.code, ot.label, ot.sort_order
                     ORDER BY ot.sort_order ASC, ot.label ASC, ot.code ASC'
                );
                $st->execute([$rangeStart, $rangeEnd, $tenantId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT
                        ot.code,
                        ot.label,
                        COALESCE(SUM(
                            CASE
                                WHEN o.id IS NOT NULL THEN 1
                                ELSE 0
                            END
                        ), 0) AS qty
                     FROM laundry_order_types ot
                     LEFT JOIN laundry_orders o
                       ON o.tenant_id = ot.tenant_id
                      AND o.order_type = ot.code
                      AND o.created_at BETWEEN ? AND ?
                      AND COALESCE(o.is_void, 0) = 0
                      AND o.status <> "void"
                      AND (
                          o.status = "paid"
                          OR (o.status = "completed" AND o.payment_status = "paid")
                      )
                     WHERE ot.tenant_id = ?
                     GROUP BY ot.code, ot.label, ot.sort_order
                     ORDER BY ot.sort_order ASC, ot.label ASC, ot.code ASC'
                );
                $st->execute([$rangeStart, $rangeEnd, $tenantId]);
            }
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $rows[] = [
                    'code' => (string) ($row['code'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'qty' => max(0.0, (float) ($row['qty'] ?? 0)),
                ];
            }
        } catch (\Throwable) {
        }
        return $rows;
    }

    public function timeIn(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') === 'super_admin') {
            return new Response('Forbidden', 403);
        }
        if (! Auth::canUseAttendanceFeature($user)) {
            session_flash('errors', ['Attendance is available only during 7-day premium trial or active premium subscription.']);

            return redirect(route('tenant.plans'));
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
        if (! Auth::canUseAttendanceFeature($user)) {
            session_flash('errors', ['Attendance is available only during 7-day premium trial or active premium subscription.']);

            return redirect(route('tenant.plans'));
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

    /**
     * @param list<string> $columns
     */
    private function tableHasColumns(PDO $pdo, string $table, array $columns): bool
    {
        if ($table === '' || $columns === []) {
            return false;
        }
        try {
            foreach ($columns as $column) {
                $st = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?
                       AND COLUMN_NAME = ?'
                );
                $st->execute([$table, $column]);
                if ((int) $st->fetchColumn() < 1) {
                    return false;
                }
            }
            return true;
        } catch (\Throwable) {
            return false;
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
            return 'branch';
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
            $v = strtolower(trim((string) ($row['fold_commission_target'] ?? 'branch')));

            return in_array($v, ['staff', 'branch'], true) ? $v : 'branch';
        } catch (\Throwable) {
            return 'branch';
        }
    }
}
