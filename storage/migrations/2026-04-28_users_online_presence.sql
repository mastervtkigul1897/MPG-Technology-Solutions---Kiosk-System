-- Track user online/offline presence for super admin user table badges.
-- Idempotent for mixed database states.

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_seen_at') = 0,
    'ALTER TABLE users ADD COLUMN last_seen_at TIMESTAMP NULL DEFAULT NULL AFTER last_login_at',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_online') = 0,
    'ALTER TABLE users ADD COLUMN is_online TINYINT(1) NOT NULL DEFAULT 0 AFTER last_seen_at',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Safety reset so stale sessions do not appear online after deployment.
UPDATE users SET is_online = 0 WHERE is_online <> 0;
