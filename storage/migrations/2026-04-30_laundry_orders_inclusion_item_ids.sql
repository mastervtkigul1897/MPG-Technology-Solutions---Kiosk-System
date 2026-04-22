-- Snapshot which inventory items were used for service inclusions (stock) per order.
-- Safe to run once; ignore duplicate column errors if already applied.

ALTER TABLE `laundry_orders`
  ADD COLUMN `inclusion_detergent_item_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `include_fold_service`,
  ADD COLUMN `inclusion_fabcon_item_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `inclusion_detergent_item_id`,
  ADD COLUMN `inclusion_bleach_item_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `inclusion_fabcon_item_id`;
