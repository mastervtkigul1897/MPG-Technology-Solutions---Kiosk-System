<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\FlavorSchema;
use App\Core\LaundrySchema;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantReceiptFields;
use App\Services\TransactionReceiptPayload;
use PDO;
use RuntimeException;

final class ReportController
{
    /** @var list<string> */
    private const CHART_PRESETS = ['today', 'yesterday', 'last_3', 'last_7', 'last_14', 'last_30', 'this_month', 'custom'];
    private static ?bool $hasLaundryOrdersDiscountAmount = null;

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }

        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        LaundrySchema::ensure($pdo);
        $today = date('Y-m-d');
        [$from, $to, $chartPreset] = $this->resolveReportRange($request, $today);
        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';
        $chartSeries = $this->buildChartSeries($pdo, $tenantId, $from, $to);
        $foldServiceAmount = $this->getBranchFoldServiceAmount($pdo, $tenantId);
        $foldCommissionTarget = $this->getBranchFoldCommissionTarget($pdo, $tenantId);

        $salesTotal = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0)
             FROM laundry_orders
             WHERE tenant_id = ?
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $discountsTotal = 0.0;
        if ($this->hasLaundryOrdersDiscountAmount($pdo)) {
            $discountsTotal = $this->scalarSum(
                $pdo,
                "SELECT COALESCE(SUM(discount_amount),0)
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND created_at BETWEEN ? AND ?
                   AND COALESCE(is_void, 0) = 0
                   AND status <> 'void'
                   AND (
                       status = 'paid'
                       OR (status = 'completed' AND payment_status = 'paid')
                   )",
                [$tenantId, $rangeStart, $rangeEnd]
            );
        }
        $foldOrdersCount = (int) $this->scalarSum(
            $pdo,
            "SELECT COUNT(*)
             FROM laundry_orders
             WHERE tenant_id = ?
               AND include_fold_service = 1
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND COALESCE(is_free, 0) = 0
               AND COALESCE(is_reward, 0) = 0
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $foldAmountTotal = $foldCommissionTarget === 'branch'
            ? ($foldServiceAmount * $foldOrdersCount)
            : 0.0;
        $grossSalesTotal = $salesTotal + $discountsTotal + $foldAmountTotal;
        $refundsTotal = 0.0;
        $st = $pdo->prepare(
            "SELECT
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'cash' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split') THEN COALESCE(split_cash_amount, 0)
                        ELSE 0
                    END
                ), 0) AS cash_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'card' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'card' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS card_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'gcash' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'gcash' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS gcash_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'paymaya' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'paymaya' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS paymaya_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('online_banking', 'online banking') THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'online_banking' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS online_banking_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'qr_payment' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'qr_payment' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS qr_payment_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                            THEN COALESCE(split_cash_amount, 0) + COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS split_payment_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'free' THEN total_amount
                        ELSE 0
                    END
                ), 0) AS free_total
             FROM laundry_orders
             WHERE tenant_id = ?
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )"
        );
        $st->execute([$tenantId, $rangeStart, $rangeEnd]);
        $paymentsByMethod = [
            'cash' => 0.0,
            'card' => 0.0,
            'gcash' => 0.0,
            'paymaya' => 0.0,
            'online_banking' => 0.0,
            'qr_payment' => 0.0,
            'split_payment' => 0.0,
            'free' => 0.0,
        ];
        $paymentRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($paymentRow !== []) {
            $paymentsByMethod['cash'] = (float) ($paymentRow['cash_total'] ?? 0);
            $paymentsByMethod['card'] = (float) ($paymentRow['card_total'] ?? 0);
            $paymentsByMethod['gcash'] = (float) ($paymentRow['gcash_total'] ?? 0);
            $paymentsByMethod['paymaya'] = (float) ($paymentRow['paymaya_total'] ?? 0);
            $paymentsByMethod['online_banking'] = (float) ($paymentRow['online_banking_total'] ?? 0);
            $paymentsByMethod['qr_payment'] = (float) ($paymentRow['qr_payment_total'] ?? 0);
            $paymentsByMethod['split_payment'] = (float) ($paymentRow['split_payment_total'] ?? 0);
            $paymentsByMethod['free'] = (float) ($paymentRow['free_total'] ?? 0);
        }
        $manualExpensesTotal = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $laundryExpensesTotal = 0.0;
        $expensesTotal = $manualExpensesTotal;
        $netSalesTotal = $grossSalesTotal - $refundsTotal - $discountsTotal - $expensesTotal;
        $cashAvailable = $netSalesTotal;
        $grossProfit = $netSalesTotal;
        $serviceModeSummary = $this->fetchServiceModeSummary($pdo, $tenantId, $rangeStart, $rangeEnd);
        $orderTypeTotals = $this->fetchOrderTypeTotals($pdo, $tenantId, $rangeStart, $rangeEnd);
        $inventoryOutTotals = $this->fetchInventoryOutTotals($pdo, $tenantId, $rangeStart, $rangeEnd);
        $inventoryLedgerRows = $this->fetchInventoryLedgerRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineCreditLedgerRows = $this->fetchMachineCreditLedgerRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineCreditUsageRows = $this->fetchMachineCreditUsageRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineIdleRows = $this->fetchMachineIdleRows($pdo, $tenantId, $rangeStart, $rangeEnd);

        $stTop = $pdo->prepare(
            'SELECT c.name, COUNT(o.id) AS frequency, COALESCE(SUM(o.total_amount),0) AS total_spending
             FROM laundry_customers c
             LEFT JOIN laundry_orders o ON o.customer_id = c.id AND o.tenant_id = c.tenant_id
                AND COALESCE(o.is_void, 0) = 0
                AND o.status <> "void"
                AND (
                    o.status = "paid"
                    OR (o.status = "completed" AND o.payment_status = "paid")
                ) AND o.created_at BETWEEN ? AND ?
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

        $rangeFromMd = date('m-d', strtotime($from) ?: time());
        $rangeToMd = date('m-d', strtotime($to) ?: time());
        $todayMd = date('m-d');
        $toMonth = date('m', strtotime($to) ?: time());
        $stBday = $pdo->prepare('SELECT name, birthday FROM laundry_customers WHERE tenant_id = ? AND birthday IS NOT NULL');
        $stBday->execute([$tenantId]);
        $birthdaysToday = [];
        $birthdaysInRange = [];
        $birthdaysThisMonth = [];
        foreach ($stBday->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $birthday = (string) ($row['birthday'] ?? '');
            if ($birthday === '' || strtotime($birthday) === false) {
                continue;
            }
            $md = date('m-d', strtotime($birthday));
            $m = date('m', strtotime($birthday));
            if ($md === $todayMd) {
                $birthdaysToday[] = $row;
            }
            if ($m === $toMonth) {
                $birthdaysThisMonth[] = $row;
            }
            $inRange = $rangeFromMd <= $rangeToMd
                ? ($md >= $rangeFromMd && $md <= $rangeToMd)
                : ($md >= $rangeFromMd || $md <= $rangeToMd);
            if ($inRange) {
                $birthdaysInRange[] = $row;
            }
        }

        $warningDays = (int) App::config('subscription_warning_days', 7);
        $reportsMaintenance = null;
        $reportsSubscription = null;

        if (App::config('maintenance_mode', false)) {
            $mm = trim((string) App::config('maintenance_message', ''));
            if ($mm !== '') {
                $reportsMaintenance = ['message' => $mm];
            }
        }

        $stExp = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
        $stExp->execute([$tenantId]);
        $trow = $stExp->fetch(PDO::FETCH_ASSOC);
        $expRaw = $trow['license_expires_at'] ?? null;
        if ($expRaw !== null && $expRaw !== '') {
            $expDate = date('Y-m-d', strtotime((string) $expRaw));
            $todayCheck = date('Y-m-d');
            $daysLeft = (int) floor((strtotime($expDate.' 00:00:00') - strtotime($todayCheck.' 00:00:00')) / 86400);
            if ($daysLeft >= 0 && $daysLeft <= $warningDays) {
                $reportsSubscription = [
                    'expires_label' => date('M j, Y', strtotime($expDate)),
                    'days_left' => $daysLeft,
                ];
            }
        }

        return view_page('Reports', 'tenant.reports.index', [
            'stats' => [
                'sales_total' => $salesTotal,
                'gross_sales_total' => $grossSalesTotal,
                'refunds_total' => $refundsTotal,
                'discounts_total' => $discountsTotal,
                'fold_amount_total' => $foldAmountTotal,
                'fold_service_amount' => $foldServiceAmount,
                'fold_commission_target' => $foldCommissionTarget,
                'expenses_total' => $expensesTotal,
                'manual_expenses_total' => $manualExpensesTotal,
                'laundry_expenses_total' => $laundryExpensesTotal,
                'net_sales' => $netSalesTotal,
                'gross_profit' => $grossProfit,
                'cash_available' => $cashAvailable,
                'payments_by_method' => $paymentsByMethod,
                'range_from' => $from,
                'range_to' => $to,
                'chart_preset' => $chartPreset,
                'service_mode_summary' => $serviceModeSummary,
                'order_type_totals' => $orderTypeTotals,
                'inclusion_items_out_total' => (float) ($inventoryOutTotals['inclusion_qty'] ?? 0.0),
                'addon_items_out_total' => (float) ($inventoryOutTotals['addon_qty'] ?? 0.0),
                'total_items_out_total' => (float) ($inventoryOutTotals['total_qty'] ?? 0.0),
                'inventory_ledger_rows' => $inventoryLedgerRows,
                'machine_credit_ledger_rows' => $machineCreditLedgerRows,
                'machine_credit_usage_rows' => $machineCreditUsageRows,
                'machine_idle_rows' => $machineIdleRows,
            ],
            'chart' => $chartSeries,
            'top_customers' => $topCustomers,
            'low_stock_items' => $lowStockItems,
            'birthdays_today' => $birthdaysToday,
            'birthdays_in_range' => $birthdaysInRange,
            'birthdays_this_month' => $birthdaysThisMonth,
            'reports_maintenance' => $reportsMaintenance,
            'reports_subscription' => $reportsSubscription,
            'free_reports_limited' => Auth::isTenantFreePlanRestricted(Auth::user()),
        ]);
    }

    public function dailyOuts(Request $request): Response
    {
        if (Auth::isTenantFreePlanRestricted(Auth::user())) {
            return json_response([
                'success' => false,
                'message' => 'Premium feature: detailed service and inventory-out reports are not available on Free access.',
            ], 403);
        }
        $tenantId = (int) Auth::user()['tenant_id'];
        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        $today = date('Y-m-d');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        if ($from === '' && $to === '') {
            $legacy = trim((string) $request->query('date', ''));
            if ($legacy !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $legacy) === 1) {
                $from = $legacy;
                $to = $legacy;
            } else {
                $from = $today;
                $to = $today;
            }
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = $today;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = $today;
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $dailyOutsData = $this->fetchDailyOutsData($pdo, $tenantId, $from, $to);

        return json_response([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'data' => $dailyOutsData['data'],
            'inventory_out' => $dailyOutsData['inventory_out'],
            'inventory_out_inclusion' => $dailyOutsData['inventory_out_inclusion'],
            'inventory_out_addon' => $dailyOutsData['inventory_out_addon'],
        ]);
    }

    public function exportExcel(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }

        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $pdo = App::db();
        LaundrySchema::ensure($pdo);
        $today = date('Y-m-d');
        [$from, $to] = $this->resolveReportRange($request, $today);
        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';

        $foldServiceAmount = $this->getBranchFoldServiceAmount($pdo, $tenantId);
        $foldCommissionTarget = $this->getBranchFoldCommissionTarget($pdo, $tenantId);
        $salesTotal = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0)
             FROM laundry_orders
             WHERE tenant_id = ?
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $discountsTotal = 0.0;
        if ($this->hasLaundryOrdersDiscountAmount($pdo)) {
            $discountsTotal = $this->scalarSum(
                $pdo,
                "SELECT COALESCE(SUM(discount_amount),0)
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND created_at BETWEEN ? AND ?
                   AND COALESCE(is_void, 0) = 0
                   AND status <> 'void'
                   AND (
                       status = 'paid'
                       OR (status = 'completed' AND payment_status = 'paid')
                   )",
                [$tenantId, $rangeStart, $rangeEnd]
            );
        }
        $foldOrdersCount = (int) $this->scalarSum(
            $pdo,
            "SELECT COUNT(*)
             FROM laundry_orders
             WHERE tenant_id = ?
               AND include_fold_service = 1
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND COALESCE(is_free, 0) = 0
               AND COALESCE(is_reward, 0) = 0
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $foldAmountTotal = $foldCommissionTarget === 'branch' ? ($foldServiceAmount * $foldOrdersCount) : 0.0;
        $grossSalesTotal = $salesTotal + $discountsTotal + $foldAmountTotal;
        $refundsTotal = 0.0;
        $manualExpensesTotal = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $expensesTotal = $manualExpensesTotal;
        $netSalesTotal = $grossSalesTotal - $refundsTotal - $discountsTotal - $expensesTotal;
        $grossProfit = $netSalesTotal;
        $isTodayOnly = ($from === $to && $from === $today);

        $st = $pdo->prepare(
            "SELECT
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'cash' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split') THEN COALESCE(split_cash_amount, 0)
                        ELSE 0
                    END
                ), 0) AS cash_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'card' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'card' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS card_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'gcash' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'gcash' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS gcash_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'paymaya' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'paymaya' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS paymaya_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('online_banking', 'online banking') THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'online_banking' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS online_banking_total,
                COALESCE(SUM(
                    CASE
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) = 'qr_payment' THEN total_amount
                        WHEN LOWER(TRIM(COALESCE(payment_method, 'cash'))) IN ('split_payment', 'split')
                             AND LOWER(TRIM(COALESCE(split_online_method, ''))) = 'qr_payment' THEN COALESCE(split_online_amount, 0)
                        ELSE 0
                    END
                ), 0) AS qr_payment_total
             FROM laundry_orders
             WHERE tenant_id = ?
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )"
        );
        $st->execute([$tenantId, $rangeStart, $rangeEnd]);
        $paymentRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $paymentsByMethod = [
            'cash' => (float) ($paymentRow['cash_total'] ?? 0),
            'card' => (float) ($paymentRow['card_total'] ?? 0),
            'gcash' => (float) ($paymentRow['gcash_total'] ?? 0),
            'paymaya' => (float) ($paymentRow['paymaya_total'] ?? 0),
            'online_banking' => (float) ($paymentRow['online_banking_total'] ?? 0),
            'qr_payment' => (float) ($paymentRow['qr_payment_total'] ?? 0),
        ];

        $orderTypeTotals = $this->fetchOrderTypeTotals($pdo, $tenantId, $rangeStart, $rangeEnd);
        $inventoryOutTotals = $this->fetchInventoryOutTotals($pdo, $tenantId, $rangeStart, $rangeEnd);
        $serviceModeSummary = $this->fetchServiceModeSummary($pdo, $tenantId, $rangeStart, $rangeEnd);
        $inventoryLedgerRows = $this->fetchInventoryLedgerRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineCreditLedgerRows = $this->fetchMachineCreditLedgerRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineCreditUsageRows = $this->fetchMachineCreditUsageRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $machineIdleRows = $this->fetchMachineIdleRows($pdo, $tenantId, $rangeStart, $rangeEnd);
        $dailyOutsData = $this->fetchDailyOutsData($pdo, $tenantId, $from, $to);
        $chartSeries = $this->buildChartSeries($pdo, $tenantId, $from, $to);

        $stTop = $pdo->prepare(
            'SELECT c.name, COUNT(o.id) AS frequency, COALESCE(SUM(o.total_amount),0) AS total_spending
             FROM laundry_customers c
             LEFT JOIN laundry_orders o ON o.customer_id = c.id AND o.tenant_id = c.tenant_id
                AND COALESCE(o.is_void, 0) = 0
                AND o.status <> "void"
                AND (
                    o.status = "paid"
                    OR (o.status = "completed" AND o.payment_status = "paid")
                ) AND o.created_at BETWEEN ? AND ?
             WHERE c.tenant_id = ?
             GROUP BY c.id
             ORDER BY frequency DESC, total_spending DESC, c.name ASC
             LIMIT 10'
        );
        $stTop->execute([$rangeStart, $rangeEnd, $tenantId]);
        $topCustomers = $stTop->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stBday = $pdo->prepare('SELECT name, birthday FROM laundry_customers WHERE tenant_id = ? AND birthday IS NOT NULL');
        $stBday->execute([$tenantId]);
        $birthdaysInRange = [];
        $rangeFromMd = date('m-d', strtotime($from) ?: time());
        $rangeToMd = date('m-d', strtotime($to) ?: time());
        foreach ($stBday->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $birthday = (string) ($row['birthday'] ?? '');
            if ($birthday === '' || strtotime($birthday) === false) {
                continue;
            }
            $md = date('m-d', strtotime($birthday));
            $inRange = $rangeFromMd <= $rangeToMd
                ? ($md >= $rangeFromMd && $md <= $rangeToMd)
                : ($md >= $rangeFromMd || $md <= $rangeToMd);
            if ($inRange) {
                $birthdaysInRange[] = $row;
            }
        }

        $sheetRows = [];
        $sheetRows['Sales amounts'] = [
            ['Metric', 'Value'],
            ['Range from', $from],
            ['Range to', $to],
            ['Gross sales', $grossSalesTotal],
            ['Refunds', $refundsTotal],
            ['Discounts', $discountsTotal],
            ['Fold amount', $foldAmountTotal],
            ['Expenses', $expensesTotal],
            ['Net sales', $netSalesTotal],
            ['Gross profit', $grossProfit],
        ];
        $sheetRows['Payments'] = [
            ['Payment method', 'Amount'],
            ['Cash '.($isTodayOnly ? 'today' : '(selected range)'), $paymentsByMethod['cash']],
            ['Card '.($isTodayOnly ? 'today' : '(selected range)'), $paymentsByMethod['card']],
            ['GCash '.($isTodayOnly ? 'today' : '(selected range)'), $paymentsByMethod['gcash']],
            ['PayMaya '.($isTodayOnly ? 'today' : '(selected range)'), $paymentsByMethod['paymaya']],
            ['Online banking '.($isTodayOnly ? 'today' : '(selected range)'), $paymentsByMethod['online_banking']],
            ['QR payment '.($isTodayOnly ? 'today' : '(selected range)'), $paymentsByMethod['qr_payment']],
        ];

        $sheetRows['Order type totals'] = [['Order type', 'Total ordered']];
        foreach ($orderTypeTotals as $row) {
            $sheetRows['Order type totals'][] = [(string) ($row['label'] ?? $row['code'] ?? 'Order type'), (float) ($row['qty'] ?? 0)];
        }
        $sheetRows['Top customers'] = [['Customer', 'Visits', 'Spending']];
        foreach ($topCustomers as $row) {
            $sheetRows['Top customers'][] = [(string) ($row['name'] ?? ''), (int) ($row['frequency'] ?? 0), (float) ($row['total_spending'] ?? 0)];
        }
        $sheetRows['Birthdays'] = [['Customer', 'Birthday']];
        foreach ($birthdaysInRange as $row) {
            $sheetRows['Birthdays'][] = [(string) ($row['name'] ?? ''), (string) ($row['birthday'] ?? '')];
        }
        $sheetRows['Inventory totals'] = [
            ['Metric', 'Qty out'],
            ['Inclusion items out', (float) ($inventoryOutTotals['inclusion_qty'] ?? 0)],
            ['Add-on items out', (float) ($inventoryOutTotals['addon_qty'] ?? 0)],
            ['Total items out', (float) ($inventoryOutTotals['total_qty'] ?? 0)],
        ];
        $sheetRows['Services sold'] = [['Service type', 'Qty sold', 'Amount']];
        foreach ($dailyOutsData['data'] as $row) {
            $sheetRows['Services sold'][] = [(string) ($row['product_name'] ?? ''), (float) ($row['qty'] ?? 0), (float) ($row['line_amount'] ?? 0)];
        }
        $sheetRows['Daily sales'] = [['Date', 'Sales', 'Expenses', 'Net']];
        $dailyDates = array_values((array) ($chartSeries['dates'] ?? []));
        $dailySales = array_values((array) ($chartSeries['sales'] ?? []));
        $dailyExpenses = array_values((array) ($chartSeries['expenses'] ?? []));
        $dailyNet = array_values((array) ($chartSeries['profit'] ?? []));
        $nDaily = count($dailyDates);
        for ($i = 0; $i < $nDaily; $i++) {
            $sheetRows['Daily sales'][] = [
                (string) ($dailyDates[$i] ?? ''),
                (float) ($dailySales[$i] ?? 0),
                (float) ($dailyExpenses[$i] ?? 0),
                (float) ($dailyNet[$i] ?? 0),
            ];
        }
        $sheetRows['Inventory items out'] = [['Item name', 'Qty out', 'Amount']];
        foreach ($dailyOutsData['inventory_out'] as $row) {
            $sheetRows['Inventory items out'][] = [(string) ($row['item_name'] ?? ''), (float) ($row['qty_out'] ?? 0), (float) ($row['amount_out'] ?? 0)];
        }
        $sheetRows['Inclusion items out'] = [['Item name', 'Qty out']];
        foreach ($dailyOutsData['inventory_out_inclusion'] as $row) {
            $sheetRows['Inclusion items out'][] = [(string) ($row['item_name'] ?? ''), (float) ($row['qty_out'] ?? 0)];
        }
        $sheetRows['Addon items out'] = [['Item name', 'Qty out']];
        foreach ($dailyOutsData['inventory_out_addon'] as $row) {
            $sheetRows['Addon items out'][] = [(string) ($row['item_name'] ?? ''), (float) ($row['qty_out'] ?? 0)];
        }
        $sheetRows['Items by service mode'] = [['Service mode', 'Count']];
        foreach ($serviceModeSummary as $row) {
            $sheetRows['Items by service mode'][] = [(string) ($row['label'] ?? ''), (int) ($row['count'] ?? 0)];
        }
        $sheetRows['Inventory ledger'] = [['Item', 'Opening', 'Stock In', 'Stock Out', 'Closing', 'Stocks left']];
        foreach ($inventoryLedgerRows as $row) {
            $closing = (float) ($row['closing'] ?? 0);
            $sheetRows['Inventory ledger'][] = [
                (string) ($row['item_name'] ?? ''),
                (float) ($row['opening'] ?? 0),
                (float) ($row['stock_in'] ?? 0),
                (float) ($row['stock_out'] ?? 0),
                $closing,
                $closing,
            ];
        }
        $sheetRows['Machine credits ledger'] = [['Machine', 'Opening', 'Restock', 'Usage (Out)', 'Closing']];
        foreach ($machineCreditLedgerRows as $row) {
            $sheetRows['Machine credits ledger'][] = [
                (string) ($row['machine_label'] ?? ''),
                (float) ($row['opening'] ?? 0),
                (float) ($row['restock'] ?? 0),
                (float) ($row['usage'] ?? 0),
                (float) ($row['closing'] ?? 0),
            ];
        }
        $sheetRows['Machine credit usage by machine'] = [['Machine', 'Usage count', 'Deducted from overall credits']];
        foreach ($machineCreditUsageRows as $row) {
            $sheetRows['Machine credit usage by machine'][] = [
                (string) ($row['machine_label'] ?? ''),
                (int) ($row['usage_count'] ?? 0),
                (float) ($row['deducted_credits'] ?? 0),
            ];
        }
        $sheetRows['Machine idle time'] = [['Machine', 'Idle hours', 'Idle gaps', 'Longest idle (hours)', 'Longest idle range', 'Usage logs']];
        foreach ($machineIdleRows as $row) {
            $sheetRows['Machine idle time'][] = [
                (string) ($row['machine_label'] ?? ''),
                (float) ($row['idle_hours'] ?? 0),
                (int) ($row['idle_gaps'] ?? 0),
                (float) ($row['longest_idle_hours'] ?? 0),
                (string) ($row['longest_idle_range'] ?? ''),
                (int) ($row['usage_logs'] ?? 0),
            ];
        }

        $xml = $this->buildSpreadsheetXml($sheetRows);
        $filename = 'reports-'.$from.'-to-'.$to.'.xls';
        return new Response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * @return array{data:list<array<string,mixed>>,inventory_out:list<array<string,mixed>>,inventory_out_inclusion:list<array<string,mixed>>,inventory_out_addon:list<array<string,mixed>>}
     */
    private function fetchDailyOutsData(PDO $pdo, int $tenantId, string $from, string $to): array
    {
        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';
        $st = $pdo->prepare(
            "SELECT order_type AS service_type,
                    COUNT(*) AS qty,
                    COALESCE(SUM(total_amount),0) AS line_amount
             FROM laundry_orders
             WHERE tenant_id = ?
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )
             GROUP BY order_type
             ORDER BY qty DESC, service_type ASC
             LIMIT 200"
        );
        $st->execute([$tenantId, $rangeStart, $rangeEnd]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'product_id' => 0,
                'product_name' => ucwords(str_replace('_', ' ', (string) ($row['service_type'] ?? ''))),
                'qty' => (float) ($row['qty'] ?? 0),
                'line_amount' => round_money((float) ($row['line_amount'] ?? 0)),
            ];
        }
        $inventoryOutMap = [];
        $inclusionOutMap = [];
        $addonOutMap = [];
        $addInventoryOut = static function (array &$map, string $name, float $qty, float $amount): void {
            $label = trim($name);
            if ($label === '') {
                return;
            }
            if (! isset($map[$label])) {
                $map[$label] = ['item_name' => $label, 'qty_out' => 0.0, 'amount_out' => 0.0];
            }
            $map[$label]['qty_out'] += $qty;
            $map[$label]['amount_out'] += $amount;
        };
        $inclusionQueries = [
            ['col' => 'o.inclusion_detergent_item_id', 'fallback' => 'Detergent'],
            ['col' => 'o.inclusion_fabcon_item_id', 'fallback' => 'Fabric conditioner'],
            ['col' => 'o.inclusion_bleach_item_id', 'fallback' => 'Bleach'],
        ];
        foreach ($inclusionQueries as $cfg) {
            $q = $pdo->prepare(
                "SELECT COALESCE(NULLIF(TRIM(i.name), ''), ?) AS item_name,
                        COUNT(*) AS qty_out,
                        COALESCE(SUM(COALESCE(i.unit_cost, 0)),0) AS amount_out
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
            $q->execute([(string) $cfg['fallback'], $tenantId, $rangeStart, $rangeEnd]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $itemName = (string) ($row['item_name'] ?? '');
                $qtyOut = (float) ($row['qty_out'] ?? 0);
                $amountOut = (float) ($row['amount_out'] ?? 0);
                $addInventoryOut($inventoryOutMap, $itemName, $qtyOut, $amountOut);
                $addInventoryOut($inclusionOutMap, $itemName, $qtyOut, $amountOut);
            }
        }
        $addonSt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(ao.item_name), ''), 'Add-on item') AS item_name,
                    COALESCE(SUM(ao.quantity),0) AS qty_out,
                    COALESCE(SUM(ao.total_price),0) AS amount_out
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
        foreach ($addonSt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $itemName = (string) ($row['item_name'] ?? '');
            $qtyOut = (float) ($row['qty_out'] ?? 0);
            $amountOut = (float) ($row['amount_out'] ?? 0);
            $addInventoryOut($inventoryOutMap, $itemName, $qtyOut, $amountOut);
            $addInventoryOut($addonOutMap, $itemName, $qtyOut, $amountOut);
        }
        $inventoryOutRows = array_values($inventoryOutMap);
        $inclusionOutRows = array_values($inclusionOutMap);
        $addonOutRows = array_values($addonOutMap);
        $sortRows = static function (array $rows): array {
            usort($rows, static function (array $a, array $b): int {
                $qtyCmp = (float) ($b['qty_out'] ?? 0) <=> (float) ($a['qty_out'] ?? 0);
                if ($qtyCmp !== 0) {
                    return $qtyCmp;
                }
                return strcmp((string) ($a['item_name'] ?? ''), (string) ($b['item_name'] ?? ''));
            });
            return $rows;
        };
        return [
            'data' => $rows,
            'inventory_out' => $sortRows($inventoryOutRows),
            'inventory_out_inclusion' => $sortRows($inclusionOutRows),
            'inventory_out_addon' => $sortRows($addonOutRows),
        ];
    }

    /** @param array<string,list<list<mixed>>> $sheetRows */
    private function buildSpreadsheetXml(array $sheetRows): string
    {
        $sheetsXml = '';
        $sheetIndex = 1;
        foreach ($sheetRows as $sheetName => $rows) {
            $safeName = preg_replace('/[\x00-\x1F:\*\\\?\/\[\]]+/', ' ', $sheetName) ?? 'Sheet '.$sheetIndex;
            $safeName = trim(substr($safeName, 0, 31));
            if ($safeName === '') {
                $safeName = 'Sheet '.$sheetIndex;
            }
            $rowsXml = '';
            foreach ($rows as $rIdx => $row) {
                $cellsXml = '';
                foreach ($row as $cell) {
                    $isNumeric = is_int($cell) || is_float($cell);
                    $type = $isNumeric ? 'Number' : 'String';
                    $value = $isNumeric
                        ? (string) (is_float($cell) ? round_money($cell) : $cell)
                        : htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $style = $rIdx === 0 ? ' ss:StyleID="header"' : '';
                    $cellsXml .= '<Cell'.$style.'><Data ss:Type="'.$type.'">'.$value.'</Data></Cell>';
                }
                $rowsXml .= '<Row>'.$cellsXml.'</Row>';
            }
            $sheetsXml .= '<Worksheet ss:Name="'.htmlspecialchars($safeName, ENT_XML1 | ENT_QUOTES, 'UTF-8').'">'
                .'<Table>'.$rowsXml.'</Table></Worksheet>';
            $sheetIndex++;
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<?mso-application progid="Excel.Sheet"?>'
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:o="urn:schemas-microsoft-com:office:office"'
            .' xmlns:x="urn:schemas-microsoft-com:office:excel"'
            .' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
            .' xmlns:html="http://www.w3.org/TR/REC-html40">'
            .'<Styles>'
            .'<Style ss:ID="header"><Font ss:Bold="1"/></Style>'
            .'</Styles>'
            .$sheetsXml
            .'</Workbook>';
    }

    private function scalarSum(PDO $pdo, string $sql, array $params): float
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return (float) ($st->fetchColumn() ?: 0.0);
    }

    /**
     * @return array{inclusion_qty:float,addon_qty:float,total_qty:float}
     */
    private function fetchInventoryOutTotals(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        $addonQty = $this->scalarSum(
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
            $totalQty = $this->scalarSum(
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
            $inclusionFallback = $this->scalarSum(
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
                $stockIn = max(0.0, (float) ($inRangeByItem[$itemId] ?? 0.0));
                $stockOut = max(0.0, (float) ($outRangeByItem[$itemId] ?? 0.0));
                $rows[] = [
                    'item_name' => (string) ($item['name'] ?? 'Item'),
                    'opening' => $opening,
                    'stock_in' => $stockIn,
                    'stock_out' => $stockOut,
                    'closing' => $closing,
                ];
            }
        } catch (\Throwable) {
            return [];
        }
        return $rows;
    }

    /**
     * @return list<array{machine_label:string,credit_required:int,opening:float,restock:float,usage:float,closing:float}>
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
     * @return list<array{machine_label:string,usage_count:int,deducted_credits:float}>
     */
    private function fetchMachineCreditUsageRows(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT id, machine_label, credit_required
                 FROM laundry_machines
                 WHERE tenant_id = ?
                 ORDER BY machine_kind ASC, machine_label ASC, id ASC'
            );
            $st->execute([$tenantId]);
            $machines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($machines === []) {
                return [];
            }
            $rowsByMachineId = [];
            foreach ($machines as $machine) {
                $machineId = (int) ($machine['id'] ?? 0);
                if ($machineId < 1 || (int) ($machine['credit_required'] ?? 0) !== 1) {
                    continue;
                }
                $rowsByMachineId[$machineId] = [
                    'machine_label' => (string) ($machine['machine_label'] ?? 'Machine'),
                    'usage_count' => 0,
                    'deducted_credits' => 0.0,
                ];
            }
            if ($rowsByMachineId === []) {
                return [];
            }
            $orderSt = $pdo->prepare(
                'SELECT washer_machine_id, dryer_machine_id, wash_qty
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND created_at BETWEEN ? AND ?
                   AND COALESCE(is_void, 0) = 0
                   AND status <> "void"
                   AND (
                       (washer_machine_id IS NOT NULL AND washer_machine_id > 0)
                       OR (dryer_machine_id IS NOT NULL AND dryer_machine_id > 0)
                   )'
            );
            $orderSt->execute([$tenantId, $rangeStart, $rangeEnd]);
            foreach ($orderSt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $order) {
                $usageQty = max(1, (int) ($order['wash_qty'] ?? 1));
                $machineIds = [];
                $washerId = (int) ($order['washer_machine_id'] ?? 0);
                $dryerId = (int) ($order['dryer_machine_id'] ?? 0);
                if ($washerId > 0) {
                    $machineIds[] = $washerId;
                }
                if ($dryerId > 0 && $dryerId !== $washerId) {
                    $machineIds[] = $dryerId;
                }
                foreach ($machineIds as $machineId) {
                    if (! isset($rowsByMachineId[$machineId])) {
                        continue;
                    }
                    $rowsByMachineId[$machineId]['usage_count']++;
                    $rowsByMachineId[$machineId]['deducted_credits'] += (float) $usageQty;
                }
            }

            return array_values($rowsByMachineId);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{machine_label:string,idle_hours:float,idle_gaps:int,longest_idle_hours:float,longest_idle_range:string,usage_logs:int}>
     */
    private function fetchMachineIdleRows(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT id, machine_label, machine_kind
                 FROM laundry_machines
                 WHERE tenant_id = ?
                 ORDER BY machine_kind ASC, machine_label ASC, id ASC'
            );
            $st->execute([$tenantId]);
            $machines = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($machines === []) {
                return [];
            }

            $usageByMachine = [];
            $usageQueries = [
                ['machine_col' => 'washer_machine_id', 'minutes_expr' => 'COALESCE(o.wash_qty, 0) * 30'],
                ['machine_col' => 'dryer_machine_id', 'minutes_expr' => 'COALESCE(o.dry_minutes, 0)'],
                ['machine_col' => 'machine_id', 'minutes_expr' => 'COALESCE(o.wash_qty, 0) * 30 + COALESCE(o.dry_minutes, 0)'],
            ];

            foreach ($usageQueries as $cfg) {
                $q = $pdo->prepare(
                    "SELECT o.{$cfg['machine_col']} AS machine_id, o.created_at,
                            {$cfg['minutes_expr']} AS usage_minutes
                     FROM laundry_orders o
                     WHERE o.tenant_id = ?
                       AND o.created_at BETWEEN ? AND ?
                       AND o.{$cfg['machine_col']} IS NOT NULL
                       AND o.{$cfg['machine_col']} > 0
                       AND COALESCE(o.is_void, 0) = 0
                       AND o.status <> 'void'
                       AND (
                           o.status = 'paid'
                           OR (o.status = 'completed' AND o.payment_status = 'paid')
                           OR o.status = 'open_ticket'
                           OR o.status = 'washing_drying'
                           OR o.status = 'running'
                           OR o.status = 'pending'
                       )
                     ORDER BY o.created_at ASC, o.id ASC"
                );
                $q->execute([$tenantId, $rangeStart, $rangeEnd]);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $machineId = (int) ($row['machine_id'] ?? 0);
                    if ($machineId < 1) {
                        continue;
                    }
                    $createdAt = (string) ($row['created_at'] ?? '');
                    $startTs = strtotime($createdAt);
                    if ($createdAt === '' || $startTs === false) {
                        continue;
                    }
                    $usageMinutes = max(0.0, (float) ($row['usage_minutes'] ?? 0));
                    if ($usageMinutes <= 0.000001) {
                        $usageMinutes = 1.0;
                    }
                    $endTs = $startTs + (int) round($usageMinutes * 60);
                    $usageByMachine[$machineId][] = [
                        'start_ts' => $startTs,
                        'end_ts' => $endTs,
                    ];
                }
            }

            $rangeStartTs = strtotime($rangeStart);
            $rangeEndTs = strtotime($rangeEnd);
            if ($rangeStartTs === false || $rangeEndTs === false || $rangeEndTs < $rangeStartTs) {
                return [];
            }

            $rows = [];
            foreach ($machines as $machine) {
                $machineId = (int) ($machine['id'] ?? 0);
                $events = $usageByMachine[$machineId] ?? [];
                usort($events, static fn (array $a, array $b): int => ($a['start_ts'] <=> $b['start_ts']) ?: ($a['end_ts'] <=> $b['end_ts']));
                $idleSeconds = 0;
                $idleGaps = 0;
                $longestIdle = 0;
                $longestRange = '';
                $cursor = $rangeStartTs;
                foreach ($events as $event) {
                    $start = max($rangeStartTs, (int) ($event['start_ts'] ?? $rangeStartTs));
                    $end = min($rangeEndTs, max($start, (int) ($event['end_ts'] ?? $start)));
                    if ($start > $cursor) {
                        $gap = $start - $cursor;
                        $idleSeconds += $gap;
                        $idleGaps++;
                        if ($gap > $longestIdle) {
                            $longestIdle = $gap;
                            $longestRange = date('Y-m-d H:i', $cursor).' -> '.date('Y-m-d H:i', $start);
                        }
                    }
                    if ($end > $cursor) {
                        $cursor = $end;
                    }
                }
                if ($cursor < $rangeEndTs) {
                    $gap = $rangeEndTs - $cursor;
                    $idleSeconds += $gap;
                    $idleGaps++;
                    if ($gap > $longestIdle) {
                        $longestIdle = $gap;
                        $longestRange = date('Y-m-d H:i', $cursor).' -> '.date('Y-m-d H:i', $rangeEndTs);
                    }
                }
                $rows[] = [
                    'machine_label' => (string) ($machine['machine_label'] ?? ''),
                    'idle_hours' => round($idleSeconds / 3600, 2),
                    'idle_gaps' => $idleGaps,
                    'longest_idle_hours' => round($longestIdle / 3600, 2),
                    'longest_idle_range' => $longestRange,
                    'usage_logs' => count($events),
                ];
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    private function hasLaundryOrdersDiscountAmount(PDO $pdo): bool
    {
        if (self::$hasLaundryOrdersDiscountAmount !== null) {
            return self::$hasLaundryOrdersDiscountAmount;
        }
        try {
            $st = $pdo->query("SHOW COLUMNS FROM `laundry_orders` LIKE 'discount_amount'");
            self::$hasLaundryOrdersDiscountAmount = $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            self::$hasLaundryOrdersDiscountAmount = false;
        }

        return self::$hasLaundryOrdersDiscountAmount;
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

    public function transactions(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $canPrintReceipt = ! Auth::isTenantFreePlanRestricted($user);
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 't.tenant_id = ?';
            $params = [$tenantId];
            $statusFilter = trim((string) $request->input('status', ''));
            if ($statusFilter !== '') {
                $allowed = ['completed', 'pending', 'void'];
                if (in_array($statusFilter, $allowed, true)) {
                    $where .= ' AND t.status = ?';
                    $params[] = $statusFilter;
                }
            }
            $paymentFilter = strtolower(trim((string) $request->input('payment_method', '')));
            if ($paymentFilter !== '') {
                $allowedPaymentMethods = [
                    'cash',
                    'card',
                    'gcash',
                    'paymaya',
                    'online_banking',
                    'online banking', // legacy rows
                    'split',
                    'free',
                ];
                if (in_array($paymentFilter, $allowedPaymentMethods, true)) {
                    $where .= ' AND LOWER(TRIM(COALESCE(t.payment_method, \'\'))) = ?';
                    $params[] = $paymentFilter;
                }
            }
            $dateFilter = trim((string) $request->input('date', ''));
            if ($dateFilter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter) === 1) {
                $where .= ' AND DATE(t.created_at) = ?';
                $params[] = $dateFilter;
            }
            if ($search !== '') {
                $where .= ' AND (t.id LIKE ? OR t.status LIKE ? OR EXISTS (SELECT 1 FROM users u WHERE u.id = t.user_id AND u.name LIKE ?))';
                $like = '%'.$search.'%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $stTotal = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE tenant_id = ?');
            $stTotal->execute([$tenantId]);
            $total = (int) $stTotal->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM transactions t WHERE $where");
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            // Column 0 is responsive control; remaining follow the table header order.
            $orderCol = (int) data_get($request->all(), 'order.0.column', 1);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $orderMap = [
                1 => 't.id',
                2 => 't.created_at',
                3 => 'u.name',
                4 => 'qty_sum',
                5 => 't.total_amount',
                6 => 't.payment_method',
                7 => 't.amount_paid',
                8 => 't.change_amount',
                9 => 't.status',
            ];
            $orderBy = $orderMap[$orderCol] ?? 't.created_at';
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT t.*, u.name AS cashier_name,
                    (SELECT COALESCE(SUM(quantity),0) FROM transaction_items ti WHERE ti.transaction_id = t.id AND ti.tenant_id = t.tenant_id) AS qty_sum
                    FROM transactions t
                    LEFT JOIN users u ON u.id = t.user_id
                    WHERE $where
                    ORDER BY $orderBy $orderDir
                    LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $trow) {
                $tid = (int) $trow['id'];
                $stItems = $pdo->prepare(
                    'SELECT ti.*, p.name AS product_name FROM transaction_items ti
                     LEFT JOIN products p ON p.id = ti.product_id
                     WHERE ti.tenant_id = ? AND ti.transaction_id = ?'
                );
                $stItems->execute([$tenantId, $tid]);
                $items = $stItems->fetchAll(PDO::FETCH_ASSOC);
                $itemsHtml = '<ul class="mb-0">';
                $hasVoidedLines = false;
                foreach ($items as $it) {
                    if ((int) ($it['quantity'] ?? 0) <= 0) {
                        $hasVoidedLines = true;
                    }
                    $lineName = (string) ($it['product_name'] ?? '');
                    $flavorName = trim((string) ($it['flavor_name'] ?? ''));
                    if ($flavorName !== '') {
                        $lineName .= ' - '.$flavorName;
                    }
                    $itemsHtml .= '<li>'.e($lineName).' - Qty '.e(format_stock((float) $it['quantity']))
                        .' x '.e(format_money((float) $it['unit_price'])).' = '.e(format_money((float) $it['line_total'])).'</li>';
                }
                $itemsHtml .= '</ul>';

                $status = (string) ($trow['status'] ?? '');
                if ($status === 'void') {
                    $details = '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemsModal'.$tid.'" title="View items"><i class="fa fa-eye"></i></button>'
                        .'<div class="modal fade" id="itemsModal'.$tid.'" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
                        .'<div class="modal-header"><h6 class="modal-title">Purchased Items #'.$tid.'</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>'
                        .'<div class="modal-body">'.$itemsHtml.'</div></div></div></div>';
                } elseif ($status === 'pending') {
                    $pendingName = trim((string) ($trow['pending_name'] ?? ''));
                    $pendingContact = trim((string) ($trow['pending_contact'] ?? ''));
                    $details = '<div class="d-flex gap-1 justify-content-center flex-wrap">'
                        .'<button type="button" class="btn btn-sm btn-success js-pay-pending" data-id="'.$tid.'" data-name="'.e($pendingName).'" data-contact="'.e($pendingContact).'" data-total="'.e((string) ($trow['total_amount'] ?? 0)).'" title="Pay pending and print customer receipt"><i class="fa fa-money-bill-wave"></i></button>'
                        .'<button type="button" class="btn btn-sm btn-outline-secondary js-reprint-receipt" data-receipt-url="'.e(route('tenant.transactions.receipt', ['id' => $tid])).'" title="Print unpaid order (UNPAID watermark)"><i class="fa fa-print"></i></button>'
                        .'<button type="button" class="btn btn-sm btn-outline-danger js-edit-transaction" data-edit-url="'.e(route('tenant.transactions.edit-data', ['id' => $tid])).'" title="Edit items"><i class="fa fa-ban"></i></button>'
                        .'</div>';
                } else {
                    $details = '<div class="d-flex gap-1 justify-content-center">'
                        .'<button type="button" class="btn btn-sm btn-outline-secondary js-reprint-receipt" data-receipt-url="'.e(route('tenant.transactions.receipt', ['id' => $tid])).'" title="Reprint receipt"><i class="fa fa-receipt"></i></button>'
                        .($status === 'completed'
                            ? '<button type="button" class="btn btn-sm btn-outline-danger js-edit-transaction" data-edit-url="'.e(route('tenant.transactions.edit-data', ['id' => $tid])).'" title="Void items"><i class="fa fa-ban"></i></button>'
                            : '')
                        .'</div>';
                }

                $action = '';
                if ($user['role'] === 'tenant_admin') {
                    $isVoid = $status === 'void';
                    $isPending = $status === 'pending';
                    if ($isPending) {
                        $action = '<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Pending orders cannot be cancelled">'
                            .'<i class="fa fa-circle-xmark"></i></button>';
                    } else {
                        $action = '<form method="POST" action="'.e(url('/tenant/transactions/'.$tid)).'">'
                            .csrf_field().method_field('DELETE')
                            .'<button class="btn btn-sm '.($isVoid ? 'btn-success' : 'btn-danger').'" title="'.($isVoid ? 'Restore order' : 'Cancel order').'">'
                            .'<i class="fa '.($isVoid ? 'fa-rotate-left' : 'fa-circle-xmark').'"></i></button></form>';
                    }
                }

                $statusText = $status === 'void' ? 'cancelled' : $status;
                if ($status === 'completed' && $hasVoidedLines) {
                    // Short, clear indicator that some line items were voided.
                    $statusText = 'completed (voided)';
                }
                $badgeClass = $status === 'void' ? 'text-bg-danger' : ($status === 'pending' ? 'text-bg-warning' : 'text-bg-success');
                $pmRaw = strtolower(trim((string) ($trow['payment_method'] ?? '')));
                $pm = strtoupper(str_replace('_', ' ', $pmRaw));
                // Net first payment: cash uses amount_paid (net to order at checkout) when set — after edits
                // change_amount may be 0 so tendered−change would wrongly equal full tendered (e.g. 500 vs 364).
                $ap = (float) ($trow['amount_paid'] ?? 0);
                $basePaid = $pmRaw === 'cash'
                    ? ($ap > money_epsilon() ? $ap : max(0.0, (float) ($trow['amount_tendered'] ?? 0) - (float) ($trow['change_amount'] ?? 0)))
                    : $ap;
                $refunded = (float) ($trow['refunded_amount'] ?? 0);
                $added = (float) ($trow['added_paid_amount'] ?? 0);
                $netPaid = max(0.0, $basePaid + $added - $refunded);
                $change = (float) ($trow['change_amount'] ?? 0);
                if ($refunded > 0 && $added > 0) {
                    // Should not happen with new logic, but render clearly if legacy data exists.
                    $adjustHtml = '<div class="small text-danger">Refunded '.e(format_money($refunded)).'</div>'
                        .'<div class="small text-success">Additional '.e(format_money($added)).'</div>';
                } elseif ($refunded > 0) {
                    $adjustHtml = '<span class="text-danger">Refunded '.e(format_money($refunded)).'</span>';
                } elseif ($added > 0) {
                    $adjustHtml = '<span class="text-success">Additional '.e(format_money($added)).'</span>';
                } elseif ($change > 0) {
                    $adjustHtml = '<span>'.e(format_money($change)).'</span>';
                } else {
                    $adjustHtml = '<span class="text-muted">0.00</span>';
                }

                $data[] = [
                    'id' => $tid,
                    'date' => $trow['created_at'] ? date('M d, Y h:i A', strtotime((string) $trow['created_at'])) : '',
                    'cashier' => e((string) ($trow['cashier_name'] ?? 'N/A')),
                    'qty' => (float) ($trow['qty_sum'] ?? 0),
                    'total' => format_money((float) $trow['total_amount']),
                    'payment_method' => $pm !== '' ? e($pm) : '-',
                    // Show NET PAID so void/restore loops don't "inflate" paid display.
                    'amount_paid' => format_money($netPaid),
                    'change_amount' => $adjustHtml,
                    'status' => '<span class="badge '.$badgeClass.'">'.e((string) $statusText).'</span>',
                    'details' => $details,
                    'action' => $action,
                ];
            }

            return json_response([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
            ]);
        }

        return view_page('Transactions', 'tenant.transactions.index', array_merge(
            thermal_receipt_client_config('transactions'),
            ['receipt_print_allowed' => $canPrintReceipt]
        ));
    }

    public function editData(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (Auth::isTenantFreePlanRestricted($user)) {
            return json_response(['success' => false, 'message' => 'Premium feature: editing receipt/transaction data is not available on the Free version.'], 403);
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $txId = (int) $id;
        if ($tenantId < 1 || $txId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $st = $pdo->prepare("SELECT id, status, total_amount, original_total_amount, payment_method, amount_paid, amount_tendered, change_amount, refunded_amount, added_paid_amount, created_at FROM transactions WHERE tenant_id = ? AND id = ? LIMIT 1");
        $st->execute([$tenantId, $txId]);
        $tx = $st->fetch(PDO::FETCH_ASSOC);
        if (! $tx) {
            return json_response(['success' => false, 'message' => 'Transaction not found.'], 404);
        }
        $txStatus = (string) ($tx['status'] ?? '');
        if (! in_array($txStatus, ['completed', 'pending'], true)) {
            return json_response(['success' => false, 'message' => 'Only completed or pending transactions can be edited.'], 422);
        }
        $st = $pdo->prepare(
            'SELECT ti.id AS item_id, ti.product_id, ti.flavor_ingredient_id, ti.flavor_name, ti.quantity, ti.unit_price, ti.line_total, p.name AS product_name
             FROM transaction_items ti
             INNER JOIN products p ON p.id = ti.product_id AND p.tenant_id = ti.tenant_id
             WHERE ti.tenant_id = ? AND ti.transaction_id = ?
             ORDER BY ti.id ASC'
        );
        $st->execute([$tenantId, $txId]);
        $items = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'item_id' => (int) ($row['item_id'] ?? 0),
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'flavor_ingredient_id' => (int) ($row['flavor_ingredient_id'] ?? 0),
                'flavor_name' => (string) ($row['flavor_name'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_total' => (float) ($row['line_total'] ?? 0),
            ];
        }

        // Products list for replacement/add (active only to avoid selling inactive).
        $st = $pdo->prepare('SELECT id, name, price FROM products WHERE tenant_id = ? AND is_active = 1 AND COALESCE(has_flavor_options,0) = 0 ORDER BY name ASC');
        $st->execute([$tenantId]);
        $products = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $products[] = [
                'id' => (int) ($p['id'] ?? 0),
                'name' => (string) ($p['name'] ?? ''),
                'price' => (float) ($p['price'] ?? 0),
            ];
        }

        return json_response([
            'success' => true,
            'transaction' => [
                'id' => $txId,
                'status' => (string) ($tx['status'] ?? ''),
                'total_amount' => (float) ($tx['total_amount'] ?? 0),
                'original_total_amount' => array_key_exists('original_total_amount', $tx) && $tx['original_total_amount'] !== null ? (float) $tx['original_total_amount'] : null,
                'payment_method' => (string) ($tx['payment_method'] ?? ''),
                'amount_paid' => (float) ($tx['amount_paid'] ?? 0),
                'amount_tendered' => array_key_exists('amount_tendered', $tx) && $tx['amount_tendered'] !== null ? (float) $tx['amount_tendered'] : null,
                'change_amount' => array_key_exists('change_amount', $tx) && $tx['change_amount'] !== null ? (float) $tx['change_amount'] : null,
                'refunded_amount' => array_key_exists('refunded_amount', $tx) ? (float) ($tx['refunded_amount'] ?? 0) : 0.0,
                'added_paid_amount' => array_key_exists('added_paid_amount', $tx) ? (float) ($tx['added_paid_amount'] ?? 0) : 0.0,
                'created_at' => $tx['created_at'] ?? null,
            ],
            'items' => $items,
            'products' => $products,
        ]);
    }

    public function editItems(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (Auth::isTenantFreePlanRestricted($user)) {
            return json_response(['success' => false, 'message' => 'Premium feature: editing receipt/transaction data is not available on the Free version.'], 403);
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $txId = (int) $id;
        if ($tenantId < 1 || $txId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        $existingItems = $request->input('existing_items', []);
        $addItems = $request->input('add_items', []);
        $refundOverrideRaw = $request->input('refund_amount');
        $additionalOverrideRaw = $request->input('additional_paid_amount');
        $refundOverride = is_numeric($refundOverrideRaw) ? max(0.0, (float) $refundOverrideRaw) : null;
        $additionalOverride = is_numeric($additionalOverrideRaw) ? max(0.0, (float) $additionalOverrideRaw) : null;
        if (! is_array($existingItems)) {
            $existingItems = [];
        }
        if (! is_array($addItems)) {
            $addItems = [];
        }
        $existingWanted = [];
        foreach ($existingItems as $it) {
            if (! is_array($it)) {
                continue;
            }
            $itemId = (int) ($it['item_id'] ?? 0);
            $qty = (int) ($it['quantity'] ?? 0);
            if ($itemId > 0) {
                $existingWanted[$itemId] = max(0, $qty);
            }
        }

        $adds = [];
        foreach ($addItems as $it) {
            if (! is_array($it)) {
                continue;
            }
            $pid = (int) ($it['product_id'] ?? 0);
            $qty = max(1, (int) ($it['quantity'] ?? 0));
            if ($pid > 0) {
                $adds[] = ['product_id' => $pid, 'quantity' => $qty];
            }
        }
        if ($existingWanted === [] && $adds === []) {
            return json_response(['success' => false, 'message' => 'No changes provided.'], 422);
        }

        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT id, status, total_amount, original_total_amount, payment_method, amount_paid, amount_tendered, change_amount, refunded_amount, added_paid_amount FROM transactions WHERE tenant_id = ? AND id = ? FOR UPDATE');
            $st->execute([$tenantId, $txId]);
            $tx = $st->fetch(PDO::FETCH_ASSOC);
            if (! $tx) {
                throw new RuntimeException('Transaction not found.');
            }
            $txStatus = (string) ($tx['status'] ?? '');
            if (! in_array($txStatus, ['completed', 'pending'], true)) {
                throw new RuntimeException('Only completed or pending transactions can be edited.');
            }
            $isPending = $txStatus === 'pending';
            $oldTotal = (float) ($tx['total_amount'] ?? 0);
            $paymentMethod = (string) ($tx['payment_method'] ?? 'cash');
            $originalTotal = array_key_exists('original_total_amount', $tx) && $tx['original_total_amount'] !== null
                ? (float) $tx['original_total_amount']
                : null;
            // Backfill for older rows: prefer amount_paid for cash (net at checkout); else tendered−change.
            if ($originalTotal === null) {
                $ap0 = (float) ($tx['amount_paid'] ?? 0);
                if ($paymentMethod === 'cash' && $ap0 > money_epsilon()) {
                    $originalTotal = $ap0;
                } elseif ($paymentMethod === 'cash' && $tx['amount_tendered'] !== null && $tx['change_amount'] !== null) {
                    $originalTotal = max(0.0, (float) $tx['amount_tendered'] - (float) $tx['change_amount']);
                } else {
                    $originalTotal = (float) ($tx['total_amount'] ?? 0);
                }
            }

            $addedPrev = (float) ($tx['added_paid_amount'] ?? 0);
            $refundPrev = (float) ($tx['refunded_amount'] ?? 0);
            $pmLower = strtolower(trim($paymentMethod));
            $apNet = (float) ($tx['amount_paid'] ?? 0);
            $basePaidNet = $pmLower === 'cash'
                ? ($apNet > money_epsilon() ? $apNet : max(0.0, (float) ($tx['amount_tendered'] ?? 0) - (float) ($tx['change_amount'] ?? 0)))
                : $apNet;

            $st = $pdo->prepare('SELECT id, product_id, flavor_ingredient_id, flavor_name, flavor_quantity_required, quantity, unit_price, line_total FROM transaction_items WHERE tenant_id = ? AND transaction_id = ?');
            $st->execute([$tenantId, $txId]);
            $existing = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $itemId = (int) ($row['id'] ?? 0);
                $pid = (int) ($row['product_id'] ?? 0);
                if ($itemId < 1 || $pid < 1) {
                    continue;
                }
                // Skip already-voided history lines for the "active set" used in totals/refund computations.
                $q = (int) ($row['quantity'] ?? 0);
                if ($q <= 0) {
                    continue;
                }
                $existing[$itemId] = [
                    'item_id' => $itemId,
                    'product_id' => $pid,
                    'flavor_ingredient_id' => (int) ($row['flavor_ingredient_id'] ?? 0),
                    'flavor_name' => (string) ($row['flavor_name'] ?? ''),
                    'flavor_quantity_required' => (float) ($row['flavor_quantity_required'] ?? 1),
                    'quantity' => $q,
                    'unit_price' => (float) ($row['unit_price'] ?? 0),
                    'line_total' => (float) ($row['line_total'] ?? 0),
                ];
            }

            // FREE orders: do not allow void/removal of any existing item.
            if ($pmLower === 'free') {
                foreach ($existing as $itemId => $row) {
                    $oldQty = max(0, (int) ($row['quantity'] ?? 0));
                    $wanted = array_key_exists($itemId, $existingWanted) ? (int) $existingWanted[$itemId] : $oldQty;
                    if ($wanted < $oldQty) {
                        throw new RuntimeException('Cannot void items on FREE (Employee) transactions.');
                    }
                }
            }

            $before = [
                'existing_new_qty' => $existingWanted,
                'added' => $adds,
            ];

            // Adjust existing items quantities (delta-based).
            foreach ($existing as $itemId => $row) {
                $oldQty = max(0, (int) ($row['quantity'] ?? 0));
                $newQty = $existingWanted[$itemId] ?? $oldQty; // if not provided, keep
                if ($oldQty === 0 && $newQty > 0) {
                    throw new RuntimeException('Voided items cannot be restored.');
                }
                $delta = $newQty - $oldQty;
                if ($delta === 0) {
                    continue;
                }
                $pid = (int) ($row['product_id'] ?? 0);
                $lineFlavorId = (int) ($row['flavor_ingredient_id'] ?? 0);
                $lineFlavorReq = max(stock_min_positive(), (float) ($row['flavor_quantity_required'] ?? 1));
                // Restore stock if reduced, deduct if increased.
                $stIng = $pdo->prepare(
                    'SELECT pi.quantity_required, i.id, i.name FROM product_ingredients pi
                     INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                     WHERE pi.tenant_id = ? AND pi.product_id = ?'
                );
                $stIng->execute([$tenantId, $pid]);
                $req = [];
                foreach ($stIng->fetchAll(PDO::FETCH_ASSOC) as $ing) {
                    $ingId = (int) ($ing['id'] ?? 0);
                    $per = (float) ($ing['quantity_required'] ?? 0);
                    if ($ingId > 0 && $per > 0) {
                        $req[$ingId] = ($req[$ingId] ?? 0) + $per;
                    }
                }

                if ($delta < 0) {
                    $restoreUnits = abs($delta);
                    foreach ($req as $ingId => $perQty) {
                        $amt = round_stock((float) $perQty * $restoreUnits);
                        $pdo->prepare('SELECT id FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE')->execute([$tenantId, $ingId]);
                        $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity + ? WHERE tenant_id = ? AND id = ?')
                            ->execute([$amt, $tenantId, $ingId]);
                        $pdo->prepare(
                            "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 'IN', ?, 'void_item', NOW(), NOW())"
                        )->execute([$tenantId, $ingId, $txId, (int) $user['id'], $amt]);
                    }
                    if ($lineFlavorId > 0) {
                        $restoreFlavor = round_stock((float) abs($delta) * $lineFlavorReq);
                        $pdo->prepare('SELECT id FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE')->execute([$tenantId, $lineFlavorId]);
                        $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity + ? WHERE tenant_id = ? AND id = ?')
                            ->execute([$restoreFlavor, $tenantId, $lineFlavorId]);
                        $pdo->prepare(
                            "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 'IN', ?, 'void_item', NOW(), NOW())"
                        )->execute([$tenantId, $lineFlavorId, $txId, (int) $user['id'], $restoreFlavor]);
                    }
                } else {
                    $deductUnits = $delta;
                    // Check sufficiency and lock
                    foreach ($req as $ingId => $perQty) {
                        $need = round_stock((float) $perQty * $deductUnits);
                        $stOne = $pdo->prepare('SELECT id, name, stock_quantity FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE');
                        $stOne->execute([$tenantId, $ingId]);
                        $ingRow = $stOne->fetch(PDO::FETCH_ASSOC);
                        if (! $ingRow || (float) ($ingRow['stock_quantity'] ?? 0) < $need) {
                            $name = $ingRow['name'] ?? 'Unknown ingredient';
                            throw new RuntimeException("Insufficient stock for {$name}.");
                        }
                    }
                    foreach ($req as $ingId => $perQty) {
                        $need = round_stock((float) $perQty * $deductUnits);
                        $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE tenant_id = ? AND id = ?')
                            ->execute([$need, $tenantId, $ingId]);
                        $pdo->prepare(
                            "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 'OUT', ?, 'edit_item', NOW(), NOW())"
                        )->execute([$tenantId, $ingId, $txId, (int) $user['id'], $need]);
                    }
                    if ($lineFlavorId > 0) {
                        $needFlavor = round_stock((float) $delta * $lineFlavorReq);
                        $stOne = $pdo->prepare('SELECT id, name, stock_quantity FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE');
                        $stOne->execute([$tenantId, $lineFlavorId]);
                        $ingRow = $stOne->fetch(PDO::FETCH_ASSOC);
                        if (! $ingRow || (float) ($ingRow['stock_quantity'] ?? 0) < $needFlavor) {
                            $name = $ingRow['name'] ?? 'Unknown ingredient';
                            throw new RuntimeException("Insufficient stock for {$name}.");
                        }
                        $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE tenant_id = ? AND id = ?')
                            ->execute([$needFlavor, $tenantId, $lineFlavorId]);
                        $pdo->prepare(
                            "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 'OUT', ?, 'edit_item', NOW(), NOW())"
                        )->execute([$tenantId, $lineFlavorId, $txId, (int) $user['id'], $needFlavor]);
                    }
                }

                if ($newQty <= 0) {
                    // Keep the line item for history; mark as voided by setting qty/total to 0.
                    $pdo->prepare('UPDATE transaction_items SET quantity = 0, line_total = 0, updated_at = NOW() WHERE tenant_id = ? AND transaction_id = ? AND id = ?')
                        ->execute([$tenantId, $txId, $itemId]);
                    unset($existing[$itemId]);
                } else {
                    $unitPrice = (float) ($row['unit_price'] ?? 0);
                    $lineTotal = $unitPrice * $newQty;
                    $pdo->prepare('UPDATE transaction_items SET quantity = ?, line_total = ?, updated_at = NOW() WHERE tenant_id = ? AND transaction_id = ? AND id = ?')
                        ->execute([$newQty, $lineTotal, $tenantId, $txId, $itemId]);
                    $existing[$itemId]['quantity'] = $newQty;
                    $existing[$itemId]['line_total'] = $lineTotal;
                }
            }

            // Deduct stock for added items.
            foreach ($adds as $it) {
                $pid = (int) $it['product_id'];
                $qty = max(1, (int) $it['quantity']);

                // Validate product exists (and active).
                $stP = $pdo->prepare('SELECT id, price, name FROM products WHERE tenant_id = ? AND id = ? AND is_active = 1 LIMIT 1');
                $stP->execute([$tenantId, $pid]);
                $p = $stP->fetch(PDO::FETCH_ASSOC);
                if (! $p) {
                    throw new RuntimeException('One or more added products are invalid or inactive.');
                }

                // Check stock requirements and lock ingredients.
                $required = [];
                $stIng = $pdo->prepare(
                    'SELECT pi.quantity_required, i.id, i.name, i.stock_quantity FROM product_ingredients pi
                     INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                     WHERE pi.tenant_id = ? AND pi.product_id = ?'
                );
                $stIng->execute([$tenantId, $pid]);
                foreach ($stIng->fetchAll(PDO::FETCH_ASSOC) as $ing) {
                    $need = (float) ($ing['quantity_required'] ?? 0) * $qty;
                    $ingId = (int) ($ing['id'] ?? 0);
                    if ($ingId < 1 || $need <= 0) {
                        continue;
                    }
                    $required[$ingId] = ($required[$ingId] ?? 0) + $need;
                }
                $requiredRounded = [];
                foreach ($required as $ingId => $needQty) {
                    $requiredRounded[(int) $ingId] = round_stock((float) $needQty);
                }
                foreach ($requiredRounded as $ingId => $needQty) {
                    $stOne = $pdo->prepare('SELECT id, name, stock_quantity FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE');
                    $stOne->execute([$tenantId, $ingId]);
                    $ingRow = $stOne->fetch(PDO::FETCH_ASSOC);
                    if (! $ingRow || (float) ($ingRow['stock_quantity'] ?? 0) < $needQty) {
                        $name = $ingRow['name'] ?? 'Unknown ingredient';
                        throw new RuntimeException("Insufficient stock for {$name}.");
                    }
                }
                foreach ($requiredRounded as $ingId => $needQty) {
                    $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE tenant_id = ? AND id = ?')
                        ->execute([$needQty, $tenantId, $ingId]);
                    $pdo->prepare(
                        "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 'OUT', ?, 'edit_item', NOW(), NOW())"
                    )->execute([$tenantId, $ingId, $txId, (int) $user['id'], $needQty]);
                }

                $unitPrice = (float) ($p['price'] ?? 0);
                $lineTotal = $unitPrice * $qty;
                $pdo->prepare(
                    'INSERT INTO transaction_items (tenant_id, transaction_id, product_id, quantity, unit_price, unit_expense, line_total, line_expense, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, 0, ?, 0, NOW(), NOW())'
                )->execute([$tenantId, $txId, $pid, $qty, $unitPrice, $lineTotal]);
                $newItemId = (int) $pdo->lastInsertId();
                // Track by line id to avoid collisions when same product is added multiple times.
                $existing[$newItemId > 0 ? $newItemId : (int) ('9'.$pid)] = [
                    'item_id' => $newItemId,
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $newTotal = 0.0;
            foreach ($existing as $row) {
                $newTotal += (float) ($row['line_total'] ?? 0);
            }
            if ($existing === []) {
                if ($isPending) {
                    // No paid amount yet; keep pending, zero totals until items are added again.
                    $pdo->prepare(
                        "UPDATE transactions
                         SET status = 'pending',
                             total_amount = 0,
                             profit_total = 0,
                             was_updated = 1,
                             updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?"
                    )->execute([$tenantId, $txId]);
                } else {
                    // Keep status as completed. Cancelled/void is reserved for "Cancel order" button.
                    $netReceived = max(0.0, $basePaidNet + $addedPrev - $refundPrev);
                    $fullRefundTotal = $refundPrev + $netReceived;
                    $pdo->prepare(
                        "UPDATE transactions
                         SET status = 'completed',
                             total_amount = 0,
                             profit_total = 0,
                             refunded_amount = ?,
                             added_paid_amount = 0,
                             original_total_amount = COALESCE(original_total_amount, ?),
                             was_updated = 1,
                             updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?"
                    )->execute([$fullRefundTotal, $originalTotal, $tenantId, $txId]);
                }
            } else {
                if ($isPending) {
                    // No payment adjustments until the order is paid.
                    $pdo->prepare(
                        "UPDATE transactions
                         SET status = 'pending',
                             total_amount = ?,
                             profit_total = ?,
                             was_updated = 1,
                             updated_at = NOW()
                         WHERE tenant_id = ? AND id = ?"
                    )->execute([$newTotal, $newTotal, $tenantId, $txId]);
                } else {
                    // Adjustments are based on net received so far (what the customer has already paid, net of refunds),
                    // so we don't ask for additional payment when the customer is actually overpaid.
                    $netReceived = max(0.0, $basePaidNet + $addedPrev - $refundPrev);
                    $refund = 0.0;
                    $added = 0.0;
                    $diff = $newTotal - $netReceived;
                    if ($diff < -money_epsilon()) {
                        $refund = abs($diff);
                    } elseif ($diff > money_epsilon()) {
                        $added = $diff;
                    }

                    if ($refund > 0 && $refundOverride !== null) {
                        if (abs($refundOverride - $refund) > money_epsilon()) {
                            throw new RuntimeException('Refund amount must equal the total difference.');
                        }
                    }
                    if ($added > 0 && $additionalOverride !== null) {
                        if (abs($additionalOverride - $added) > money_epsilon()) {
                            throw new RuntimeException('Additional paid amount must equal the total difference.');
                        }
                    }

                    $newRefunded = $refundPrev + $refund;
                    $newAddedPaid = $addedPrev + $added;

                    $changeSql = ($newRefunded > 0 || $newAddedPaid > 0) ? ", change_amount = 0" : "";
                    $pdo->prepare("UPDATE transactions SET status = 'completed', total_amount = ?, profit_total = ?, refunded_amount = ?, added_paid_amount = ?, original_total_amount = COALESCE(original_total_amount, ?), was_updated = 1{$changeSql}, updated_at = NOW() WHERE tenant_id = ? AND id = ?")
                        ->execute([$newTotal, $newTotal, $newRefunded, $newAddedPaid, $originalTotal, $tenantId, $txId]);
                }
            }

            ActivityLogger::log(
                $tenantId,
                (int) $user['id'],
                (string) $user['role'],
                'transactions',
                'edit_items',
                $request,
                sprintf('Transaction #%d items updated', $txId),
                ['transaction_id' => $txId, 'changes' => $before]
            );

            $pdo->commit();
            return json_response(['success' => true]);
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            return json_response(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable) {
            $pdo->rollBack();
            return json_response(['success' => false, 'message' => 'Could not update transaction.'], 500);
        }
    }

    public function receipt(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (Auth::isTenantFreePlanRestricted($user)) {
            return json_response(['success' => false, 'message' => 'Premium feature: receipt printing is not available on the Free version.'], 403);
        }
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $txId = (int) $id;
        if ($tenantId < 1 || $txId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $st = $pdo->prepare('SELECT id FROM transactions WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $txId]);
        if (! $st->fetch(PDO::FETCH_ASSOC)) {
            return json_response(['success' => false, 'message' => 'Transaction not found.'], 404);
        }

        TenantReceiptFields::ensure($pdo);
        $receipt = $this->buildReceiptPayload($pdo, $tenantId, $txId);

        return json_response(['success' => true, 'receipt' => $receipt]);
    }

    public function destroyTransaction(Request $request, string $id): Response
    {
        $user = Auth::user();
        if ($user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        $txId = (int) $id;
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT status FROM transactions WHERE tenant_id = ? AND id = ? FOR UPDATE');
            $st->execute([$tenantId, $txId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (! $row) {
                $pdo->rollBack();
                return new Response('Not found', 404);
            }

            $status = (string) ($row['status'] ?? '');
            if ($status === 'pending') {
                throw new RuntimeException('Pending orders cannot be cancelled.');
            }
            $isVoid = $status === 'void';
            $newStatus = $isVoid ? 'completed' : 'void';

            // Load items once (for stock reversal/deduction).
            $st = $pdo->prepare('SELECT product_id, flavor_ingredient_id, flavor_quantity_required, quantity FROM transaction_items WHERE tenant_id = ? AND transaction_id = ?');
            $st->execute([$tenantId, $txId]);
            $items = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $it) {
                $pid = (int) ($it['product_id'] ?? 0);
                $qty = max(1, (int) ($it['quantity'] ?? 0));
                if ($pid > 0) {
                    $items[] = [
                        'product_id' => $pid,
                        'quantity' => $qty,
                        'flavor_ingredient_id' => max(0, (int) ($it['flavor_ingredient_id'] ?? 0)),
                        'flavor_quantity_required' => max(stock_min_positive(), (float) ($it['flavor_quantity_required'] ?? 1)),
                    ];
                }
            }

            // Compute ingredient requirements across all items.
            $required = []; // ingredient_id => qty
            foreach ($items as $it) {
                $pid = (int) $it['product_id'];
                $qty = (int) $it['quantity'];
                $stIng = $pdo->prepare(
                    'SELECT pi.quantity_required, i.id
                     FROM product_ingredients pi
                     INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                     WHERE pi.tenant_id = ? AND pi.product_id = ?'
                );
                $stIng->execute([$tenantId, $pid]);
                foreach ($stIng->fetchAll(PDO::FETCH_ASSOC) as $ing) {
                    $ingId = (int) ($ing['id'] ?? 0);
                    $need = (float) ($ing['quantity_required'] ?? 0) * $qty;
                    if ($ingId > 0 && $need > 0) {
                        $required[$ingId] = ($required[$ingId] ?? 0) + $need;
                    }
                }
                $lineFlavorId = max(0, (int) ($it['flavor_ingredient_id'] ?? 0));
                if ($lineFlavorId > 0) {
                    $lineFlavorReq = max(stock_min_positive(), (float) ($it['flavor_quantity_required'] ?? 1));
                    $required[$lineFlavorId] = ($required[$lineFlavorId] ?? 0) + ($qty * $lineFlavorReq);
                }
            }

            $requiredVoidRounded = [];
            foreach ($required as $ingId => $needQty) {
                $requiredVoidRounded[(int) $ingId] = round_stock((float) $needQty);
            }

            if (! $isVoid) {
                // Void: restore stock for ALL associated requirements.
                foreach ($requiredVoidRounded as $ingId => $needQty) {
                    $pdo->prepare('SELECT id FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE')
                        ->execute([$tenantId, $ingId]);
                    $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity + ? WHERE tenant_id = ? AND id = ?')
                        ->execute([$needQty, $tenantId, $ingId]);
                    $pdo->prepare(
                        "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 'IN', ?, 'void_transaction', NOW(), NOW())"
                    )->execute([$tenantId, $ingId, $txId, (int) $user['id'], $needQty]);
                }
            } else {
                // Unvoid: deduct stock again (to match the restored inventory).
                foreach ($requiredVoidRounded as $ingId => $needQty) {
                    $stOne = $pdo->prepare('SELECT id, name, stock_quantity FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE');
                    $stOne->execute([$tenantId, $ingId]);
                    $ingRow = $stOne->fetch(PDO::FETCH_ASSOC);
                    if (! $ingRow || (float) ($ingRow['stock_quantity'] ?? 0) < $needQty) {
                        $name = $ingRow['name'] ?? 'Unknown ingredient';
                        throw new RuntimeException("Insufficient stock for {$name}.");
                    }
                }
                foreach ($requiredVoidRounded as $ingId => $needQty) {
                    $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE tenant_id = ? AND id = ?')
                        ->execute([$needQty, $tenantId, $ingId]);
                    $pdo->prepare(
                        "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                         VALUES (?, ?, ?, ?, 'OUT', ?, 'unvoid_transaction', NOW(), NOW())"
                    )->execute([$tenantId, $ingId, $txId, (int) $user['id'], $needQty]);
                }
            }

            $pdo->prepare('UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute([$newStatus, $txId, $tenantId]);
            $pdo->commit();
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            session_flash('errors', [$e->getMessage()]);
            return redirect(url('/tenant/transactions'));
        } catch (\Throwable) {
            $pdo->rollBack();
            session_flash('errors', ['Could not update transaction.']);
            return redirect(url('/tenant/transactions'));
        }

        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'transactions',
            $isVoid ? 'unvoid' : 'void',
            $request,
            sprintf('Transaction #%d marked as %s', (int) $id, $newStatus),
            ['transaction_id' => $txId, 'status' => $newStatus]
        );

            session_flash('success', $isVoid ? 'Order has been restored and set to completed.' : 'Order has been cancelled.');

        return redirect(url('/tenant/transactions'));
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function resolveReportRange(Request $request, string $today): array
    {
        $preset = trim((string) $request->query('preset', ''));

        if ($preset !== '' && in_array($preset, self::CHART_PRESETS, true)) {
            if ($preset === 'custom') {
                $from = trim((string) $request->query('from', ''));
                $to = trim((string) $request->query('to', ''));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
                    $from = $today;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
                    $to = $today;
                }
                if (strtotime($from) > strtotime($to)) {
                    [$from, $to] = [$to, $from];
                }

                return [$from, $to, 'custom'];
            }

            return [...$this->rangeForPreset($preset, $today), $preset];
        }

        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = $today;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = $today;
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }
        $inferred = ($from === $to && $from === $today) ? 'today' : 'custom';

        return [$from, $to, $inferred];
    }

    /** @return array{0:string,1:string} */
    private function rangeForPreset(string $preset, string $today): array
    {
        $todayTs = strtotime($today.' 00:00:00');
        if ($todayTs === false) {
            return [$today, $today];
        }

        return match ($preset) {
            'today' => [$today, $today],
            'yesterday' => [
                date('Y-m-d', strtotime('-1 day', $todayTs)),
                date('Y-m-d', strtotime('-1 day', $todayTs)),
            ],
            'last_3' => [date('Y-m-d', strtotime('-2 days', $todayTs)), $today],
            'last_7' => [date('Y-m-d', strtotime('-6 days', $todayTs)), $today],
            'last_14' => [date('Y-m-d', strtotime('-13 days', $todayTs)), $today],
            'last_30' => [date('Y-m-d', strtotime('-29 days', $todayTs)), $today],
            'this_month' => [date('Y-m-01', $todayTs), $today],
            default => [$today, $today],
        };
    }

    /**
     * @return array{labels:list<string>,dates:list<string>,orders:list<int>,sales:list<float>,expenses:list<float>,profit:list<float>}
     */
    private function buildChartSeries(PDO $pdo, int $tenantId, string $from, string $to): array
    {
        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';
        $orderMap = $this->groupCount($pdo, $tenantId, $rangeStart, $rangeEnd);
        $salesMap = $this->groupSumSales($pdo, $tenantId, $rangeStart, $rangeEnd);
        $expenseMap = $this->groupSumExpenses($pdo, $tenantId, $rangeStart, $rangeEnd);

        $labels = [];
        $dates = [];
        $orders = [];
        $sales = [];
        $expenses = [];
        $profit = [];

        $cur = strtotime($from.' 00:00:00');
        $endTs = strtotime($to.' 00:00:00');
        if ($cur === false || $endTs === false) {
            return [
                'labels' => [],
                'dates' => [],
                'orders' => [],
                'sales' => [],
                'expenses' => [],
                'profit' => [],
            ];
        }

        while ($cur <= $endTs) {
            $d = date('Y-m-d', $cur);
            $dates[] = $d;
            $labels[] = date('M j', $cur);
            $o = $orderMap[$d] ?? 0;
            $s = (float) ($salesMap[$d] ?? 0.0);
            $e = (float) ($expenseMap[$d] ?? 0.0);
            $orders[] = $o;
            $sales[] = round_money($s);
            $expenses[] = round_money($e);
            $profit[] = round_money($s - $e);
            $cur = strtotime('+1 day', $cur);
            if ($cur === false) {
                break;
            }
        }

        return [
            'labels' => $labels,
            'dates' => $dates,
            'orders' => $orders,
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => $profit,
        ];
    }

    /** @return array<string,int> */
    private function groupCount(PDO $pdo, int $tenantId, string $start, string $end): array
    {
        $st = $pdo->prepare(
            "SELECT DATE(created_at) as d, COUNT(*) as c FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
             GROUP BY d"
        );
        $st->execute([$tenantId, $start, $end]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['d']] = (int) $row['c'];
        }

        return $out;
    }

    /** @return array<string,float> */
    private function groupSumSales(PDO $pdo, int $tenantId, string $start, string $end): array
    {
        $st = $pdo->prepare(
            "SELECT DATE(created_at) as d, COALESCE(SUM(total_amount),0) as s FROM laundry_orders
             WHERE tenant_id = ?
               AND created_at BETWEEN ? AND ?
               AND COALESCE(is_void, 0) = 0
               AND status <> 'void'
               AND (
                   status = 'paid'
                   OR (status = 'completed' AND payment_status = 'paid')
               )
             GROUP BY d"
        );
        $st->execute([$tenantId, $start, $end]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['d']] = (float) $row['s'];
        }

        return $out;
    }

    /** @return array<string,float> */
    private function groupSumExpenses(PDO $pdo, int $tenantId, string $start, string $end): array
    {
        $out = [];

        $stManual = $pdo->prepare(
            "SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as s FROM expenses
             WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?
             GROUP BY d"
        );
        $stManual->execute([$tenantId, $start, $end]);
        foreach ($stManual->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = (string) $row['d'];
            $out[$d] = ($out[$d] ?? 0.0) + (float) $row['s'];
        }

        return $out;
    }

    /**
     * @return array<string,array{label:string,count:int}>
     */
    private function fetchServiceModeSummary(PDO $pdo, int $tenantId, string $rangeStart, string $rangeEnd): array
    {
        $summary = [
            'regular' => ['label' => 'Regular', 'count' => 0],
            'free' => ['label' => 'Free', 'count' => 0],
            'reward' => ['label' => 'Reward', 'count' => 0],
        ];

        try {
            $st = $pdo->prepare(
                "SELECT
                    CASE
                        WHEN COALESCE(is_reward, 0) = 1 THEN 'reward'
                        WHEN COALESCE(is_free, 0) = 1 THEN 'free'
                        ELSE 'regular'
                    END AS mode_bucket,
                    COUNT(*) AS cnt
                 FROM laundry_orders
                 WHERE tenant_id = ?
                   AND created_at BETWEEN ? AND ?
                   AND COALESCE(is_void, 0) = 0
                   AND status <> 'void'
                 GROUP BY mode_bucket"
            );
            $st->execute([$tenantId, $rangeStart, $rangeEnd]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $bucket = (string) ($row['mode_bucket'] ?? '');
                if (! isset($summary[$bucket])) {
                    continue;
                }
                $summary[$bucket]['count'] = (int) ($row['cnt'] ?? 0);
            }
        } catch (\Throwable) {
        }

        return $summary;
    }

    /** @return array<string,mixed> */
    private function buildReceiptPayload(PDO $pdo, int $tenantId, int $transactionId): array
    {
        return TransactionReceiptPayload::build($pdo, $tenantId, $transactionId);
    }
}
