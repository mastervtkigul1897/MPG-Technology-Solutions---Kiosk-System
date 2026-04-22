-- Rename legacy zonrox category values to bleach
UPDATE laundry_inventory_items
SET category = "bleach"
WHERE LOWER(TRIM(category)) = "zonrox";

-- Normalize historical add-on naming for reports/history
UPDATE laundry_order_add_ons
SET item_name = "Bleach"
WHERE LOWER(TRIM(item_name)) IN ("zonrox", "extra zonrox", "bleach / zonrox");
