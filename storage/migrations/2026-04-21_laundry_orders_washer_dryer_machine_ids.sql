-- Optional manual migration if LaundrySchema::ensure cannot ALTER (e.g. restricted host).
-- Otherwise columns are added automatically on app boot.

ALTER TABLE `laundry_orders`
  ADD COLUMN `washer_machine_id` BIGINT UNSIGNED DEFAULT NULL AFTER `machine_id`,
  ADD COLUMN `dryer_machine_id` BIGINT UNSIGNED DEFAULT NULL AFTER `washer_machine_id`;
