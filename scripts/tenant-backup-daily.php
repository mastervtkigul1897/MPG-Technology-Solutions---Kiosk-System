<?php

declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Core\App;
use App\Services\TenantBackupService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only.\n";
    exit(1);
}

$pdo = App::db();
$service = new TenantBackupService($pdo);
$retentionDays = \App\Services\TenantBackupService::RETENTION_DAYS;

$st = $pdo->query('SELECT id, name, slug FROM tenants WHERE is_active = 1 ORDER BY id ASC');
$tenants = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

if ($tenants === []) {
    echo "[daily-backup] No active stores found.\n";
    exit(0);
}

foreach ($tenants as $tenant) {
    $tenantId = (int) ($tenant['id'] ?? 0);
    if ($tenantId < 1) {
        continue;
    }
    $name = (string) ($tenant['name'] ?? '');
    $slug = (string) ($tenant['slug'] ?? '');

    try {
        $snapshot = $service->createTenantSnapshot($tenantId, 'daily', null);
        $deleted = $service->pruneOldBackups($tenantId, $retentionDays);
        echo sprintf(
            "[daily-backup] OK tenant=%d slug=%s backup_id=%d pruned=%d\n",
            $tenantId,
            $slug,
            (int) ($snapshot['id'] ?? 0),
            $deleted
        );
    } catch (Throwable $e) {
        echo sprintf(
            "[daily-backup] FAILED tenant=%d name=%s error=%s\n",
            $tenantId,
            $name,
            $e->getMessage()
        );
    }
}

echo "[daily-backup] Done.\n";
