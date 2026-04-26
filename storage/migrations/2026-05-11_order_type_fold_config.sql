-- Per-order-type fold configuration (amount + commission target).
ALTER TABLE laundry_order_types
    ADD COLUMN fold_service_amount DECIMAL(16,4) NOT NULL DEFAULT 10 AFTER required_weight,
    ADD COLUMN fold_commission_target VARCHAR(20) NOT NULL DEFAULT 'branch' AFTER fold_service_amount;

-- Seed existing rows from branch-level config where available.
UPDATE laundry_order_types ot
LEFT JOIN laundry_branch_configs bc ON bc.tenant_id = ot.tenant_id
SET
    ot.fold_service_amount = COALESCE(bc.fold_service_amount, ot.fold_service_amount, 10),
    ot.fold_commission_target = CASE
        WHEN LOWER(TRIM(COALESCE(bc.fold_commission_target, 'branch'))) = 'staff' THEN 'staff'
        ELSE 'branch'
    END;
