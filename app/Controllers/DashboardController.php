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
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $periodEnd = date('Y-m-d 23:59:59');
        $start = date('Y-m-d 00:00:00', strtotime('-6 days'));

        $ordersByDay = $this->groupCount($pdo, $tenantId, $start, $periodEnd);
        $salesByDay = $this->groupSumSales($pdo, $tenantId, $start, $periodEnd);
        $expensesByDay = $this->groupSumExpenses($pdo, $tenantId, $start, $periodEnd);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime($start.' +'.$i.' days'));
            $days[] = ['key' => $d, 'label' => date('M d', strtotime($d))];
        }

        $periods = [
            'today' => $this->buildPeriod($pdo, $tenantId, $today, null, $periodEnd),
            'yesterday' => $this->buildPeriod($pdo, $tenantId, $yesterday, null, $periodEnd),
            'last_3_days' => $this->buildPeriod($pdo, $tenantId, null, date('Y-m-d 00:00:00', strtotime('-2 days')), $periodEnd),
            'last_7_days' => $this->buildPeriod($pdo, $tenantId, null, date('Y-m-d 00:00:00', strtotime('-6 days')), $periodEnd),
            'last_30_days' => $this->buildPeriod($pdo, $tenantId, null, date('Y-m-d 00:00:00', strtotime('-29 days')), $periodEnd),
        ];

        $ordersToday = $this->countCompleted($pdo, $tenantId, $today);
        $damagesToday = $this->sumDamagedQuantity($pdo, $tenantId, $today);
        $ingCount = (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM ingredients WHERE tenant_id = ?', [$tenantId]);
        $prodCount = (int) $this->scalar($pdo, 'SELECT COUNT(*) FROM products WHERE tenant_id = ?', [$tenantId]);

        $chartOrders = [];
        $chartSales = [];
        $chartExpenses = [];
        $chartProfit = [];
        foreach ($days as $day) {
            $k = $day['key'];
            $chartOrders[] = (int) ($ordersByDay[$k] ?? 0);
            $chartSales[] = (float) ($salesByDay[$k] ?? 0);
            $chartExpenses[] = (float) ($expensesByDay[$k] ?? 0);
            $chartProfit[] = (float) (($salesByDay[$k] ?? 0) - ($expensesByDay[$k] ?? 0));
        }

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
                'orders_today' => $ordersToday,
                'damages_today' => $damagesToday,
                'ingredients_count' => $ingCount,
                'products_count' => $prodCount,
            ],
            'periods' => $periods,
            'chart' => [
                'labels' => array_column($days, 'label'),
                'orders' => $chartOrders,
                'sales' => $chartSales,
                'expenses' => $chartExpenses,
                'profit' => $chartProfit,
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
