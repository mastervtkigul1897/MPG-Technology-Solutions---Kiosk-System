-- Phase 1 laundry tables for generic Laundry System
-- Safe to run multiple times; uses IF NOT EXISTS.

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
