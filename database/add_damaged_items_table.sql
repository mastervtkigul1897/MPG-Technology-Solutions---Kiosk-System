-- Run in phpMyAdmin (tenant database) to create the table needed by "Damaged Items"
-- Note: inventory_movements and ingredients/tenants tables must already exist.

CREATE TABLE IF NOT EXISTS `damaged_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ingredient_id` bigint unsigned NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `note` varchar(255) NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `damaged_items_tenant_id_created_at_index` (`tenant_id`,`created_at`),
  KEY `damaged_items_ingredient_id_foreign` (`ingredient_id`),
  CONSTRAINT `damaged_items_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `damaged_items_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

