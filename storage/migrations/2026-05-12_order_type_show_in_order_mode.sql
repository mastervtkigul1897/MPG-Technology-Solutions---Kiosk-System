-- Control where each order type is shown in Kiosk:
-- both, drop_off, self_service.
-- Default is "both" for new and existing tenants.

SET @has_show_in_order_mode := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_order_types'
      AND COLUMN_NAME = 'show_in_order_mode'
);

SET @sql_show_in_order_mode := IF(
    @has_show_in_order_mode = 0,
    'ALTER TABLE laundry_order_types ADD COLUMN show_in_order_mode VARCHAR(20) NOT NULL DEFAULT ''both'' AFTER service_kind',
    'SELECT ''SKIP show_in_order_mode column already exists'''
);

PREPARE stmt_show_in_order_mode FROM @sql_show_in_order_mode;
EXECUTE stmt_show_in_order_mode;
DEALLOCATE PREPARE stmt_show_in_order_mode;

UPDATE laundry_order_types
SET show_in_order_mode = 'both'
WHERE show_in_order_mode IS NULL
   OR TRIM(show_in_order_mode) = ''
   OR LOWER(TRIM(show_in_order_mode)) NOT IN ('both', 'drop_off', 'self_service');
