<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class FlavorSchema
{
    public static function ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        self::ensureIngredientsCategory($pdo);
        self::ensureProductsFlavorFlag($pdo);
        self::ensureProductFlavorTable($pdo);
        self::ensureTransactionItemFlavorColumns($pdo);
    }

    private static function ensureIngredientsCategory(PDO $pdo): void
    {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM ingredients LIKE 'category'");
            if ($chk !== false && $chk->fetch()) {
                return;
            }
            $pdo->exec("ALTER TABLE ingredients ADD COLUMN `category` VARCHAR(30) NOT NULL DEFAULT 'general' AFTER `unit`");
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function ensureProductsFlavorFlag(PDO $pdo): void
    {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM products LIKE 'has_flavor_options'");
            if ($chk !== false && $chk->fetch()) {
                return;
            }
            $pdo->exec('ALTER TABLE products ADD COLUMN `has_flavor_options` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`');
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function ensureProductFlavorTable(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS `product_flavor_ingredients` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tenant_id` BIGINT UNSIGNED NOT NULL,
                    `product_id` BIGINT UNSIGNED NOT NULL,
                    `ingredient_id` BIGINT UNSIGNED NOT NULL,
                    `quantity_required` DECIMAL(38,16) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NULL DEFAULT NULL,
                    `updated_at` DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `pfi_tenant_product_ingredient_unique` (`tenant_id`, `product_id`, `ingredient_id`),
                    KEY `pfi_tenant_product_idx` (`tenant_id`, `product_id`),
                    KEY `pfi_tenant_ingredient_idx` (`tenant_id`, `ingredient_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable) {
            // ignore
        }
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM product_flavor_ingredients LIKE 'quantity_required'");
            if (! ($chk !== false && $chk->fetch())) {
                $pdo->exec("ALTER TABLE product_flavor_ingredients ADD COLUMN `quantity_required` DECIMAL(38,16) NOT NULL DEFAULT 1 AFTER `ingredient_id`");
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    private static function ensureTransactionItemFlavorColumns(PDO $pdo): void
    {
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM transaction_items LIKE 'flavor_ingredient_id'");
            if (! ($chk !== false && $chk->fetch())) {
                $pdo->exec('ALTER TABLE transaction_items ADD COLUMN `flavor_ingredient_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `product_id`');
            }
        } catch (\Throwable) {
            // ignore
        }
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM transaction_items LIKE 'flavor_name'");
            if (! ($chk !== false && $chk->fetch())) {
                $pdo->exec('ALTER TABLE transaction_items ADD COLUMN `flavor_name` VARCHAR(255) NULL DEFAULT NULL AFTER `flavor_ingredient_id`');
            }
        } catch (\Throwable) {
            // ignore
        }
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM transaction_items LIKE 'flavor_quantity_required'");
            if (! ($chk !== false && $chk->fetch())) {
                $pdo->exec("ALTER TABLE transaction_items ADD COLUMN `flavor_quantity_required` DECIMAL(38,16) NULL DEFAULT NULL AFTER `flavor_name`");
            }
        } catch (\Throwable) {
            // ignore
        }
    }
}
