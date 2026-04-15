-- Optional manual migration for product flavoring support.
-- App also attempts to auto-add these schema updates on runtime.

ALTER TABLE `ingredients`
  ADD COLUMN `category` VARCHAR(30) NOT NULL DEFAULT 'general' AFTER `unit`;

ALTER TABLE `products`
  ADD COLUMN `has_flavor_options` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

CREATE TABLE IF NOT EXISTS `product_flavor_ingredients` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `transaction_items`
  ADD COLUMN `flavor_ingredient_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `product_id`,
  ADD COLUMN `flavor_name` VARCHAR(255) NULL DEFAULT NULL AFTER `flavor_ingredient_id`,
  ADD COLUMN `flavor_quantity_required` DECIMAL(38,16) NULL DEFAULT NULL AFTER `flavor_name`;
