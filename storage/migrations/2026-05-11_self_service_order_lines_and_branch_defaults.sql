-- Self Service order lines + branch config default alignment.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS and idempotent ALTER/UPDATE intent.

CREATE TABLE IF NOT EXISTS laundry_order_lines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_type_code VARCHAR(20) NOT NULL,
    order_type_label VARCHAR(120) NOT NULL,
    service_kind VARCHAR(30) NOT NULL DEFAULT 'full_service',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(16,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(16,4) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY laundry_order_lines_tenant_idx (tenant_id, order_id),
    CONSTRAINT laundry_order_lines_order_fk FOREIGN KEY (order_id) REFERENCES laundry_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Make sure new branch config records default to OFF and fold target defaults to branch.
ALTER TABLE laundry_branch_configs
    MODIFY COLUMN machine_assignment_enabled TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY COLUMN laundry_status_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0,
    MODIFY COLUMN fold_commission_target VARCHAR(20) NOT NULL DEFAULT 'branch';
