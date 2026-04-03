-- Run in phpMyAdmin (tenant or shared DB) to create DB-driven category aliases
-- for POS "folder" grouping.
--
-- Optional: insert your aliases (example below):
--   patimpla -> paluto

CREATE TABLE IF NOT EXISTS `pos_category_aliases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `alias` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `canonical` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pos_category_aliases_tenant_alias_unique` (`tenant_id`,`alias`),
  CONSTRAINT `pos_category_aliases_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example seed (edit tenant_id if needed, or remove if you will manage it manually)
-- INSERT INTO `pos_category_aliases` (`tenant_id`, `alias`, `canonical`, `created_at`, `updated_at`)
-- VALUES (1, 'patimpla', 'paluto', NOW(), NOW());

