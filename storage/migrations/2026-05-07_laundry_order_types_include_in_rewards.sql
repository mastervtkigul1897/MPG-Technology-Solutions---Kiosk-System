ALTER TABLE `laundry_order_types`
  ADD COLUMN `include_in_rewards` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`;

-- If this migration already ran without a data step, run once in MySQL (then stop re-running it if you turn rewards off for a full-service type on purpose):
-- UPDATE `laundry_order_types` SET `include_in_rewards` = 1 WHERE `service_kind` = 'full_service';
