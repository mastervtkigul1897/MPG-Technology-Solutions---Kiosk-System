<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class ActivityLogController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 'al.tenant_id = ?';
            $params = [$tenantId];
            if ($user['role'] !== 'tenant_admin') {
                $where .= ' AND al.user_id = ?';
                $params[] = $user['id'];
            }
            if ($search !== '') {
                $where .= ' AND (al.module LIKE ? OR al.action LIKE ? OR al.description LIKE ? OR EXISTS (SELECT 1 FROM users u WHERE u.id = al.user_id AND (u.name LIKE ? OR u.email LIKE ?)))';
                $like = '%'.$search.'%';
                array_push($params, $like, $like, $like, $like, $like);
            }

            $countSql = 'SELECT COUNT(*) FROM activity_logs al WHERE '.$where;
            $st = $pdo->prepare($countSql);
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            $totalWhere = 'tenant_id = ?';
            $totalParams = [$tenantId];
            if ($user['role'] !== 'tenant_admin') {
                $totalWhere .= ' AND user_id = ?';
                $totalParams[] = $user['id'];
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE '.$totalWhere);
            $st->execute($totalParams);
            $total = (int) $st->fetchColumn();

            $orderIdx = (int) data_get($request->all(), 'order.0.column', 1);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $orderColumnMap = [
                1 => 'al.id',
                2 => 'al.created_at',
                3 => 'al.module',
                4 => 'al.action',
                5 => 'u.name',
                6 => 'u.email',
                7 => 'u.role',
                8 => 'al.method',
                9 => 'al.description',
            ];
            $orderBy = $orderColumnMap[$orderIdx] ?? 'al.created_at';
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT al.*, u.name AS user_name, u.email AS user_email, u.role AS user_role_live
                    FROM activity_logs al
                    LEFT JOIN users u ON u.id = al.user_id
                    WHERE $where
                    ORDER BY $orderBy $orderDir
                    LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $isOwner = ($user['role'] ?? '') === 'tenant_admin';
            $data = [];
            foreach ($rows as $log) {
                $role = strtoupper((string) ($log['user_role'] ?? $log['user_role_live'] ?? ''));
                $lid = (int) $log['id'];
                $actions = '';
                if ($isOwner) {
                    $actions = '<form method="POST" action="'.e(url('/tenant/activity-logs/'.$lid)).'" class="d-inline" onsubmit="return confirm(\'Delete this activity log entry?\');">'
                        .csrf_field().method_field('DELETE')
                        .'<button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Delete log" aria-label="Delete log"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></form>';
                }
                $data[] = [
                    'id' => $log['id'],
                    'date' => $log['created_at'] ? date('M d, Y h:i A', strtotime((string) $log['created_at'])) : '',
                    'module' => e(ucfirst((string) $log['module'])),
                    'action' => e((string) $log['action']),
                    'user' => e((string) ($log['user_name'] ?? 'User')),
                    'email' => e((string) ($log['user_email'] ?? 'N/A')),
                    'role' => e($role),
                    'method' => e((string) $log['method']),
                    'description' => e((string) $log['description']),
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

        return view_page('Activity Log', 'tenant.activity-logs.index');
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        $logId = (int) $id;
        $del = $pdo->prepare('DELETE FROM activity_logs WHERE id = ? AND tenant_id = ?');
        $del->execute([$logId, $tenantId]);
        if ($del->rowCount() > 0) {
            ActivityLogger::log(
                $tenantId,
                (int) ($user['id'] ?? 0),
                (string) ($user['role'] ?? 'tenant_admin'),
                'activity_logs',
                'destroy',
                $request,
                'Deleted one activity log entry.',
                ['deleted_log_id' => $logId]
            );
        }

        session_flash('success', 'Activity log entry deleted.');

        return redirect(url('/tenant/activity-logs'));
    }

    public function clear(Request $request): Response
    {
        $user = Auth::user();
        if (! $user || $user['role'] !== 'tenant_admin') {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();
        $countSt = $pdo->prepare('SELECT COUNT(*) FROM activity_logs WHERE tenant_id = ?');
        $countSt->execute([$tenantId]);
        $total = (int) $countSt->fetchColumn();
        $pdo->prepare('DELETE FROM activity_logs WHERE tenant_id = ?')->execute([$tenantId]);
        ActivityLogger::log(
            $tenantId,
            (int) ($user['id'] ?? 0),
            (string) ($user['role'] ?? 'tenant_admin'),
            'activity_logs',
            'clear',
            $request,
            'Cleared tenant activity logs.',
            ['deleted_count' => $total]
        );

        session_flash('success', 'All activity logs for this store have been deleted.');

        return redirect(url('/tenant/activity-logs'));
    }
}
