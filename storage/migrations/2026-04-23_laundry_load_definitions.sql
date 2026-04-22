-- Loads: named service packages with per-load inclusion quantities (detergent / fabcon / bleach).
-- Run after inventory exists; LaundrySchema::ensure() also applies compatible changes on boot.

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
  ADD COLUMN `load_definition_id` bigint unsigned DEFAULT NULL AFTER `customer_id`,
  ADD COLUMN `load_label_snapshot` varchar(200) DEFAULT NULL AFTER `load_definition_id`;
