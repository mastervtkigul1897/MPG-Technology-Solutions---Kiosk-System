-- Add tenant presence timestamp for online/offline visibility.

SET @has_tenants_last_seen_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tenants'
      AND COLUMN_NAME = 'last_seen_at'
);
SET @sql_tenants_last_seen_at := IF(
    @has_tenants_last_seen_at = 0,
    'ALTER TABLE tenants ADD COLUMN last_seen_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at',
    'SELECT "SKIP tenants.last_seen_at already exists"'
);
PREPARE stmt_tenants_last_seen_at FROM @sql_tenants_last_seen_at;
EXECUTE stmt_tenants_last_seen_at;
DEALLOCATE PREPARE stmt_tenants_last_seen_at;
