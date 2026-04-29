-- Add shop contact fields used by automatic no-reply disclaimers
-- for pickup-ready SMS/Email notifications.

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'laundry_branch_configs'
       AND COLUMN_NAME = 'pickup_contact_shop_email') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_contact_shop_email VARCHAR(180) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @q := IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'laundry_branch_configs'
       AND COLUMN_NAME = 'pickup_contact_shop_phone') = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN pickup_contact_shop_phone VARCHAR(30) NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @q; EXECUTE stmt; DEALLOCATE PREPARE stmt;
