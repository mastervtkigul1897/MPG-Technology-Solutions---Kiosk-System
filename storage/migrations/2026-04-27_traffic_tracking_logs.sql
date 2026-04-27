CREATE TABLE IF NOT EXISTS super_admin_traffic_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    tenant_id BIGINT UNSIGNED DEFAULT NULL,
    role VARCHAR(40) DEFAULT NULL,
    ip_address VARCHAR(64) NOT NULL,
    user_agent VARCHAR(1024) DEFAULT NULL,
    path VARCHAR(255) NOT NULL,
    query_string TEXT DEFAULT NULL,
    referrer VARCHAR(1024) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY traffic_logs_created_idx (created_at),
    KEY traffic_logs_user_idx (user_id),
    KEY traffic_logs_tenant_idx (tenant_id),
    KEY traffic_logs_path_idx (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
