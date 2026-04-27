-- Track Machine Movement (feature flag + timer metadata, add-only migration)

SET @has_track_machine_movement := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'track_machine_movement'
);
SET @sql_track_machine_movement := IF(
    @has_track_machine_movement = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN track_machine_movement TINYINT(1) NOT NULL DEFAULT 0 AFTER laundry_status_tracking_enabled',
    'SELECT "SKIP track_machine_movement column already exists"'
);
PREPARE stmt_track_machine_movement FROM @sql_track_machine_movement;
EXECUTE stmt_track_machine_movement;
DEALLOCATE PREPARE stmt_track_machine_movement;

SET @has_default_drying_minutes := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'default_drying_minutes'
);
SET @sql_default_drying_minutes := IF(
    @has_default_drying_minutes = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN default_drying_minutes INT NULL DEFAULT NULL AFTER track_machine_movement',
    'SELECT "SKIP default_drying_minutes column already exists"'
);
PREPARE stmt_default_drying_minutes FROM @sql_default_drying_minutes;
EXECUTE stmt_default_drying_minutes;
DEALLOCATE PREPARE stmt_default_drying_minutes;

SET @has_track_machine_stage := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'track_machine_stage'
);
SET @sql_track_machine_stage := IF(
    @has_track_machine_stage = 0,
    'ALTER TABLE laundry_orders ADD COLUMN track_machine_stage VARCHAR(40) NULL DEFAULT NULL AFTER dryer_machine_id',
    'SELECT "SKIP track_machine_stage column already exists"'
);
PREPARE stmt_track_machine_stage FROM @sql_track_machine_stage;
EXECUTE stmt_track_machine_stage;
DEALLOCATE PREPARE stmt_track_machine_stage;

SET @has_wash_rinse_minutes := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'wash_rinse_minutes'
);
SET @sql_wash_rinse_minutes := IF(
    @has_wash_rinse_minutes = 0,
    'ALTER TABLE laundry_orders ADD COLUMN wash_rinse_minutes INT NULL DEFAULT NULL AFTER track_machine_stage',
    'SELECT "SKIP wash_rinse_minutes column already exists"'
);
PREPARE stmt_wash_rinse_minutes FROM @sql_wash_rinse_minutes;
EXECUTE stmt_wash_rinse_minutes;
DEALLOCATE PREPARE stmt_wash_rinse_minutes;

SET @has_wash_rinse_machine_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'wash_rinse_machine_id'
);
SET @sql_wash_rinse_machine_id := IF(
    @has_wash_rinse_machine_id = 0,
    'ALTER TABLE laundry_orders ADD COLUMN wash_rinse_machine_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER wash_rinse_minutes',
    'SELECT "SKIP wash_rinse_machine_id column already exists"'
);
PREPARE stmt_wash_rinse_machine_id FROM @sql_wash_rinse_machine_id;
EXECUTE stmt_wash_rinse_machine_id;
DEALLOCATE PREPARE stmt_wash_rinse_machine_id;

SET @has_wash_rinse_started_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'wash_rinse_started_at'
);
SET @sql_wash_rinse_started_at := IF(
    @has_wash_rinse_started_at = 0,
    'ALTER TABLE laundry_orders ADD COLUMN wash_rinse_started_at DATETIME NULL DEFAULT NULL AFTER wash_rinse_machine_id',
    'SELECT "SKIP wash_rinse_started_at column already exists"'
);
PREPARE stmt_wash_rinse_started_at FROM @sql_wash_rinse_started_at;
EXECUTE stmt_wash_rinse_started_at;
DEALLOCATE PREPARE stmt_wash_rinse_started_at;

SET @has_wash_rinse_end_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'wash_rinse_end_at'
);
SET @sql_wash_rinse_end_at := IF(
    @has_wash_rinse_end_at = 0,
    'ALTER TABLE laundry_orders ADD COLUMN wash_rinse_end_at DATETIME NULL DEFAULT NULL AFTER wash_rinse_started_at',
    'SELECT "SKIP wash_rinse_end_at column already exists"'
);
PREPARE stmt_wash_rinse_end_at FROM @sql_wash_rinse_end_at;
EXECUTE stmt_wash_rinse_end_at;
DEALLOCATE PREPARE stmt_wash_rinse_end_at;

SET @has_drying_minutes := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'drying_minutes'
);
SET @sql_drying_minutes := IF(
    @has_drying_minutes = 0,
    'ALTER TABLE laundry_orders ADD COLUMN drying_minutes INT NULL DEFAULT NULL AFTER wash_rinse_end_at',
    'SELECT "SKIP drying_minutes column already exists"'
);
PREPARE stmt_drying_minutes FROM @sql_drying_minutes;
EXECUTE stmt_drying_minutes;
DEALLOCATE PREPARE stmt_drying_minutes;

SET @has_drying_machine_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'drying_machine_id'
);
SET @sql_drying_machine_id := IF(
    @has_drying_machine_id = 0,
    'ALTER TABLE laundry_orders ADD COLUMN drying_machine_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER drying_minutes',
    'SELECT "SKIP drying_machine_id column already exists"'
);
PREPARE stmt_drying_machine_id FROM @sql_drying_machine_id;
EXECUTE stmt_drying_machine_id;
DEALLOCATE PREPARE stmt_drying_machine_id;

SET @has_drying_started_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'drying_started_at'
);
SET @sql_drying_started_at := IF(
    @has_drying_started_at = 0,
    'ALTER TABLE laundry_orders ADD COLUMN drying_started_at DATETIME NULL DEFAULT NULL AFTER drying_machine_id',
    'SELECT "SKIP drying_started_at column already exists"'
);
PREPARE stmt_drying_started_at FROM @sql_drying_started_at;
EXECUTE stmt_drying_started_at;
DEALLOCATE PREPARE stmt_drying_started_at;

SET @has_drying_end_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'drying_end_at'
);
SET @sql_drying_end_at := IF(
    @has_drying_end_at = 0,
    'ALTER TABLE laundry_orders ADD COLUMN drying_end_at DATETIME NULL DEFAULT NULL AFTER drying_started_at',
    'SELECT "SKIP drying_end_at column already exists"'
);
PREPARE stmt_drying_end_at FROM @sql_drying_end_at;
EXECUTE stmt_drying_end_at;
DEALLOCATE PREPARE stmt_drying_end_at;

SET @has_movement_completed_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'movement_completed_at'
);
SET @sql_movement_completed_at := IF(
    @has_movement_completed_at = 0,
    'ALTER TABLE laundry_orders ADD COLUMN movement_completed_at DATETIME NULL DEFAULT NULL AFTER drying_end_at',
    'SELECT "SKIP movement_completed_at column already exists"'
);
PREPARE stmt_movement_completed_at FROM @sql_movement_completed_at;
EXECUTE stmt_movement_completed_at;
DEALLOCATE PREPARE stmt_movement_completed_at;

SET @has_movement_last_error := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_orders' AND COLUMN_NAME = 'movement_last_error'
);
SET @sql_movement_last_error := IF(
    @has_movement_last_error = 0,
    'ALTER TABLE laundry_orders ADD COLUMN movement_last_error VARCHAR(255) NULL DEFAULT NULL AFTER movement_completed_at',
    'SELECT "SKIP movement_last_error column already exists"'
);
PREPARE stmt_movement_last_error FROM @sql_movement_last_error;
EXECUTE stmt_movement_last_error;
DEALLOCATE PREPARE stmt_movement_last_error;
