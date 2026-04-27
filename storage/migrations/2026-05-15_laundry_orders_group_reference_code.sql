-- Shared group reference for split-created laundry job orders.

SET @has_group_reference_code := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_orders'
      AND COLUMN_NAME = 'group_reference_code'
);
SET @sql_group_reference_code := IF(
    @has_group_reference_code = 0,
    'ALTER TABLE laundry_orders ADD COLUMN group_reference_code VARCHAR(40) NULL DEFAULT NULL AFTER reference_code',
    'SELECT "SKIP group_reference_code column already exists"'
);
PREPARE stmt_group_reference_code FROM @sql_group_reference_code;
EXECUTE stmt_group_reference_code;
DEALLOCATE PREPARE stmt_group_reference_code;
