<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;
use RuntimeException;

final class DamagedItemController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        if ($request->ajax() || $request->boolean('datatable')) {
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $where = 'd.tenant_id = ?';
            $params = [$tenantId];
            if ($search !== '') {
                $where .= ' AND (i.name LIKE ? OR CAST(d.id AS CHAR) LIKE ?)';
                $like = '%'.$search.'%';
                $params[] = $like;
                $params[] = $like;
            }

            $total = (int) $pdo->query(
                "SELECT COUNT(*) FROM damaged_items d WHERE d.tenant_id = {$tenantId}"
            )->fetchColumn();

            $st = $pdo->prepare("SELECT COUNT(*) FROM damaged_items d INNER JOIN ingredients i ON i.id = d.ingredient_id WHERE $where");
            $st->execute($params);
            $filtered = (int) $st->fetchColumn();

            // DataTables: column 0 is responsive control; 1..map to ID, ingredient, quantity, created_at, actions.
            $orderCol = (int) data_get($request->all(), 'order.0.column', 1);
            $orderDir = strtolower((string) data_get($request->all(), 'order.0.dir', 'desc')) === 'asc' ? 'ASC' : 'DESC';
            $orderMap = [
                1 => 'd.id',
                2 => 'i.name',
                3 => 'd.quantity',
                4 => 'd.created_at',
            ];
            $orderBy = $orderMap[$orderCol] ?? 'd.created_at';

            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 25)));

            $sql = "SELECT d.id, d.ingredient_id, d.quantity, d.created_at, i.name AS ingredient_name, i.unit
                    FROM damaged_items d
                    INNER JOIN ingredients i ON i.id = d.ingredient_id AND i.tenant_id = d.tenant_id
                    WHERE $where
                    ORDER BY $orderBy $orderDir
                    LIMIT $length OFFSET $start";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($rows as $row) {
                $did = (int) $row['id'];
                $editBtn = '';
                $deleteBtn = '';
                if (Auth::tenantMayManage($user, 'damaged_items')) {
                    $editBtn = '<button type="button" class="btn btn-sm btn-outline-primary js-edit-damaged" data-id="'.$did.'" title="Edit"><i class="fa fa-pen"></i></button>';
                    $deleteBtn = '<button type="button" class="btn btn-sm btn-outline-danger js-delete-damaged" data-id="'.$did.'" title="Delete"><i class="fa fa-trash"></i></button>';
                }

                $qtyValue = round((float) $row['quantity'], 2);
                $qtyStr = number_format((float) $qtyValue, 2, '.', '');

                $data[] = [
                    'id' => $did,
                    'ingredient_id' => (int) $row['ingredient_id'],
                    'ingredient_name' => e((string) $row['ingredient_name']),
                    'quantity' => $qtyStr,
                    'quantity_value' => (float) $qtyValue,
                    'unit' => e((string) $row['unit']),
                    'created_at' => $row['created_at'] ? date('M d, Y h:i A', strtotime((string) $row['created_at'])) : '',
                    'actions' => '<div class="d-flex gap-1">'.$editBtn.$deleteBtn.'</div>',
                ];
            }

            return json_response([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => $total,
                'recordsFiltered' => $filtered,
                'data' => $data,
            ]);
        }

        $st = $pdo->prepare('SELECT id, name, unit FROM ingredients WHERE tenant_id = ? ORDER BY name');
        $st->execute([$tenantId]);
        $ingredients = $st->fetchAll(PDO::FETCH_ASSOC);

        return view_page('Damaged Items', 'tenant.damaged-items.index', [
            'ingredients' => $ingredients,
        ]);
    }

    public function store(Request $request): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'damaged_items')) {
            return new Response('Forbidden', 403);
        }

        $tenantId = (int) $user['tenant_id'];
        $ingredientId = (int) $request->input('ingredient_id');
        $qty = round((float) $request->input('quantity'), 2);

        $errors = [];
        if ($ingredientId < 1) {
            $errors['ingredient_id'] = ['Invalid ingredient.'];
        }
        if ($qty < 0.01) {
            $errors['quantity'] = ['Quantity must be at least 0.01.'];
        }

        if ($errors !== []) {
            return $this->jsonOrBack($request, $errors, 422, '/tenant/damaged-items');
        }

        try {
            $pdo = App::db();
            $pdo->beginTransaction();

            // Deduct stock only if enough exists (prevents negative stock).
            $st = $pdo->prepare(
                'UPDATE ingredients
                 SET stock_quantity = stock_quantity - ?
                 WHERE tenant_id = ? AND id = ? AND stock_quantity >= ?'
            );
            $st->execute([$qty, $tenantId, $ingredientId, $qty]);
            if ($st->rowCount() < 1) {
                $pdo->rollBack();

                return $this->jsonOrBack($request, ['quantity' => ['Insufficient stock for selected ingredient.']], 422, '/tenant/damaged-items');
            }

            $pdo->prepare(
                'INSERT INTO damaged_items (tenant_id, user_id, ingredient_id, quantity, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NOW(), NOW())'
            )->execute([$tenantId, (int) $user['id'], $ingredientId, $qty]);

            $pdo->prepare(
                'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, \'OUT\', ?, \'damage\', NOW(), NOW())'
            )->execute([$tenantId, $ingredientId, (int) $user['id'], $qty]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // For security: don't show raw DB errors by default.
            return $this->jsonOrBack(
                $request,
                ['quantity' => ['Unable to save damage entry. Please try again.']],
                500,
                '/tenant/damaged-items'
            );
        }

        if ($request->wantsJson() || $request->ajax()) {
            return json_response(['message' => 'Damage entry added successfully.']);
        }

        session_flash('success', 'Damage entry added successfully.');

        return redirect(url('/tenant/damaged-items'));
    }

    public function update(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'damaged_items')) {
            return new Response('Forbidden', 403);
        }

        $tenantId = (int) $user['tenant_id'];
        $damageId = (int) $id;
        $newIngredientId = (int) $request->input('ingredient_id');
        $newQty = round((float) $request->input('quantity'), 2);

        if ($damageId < 1) {
            return new Response('Not found', 404);
        }

        $errors = [];
        if ($newIngredientId < 1) {
            $errors['ingredient_id'] = ['Invalid ingredient.'];
        }
        if ($newQty < 0.01) {
            $errors['quantity'] = ['Quantity must be at least 0.01.'];
        }
        if ($errors !== []) {
            return $this->jsonOrBack($request, $errors, 422, '/tenant/damaged-items');
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT ingredient_id, quantity FROM damaged_items WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $damageId]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if (! $existing) {
            return new Response('Not found', 404);
        }

        $oldIngredientId = (int) $existing['ingredient_id'];
        $oldQty = (float) $existing['quantity'];
        $delta = $newQty - $oldQty;

        try {
            $pdo->beginTransaction();

            if ($newIngredientId !== $oldIngredientId) {
                // Restore old ingredient stock (IN).
                $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity + ? WHERE tenant_id = ? AND id = ?')
                    ->execute([$oldQty, $tenantId, $oldIngredientId]);

                // Deduct new ingredient stock (OUT) only if enough exists.
                $st2 = $pdo->prepare(
                    'UPDATE ingredients
                     SET stock_quantity = stock_quantity - ?
                     WHERE tenant_id = ? AND id = ? AND stock_quantity >= ?'
                );
                $st2->execute([$newQty, $tenantId, $newIngredientId, $newQty]);
                if ($st2->rowCount() < 1) {
                    $pdo->rollBack();
                    return $this->jsonOrBack($request, ['quantity' => ['Insufficient stock for selected ingredient.']], 422, '/tenant/damaged-items');
                }

                $pdo->prepare(
                    'UPDATE damaged_items SET ingredient_id = ?, quantity = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
                )->execute([$newIngredientId, $newQty, $tenantId, $damageId]);

                // Inventory movements: IN for old restore, OUT for new deduct.
                $pdo->prepare(
                    'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                     VALUES (?, ?, NULL, ?, \'IN\', ?, \'damage_edit\', NOW(), NOW())'
                )->execute([$tenantId, $oldIngredientId, (int) $user['id'], $oldQty]);

                $pdo->prepare(
                    'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                     VALUES (?, ?, NULL, ?, \'OUT\', ?, \'damage_edit\', NOW(), NOW())'
                )->execute([$tenantId, $newIngredientId, (int) $user['id'], $newQty]);
            } else {
                // Same ingredient: adjust by delta.
                if (abs($delta) >= 0.0001) {
                    if ($delta > 0) {
                        // More damage => stock decreases.
                        $st2 = $pdo->prepare(
                            'UPDATE ingredients
                             SET stock_quantity = stock_quantity - ?
                             WHERE tenant_id = ? AND id = ? AND stock_quantity >= ?'
                        );
                        $st2->execute([$delta, $tenantId, $oldIngredientId, $delta]);
                        if ($st2->rowCount() < 1) {
                            $pdo->rollBack();
                            return $this->jsonOrBack($request, ['quantity' => ['Insufficient stock for the increased damage quantity.']], 422, '/tenant/damaged-items');
                        }

                        $pdo->prepare(
                            'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                             VALUES (?, ?, NULL, ?, \'OUT\', ?, \'damage_edit\', NOW(), NOW())'
                        )->execute([$tenantId, $oldIngredientId, (int) $user['id'], $delta]);
                    } else {
                        // Less damage => stock increases.
                        $restore = abs($delta);
                        $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity + ? WHERE tenant_id = ? AND id = ?')
                            ->execute([$restore, $tenantId, $oldIngredientId]);

                        $pdo->prepare(
                            'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                             VALUES (?, ?, NULL, ?, \'IN\', ?, \'damage_edit\', NOW(), NOW())'
                        )->execute([$tenantId, $oldIngredientId, (int) $user['id'], $restore]);
                    }
                }

                $pdo->prepare(
                    'UPDATE damaged_items SET quantity = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
                )->execute([$newQty, $tenantId, $damageId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->jsonOrBack(
                $request,
                ['quantity' => ['Unable to update damage entry. Please try again.']],
                500,
                '/tenant/damaged-items'
            );
        }

        if ($request->wantsJson() || $request->ajax()) {
            return json_response(['message' => 'Damage entry updated successfully.']);
        }

        session_flash('success', 'Damage entry updated successfully.');

        return redirect(url('/tenant/damaged-items'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = Auth::user();
        if (! Auth::tenantMayManage($user, 'damaged_items')) {
            return new Response('Forbidden', 403);
        }

        $tenantId = (int) $user['tenant_id'];
        $damageId = (int) $id;
        if ($damageId < 1) {
            return new Response('Not found', 404);
        }

        $pdo = App::db();
        $st = $pdo->prepare('SELECT ingredient_id, quantity FROM damaged_items WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $damageId]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);
        if (! $existing) {
            return new Response('Not found', 404);
        }

        $ingredientId = (int) $existing['ingredient_id'];
        $qty = (float) $existing['quantity'];

        try {
            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM damaged_items WHERE tenant_id = ? AND id = ?')
                ->execute([$tenantId, $damageId]);

            // Restore stock (IN).
            $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity + ? WHERE tenant_id = ? AND id = ?')
                ->execute([$qty, $tenantId, $ingredientId]);

            $pdo->prepare(
                'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, \'IN\', ?, \'damage_delete\', NOW(), NOW())'
            )->execute([$tenantId, $ingredientId, (int) $user['id'], $qty]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return $this->jsonOrBack(
                $request,
                ['id' => ['Unable to delete damage entry. Please try again.']],
                500,
                '/tenant/damaged-items'
            );
        }

        if ($request->wantsJson() || $request->ajax()) {
            return json_response(['message' => 'Damage entry deleted successfully.']);
        }

        session_flash('success', 'Damage entry deleted successfully.');

        return redirect(url('/tenant/damaged-items'));
    }

    /** @param  array<string, array<int,string>>  $errors */
    private function jsonOrBack(Request $request, array $errors, int $code, string $redirectTo): Response
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

        return redirect(url($redirectTo));
    }
}

