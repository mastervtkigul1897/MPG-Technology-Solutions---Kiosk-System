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
use App\Controllers\Tenant\PosController;
use App\Controllers\Tenant\ProductController;
use App\Controllers\Tenant\ReceiptSettingsController;
use App\Controllers\Tenant\ReportController;
use App\Controllers\Tenant\StaffController;
use Closure;

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
                return redirect(url('/dashboard'));
            }
            if ($rule === 'auth' && ! $user) {
                return redirect(url('/login'));
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
                    if (Auth::canAccessModule($user, 'notifications') && $request->input('_source') === 'notifications') {
                        continue;
                    }

                    return new Response('Forbidden.', 403);
                }
                if (! Auth::canAccessModule($user, $mod)) {
                    return new Response('Forbidden.', 403);
                }
            }
            if ($rule === 'tenant.subscription') {
                if (! $user) {
                    continue;
                }
                if (($user['role'] ?? '') === 'super_admin') {
                    continue;
                }
                if (! Auth::isTenantSubscriptionExpired($user)) {
                    continue;
                }
                $path = $request->path;
                if ($path === '/subscription-ended' || $path === '/logout') {
                    continue;
                }

                return redirect(url('/subscription-ended'));
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
        $r(['GET'], '#^/login$#', AuthController::class.'::showLogin', 'login', ['guest']);
        $r(['POST'], '#^/login$#', AuthController::class.'::login', 'login.post', ['guest']);
        $r(['GET'], '#^/register$#', AuthController::class.'::showRegister', 'register', ['guest']);
        $r(['POST'], '#^/register$#', AuthController::class.'::register', 'register.post', ['guest']);
        $r(['POST'], '#^/logout$#', AuthController::class.'::logout', 'logout', ['auth']);

        $r(['GET'], '#^/subscription-ended$#', AuthController::class.'::subscriptionEnded', 'subscription-ended', ['auth']);

        $r(['GET'], '#^/dashboard$#', DashboardController::class.'::index', 'dashboard', ['auth', 'tenant.active', 'tenant.subscription']);

        $r(['GET'], '#^/profile$#', ProfileController::class.'::edit', 'profile.edit', ['auth', 'tenant.active', 'tenant.subscription']);
        $r(['PATCH'], '#^/profile$#', ProfileController::class.'::update', 'profile.update', ['auth', 'tenant.active', 'tenant.subscription']);
        $r(['DELETE'], '#^/profile$#', ProfileController::class.'::destroy', 'profile.destroy', ['auth', 'tenant.active', 'tenant.subscription']);
        $r(['PUT'], '#^/password$#', ProfileController::class.'::updatePassword', 'password.update', ['auth', 'tenant.active', 'tenant.subscription']);

        $r(['GET'], '#^/super-admin/settings$#', SettingsController::class.'::edit', 'super-admin.settings.edit', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/settings$#', SettingsController::class.'::update', 'super-admin.settings.update', ['auth', 'role:super_admin']);

        $r(['GET'], '#^/super-admin/tenants$#', TenantController::class.'::index', 'super-admin.tenants.index', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants$#', TenantController::class.'::store', 'super-admin.tenants.store', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/toggle-active$#', TenantController::class.'::toggleActive', 'super-admin.tenants.toggle-active', ['auth', 'role:super_admin']);
        $r(['POST'], '#^/super-admin/tenants/(\d+)/reset-owner-password$#', TenantController::class.'::resetOwnerPassword', 'super-admin.tenants.reset-owner-password', ['auth', 'role:super_admin']);
        $r(['PUT', 'PATCH'], '#^/super-admin/tenants/(\d+)$#', TenantController::class.'::update', 'super-admin.tenants.update', ['auth', 'role:super_admin']);
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

        $ta = ['auth', 'tenant.active', 'tenant.subscription'];
        $tenantAdmin = ['auth', 'tenant.active', 'tenant.subscription', 'role:tenant_admin'];
        $r(['GET'], '#^/tenant/pos$#', PosController::class.'::index', 'tenant.pos.index', array_merge($ta, ['tenant.access:pos']));
        $r(['POST'], '#^/tenant/pos/checkout$#', PosController::class.'::checkout', 'tenant.pos.checkout', array_merge($ta, ['tenant.access:pos']));
        $r(['GET'], '#^/tenant/transactions$#', ReportController::class.'::transactions', 'tenant.transactions.index', array_merge($ta, ['tenant.access:transactions']));
        $r(['GET'], '#^/tenant/activity-logs$#', ActivityLogController::class.'::index', 'tenant.activity-logs.index', array_merge($ta, ['tenant.access:activity_logs']));
        $r(['DELETE'], '#^/tenant/activity-logs/(\d+)$#', ActivityLogController::class.'::destroy', 'tenant.activity-logs.destroy', $tenantAdmin);
        $r(['DELETE'], '#^/tenant/activity-logs$#', ActivityLogController::class.'::clear', 'tenant.activity-logs.clear', $tenantAdmin);
        $r(['GET'], '#^/tenant/notifications$#', IngredientController::class.'::notifications', 'tenant.notifications.index', array_merge($ta, ['tenant.access:notifications']));

        $r(['GET'], '#^/tenant/staff$#', StaffController::class.'::index', 'tenant.staff.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/staff$#', StaffController::class.'::store', 'tenant.staff.store', $tenantAdmin);
        $r(['PATCH'], '#^/tenant/staff/(\d+)/modules$#', StaffController::class.'::updateModules', 'tenant.staff.update-modules', $tenantAdmin);
        $r(['DELETE'], '#^/tenant/staff/(\d+)$#', StaffController::class.'::destroy', 'tenant.staff.destroy', $tenantAdmin);
        $r(['GET'], '#^/tenant/receipt-settings$#', ReceiptSettingsController::class.'::edit', 'tenant.receipt-settings.edit', $tenantAdmin);
        $r(['PATCH'], '#^/tenant/receipt-settings$#', ReceiptSettingsController::class.'::update', 'tenant.receipt-settings.update', $tenantAdmin);
        $r(['GET'], '#^/tenant/branches$#', TenantBranchController::class.'::index', 'tenant.branches.index', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches$#', TenantBranchController::class.'::store', 'tenant.branches.store', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/switch$#', TenantBranchController::class.'::switch', 'tenant.branches.switch', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/(\d+)/toggle-active$#', TenantBranchController::class.'::toggleActive', 'tenant.branches.toggle-active', $tenantAdmin);
        $r(['POST'], '#^/tenant/branches/(\d+)/set-main$#', TenantBranchController::class.'::setMain', 'tenant.branches.set-main', $tenantAdmin);

        $r(['GET'], '#^/tenant/ingredients$#', IngredientController::class.'::index', 'tenant.ingredients.index', array_merge($ta, ['tenant.access:ingredients']));
        $r(['POST'], '#^/tenant/ingredients$#', IngredientController::class.'::store', 'tenant.ingredients.store', array_merge($ta, ['tenant.access:ingredients']));
        $r(['PUT'], '#^/tenant/ingredients/(\d+)$#', IngredientController::class.'::update', 'tenant.ingredients.update', array_merge($ta, ['tenant.access:ingredient_update']));
        $r(['DELETE'], '#^/tenant/ingredients/(\d+)$#', IngredientController::class.'::destroy', 'tenant.ingredients.destroy', array_merge($ta, ['tenant.access:ingredients']));

        $r(['GET'], '#^/tenant/expenses$#', ExpenseController::class.'::index', 'tenant.expenses.index', array_merge($ta, ['tenant.access:expenses']));
        $r(['POST'], '#^/tenant/expenses$#', ExpenseController::class.'::store', 'tenant.expenses.store', array_merge($ta, ['tenant.access:expenses']));
        $r(['DELETE'], '#^/tenant/expenses/(\d+)$#', ExpenseController::class.'::destroy', 'tenant.expenses.destroy', array_merge($ta, ['tenant.access:expenses']));

        $r(['GET'], '#^/tenant/damaged-items$#', \App\Controllers\Tenant\DamagedItemController::class.'::index', 'tenant.damaged-items.index', array_merge($ta, ['tenant.access:damaged_items']));
        $r(['POST'], '#^/tenant/damaged-items$#', \App\Controllers\Tenant\DamagedItemController::class.'::store', 'tenant.damaged-items.store', array_merge($ta, ['tenant.access:damaged_items']));
        $r(['PUT'], '#^/tenant/damaged-items/(\d+)$#', \App\Controllers\Tenant\DamagedItemController::class.'::update', 'tenant.damaged-items.update', array_merge($ta, ['tenant.access:damaged_items']));
        $r(['DELETE'], '#^/tenant/damaged-items/(\d+)$#', \App\Controllers\Tenant\DamagedItemController::class.'::destroy', 'tenant.damaged-items.destroy', array_merge($ta, ['tenant.access:damaged_items']));

        $r(['GET'], '#^/tenant/products$#', ProductController::class.'::index', 'tenant.products.index', array_merge($ta, ['tenant.access:products']));
        $r(['POST'], '#^/tenant/products$#', ProductController::class.'::store', 'tenant.products.store', array_merge($ta, ['tenant.access:products']));
        $r(['PUT'], '#^/tenant/products/(\d+)$#', ProductController::class.'::update', 'tenant.products.update', array_merge($ta, ['tenant.access:products']));
        $r(['DELETE'], '#^/tenant/products/(\d+)$#', ProductController::class.'::destroy', 'tenant.products.destroy', array_merge($ta, ['tenant.access:products']));
        $r(['PATCH'], '#^/tenant/products/(\d+)/toggle-status$#', ProductController::class.'::toggleStatus', 'tenant.products.toggle-status', array_merge($ta, ['tenant.access:products']));

        $r(['GET'], '#^/tenant/reports$#', ReportController::class.'::index', 'tenant.reports.index', array_merge($ta, ['tenant.access:reports']));
        $r(['GET'], '#^/tenant/transactions/(\d+)/receipt$#', ReportController::class.'::receipt', 'tenant.transactions.receipt', array_merge($ta, ['tenant.access:transactions']));
        $r(['DELETE'], '#^/tenant/transactions/(\d+)$#', ReportController::class.'::destroyTransaction', 'tenant.transactions.destroy', $tenantAdmin);
    }
}
