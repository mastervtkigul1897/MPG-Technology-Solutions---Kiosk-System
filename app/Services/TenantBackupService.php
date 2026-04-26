<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use PDO;
use RuntimeException;

final class TenantBackupService
{
    /** @var list<string> */
    private const BACKUP_TABLES = [
        'tenants',
        'users',
        'categories',
        'ingredients',
        'products',
        'product_ingredients',
        'expenses',
        'transactions',
        'transaction_items',
        'inventory_movements',
        'activity_logs',
        'damaged_items',
    ];

    /** Child-to-parent delete order; do not delete tenant_backups tables here. */
    private const DELETE_ORDER = [
        'transaction_items',
        'transactions',
        'product_ingredients',
        'inventory_movements',
        'damaged_items',
        'expenses',
        'products',
        'ingredients',
        'categories',
        'activity_logs',
        'users',
    ];

    private const STORAGE_DIR = 'storage/backups/tenants';
    public const RETENTION_DAYS = 7;
    /** @var list<int> */
    public const DAILY_SCHEDULE_HOURS = [17, 21];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? App::db();
        $this->ensureSchema();
    }

    /** @return array<string,mixed> */
    public function createTenantSnapshot(int $tenantId, string $backupType = 'manual', ?int $createdByUserId = null): array
    {
        $tenant = $this->getTenant($tenantId);
        if ($tenant === null) {
            throw new RuntimeException('Store not found.');
        }

        $type = trim($backupType) !== '' ? trim($backupType) : 'manual';
        $key = $this->buildBackupKey($tenantId, $type);
        $relativePath = self::STORAGE_DIR.'/'.$tenantId.'/'.$key.'.sql.gz';
        $absolutePath = $this->absolutePath($relativePath);

        $backupId = $this->createPendingBackupRow($tenantId, $type, $key, $relativePath, $createdByUserId);
        try {
            $this->ensureDirectory(dirname($absolutePath));
            [$sql, $stats] = $this->buildTenantSqlSnapshot($tenantId);
            $gz = gzencode($sql, 9);
            if ($gz === false) {
                throw new RuntimeException('Could not compress backup payload.');
            }
            if (file_put_contents($absolutePath, $gz) === false) {
                throw new RuntimeException('Could not write backup file.');
            }

            $this->pdo->prepare(
                'UPDATE tenant_backups
                 SET file_size = ?, checksum_sha256 = ?, table_count = ?, row_count = ?, status = ?, error_message = NULL, updated_at = NOW()
                 WHERE id = ?'
            )->execute([
                filesize($absolutePath) ?: strlen($gz),
                hash('sha256', $gz),
                (int) count($stats),
                (int) array_sum(array_values($stats)),
                'ready',
                $backupId,
            ]);

            $this->persistBackupItems($backupId, $stats);
            $this->pruneOldBackups($tenantId);

            return [
                'id' => $backupId,
                'tenant_id' => $tenantId,
                'backup_key' => $key,
                'storage_path' => $relativePath,
            ];
        } catch (\Throwable $e) {
            $this->pdo->prepare(
                'UPDATE tenant_backups SET status = ?, error_message = ?, updated_at = NOW() WHERE id = ?'
            )->execute(['failed', mb_substr($e->getMessage(), 0, 2000), $backupId]);
            throw new RuntimeException('Backup failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @return list<array<string,mixed>> */
    public function listBackups(int $tenantId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $st = $this->pdo->prepare(
            'SELECT b.*, u.email AS created_by_email
             FROM tenant_backups b
             LEFT JOIN users u ON u.id = b.created_by_user_id
             WHERE b.tenant_id = ?
             ORDER BY b.id DESC
             LIMIT '.$limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function restoreTenantFromBackup(int $tenantId, int $backupId, ?int $actorUserId = null, bool $withPreRestoreBackup = true): void
    {
        $backup = $this->getReadyBackup($tenantId, $backupId);
        if ($backup === null) {
            throw new RuntimeException('Backup not found or not ready.');
        }

        if ($withPreRestoreBackup) {
            $this->createTenantSnapshot($tenantId, 'before_restore', $actorUserId);
        }

        $absolutePath = $this->absolutePath((string) $backup['storage_path']);
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Backup file is missing.');
        }
        $compressed = file_get_contents($absolutePath);
        if (! is_string($compressed) || $compressed === '') {
            throw new RuntimeException('Backup file is unreadable.');
        }
        $expectedChecksum = strtolower(trim((string) ($backup['checksum_sha256'] ?? '')));
        if ($expectedChecksum !== '' && hash('sha256', $compressed) !== $expectedChecksum) {
            throw new RuntimeException('Backup integrity check failed (checksum mismatch).');
        }
        $decoded = gzdecode($compressed);
        if (! is_string($decoded) || $decoded === '') {
            throw new RuntimeException('Backup file is invalid.');
        }

        $statements = $this->splitSqlStatements($decoded);
        if ($statements === []) {
            throw new RuntimeException('Backup file contains no restore statements.');
        }

        $this->pdo->beginTransaction();
        try {
            foreach ($statements as $sql) {
                $trim = trim($sql);
                if ($trim === '') {
                    continue;
                }
                if (! $this->isSafeRestoreStatement($trim, $tenantId)) {
                    throw new RuntimeException('Backup file contains unsafe restore statement.');
                }
                $this->pdo->exec($trim);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Restore failed: '.$e->getMessage(), 0, $e);
        }
    }

    /** @return array<string,mixed>|null */
    public function getTenant(int $tenantId): ?array
    {
        if (! $this->tableExists('tenants')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function pruneOldBackups(int $tenantId, ?int $keepDays = null): int
    {
        $days = $keepDays ?? self::RETENTION_DAYS;
        $days = max(1, min(365, $days));

        $st = $this->pdo->prepare(
            'SELECT id, storage_path
             FROM tenant_backups
             WHERE tenant_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY id ASC'
        );
        $st->execute([$tenantId, $days]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return 0;
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
            $path = $this->absolutePath((string) ($row['storage_path'] ?? ''));
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        if ($ids === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $del = $this->pdo->prepare('DELETE FROM tenant_backups WHERE id IN ('.$in.')');
        $del->execute($ids);

        return count($ids);
    }

    /**
     * Runs daily snapshots for all active stores.
     *
     * @return list<array{tenant_id:int,slug:string,status:string,backup_id:int,pruned:int,error:string}>
     */
    public function runDailyBackupsForAll(?int $createdByUserId = null): array
    {
        return $this->runScheduledBackupsForAll($createdByUserId)['results'];
    }

    /**
     * Force-create backups for all active stores regardless of schedule.
     *
     * @return list<array{tenant_id:int,slug:string,status:string,backup_id:int,pruned:int,error:string}>
     */
    public function runForcedBackupsForAll(?int $createdByUserId = null): array
    {
        if (! $this->tableExists('tenants')) {
            return [];
        }
        $st = $this->pdo->query('SELECT id, slug FROM tenants WHERE is_active = 1 ORDER BY id ASC');
        $tenants = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $results = [];
        foreach ($tenants as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            $slug = (string) ($tenant['slug'] ?? '');
            if ($tenantId < 1) {
                continue;
            }
            try {
                $snapshot = $this->createTenantSnapshot($tenantId, 'manual_forced', $createdByUserId);
                $results[] = [
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'status' => 'created',
                    'backup_id' => (int) ($snapshot['id'] ?? 0),
                    'pruned' => 0,
                    'error' => '',
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'status' => 'failed',
                    'backup_id' => 0,
                    'pruned' => 0,
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ];
            }
        }

        return $results;
    }

    /**
     * Runs backups only when current hour matches configured schedule.
     *
     * @return array{triggered:bool,slot:string,results:list<array{tenant_id:int,slug:string,status:string,backup_id:int,pruned:int,error:string}>}
     */
    public function runScheduledBackupsForAll(?int $createdByUserId = null): array
    {
        $slot = $this->currentScheduleSlot();
        if ($slot === '') {
            return [
                'triggered' => false,
                'slot' => '',
                'results' => [],
            ];
        }

        if (! $this->tableExists('tenants')) {
            return [
                'triggered' => true,
                'slot' => $slot,
                'results' => [],
            ];
        }
        $st = $this->pdo->query('SELECT id, slug FROM tenants WHERE is_active = 1 ORDER BY id ASC');
        $tenants = $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $results = [];
        foreach ($tenants as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            $slug = (string) ($tenant['slug'] ?? '');
            if ($tenantId < 1) {
                continue;
            }
            try {
                if ($this->hasScheduledBackupToday($tenantId, $slot)) {
                    $pruned = $this->pruneOldBackups($tenantId, self::RETENTION_DAYS);
                    $results[] = [
                        'tenant_id' => $tenantId,
                        'slug' => $slug,
                        'status' => 'skipped',
                        'backup_id' => 0,
                        'pruned' => $pruned,
                        'error' => '',
                    ];
                    continue;
                }
                $snapshot = $this->createTenantSnapshot($tenantId, $slot, $createdByUserId);
                $results[] = [
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'status' => 'created',
                    'backup_id' => (int) ($snapshot['id'] ?? 0),
                    'pruned' => 0,
                    'error' => '',
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'status' => 'failed',
                    'backup_id' => 0,
                    'pruned' => 0,
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ];
            }
        }

        return [
            'triggered' => true,
            'slot' => $slot,
            'results' => $results,
        ];
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `tenant_backups` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `tenant_id` BIGINT UNSIGNED NOT NULL,
              `backup_type` VARCHAR(32) NOT NULL DEFAULT \'manual\',
              `backup_key` VARCHAR(191) NOT NULL,
              `storage_path` VARCHAR(255) NOT NULL,
              `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
              `checksum_sha256` CHAR(64) NOT NULL DEFAULT \'\',
              `table_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `row_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
              `status` VARCHAR(20) NOT NULL DEFAULT \'ready\',
              `created_by_user_id` BIGINT UNSIGNED NULL,
              `error_message` TEXT NULL,
              `created_at` TIMESTAMP NULL DEFAULT NULL,
              `updated_at` TIMESTAMP NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `tenant_backups_backup_key_unique` (`backup_key`),
              KEY `tenant_backups_tenant_id_created_at_index` (`tenant_id`, `created_at`),
              KEY `tenant_backups_status_index` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `tenant_backup_items` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `backup_id` BIGINT UNSIGNED NOT NULL,
              `table_name` VARCHAR(64) NOT NULL,
              `row_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NULL DEFAULT NULL,
              `updated_at` TIMESTAMP NULL DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `tenant_backup_items_backup_table_unique` (`backup_id`, `table_name`),
              KEY `tenant_backup_items_table_name_index` (`table_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->ensureForeignKey(
            'tenant_backups',
            'tenant_backups_tenant_id_fk',
            'ALTER TABLE tenant_backups ADD CONSTRAINT tenant_backups_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE'
        );
        $this->ensureForeignKey(
            'tenant_backups',
            'tenant_backups_created_by_user_id_fk',
            'ALTER TABLE tenant_backups ADD CONSTRAINT tenant_backups_created_by_user_id_fk FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL'
        );
        $this->ensureForeignKey(
            'tenant_backup_items',
            'tenant_backup_items_backup_id_fk',
            'ALTER TABLE tenant_backup_items ADD CONSTRAINT tenant_backup_items_backup_id_fk FOREIGN KEY (backup_id) REFERENCES tenant_backups(id) ON DELETE CASCADE'
        );
    }

    private function ensureForeignKey(string $tableName, string $constraintName, string $alterSql): void
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT CONSTRAINT_NAME
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND CONSTRAINT_NAME = ?
                   AND CONSTRAINT_TYPE = \'FOREIGN KEY\'
                 LIMIT 1'
            );
            $st->execute([$tableName, $constraintName]);
            if ($st->fetch(PDO::FETCH_ASSOC)) {
                return;
            }
            $this->pdo->exec($alterSql);
        } catch (\Throwable) {
            // Ignore if no ALTER privilege or old MySQL limitations.
        }
    }

    private function createPendingBackupRow(
        int $tenantId,
        string $backupType,
        string $backupKey,
        string $storagePath,
        ?int $createdByUserId
    ): int {
        $this->pdo->prepare(
            'INSERT INTO tenant_backups
             (tenant_id, backup_type, backup_key, storage_path, status, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([
            $tenantId,
            $backupType,
            $backupKey,
            $storagePath,
            'running',
            $createdByUserId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{0:string,1:array<string,int>} */
    private function buildTenantSqlSnapshot(int $tenantId): array
    {
        $existingTables = [];
        foreach (self::BACKUP_TABLES as $table) {
            if ($this->tableExists($table)) {
                $existingTables[] = $table;
            }
        }
        if ($existingTables === []) {
            throw new RuntimeException('No backupable tables found.');
        }

        $sql = [];
        $sql[] = '-- Tenant backup snapshot';
        $sql[] = '-- tenant_id: '.$tenantId;
        $sql[] = '-- created_at: '.date('Y-m-d H:i:s');
        $sql[] = "SET time_zone = '+08:00';";
        $sql[] = 'SET FOREIGN_KEY_CHECKS = 0;';

        foreach (self::DELETE_ORDER as $table) {
            if (! in_array($table, $existingTables, true)) {
                continue;
            }
            $sql[] = 'DELETE FROM `'.$table.'` WHERE `tenant_id` = '.(int) $tenantId.';';
        }

        $stats = [];
        foreach ($existingTables as $table) {
            $rows = $this->fetchTenantRows($table, $tenantId);
            $stats[$table] = count($rows);
            if ($rows === []) {
                continue;
            }

            if ($table === 'tenants') {
                $sql[] = $this->buildTenantUpsert($rows[0]);
                continue;
            }

            foreach ($rows as $row) {
                $sql[] = $this->buildInsertStatement($table, $row);
            }
        }

        $sql[] = 'SET FOREIGN_KEY_CHECKS = 1;';
        $sql[] = '';

        return [implode("\n", $sql), $stats];
    }

    /** @return list<array<string,mixed>> */
    private function fetchTenantRows(string $table, int $tenantId): array
    {
        if ($table === 'tenants') {
            $st = $this->pdo->prepare('SELECT * FROM `tenants` WHERE `id` = ? LIMIT 1');
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row ? [$row] : [];
        }

        $st = $this->pdo->prepare('SELECT * FROM `'.$table.'` WHERE `tenant_id` = ? ORDER BY `id` ASC');
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $row */
    private function buildInsertStatement(string $table, array $row): string
    {
        $columns = array_keys($row);
        $colSql = implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns));
        $valSql = implode(', ', array_map(fn ($v): string => $this->toSqlLiteral($v), array_values($row)));

        return 'INSERT INTO `'.$table.'` ('.$colSql.') VALUES ('.$valSql.');';
    }

    /** @param array<string,mixed> $row */
    private function buildTenantUpsert(array $row): string
    {
        $columns = array_keys($row);
        $colSql = implode(', ', array_map(static fn (string $c): string => '`'.$c.'`', $columns));
        $valSql = implode(', ', array_map(fn ($v): string => $this->toSqlLiteral($v), array_values($row)));
        $updates = [];
        foreach ($columns as $column) {
            if ($column === 'id') {
                continue;
            }
            $updates[] = '`'.$column.'` = VALUES(`'.$column.'`)';
        }

        return 'INSERT INTO `tenants` ('.$colSql.') VALUES ('.$valSql.') ON DUPLICATE KEY UPDATE '.implode(', ', $updates).';';
    }

    private function toSqlLiteral(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->pdo->quote((string) $value);
    }

    /** @param array<string,int> $stats */
    private function persistBackupItems(int $backupId, array $stats): void
    {
        $this->pdo->prepare('DELETE FROM tenant_backup_items WHERE backup_id = ?')->execute([$backupId]);
        if ($stats === []) {
            return;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_backup_items (backup_id, table_name, row_count, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        foreach ($stats as $table => $rows) {
            $st->execute([$backupId, $table, max(0, (int) $rows)]);
        }
    }

    private function buildBackupKey(int $tenantId, string $backupType): string
    {
        $suffix = bin2hex(random_bytes(4));
        $type = preg_replace('/[^a-z0-9\-_]+/i', '-', strtolower($backupType)) ?: 'manual';

        return 'tenant-'.$tenantId.'-'.$type.'-'.date('Ymd-His').'-'.$suffix;
    }

    private function absolutePath(string $relativePath): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__, 2);
        $clean = ltrim(str_replace(['..\\', '../'], '', $relativePath), '/');

        return rtrim($base, '/').'/'.$clean;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }
        if (! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create backup directory.');
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 LIMIT 1'
            );
            $st->execute([$table]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasScheduledBackupToday(int $tenantId, string $slot): bool
    {
        $st = $this->pdo->prepare(
            'SELECT id
             FROM tenant_backups
             WHERE tenant_id = ? AND backup_type = ? AND status = ? AND DATE(created_at) = CURDATE()
             LIMIT 1'
        );
        $st->execute([$tenantId, $slot, 'ready']);

        return $st->fetch(PDO::FETCH_ASSOC) !== false;
    }

    private function currentScheduleSlot(): string
    {
        $hour = (int) date('G');
        foreach (self::DAILY_SCHEDULE_HOURS as $h) {
            if ($hour === $h) {
                return 'daily_'.str_pad((string) $h, 2, '0', STR_PAD_LEFT).'00';
            }
        }

        return '';
    }

    /** @return array<string,mixed>|null */
    private function getReadyBackup(int $tenantId, int $backupId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_backups WHERE id = ? AND tenant_id = ? AND status = ? LIMIT 1'
        );
        $st->execute([$backupId, $tenantId, 'ready']);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return list<string> */
    private function splitSqlStatements(string $sql): array
    {
        $out = [];
        $buf = '';
        $inSingle = false;
        $inDouble = false;
        $escaped = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $buf .= $ch;

            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($ch === '\\') {
                $escaped = true;
                continue;
            }
            if (! $inDouble && $ch === "'") {
                $inSingle = ! $inSingle;
                continue;
            }
            if (! $inSingle && $ch === '"') {
                $inDouble = ! $inDouble;
                continue;
            }
            if (! $inSingle && ! $inDouble && $ch === ';') {
                $trimmed = trim($buf);
                if ($trimmed !== '' && ! str_starts_with($trimmed, '--')) {
                    $out[] = $trimmed;
                }
                $buf = '';
            }
        }

        $tail = trim($buf);
        if ($tail !== '' && ! str_starts_with($tail, '--')) {
            $out[] = $tail;
        }

        return $out;
    }

    private function isSafeRestoreStatement(string $sql, int $tenantId): bool
    {
        $stmt = trim($sql);
        if ($stmt === '') {
            return true;
        }
        $upper = strtoupper($stmt);
        foreach ([' DROP ', ' TRUNCATE ', ' ALTER ', ' CREATE ', ' GRANT ', ' REVOKE ', ' INTO OUTFILE ', ' LOAD DATA ', ' USE '] as $needle) {
            if (str_contains(' '.$upper.' ', $needle)) {
                return false;
            }
        }
        if (preg_match('/^SET\s+TIME_ZONE\s*=.+;?$/i', $stmt) === 1) {
            return true;
        }
        if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]\s*;?$/i', $stmt) === 1) {
            return true;
        }
        if (preg_match('/^DELETE\s+FROM\s+`([a-z0-9_]+)`\s+WHERE\s+`tenant_id`\s*=\s*(\d+)\s*;?$/i', $stmt, $m) === 1) {
            return in_array((string) $m[1], self::DELETE_ORDER, true) && (int) ($m[2] ?? 0) === $tenantId;
        }
        if (preg_match('/^INSERT\s+INTO\s+`([a-z0-9_]+)`\s+/i', $stmt, $m) === 1) {
            $table = (string) ($m[1] ?? '');
            if ($table === 'tenants') {
                return str_contains($upper, 'ON DUPLICATE KEY UPDATE');
            }
            return in_array($table, self::BACKUP_TABLES, true);
        }

        return false;
    }
}
