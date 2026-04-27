-- Kiosk per-branch automation settings
-- Safe to re-run.

SET @has_inclusion_mode := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'kiosk_inclusion_autofill_mode'
);
SET @sql_inclusion_mode := IF(
    @has_inclusion_mode = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN kiosk_inclusion_autofill_mode VARCHAR(20) NOT NULL DEFAULT ''off'' AFTER track_gasul_usage',
    'SELECT "SKIP kiosk_inclusion_autofill_mode already exists"'
);
PREPARE stmt_inclusion_mode FROM @sql_inclusion_mode;
EXECUTE stmt_inclusion_mode;
DEALLOCATE PREPARE stmt_inclusion_mode;

SET @has_fold_mode := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'kiosk_fold_autofill_mode'
);
SET @sql_fold_mode := IF(
    @has_fold_mode = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN kiosk_fold_autofill_mode VARCHAR(20) NOT NULL DEFAULT ''off'' AFTER kiosk_inclusion_autofill_mode',
    'SELECT "SKIP kiosk_fold_autofill_mode already exists"'
);
PREPARE stmt_fold_mode FROM @sql_fold_mode;
EXECUTE stmt_fold_mode;
DEALLOCATE PREPARE stmt_fold_mode;

SET @has_codes := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'kiosk_autofill_order_type_codes'
);
SET @sql_codes := IF(
    @has_codes = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN kiosk_autofill_order_type_codes TEXT NULL AFTER kiosk_fold_autofill_mode',
    'SELECT "SKIP kiosk_autofill_order_type_codes already exists"'
);
PREPARE stmt_codes FROM @sql_codes;
EXECUTE stmt_codes;
DEALLOCATE PREPARE stmt_codes;
