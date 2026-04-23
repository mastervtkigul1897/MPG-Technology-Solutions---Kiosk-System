<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class LaundrySchema
{
    private static bool $ensured = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$ensured) {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_customers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                contact VARCHAR(50) DEFAULT NULL,
                email VARCHAR(150) DEFAULT NULL,
                birthday DATE DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_customers_tenant_idx (tenant_id),
                KEY laundry_customers_birthday_idx (tenant_id, birthday)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_inventory_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                category VARCHAR(40) NOT NULL DEFAULT \'other\',
                unit VARCHAR(20) NOT NULL DEFAULT \'pcs\',
                stock_quantity DECIMAL(16,4) NOT NULL DEFAULT 0,
                low_stock_threshold DECIMAL(16,4) NOT NULL DEFAULT 0,
                unit_cost DECIMAL(16,4) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_inventory_unique (tenant_id, name),
                KEY laundry_inventory_tenant_idx (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_inventory_purchases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                item_id BIGINT UNSIGNED NOT NULL,
                quantity DECIMAL(16,4) NOT NULL,
                unit_cost DECIMAL(16,4) NOT NULL DEFAULT 0,
                note VARCHAR(255) DEFAULT NULL,
                purchased_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_inventory_purchases_tenant_idx (tenant_id, purchased_at),
                CONSTRAINT laundry_inventory_purchases_item_fk FOREIGN KEY (item_id) REFERENCES laundry_inventory_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_orders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                machine_id BIGINT UNSIGNED DEFAULT NULL,
                customer_id BIGINT UNSIGNED DEFAULT NULL,
                order_type VARCHAR(20) NOT NULL,
                machine_type VARCHAR(20) DEFAULT NULL,
                wash_qty INT NOT NULL DEFAULT 0,
                dry_minutes INT NOT NULL DEFAULT 0,
                subtotal DECIMAL(16,4) NOT NULL DEFAULT 0,
                add_on_total DECIMAL(16,4) NOT NULL DEFAULT 0,
                total_amount DECIMAL(16,4) NOT NULL DEFAULT 0,
                payment_method VARCHAR(30) NOT NULL DEFAULT \'cash\',
                status VARCHAR(20) NOT NULL DEFAULT \'completed\',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_orders_tenant_idx (tenant_id, created_at),
                KEY laundry_orders_user_idx (created_by_user_id),
                KEY laundry_orders_customer_idx (customer_id),
                CONSTRAINT laundry_orders_customer_fk FOREIGN KEY (customer_id) REFERENCES laundry_customers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_machines (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                machine_kind VARCHAR(20) NOT NULL DEFAULT \'washer\',
                machine_type VARCHAR(20) NOT NULL DEFAULT \'c5\',
                credit_required TINYINT(1) NOT NULL DEFAULT 0,
                credit_balance DECIMAL(16,4) NOT NULL DEFAULT 0,
                machine_code VARCHAR(60) NOT NULL,
                machine_label VARCHAR(120) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'available\',
                current_order_id BIGINT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_machines_tenant_code_unique (tenant_id, machine_code),
                KEY laundry_machines_tenant_status_idx (tenant_id, status),
                KEY laundry_machines_tenant_kind_idx (tenant_id, machine_kind)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_order_add_ons (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                item_name VARCHAR(120) NOT NULL,
                quantity DECIMAL(16,4) NOT NULL DEFAULT 1,
                unit_price DECIMAL(16,4) NOT NULL DEFAULT 0,
                total_price DECIMAL(16,4) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY laundry_order_addons_tenant_idx (tenant_id),
                CONSTRAINT laundry_order_addons_order_fk FOREIGN KEY (order_id) REFERENCES laundry_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_attendance (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                staff_name VARCHAR(120) NOT NULL,
                attendance_date DATE NOT NULL,
                days_worked DECIMAL(6,2) NOT NULL DEFAULT 1,
                loads_folded INT NOT NULL DEFAULT 0,
                day_rate DECIMAL(16,4) NOT NULL DEFAULT 350,
                folding_fee_per_load DECIMAL(16,4) NOT NULL DEFAULT 10,
                deductions DECIMAL(16,4) NOT NULL DEFAULT 0,
                notes VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_attendance_tenant_idx (tenant_id, attendance_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_time_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                clock_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                clock_out_at TIMESTAMP NULL DEFAULT NULL,
                note VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_time_logs_tenant_idx (tenant_id, clock_in_at),
                KEY laundry_time_logs_user_idx (user_id, clock_in_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_load_cards (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                machine_type VARCHAR(20) NOT NULL,
                balance DECIMAL(16,4) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_load_cards_unique (tenant_id, machine_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_reward_configs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                points_per_amount_spent DECIMAL(16,4) NOT NULL DEFAULT 0,
                points_per_dropoff_load DECIMAL(16,4) NOT NULL DEFAULT 1,
                reward_name VARCHAR(120) NOT NULL DEFAULT "Reward",
                reward_description VARCHAR(255) DEFAULT NULL,
                reward_points_cost INT NOT NULL DEFAULT 10,
                minimum_points_to_redeem INT NOT NULL DEFAULT 10,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_reward_configs_tenant_unique (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_branch_configs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                machine_assignment_enabled TINYINT(1) NOT NULL DEFAULT 1,
                laundry_status_tracking_enabled TINYINT(1) NOT NULL DEFAULT 1,
                fold_service_amount DECIMAL(16,4) NOT NULL DEFAULT 10,
                fold_commission_target VARCHAR(20) NOT NULL DEFAULT "staff",
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_branch_configs_tenant_unique (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_customer_points (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                points_balance DECIMAL(16,4) NOT NULL DEFAULT 0,
                lifetime_earned DECIMAL(16,4) NOT NULL DEFAULT 0,
                lifetime_redeemed DECIMAL(16,4) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_customer_points_unique (tenant_id, customer_id),
                KEY laundry_customer_points_tenant_idx (tenant_id),
                CONSTRAINT laundry_customer_points_customer_fk FOREIGN KEY (customer_id) REFERENCES laundry_customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_reward_redemptions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                reward_name VARCHAR(120) NOT NULL,
                order_id BIGINT UNSIGNED DEFAULT NULL,
                reward_order_type_code VARCHAR(64) DEFAULT NULL,
                points_used DECIMAL(16,4) NOT NULL DEFAULT 0,
                redeemed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_reward_redemptions_tenant_idx (tenant_id, created_at),
                CONSTRAINT laundry_reward_redemptions_customer_fk FOREIGN KEY (customer_id) REFERENCES laundry_customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_service_pricing (
                tenant_id BIGINT UNSIGNED NOT NULL,
                price_drop_off DECIMAL(16,4) NOT NULL DEFAULT 80,
                price_wash_only DECIMAL(16,4) NOT NULL DEFAULT 60,
                price_dry_only DECIMAL(16,4) NOT NULL DEFAULT 40,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_order_types (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(150) NOT NULL,
                service_kind VARCHAR(32) NOT NULL,
                supply_block VARCHAR(32) NOT NULL DEFAULT \'none\',
                show_addon_supplies TINYINT(1) NOT NULL DEFAULT 1,
                required_weight TINYINT(1) NOT NULL DEFAULT 0,
                price_per_load DECIMAL(16,4) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY laundry_order_types_tenant_code (tenant_id, code),
                KEY laundry_order_types_tenant_sort (tenant_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::addColumnIfMissing($pdo, 'laundry_order_types', 'supply_block', 'VARCHAR(32) NOT NULL DEFAULT \'none\' AFTER service_kind');
        self::addColumnIfMissing($pdo, 'laundry_order_types', 'show_addon_supplies', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER supply_block');
        self::addColumnIfMissing($pdo, 'laundry_order_types', 'required_weight', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER show_addon_supplies');
        try {
            $chk = $pdo->prepare('SHOW COLUMNS FROM `laundry_order_types` LIKE ?');
            $chk->execute(['include_in_rewards']);
            $includeRewardsMissing = $chk->fetch(PDO::FETCH_ASSOC) === false;
        } catch (\Throwable) {
            $includeRewardsMissing = false;
        }
        self::addColumnIfMissing($pdo, 'laundry_order_types', 'include_in_rewards', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
        if ($includeRewardsMissing) {
            try {
                $pdo->exec("UPDATE laundry_order_types SET include_in_rewards = 1 WHERE service_kind = 'full_service'");
            } catch (\Throwable) {
            }
        }

        self::widenOrderTypeColumn($pdo);
        self::ensureOrderTypesForAllTenants($pdo);
        self::ensureRinseOnlyOrderType($pdo);
        self::ensureDryCleaningOrderType($pdo);

        self::addColumnIfMissing($pdo, 'users', 'day_rate', 'DECIMAL(16,4) NOT NULL DEFAULT 350');
        self::addColumnIfMissing($pdo, 'users', 'folding_fee_per_load', 'DECIMAL(16,4) NOT NULL DEFAULT 10');
        self::addColumnIfMissing($pdo, 'users', 'staff_type', 'VARCHAR(20) NOT NULL DEFAULT \'full_time\' AFTER role');
        self::addColumnIfMissing($pdo, 'users', 'overtime_rate_per_hour', 'DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER day_rate');
        self::addColumnIfMissing($pdo, 'users', 'work_days_csv', 'VARCHAR(64) NOT NULL DEFAULT \'1,2,3,4,5,6,7\' AFTER overtime_rate_per_hour');
        self::addColumnIfMissing($pdo, 'users', 'working_hours_per_day', 'DECIMAL(6,2) NOT NULL DEFAULT 8.00 AFTER work_days_csv');
        self::addColumnIfMissing($pdo, 'users', 'commission_eligible', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER working_hours_per_day');
        self::addColumnIfMissing($pdo, 'laundry_reward_configs', 'reward_order_type_code', 'VARCHAR(64) NULL DEFAULT NULL AFTER reward_points_cost');
        self::addColumnIfMissing($pdo, 'laundry_reward_configs', 'reward_quantity', 'INT NOT NULL DEFAULT 1 AFTER reward_order_type_code');
        self::addColumnIfMissing($pdo, 'laundry_reward_redemptions', 'order_id', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER reward_name');
        self::addColumnIfMissing($pdo, 'laundry_reward_redemptions', 'reward_order_type_code', 'VARCHAR(64) NULL DEFAULT NULL AFTER order_id');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'payroll_cutoff_days', 'INT NOT NULL DEFAULT 15 AFTER fold_commission_target');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'payroll_hours_per_day', 'DECIMAL(6,2) NOT NULL DEFAULT 8.00 AFTER payroll_cutoff_days');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'activate_commission', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER payroll_hours_per_day');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'daily_load_quota', 'INT NOT NULL DEFAULT 0 AFTER activate_commission');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'commission_rate_per_load', 'DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER daily_load_quota');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'activate_ot_incentives', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER commission_rate_per_load');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'laundry_status_tracking_enabled', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER machine_assignment_enabled');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'created_by_user_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER tenant_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'machine_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER created_by_user_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'reference_code', 'VARCHAR(32) NULL DEFAULT NULL AFTER id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'washer_machine_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER machine_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'dryer_machine_id', 'BIGINT UNSIGNED DEFAULT NULL AFTER washer_machine_id');
        self::addColumnIfMissing($pdo, 'laundry_inventory_items', 'category', 'VARCHAR(40) NOT NULL DEFAULT \'other\' AFTER name');
        self::addColumnIfMissing($pdo, 'laundry_inventory_items', 'image_path', 'VARCHAR(255) NULL DEFAULT NULL AFTER unit_cost');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'fold_service_amount', 'DECIMAL(16,4) NOT NULL DEFAULT 10 AFTER machine_assignment_enabled');
        self::addColumnIfMissing($pdo, 'laundry_branch_configs', 'fold_commission_target', 'VARCHAR(20) NOT NULL DEFAULT "staff" AFTER fold_service_amount');
        try {
            $pdo->exec('ALTER TABLE laundry_orders ADD UNIQUE KEY laundry_orders_tenant_reference_unique (tenant_id, reference_code)');
        } catch (\Throwable) {
        }
        self::ensureMachineCreditColumns($pdo);
        self::addColumnIfMissing($pdo, 'laundry_customers', 'email', 'VARCHAR(150) DEFAULT NULL AFTER contact');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'include_fold_service', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'inclusion_detergent_item_id', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER include_fold_service');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'inclusion_fabcon_item_id', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER inclusion_detergent_item_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'inclusion_bleach_item_id', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER inclusion_fabcon_item_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'payment_status', "VARCHAR(20) NOT NULL DEFAULT 'paid' AFTER payment_method");
        self::addColumnIfMissing($pdo, 'laundry_orders', 'service_weight', 'DECIMAL(10,3) NULL DEFAULT NULL AFTER dry_minutes');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'amount_tendered', 'DECIMAL(16,4) NULL DEFAULT NULL AFTER payment_status');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'change_amount', 'DECIMAL(16,4) NULL DEFAULT NULL AFTER amount_tendered');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'discount_percentage', 'DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER change_amount');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'discount_amount', 'DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER discount_percentage');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'is_free', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER discount_amount');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'is_reward', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER is_free');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'reward_config_id', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER is_reward');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'is_void', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER reward_config_id');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'voided_by', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER is_void');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'voided_at', 'TIMESTAMP NULL DEFAULT NULL AFTER voided_by');
        self::addColumnIfMissing($pdo, 'laundry_orders', 'void_reason', 'VARCHAR(255) NULL DEFAULT NULL AFTER voided_at');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'clock_in_photo_path', 'VARCHAR(255) NULL DEFAULT NULL AFTER clock_in_at');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'clock_out_photo_path', 'VARCHAR(255) NULL DEFAULT NULL AFTER clock_out_at');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'original_clock_in_at', 'TIMESTAMP NULL DEFAULT NULL AFTER clock_out_photo_path');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'original_clock_out_at', 'TIMESTAMP NULL DEFAULT NULL AFTER original_clock_in_at');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'is_edited', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER original_clock_out_at');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'edited_by', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER is_edited');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'edited_at', 'TIMESTAMP NULL DEFAULT NULL AFTER edited_by');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'edit_reason', 'VARCHAR(255) NULL DEFAULT NULL AFTER edited_at');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'overtime_status', "VARCHAR(20) NOT NULL DEFAULT 'none' AFTER edit_reason");
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'overtime_approved_by', 'BIGINT UNSIGNED NULL DEFAULT NULL AFTER overtime_status');
        self::addColumnIfMissing($pdo, 'laundry_time_logs', 'overtime_approved_at', 'TIMESTAMP NULL DEFAULT NULL AFTER overtime_approved_by');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS laundry_reward_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED DEFAULT NULL,
                event_type VARCHAR(20) NOT NULL,
                points_delta DECIMAL(16,4) NOT NULL DEFAULT 0,
                balance_after DECIMAL(16,4) NOT NULL DEFAULT 0,
                reward_config_id BIGINT UNSIGNED DEFAULT NULL,
                reward_order_type_code VARCHAR(64) DEFAULT NULL,
                actor_user_id BIGINT UNSIGNED DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY laundry_reward_events_tenant_idx (tenant_id, created_at),
                KEY laundry_reward_events_customer_idx (tenant_id, customer_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::seedDefaults($pdo);
        self::$ensured = true;
    }

    public static function ensureMachineCreditColumns(PDO $pdo): void
    {
        self::addColumnIfMissing($pdo, 'laundry_machines', 'machine_kind', 'VARCHAR(20) NOT NULL DEFAULT \'washer\' AFTER tenant_id');
        self::addColumnIfMissing($pdo, 'laundry_machines', 'machine_type', 'VARCHAR(20) NOT NULL DEFAULT \'c5\' AFTER machine_kind');
        self::addColumnIfMissing($pdo, 'laundry_machines', 'credit_required', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER machine_type');
        self::addColumnIfMissing($pdo, 'laundry_machines', 'credit_balance', 'DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER credit_required');
    }

    private static function widenOrderTypeColumn(PDO $pdo): void
    {
        try {
            $pdo->exec('ALTER TABLE laundry_orders MODIFY COLUMN order_type VARCHAR(64) NOT NULL');
        } catch (\Throwable) {
        }
    }

    /**
     * Seed default order types per tenant when none exist (from legacy laundry_service_pricing or built-in defaults).
     */
    public static function ensureOrderTypesForAllTenants(PDO $pdo): void
    {
        try {
            $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return;
        }
        $countSt = $pdo->prepare('SELECT COUNT(*) FROM laundry_order_types WHERE tenant_id = ?');
        $ins = $pdo->prepare(
            'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, supply_block, show_addon_supplies, required_weight, price_per_load, sort_order, is_active, include_in_rewards, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())'
        );
        foreach ($tenantIds as $tid) {
            $tenantId = (int) $tid;
            $countSt->execute([$tenantId]);
            if ((int) $countSt->fetchColumn() > 0) {
                continue;
            }
            $pDrop = 80.0;
            $pWash = 60.0;
            $pDry = 40.0;
            try {
                $lp = $pdo->prepare(
                    'SELECT price_drop_off, price_wash_only, price_dry_only FROM laundry_service_pricing WHERE tenant_id = ? LIMIT 1'
                );
                $lp->execute([$tenantId]);
                $row = $lp->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $pDrop = max(0.0, (float) ($row['price_drop_off'] ?? $pDrop));
                    $pWash = max(0.0, (float) ($row['price_wash_only'] ?? $pWash));
                    $pDry = max(0.0, (float) ($row['price_dry_only'] ?? $pDry));
                }
            } catch (\Throwable) {
            }
            $ins->execute([$tenantId, 'drop_off', 'Drop-off (Full Service)', 'full_service', 'full_service', 1, 0, $pDrop, 1, 1]);
            $ins->execute([$tenantId, 'wash_only', 'Wash only', 'wash_only', 'wash_supplies', 1, 0, $pWash, 2, 0]);
            $ins->execute([$tenantId, 'dry_only', 'Dry only', 'dry_only', 'none', 0, 0, $pDry, 3, 0]);
            $ins->execute([$tenantId, 'rinse_only', 'Rinse only', 'rinse_only', 'rinse_supplies', 0, 0, $pWash, 4, 0]);
            $ins->execute([$tenantId, 'dry_cleaning', 'Dry Cleaning', 'dry_cleaning', 'none', 0, 1, $pWash, 5, 0]);
        }
    }

    /**
     * Add default "Rinse only" order type when the tenant already has the three base types from an older seed.
     */
    private static function ensureRinseOnlyOrderType(PDO $pdo): void
    {
        try {
            $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return;
        }
        $chk = $pdo->prepare('SELECT 1 FROM laundry_order_types WHERE tenant_id = ? AND code = \'rinse_only\' LIMIT 1');
        $priceWash = $pdo->prepare('SELECT price_per_load FROM laundry_order_types WHERE tenant_id = ? AND code = \'wash_only\' LIMIT 1');
        $ins = $pdo->prepare(
            'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, supply_block, show_addon_supplies, required_weight, price_per_load, sort_order, is_active, include_in_rewards, created_at, updated_at)
             VALUES (?, \'rinse_only\', \'Rinse only\', \'rinse_only\', \'rinse_supplies\', 0, 0, ?, 4, 1, 0, NOW(), NOW())'
        );
        foreach ($tenantIds as $tid) {
            $tenantId = (int) $tid;
            $chk->execute([$tenantId]);
            if ($chk->fetch() !== false) {
                continue;
            }
            $p = 60.0;
            $priceWash->execute([$tenantId]);
            $pw = $priceWash->fetch(PDO::FETCH_ASSOC);
            if (is_array($pw) && isset($pw['price_per_load'])) {
                $p = max(0.0, (float) $pw['price_per_load']);
            }
            try {
                $ins->execute([$tenantId, $p]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Add default "Dry Cleaning" order type for legacy tenants.
     */
    private static function ensureDryCleaningOrderType(PDO $pdo): void
    {
        try {
            $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return;
        }
        $chk = $pdo->prepare('SELECT 1 FROM laundry_order_types WHERE tenant_id = ? AND code = \'dry_cleaning\' LIMIT 1');
        $priceWash = $pdo->prepare('SELECT price_per_load FROM laundry_order_types WHERE tenant_id = ? AND code = \'wash_only\' LIMIT 1');
        $ins = $pdo->prepare(
            'INSERT INTO laundry_order_types (tenant_id, code, label, service_kind, supply_block, show_addon_supplies, required_weight, price_per_load, sort_order, is_active, include_in_rewards, created_at, updated_at)
             VALUES (?, \'dry_cleaning\', \'Dry Cleaning\', \'dry_cleaning\', \'none\', 0, 1, ?, 5, 1, 0, NOW(), NOW())'
        );
        foreach ($tenantIds as $tid) {
            $tenantId = (int) $tid;
            $chk->execute([$tenantId]);
            if ($chk->fetch() !== false) {
                continue;
            }
            $p = 60.0;
            try {
                $priceWash->execute([$tenantId]);
                $v = $priceWash->fetchColumn();
                if ($v !== false) {
                    $p = max(0.0, (float) $v);
                }
            } catch (\Throwable) {
            }
            $ins->execute([$tenantId, $p]);
        }
    }

    private static function addColumnIfMissing(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $st = $pdo->prepare('SHOW COLUMNS FROM `'.$table.'` LIKE ?');
            $st->execute([$column]);
            $exists = $st->fetch(PDO::FETCH_ASSOC) !== false;
            if ($exists) {
                return;
            }
            $pdo->exec('ALTER TABLE `'.$table.'` ADD COLUMN `'.$column.'` '.$definition);
        } catch (\Throwable) {
            // Ignore on environments where ALTER is restricted; table may already be in sync.
        }
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM laundry_inventory_items')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $defaults = [
            ['Detergent', 'pcs', 0, 30],
            ['Fabric Conditioner', 'pcs', 0, 30],
            ['Bleach Sachet', 'pcs', 0, 30],
            ['Colorsafe (1 Liter)', 'bottle', 0, 10],
            ['Baking Soda', 'pack', 0, 3],
            ['Vinegar', 'bottle', 0, 3],
            ['Finishing Spray', 'bottle', 0, 10],
            ['Gas', 'tank', 0, 3],
            ['Cellophane', 'pcs', 0, 30],
            ['Receipt', 'roll', 0, 3],
        ];

        $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $st = $pdo->prepare(
            'INSERT INTO laundry_inventory_items (tenant_id, name, unit, stock_quantity, low_stock_threshold, unit_cost, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())'
        );
        foreach ($tenantIds as $tenantId) {
            foreach ($defaults as $row) {
                $st->execute([(int) $tenantId, $row[0], $row[1], $row[2], $row[3]]);
            }
        }
    }

}
