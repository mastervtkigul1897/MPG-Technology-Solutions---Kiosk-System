ALTER TABLE laundry_order_types
    ADD COLUMN required_weight TINYINT(1) NOT NULL DEFAULT 0
    AFTER show_addon_supplies;

ALTER TABLE laundry_orders
    ADD COLUMN service_weight DECIMAL(10,3) NULL DEFAULT NULL
    AFTER dry_minutes;
