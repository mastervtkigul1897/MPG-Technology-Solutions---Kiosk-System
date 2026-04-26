-- Staff Kiosk: optional auto Bluetooth print after save (per branch).
-- Column appended without AFTER so ALTER succeeds even if activate_ot_incentives is missing.
ALTER TABLE laundry_branch_configs
    ADD COLUMN enable_bluetooth_print TINYINT(1) NOT NULL DEFAULT 0;
