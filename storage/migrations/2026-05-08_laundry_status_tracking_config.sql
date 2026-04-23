ALTER TABLE laundry_branch_configs
    ADD COLUMN laundry_status_tracking_enabled TINYINT(1) NOT NULL DEFAULT 1
    AFTER machine_assignment_enabled;
