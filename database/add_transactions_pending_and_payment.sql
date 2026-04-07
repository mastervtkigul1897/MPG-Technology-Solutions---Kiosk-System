-- Adds support for pending/utang and cash tendered/change.
-- Safe to run multiple times (but your MySQL may error on duplicates; run once if manual).

ALTER TABLE `transactions`
  ADD COLUMN `amount_tendered` DECIMAL(10,2) NULL DEFAULT NULL AFTER `total_amount`,
  ADD COLUMN `change_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `amount_tendered`,
  ADD COLUMN `payment_method` VARCHAR(30) NULL DEFAULT NULL AFTER `change_amount`,
  ADD COLUMN `amount_paid` DECIMAL(10,2) NULL DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN `refunded_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `amount_paid`,
  ADD COLUMN `added_paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `refunded_amount`,
  ADD COLUMN `original_total_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `added_paid_amount`,
  ADD COLUMN `was_updated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `pending_name` VARCHAR(255) NULL DEFAULT NULL AFTER `was_updated`,
  ADD COLUMN `pending_contact` VARCHAR(50) NULL DEFAULT NULL AFTER `pending_name`;

ALTER TABLE `transactions`
  ADD INDEX `transactions_tenant_status_created_at_index` (`tenant_id`, `status`, `created_at`);

