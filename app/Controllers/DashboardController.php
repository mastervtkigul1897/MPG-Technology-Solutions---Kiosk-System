<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect(url('/login'));
        }

        if ($user['role'] === 'super_admin') {
            $pdo = App::db();
            $tenants = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();

            return view_page('Dashboard', 'dashboard', [
                'is_super' => true,
                'stats' => [
                    'tenants_count' => $tenants,
                ],
                'periods' => [],
                'chart' => [
                    'labels' => [],
                    'orders' => [],
                    'sales' => [],
                    'profit' => [],
                ],
            ]);
        }

        $tid = $user['tenant_id'] ?? null;
        if (($user['role'] === 'tenant_admin' || $user['role'] === 'cashier')
            && ($tid === null || $tid === '' || (int) $tid === 0)) {
            Auth::logout();
            session_flash('errors', ['Your account is not assigned to a store. Contact the platform administrator.']);

            return redirect(url('/login'));
        }

        $tenantId = (int) $user['tenant_id'];
        $pdo = App::db();

        $today = date('Y-m-d');
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = $today;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = $today;
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }
        $rangeStart = $from.' 00:00:00';
        $rangeEnd = $to.' 23:59:59';

        $salesToday = (float) $this->scalar(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        // Payment totals today (one card per method on dashboard)
        $st = $pdo->prepare(
            "SELECT LOWER(TRIM(COALESCE(payment_method,''))) AS pm, COALESCE(SUM(total_amount),0) AS total
             FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
             GROUP BY pm"
        );
        $st->execute([$tenantId, $rangeStart, $rangeEnd]);
        $paymentsToday = [
            'cash' => 0.0,
            'card' => 0.0,
            'gcash' => 0.0,
            'paymaya' => 0.0,
            'online_banking' => 0.0,
            'free' => 0.0,
        ];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pm = (string) ($row['pm'] ?? '');
            if ($pm === '') {
                $pm = 'cash';
            }
            if (array_key_exists($pm, $paymentsToday)) {
                $paymentsToday[$pm] += (float) ($row['total'] ?? 0);
            }
        }
        $expensesToday = (float) $this->scalar(
            $pdo,
            "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
        $netSalesToday = $salesToday - $expensesToday;

        $warningDays = (int) App::config('subscription_warning_days', 7);
        $dashboardMaintenance = null;
        $dashboardSubscription = null;

        if (App::config('maintenance_mode', false)) {
            $mm = trim((string) App::config('maintenance_message', ''));
            if ($mm !== '') {
                $dashboardMaintenance = ['message' => $mm];
            }
        }

        $stExp = $pdo->prepare('SELECT license_expires_at FROM tenants WHERE id = ? LIMIT 1');
        $stExp->execute([$tenantId]);
        $trow = $stExp->fetch(PDO::FETCH_ASSOC);
        $expRaw = $trow['license_expires_at'] ?? null;
        if ($expRaw !== null && $expRaw !== '') {
            $expDate = date('Y-m-d', strtotime((string) $expRaw));
            $today = date('Y-m-d');
            $daysLeft = (int) floor((strtotime($expDate.' 00:00:00') - strtotime($today.' 00:00:00')) / 86400);
            if ($daysLeft >= 0 && $daysLeft <= $warningDays) {
                $dashboardSubscription = [
                    'expires_label' => date('M j, Y', strtotime($expDate)),
                    'days_left' => $daysLeft,
                ];
            }
        }

        return view_page('Dashboard', 'dashboard', [
            'is_super' => false,
            'stats' => [
                'sales_today' => $salesToday,
                'expenses_today' => $expensesToday,
                'net_sales_today' => $netSalesToday,
                'payments_today' => $paymentsToday,
                'range_from' => $from,
                'range_to' => $to,
            ],
            'periods' => [],
            'chart' => [
                'labels' => [],
                'orders' => [],
                'sales' => [],
                'expenses' => [],
                'profit' => [],
            ],
            'dashboard_maintenance' => $dashboardMaintenance,
            'dashboard_subscription' => $dashboardSubscription,
        ]);
    }

    private function scalar(PDO $pdo, string $sql, array $params): mixed
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchColumn();
    }

    private function countCompleted(PDO $pdo, int $tenantId, string $date): int
    {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND DATE(created_at) = ?"
        );
        $st->execute([$tenantId, $date]);

        return (int) $st->fetchColumn();
    }

    private function sumDamagedQuantity(PDO $pdo, int $tenantId, string $date): float
    {
        try {
            $st = $pdo->prepare(
                "SELECT COALESCE(SUM(quantity),0) FROM damaged_items
                 WHERE tenant_id = ? AND DATE(created_at) = ?"
            );
            $st->execute([$tenantId, $date]);

            return round((float) $st->fetchColumn(), 2);
        } catch (\PDOException $e) {
            // If the table doesn't exist yet (fresh deployment), don't break the dashboard.
            return 0.0;
        }
    }

    /** @return array<string,int> */
    private function groupCount(PDO $pdo, int $tenantId, string $start, string $end): array
    {
        $st = $pdo->prepare(
            "SELECT DATE(created_at) as d, COUNT(*) as c FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
             GROUP BY d"
        );
        $st->execute([$tenantId, $start, $end]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['d']] = (int) $row['c'];
        }

        return $out;
    }

    /** @return array<string,float> */
    private function groupSumSales(PDO $pdo, int $tenantId, string $start, string $end): array
    {
        $st = $pdo->prepare(
            "SELECT DATE(created_at) as d, COALESCE(SUM(total_amount),0) as s FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
             GROUP BY d"
        );
        $st->execute([$tenantId, $start, $end]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['d']] = (float) $row['s'];
        }

        return $out;
    }

    /** @return array<string,float> */
    private function groupSumExpenses(PDO $pdo, int $tenantId, string $start, string $end): array
    {
        $st = $pdo->prepare(
            "SELECT DATE(created_at) as d, COALESCE(SUM(amount),0) as s FROM expenses
             WHERE tenant_id = ? AND type = 'manual' AND created_at BETWEEN ? AND ?
             GROUP BY d"
        );
        $st->execute([$tenantId, $start, $end]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(string) $row['d']] = (float) $row['s'];
        }

        return $out;
    }

    /** @return array{orders:int,sales:float,expenses:float,profit:float} */
    private function buildPeriod(PDO $pdo, int $tenantId, ?string $date, ?string $rangeStart, string $nowEnd): array
    {
        $tSql = 'SELECT COUNT(*), COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = ?';
        $eSql = 'SELECT COALESCE(SUM(amount),0) FROM expenses WHERE tenant_id = ? AND type = ?';
        $paramsT = [$tenantId, 'completed'];
        $paramsE = [$tenantId, 'manual'];

        if ($date) {
            $tSql .= ' AND DATE(created_at) = ?';
            $paramsT[] = $date;
            $eSql .= ' AND DATE(created_at) = ?';
            $paramsE[] = $date;
        } elseif ($rangeStart) {
            $tSql .= ' AND created_at BETWEEN ? AND ?';
            $paramsT[] = $rangeStart;
            $paramsT[] = $nowEnd;
            $eSql .= ' AND created_at BETWEEN ? AND ?';
            $paramsE[] = $rangeStart;
            $paramsE[] = $nowEnd;
        }

        $st = $pdo->prepare($tSql);
        $st->execute($paramsT);
        $row = $st->fetch(PDO::FETCH_NUM);
        $orders = (int) ($row[0] ?? 0);
        $sales = (float) ($row[1] ?? 0);

        $st = $pdo->prepare($eSql);
        $st->execute($paramsE);
        $expenses = (float) $st->fetchColumn();

        return [
            'orders' => $orders,
            'sales' => $sales,
            'expenses' => $expenses,
            'profit' => $sales - $expenses,
        ];
    }
}
