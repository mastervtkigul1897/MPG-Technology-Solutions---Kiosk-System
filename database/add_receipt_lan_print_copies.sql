-- Optional manual migration (app also adds column via TenantReceiptFields::ensure on demand).
-- Wi‑Fi / LAN: how many ESC/POS copies the server sends per tap (1–10; default 1).

ALTER TABLE `tenants`
  ADD COLUMN `receipt_lan_print_copies` TINYINT UNSIGNED NOT NULL DEFAULT 1
  AFTER `receipt_footer_note`;
