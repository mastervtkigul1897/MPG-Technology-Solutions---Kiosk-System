-- Per order type: which stock-supply block to show (full service vs wash supplies vs none) and whether add-on chemicals are shown.
ALTER TABLE `laundry_order_types`
  ADD COLUMN `supply_block` varchar(32) NOT NULL DEFAULT 'none' COMMENT 'none|full_service|wash_supplies' AFTER `service_kind`,
  ADD COLUMN `show_addon_supplies` tinyint(1) NOT NULL DEFAULT '1' AFTER `supply_block`;

UPDATE `laundry_order_types` SET `supply_block` = 'full_service', `show_addon_supplies` = 1 WHERE `service_kind` = 'full_service';
UPDATE `laundry_order_types` SET `supply_block` = 'wash_supplies', `show_addon_supplies` = 1 WHERE `service_kind` = 'wash_only';
UPDATE `laundry_order_types` SET `supply_block` = 'none', `show_addon_supplies` = 0 WHERE `service_kind` IN ('dry_only', 'rinse_only');

INSERT INTO `laundry_order_types` (`tenant_id`, `code`, `label`, `service_kind`, `supply_block`, `show_addon_supplies`, `price_per_load`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT w.`tenant_id`, 'rinse_only', 'Rinse only', 'rinse_only', 'none', 0, w.`price_per_load`, 4, 1, NOW(), NOW()
FROM `laundry_order_types` w
WHERE w.`code` = 'wash_only'
  AND NOT EXISTS (SELECT 1 FROM `laundry_order_types` x WHERE x.`tenant_id` = w.`tenant_id` AND x.`code` = 'rinse_only');
