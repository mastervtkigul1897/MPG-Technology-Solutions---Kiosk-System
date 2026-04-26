ALTER TABLE `laundry_order_types`
  ADD COLUMN `max_weight_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `price_per_load`,
  ADD COLUMN `excess_weight_fee_per_kg` DECIMAL(16,4) NOT NULL DEFAULT 0.0000 AFTER `max_weight_kg`;

ALTER TABLE `laundry_orders`
  ADD COLUMN `actual_weight_kg` DECIMAL(10,3) NULL DEFAULT NULL AFTER `service_weight`,
  ADD COLUMN `excess_weight_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `actual_weight_kg`,
  ADD COLUMN `excess_weight_fee_amount` DECIMAL(16,4) NOT NULL DEFAULT 0.0000 AFTER `excess_weight_kg`;
