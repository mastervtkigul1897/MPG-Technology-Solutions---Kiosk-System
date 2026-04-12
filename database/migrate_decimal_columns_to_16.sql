-- Expand DECIMAL columns (run once on MySQL/MariaDB). Total width 38, scale 16.
-- App policy: 16 fractional digits matter most for STOCK (ingredients, recipe qty, damage, movements).
-- Money columns are widened here for flexibility; the UI formats currency to 2 decimals (format_money).

ALTER TABLE `expenses` MODIFY `amount` DECIMAL(38,16) NOT NULL;

ALTER TABLE `ingredients` MODIFY `unit_cost` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `ingredients` MODIFY `stock_quantity` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `ingredients` MODIFY `low_stock_threshold` DECIMAL(38,16) NOT NULL DEFAULT 0;

ALTER TABLE `product_ingredients` MODIFY `quantity_required` DECIMAL(38,16) NOT NULL;

ALTER TABLE `products` MODIFY `price` DECIMAL(38,16) NOT NULL;

ALTER TABLE `tenants` MODIFY `paid_amount` DECIMAL(38,16) NULL DEFAULT NULL;

ALTER TABLE `transaction_items` MODIFY `unit_price` DECIMAL(38,16) NOT NULL;
ALTER TABLE `transaction_items` MODIFY `unit_expense` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `transaction_items` MODIFY `line_total` DECIMAL(38,16) NOT NULL;
ALTER TABLE `transaction_items` MODIFY `line_expense` DECIMAL(38,16) NOT NULL DEFAULT 0;

ALTER TABLE `transactions` MODIFY `total_amount` DECIMAL(38,16) NOT NULL;
ALTER TABLE `transactions` MODIFY `expense_total` DECIMAL(38,16) NOT NULL DEFAULT 0;
ALTER TABLE `transactions` MODIFY `profit_total` DECIMAL(38,16) NOT NULL DEFAULT 0;

ALTER TABLE `damaged_items` MODIFY `quantity` DECIMAL(38,16) NOT NULL;

ALTER TABLE `inventory_movements` MODIFY `quantity` DECIMAL(38,16) NOT NULL;

-- Payment columns: use `migrate_transactions_payment_columns_decimal_16.sql` when those fields exist.
