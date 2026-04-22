-- Hosting SQL sync for Laundry module updates
-- Includes payment tracking + free/reward/void support
-- Safe to run multiple times (uses information_schema checks where possible)

SET @db := DATABASE();

-- =========================================================
-- 1) laundry_orders payment tracking columns
-- =========================================================
SET @has_payment_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'payment_status'
);
SET @sql_payment_status := IF(
  @has_payment_status = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'paid' AFTER `payment_method`",
  "SELECT 'SKIP payment_status exists' AS status"
);
PREPARE stmt_payment_status FROM @sql_payment_status;
EXECUTE stmt_payment_status;
DEALLOCATE PREPARE stmt_payment_status;

SET @has_amount_tendered := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'amount_tendered'
);
SET @sql_amount_tendered := IF(
  @has_amount_tendered = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `amount_tendered` DECIMAL(16,4) NULL DEFAULT NULL AFTER `payment_status`",
  "SELECT 'SKIP amount_tendered exists' AS status"
);
PREPARE stmt_amount_tendered FROM @sql_amount_tendered;
EXECUTE stmt_amount_tendered;
DEALLOCATE PREPARE stmt_amount_tendered;

SET @has_change_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'change_amount'
);
SET @sql_change_amount := IF(
  @has_change_amount = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `change_amount` DECIMAL(16,4) NULL DEFAULT NULL AFTER `amount_tendered`",
  "SELECT 'SKIP change_amount exists' AS status"
);
PREPARE stmt_change_amount FROM @sql_change_amount;
EXECUTE stmt_change_amount;
DEALLOCATE PREPARE stmt_change_amount;

-- Keep existing historical semantics aligned
UPDATE `laundry_orders`
SET `payment_status` = 'paid'
WHERE `status` = 'completed' AND COALESCE(`payment_status`, '') = '';

UPDATE `laundry_orders`
SET `payment_status` = 'unpaid'
WHERE `status` = 'running' AND COALESCE(`payment_status`, '') <> 'unpaid';

-- =========================================================
-- 2) Branch payroll/commission config columns
-- =========================================================
SET @has_activate_commission := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'activate_commission'
);
SET @sql_activate_commission := IF(
  @has_activate_commission = 0,
  "ALTER TABLE `laundry_branch_configs` ADD COLUMN `activate_commission` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payroll_hours_per_day`",
  "SELECT 'SKIP activate_commission exists' AS status"
);
PREPARE stmt_activate_commission FROM @sql_activate_commission;
EXECUTE stmt_activate_commission;
DEALLOCATE PREPARE stmt_activate_commission;

SET @has_daily_load_quota := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'daily_load_quota'
);
SET @sql_daily_load_quota := IF(
  @has_daily_load_quota = 0,
  "ALTER TABLE `laundry_branch_configs` ADD COLUMN `daily_load_quota` INT NOT NULL DEFAULT 0 AFTER `activate_commission`",
  "SELECT 'SKIP daily_load_quota exists' AS status"
);
PREPARE stmt_daily_load_quota FROM @sql_daily_load_quota;
EXECUTE stmt_daily_load_quota;
DEALLOCATE PREPARE stmt_daily_load_quota;

SET @has_commission_rate_per_load := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'commission_rate_per_load'
);
SET @sql_commission_rate_per_load := IF(
  @has_commission_rate_per_load = 0,
  "ALTER TABLE `laundry_branch_configs` ADD COLUMN `commission_rate_per_load` DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER `daily_load_quota`",
  "SELECT 'SKIP commission_rate_per_load exists' AS status"
);
PREPARE stmt_commission_rate_per_load FROM @sql_commission_rate_per_load;
EXECUTE stmt_commission_rate_per_load;
DEALLOCATE PREPARE stmt_commission_rate_per_load;

SET @has_activate_ot_incentives := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'activate_ot_incentives'
);
SET @sql_activate_ot_incentives := IF(
  @has_activate_ot_incentives = 0,
  "ALTER TABLE `laundry_branch_configs` ADD COLUMN `activate_ot_incentives` TINYINT(1) NOT NULL DEFAULT 0 AFTER `commission_rate_per_load`",
  "SELECT 'SKIP activate_ot_incentives exists' AS status"
);
PREPARE stmt_activate_ot_incentives FROM @sql_activate_ot_incentives;
EXECUTE stmt_activate_ot_incentives;
DEALLOCATE PREPARE stmt_activate_ot_incentives;

-- =========================================================
-- 3) users payroll columns
-- =========================================================
SET @has_working_hours_per_day := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'working_hours_per_day'
);
SET @sql_working_hours_per_day := IF(
  @has_working_hours_per_day = 0,
  "ALTER TABLE `users` ADD COLUMN `working_hours_per_day` DECIMAL(6,2) NOT NULL DEFAULT 8.00 AFTER `work_days_csv`",
  "SELECT 'SKIP working_hours_per_day exists' AS status"
);
PREPARE stmt_working_hours_per_day FROM @sql_working_hours_per_day;
EXECUTE stmt_working_hours_per_day;
DEALLOCATE PREPARE stmt_working_hours_per_day;

SET @has_commission_eligible := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'commission_eligible'
);
SET @sql_commission_eligible := IF(
  @has_commission_eligible = 0,
  "ALTER TABLE `users` ADD COLUMN `commission_eligible` TINYINT(1) NOT NULL DEFAULT 0 AFTER `working_hours_per_day`",
  "SELECT 'SKIP commission_eligible exists' AS status"
);
PREPARE stmt_commission_eligible FROM @sql_commission_eligible;
EXECUTE stmt_commission_eligible;
DEALLOCATE PREPARE stmt_commission_eligible;

-- =========================================================
-- 4) laundry_orders free/reward/void columns
-- =========================================================
SET @has_discount_percentage := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'discount_percentage'
);
SET @sql_discount_percentage := IF(
  @has_discount_percentage = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `discount_percentage` DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER `change_amount`",
  "SELECT 'SKIP discount_percentage exists' AS status"
);
PREPARE stmt_discount_percentage FROM @sql_discount_percentage;
EXECUTE stmt_discount_percentage;
DEALLOCATE PREPARE stmt_discount_percentage;

SET @has_discount_amount := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'discount_amount'
);
SET @sql_discount_amount := IF(
  @has_discount_amount = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `discount_amount` DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER `discount_percentage`",
  "SELECT 'SKIP discount_amount exists' AS status"
);
PREPARE stmt_discount_amount FROM @sql_discount_amount;
EXECUTE stmt_discount_amount;
DEALLOCATE PREPARE stmt_discount_amount;

SET @has_is_free := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'is_free'
);
SET @sql_is_free := IF(
  @has_is_free = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `is_free` TINYINT(1) NOT NULL DEFAULT 0 AFTER `discount_amount`",
  "SELECT 'SKIP is_free exists' AS status"
);
PREPARE stmt_is_free FROM @sql_is_free;
EXECUTE stmt_is_free;
DEALLOCATE PREPARE stmt_is_free;

SET @has_is_reward := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'is_reward'
);
SET @sql_is_reward := IF(
  @has_is_reward = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `is_reward` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_free`",
  "SELECT 'SKIP is_reward exists' AS status"
);
PREPARE stmt_is_reward FROM @sql_is_reward;
EXECUTE stmt_is_reward;
DEALLOCATE PREPARE stmt_is_reward;

SET @has_reward_config_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'reward_config_id'
);
SET @sql_reward_config_id := IF(
  @has_reward_config_id = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `reward_config_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_reward`",
  "SELECT 'SKIP reward_config_id exists' AS status"
);
PREPARE stmt_reward_config_id FROM @sql_reward_config_id;
EXECUTE stmt_reward_config_id;
DEALLOCATE PREPARE stmt_reward_config_id;

SET @has_is_void := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'is_void'
);
SET @sql_is_void := IF(
  @has_is_void = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `is_void` TINYINT(1) NOT NULL DEFAULT 0 AFTER `reward_config_id`",
  "SELECT 'SKIP is_void exists' AS status"
);
PREPARE stmt_is_void FROM @sql_is_void;
EXECUTE stmt_is_void;
DEALLOCATE PREPARE stmt_is_void;

SET @has_voided_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'voided_by'
);
SET @sql_voided_by := IF(
  @has_voided_by = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `voided_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_void`",
  "SELECT 'SKIP voided_by exists' AS status"
);
PREPARE stmt_voided_by FROM @sql_voided_by;
EXECUTE stmt_voided_by;
DEALLOCATE PREPARE stmt_voided_by;

SET @has_voided_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'voided_at'
);
SET @sql_voided_at := IF(
  @has_voided_at = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `voided_at` TIMESTAMP NULL DEFAULT NULL AFTER `voided_by`",
  "SELECT 'SKIP voided_at exists' AS status"
);
PREPARE stmt_voided_at FROM @sql_voided_at;
EXECUTE stmt_voided_at;
DEALLOCATE PREPARE stmt_voided_at;

SET @has_void_reason := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'void_reason'
);
SET @sql_void_reason := IF(
  @has_void_reason = 0,
  "ALTER TABLE `laundry_orders` ADD COLUMN `void_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `voided_at`",
  "SELECT 'SKIP void_reason exists' AS status"
);
PREPARE stmt_void_reason FROM @sql_void_reason;
EXECUTE stmt_void_reason;
DEALLOCATE PREPARE stmt_void_reason;

-- =========================================================
-- 5) laundry_time_logs edit/overtime columns
-- =========================================================
SET @has_original_clock_in_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'original_clock_in_at'
);
SET @sql_original_clock_in_at := IF(
  @has_original_clock_in_at = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `original_clock_in_at` TIMESTAMP NULL DEFAULT NULL AFTER `clock_out_photo_path`",
  "SELECT 'SKIP original_clock_in_at exists' AS status"
);
PREPARE stmt_original_clock_in_at FROM @sql_original_clock_in_at;
EXECUTE stmt_original_clock_in_at;
DEALLOCATE PREPARE stmt_original_clock_in_at;

SET @has_original_clock_out_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'original_clock_out_at'
);
SET @sql_original_clock_out_at := IF(
  @has_original_clock_out_at = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `original_clock_out_at` TIMESTAMP NULL DEFAULT NULL AFTER `original_clock_in_at`",
  "SELECT 'SKIP original_clock_out_at exists' AS status"
);
PREPARE stmt_original_clock_out_at FROM @sql_original_clock_out_at;
EXECUTE stmt_original_clock_out_at;
DEALLOCATE PREPARE stmt_original_clock_out_at;

SET @has_is_edited := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'is_edited'
);
SET @sql_is_edited := IF(
  @has_is_edited = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `is_edited` TINYINT(1) NOT NULL DEFAULT 0 AFTER `original_clock_out_at`",
  "SELECT 'SKIP is_edited exists' AS status"
);
PREPARE stmt_is_edited FROM @sql_is_edited;
EXECUTE stmt_is_edited;
DEALLOCATE PREPARE stmt_is_edited;

SET @has_edited_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'edited_by'
);
SET @sql_edited_by := IF(
  @has_edited_by = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `edited_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `is_edited`",
  "SELECT 'SKIP edited_by exists' AS status"
);
PREPARE stmt_edited_by FROM @sql_edited_by;
EXECUTE stmt_edited_by;
DEALLOCATE PREPARE stmt_edited_by;

SET @has_edited_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'edited_at'
);
SET @sql_edited_at := IF(
  @has_edited_at = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `edited_at` TIMESTAMP NULL DEFAULT NULL AFTER `edited_by`",
  "SELECT 'SKIP edited_at exists' AS status"
);
PREPARE stmt_edited_at FROM @sql_edited_at;
EXECUTE stmt_edited_at;
DEALLOCATE PREPARE stmt_edited_at;

SET @has_edit_reason := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'edit_reason'
);
SET @sql_edit_reason := IF(
  @has_edit_reason = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `edit_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `edited_at`",
  "SELECT 'SKIP edit_reason exists' AS status"
);
PREPARE stmt_edit_reason FROM @sql_edit_reason;
EXECUTE stmt_edit_reason;
DEALLOCATE PREPARE stmt_edit_reason;

SET @has_overtime_status := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'overtime_status'
);
SET @sql_overtime_status := IF(
  @has_overtime_status = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `overtime_status` VARCHAR(20) NOT NULL DEFAULT 'none' AFTER `edit_reason`",
  "SELECT 'SKIP overtime_status exists' AS status"
);
PREPARE stmt_overtime_status FROM @sql_overtime_status;
EXECUTE stmt_overtime_status;
DEALLOCATE PREPARE stmt_overtime_status;

SET @has_overtime_approved_by := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'overtime_approved_by'
);
SET @sql_overtime_approved_by := IF(
  @has_overtime_approved_by = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `overtime_approved_by` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `overtime_status`",
  "SELECT 'SKIP overtime_approved_by exists' AS status"
);
PREPARE stmt_overtime_approved_by FROM @sql_overtime_approved_by;
EXECUTE stmt_overtime_approved_by;
DEALLOCATE PREPARE stmt_overtime_approved_by;

SET @has_overtime_approved_at := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_time_logs' AND COLUMN_NAME = 'overtime_approved_at'
);
SET @sql_overtime_approved_at := IF(
  @has_overtime_approved_at = 0,
  "ALTER TABLE `laundry_time_logs` ADD COLUMN `overtime_approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `overtime_approved_by`",
  "SELECT 'SKIP overtime_approved_at exists' AS status"
);
PREPARE stmt_overtime_approved_at FROM @sql_overtime_approved_at;
EXECUTE stmt_overtime_approved_at;
DEALLOCATE PREPARE stmt_overtime_approved_at;

-- =========================================================
-- 6) Reward config/redemption columns
-- =========================================================
SET @has_reward_order_type_code_cfg := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_reward_configs' AND COLUMN_NAME = 'reward_order_type_code'
);
SET @sql_reward_order_type_code_cfg := IF(
  @has_reward_order_type_code_cfg = 0,
  "ALTER TABLE `laundry_reward_configs` ADD COLUMN `reward_order_type_code` VARCHAR(64) NULL DEFAULT NULL AFTER `reward_points_cost`",
  "SELECT 'SKIP reward_order_type_code (config) exists' AS status"
);
PREPARE stmt_reward_order_type_code_cfg FROM @sql_reward_order_type_code_cfg;
EXECUTE stmt_reward_order_type_code_cfg;
DEALLOCATE PREPARE stmt_reward_order_type_code_cfg;

SET @has_reward_quantity := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_reward_configs' AND COLUMN_NAME = 'reward_quantity'
);
SET @sql_reward_quantity := IF(
  @has_reward_quantity = 0,
  "ALTER TABLE `laundry_reward_configs` ADD COLUMN `reward_quantity` INT NOT NULL DEFAULT 1 AFTER `reward_order_type_code`",
  "SELECT 'SKIP reward_quantity exists' AS status"
);
PREPARE stmt_reward_quantity FROM @sql_reward_quantity;
EXECUTE stmt_reward_quantity;
DEALLOCATE PREPARE stmt_reward_quantity;

SET @has_order_id_redemption := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_reward_redemptions' AND COLUMN_NAME = 'order_id'
);
SET @sql_order_id_redemption := IF(
  @has_order_id_redemption = 0,
  "ALTER TABLE `laundry_reward_redemptions` ADD COLUMN `order_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `reward_name`",
  "SELECT 'SKIP order_id (redemption) exists' AS status"
);
PREPARE stmt_order_id_redemption FROM @sql_order_id_redemption;
EXECUTE stmt_order_id_redemption;
DEALLOCATE PREPARE stmt_order_id_redemption;

SET @has_reward_order_type_code_redemption := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'laundry_reward_redemptions' AND COLUMN_NAME = 'reward_order_type_code'
);
SET @sql_reward_order_type_code_redemption := IF(
  @has_reward_order_type_code_redemption = 0,
  "ALTER TABLE `laundry_reward_redemptions` ADD COLUMN `reward_order_type_code` VARCHAR(64) NULL DEFAULT NULL AFTER `order_id`",
  "SELECT 'SKIP reward_order_type_code (redemption) exists' AS status"
);
PREPARE stmt_reward_order_type_code_redemption FROM @sql_reward_order_type_code_redemption;
EXECUTE stmt_reward_order_type_code_redemption;
DEALLOCATE PREPARE stmt_reward_order_type_code_redemption;

-- =========================================================
-- 7) Reward events table
-- =========================================================
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

SELECT 'DONE: hosting-phpmyadmin-laundry-sync.sql executed' AS status;
