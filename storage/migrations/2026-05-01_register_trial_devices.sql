CREATE TABLE IF NOT EXISTS `tenant_trial_devices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `device_hash` CHAR(64) NOT NULL,
  `device_token` VARCHAR(191) DEFAULT NULL,
  `device_label` VARCHAR(191) NOT NULL,
  `user_agent` TEXT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_trial_devices_device_hash_unique` (`device_hash`),
  KEY `tenant_trial_devices_tenant_id_index` (`tenant_id`),
  KEY `tenant_trial_devices_user_id_index` (`user_id`),
  CONSTRAINT `tenant_trial_devices_tenant_id_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_trial_devices_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
