<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class ExpenseController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 'tenant_id = ? AND type = ?';
            $params = [$tenantId, 'manual'];
            if ($search !== '') {
                $where .= ' AND (description LIKE ? OR CAST(id AS CHAR) LIKE ?)';
                $like = '%'.$search.'%';
                $params[] = $like;
                $params[] = $like;
            }

            $stTotal = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE tenant_id = ? AND type = ?');
            $stTotal->execute([$tenantId, 'manual']);
            $total = (int) $stTotal->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE $where");
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            $orderIdx = (int) data_get($request->all(), 'order.0.column', 4);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
            // Same column order as DataTables: 0 = responsive control, 1=id, 2=desc, 3=amount, 4=created_at, 5=actions
            $columns = [null, 'id', 'description', 'amount', 'created_at', null];
            $orderBy = $columns[$orderIdx] ?? 'created_at';
            if ($orderBy === null || $orderBy === '') {
                $orderBy = 'created_at';
            }
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT * FROM expenses WHERE $where ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $trialBrowse = Auth::isTenantFreePlanRestricted($user);
            $data = [];
            foreach ($rows as $expense) {
                $actions = '';
                if (Auth::tenantMayManage($user, 'expenses') && ! $trialBrowse) {
                    $eid = (int) $expense['id'];
                    $descAttr = e((string) $expense['description']);
                    $amountAttr = (string) ((float) $expense['amount']);
                    $actions = '<div class="d-flex gap-1">'
                        .'<button type="button" class="btn btn-sm btn-outline-primary js-edit-expense" data-id="'.$eid.'" data-description="'.$descAttr.'" data-amount="'.$amountAttr.'" title="Edit"><i class="fa fa-pen"></i></button>'
                        .'<button type="button" class="btn btn-sm btn-outline-danger js-delete-expense" data-id="'.$eid.'" title="Delete"><i class="fa fa-trash"></i></button>'
                        .'</div>';
                }
                $data[] = [
                    'id' => $expense['id'],
                    'description' => e((string) $expense['description']),
                    'amount' => format_money((float) $expense['amount']),
                    'created_at' => $expense['created_at'] ? date('M d, Y h:i A', strtotime((string) $expense['created_at'])) : '',
                    'actions' => $actions,
                ];
            }

            return json_response([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
            ]);
        }

        return view_page('Expenses', 'tenant.expenses.index', [
            'premium_trial_browse_lock' => Auth::isTenantFreePlanRestricted($user),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'expenses')) {
            return new Response('Forbidden', 403);
        }
        if (Auth::isTenantFreePlanRestricted($user)) {
            session_flash('errors', ['Premium: adding expenses is not available on the Free version. View plans & pricing to upgrade.']);

            return redirect(url('/tenant/expenses'));
        }
        $tenantId = (int) $user['tenant_id'];
        $desc = trim((string) $request->input('description'));
        $amount = (float) $request->input('amount');

        if ($desc === '' || $amount < money_min_positive()) {
            return redirect(url('/tenant/expenses'));
        }

        $pdo = App::db();
        $pdo->prepare(
            'INSERT INTO expenses (tenant_id, user_id, type, description, amount, created_at, updated_at)
             VALUES (?, ?, \'manual\', ?, ?, NOW(), NOW())'
        )->execute([$tenantId, $user['id'], $desc, $amount]);

        return redirect(url('/tenant/expenses'));
    }

    public function update(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'expenses')) {
            return new Response('Forbidden', 403);
        }
        if (Auth::isTenantFreePlanRestricted($user)) {
            session_flash('errors', ['Premium: editing expenses is not available on the Free version.']);

            return redirect(url('/tenant/expenses'));
        }
        $tenantId = (int) $user['tenant_id'];
        $desc = trim((string) $request->input('description'));
        $amount = (float) $request->input('amount');
        if ($desc === '' || $amount < money_min_positive()) {
            session_flash('errors', ['Description and amount are required.']);

            return redirect(url('/tenant/expenses'));
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT type FROM expenses WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, (int) $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row || ($row['type'] ?? '') !== 'manual') {
            return new Response('Forbidden', 403);
        }

        $pdo->prepare(
            'UPDATE expenses
             SET description = ?, amount = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        )->execute([$desc, $amount, $tenantId, (int) $id]);

        session_flash('success', 'Expense updated.');

        return redirect(url('/tenant/expenses'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'expenses')) {
            return new Response('Forbidden', 403);
        }
        if (Auth::isTenantFreePlanRestricted($user)) {
            session_flash('errors', ['Premium: deleting expenses is not available on the Free version.']);

            return redirect(url('/tenant/expenses'));
        }
        $pdo = App::db();
        $st = $pdo->prepare('SELECT type FROM expenses WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([(int) $user['tenant_id'], (int) $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (! $row || $row['type'] !== 'manual') {
            return new Response('Forbidden', 403);
        }
        $pdo->prepare('DELETE FROM expenses WHERE tenant_id = ? AND id = ?')->execute([(int) $user['tenant_id'], (int) $id]);

        return redirect(url('/tenant/expenses'));
    }
}
