-- Customer Profile: toggle required Contact/Email fields per branch.
SET @db_name := DATABASE();

SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'customer_contact_required'
);
SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN customer_contact_required TINYINT(1) NOT NULL DEFAULT 0 AFTER sms_credits_last_reset_date',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'customer_email_required'
);
SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN customer_email_required TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_contact_required',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
