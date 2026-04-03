-- Safe / repeatable migration for receipt-related columns.
-- This avoids "Duplicate column name" errors in phpMyAdmin.

SET @db := DATABASE();

SET @col := 'receipt_display_name';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_display_name already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_display_name` VARCHAR(255) NULL DEFAULT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'receipt_business_style';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_business_style already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_business_style` VARCHAR(255) NULL DEFAULT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'receipt_tax_id';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_tax_id already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_tax_id` VARCHAR(100) NULL DEFAULT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'receipt_phone';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_phone already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_phone` VARCHAR(255) NULL DEFAULT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'receipt_address';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_address already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_address` TEXT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'receipt_email';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_email already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_email` VARCHAR(255) NULL DEFAULT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := 'receipt_footer_note';
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tenants' AND COLUMN_NAME = @col
    ),
    'SELECT ''Column receipt_footer_note already exists''',
    'ALTER TABLE `tenants` ADD COLUMN `receipt_footer_note` TEXT NULL'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
