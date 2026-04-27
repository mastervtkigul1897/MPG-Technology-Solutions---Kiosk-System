<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class TrafficTrackingController
{
    public function index(Request $request): Response
    {
        $pdo = App::db();
        if (! $this->hasTrafficTable($pdo)) {
            return view_page('Traffic Tracking', 'super-admin.traffic-tracking.index', [
                'traffic_table_ready' => false,
                'daily_traffic_rows' => [],
                'traffic_totals' => [
                    'today_visits' => 0,
                    'today_unique_visitors' => 0,
                    'today_login_count' => 0,
                    'today_main_page_visits' => 0,
                    'last_7_days_visits' => 0,
                    'last_30_days_visits' => 0,
                ],
                'recent_visitors' => [],
            ]);
        }

        $dailyTrafficRows = $this->fetchDailyTrafficRows($pdo);
        $trafficTotals = $this->fetchTrafficTotals($pdo);
        $recentVisitors = $this->fetchRecentVisitors($pdo);

        return view_page('Traffic Tracking', 'super-admin.traffic-tracking.index', [
            'traffic_table_ready' => true,
            'daily_traffic_rows' => $dailyTrafficRows,
            'traffic_totals' => $trafficTotals,
            'recent_visitors' => $recentVisitors,
        ]);
    }

    private function hasTrafficTable(PDO $pdo): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?'
            );
            $st->execute(['super_admin_traffic_logs']);
            return (int) $st->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchDailyTrafficRows(PDO $pdo): array
    {
        $hasActivityLogs = $this->hasTable($pdo, 'activity_logs');
        $st = $pdo->query(
            "SELECT
                DATE(created_at) AS visit_date,
                COUNT(*) AS total_visits,
                COUNT(DISTINCT CASE WHEN user_id IS NOT NULL THEN CONCAT('u:', user_id) ELSE CONCAT('ip:', ip_address) END) AS unique_visitors,
                SUM(CASE WHEN path = '/' THEN 1 ELSE 0 END) AS main_page_visits,
                ".($hasActivityLogs
                    ? "(SELECT COUNT(*)
                        FROM activity_logs al
                        WHERE DATE(al.created_at) = DATE(super_admin_traffic_logs.created_at)
                          AND al.module = 'auth'
                          AND al.action = 'login'
                          AND COALESCE(al.user_role, '') <> 'super_admin')"
                    : '0')." AS login_count
             FROM super_admin_traffic_logs
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY visit_date DESC"
        );
        if ($st === false) {
            return [];
        }

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{today_visits:int,today_unique_visitors:int,today_login_count:int,today_main_page_visits:int,last_7_days_visits:int,last_30_days_visits:int}
     */
    private function fetchTrafficTotals(PDO $pdo): array
    {
        $hasActivityLogs = $this->hasTable($pdo, 'activity_logs');
        $st = $pdo->query(
            "SELECT
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_visits,
                COUNT(DISTINCT CASE WHEN DATE(created_at) = CURDATE() THEN (CASE WHEN user_id IS NOT NULL THEN CONCAT('u:', user_id) ELSE CONCAT('ip:', ip_address) END) ELSE NULL END) AS today_unique_visitors,
                SUM(CASE WHEN DATE(created_at) = CURDATE() AND path = '/' THEN 1 ELSE 0 END) AS today_main_page_visits,
                ".($hasActivityLogs
                    ? "(SELECT COUNT(*)
                        FROM activity_logs al
                        WHERE DATE(al.created_at) = CURDATE()
                          AND al.module = 'auth'
                          AND al.action = 'login'
                          AND COALESCE(al.user_role, '') <> 'super_admin')"
                    : '0')." AS today_login_count,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last_7_days_visits,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS last_30_days_visits
             FROM super_admin_traffic_logs"
        );
        $row = $st !== false ? ($st->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        return [
            'today_visits' => (int) ($row['today_visits'] ?? 0),
            'today_unique_visitors' => (int) ($row['today_unique_visitors'] ?? 0),
            'today_login_count' => (int) ($row['today_login_count'] ?? 0),
            'today_main_page_visits' => (int) ($row['today_main_page_visits'] ?? 0),
            'last_7_days_visits' => (int) ($row['last_7_days_visits'] ?? 0),
            'last_30_days_visits' => (int) ($row['last_30_days_visits'] ?? 0),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fetchRecentVisitors(PDO $pdo): array
    {
        $st = $pdo->query(
            "SELECT
                l.created_at,
                l.path,
                l.ip_address,
                l.role,
                l.user_agent,
                u.name AS user_name,
                u.email AS user_email,
                t.name AS shop_name
             FROM super_admin_traffic_logs l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN tenants t ON t.id = l.tenant_id
             ORDER BY l.id DESC
             LIMIT 300"
        );
        if ($st === false) {
            return [];
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?'
            );
            $st->execute([$table]);
            return (int) $st->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
