ALTER TABLE `tenants`
  ADD COLUMN `parent_tenant_id` BIGINT UNSIGNED NULL AFTER `id`,
  ADD COLUMN `branch_group_id` BIGINT UNSIGNED NULL AFTER `parent_tenant_id`,
  ADD COLUMN `is_main_branch` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN `max_branches` INT UNSIGNED NULL DEFAULT NULL AFTER `paid_amount`;

ALTER TABLE `tenants`
  ADD INDEX `tenants_parent_tenant_id_index` (`parent_tenant_id`),
  ADD INDEX `tenants_branch_group_id_index` (`branch_group_id`),
  ADD INDEX `tenants_branch_group_active_index` (`branch_group_id`, `is_active`),
  ADD INDEX `tenants_branch_group_main_index` (`branch_group_id`, `is_main_branch`);

ALTER TABLE `tenants`
  ADD CONSTRAINT `tenants_parent_tenant_id_fk`
    FOREIGN KEY (`parent_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tenants_branch_group_id_fk`
    FOREIGN KEY (`branch_group_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL;
