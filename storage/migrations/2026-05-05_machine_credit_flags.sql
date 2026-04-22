SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_machines'
      AND COLUMN_NAME = 'machine_type'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `laundry_machines` ADD COLUMN `machine_type` VARCHAR(20) NOT NULL DEFAULT ''c5'' AFTER `machine_kind`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_machines'
      AND COLUMN_NAME = 'credit_required'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `laundry_machines` ADD COLUMN `credit_required` TINYINT(1) NOT NULL DEFAULT 0 AFTER `machine_type`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_machines'
      AND COLUMN_NAME = 'credit_balance'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `laundry_machines` ADD COLUMN `credit_balance` DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER `credit_required`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
