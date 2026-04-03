<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use PDO;
use RuntimeException;

final class CheckoutService
{
    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function checkout(int $tenantId, int $userId, array $items): int
    {
        $pdo = App::db();
        $pdo->beginTransaction();
        try {
            $transactionId = $this->runCheckout($pdo, $tenantId, $userId, $items);
            $pdo->commit();

            return $transactionId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    private function runCheckout(PDO $pdo, int $tenantId, int $userId, array $items): int
    {
            $productIds = array_column($items, 'product_id');
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $st = $pdo->prepare("SELECT * FROM products WHERE tenant_id = ? AND id IN ($placeholders)");
            $st->execute(array_merge([$tenantId], $productIds));
            $products = [];
            foreach ($st->fetchAll() as $row) {
                $products[(int) $row['id']] = $row;
            }

            $required = [];
            $totalAmount = 0.0;

            foreach ($items as $item) {
                $pid = (int) $item['product_id'];
                $product = $products[$pid] ?? null;
                if (! $product) {
                    throw new RuntimeException('One or more products are invalid.');
                }
                $qty = max(1, (int) $item['quantity']);
                $totalAmount += (float) $product['price'] * $qty;

                $stIng = $pdo->prepare(
                    'SELECT pi.quantity_required, i.id, i.stock_quantity, i.name FROM product_ingredients pi
                     INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                     WHERE pi.tenant_id = ? AND pi.product_id = ?'
                );
                $stIng->execute([$tenantId, $pid]);
                foreach ($stIng->fetchAll() as $row) {
                    $need = (float) $row['quantity_required'] * $qty;
                    $iid = (int) $row['id'];
                    $required[$iid] = ($required[$iid] ?? 0) + $need;
                }
            }

            foreach ($required as $ingId => $needQty) {
                $stOne = $pdo->prepare('SELECT id, name, stock_quantity FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE');
                $stOne->execute([$tenantId, $ingId]);
                $ing = $stOne->fetch();
                if (! $ing || (float) $ing['stock_quantity'] < $needQty) {
                    $name = $ing['name'] ?? 'Unknown ingredient';
                    throw new RuntimeException("Insufficient stock for {$name}.");
                }
            }

            $stTr = $pdo->prepare(
                'INSERT INTO transactions (tenant_id, user_id, total_amount, expense_total, profit_total, status, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, \'completed\', NOW(), NOW())'
            );
            $stTr->execute([$tenantId, $userId, $totalAmount, $totalAmount]);
            $transactionId = (int) $pdo->lastInsertId();

            foreach ($items as $item) {
                $pid = (int) $item['product_id'];
                $product = $products[$pid];
                $qty = max(1, (int) $item['quantity']);
                $lineTotal = (float) $product['price'] * $qty;
                $stIt = $pdo->prepare(
                    'INSERT INTO transaction_items (tenant_id, transaction_id, product_id, quantity, unit_price, unit_expense, line_total, line_expense, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, 0, ?, 0, NOW(), NOW())'
                );
                $stIt->execute([$tenantId, $transactionId, $pid, $qty, $product['price'], $lineTotal]);
            }

            foreach ($required as $ingId => $needQty) {
                $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE tenant_id = ? AND id = ?')
                    ->execute([$needQty, $tenantId, $ingId]);
                $pdo->prepare(
                    'INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                     VALUES (?, ?, ?, ?, \'OUT\', ?, \'sale\', NOW(), NOW())'
                )->execute([$tenantId, $ingId, $transactionId, $userId, $needQty]);
            }

            return $transactionId;
    }
}
