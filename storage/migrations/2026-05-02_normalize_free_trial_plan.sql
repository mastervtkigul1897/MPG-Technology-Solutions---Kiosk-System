-- Normalize legacy free-trial plan codes to the Free plan code used by the app UI.
UPDATE `tenants`
SET `license_starts_at` = COALESCE(`license_starts_at`, `created_at`, NOW()),
    `license_expires_at` = DATE_ADD(COALESCE(`license_starts_at`, `created_at`, NOW()), INTERVAL 7 DAY),
    `plan` = 'free_access'
WHERE LOWER(TRIM(`plan`)) IN ('trial', 'free', 'free_trial');

UPDATE `tenants`
SET `license_starts_at` = COALESCE(`license_starts_at`, `created_at`, NOW()),
    `license_expires_at` = DATE_ADD(COALESCE(`license_starts_at`, `created_at`, NOW()), INTERVAL 7 DAY)
WHERE LOWER(TRIM(`plan`)) = 'free_access'
  AND (`license_expires_at` IS NULL OR `license_expires_at` = '0000-00-00 00:00:00');
