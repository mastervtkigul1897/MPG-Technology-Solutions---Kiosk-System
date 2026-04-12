-- Adds "note" column to damaged_items if missing (safe to run repeatedly).
-- Run in phpMyAdmin / MySQL client.

SET @schema := DATABASE();
SET @tbl := 'damaged_items';
SET @col := 'note';

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema
        AND TABLE_NAME = @tbl
        AND COLUMN_NAME = @col
    ),
    'SELECT \"OK\" AS status',
    'ALTER TABLE damaged_items ADD COLUMN note VARCHAR(255) NULL DEFAULT NULL AFTER quantity'
  )
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
