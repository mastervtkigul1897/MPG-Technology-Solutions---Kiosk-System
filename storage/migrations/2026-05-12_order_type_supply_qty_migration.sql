-- Order type supply quantities migration (one-time, idempotent).
-- Goal: move implied supply usage from supply_block into explicit qty fields.

-- 1) Ensure columns exist.
SET @has_det := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_order_types' AND COLUMN_NAME = 'detergent_qty'
);
SET @sql_det := IF(
    @has_det = 0,
    'ALTER TABLE laundry_order_types ADD COLUMN detergent_qty DECIMAL(10,3) NOT NULL DEFAULT 0 AFTER required_weight',
    'SELECT "SKIP detergent_qty column already exists"'
);
PREPARE stmt_det FROM @sql_det;
EXECUTE stmt_det;
DEALLOCATE PREPARE stmt_det;

SET @has_fab := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_order_types' AND COLUMN_NAME = 'fabcon_qty'
);
SET @sql_fab := IF(
    @has_fab = 0,
    'ALTER TABLE laundry_order_types ADD COLUMN fabcon_qty DECIMAL(10,3) NOT NULL DEFAULT 0 AFTER detergent_qty',
    'SELECT "SKIP fabcon_qty column already exists"'
);
PREPARE stmt_fab FROM @sql_fab;
EXECUTE stmt_fab;
DEALLOCATE PREPARE stmt_fab;

SET @has_bleach := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'laundry_order_types' AND COLUMN_NAME = 'bleach_qty'
);
SET @sql_bleach := IF(
    @has_bleach = 0,
    'ALTER TABLE laundry_order_types ADD COLUMN bleach_qty DECIMAL(10,3) NOT NULL DEFAULT 0 AFTER fabcon_qty',
    'SELECT "SKIP bleach_qty column already exists"'
);
PREPARE stmt_bleach FROM @sql_bleach;
EXECUTE stmt_bleach;
DEALLOCATE PREPARE stmt_bleach;

-- 2) Create idempotent migration log table (prevents duplicate remap).
CREATE TABLE IF NOT EXISTS laundry_order_type_supply_qty_migration_log (
    order_type_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    old_supply_block VARCHAR(32) NOT NULL DEFAULT 'none',
    migrated_detergent_qty DECIMAL(10,3) NOT NULL DEFAULT 0,
    migrated_fabcon_qty DECIMAL(10,3) NOT NULL DEFAULT 0,
    migrated_bleach_qty DECIMAL(10,3) NOT NULL DEFAULT 0,
    migrated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (order_type_id),
    KEY laundry_ot_supply_qty_migration_tenant_idx (tenant_id, migrated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) One-time remap only for order types not yet logged.
UPDATE laundry_order_types ot
LEFT JOIN laundry_order_type_supply_qty_migration_log ml ON ml.order_type_id = ot.id
SET
    ot.detergent_qty = CASE
        WHEN ot.supply_block = 'full_service_2x' THEN 2.000
        WHEN ot.supply_block IN ('full_service', 'wash_supplies') AND ot.service_kind = 'full_service' THEN 1.000
        ELSE 0.000
    END,
    ot.fabcon_qty = CASE
        WHEN ot.supply_block = 'full_service_2x' THEN 2.000
        WHEN ot.supply_block IN ('full_service', 'wash_supplies') AND ot.service_kind = 'full_service' THEN 1.000
        WHEN ot.supply_block = 'rinse_supplies' THEN 1.000
        ELSE 0.000
    END,
    ot.bleach_qty = CASE
        -- keep legacy default optional behavior unless explicitly configured later
        WHEN ot.supply_block = 'full_service_2x' THEN 0.000
        WHEN ot.supply_block IN ('full_service', 'wash_supplies') THEN 0.000
        ELSE 0.000
    END
WHERE ml.order_type_id IS NULL;

-- 4) Persist migration log for verification and re-run safety.
INSERT INTO laundry_order_type_supply_qty_migration_log (
    order_type_id,
    tenant_id,
    old_supply_block,
    migrated_detergent_qty,
    migrated_fabcon_qty,
    migrated_bleach_qty
)
SELECT
    ot.id,
    ot.tenant_id,
    ot.supply_block,
    ot.detergent_qty,
    ot.fabcon_qty,
    ot.bleach_qty
FROM laundry_order_types ot
LEFT JOIN laundry_order_type_supply_qty_migration_log ml ON ml.order_type_id = ot.id
WHERE ml.order_type_id IS NULL;

-- 5) Verification output.
SELECT
    'order_type_supply_qty_migration_summary' AS log_label,
    COUNT(*) AS total_order_types,
    SUM(CASE WHEN detergent_qty > 0 THEN 1 ELSE 0 END) AS det_configured_rows,
    SUM(CASE WHEN fabcon_qty > 0 THEN 1 ELSE 0 END) AS fab_configured_rows,
    SUM(CASE WHEN bleach_qty > 0 THEN 1 ELSE 0 END) AS bleach_configured_rows
FROM laundry_order_types;

SELECT
    'order_type_supply_qty_migration_log_rows' AS log_label,
    COUNT(*) AS migrated_rows
FROM laundry_order_type_supply_qty_migration_log;
