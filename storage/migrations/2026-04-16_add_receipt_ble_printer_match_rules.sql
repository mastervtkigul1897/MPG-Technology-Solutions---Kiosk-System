-- Add configurable Bluetooth printer match rules per tenant.
-- Safe to run multiple times on MySQL/MariaDB (no IF NOT EXISTS dependency).

SET @mpg_db := DATABASE();
SET @mpg_col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @mpg_db
    AND TABLE_NAME = 'tenants'
    AND COLUMN_NAME = 'receipt_ble_printer_match_rules'
);

SET @mpg_sql := IF(
  @mpg_col_exists > 0,
  'SELECT \"OK: column receipt_ble_printer_match_rules already exists\" AS status',
  'ALTER TABLE `tenants` ADD COLUMN `receipt_ble_printer_match_rules` TEXT NULL AFTER `receipt_lan_print_copies`'
);

PREPARE mpg_stmt FROM @mpg_sql;
EXECUTE mpg_stmt;
DEALLOCATE PREPARE mpg_stmt;

