-- Require a reference number for non-cash payment methods.
SET @db_name := DATABASE();

SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'laundry_orders'
      AND COLUMN_NAME = 'payment_reference_no'
);
SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE laundry_orders ADD COLUMN payment_reference_no VARCHAR(120) NULL DEFAULT NULL AFTER split_online_method',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
