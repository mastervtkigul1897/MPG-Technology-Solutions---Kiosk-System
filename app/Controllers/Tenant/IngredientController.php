<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class IngredientController
{
    private const UNITS = 'pc,pcs,g,kg,ml,l,oz,lb,cup,tbsp,tsp,pack,sachet,bottle,can,box,tray,bundle,set,slice,serving';

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 'tenant_id = ?';
            $params = [$tenantId];
            if ($search !== '') {
                $where .= ' AND (name LIKE ? OR unit LIKE ? OR id LIKE ?)';
                $like = '%'.$search.'%';
                array_push($params, $like, $like, $like);
            }

            $total = (int) $pdo->query('SELECT COUNT(*) FROM ingredients WHERE tenant_id = '.$tenantId)->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM ingredients WHERE $where");
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            $columns = ['id', 'name', 'unit', 'stock_quantity', 'low_stock_threshold'];
            $orderIdx = (int) data_get($request->all(), 'order.0.column', 1);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $orderBy = $columns[$orderIdx] ?? 'name';
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT * FROM ingredients WHERE $where ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $ingredient) {
                $actions = '';
                if (Auth::tenantMayManage($user, 'ingredients')) {
                    $id = (int) $ingredient['id'];
                    $actions = '<div class="d-flex gap-1">'
                        .'<button type="button" class="btn btn-sm btn-outline-primary js-edit" data-id="'.$id.'" title="Edit"><i class="fa fa-pen"></i></button>'
                        .'<button type="button" class="btn btn-sm btn-outline-danger js-delete" data-id="'.$id.'" title="Delete"><i class="fa fa-trash"></i></button>'
                        .'</div>';
                }
                $data[] = [
                    'id' => $ingredient['id'],
                    'name' => e((string) $ingredient['name']),
                    'unit' => e((string) $ingredient['unit']),
                    'stock_quantity' => number_format((float) $ingredient['stock_quantity'], 2),
                    'low_stock_threshold' => number_format((float) $ingredient['low_stock_threshold'], 2),
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

        return view_page('Inventory Items', 'tenant.ingredients.index', [
            'allowed_units' => explode(',', self::UNITS),
        ]);
    }

    public function notifications(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 'tenant_id = ? AND stock_quantity <= low_stock_threshold';
            $params = [$tenantId];
            if ($search !== '') {
                $where .= ' AND (name LIKE ? OR unit LIKE ? OR id LIKE ?)';
                $like = '%'.$search.'%';
                array_push($params, $like, $like, $like);
            }

            $total = (int) $pdo->query(
                'SELECT COUNT(*) FROM ingredients WHERE tenant_id = '.$tenantId.' AND stock_quantity <= low_stock_threshold'
            )->fetchColumn();
            $st = $pdo->prepare("SELECT COUNT(*) FROM ingredients WHERE $where");
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            $columns = ['id', 'name', 'unit', 'stock_quantity', 'low_stock_threshold'];
            $orderIdx = (int) data_get($request->all(), 'order.0.column', 1);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $orderBy = $columns[$orderIdx] ?? 'name';
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT * FROM ingredients WHERE $where ORDER BY $orderBy $orderDir LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $ingredient) {
                $actions = '';
                if (Auth::tenantMayManage($user, 'notifications') || Auth::tenantMayManage($user, 'ingredients')) {
                    $id = (int) $ingredient['id'];
                    $actions = '<button type="button" class="btn btn-sm btn-outline-primary js-edit" data-id="'.$id.'" title="Edit"><i class="fa fa-pen"></i></button>';
                }
                $data[] = [
                    'id' => $ingredient['id'],
                    'name' => e((string) $ingredient['name']),
                    'unit' => e((string) $ingredient['unit']),
                    'stock_quantity' => number_format((float) $ingredient['stock_quantity'], 2),
                    'low_stock_threshold' => number_format((float) $ingredient['low_stock_threshold'], 2),
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

        $branchExpiredNotice = session_get('branch_expired_notice');
        if (is_array($branchExpiredNotice)) {
            session_set('branch_expired_notice', null);
        } else {
            $branchExpiredNotice = null;
        }

        return view_page('Notifications', 'tenant.notifications.index', [
            'allowed_units' => explode(',', self::UNITS),
            'branchExpiredNotice' => $branchExpiredNotice,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'ingredients')) {
            return new Response('Forbidden', 403);
        }
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        $name = trim((string) $request->input('name'));
        $unit = (string) $request->input('unit');
        $stock = (float) $request->input('stock_quantity');
        $low = $request->input('low_stock_threshold');
        $low = $low === null || $low === '' ? 0.0 : (float) $low;

        $units = explode(',', self::UNITS);
        if (! in_array($unit, $units, true)) {
            return $this->jsonOrBack($request, ['name' => ['Invalid unit.']], 422);
        }

        $st = $pdo->prepare('SELECT id FROM ingredients WHERE tenant_id = ? AND LOWER(name) = LOWER(?) LIMIT 1');
        $st->execute([$tenantId, $name]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return $this->jsonOrBack($request, ['name' => ['Item already exists. Item ID: '.$existing['id']]], 422);
        }

        $pdo->prepare(
            'INSERT INTO ingredients (tenant_id, name, unit, unit_cost, stock_quantity, low_stock_threshold, created_at, updated_at)
             VALUES (?, ?, ?, 0, ?, ?, NOW(), NOW())'
        )->execute([$tenantId, $name, $unit, $stock, $low]);

        if ($request->wantsJson() || $request->ajax()) {
            return json_response(['message' => 'Item added successfully.']);
        }

        session_flash('success', 'Item added successfully.');

        return redirect(url('/tenant/ingredients'));
    }

    public function update(Request $request, string $id): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        $st = $pdo->prepare('SELECT * FROM ingredients WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, (int) $id]);
        $ingredient = $st->fetch(PDO::FETCH_ASSOC);
        if (! $ingredient) {
            return new Response('Not found', 404);
        }

        $canFullEdit = Auth::tenantMayManage($user, 'ingredients');
        $fromNotifications = $request->input('_source') === 'notifications';
        $canCashierNotifOnly = ($user['role'] ?? '') === 'cashier' && $fromNotifications
            && Auth::canAccessModule($user, 'notifications')
            && ! Auth::canAccessModule($user, 'ingredients');

        if (! $canFullEdit && ! $canCashierNotifOnly) {
            return new Response('Forbidden', 403);
        }
        if ($canCashierNotifOnly) {
            if ((float) $ingredient['stock_quantity'] > (float) $ingredient['low_stock_threshold']) {
                return new Response('Forbidden', 403);
            }
        }

        $previousStock = (float) $ingredient['stock_quantity'];
        $name = trim((string) $request->input('name'));
        $unit = (string) $request->input('unit');
        $stock = (float) $request->input('stock_quantity');
        $low = $request->input('low_stock_threshold');
        $low = $low === null || $low === '' ? 0.0 : (float) $low;

        $units = explode(',', self::UNITS);
        if (! in_array($unit, $units, true)) {
            return $this->jsonOrBack($request, ['name' => ['Invalid unit.']], 422);
        }

        $st = $pdo->prepare('SELECT id FROM ingredients WHERE tenant_id = ? AND LOWER(name) = LOWER(?) AND id != ? LIMIT 1');
        $st->execute([$tenantId, $name, (int) $id]);
        if ($row = $st->fetch()) {
            return $this->jsonOrBack($request, ['name' => ['Item already exists. Item ID: '.$row['id']]], 422);
        }

        $pdo->prepare(
            'UPDATE ingredients SET name = ?, unit = ?, stock_quantity = ?, low_stock_threshold = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?'
        )->execute([$name, $unit, $stock, $low, (int) $id, $tenantId]);

        $st = $pdo->prepare('SELECT * FROM ingredients WHERE id = ? LIMIT 1');
        $st->execute([(int) $id]);
        $fresh = $st->fetch(PDO::FETCH_ASSOC);
        $newStock = (float) ($fresh['stock_quantity'] ?? 0);
        $delta = $newStock - $previousStock;
        $source = (string) $request->input('_source', 'ingredients');

        // Log only low-stock / notification restocks (not every ingredient edit) to limit storage.
        if ($source === 'notifications') {
            $desc = sprintf(
                'Restock (notifications) [ID: %d, %s] stock change: %+0.2f (was %.2f → %.2f)',
                (int) $id,
                (string) $fresh['name'],
                $delta,
                $previousStock,
                $newStock
            );
            ActivityLogger::log(
                $tenantId,
                (int) $user['id'],
                (string) $user['role'],
                'inventory',
                'restock',
                $request,
                $desc,
                [
                    'ingredient_id' => (int) $id,
                    'ingredient_name' => $fresh['name'],
                    'previous_stock' => $previousStock,
                    'stock_change' => $delta,
                    'source' => 'notifications',
                ]
            );
        }

        if ($request->wantsJson() || $request->ajax()) {
            return json_response(['message' => 'Item updated successfully.']);
        }

        session_flash('success', 'Item updated successfully.');

        if ($request->input('_source') === 'notifications') {
            return redirect(url('/tenant/notifications'));
        }

        return redirect(url('/tenant/ingredients'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'ingredients')) {
            return new Response('Forbidden', 403);
        }
        $pdo = App::db();
        $pdo->prepare('DELETE FROM ingredients WHERE tenant_id = ? AND id = ?')->execute([(int) $user['tenant_id'], (int) $id]);

        if ($request->wantsJson() || $request->ajax()) {
            return json_response(['message' => 'Item deleted successfully.']);
        }

        session_flash('success', 'Item deleted successfully.');

        return redirect(url('/tenant/ingredients'));
    }

    /** @param  array<string, array<int, string>>  $errors */
    private function jsonOrBack(Request $request, array $errors, int $code): Response
    {
        if ($request->wantsJson() || $request->ajax()) {
            $msg = '';
            foreach ($errors as $e) {
                $msg = $e[0] ?? '';
                break;
            }

            return json_response(['message' => $msg, 'errors' => $errors], $code);
        }
        session_flash('errors', array_merge(...array_values($errors)));

        return redirect(url('/tenant/ingredients'));
    }
}
