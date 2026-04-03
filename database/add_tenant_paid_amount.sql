-- Optional: run manually if you prefer not to use auto-migration on first tenant create/list load.

ALTER TABLE `tenants`
  ADD COLUMN `paid_amount` DECIMAL(12,2) NULL DEFAULT NULL AFTER `license_expires_at`;
