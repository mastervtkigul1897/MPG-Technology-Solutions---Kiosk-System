-- Inventory visibility + order inventory movement audit for void restore.

SET @has_show_item_in := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_inventory_items' AND COLUMN_NAME = 'show_item_in'
);
SET @sql_show_item_in := IF(
    @has_show_item_in = 0,
    'ALTER TABLE laundry_inventory_items ADD COLUMN show_item_in VARCHAR(20) NOT NULL DEFAULT ''both'' AFTER category',
    'SELECT ''SKIP show_item_in column already exists'''
);
PREPARE stmt_show_item_in FROM @sql_show_item_in;
EXECUTE stmt_show_item_in;
DEALLOCATE PREPARE stmt_show_item_in;

UPDATE laundry_inventory_items
SET show_item_in = 'both'
WHERE show_item_in IS NULL OR TRIM(show_item_in) = '';

CREATE TABLE IF NOT EXISTS laundry_order_inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    direction VARCHAR(10) NOT NULL DEFAULT 'deduct',
    quantity DECIMAL(16,4) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY laundry_order_inventory_movements_tenant_order_idx (tenant_id, order_id, inventory_item_id),
    KEY laundry_order_inventory_movements_item_idx (inventory_item_id),
    CONSTRAINT laundry_order_inventory_movements_order_fk FOREIGN KEY (order_id) REFERENCES laundry_orders(id) ON DELETE CASCADE,
    CONSTRAINT laundry_order_inventory_movements_item_fk FOREIGN KEY (inventory_item_id) REFERENCES laundry_inventory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
