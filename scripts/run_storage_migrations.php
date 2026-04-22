<?php

declare(strict_types=1);

/**
 * Local DB: run all SQL files in storage/migrations/ in sorted order.
 *
 * Usage (from project root):
 *   php scripts/run_storage_migrations.php
 *
 * Uses DB_* from .env via config/app.php. Safe to run repeatedly: common
 * "already applied" errors are reported as SKIP and do not stop the run.
 */
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only. Run: php scripts/run_storage_migrations.php\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

/** @var array $config */
$config = require BASE_PATH . '/config/app.php';
$db = $config['db'];

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['database'],
    $db['charset']
);

try {
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
    $tz = (string) ($db['timezone'] ?? '+08:00');
    $pdo->prepare('SET time_zone = ?')->execute([$tz]);
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: '.$e->getMessage()."\n");
    exit(1);
}

$dir = BASE_PATH.'/storage/migrations';
$files = glob($dir.'/*.sql') ?: [];
sort($files);

$isBenign = static function (PDOException $e): bool {
    $m = strtolower($e->getMessage());

    return str_contains($m, 'duplicate column')
        || str_contains($m, 'duplicate key name')
        || str_contains($m, 'already exists');
};

$nOk = 0;
$nSkip = 0;

foreach ($files as $file) {
    $rel = basename($file);
    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        continue;
    }
    try {
        $pdo->exec($sql);
        echo "OK    {$rel}\n";
        ++$nOk;
    } catch (PDOException $e) {
        if ($isBenign($e)) {
            echo "SKIP  {$rel}\n";
            ++$nSkip;
            continue;
        }
        fwrite(STDERR, "FAIL  {$rel}\n".$e->getMessage()."\n");
        exit(1);
    }
}

echo "\nDone. Processed ".count($files)." file(s): {$nOk} applied, {$nSkip} skipped (already present).\n";
