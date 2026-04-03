-- Run once on your database (MySQL 5.7.8+ for JSON type).
-- Grants per-cashier module access controlled by the store owner.

ALTER TABLE users
  ADD COLUMN module_permissions JSON NULL DEFAULT NULL
  AFTER tenant_id;
