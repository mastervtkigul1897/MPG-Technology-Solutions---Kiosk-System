-- SMS Gateway queue table (shared hosting friendly, idempotent updates)
CREATE TABLE IF NOT EXISTS sms_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','processing','sent','failed','delivered') NOT NULL DEFAULT 'pending',
    retry_count INT NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sms_queue_device_status (device_id, status),
    KEY idx_sms_queue_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sms_queue
    ADD COLUMN __mpg_sms_queue_tmp_guard INT NULL;
ALTER TABLE sms_queue
    DROP COLUMN __mpg_sms_queue_tmp_guard;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'device_id') = 0,
    'ALTER TABLE sms_queue ADD COLUMN device_id VARCHAR(100) NOT NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'phone') = 0,
    'ALTER TABLE sms_queue ADD COLUMN phone VARCHAR(30) NOT NULL AFTER device_id',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'message') = 0,
    'ALTER TABLE sms_queue ADD COLUMN message TEXT NOT NULL AFTER phone',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE sms_queue ADD COLUMN status ENUM(''pending'',''processing'',''sent'',''failed'',''delivered'') NOT NULL DEFAULT ''pending'' AFTER message',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'retry_count') = 0,
    'ALTER TABLE sms_queue ADD COLUMN retry_count INT NOT NULL DEFAULT 0 AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'error_message') = 0,
    'ALTER TABLE sms_queue ADD COLUMN error_message TEXT NULL AFTER retry_count',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'created_at') = 0,
    'ALTER TABLE sms_queue ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER error_message',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'sent_at') = 0,
    'ALTER TABLE sms_queue ADD COLUMN sent_at DATETIME NULL AFTER created_at',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE sms_queue ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER sent_at',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND INDEX_NAME = 'idx_sms_queue_device_status') = 0,
    'ALTER TABLE sms_queue ADD INDEX idx_sms_queue_device_status (device_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sms_queue' AND INDEX_NAME = 'idx_sms_queue_created_at') = 0,
    'ALTER TABLE sms_queue ADD INDEX idx_sms_queue_created_at (created_at)',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
