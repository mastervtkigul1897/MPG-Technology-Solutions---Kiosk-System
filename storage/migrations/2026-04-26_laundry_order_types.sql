-- Configurable order types (label, code, service behavior, price per load). Replaces fixed 3-type pricing row.
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

-- Widen stored order type code for custom labels
ALTER TABLE `laundry_orders` MODIFY COLUMN `order_type` varchar(64) NOT NULL;

-- Seed from legacy per-tenant pricing row (if table exists)
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
