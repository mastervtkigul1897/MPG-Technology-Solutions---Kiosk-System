-- Persist Track Gasul Usage as branch-level setting.

SET @has_track_gasul_usage := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'track_gasul_usage'
);
SET @sql_track_gasul_usage := IF(
    @has_track_gasul_usage = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN track_gasul_usage TINYINT(1) NOT NULL DEFAULT 0 AFTER editable_order_date',
    'SELECT "SKIP track_gasul_usage column already exists"'
);
PREPARE stmt_track_gasul_usage FROM @sql_track_gasul_usage;
EXECUTE stmt_track_gasul_usage;
DEALLOCATE PREPARE stmt_track_gasul_usage;
