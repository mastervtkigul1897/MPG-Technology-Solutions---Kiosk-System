-- Run once on your MySQL database (e.g. mysql ... < database/app_settings.sql)

CREATE TABLE IF NOT EXISTS `app_settings` (
  `key` VARCHAR(64) NOT NULL,
  `value` LONGTEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`key`, `value`) VALUES
  ('app_name', ''),
  ('maintenance_mode', '0'),
  ('maintenance_message', ''),
  ('subscription_warning_days', '7')
ON DUPLICATE KEY UPDATE `key` = `key`;
