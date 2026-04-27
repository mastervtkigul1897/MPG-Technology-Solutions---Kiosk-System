-- Remove legacy Fold order type (code: fold_only).
-- Free Fold and Fold with Price remain as the supported fold order types.
-- Safe to re-run.

DELETE FROM laundry_order_types
WHERE code = 'fold_only';
