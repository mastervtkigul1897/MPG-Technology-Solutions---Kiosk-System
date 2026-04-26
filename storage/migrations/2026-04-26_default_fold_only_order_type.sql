-- Seed Fold as a protected default order type for all tenants.
-- Safe to re-run: inserts only when tenant does not yet have fold_only.

INSERT INTO laundry_order_types (
    tenant_id,
    code,
    label,
    service_kind,
    supply_block,
    show_addon_supplies,
    required_weight,
    detergent_qty,
    fabcon_qty,
    bleach_qty,
    fold_service_amount,
    fold_commission_target,
    price_per_load,
    max_weight_kg,
    excess_weight_fee_per_kg,
    sort_order,
    is_active,
    include_in_rewards,
    created_at,
    updated_at
)
SELECT
    t.id,
    'fold_only',
    'Fold',
    'fold_only',
    'none',
    0,
    0,
    0,
    0,
    0,
    10,
    'branch',
    0,
    0,
    0,
    5,
    1,
    0,
    NOW(),
    NOW()
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1
    FROM laundry_order_types ot
    WHERE ot.tenant_id = t.id
      AND ot.code = 'fold_only'
);
