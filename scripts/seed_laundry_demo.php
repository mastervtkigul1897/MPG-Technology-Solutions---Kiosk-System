<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\App;
use App\Core\LaundrySchema;

$pdo = App::db();
LaundrySchema::ensure($pdo);

$tenantIds = $pdo->query('SELECT id FROM tenants ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
if ($tenantIds === []) {
    fwrite(STDOUT, "No tenants found. Nothing to seed.\n");
    exit(0);
}

foreach ($tenantIds as $tenantIdRaw) {
    $tenantId = (int) $tenantIdRaw;

    $ordersCountSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_orders WHERE tenant_id = ?');
    $ordersCountSt->execute([$tenantId]);
    $ordersCount = (int) $ordersCountSt->fetchColumn();
    if ($ordersCount > 0) {
        fwrite(STDOUT, "Tenant {$tenantId}: skipped (already has laundry orders).\n");
        continue;
    }

    $pdo->beginTransaction();
    try {
        $stMachine = $pdo->prepare(
            'INSERT INTO laundry_machines (tenant_id, machine_kind, machine_code, machine_label, status, current_order_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, "available", NULL, NOW(), NOW())
             ON DUPLICATE KEY UPDATE machine_label = VALUES(machine_label), machine_kind = VALUES(machine_kind), updated_at = NOW()'
        );
        foreach (
            [
                ['washer', 'W-01', 'Washer #1'],
                ['washer', 'W-02', 'Washer #2'],
                ['washer', 'W-03', 'Washer #3'],
                ['dryer', 'D-01', 'Dryer #1'],
                ['dryer', 'D-02', 'Dryer #2'],
                ['dryer', 'D-03', 'Dryer #3'],
            ] as $m
        ) {
            $stMachine->execute([$tenantId, $m[0], $m[1], $m[2]]);
        }

        $customers = [
            ['Juan Dela Cruz', '09171234567', '1992-04-20'],
            ['Maria Santos', '09998887777', '1995-04-25'],
            ['Ana Reyes', '', '1990-06-15'],
            ['Carlo Villanueva', '09175556666', '1989-11-03'],
            ['Sofia Garcia', '', '1998-04-30'],
        ];
        $stCustomer = $pdo->prepare(
            'INSERT INTO laundry_customers (tenant_id, name, contact, birthday, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $customerIds = [];
        foreach ($customers as $c) {
            $stCustomer->execute([$tenantId, $c[0], $c[1] !== '' ? $c[1] : null, $c[2]]);
            $customerIds[] = (int) $pdo->lastInsertId();
        }

        $inventoryBoost = [
            ['Detergent', 80, 12],
            ['Fabric Conditioner', 65, 10],
            ['Bleach Sachet', 30, 9],
            ['Colorsafe (1 Liter)', 12, 70],
            ['Baking Soda', 5, 25],
            ['Vinegar', 6, 30],
            ['Finishing Spray', 10, 45],
            ['Gas', 4, 900],
            ['Cellophane', 120, 1.5],
            ['Receipt', 4, 50],
        ];
        $stInv = $pdo->prepare(
            'UPDATE laundry_inventory_items
             SET stock_quantity = ?, unit_cost = ?, updated_at = NOW()
             WHERE tenant_id = ? AND name = ?'
        );
        foreach ($inventoryBoost as $row) {
            $stInv->execute([$row[1], $row[2], $tenantId, $row[0]]);
        }

        $stPurchase = $pdo->prepare(
            'INSERT INTO laundry_inventory_purchases (tenant_id, item_id, quantity, unit_cost, note, purchased_at)
             SELECT ?, id, ?, ?, ?, NOW()
             FROM laundry_inventory_items
             WHERE tenant_id = ? AND name = ?
             LIMIT 1'
        );
        $stPurchase->execute([$tenantId, 40, 12, 'Initial detergent stock', $tenantId, 'Detergent']);
        $stPurchase->execute([$tenantId, 30, 10, 'Initial fabcon stock', $tenantId, 'Fabric Conditioner']);

        $stOrder = $pdo->prepare(
            'INSERT INTO laundry_orders
             (tenant_id, customer_id, order_type, machine_type, wash_qty, dry_minutes, subtotal, add_on_total, total_amount, payment_method, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed", ?, ?)'
        );
        $stAddon = $pdo->prepare(
            'INSERT INTO laundry_order_add_ons (tenant_id, order_id, item_name, quantity, unit_price, total_price)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        for ($i = 0; $i < 20; $i++) {
            $date = date('Y-m-d H:i:s', strtotime('-'.random_int(0, 14).' days +'.random_int(8, 18).' hours'));
            $orderTypeOptions = ['drop_off', 'wash_only', 'dry_only'];
            $machineType = random_int(0, 100) <= 80 ? 'c5' : 'maytag';
            $orderType = $orderTypeOptions[array_rand($orderTypeOptions)];
            $washQty = random_int(1, 3);
            $dryMinutes = [30, 40, 50, 55, 60][array_rand([30, 40, 50, 55, 60])];

            $subtotal = 80;
            if ($orderType === 'wash_only') {
                $subtotal = 60 * $washQty;
            } elseif ($orderType === 'dry_only') {
                $subtotal = $dryMinutes <= 30 ? 40 : ($dryMinutes <= 40 ? 50 : 60 + max(0, (int) ceil(($dryMinutes - 50) / 5)) * 10);
            } else {
                $subtotal = 80 * $washQty;
            }

            $extraDet = random_int(0, 2);
            $extraFab = random_int(0, 2);
            $extraBleach = random_int(0, 1);
            $addOnTotal = ($extraDet + $extraFab + $extraBleach) * 10;
            $total = $subtotal + $addOnTotal;
            $payment = ['cash', 'gcash', 'card'][array_rand(['cash', 'gcash', 'card'])];
            $customerId = $customerIds[array_rand($customerIds)] ?? null;

            $stOrder->execute([
                $tenantId,
                $customerId,
                $orderType,
                $machineType,
                $washQty,
                $dryMinutes,
                $subtotal,
                $addOnTotal,
                $total,
                $payment,
                $date,
                $date,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            if ($extraDet > 0) {
                $stAddon->execute([$tenantId, $orderId, 'Extra detergent', $extraDet, 10, $extraDet * 10]);
            }
            if ($extraFab > 0) {
                $stAddon->execute([$tenantId, $orderId, 'Extra fabcon', $extraFab, 10, $extraFab * 10]);
            }
            if ($extraBleach > 0) {
                $stAddon->execute([$tenantId, $orderId, 'Bleach', $extraBleach, 10, $extraBleach * 10]);
            }
        }

        $attendanceRows = [
            ['Aira', 1, 8, 0, 'Normal shift'],
            ['Ben', 1, 6, 50, 'Advance deduction'],
            ['Cara', 0.5, 4, 0, 'Half day'],
        ];
        $stAttendance = $pdo->prepare(
            'INSERT INTO laundry_attendance
             (tenant_id, staff_name, attendance_date, days_worked, loads_folded, day_rate, folding_fee_per_load, deductions, notes, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 350, 10, ?, ?, NOW(), NOW())'
        );
        foreach ($attendanceRows as $idx => $row) {
            $date = date('Y-m-d', strtotime('-'.$idx.' days'));
            $stAttendance->execute([$tenantId, $row[0], $date, $row[1], $row[2], $row[3], $row[4]]);
        }

        $pdo->prepare(
            'INSERT INTO laundry_load_cards (tenant_id, machine_type, balance, created_at, updated_at)
             VALUES (?, "c5", 940, NOW(), NOW())
             ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = NOW()'
        )->execute([$tenantId]);

        $pdo->commit();
        fwrite(STDOUT, "Tenant {$tenantId}: seeded demo laundry data.\n");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "Tenant {$tenantId}: seed failed - {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, "Done.\n");
