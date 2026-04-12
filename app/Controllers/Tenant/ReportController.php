<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantReceiptFields;
use PDO;
use RuntimeException;

final class ReportController
{
    /** @var list<string> */
    private const CHART_PRESETS = ['today', 'yesterday', 'last_3', 'last_7', 'last_14', 'last_30', 'this_month', 'custom'];

    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || ($user['role'] ?? '') !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }

        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        $today = date('Y-m-d');
        [$from, $to, $chartPreset] = $this->resolveReportRange($request, $today);
        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';
        $chartSeries = $this->buildChartSeries($pdo, $tenantId, $from, $to);

        $salesTotal = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $st = $pdo->prepare(
            "SELECT LOWER(TRIM(COALESCE(payment_method,''))) AS pm, COALESCE(SUM(total_amount),0) AS total
             FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
             GROUP BY pm"
        );
        $st->execute([$tenantId, $rangeStart, $rangeEnd]);
        $paymentsByMethod = [
            'cash' => 0.0,
            'card' => 0.0,
            'gcash' => 0.0,
            'paymaya' => 0.0,
            'online_banking' => 0.0,
            'free' => 0.0,
        ];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pm = (string) ($row['pm'] ?? '');
            if ($pm === '') {
                $pm = 'cash';
            }
            if (array_key_exists($pm, $paymentsByMethod)) {
                $paymentsByMethod[$pm] += (float) ($row['total'] ?? 0);
            }
        }
        $expensesTotal = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $netSales = $salesTotal - $expensesTotal;

        // Audit: sum of line items vs transaction totals (should match; FREE orders = ₱0 sa both).
        $lineItemsSum = $this->scalarSum(
            $pdo,
            'SELECT COALESCE(SUM(ti.line_total), 0) FROM transaction_items ti
             INNER JOIN transactions t ON t.id = ti.transaction_id AND t.tenant_id = ti.tenant_id
             WHERE t.tenant_id = ? AND t.status = \'completed\' AND t.created_at BETWEEN ? AND ?',
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $reconciliationDelta = round_money($salesTotal - $lineItemsSum);
        $stFree = $pdo->prepare(
            'SELECT COUNT(*) FROM transactions
             WHERE tenant_id = ? AND status = \'completed\'
             AND LOWER(TRIM(COALESCE(payment_method, \'\'))) = \'free\'
             AND created_at BETWEEN ? AND ?'
        );
        $stFree->execute([$tenantId, $rangeStart, $rangeEnd]);
        $freeOrdersCount = (int) $stFree->fetchColumn();
        $stFreeLines = $pdo->prepare(
            'SELECT COALESCE(SUM(ti.line_total), 0) FROM transaction_items ti
             INNER JOIN transactions t ON t.id = ti.transaction_id AND t.tenant_id = ti.tenant_id
             WHERE t.tenant_id = ? AND t.status = \'completed\'
             AND LOWER(TRIM(COALESCE(t.payment_method, \'\'))) = \'free\'
             AND t.created_at BETWEEN ? AND ?'
        );
        $stFreeLines->execute([$tenantId, $rangeStart, $rangeEnd]);
        $freeLineSum = (float) $stFreeLines->fetchColumn();

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
                'line_items_sum' => $lineItemsSum,
                'reconciliation_delta' => $reconciliationDelta,
                'free_orders_count' => $freeOrdersCount,
                'free_line_sum' => $freeLineSum,
                'expenses_total' => $expensesTotal,
                'net_sales' => $netSales,
                'payments_by_method' => $paymentsByMethod,
                'range_from' => $from,
                'range_to' => $to,
                'chart_preset' => $chartPreset,
            ],
            'chart' => $chartSeries,
            'reports_maintenance' => $reportsMaintenance,
            'reports_subscription' => $reportsSubscription,
        ]);
    }

    public function dailyOuts(Request $request): Response
    {
        $tenantId = (int) Auth::user()['tenant_id'];
        $pdo = App::db();
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

        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';

        $st = $pdo->prepare(
            "SELECT p.id AS product_id, p.name AS product_name,
                    COALESCE(SUM(ti.quantity),0) AS qty,
                    COALESCE(SUM(ti.line_total),0) AS line_amount
             FROM transactions t
             INNER JOIN transaction_items ti ON ti.transaction_id = t.id AND ti.tenant_id = t.tenant_id
             INNER JOIN products p ON p.id = ti.product_id AND p.tenant_id = ti.tenant_id
             WHERE t.tenant_id = ? AND t.status = 'completed' AND t.created_at BETWEEN ? AND ?
             GROUP BY p.id, p.name
             ORDER BY qty DESC, p.name ASC
             LIMIT 200"
        );
        $st->execute([$tenantId, $rangeStart, $rangeEnd]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'qty' => (float) ($row['qty'] ?? 0),
                'line_amount' => round_money((float) ($row['line_amount'] ?? 0)),
            ];
        }

        return json_response(['success' => true, 'from' => $from, 'to' => $to, 'data' => $rows]);
    }

    private function scalarSum(PDO $pdo, string $sql, array $params): float
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return (float) $st->fetchColumn();
    }

    public function transactions(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
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

            $total = (int) $pdo->query('SELECT COUNT(*) FROM transactions WHERE tenant_id = '.$tenantId)->fetchColumn();
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
                    $itemsHtml .= '<li>'.e((string) $it['product_name']).' - Qty '.e(format_stock((float) $it['quantity']))
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
                    $details = '<div class="d-flex gap-1 justify-content-center">'
                        .'<button type="button" class="btn btn-sm btn-success js-pay-pending" data-id="'.$tid.'" data-name="'.e($pendingName).'" data-contact="'.e($pendingContact).'" data-total="'.e((string) ($trow['total_amount'] ?? 0)).'" title="Pay pending and print receipt"><i class="fa fa-money-bill-wave"></i></button>'
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

        return view_page('Transactions', 'tenant.transactions.index', thermal_receipt_client_config('transactions'));
    }

    public function editData(Request $request, string $id): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $txId = (int) $id;
        if ($tenantId < 1 || $txId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }
        $pdo = App::db();
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
            'SELECT ti.id AS item_id, ti.product_id, ti.quantity, ti.unit_price, ti.line_total, p.name AS product_name
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
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_total' => (float) ($row['line_total'] ?? 0),
            ];
        }

        // Products list for replacement/add (active only to avoid selling inactive).
        $st = $pdo->prepare('SELECT id, name, price FROM products WHERE tenant_id = ? AND is_active = 1 ORDER BY name ASC');
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

            $st = $pdo->prepare('SELECT id, product_id, quantity, unit_price, line_total FROM transaction_items WHERE tenant_id = ? AND transaction_id = ?');
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
        $tenantId = (int) ($user['tenant_id'] ?? 0);
        $txId = (int) $id;
        if ($tenantId < 1 || $txId < 1) {
            return json_response(['success' => false, 'message' => 'Invalid request.'], 422);
        }

        $pdo = App::db();
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
            $st = $pdo->prepare('SELECT product_id, quantity FROM transaction_items WHERE tenant_id = ? AND transaction_id = ?');
            $st->execute([$tenantId, $txId]);
            $items = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $it) {
                $pid = (int) ($it['product_id'] ?? 0);
                $qty = max(1, (int) ($it['quantity'] ?? 0));
                if ($pid > 0) {
                    $items[] = ['product_id' => $pid, 'quantity' => $qty];
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
            "SELECT DATE(created_at) as d, COALESCE(SUM(total_amount),0) as s FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
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
        $st = $pdo->prepare(
            "SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as s FROM expenses
             WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?
             GROUP BY d"
        );
        $st->execute([$tenantId, $start, $end]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['d']] = (float) $row['s'];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function buildReceiptPayload(PDO $pdo, int $tenantId, int $transactionId): array
    {
        $st = $pdo->prepare(
            'SELECT name, receipt_display_name, receipt_business_style, receipt_tax_id, receipt_phone, receipt_address, receipt_email, receipt_footer_note
             FROM tenants
             WHERE id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $tenant = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st = $pdo->prepare(
            'SELECT total_amount, original_total_amount, amount_tendered, change_amount, payment_method, amount_paid, refunded_amount, added_paid_amount, created_at
             FROM transactions WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$transactionId, $tenantId]);
        $tx = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        $st = $pdo->prepare(
            'SELECT ti.quantity, ti.unit_price, ti.line_total, p.name AS product_name
             FROM transaction_items ti
             INNER JOIN products p ON p.id = ti.product_id AND p.tenant_id = ti.tenant_id
             WHERE ti.transaction_id = ? AND ti.tenant_id = ?
             ORDER BY ti.id ASC'
        );
        $st->execute([$transactionId, $tenantId]);
        $lines = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $lines[] = [
                'name' => (string) ($row['product_name'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'line_total' => (float) ($row['line_total'] ?? 0),
            ];
        }

        return [
            'transaction_id' => $transactionId,
            'store_name' => (string) ($tenant['name'] ?? ''),
            'display_name' => trim((string) ($tenant['receipt_display_name'] ?? '')),
            'business_style' => trim((string) ($tenant['receipt_business_style'] ?? '')),
            'tax_id' => trim((string) ($tenant['receipt_tax_id'] ?? '')),
            'contact' => [
                'phone' => trim((string) ($tenant['receipt_phone'] ?? '')),
                'address' => trim((string) ($tenant['receipt_address'] ?? '')),
                'email' => trim((string) ($tenant['receipt_email'] ?? '')),
            ],
            'footer_note' => trim((string) ($tenant['receipt_footer_note'] ?? '')),
            'items' => $lines,
            'grand_total' => (float) ($tx['total_amount'] ?? 0),
            'original_total_amount' => array_key_exists('original_total_amount', $tx) && $tx['original_total_amount'] !== null ? (float) $tx['original_total_amount'] : null,
            'amount_tendered' => array_key_exists('amount_tendered', $tx) && $tx['amount_tendered'] !== null ? (float) $tx['amount_tendered'] : null,
            'change_amount' => array_key_exists('change_amount', $tx) && $tx['change_amount'] !== null ? (float) $tx['change_amount'] : null,
            'payment_method' => array_key_exists('payment_method', $tx) && $tx['payment_method'] !== null ? (string) $tx['payment_method'] : null,
            'amount_paid' => array_key_exists('amount_paid', $tx) && $tx['amount_paid'] !== null ? (float) $tx['amount_paid'] : null,
            'refunded_amount' => array_key_exists('refunded_amount', $tx) ? (float) ($tx['refunded_amount'] ?? 0) : 0.0,
            'added_paid_amount' => array_key_exists('added_paid_amount', $tx) ? (float) ($tx['added_paid_amount'] ?? 0) : 0.0,
            'created_at' => $tx['created_at'] ?? null,
        ];
    }
}
