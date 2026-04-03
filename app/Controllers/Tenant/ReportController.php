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

final class ReportController
{
    public function index(Request $request): Response
    {
        $tenantId = (int) Auth::user()['tenant_id'];
        $pdo = App::db();
        $periodEnd = date('Y-m-d 23:59:59');
        $startOfWeek = date('Y-m-d 00:00:00', strtotime('-6 days'));
        $startOfMonth = date('Y-m-d 00:00:00', strtotime('-29 days'));
        $startOfYear = date('Y-m-d 00:00:00', strtotime('-364 days'));
        $today = date('Y-m-d');

        $dailySales = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND DATE(created_at) = ?",
            [$tenantId, $today]
        );
        $weeklySales = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?",
            [$tenantId, $startOfWeek, $periodEnd]
        );
        $monthlySales = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?",
            [$tenantId, $startOfMonth, $periodEnd]
        );
        $yearlySales = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?",
            [$tenantId, $startOfYear, $periodEnd]
        );

        $dailyExpense = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND DATE(created_at) = ?",
            [$tenantId, $today]
        );
        $weeklyExpense = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $startOfWeek, $periodEnd]
        );
        $monthlyExpense = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $startOfMonth, $periodEnd]
        );
        $yearlyExpense = $this->scalarSum(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $startOfYear, $periodEnd]
        );

        return view_page('Reports', 'tenant.reports.index', [
            'daily_sales' => $dailySales,
            'weekly_sales' => $weeklySales,
            'monthly_sales' => $monthlySales,
            'yearly_sales' => $yearlySales,
            'daily_expense' => $dailyExpense,
            'weekly_expense' => $weeklyExpense,
            'monthly_expense' => $monthlyExpense,
            'yearly_expense' => $yearlyExpense,
            'daily_profit' => $dailySales - $dailyExpense,
            'weekly_profit' => $weeklySales - $weeklyExpense,
            'monthly_profit' => $monthlySales - $monthlyExpense,
            'yearly_profit' => $yearlySales - $yearlyExpense,
        ]);
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

            // Column 0 is responsive control; 1–6 map to ID, Date, Cashier, Qty, Total, Status (matches DataTables order index).
            $orderCol = (int) data_get($request->all(), 'order.0.column', 1);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $orderMap = [
                1 => 't.id',
                2 => 't.created_at',
                3 => 'u.name',
                4 => 'qty_sum',
                5 => 't.total_amount',
                6 => 't.status',
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
                foreach ($items as $it) {
                    $itemsHtml .= '<li>'.e((string) $it['product_name']).' - Qty '.number_format((float) $it['quantity'], 2)
                        .' x '.number_format((float) $it['unit_price'], 2).' = '.number_format((float) $it['line_total'], 2).'</li>';
                }
                $itemsHtml .= '</ul>';

                if ((string) ($trow['status'] ?? '') === 'void') {
                    $details = '<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemsModal'.$tid.'" title="View items"><i class="fa fa-eye"></i></button>'
                        .'<div class="modal fade" id="itemsModal'.$tid.'" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
                        .'<div class="modal-header"><h6 class="modal-title">Purchased Items #'.$tid.'</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>'
                        .'<div class="modal-body">'.$itemsHtml.'</div></div></div></div>';
                } else {
                    $details = '<button type="button" class="btn btn-sm btn-outline-secondary js-reprint-receipt" data-receipt-url="'.e(route('tenant.transactions.receipt', ['id' => $tid])).'" title="Reprint receipt"><i class="fa fa-receipt"></i></button>';
                }

                $action = '';
                if ($user['role'] === 'tenant_admin') {
                    $isVoid = $trow['status'] === 'void';
                    $action = '<form method="POST" action="'.e(url('/tenant/transactions/'.$tid)).'">'
                        .csrf_field().method_field('DELETE')
                        .'<button class="btn btn-sm '.($isVoid ? 'btn-success' : 'btn-danger').'" title="'.($isVoid ? 'Unvoid transaction' : 'Void transaction').'">'
                        .'<i class="fa '.($isVoid ? 'fa-rotate-left' : 'fa-ban').'"></i></button></form>';
                }

                $data[] = [
                    'id' => $tid,
                    'date' => $trow['created_at'] ? date('M d, Y h:i A', strtotime((string) $trow['created_at'])) : '',
                    'cashier' => e((string) ($trow['cashier_name'] ?? 'N/A')),
                    'qty' => (float) ($trow['qty_sum'] ?? 0),
                    'total' => number_format((float) $trow['total_amount'], 2),
                    'status' => '<span class="badge '.($trow['status'] === 'void' ? 'text-bg-danger' : 'text-bg-success').'">'.e((string) $trow['status']).'</span>',
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

        return view_page('Transactions', 'tenant.transactions.index');
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
        $st = $pdo->prepare('SELECT status FROM transactions WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, (int) $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row) {
            return new Response('Not found', 404);
        }
        $isVoid = $row['status'] === 'void';
        $newStatus = $isVoid ? 'completed' : 'void';
        $pdo->prepare('UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$newStatus, (int) $id, $tenantId]);

        ActivityLogger::log(
            $tenantId,
            (int) $user['id'],
            (string) $user['role'],
            'transactions',
            $isVoid ? 'unvoid' : 'void',
            $request,
            sprintf('Transaction #%d marked as %s', (int) $id, $newStatus),
            ['transaction_id' => (int) $id, 'status' => $newStatus]
        );

        session_flash('success', $isVoid ? 'Transaction has been unvoided and set to completed.' : 'Transaction has been voided.');

        return redirect(url('/tenant/transactions'));
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
            'SELECT total_amount, created_at FROM transactions WHERE id = ? AND tenant_id = ? LIMIT 1'
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
            'created_at' => $tx['created_at'] ?? null,
        ];
    }
}
