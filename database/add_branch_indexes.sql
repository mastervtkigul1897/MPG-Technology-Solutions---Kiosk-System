ALTER TABLE `tenants`
  ADD INDEX `tenants_branch_group_id_id_index` (`branch_group_id`, `id`),
  ADD INDEX `tenants_branch_group_main_active_index` (`branch_group_id`, `is_main_branch`, `is_active`),
  ADD INDEX `tenants_parent_active_index` (`parent_tenant_id`, `is_active`);
