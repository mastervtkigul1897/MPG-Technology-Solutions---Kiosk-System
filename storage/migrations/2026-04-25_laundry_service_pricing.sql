-- Configurable per-load prices for Drop-off / Wash only / Dry only (tenant admin: Order Pricing).
CREATE TABLE IF NOT EXISTS `laundry_service_pricing` (
  `tenant_id` bigint unsigned NOT NULL,
  `price_drop_off` decimal(16,4) NOT NULL DEFAULT '80.0000',
  `price_wash_only` decimal(16,4) NOT NULL DEFAULT '60.0000',
  `price_dry_only` decimal(16,4) NOT NULL DEFAULT '40.0000',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
