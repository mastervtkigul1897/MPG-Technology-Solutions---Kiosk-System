CREATE TABLE IF NOT EXISTS `tenant_backups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `backup_type` VARCHAR(32) NOT NULL DEFAULT 'manual',
  `backup_key` VARCHAR(191) NOT NULL,
  `storage_path` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `checksum_sha256` CHAR(64) NOT NULL,
  `table_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `row_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'ready',
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_backups_backup_key_unique` (`backup_key`),
  KEY `tenant_backups_tenant_id_created_at_index` (`tenant_id`, `created_at`),
  KEY `tenant_backups_status_index` (`status`),
  CONSTRAINT `tenant_backups_tenant_id_fk`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_backups_created_by_user_id_fk`
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_backup_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `backup_id` BIGINT UNSIGNED NOT NULL,
  `table_name` VARCHAR(64) NOT NULL,
  `row_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_backup_items_backup_table_unique` (`backup_id`, `table_name`),
  KEY `tenant_backup_items_table_name_index` (`table_name`),
  CONSTRAINT `tenant_backup_items_backup_id_fk`
    FOREIGN KEY (`backup_id`) REFERENCES `tenant_backups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
