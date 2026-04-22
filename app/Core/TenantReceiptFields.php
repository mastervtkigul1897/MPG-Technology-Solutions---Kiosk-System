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
            // Comma-separated rules, e.g. "RP|name, RPP|name, 58|name"
            'receipt_ble_printer_match_rules' => 'TEXT NULL',
            'receipt_lan_print_copies' => 'TINYINT UNSIGNED NOT NULL DEFAULT 1',
            'receipt_escpos_line_width' => 'TINYINT UNSIGNED NOT NULL DEFAULT 32',
            'receipt_escpos_right_col_width' => 'TINYINT UNSIGNED NOT NULL DEFAULT 10',
            'receipt_escpos_extra_feeds' => 'TINYINT UNSIGNED NOT NULL DEFAULT 8',
            'receipt_escpos_cut_mode' => "VARCHAR(16) NOT NULL DEFAULT 'none'",
            'receipt_serial_number' => 'VARCHAR(100) NULL DEFAULT NULL',
            'receipt_vat_applicable' => 'TINYINT(1) NOT NULL DEFAULT 1',
            'receipt_dti_number' => 'VARCHAR(100) NULL DEFAULT NULL',
            'receipt_tax_type' => "VARCHAR(16) NOT NULL DEFAULT 'non_vat'",
            'receipt_is_bir_registered' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'receipt_bir_accreditation_no' => 'VARCHAR(120) NULL DEFAULT NULL',
            'receipt_min' => 'VARCHAR(120) NULL DEFAULT NULL',
            'receipt_permit_no' => 'VARCHAR(120) NULL DEFAULT NULL',
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
