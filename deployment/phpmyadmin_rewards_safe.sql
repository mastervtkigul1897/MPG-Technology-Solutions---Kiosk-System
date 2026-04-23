-- Safe to run multiple times in phpMyAdmin.
-- It adds missing reward-related schema only when needed.

SET @db := DATABASE();

-- 1) Add laundry_order_types.include_in_rewards only if missing
SET @has_include_in_rewards := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'laundry_order_types'
    AND COLUMN_NAME = 'include_in_rewards'
);
SET @sql_include_in_rewards := IF(
  @has_include_in_rewards = 0,
  "ALTER TABLE `laundry_order_types` ADD COLUMN `include_in_rewards` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`",
  "SELECT 'SKIP include_in_rewards exists' AS status"
);
PREPARE stmt_include_in_rewards FROM @sql_include_in_rewards;
EXECUTE stmt_include_in_rewards;
DEALLOCATE PREPARE stmt_include_in_rewards;

-- 2) Backfill full-service default only on rows still zero.
--    Keep existing explicit choices untouched when already set to 1.
UPDATE `laundry_order_types`
SET `include_in_rewards` = 1
WHERE `service_kind` = 'full_service'
  AND COALESCE(`include_in_rewards`, 0) = 0;

-- 3) Ensure laundry_reward_events table exists
CREATE TABLE IF NOT EXISTS `laundry_reward_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(20) NOT NULL,
  `points_delta` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `balance_after` DECIMAL(16,4) NOT NULL DEFAULT 0,
  `reward_config_id` BIGINT UNSIGNED DEFAULT NULL,
  `reward_order_type_code` VARCHAR(64) DEFAULT NULL,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `laundry_reward_events_tenant_idx` (`tenant_id`, `created_at`),
  KEY `laundry_reward_events_customer_idx` (`tenant_id`, `customer_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'DONE rewards safe migration' AS status;
