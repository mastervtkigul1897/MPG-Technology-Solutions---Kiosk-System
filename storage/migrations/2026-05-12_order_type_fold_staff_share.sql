-- Per-order-type fold staff share amount.
-- Use this amount when fold commission target is set to staff.

SET @has_fold_staff_share := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_order_types'
      AND COLUMN_NAME = 'fold_staff_share_amount'
);

SET @sql_fold_staff_share := IF(
    @has_fold_staff_share = 0,
    'ALTER TABLE laundry_order_types ADD COLUMN fold_staff_share_amount DECIMAL(16,4) NOT NULL DEFAULT 10 AFTER fold_commission_target',
    'SELECT ''SKIP fold_staff_share_amount column already exists'''
);

PREPARE stmt_fold_staff_share FROM @sql_fold_staff_share;
EXECUTE stmt_fold_staff_share;
DEALLOCATE PREPARE stmt_fold_staff_share;

UPDATE laundry_order_types
SET fold_staff_share_amount = 10
WHERE fold_staff_share_amount IS NULL OR fold_staff_share_amount = 0;
