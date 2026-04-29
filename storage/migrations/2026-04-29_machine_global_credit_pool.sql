-- Global machine credit pool (single credit balance for all machines).
SET @db_name := DATABASE();

SET @has_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'laundry_branch_configs'
      AND COLUMN_NAME = 'machine_global_credit_balance'
);
SET @sql := IF(
    @has_col = 0,
    'ALTER TABLE laundry_branch_configs ADD COLUMN machine_global_credit_balance DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER customer_email_required',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS laundry_machine_global_credit_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED DEFAULT NULL,
    direction ENUM('deduct','restock') NOT NULL,
    amount DECIMAL(16,4) NOT NULL DEFAULT 0,
    note VARCHAR(255) DEFAULT NULL,
    created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY laundry_machine_global_credit_mv_tenant_idx (tenant_id, created_at),
    KEY laundry_machine_global_credit_mv_order_idx (tenant_id, order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time backfill: if a branch has no global movements yet and global balance is still zero,
-- seed global balance from existing per-machine credits.
UPDATE laundry_branch_configs cfg
SET cfg.machine_global_credit_balance = (
    SELECT COALESCE(SUM(CASE WHEN m.credit_required = 1 THEN m.credit_balance ELSE 0 END), 0)
    FROM laundry_machines m
    WHERE m.tenant_id = cfg.tenant_id
)
WHERE COALESCE(cfg.machine_global_credit_balance, 0) = 0
  AND NOT EXISTS (
      SELECT 1
      FROM laundry_machine_global_credit_movements mv
      WHERE mv.tenant_id = cfg.tenant_id
      LIMIT 1
  );
