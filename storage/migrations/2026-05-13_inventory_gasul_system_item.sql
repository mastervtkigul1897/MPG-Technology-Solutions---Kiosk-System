-- Ensure Gasul exists for every tenant and cannot be deleted.

SET @has_system_item := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'laundry_inventory_items'
      AND COLUMN_NAME = 'is_system_item'
);
SET @sql_system_item := IF(
    @has_system_item = 0,
    'ALTER TABLE laundry_inventory_items ADD COLUMN is_system_item TINYINT(1) NOT NULL DEFAULT 0 AFTER unit_cost',
    'SELECT "SKIP is_system_item column already exists"'
);
PREPARE stmt_system_item FROM @sql_system_item;
EXECUTE stmt_system_item;
DEALLOCATE PREPARE stmt_system_item;

INSERT INTO laundry_inventory_items (
    tenant_id,
    name,
    category,
    show_item_in,
    unit,
    stock_quantity,
    low_stock_threshold,
    unit_cost,
    is_system_item,
    created_at,
    updated_at
)
SELECT
    t.id,
    'Gasul',
    'other',
    'both',
    'tank',
    0,
    3,
    0,
    1,
    NOW(),
    NOW()
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1
    FROM laundry_inventory_items li
    WHERE li.tenant_id = t.id
      AND LOWER(li.name) = 'gasul'
);

UPDATE laundry_inventory_items
SET is_system_item = 1,
    stock_quantity = COALESCE(stock_quantity, 0),
    unit_cost = COALESCE(unit_cost, 0),
    updated_at = NOW()
WHERE LOWER(name) = 'gasul';
