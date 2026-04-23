<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ProfileController;
use App\Controllers\SuperAdmin\SettingsController;
use App\Controllers\SuperAdmin\BranchController as SuperAdminBranchController;
use App\Controllers\SuperAdmin\TenantBackupController;
use App\Controllers\SuperAdmin\TenantController;
use App\Controllers\Tenant\ActivityLogController;
use App\Controllers\Tenant\BranchController as TenantBranchController;
use App\Controllers\Tenant\ExpenseController;
use App\Controllers\Tenant\IngredientController;
use App\Controllers\Tenant\LaundryController;
use App\Controllers\Tenant\PosController;
use App\Controllers\Tenant\ReceiptSettingsController;
use App\Controllers\Tenant\ReportController;
use App\Controllers\Tenant\StaffController;
use Closure;
use PDO;

final class Router
{
    /** @var list<array{methods:string[],pattern:string,handler:Closure|string,name:string,middleware:array}> */
    private array $routes = [];

    public function __construct()
    {
        $this->registerRoutes();
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if (! in_array($request->method, $route['methods'], true)) {
                continue;
            }
            if (! preg_match($route['pattern'], $request->path, $m)) {
                continue;
            }
            array_shift($m);

            return $this->runRoute($route, $request, $m);
        }

        return new Response(view('errors.404'), 404);
    }

    private function runRoute(array $route, Request $request, array $params): Response
    {
        App::$routeName = $route['name'];
        $mw = $route['middleware'];
        $early = $this->applyMiddleware($request, $mw);
        if ($early instanceof Response) {
            return $early;
        }

        $handler = $route['handler'];
        if ($handler instanceof Closure) {
            $result = $handler($request, ...$params);
        } else {
            [$class, $method] = explode('::', $handler);
            $controller = new $class();
            $result = $controller->{$method}($request, ...$params);
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_string($result)) {
            return new Response($result, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return new Response('', 204);
    }

    private function applyMiddleware(Request $request, array $mw): ?Response
    {
        $user = Auth::user();
        foreach ($mw as $rule) {
            if ($rule === 'guest' && $user) {
                if (($user['role'] ?? '') !== 'super_admin' && empty($user['email_verified_at'])) {
                    return redirect(url('/email/verification-notice'));
                }

                return redirect(url('/dashboard'));
            }
            if ($rule === 'auth' && ! $user) {
                return redirect(url('/login'));
            }
            if ($rule === 'auth' && $user && ($user['role'] ?? '') !== 'super_admin' && empty($user['email_verified_at'])) {
                $allowedPaths = [
                    '/email/verification-notice',
                    '/email/verification-notification',
                    '/verify-email',
                    '/logout',
                ];
                if (! in_array($request->path, $allowedPaths, true)) {
                    return redirect(url('/email/verification-notice'));
                }
            }
            if ($rule === 'tenant.active' && $user && ! empty($user['tenant_id'])) {
                $path = $request->path;
                if ($path !== '/subscription-ended' && $path !== '/logout') {
                    $pdo = App::db();
                    $st = $pdo->prepare('SELECT is_active FROM tenants WHERE id = ? LIMIT 1');
                    $st->execute([(int) $user['tenant_id']]);
                    $t = $st->fetch();
                    if ($t && ! (bool) $t['is_active']) {
                        return redirect(url('/subscription-ended'));
                    }
                }
            }
            if (str_starts_with($rule, 'role:')) {
                $roles = explode(',', substr($rule, 5));
                if (! $user || ! in_array($user['role'], $roles, true)) {
                    return new Response('Forbidden.', 403);
                }
            }
            if (str_starts_with($rule, 'tenant.access:')) {
                $mod = substr($rule, strlen('tenant.access:'));
                if (! $user) {
                    return redirect(url('/login'));
                }
                $role = $user['role'] ?? '';
                if ($role !== 'tenant_admin' && $role !== 'cashier') {
                    return new Response('Forbidden.', 403);
                }
                if (empty($user['tenant_id'])) {
                    return new Response('Forbidden.', 403);
                }
                if ($mod === 'ingredient_update') {
                    if ($role === 'tenant_admin') {
                        continue;
                    }
                    if (Auth::canAccessModule($user, 'ingredients')) {
                        continue;
                    }
                    return new Response('Forbidden.', 403);
                }
                if (! Auth::canAccessModule($user, $mod)) {
                    return new Response('Forbidden.', 403);
                }
            }
            if ($rule === 'tenant.subscription') {
                // Subscription expiry downgrades tenants to limited Free access instead of locking the account.
                continue;
            }
            if ($rule === 'tenant.free.restricted') {
                // Free Mode restrictions are applied inside each module so Premium-tagged
                // pages remain visible and browseable.
                continue;
            }
            if ($rule === 'auth' && $user && ($user['role'] ?? '') === 'cashier') {
                if (! Auth::isCashierWithinFreeLimit($user)) {
                    session_flash('errors', ['Free Mode allows 1 staff account plus the store owner. This account is restricted.']);
                    return redirect(url('/tenant/plans'));
                }
                $staffType = strtolower(trim((string) ($user['staff_type'] ?? 'full_time')));
                if (in_array($staffType, ['utility', 'driver'], true)) {
                    $allowedPaths = [
                        '/dashboard',
                        '/dashboard/time-in',
                        '/dashboard/time-out',
                        '/logout',
                        '/subscription-ended',
                        '/tenant/plans',
                    ];
                    if (! in_array($request->path, $allowedPaths, true)) {
                        return redirect(url('/dashboard'));
                    }
                }
            }
            if ($rule === 'tenant.clock_in') {
                if (! $user || ($user['role'] ?? '') === 'super_admin') {
                    continue;
                }
                if (($user['role'] ?? '') === 'tenant_admin') {
                    continue;
                }
                $tenantId = (int) ($user['tenant_id'] ?? 0);
                if ($tenantId < 1) {
                    continue;
                }
                $path = $request->path;
                $clockInAllowPaths = [
                    '/dashboard',
                    '/dashboard/time-in',
                    '/dashboard/time-out',
                    '/logout',
                    '/subscription-ended',
                    '/tenant/plans',
                ];
                if (in_array($path, $clockInAllowPaths, true)) {
                    continue;
                }
                try {
                    $pdo = App::db();
                    LaundrySchema::ensure($pdo);
                    $today = date('Y-m-d');
                    $uid = (int) ($user['id'] ?? 0);
                    $st = $pdo->prepare(
                        'SELECT id FROM laundry_time_logs
                         WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = ? AND clock_out_at IS NULL
                         LIMIT 1'
                    );
                    $st->execute([$tenantId, $uid, $today]);
                    if ($st->fetch(PDO::FETCH_ASSOC) !== false) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }
                session_flash('swal_clock_in_required', true);

                return redirect(url('/dashboard'));
            }
        }

        return null;
    }

    private function registerRoutes(): void
    {
        $r = function (array $methods, string $pattern, string $handler, string $name, array $mw = []) {
            $this->routes[] = [
                'methods' => $methods,
                'pattern' => $pattern,
                'handler' => $handler,
                'name' => $name,
                'middleware' => $mw,
            ];
        };

        $r(['GET'], '#^/$#', HomeController::class.'::welcome', 'welcome', ['guest']);
        $r(['GET'], '#^/install-app$#', HomeController::class.'::installApp', 'install.app', ['guest']);
        $r(['GET'], '#^/demo-video$#', HomeController::class.'::demoVideo', 'demo.video', ['guest']);
        $r(['GET'], '#^/pricing$#', HomeController::class.'::pricing', 'pricing', ['guest']);
        $r(['GET'], '#^/login$#', AuthController::class.'::showLogin', 'login', ['guest']);
        $r(['POST'], '#^/login$#', AuthController::class.'::login', 'login.post', ['guest']);
        $r(['GET'], '#^/forgot-password$#', AuthController::class.'::showForgotPassword', 'password.request', ['guest']);
        $r(['POST'], '#^/forgot-password$#', AuthController::class.'::sendPasswordResetLink', 'password.email', ['guest']);
        $r(['GET'], '#^/reset-password$#', AuthController::class.'::showResetPassword', 'password.reset', ['guest']);
        $r(['POST'], '#^/reset-password$#', AuthController::class.'::resetPassword', 'password.store', ['guest']);
        $r(['GET'], '#^/register$#', AuthController::class.'::showRegister', 'register', ['guest']);
        $r(['POST'], '#^/register$#', AuthController::class.'::register', 'register.post', ['guest']);
        $r(['GET'], '#^/email/verification-notice$#', AuthController::class.'::showVerificationNotice', 'verification.notice', ['auth']);
        $r(['POST'], '#^/email/verification-notification$#', AuthController::class.'::resendVerification', 'verification.resend', ['auth']);
        $r(['GET'], '#^/verify-email$#', AuthController::class.'::verifyEmail', 'verification.verify');
        $r(['POST'], '#^/logout$#', AuthController::class.'::logout', 'logout', ['auth']);

        $r(['GET'], '#^/subscription-ended$#', AuthController::class.'::subscriptionEnded', 'subscription-ended', ['auth']);

        $r(['GET'], '#^/dashboard$#', DashboardController::class.'::index', 'dashboard', ['auth', 'tenant.active', 'tenant.subscription']);
        $r(['POST'], '#^/dashboard/time-in$#', DashboardController::class.'::timeIn', 'dashboard.time-in', ['auth', 'tenant.active', 'tenant.subscription']);
        $r(['POST'], '#^/dashboard/time-out$#', DashboardController::class.'::timeOut', 'dashboard.time-out', ['auth', 'tenant.active', 'tenant.subscription']);

        $r(['GET'], '#^/profile$#', ProfileController::class.'::edit', 'profile.edit', ['auth', 'tenant.active', 'tenant.subscription', 'tenant.clock_in']);
        $r(['PATCH'], '#^/profile$#', ProfileController::class.'::update', 'profile.update', ['auth', 'tenant.active', 'tenant.subscription', 'tenant.clock_in']);
        $r(['DELETE'], '#^/profile$#', ProfileController::class.'::destroy', 'profile.destroy', ['auth', 'tenant.active', 'tenant.subscription', 'tenant.clock_in']);
        $r(['PUT'], '#^/password$#', ProfileController::class.'::updatePassword', 'password.update', ['auth', 'tenant.active', 'tenant.subscription', 'tenant.clock_in']);

        $r(['GET'], '#^/super-admin/settings$#', SettingsController::class.'::edit', 'super-admin.settings.edit', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/settings$#', SettingsController::class.'::update', 'super-admin.settings.update', ['auth', 'role:super_admin']);

        $r(['GET'], '#^/super-admin/tenants$#', TenantController::class.'::index', 'super-admin.tenants.index', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants$#', TenantController::class.'::store', 'super-admin.tenants.store', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/toggle-active$#', TenantController::class.'::toggleActive', 'super-admin.tenants.toggle-active', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/reset-owner-password$#', TenantController::class.'::resetOwnerPassword', 'super-admin.tenants.reset-owner-password', ['auth', 'role:super_admin']);
        $r(['PUT', 'PATCH'], '#^/super-admin/tenants/(\d+)$#', TenantController::class.'::update', 'super-admin.tenants.update', ['auth', 'role:super_admin']);
        $r(['DELETE'], '#^/super-admin/tenants/(\d+)$#', TenantController::class.'::destroy', 'super-admin.tenants.destroy', ['auth', 'role:super_admin']);
        $r(['GET'], '#^/super-admin/tenants/(\d+)/branches$#', SuperAdminBranchController::class.'::index', 'super-admin.tenants.branches.index', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/branches$#', SuperAdminBranchController::class.'::store', 'super-admin.tenants.branches.store', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/branches/limit$#', SuperAdminBranchController::class.'::updateLimit', 'super-admin.tenants.branches.limit', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/branches/(\d+)/toggle-active$#', SuperAdminBranchController::class.'::toggleActive', 'super-admin.tenants.branches.toggle-active', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/branches/(\d+)/set-main$#', SuperAdminBranchController::class.'::setMain', 'super-admin.tenants.branches.set-main', ['auth', 'role:super_admin']);
        $r(['GET'], '#^/super-admin/tenants/(\d+)/backups$#', TenantBackupController::class.'::index', 'super-admin.tenants.backups.index', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/backups$#', TenantBackupController::class.'::store', 'super-admin.tenants.backups.store', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/backups/(\d+)/restore$#', TenantBackupController::class.'::restore', 'super-admin.tenants.backups.restore', ['auth', 'role:super_admin']);
        $r(['GET'], '#^/super-admin/backups/runner$#', TenantBackupController::class.'::runner', 'super-admin.backups.runner', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/backups/runner/force$#', TenantBackupController::class.'::runnerForce', 'super-admin.backups.runner.force', ['auth', 'role:super_admin']);
        $r(['GET'], '#^/super-admin/backups/runner/check$#', TenantBackupController::class.'::runnerCheck', 'super-admin.backups.runner.check', ['auth', 'role:super_admin']);

        $ta = ['auth', 'tenant.active', 'tenant.subscription', 'tenant.free.restricted', 'tenant.clock_in'];
        $tenantAdmin = ['auth', 'tenant.active', 'tenant.subscription', 'tenant.free.restricted', 'role:tenant_admin', 'tenant.clock_in'];
        $r(['GET'], '#^/tenant/plans$#', HomeController::class.'::tenantPlans', 'tenant.plans', $ta);
        $r(['GET'], '#^/tenant/pos$#', PosController::class.'::index', 'tenant.pos.index', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/pos/checkout$#', PosController::class.'::checkout', 'tenant.pos.checkout', array_merge($ta, ['tenant.access:pos']));
        $r(['GET'], '#^/tenant/pos/pending$#', PosController::class.'::pendingIndex', 'tenant.pos.pending.index', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/pos/pending$#', PosController::class.'::storePending', 'tenant.pos.pending.store', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/pos/pending/(\d+)/pay$#', PosController::class.'::payPending', 'tenant.pos.pending.pay', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/pos/receipt-escpos$#', PosController::class.'::receiptEscpos', 'tenant.pos.receipt-escpos', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/pos/receipt-print-network$#', PosController::class.'::receiptPrintNetwork', 'tenant.pos.receipt-print-network', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/transactions/receipt-escpos$#', PosController::class.'::receiptEscpos', 'tenant.transactions.receipt-escpos', array_merge($ta, ['tenant.access:transactions']));
        $r(['POST'], '#^/tenant/transactions/receipt-print-network$#', PosController::class.'::receiptPrintNetwork', 'tenant.transactions.receipt-print-network', array_merge($ta, ['tenant.access:transactions']));
        $r(['GET'], '#^/tenant/transactions$#', ReportController::class.'::transactions', 'tenant.transactions.index', array_merge($ta, ['tenant.access:transactions']));
        $r(['GET'], '#^/tenant/transactions/(\d+)/edit-data$#', ReportController::class.'::editData', 'tenant.transactions.edit-data', array_merge($ta, ['tenant.access:transactions']));
        $r(['POST'], '#^/tenant/transactions/(\d+)/edit-items$#', ReportController::class.'::editItems', 'tenant.transactions.edit-items', array_merge($ta, ['tenant.access:transactions']));
        $r(['GET'], '#^/tenant/activity-logs$#', ActivityLogController::class.'::index', 'tenant.activity-logs.index', array_merge($ta, ['tenant.access:activity_logs']));
        $r(['DELETE'], '#^/tenant/activity-logs/(\d+)$#', ActivityLogController::class.'::destroy', 'tenant.activity-logs.destroy', $tenantAdmin);
        $r(['DELETE'], '#^/tenant/activity-logs$#', ActivityLogController::class.'::clear', 'tenant.activity-logs.clear', $tenantAdmin);
        $r(['GET'], '#^/tenant/staff$#', StaffController::class.'::index', 'tenant.staff.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/staff$#', StaffController::class.'::store', 'tenant.staff.store', $tenantAdmin);
        $r(['PATCH'], '#^/tenant/staff/(\d+)/modules$#', StaffController::class.'::updateModules', 'tenant.staff.update-modules', $tenantAdmin);
        $r(['PATCH'], '#^/tenant/staff/(\d+)/day-rate$#', StaffController::class.'::updateDayRate', 'tenant.staff.update-day-rate', $tenantAdmin);
        $r(['DELETE'], '#^/tenant/staff/(\d+)$#', StaffController::class.'::destroy', 'tenant.staff.destroy', $tenantAdmin);
        $r(['GET'], '#^/tenant/receipt-settings$#', ReceiptSettingsController::class.'::edit', 'tenant.receipt-settings.edit', $tenantAdmin);
        $r(['PATCH'], '#^/tenant/receipt-settings$#', ReceiptSettingsController::class.'::update', 'tenant.receipt-settings.update', $tenantAdmin);
        $r(['GET'], '#^/tenant/branches$#', TenantBranchController::class.'::index', 'tenant.branches.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches$#', TenantBranchController::class.'::store', 'tenant.branches.store', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/switch$#', TenantBranchController::class.'::switch', 'tenant.branches.switch', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/(\d+)/toggle-active$#', TenantBranchController::class.'::toggleActive', 'tenant.branches.toggle-active', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/(\d+)/set-main$#', TenantBranchController::class.'::setMain', 'tenant.branches.set-main', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/laundry-config$#', TenantBranchController::class.'::updateLaundryConfig', 'tenant.branches.laundry-config.update', $tenantAdmin);

        $r(['GET'], '#^/tenant/ingredients$#', IngredientController::class.'::index', 'tenant.ingredients.index', array_merge($ta, ['tenant.access:ingredients']));
        $r(['POST'], '#^/tenant/ingredients$#', IngredientController::class.'::store', 'tenant.ingredients.store', array_merge($ta, ['tenant.access:ingredients']));
        $r(['PUT'], '#^/tenant/ingredients/(\d+)$#', IngredientController::class.'::update', 'tenant.ingredients.update', array_merge($ta, ['tenant.access:ingredient_update']));
        $r(['DELETE'], '#^/tenant/ingredients/(\d+)$#', IngredientController::class.'::destroy', 'tenant.ingredients.destroy', array_merge($ta, ['tenant.access:ingredients']));

        $r(['GET'], '#^/tenant/expenses$#', ExpenseController::class.'::index', 'tenant.expenses.index', array_merge($ta, ['tenant.access:expenses']));
        $r(['POST'], '#^/tenant/expenses$#', ExpenseController::class.'::store', 'tenant.expenses.store', array_merge($ta, ['tenant.access:expenses']));
        $r(['PUT'], '#^/tenant/expenses/(\d+)$#', ExpenseController::class.'::update', 'tenant.expenses.update', array_merge($ta, ['tenant.access:expenses']));
        $r(['DELETE'], '#^/tenant/expenses/(\d+)$#', ExpenseController::class.'::destroy', 'tenant.expenses.destroy', array_merge($ta, ['tenant.access:expenses']));

        $r(['GET'], '#^/tenant/damaged-items$#', \App\Controllers\Tenant\DamagedItemController::class.'::index', 'tenant.damaged-items.index', array_merge($ta, ['tenant.access:damaged_items']));
        $r(['POST'], '#^/tenant/damaged-items$#', \App\Controllers\Tenant\DamagedItemController::class.'::store', 'tenant.damaged-items.store', array_merge($ta, ['tenant.access:damaged_items']));
        $r(['PUT'], '#^/tenant/damaged-items/(\d+)$#', \App\Controllers\Tenant\DamagedItemController::class.'::update', 'tenant.damaged-items.update', array_merge($ta, ['tenant.access:damaged_items']));
        $r(['DELETE'], '#^/tenant/damaged-items/(\d+)$#', \App\Controllers\Tenant\DamagedItemController::class.'::destroy', 'tenant.damaged-items.destroy', array_merge($ta, ['tenant.access:damaged_items']));

        $r(['GET'], '#^/tenant/laundry-sales$#', LaundryController::class.'::salesIndex', 'tenant.laundry-sales.index', array_merge($ta, ['tenant.access:pos']));
        $r(['GET'], '#^/tenant/staff-portal$#', LaundryController::class.'::staffPortalIndex', 'tenant.staff-portal.index', ['auth', 'tenant.active', 'tenant.subscription', 'tenant.access:pos']);
        $r(['POST'], '#^/tenant/laundry-sales$#', LaundryController::class.'::salesStore', 'tenant.laundry-sales.store', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/laundry-sales/(\d+)/advance$#', LaundryController::class.'::advanceTransactionStatus', 'tenant.laundry-sales.advance', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/laundry-sales/(\d+)/complete$#', LaundryController::class.'::completeTransaction', 'tenant.laundry-sales.complete', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/laundry-sales/(\d+)/pay$#', LaundryController::class.'::payTransaction', 'tenant.laundry-sales.pay', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/laundry-sales/(\d+)/void$#', LaundryController::class.'::voidTransaction', 'tenant.laundry-sales.void', $tenantAdmin);
        $r(['GET'], '#^/tenant/laundry-sales/(\d+)/detail$#', LaundryController::class.'::salesOrderDetail', 'tenant.laundry-sales.detail', array_merge($ta, ['tenant.access:pos']));
        $r(['GET'], '#^/tenant/laundry-inventory$#', LaundryController::class.'::inventoryIndex', 'tenant.laundry-inventory.index', array_merge($ta, ['tenant.access:ingredients']));
        $r(['POST'], '#^/tenant/laundry-inventory/items$#', LaundryController::class.'::inventoryStore', 'tenant.laundry-inventory.store', array_merge($ta, ['tenant.access:ingredients']));
        $r(['PUT'], '#^/tenant/laundry-inventory/items/(\d+)$#', LaundryController::class.'::inventoryUpdate', 'tenant.laundry-inventory.update', array_merge($ta, ['tenant.access:ingredients']));
        $r(['DELETE'], '#^/tenant/laundry-inventory/items/(\d+)$#', LaundryController::class.'::inventoryDestroy', 'tenant.laundry-inventory.destroy', array_merge($ta, ['tenant.access:ingredients']));
        $r(['POST'], '#^/tenant/laundry-inventory/purchases$#', LaundryController::class.'::inventoryPurchase', 'tenant.laundry-inventory.purchase', array_merge($ta, ['tenant.access:ingredients']));
        $r(['GET'], '#^/tenant/machines$#', LaundryController::class.'::machinesIndex', 'tenant.machines.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/machines$#', LaundryController::class.'::machineStore', 'tenant.machines.store', $tenantAdmin);
        $r(['PUT'], '#^/tenant/machines/(\d+)$#', LaundryController::class.'::machineUpdate', 'tenant.machines.update', $tenantAdmin);
        $r(['DELETE'], '#^/tenant/machines/(\d+)$#', LaundryController::class.'::machineDestroy', 'tenant.machines.destroy', $tenantAdmin);
        $r(['GET'], '#^/tenant/laundry-order-pricing$#', LaundryController::class.'::orderTypePricingIndex', 'tenant.laundry-order-pricing.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/laundry-order-pricing/types$#', LaundryController::class.'::orderTypeCreate', 'tenant.laundry-order-pricing.types.store', $tenantAdmin);
        $r(['POST'], '#^/tenant/laundry-order-pricing/types/(\d+)/update$#', LaundryController::class.'::orderTypeUpdate', 'tenant.laundry-order-pricing.types.update', $tenantAdmin);
        $r(['POST'], '#^/tenant/laundry-order-pricing/types/(\d+)/delete$#', LaundryController::class.'::orderTypeDestroy', 'tenant.laundry-order-pricing.types.destroy', $tenantAdmin);
        $r(['GET'], '#^/tenant/customers$#', LaundryController::class.'::customersIndex', 'tenant.customers.index', array_merge($ta, ['tenant.access:transactions']));
        $r(['POST'], '#^/tenant/customers$#', LaundryController::class.'::customersStore', 'tenant.customers.store', array_merge($ta, ['tenant.access:transactions']));
        $r(['PUT'], '#^/tenant/customers/(\d+)$#', LaundryController::class.'::customersUpdate', 'tenant.customers.update', array_merge($ta, ['tenant.access:transactions']));
        $r(['DELETE'], '#^/tenant/customers/(\d+)$#', LaundryController::class.'::customersDestroy', 'tenant.customers.destroy', array_merge($ta, ['tenant.access:transactions']));
        $r(['POST'], '#^/tenant/customers/(\d+)/rewards-adjust$#', LaundryController::class.'::customersAdjustRewards', 'tenant.customers.rewards.adjust', array_merge($ta, ['tenant.access:transactions']));
        $r(['GET'], '#^/tenant/redeem-rewards-config$#', LaundryController::class.'::redeemConfigIndex', 'tenant.redeem-config.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/redeem-rewards-config$#', LaundryController::class.'::redeemConfigUpdate', 'tenant.redeem-config.update', $tenantAdmin);
        $r(['POST'], '#^/tenant/redeem-rewards-config/redeem$#', LaundryController::class.'::redeemGift', 'tenant.redeem-config.redeem', $tenantAdmin);
        $r(['GET'], '#^/tenant/attendance$#', LaundryController::class.'::attendanceIndex', 'tenant.attendance.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/attendance/(\d+)$#', LaundryController::class.'::attendanceUpdate', 'tenant.attendance.update', $tenantAdmin);
        $r(['POST'], '#^/tenant/attendance/(\d+)/approve-ot$#', LaundryController::class.'::attendanceApproveOt', 'tenant.attendance.approve-ot', $tenantAdmin);
        $r(['GET'], '#^/tenant/payroll$#', LaundryController::class.'::payrollIndex', 'tenant.payroll.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/payroll$#', LaundryController::class.'::payrollStore', 'tenant.payroll.store', $tenantAdmin);

        $r(['GET'], '#^/tenant/reports$#', ReportController::class.'::index', 'tenant.reports.index', $tenantAdmin);
        $r(['GET'], '#^/tenant/reports/daily-outs$#', ReportController::class.'::dailyOuts', 'tenant.reports.daily-outs', $tenantAdmin);
        $r(['GET'], '#^/tenant/transactions/(\d+)/receipt$#', ReportController::class.'::receipt', 'tenant.transactions.receipt', array_merge($ta, ['tenant.access:transactions']));
        $r(['DELETE'], '#^/tenant/transactions/(\d+)$#', ReportController::class.'::destroyTransaction', 'tenant.transactions.destroy', $tenantAdmin);
    }
}
