-- Split service completion from payment: Complete frees machines; Pay records tendered amount and change.
ALTER TABLE `laundry_orders`
  ADD COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'paid' AFTER `payment_method`,
  ADD COLUMN `amount_tendered` DECIMAL(16,4) NULL DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `change_amount` DECIMAL(16,4) NULL DEFAULT NULL AFTER `amount_tendered`;

-- Existing completed rows were paid at complete-time; keep them as paid.
UPDATE `laundry_orders` SET `payment_status` = 'paid' WHERE `status` = 'completed';
UPDATE `laundry_orders` SET `payment_status` = 'unpaid' WHERE `status` = 'running';
