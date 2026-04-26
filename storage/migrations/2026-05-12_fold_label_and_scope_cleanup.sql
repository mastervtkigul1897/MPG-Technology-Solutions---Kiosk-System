-- Replace legacy fold_only setup with two defaults:
-- 1) free_fold (always 0)
-- 2) fold_with_price (paid fold)

-- If fold_with_price does not exist yet, convert legacy fold_only to fold_with_price.
UPDATE laundry_order_types
SET
    code = 'fold_with_price',
    label = 'Fold with Price'
WHERE code = 'fold_only'
  AND NOT EXISTS (
      SELECT 1 FROM (
          SELECT id, tenant_id, code FROM laundry_order_types
      ) otx
      WHERE otx.tenant_id = laundry_order_types.tenant_id
        AND otx.code = 'fold_with_price'
  );

-- Ensure fold_with_price label is normalized.
UPDATE laundry_order_types
SET label = 'Fold with Price'
WHERE code = 'fold_with_price';

-- Create free_fold for tenants that do not have it yet.
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
    fold_staff_share_amount,
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
    'free_fold',
    'Free Fold',
    'fold_only',
    'none',
    0,
    0,
    0,
    0,
    0,
    0,
    'branch',
    0,
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
      AND ot.code = 'free_fold'
);

-- Ensure free_fold always stays free and does not carry commission config.
UPDATE laundry_order_types
SET
    price_per_load = 0,
    fold_service_amount = 0,
    fold_commission_target = 'branch',
    fold_staff_share_amount = 0
WHERE code = 'free_fold';

-- Keep fold_with_price fallback fold amount aligned to its base price.
UPDATE laundry_order_types
SET fold_service_amount = COALESCE(price_per_load, 0)
WHERE code = 'fold_with_price';
