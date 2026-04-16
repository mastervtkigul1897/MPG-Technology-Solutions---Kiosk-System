<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\FlavorSchema;
use PDO;
use RuntimeException;

final class CheckoutService
{
    /** Cached only when schema exists (true), so we re-check after DB migrations without restarting PHP. */
    private static ?bool $transactionsPaymentSchemaReady = null;

    private static function ensureTransactionsPaymentSchema(PDO $pdo): void
    {
        if (self::$transactionsPaymentSchemaReady === true) {
            return;
        }
        try {
            // IMPORTANT: Don't call fetch() twice on the same result set.
            $hasAmountTendered = false;
            $hasChangeAmount = false;
            $hasWasUpdated = false;
            $hasPendingName = false;
            $hasPendingContact = false;
            $hasPaymentMethod = false;
            $hasAmountPaid = false;
            $hasPaymentBreakdownJson = false;
            $hasRefundedAmount = false;
            $hasAddedPaidAmount = false;
            $hasOriginalTotalAmount = false;
            try {
                $ra = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'amount_tendered'");
                $hasAmountTendered = $ra !== false && $ra->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rb = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'change_amount'");
                $hasChangeAmount = $rb !== false && $rb->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rc = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'was_updated'");
                $hasWasUpdated = $rc !== false && $rc->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rd = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'pending_name'");
                $hasPendingName = $rd !== false && $rd->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $re = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'pending_contact'");
                $hasPendingContact = $re !== false && $re->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rf = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'payment_method'");
                $hasPaymentMethod = $rf !== false && $rf->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rg = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'amount_paid'");
                $hasAmountPaid = $rg !== false && $rg->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rgb = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'payment_breakdown_json'");
                $hasPaymentBreakdownJson = $rgb !== false && $rgb->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rh = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'refunded_amount'");
                $hasRefundedAmount = $rh !== false && $rh->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $ri = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'added_paid_amount'");
                $hasAddedPaidAmount = $ri !== false && $ri->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }
            try {
                $rj = $pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'original_total_amount'");
                $hasOriginalTotalAmount = $rj !== false && $rj->fetch(\PDO::FETCH_ASSOC) !== false;
            } catch (\Throwable) {
            }

            // Keep this tolerant: add what is missing (ignore duplicates/permissions).
            if (! $hasAmountTendered) {
                try {
                    $pdo->exec('ALTER TABLE `transactions` ADD COLUMN `amount_tendered` DECIMAL(38,16) NULL DEFAULT NULL AFTER `total_amount`');
                } catch (\Throwable) {
                }
            }
            if (! $hasChangeAmount) {
                try {
                    $pdo->exec('ALTER TABLE `transactions` ADD COLUMN `change_amount` DECIMAL(38,16) NULL DEFAULT NULL AFTER `amount_tendered`');
                } catch (\Throwable) {
                }
            }
            if (! $hasPaymentMethod) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `payment_method` VARCHAR(30) NULL DEFAULT NULL AFTER `change_amount`");
                } catch (\Throwable) {
                }
            }
            if (! $hasAmountPaid) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `amount_paid` DECIMAL(38,16) NULL DEFAULT NULL AFTER `payment_method`");
                } catch (\Throwable) {
                }
            }
            if (! $hasPaymentBreakdownJson) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `payment_breakdown_json` LONGTEXT NULL DEFAULT NULL AFTER `amount_paid`");
                } catch (\Throwable) {
                }
            }
            if (! $hasRefundedAmount) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `refunded_amount` DECIMAL(38,16) NOT NULL DEFAULT 0 AFTER `amount_paid`");
                } catch (\Throwable) {
                }
            }
            if (! $hasAddedPaidAmount) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `added_paid_amount` DECIMAL(38,16) NOT NULL DEFAULT 0 AFTER `refunded_amount`");
                } catch (\Throwable) {
                }
            }
            if (! $hasOriginalTotalAmount) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `original_total_amount` DECIMAL(38,16) NULL DEFAULT NULL AFTER `added_paid_amount`");
                } catch (\Throwable) {
                }
            }
            if (! $hasWasUpdated) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `was_updated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
                } catch (\Throwable) {
                }
            }
            if (! $hasPendingName) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `pending_name` VARCHAR(255) NULL DEFAULT NULL AFTER `was_updated`");
                } catch (\Throwable) {
                }
            }
            if (! $hasPendingContact) {
                try {
                    $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `pending_contact` VARCHAR(50) NULL DEFAULT NULL AFTER `pending_name`");
                } catch (\Throwable) {
                }
            }

            // Enforce sensible defaults (tolerant; safe to run repeatedly).
            try {
                $pdo->exec("UPDATE `transactions` SET `payment_method` = 'cash' WHERE `payment_method` IS NULL OR TRIM(`payment_method`) = ''");
            } catch (\Throwable) {
            }
            try {
                $pdo->exec("UPDATE `transactions` SET `amount_paid` = 0 WHERE `amount_paid` IS NULL");
            } catch (\Throwable) {
            }
            try {
                $pdo->exec("ALTER TABLE `transactions` MODIFY COLUMN `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash'");
            } catch (\Throwable) {
            }
            try {
                $pdo->exec("ALTER TABLE `transactions` MODIFY COLUMN `amount_paid` DECIMAL(38,16) NOT NULL DEFAULT 0");
            } catch (\Throwable) {
            }

            // Helpful index for edit/receipt queries (qty>0 filter).
            try {
                $pdo->exec("ALTER TABLE `transaction_items` ADD INDEX `ti_tenant_tx_qty_idx` (`tenant_id`, `transaction_id`, `quantity`)");
            } catch (\Throwable) {
            }

            // Index for fast today/receipt queries
            $idx = $pdo->query("SHOW INDEX FROM `transactions` WHERE Key_name = 'transactions_tenant_status_created_at_index'");
            $hasIdx = $idx !== false && $idx->fetch(\PDO::FETCH_ASSOC) !== false;
            if (! $hasIdx) {
                try {
                    $pdo->exec('ALTER TABLE `transactions` ADD INDEX `transactions_tenant_status_created_at_index` (`tenant_id`, `status`, `created_at`)');
                } catch (\Throwable) {
                }
            }

            self::$transactionsPaymentSchemaReady = true;
        } catch (\Throwable) {
            // If schema changes fail due to permissions, continue without blocking checkout.
        }
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function checkout(int $tenantId, int $userId, array $items): int
    {
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        self::ensureTransactionsPaymentSchema($pdo);
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
     * Create a pending (credit) transaction (no stock deduction yet).
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    public function createPending(int $tenantId, int $userId, array $items, string $pendingName, ?string $pendingContact): int
    {
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        self::ensureTransactionsPaymentSchema($pdo);
        $pdo->beginTransaction();
        try {
            $transactionId = $this->runCreateTransactionWithPendingMeta($pdo, $tenantId, $userId, $items, 'pending', false, $pendingName, $pendingContact);
            $pdo->commit();

            return $transactionId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Pay/complete an existing pending transaction (deduct stock now).
     *
     * @throws RuntimeException when transaction is not payable
     */
    public function payPending(int $tenantId, int $userId, int $pendingTransactionId, float $amountTendered): int
    {
        $pdo = App::db();
        FlavorSchema::ensure($pdo);
        self::ensureTransactionsPaymentSchema($pdo);
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT id, total_amount, status FROM transactions WHERE tenant_id = ? AND id = ? FOR UPDATE');
            $st->execute([$tenantId, $pendingTransactionId]);
            $tx = $st->fetch(PDO::FETCH_ASSOC);
            if (! $tx) {
                throw new RuntimeException('Pending transaction not found.');
            }
            if ((string) ($tx['status'] ?? '') !== 'pending') {
                throw new RuntimeException('This transaction is no longer pending.');
            }
            $total = (float) ($tx['total_amount'] ?? 0);
            if ($amountTendered < $total) {
                throw new RuntimeException('Cash received is less than total.');
            }
            $change = $amountTendered - $total;

            $st = $pdo->prepare('SELECT product_id, quantity, flavor_ingredient_id FROM transaction_items WHERE tenant_id = ? AND transaction_id = ?');
            $st->execute([$tenantId, $pendingTransactionId]);
            $items = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => max(1, (int) ($row['quantity'] ?? 0)),
                    'flavor_ingredient_id' => max(0, (int) ($row['flavor_ingredient_id'] ?? 0)),
                ];
            }
            if ($items === []) {
                throw new RuntimeException('Pending transaction has no items.');
            }

            // Deduct stock & movements now, but do not create a new transaction row.
            $this->runDeductStockForItems($pdo, $tenantId, $userId, $pendingTransactionId, $items, 'sale');

            $pdo->prepare(
                "UPDATE transactions
                 SET status = 'completed', user_id = ?, amount_tendered = ?, change_amount = ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?"
            )->execute([$userId, $amountTendered, $change, $tenantId, $pendingTransactionId]);

            $pdo->commit();

            return $pendingTransactionId;
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
            return $this->runCreateTransaction($pdo, $tenantId, $userId, $items, 'completed', true);
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    private function runCreateTransaction(PDO $pdo, int $tenantId, int $userId, array $items, string $status, bool $deductStock): int
    {
        return $this->runCreateTransactionWithPendingMeta($pdo, $tenantId, $userId, $items, $status, $deductStock, null, null);
    }

    /**
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    private function runCreateTransactionWithPendingMeta(PDO $pdo, int $tenantId, int $userId, array $items, string $status, bool $deductStock, ?string $pendingName, ?string $pendingContact): int
    {
        self::ensureTransactionsPaymentSchema($pdo);
        FlavorSchema::ensure($pdo);

        $productIds = array_column($items, 'product_id');
        if ($productIds === []) {
            throw new RuntimeException('Cart is empty.');
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $st = $pdo->prepare("SELECT * FROM products WHERE tenant_id = ? AND id IN ($placeholders)");
        $st->execute(array_merge([$tenantId], $productIds));
        $products = [];
        foreach ($st->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }

        $totalAmount = 0.0;
        $flavorMapByProduct = [];
        $stFl = $pdo->prepare(
            "SELECT pfi.product_id, pfi.ingredient_id, pfi.quantity_required, i.name, i.stock_quantity
             FROM product_flavor_ingredients pfi
             INNER JOIN ingredients i ON i.id = pfi.ingredient_id AND i.tenant_id = pfi.tenant_id
             WHERE pfi.tenant_id = ?"
        );
        $stFl->execute([$tenantId]);
        foreach ($stFl->fetchAll(PDO::FETCH_ASSOC) as $fr) {
            $pid = (int) ($fr['product_id'] ?? 0);
            $fid = (int) ($fr['ingredient_id'] ?? 0);
            if ($pid < 1 || $fid < 1) {
                continue;
            }
            if (! isset($flavorMapByProduct[$pid])) {
                $flavorMapByProduct[$pid] = [];
            }
            $flavorMapByProduct[$pid][$fid] = [
                'id' => $fid,
                'name' => (string) ($fr['name'] ?? ''),
                'stock_quantity' => (float) ($fr['stock_quantity'] ?? 0),
                'qty_required' => (float) ($fr['quantity_required'] ?? 1),
            ];
        }
        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $product = $products[$pid] ?? null;
            if (! $product) {
                throw new RuntimeException('One or more products are invalid.');
            }
            $qty = max(1, (int) $item['quantity']);
            $flavorId = max(0, (int) ($item['flavor_ingredient_id'] ?? 0));
            $hasFlavorOptions = (int) ($product['has_flavor_options'] ?? 0) === 1;
            if ($hasFlavorOptions) {
                $flavors = $flavorMapByProduct[$pid] ?? [];
                if ($flavorId < 1 || ! isset($flavors[$flavorId])) {
                    throw new RuntimeException('Please select a valid flavor.');
                }
                $flavorReq = max(stock_min_positive(), (float) ($flavors[$flavorId]['qty_required'] ?? 1));
                if ((float) ($flavors[$flavorId]['stock_quantity'] ?? 0) < ($qty * $flavorReq)) {
                    throw new RuntimeException('Selected flavor is out of stock.');
                }
            }
            $totalAmount += (float) $product['price'] * $qty;
        }

        $hasPendingName = false;
        $hasPendingContact = false;
        try {
            $hasPendingName = ($pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'pending_name'")?->fetch(\PDO::FETCH_ASSOC)) !== false;
            $hasPendingContact = ($pdo->query("SHOW COLUMNS FROM `transactions` LIKE 'pending_contact'")?->fetch(\PDO::FETCH_ASSOC)) !== false;
        } catch (\Throwable) {
            // ignore
        }

        if ($status === 'pending' && $hasPendingName) {
            $cols = 'tenant_id, user_id, total_amount, expense_total, profit_total, status, pending_name';
            $vals = '?, ?, ?, 0, ?, ?, ?';
            $params = [$tenantId, $userId, $totalAmount, $totalAmount, $status, $pendingName ?? ''];
            if ($hasPendingContact) {
                $cols .= ', pending_contact';
                $vals .= ', ?';
                $params[] = $pendingContact;
            }
            $stTr = $pdo->prepare("INSERT INTO transactions ($cols, created_at, updated_at) VALUES ($vals, NOW(), NOW())");
            $stTr->execute($params);
        } else {
            $stTr = $pdo->prepare(
                'INSERT INTO transactions (tenant_id, user_id, total_amount, expense_total, profit_total, status, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, ?, NOW(), NOW())'
            );
            $stTr->execute([$tenantId, $userId, $totalAmount, $totalAmount, $status]);
        }
        $transactionId = (int) $pdo->lastInsertId();

        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $product = $products[$pid];
            $qty = max(1, (int) $item['quantity']);
            $lineTotal = (float) $product['price'] * $qty;
            $flavorId = max(0, (int) ($item['flavor_ingredient_id'] ?? 0));
            $flavorName = null;
            $flavorQtyRequired = null;
            if ($flavorId > 0) {
                $flavorName = (string) (($flavorMapByProduct[$pid][$flavorId]['name'] ?? null) ?: '');
                $flavorQtyRequired = max(stock_min_positive(), (float) (($flavorMapByProduct[$pid][$flavorId]['qty_required'] ?? 1)));
                if ($flavorName === '') {
                    $flavorId = 0;
                    $flavorQtyRequired = null;
                }
            }
            $stIt = $pdo->prepare(
                'INSERT INTO transaction_items (tenant_id, transaction_id, product_id, flavor_ingredient_id, flavor_name, flavor_quantity_required, quantity, unit_price, unit_expense, line_total, line_expense, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 0, NOW(), NOW())'
            );
            $stIt->execute([$tenantId, $transactionId, $pid, $flavorId > 0 ? $flavorId : null, $flavorName, $flavorQtyRequired, $qty, $product['price'], $lineTotal]);
        }

        if ($deductStock) {
            $this->runDeductStockForItems($pdo, $tenantId, $userId, $transactionId, $items, 'sale');
        }

        return $transactionId;
    }

    /**
     * Compute ingredient requirements for items and apply stock/movements.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     */
    private function runDeductStockForItems(PDO $pdo, int $tenantId, int $userId, int $transactionId, array $items, string $reason): void
    {
        $productIds = array_column($items, 'product_id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $st = $pdo->prepare("SELECT id, price, name FROM products WHERE tenant_id = ? AND id IN ($placeholders)");
        $st->execute(array_merge([$tenantId], $productIds));
        $products = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $products[(int) $row['id']] = $row;
        }
        $flavorMapByProduct = [];
        $stFl = $pdo->prepare(
            "SELECT product_id, ingredient_id, quantity_required
             FROM product_flavor_ingredients
             WHERE tenant_id = ?"
        );
        $stFl->execute([$tenantId]);
        foreach ($stFl->fetchAll(PDO::FETCH_ASSOC) as $fr) {
            $pid = (int) ($fr['product_id'] ?? 0);
            $fid = (int) ($fr['ingredient_id'] ?? 0);
            if ($pid < 1 || $fid < 1) {
                continue;
            }
            if (! isset($flavorMapByProduct[$pid])) {
                $flavorMapByProduct[$pid] = [];
            }
            $flavorMapByProduct[$pid][$fid] = [
                'qty_required' => (float) ($fr['quantity_required'] ?? 1),
            ];
        }

        $required = [];
        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $product = $products[$pid] ?? null;
            if (! $product) {
                throw new RuntimeException('One or more products are invalid.');
            }
            $qty = max(1, (int) $item['quantity']);
            $stIng = $pdo->prepare(
                'SELECT pi.quantity_required, i.id, i.stock_quantity, i.name FROM product_ingredients pi
                 INNER JOIN ingredients i ON i.id = pi.ingredient_id AND i.tenant_id = pi.tenant_id
                 WHERE pi.tenant_id = ? AND pi.product_id = ?'
            );
            $stIng->execute([$tenantId, $pid]);
            foreach ($stIng->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $need = (float) $row['quantity_required'] * $qty;
                $iid = (int) $row['id'];
                $required[$iid] = ($required[$iid] ?? 0) + $need;
            }
            $flavorId = max(0, (int) ($item['flavor_ingredient_id'] ?? 0));
            if ($flavorId > 0) {
                $flavorReq = max(stock_min_positive(), (float) (($flavorMapByProduct[$pid][$flavorId]['qty_required'] ?? 1)));
                $required[$flavorId] = ($required[$flavorId] ?? 0) + ($qty * $flavorReq);
            }
        }

        $roundedRequired = [];
        foreach ($required as $ingId => $needQty) {
            $roundedRequired[(int) $ingId] = round_stock((float) $needQty);
        }

        foreach ($roundedRequired as $ingId => $needQty) {
            $stOne = $pdo->prepare('SELECT id, name, stock_quantity FROM ingredients WHERE tenant_id = ? AND id = ? FOR UPDATE');
            $stOne->execute([$tenantId, $ingId]);
            $ing = $stOne->fetch(PDO::FETCH_ASSOC);
            if (! $ing || (float) $ing['stock_quantity'] < $needQty) {
                $name = $ing['name'] ?? 'Unknown ingredient';
                throw new RuntimeException("Insufficient stock for {$name}.");
            }
        }

        foreach ($roundedRequired as $ingId => $needQty) {
            $pdo->prepare('UPDATE ingredients SET stock_quantity = stock_quantity - ? WHERE tenant_id = ? AND id = ?')
                ->execute([$needQty, $tenantId, $ingId]);
            $pdo->prepare(
                "INSERT INTO inventory_movements (tenant_id, ingredient_id, transaction_id, user_id, type, quantity, reason, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 'OUT', ?, ?, NOW(), NOW())"
            )->execute([$tenantId, $ingId, $transactionId, $userId, $needQty, $reason]);
        }
    }
}
