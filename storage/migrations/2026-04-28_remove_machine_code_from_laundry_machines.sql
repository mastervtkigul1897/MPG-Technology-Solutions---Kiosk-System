-- Remove legacy machine_code and rely on unique machine_label per tenant.
-- Idempotent for mixed environments.

SET @drop_idx_code := IF(
    (SELECT COUNT(*)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'laundry_machines'
       AND INDEX_NAME = 'laundry_machines_tenant_code_unique') > 0,
    'ALTER TABLE laundry_machines DROP INDEX laundry_machines_tenant_code_unique',
    'SELECT 1'
);
PREPARE stmt FROM @drop_idx_code; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_idx_label := IF(
    (SELECT COUNT(*)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'laundry_machines'
       AND INDEX_NAME = 'laundry_machines_tenant_label_unique') = 0,
    'ALTER TABLE laundry_machines ADD UNIQUE KEY laundry_machines_tenant_label_unique (tenant_id, machine_label)',
    'SELECT 1'
);
PREPARE stmt FROM @add_idx_label; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @drop_col_code := IF(
    (SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'laundry_machines'
       AND COLUMN_NAME = 'machine_code') > 0,
    'ALTER TABLE laundry_machines DROP COLUMN machine_code',
    'SELECT 1'
);
PREPARE stmt FROM @drop_col_code; EXECUTE stmt; DEALLOCATE PREPARE stmt;
