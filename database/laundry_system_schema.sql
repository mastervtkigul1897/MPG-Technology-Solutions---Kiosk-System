-- Laundry System bootstrap schema
-- Create and use a separate database from the old legacy database.

CREATE DATABASE IF NOT EXISTS `laundry_system`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `laundry_system`;

-- Core platform tables (users/tenants/etc.) should be imported from your base app schema first.
-- This file adds laundry-specific domain tables for Phase 1.

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
  `stock_quantity` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `low_stock_threshold` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_inventory_unique` (`tenant_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_inventory_purchases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `quantity` decimal(16,4) NOT NULL,
  `unit_cost` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `note` varchar(255) DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_inventory_purchases_tenant_idx` (`tenant_id`,`purchased_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned DEFAULT NULL,
  `order_type` varchar(20) NOT NULL,
  `machine_type` varchar(20) DEFAULT NULL,
  `wash_qty` int NOT NULL DEFAULT '0',
  `dry_minutes` int NOT NULL DEFAULT '0',
  `subtotal` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `add_on_total` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `payment_method` varchar(30) NOT NULL DEFAULT 'cash',
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_orders_tenant_idx` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_order_add_ons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `item_name` varchar(120) NOT NULL,
  `quantity` decimal(16,4) NOT NULL DEFAULT '1.0000',
  `unit_price` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `total_price` decimal(16,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `staff_name` varchar(120) NOT NULL,
  `attendance_date` date NOT NULL,
  `days_worked` decimal(6,2) NOT NULL DEFAULT '1.00',
  `loads_folded` int NOT NULL DEFAULT '0',
  `day_rate` decimal(16,4) NOT NULL DEFAULT '350.0000',
  `folding_fee_per_load` decimal(16,4) NOT NULL DEFAULT '10.0000',
  `deductions` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_attendance_tenant_idx` (`tenant_id`,`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `laundry_load_cards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `machine_type` varchar(20) NOT NULL,
  `balance` decimal(16,4) NOT NULL DEFAULT '0.0000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `laundry_load_cards_unique` (`tenant_id`,`machine_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
