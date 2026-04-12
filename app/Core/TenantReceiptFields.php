<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/** Ensures `tenants` columns used for printed/POS receipts exist. */
final class TenantReceiptFields
{
    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $cols = [
            'receipt_display_name' => 'VARCHAR(255) NULL DEFAULT NULL',
            'receipt_business_style' => 'VARCHAR(255) NULL DEFAULT NULL',
            'receipt_tax_id' => 'VARCHAR(100) NULL DEFAULT NULL',
            'receipt_phone' => 'VARCHAR(255) NULL DEFAULT NULL',
            'receipt_address' => 'TEXT NULL',
            'receipt_email' => 'VARCHAR(255) NULL DEFAULT NULL',
            'receipt_footer_note' => 'TEXT NULL',
            'receipt_lan_print_copies' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1',
        ];
        foreach ($cols as $name => $def) {
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM tenants LIKE ". $pdo->quote($name));
                if ($chk !== false && $chk->fetch()) {
                    continue;
                }
                $pdo->exec("ALTER TABLE tenants ADD COLUMN `{$name}` {$def}");
            } catch (\Throwable) {
                // ignore
            }
        }
    }
}
