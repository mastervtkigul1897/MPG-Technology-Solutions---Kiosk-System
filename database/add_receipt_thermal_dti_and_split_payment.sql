-- MPG Kiosk System
-- Safe/idempotent SQL for phpMyAdmin import
-- Adds tenant receipt thermal safety config, BIR/DTI fields, and split payment breakdown storage.

SET @db_name := DATABASE();

-- tenants.receipt_escpos_line_width
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_escpos_line_width'
        ),
        'SELECT ''skip tenants.receipt_escpos_line_width (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_escpos_line_width` TINYINT UNSIGNED NOT NULL DEFAULT 32'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_escpos_right_col_width
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_escpos_right_col_width'
        ),
        'SELECT ''skip tenants.receipt_escpos_right_col_width (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_escpos_right_col_width` TINYINT UNSIGNED NOT NULL DEFAULT 10'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_escpos_extra_feeds
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_escpos_extra_feeds'
        ),
        'SELECT ''skip tenants.receipt_escpos_extra_feeds (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_escpos_extra_feeds` TINYINT UNSIGNED NOT NULL DEFAULT 8'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_escpos_cut_mode
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_escpos_cut_mode'
        ),
        'SELECT ''skip tenants.receipt_escpos_cut_mode (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_escpos_cut_mode` VARCHAR(16) NOT NULL DEFAULT ''none'''
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_serial_number
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_serial_number'
        ),
        'SELECT ''skip tenants.receipt_serial_number (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_serial_number` VARCHAR(100) NULL DEFAULT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_vat_applicable
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_vat_applicable'
        ),
        'SELECT ''skip tenants.receipt_vat_applicable (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_vat_applicable` TINYINT(1) NOT NULL DEFAULT 1'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_dti_number
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_dti_number'
        ),
        'SELECT ''skip tenants.receipt_dti_number (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_dti_number` VARCHAR(100) NULL DEFAULT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_tax_type
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_tax_type'
        ),
        'SELECT ''skip tenants.receipt_tax_type (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_tax_type` VARCHAR(16) NOT NULL DEFAULT ''non_vat'''
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_is_bir_registered
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_is_bir_registered'
        ),
        'SELECT ''skip tenants.receipt_is_bir_registered (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_is_bir_registered` TINYINT(1) NOT NULL DEFAULT 0'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_bir_accreditation_no
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_bir_accreditation_no'
        ),
        'SELECT ''skip tenants.receipt_bir_accreditation_no (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_bir_accreditation_no` VARCHAR(120) NULL DEFAULT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_min
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_min'
        ),
        'SELECT ''skip tenants.receipt_min (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_min` VARCHAR(120) NULL DEFAULT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tenants.receipt_permit_no
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'tenants'
              AND COLUMN_NAME = 'receipt_permit_no'
        ),
        'SELECT ''skip tenants.receipt_permit_no (exists)''',
        'ALTER TABLE `tenants` ADD COLUMN `receipt_permit_no` VARCHAR(120) NULL DEFAULT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- transactions.payment_breakdown_json
SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = @db_name
              AND TABLE_NAME = 'transactions'
              AND COLUMN_NAME = 'payment_breakdown_json'
        ),
        'SELECT ''skip transactions.payment_breakdown_json (exists)''',
        'ALTER TABLE `transactions` ADD COLUMN `payment_breakdown_json` LONGTEXT NULL DEFAULT NULL'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
