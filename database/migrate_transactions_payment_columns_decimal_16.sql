-- Run after payment columns exist on `transactions` (widens DECIMAL to 16 fractional digits).

ALTER TABLE `transactions` MODIFY `amount_tendered` DECIMAL(38,16) NULL DEFAULT NULL;
ALTER TABLE `transactions` MODIFY `change_amount` DECIMAL(38,16) NULL DEFAULT NULL;
ALTER TABLE `transactions` MODIFY `amount_paid` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `transactions` MODIFY `refunded_amount` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `transactions` MODIFY `added_paid_amount` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `transactions` MODIFY `original_total_amount` DECIMAL(38,16) NULL DEFAULT NULL;
