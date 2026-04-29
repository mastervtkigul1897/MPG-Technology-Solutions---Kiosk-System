-- Premium SMS/Email notification settings + SMS credits
-- Safe for mixed environments (idempotent)

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'pickup_sms_enabled') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_sms_enabled TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'pickup_sms_device_id') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_sms_device_id VARCHAR(100) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'pickup_sms_template') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_sms_template TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'pickup_email_enabled') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_email_enabled TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'pickup_email_subject') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_email_subject VARCHAR(180) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'pickup_email_template') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_email_template TEXT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'sms_daily_credits') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN sms_daily_credits INT NOT NULL DEFAULT 30',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'sms_extra_credits') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN sms_extra_credits INT NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_branch_configs' AND COLUMN_NAME = 'sms_credits_last_reset_date') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN sms_credits_last_reset_date DATE NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'pickup_sms_notified_at') = 0,
    'ALTER TABLE laundry_orders ADD COLUMN pickup_sms_notified_at DATETIME NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'pickup_email_notified_at') = 0,
    'ALTER TABLE laundry_orders ADD COLUMN pickup_email_notified_at DATETIME NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Normalize existing rows for daily credits baseline.
UPDATE laundry_branch_configs
SET sms_daily_credits = COALESCE(sms_daily_credits, 30),
    sms_extra_credits = COALESCE(sms_extra_credits, 0)
WHERE tenant_id IS NOT NULL;
