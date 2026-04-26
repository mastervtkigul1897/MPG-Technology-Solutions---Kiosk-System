-- Owner-only setting: allow inline editing of transaction order date/time in Daily Sales.

SET @has_editable_order_date := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'editable_order_date'
);
SET @sql_editable_order_date := IF(
    @has_editable_order_date = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN editable_order_date TINYINT(1) NOT NULL DEFAULT 0 AFTER laundry_status_tracking_enabled',
    'SELECT "SKIP editable_order_date column already exists"'
);
PREPARE stmt_editable_order_date FROM @sql_editable_order_date;
EXECUTE stmt_editable_order_date;
DEALLOCATE PREPARE stmt_editable_order_date;
