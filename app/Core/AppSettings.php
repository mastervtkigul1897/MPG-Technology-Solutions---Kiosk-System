<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Key/value app settings stored in `app_settings` table. Merged into App::config after boot.
 */
final class AppSettings
{
    private static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `app_settings` (
  `key` VARCHAR(64) NOT NULL,
  `value` LONGTEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ins = $pdo->prepare(
            'INSERT INTO `app_settings` (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `key` = `key`'
        );
        foreach ([
            ['app_name', ''],
            ['maintenance_mode', '0'],
            ['maintenance_message', ''],
            ['subscription_warning_days', '7'],
            ['backup_retention_days', '30'],
        ] as $row) {
            $ins->execute($row);
        }
    }

    /** Apply DB settings onto App config (safe if table missing). */
    public static function apply(): void
    {
        try {
            $pdo = App::db();
            self::ensureSchema($pdo);
            $st = $pdo->query('SELECT `key`, `value` FROM app_settings');
            if ($st === false) {
                self::defaultsOnly();

                return;
            }
            $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($rows === []) {
                self::defaultsOnly();

                return;
            }

            $name = trim((string) ($rows['app_name'] ?? ''));
            $patch = [
                'maintenance_mode' => filter_var($rows['maintenance_mode'] ?? '0', FILTER_VALIDATE_BOOL),
                'maintenance_message' => (string) ($rows['maintenance_message'] ?? ''),
                'subscription_warning_days' => max(1, min(90, (int) ($rows['subscription_warning_days'] ?? 7))),
                'backup_retention_days' => max(1, min(365, (int) ($rows['backup_retention_days'] ?? 30))),
            ];
            if ($name !== '') {
                $patch['name'] = $name;
            }
            App::mergeConfig($patch);
        } catch (\Throwable) {
            self::defaultsOnly();
        }
    }

    private static function defaultsOnly(): void
    {
        App::mergeConfig([
            'maintenance_mode' => false,
            'maintenance_message' => '',
            'subscription_warning_days' => 7,
            'backup_retention_days' => 30,
        ]);
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        $defaults = [
            'app_name' => '',
            'maintenance_mode' => '0',
            'maintenance_message' => '',
            'subscription_warning_days' => '7',
            'backup_retention_days' => '30',
        ];
        try {
            $pdo = App::db();
            self::ensureSchema($pdo);
            $st = $pdo->query('SELECT `key`, `value` FROM app_settings');
            if ($st === false) {
                return $defaults;
            }
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $defaults[(string) $k] = (string) $v;
            }
        } catch (\Throwable) {
            // keep defaults
        }

        return $defaults;
    }

    /** @param array<string, string|int|bool> $input */
    public static function save(array $input): void
    {
        $pdo = App::db();
        self::ensureSchema($pdo);
        $appName = trim((string) ($input['app_name'] ?? ''));
        $maint = ! empty($input['maintenance_mode']);
        $msg = trim((string) ($input['maintenance_message'] ?? ''));
        $warnDays = max(1, min(90, (int) ($input['subscription_warning_days'] ?? 7)));
        $backupRetentionDays = max(1, min(365, (int) ($input['backup_retention_days'] ?? 30)));

        $st = $pdo->prepare(
            'INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $st->execute(['app_name', $appName]);
        $st->execute(['maintenance_mode', $maint ? '1' : '0']);
        $st->execute(['maintenance_message', $msg]);
        $st->execute(['subscription_warning_days', (string) $warnDays]);
        $st->execute(['backup_retention_days', (string) $backupRetentionDays]);
    }
}
