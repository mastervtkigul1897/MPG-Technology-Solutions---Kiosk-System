-- Laundry System all-in-one DB sync (existing database)
-- Import this single file in phpMyAdmin.
-- Order preserved from individual migration files.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

-- =========================================================
-- 2026-04-16_add_receipt_ble_printer_match_rules.sql
-- =========================================================
-- Add configurable Bluetooth printer match rules per tenant.
-- Safe to run multiple times on MySQL/MariaDB (no IF NOT EXISTS dependency).

SET @mpg_db := DATABASE();
SET @mpg_col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @mpg_db
    AND TABLE_NAME = 'tenants'
    AND COLUMN_NAME = 'receipt_ble_printer_match_rules'
);

SET @mpg_sql := IF(
  @mpg_col_exists > 0,
  'SELECT "OK: column receipt_ble_printer_match_rules already exists" AS status',
  'ALTER TABLE `tenants` ADD COLUMN `receipt_ble_printer_match_rules` TEXT NULL AFTER `receipt_lan_print_copies`'
);

PREPARE mpg_stmt FROM @mpg_sql;
EXECUTE mpg_stmt;
DEALLOCATE PREPARE mpg_stmt;

-- =========================================================
-- 2026-04-20_add_laundry_phase1_tables.sql
-- =========================================================
CREATE TABLE IF NOT EXISTS `laundry_customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_customers_tenant_idx` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `stock_quantity` decimal(16,4) NOT NULL DEFAULT 0,
  `low_stock_threshold` decimal(16,4) NOT NULL DEFAULT 0,
  `unit_cost` decimal(16,4) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_inventory_unique` (`tenant_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `order_type` varchar(20) NOT NULL,
  `machine_type` varchar(20) DEFAULT NULL,
  `wash_qty` int NOT NULL DEFAULT 0,
  `dry_minutes` int NOT NULL DEFAULT 0,
  `subtotal` decimal(16,4) NOT NULL DEFAULT 0,
  `add_on_total` decimal(16,4) NOT NULL DEFAULT 0,
  `total_amount` decimal(16,4) NOT NULL DEFAULT 0,
  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_orders_tenant_idx` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `staff_name` varchar(120) NOT NULL,
  `attendance_date` date NOT NULL,
  `days_worked` decimal(6,2) NOT NULL DEFAULT 1,
  `loads_folded` int NOT NULL DEFAULT 0,
  `day_rate` decimal(16,4) NOT NULL DEFAULT 350,
  `folding_fee_per_load` decimal(16,4) NOT NULL DEFAULT 10,
  `deductions` decimal(16,4) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_attendance_tenant_idx` (`tenant_id`,`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2026-04-21_laundry_orders_washer_dryer_machine_ids.sql
-- =========================================================
SET @has_washer_machine_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'washer_machine_id'
);
SET @sql_washer_machine_id := IF(
  @has_washer_machine_id = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `washer_machine_id` BIGINT UNSIGNED DEFAULT NULL',
  'SELECT "SKIP washer_machine_id already exists" AS status'
);
PREPARE stmt_washer_machine_id FROM @sql_washer_machine_id;
EXECUTE stmt_washer_machine_id;
DEALLOCATE PREPARE stmt_washer_machine_id;

SET @has_dryer_machine_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'dryer_machine_id'
);
SET @sql_dryer_machine_id := IF(
  @has_dryer_machine_id = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `dryer_machine_id` BIGINT UNSIGNED DEFAULT NULL',
  'SELECT "SKIP dryer_machine_id already exists" AS status'
);
PREPARE stmt_dryer_machine_id FROM @sql_dryer_machine_id;
EXECUTE stmt_dryer_machine_id;
DEALLOCATE PREPARE stmt_dryer_machine_id;

-- =========================================================
-- 2026-04-22_rename_zonrox_to_bleach.sql
-- =========================================================
UPDATE laundry_inventory_items
SET category = "bleach"
WHERE LOWER(TRIM(category)) = "zonrox";

UPDATE laundry_order_add_ons
SET item_name = "Bleach"
WHERE LOWER(TRIM(item_name)) IN ("zonrox", "extra zonrox", "bleach / zonrox");

-- =========================================================
-- 2026-04-23_laundry_load_definitions.sql
-- =========================================================
CREATE TABLE IF NOT EXISTS `laundry_load_definitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(200) NOT NULL,
  `detergent_item_id` bigint unsigned DEFAULT NULL,
  `fabcon_item_id` bigint unsigned DEFAULT NULL,
  `bleach_item_id` bigint unsigned DEFAULT NULL,
  `detergent_included_qty` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `fabcon_included_qty` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `bleach_included_qty` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_load_definitions_tenant_idx` (`tenant_id`,`sort_order`,`name`),
  CONSTRAINT `laundry_load_definitions_detergent_item_fk` FOREIGN KEY (`detergent_item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laundry_load_definitions_fabcon_item_fk` FOREIGN KEY (`fabcon_item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `laundry_load_definitions_bleach_item_fk` FOREIGN KEY (`bleach_item_id`) REFERENCES `laundry_inventory_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `laundry_orders`
  MODIFY COLUMN `order_type` varchar(64) NOT NULL;

SET @has_load_definition_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'load_definition_id'
);
SET @sql_load_definition_id := IF(
  @has_load_definition_id = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `load_definition_id` BIGINT UNSIGNED DEFAULT NULL',
  'SELECT "SKIP load_definition_id already exists" AS status'
);
PREPARE stmt_load_definition_id FROM @sql_load_definition_id;
EXECUTE stmt_load_definition_id;
DEALLOCATE PREPARE stmt_load_definition_id;

SET @has_load_label_snapshot := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'load_label_snapshot'
);
SET @sql_load_label_snapshot := IF(
  @has_load_label_snapshot = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `load_label_snapshot` VARCHAR(200) DEFAULT NULL',
  'SELECT "SKIP load_label_snapshot already exists" AS status'
);
PREPARE stmt_load_label_snapshot FROM @sql_load_label_snapshot;
EXECUTE stmt_load_label_snapshot;
DEALLOCATE PREPARE stmt_load_label_snapshot;

-- =========================================================
-- 2026-04-24_laundry_orders_include_fold_service.sql
-- =========================================================
ALTER TABLE `laundry_orders`
  MODIFY COLUMN `order_type` varchar(64) NOT NULL;

SET @has_include_fold_service := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'include_fold_service'
);
SET @sql_include_fold_service := IF(
  @has_include_fold_service = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `include_fold_service` TINYINT(1) NOT NULL DEFAULT '0'",
  'SELECT "SKIP include_fold_service already exists" AS status'
);
PREPARE stmt_include_fold_service FROM @sql_include_fold_service;
EXECUTE stmt_include_fold_service;
DEALLOCATE PREPARE stmt_include_fold_service;

-- =========================================================
-- 2026-04-25_laundry_service_pricing.sql
-- =========================================================
CREATE TABLE IF NOT EXISTS `laundry_service_pricing` (
  `tenant_id` bigint unsigned NOT NULL,
  `price_drop_off` decimal(16,4) NOT NULL DEFAULT '80.0000',
  `price_wash_only` decimal(16,4) NOT NULL DEFAULT '60.0000',
  `price_dry_only` decimal(16,4) NOT NULL DEFAULT '40.0000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 2026-04-26_laundry_order_types.sql
-- =========================================================
CREATE TABLE IF NOT EXISTS `laundry_order_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `code` varchar(64) NOT NULL,
  `label` varchar(150) NOT NULL,
  `service_kind` varchar(32) NOT NULL COMMENT 'full_service | wash_only | dry_only',
  `price_per_load` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_order_types_tenant_code` (`tenant_id`,`code`),
  KEY `laundry_order_types_tenant_sort` (`tenant_id`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `laundry_orders` MODIFY COLUMN `order_type` varchar(64) NOT NULL;

INSERT INTO `laundry_order_types` (`tenant_id`, `code`, `label`, `service_kind`, `price_per_load`, `sort_order`, `is_active`)
SELECT `tenant_id`, 'drop_off', 'Drop-off (Full Service)', 'full_service', `price_drop_off`, 1, 1
FROM `laundry_service_pricing` sp
WHERE NOT EXISTS (
  SELECT 1 FROM `laundry_order_types` ot WHERE ot.tenant_id = sp.tenant_id AND ot.code = 'drop_off'
);

INSERT INTO `laundry_order_types` (`tenant_id`, `code`, `label`, `service_kind`, `price_per_load`, `sort_order`, `is_active`)
SELECT `tenant_id`, 'wash_only', 'Wash only', 'wash_only', `price_wash_only`, 2, 1
FROM `laundry_service_pricing` sp
WHERE NOT EXISTS (
  SELECT 1 FROM `laundry_order_types` ot WHERE ot.tenant_id = sp.tenant_id AND ot.code = 'wash_only'
);

INSERT INTO `laundry_order_types` (`tenant_id`, `code`, `label`, `service_kind`, `price_per_load`, `sort_order`, `is_active`)
SELECT `tenant_id`, 'dry_only', 'Dry only', 'dry_only', `price_dry_only`, 3, 1
FROM `laundry_service_pricing` sp
WHERE NOT EXISTS (
  SELECT 1 FROM `laundry_order_types` ot WHERE ot.tenant_id = sp.tenant_id AND ot.code = 'dry_only'
);

-- =========================================================
-- 2026-04-27_laundry_order_type_supply_flags.sql
-- =========================================================
ALTER TABLE `laundry_order_types`
  MODIFY COLUMN `service_kind` varchar(32) NOT NULL;

SET @has_supply_block := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_order_types'
    AND COLUMN_NAME = 'supply_block'
);
SET @sql_supply_block := IF(
  @has_supply_block = 0,
  "ALTER TABLE `laundry_order_types` ADD COLUMN `supply_block` VARCHAR(32) NOT NULL DEFAULT 'none' COMMENT 'none|full_service|wash_supplies'",
  'SELECT "SKIP supply_block already exists" AS status'
);
PREPARE stmt_supply_block FROM @sql_supply_block;
EXECUTE stmt_supply_block;
DEALLOCATE PREPARE stmt_supply_block;

SET @has_show_addon_supplies := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_order_types'
    AND COLUMN_NAME = 'show_addon_supplies'
);
SET @sql_show_addon_supplies := IF(
  @has_show_addon_supplies = 0,
  "ALTER TABLE `laundry_order_types` ADD COLUMN `show_addon_supplies` TINYINT(1) NOT NULL DEFAULT '1'",
  'SELECT "SKIP show_addon_supplies already exists" AS status'
);
PREPARE stmt_show_addon_supplies FROM @sql_show_addon_supplies;
EXECUTE stmt_show_addon_supplies;
DEALLOCATE PREPARE stmt_show_addon_supplies;

UPDATE `laundry_order_types` SET `supply_block` = 'full_service', `show_addon_supplies` = 1 WHERE `service_kind` = 'full_service';
UPDATE `laundry_order_types` SET `supply_block` = 'wash_supplies', `show_addon_supplies` = 1 WHERE `service_kind` = 'wash_only';
UPDATE `laundry_order_types` SET `supply_block` = 'none', `show_addon_supplies` = 0 WHERE `service_kind` IN ('dry_only', 'rinse_only');

INSERT INTO `laundry_order_types` (`tenant_id`, `code`, `label`, `service_kind`, `supply_block`, `show_addon_supplies`, `price_per_load`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT w.`tenant_id`, 'rinse_only', 'Rinse only', 'rinse_only', 'none', 0, w.`price_per_load`, 4, 1, NOW(), NOW()
FROM `laundry_order_types` w
WHERE w.`code` = 'wash_only'
  AND NOT EXISTS (SELECT 1 FROM `laundry_order_types` x WHERE x.`tenant_id` = w.`tenant_id` AND x.`code` = 'rinse_only');

-- =========================================================
-- 2026-04-28_rinse_supplies_supply_block.sql
-- =========================================================
UPDATE `laundry_order_types`
SET `supply_block` = 'rinse_supplies'
WHERE `code` = 'rinse_only' AND `service_kind` = 'rinse_only';

-- =========================================================
-- 2026-04-29_laundry_orders_payment_tracking.sql
-- =========================================================
ALTER TABLE `laundry_orders`
  MODIFY COLUMN `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash';

SET @has_payment_status := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'payment_status'
);
SET @sql_payment_status := IF(
  @has_payment_status = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'paid'",
  'SELECT "SKIP payment_status already exists" AS status'
);
PREPARE stmt_payment_status FROM @sql_payment_status;
EXECUTE stmt_payment_status;
DEALLOCATE PREPARE stmt_payment_status;

SET @has_amount_tendered := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'amount_tendered'
);
SET @sql_amount_tendered := IF(
  @has_amount_tendered = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `amount_tendered` DECIMAL(16,4) NULL DEFAULT NULL',
  'SELECT "SKIP amount_tendered already exists" AS status'
);
PREPARE stmt_amount_tendered FROM @sql_amount_tendered;
EXECUTE stmt_amount_tendered;
DEALLOCATE PREPARE stmt_amount_tendered;

SET @has_change_amount := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'change_amount'
);
SET @sql_change_amount := IF(
  @has_change_amount = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `change_amount` DECIMAL(16,4) NULL DEFAULT NULL',
  'SELECT "SKIP change_amount already exists" AS status'
);
PREPARE stmt_change_amount FROM @sql_change_amount;
EXECUTE stmt_change_amount;
DEALLOCATE PREPARE stmt_change_amount;

UPDATE `laundry_orders` SET `payment_status` = 'paid' WHERE `status` = 'completed';
UPDATE `laundry_orders` SET `payment_status` = 'unpaid' WHERE `status` = 'running';

-- =========================================================
-- 2026-04-30_laundry_orders_inclusion_item_ids.sql
-- =========================================================
ALTER TABLE `laundry_orders`
  MODIFY COLUMN `payment_method` VARCHAR(30) NOT NULL DEFAULT 'cash';

SET @has_inclusion_detergent_item_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'inclusion_detergent_item_id'
);
SET @sql_inclusion_detergent_item_id := IF(
  @has_inclusion_detergent_item_id = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `inclusion_detergent_item_id` BIGINT UNSIGNED NULL DEFAULT NULL',
  'SELECT "SKIP inclusion_detergent_item_id already exists" AS status'
);
PREPARE stmt_inclusion_detergent_item_id FROM @sql_inclusion_detergent_item_id;
EXECUTE stmt_inclusion_detergent_item_id;
DEALLOCATE PREPARE stmt_inclusion_detergent_item_id;

SET @has_inclusion_fabcon_item_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'inclusion_fabcon_item_id'
);
SET @sql_inclusion_fabcon_item_id := IF(
  @has_inclusion_fabcon_item_id = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `inclusion_fabcon_item_id` BIGINT UNSIGNED NULL DEFAULT NULL',
  'SELECT "SKIP inclusion_fabcon_item_id already exists" AS status'
);
PREPARE stmt_inclusion_fabcon_item_id FROM @sql_inclusion_fabcon_item_id;
EXECUTE stmt_inclusion_fabcon_item_id;
DEALLOCATE PREPARE stmt_inclusion_fabcon_item_id;

SET @has_inclusion_bleach_item_id := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'laundry_orders'
    AND COLUMN_NAME = 'inclusion_bleach_item_id'
);
SET @sql_inclusion_bleach_item_id := IF(
  @has_inclusion_bleach_item_id = 0,
  'ALTER TABLE `laundry_orders` ADD COLUMN `inclusion_bleach_item_id` BIGINT UNSIGNED NULL DEFAULT NULL',
  'SELECT "SKIP inclusion_bleach_item_id already exists" AS status'
);
PREPARE stmt_inclusion_bleach_item_id FROM @sql_inclusion_bleach_item_id;
EXECUTE stmt_inclusion_bleach_item_id;
DEALLOCATE PREPARE stmt_inclusion_bleach_item_id;

