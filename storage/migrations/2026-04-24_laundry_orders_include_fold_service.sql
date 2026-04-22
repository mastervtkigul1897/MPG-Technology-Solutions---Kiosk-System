-- Folding commission: only counted in payroll when include_fold_service = 1 on drop-off orders.
ALTER TABLE `laundry_orders`
  ADD COLUMN `include_fold_service` tinyint(1) NOT NULL DEFAULT '0' AFTER `customer_id`;
