UPDATE `tenants`
SET
  `parent_tenant_id` = COALESCE(`parent_tenant_id`, `id`),
  `branch_group_id` = COALESCE(`branch_group_id`, `id`),
  `is_main_branch` = CASE
    WHEN `is_main_branch` IS NULL OR `is_main_branch` = 0 THEN 1
    ELSE `is_main_branch`
  END,
  `max_branches` = COALESCE(`max_branches`, 1),
  `updated_at` = NOW()
WHERE `parent_tenant_id` IS NULL
   OR `branch_group_id` IS NULL
   OR `is_main_branch` = 0
   OR `max_branches` IS NULL;

UPDATE `tenants` t
INNER JOIN (
  SELECT
    `branch_group_id`,
    MIN(`id`) AS `min_id`
  FROM `tenants`
  WHERE `branch_group_id` IS NOT NULL
  GROUP BY `branch_group_id`
) x
  ON x.`branch_group_id` = t.`branch_group_id`
SET t.`is_main_branch` = CASE WHEN t.`id` = x.`min_id` THEN 1 ELSE 0 END,
    t.`updated_at` = NOW();
