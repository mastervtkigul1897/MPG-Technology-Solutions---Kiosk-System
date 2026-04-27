CREATE TABLE IF NOT EXISTS super_admin_email_campaign_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    super_admin_user_id BIGINT UNSIGNED DEFAULT NULL,
    recipient_mode VARCHAR(30) NOT NULL DEFAULT 'segment',
    recipient_segment VARCHAR(30) DEFAULT NULL,
    subject VARCHAR(255) NOT NULL,
    recipients_total INT UNSIGNED NOT NULL DEFAULT 0,
    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY email_campaign_logs_created_idx (created_at),
    KEY email_campaign_logs_admin_idx (super_admin_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
