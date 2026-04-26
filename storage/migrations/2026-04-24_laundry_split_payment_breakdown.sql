ALTER TABLE `laundry_orders`
  ADD COLUMN `split_cash_amount` DECIMAL(16,4) NOT NULL DEFAULT 0.0000 AFTER `payment_status`,
  ADD COLUMN `split_online_amount` DECIMAL(16,4) NOT NULL DEFAULT 0.0000 AFTER `split_cash_amount`,
  ADD COLUMN `split_online_method` VARCHAR(30) NULL DEFAULT NULL AFTER `split_online_amount`;
