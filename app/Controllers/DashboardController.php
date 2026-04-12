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
        $rangeStart = $today.' 00:00:00';
        $rangeEnd = $today.' 23:59:59';

        $salesToday = (float) $this->scalar(
            $pdo,
            "SELECT COALESCE(SUM(total_amount),0) FROM transactions WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?",
            [$tenantId, $rangeStart, $rangeEnd]
        );
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

        $freeMealsToday = [];
        $stFree = $pdo->prepare(
            "SELECT t.id, t.created_at
             FROM transactions t
             WHERE t.tenant_id = ? AND t.status = 'completed'
               AND LOWER(TRIM(COALESCE(t.payment_method,''))) = 'free'
               AND t.created_at BETWEEN ? AND ?
             ORDER BY t.created_at DESC"
        );
        $stFree->execute([$tenantId, $rangeStart, $rangeEnd]);
        foreach ($stFree->fetchAll(PDO::FETCH_ASSOC) as $tr) {
            $txId = (int) ($tr['id'] ?? 0);
            if ($txId < 1) {
                continue;
            }
            $sti = $pdo->prepare(
                'SELECT p.name, ti.quantity
                 FROM transaction_items ti
                 INNER JOIN products p ON p.id = ti.product_id AND p.tenant_id = ti.tenant_id
                 WHERE ti.tenant_id = ? AND ti.transaction_id = ?
                 ORDER BY ti.id ASC'
            );
            $sti->execute([$tenantId, $txId]);
            $items = [];
            foreach ($sti->fetchAll(PDO::FETCH_ASSOC) as $ir) {
                $items[] = [
                    'name' => (string) ($ir['name'] ?? ''),
                    'qty' => (float) ($ir['quantity'] ?? 0),
                ];
            }
            $freeMealsToday[] = [
                'id' => $txId,
                'created_at' => (string) ($tr['created_at'] ?? ''),
                'items' => $items,
            ];
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
            $todayCheck = date('Y-m-d');
            $daysLeft = (int) floor((strtotime($expDate.' 00:00:00') - strtotime($todayCheck.' 00:00:00')) / 86400);
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
            ],
            'free_meals_today' => $freeMealsToday,
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
}
