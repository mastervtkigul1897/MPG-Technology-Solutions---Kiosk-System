ALTER TABLE `laundry_branch_configs`
  ADD COLUMN `activate_commission` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payroll_hours_per_day`,
  ADD COLUMN `daily_load_quota` INT NOT NULL DEFAULT 0 AFTER `activate_commission`,
  ADD COLUMN `commission_rate_per_load` DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER `daily_load_quota`,
  ADD COLUMN `activate_ot_incentives` TINYINT(1) NOT NULL DEFAULT 0 AFTER `commission_rate_per_load`;

ALTER TABLE `users`
  ADD COLUMN `working_hours_per_day` DECIMAL(6,2) NOT NULL DEFAULT 8.00 AFTER `work_days_csv`,
  ADD COLUMN `commission_eligible` TINYINT(1) NOT NULL DEFAULT 0 AFTER `working_hours_per_day`;

ALTER TABLE `laundry_orders`
  ADD COLUMN `is_free` TINYINT(1) NOT NULL DEFAULT 0 AFTER `discount_amount`,
  ADD COLUMN `is_reward` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_free`,
  ADD COLUMN `reward_config_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_reward`,
  ADD COLUMN `is_void` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reward_config_id`,
  ADD COLUMN `voided_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_void`,
  ADD COLUMN `voided_at` TIMESTAMP NULL DEFAULT NULL AFTER `voided_by`,
  ADD COLUMN `void_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `voided_at`;

ALTER TABLE `laundry_time_logs`
  ADD COLUMN `original_clock_in_at` TIMESTAMP NULL DEFAULT NULL AFTER `clock_out_photo_path`,
  ADD COLUMN `original_clock_out_at` TIMESTAMP NULL DEFAULT NULL AFTER `original_clock_in_at`,
  ADD COLUMN `is_edited` TINYINT(1) NOT NULL DEFAULT 0 AFTER `original_clock_out_at`,
  ADD COLUMN `edited_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_edited`,
  ADD COLUMN `edited_at` TIMESTAMP NULL DEFAULT NULL AFTER `edited_by`,
  ADD COLUMN `edit_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `edited_at`,
  ADD COLUMN `overtime_status` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `edit_reason`,
  ADD COLUMN `overtime_approved_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `overtime_status`,
  ADD COLUMN `overtime_approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `overtime_approved_by`;

ALTER TABLE `laundry_reward_configs`
  ADD COLUMN `reward_order_type_code` VARCHAR(64) NULL DEFAULT NULL AFTER `reward_points_cost`,
  ADD COLUMN `reward_quantity` INT NOT NULL DEFAULT 1 AFTER `reward_order_type_code`;

ALTER TABLE `laundry_reward_redemptions`
  ADD COLUMN `order_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `reward_name`,
  ADD COLUMN `reward_order_type_code` VARCHAR(64) NULL DEFAULT NULL AFTER `order_id`;

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
