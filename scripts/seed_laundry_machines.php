<?php

declare(strict_types=1);

/**
 * Idempotent test data: washers and dryers per tenant (unique machine_label per tenant).
 * Run: php scripts/seed_laundry_machines.php
 */

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\App;
use App\Core\LaundrySchema;

$pdo = App::db();
LaundrySchema::ensure($pdo);

/** @var list<array{0: string, 1: string}> machine_kind, machine_label */
$machines = [
    ['washer', 'Washer #1'],
    ['washer', 'Washer #2'],
    ['washer', 'Washer #3'],
    ['dryer', 'Dryer #1'],
    ['dryer', 'Dryer #2'],
    ['dryer', 'Dryer #3'],
];

$st = $pdo->prepare(
    'INSERT INTO laundry_machines (tenant_id, machine_kind, machine_label, status, current_order_id, created_at, updated_at)
     VALUES (?, ?, ?, "available", NULL, NOW(), NOW())
     ON DUPLICATE KEY UPDATE machine_kind = VALUES(machine_kind), updated_at = NOW()'
);

$tenantIds = $pdo->query('SELECT id FROM tenants ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
if ($tenantIds === []) {
    fwrite(STDOUT, "No tenants found.\n");
    exit(0);
}

foreach ($tenantIds as $tenantIdRaw) {
    $tenantId = (int) $tenantIdRaw;
    foreach ($machines as $m) {
        $st->execute([$tenantId, $m[0], $m[1]]);
    }
    fwrite(STDOUT, "Tenant {$tenantId}: seeded washers + dryers by label.\n");
}

fwrite(STDOUT, "Done.\n");
