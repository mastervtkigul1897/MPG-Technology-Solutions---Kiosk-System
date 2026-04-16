<?php
/** @var string $title */
/** @var string $content */
$u = $user ?? auth_user();
$appName = \App\Core\App::config('name') ?? 'Kiosk System';
$brandSuffix = trim((string) (\App\Core\App::config('brand_suffix') ?? 'MPG Technology Solutions'));
$docTitleParts = [];
if (isset($title) && (string) $title !== '') {
    $docTitleParts[] = (string) $title;
}
$docTitleParts[] = $appName;
if ($brandSuffix !== '') {
    $docTitleParts[] = $brandSuffix;
}
$documentTitle = implode(' — ', $docTitleParts);
$brandLogoPath = url('images/branding/mpg-kis-logo.png');
$brandDisplayName = 'MPG KIS - Kiosk & Inventory System';

$branchSwitcherRows = [];
$currentBranchIsMain = false;
if (($u['role'] ?? '') === 'tenant_admin' && ! empty($u['tenant_id'])) {
    try {
        $svc = new \App\Services\BranchService();
        $branchSwitcherRows = $svc->listBranches((int) $u['tenant_id']);
        foreach ($branchSwitcherRows as $b) {
            if ((int) ($b['id'] ?? 0) === (int) ($u['tenant_id'] ?? 0)) {
                $currentBranchIsMain = (bool) ($b['is_main_branch'] ?? false);
                break;
            }
        }
    } catch (\Throwable) {
        $branchSwitcherRows = [];
        $currentBranchIsMain = false;
    }
}

$navPremiumTrialHints = (($u['role'] ?? '') !== 'super_admin')
    && ! empty($u['tenant_id'])
    && \App\Core\Auth::isTenantFreeTrial($u);
$tenantPlansUrl = url('/tenant/plans');
$premiumNavBadge = static function (bool $isPremiumItem) use ($navPremiumTrialHints): string {
    if (! $navPremiumTrialHints || ! $isPremiumItem) {
        return '';
    }

    return ' <span class="badge rounded-pill bg-warning text-dark border border-dark border-opacity-10 ms-1" style="font-size:0.65rem;font-weight:700;vertical-align:middle;">Premium</span>';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($documentTitle) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e($brandLogoPath) ?>">
    <link rel="shortcut icon" href="<?= e($brandLogoPath) ?>">
    <link rel="apple-touch-icon" href="<?= e($brandLogoPath) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="<?= e(url('css/app-theme.css')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="app-theme bg-light d-flex flex-column min-vh-100">
<div class="app-shell d-flex flex-grow-1 min-vh-100">
    <aside class="desktop-sidebar bg-dark text-white p-3">
        <div class="d-flex align-items-center gap-2 mb-3">
            <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="36" height="36" class="rounded-circle border border-secondary-subtle">
            <h5 class="mb-0"><?= e($brandDisplayName) ?></h5>
        </div>
        <div class="small text-secondary mb-3"><?= e(strtoupper((string) ($u['role'] ?? ''))) ?></div>
        <nav class="nav flex-column gap-1">
            <a class="nav-link text-white <?= route_is('dashboard') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/dashboard')) ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
            <?php if (($u['role'] ?? null) === 'super_admin'): ?>
                <a class="nav-link text-white <?= route_is('super-admin.tenants.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/tenants')) ?>"><i class="fa-solid fa-building"></i><span>Tenants</span></a>
                <a class="nav-link text-white <?= route_is('super-admin.backups.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/backups/runner')) ?>"><i class="fa-solid fa-database"></i><span>Backup Runner</span></a>
                <a class="nav-link text-white <?= route_is('super-admin.settings.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/settings')) ?>"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
            <?php else: ?>
                <?php if (user_can_module('pos')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.pos.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/pos')) ?>"><i class="fa-solid fa-cart-plus"></i><span>Create Transaction</span></a>
                <?php endif; ?>
                <?php if (user_can_module('transactions')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.transactions.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/transactions')) ?>"><i class="fa-solid fa-receipt"></i><span>Transactions</span></a>
                <?php endif; ?>
                <?php if (user_can_module('ingredients')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.ingredients.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/ingredients')) ?>"><i class="fa-solid fa-boxes-stacked"></i><span>Inventory Items</span></a>
                <?php endif; ?>
                <?php if (user_can_module('products')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.products.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/products')) ?>"><i class="fa-solid fa-tags"></i><span>Products</span></a>
                <?php endif; ?>
                <?php if (user_can_module('expenses')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.expenses.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/expenses')) ?>"><i class="fa-solid fa-money-bill-wave"></i><span>Expenses<?= $premiumNavBadge(true) ?></span></a>
                <?php endif; ?>
                <?php if (user_can_module('damaged_items')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.damaged-items.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/damaged-items')) ?>"><i class="fa-solid fa-triangle-exclamation"></i><span>Damaged Items<?= $premiumNavBadge(true) ?></span></a>
                <?php endif; ?>
                <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                    <a class="nav-link text-white <?= route_is('tenant.reports.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/reports')) ?>"><i class="fa-solid fa-chart-line"></i><span>Reports</span></a>
                <?php endif; ?>
                <?php if (user_can_module('notifications')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.notifications.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/notifications')) ?>"><i class="fa-solid fa-bell"></i><span>Notifications<?= $premiumNavBadge(true) ?></span></a>
                <?php endif; ?>
                <hr class="border-secondary my-2 opacity-50">
                <?php if (user_can_module('activity_logs')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.activity-logs.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/activity-logs')) ?>"><i class="fa-solid fa-clock-rotate-left"></i><span>Activity Log</span></a>
                <?php endif; ?>
                <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                    <a class="nav-link text-white <?= route_is('tenant.staff.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/staff')) ?>"><i class="fa-solid fa-users"></i><span>Staff<?= $premiumNavBadge(true) ?></span></a>
                    <a class="nav-link text-white <?= route_is('tenant.receipt-settings.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.receipt-settings.edit')) ?>"><i class="fa-solid fa-file-lines"></i><span>Receipt Config<?= $premiumNavBadge(true) ?></span></a>
                    <?php if ($currentBranchIsMain): ?>
                        <a class="nav-link text-white <?= route_is('tenant.branches.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/branches')) ?>"><i class="fa-solid fa-code-branch"></i><span>Branches<?= $premiumNavBadge(true) ?></span></a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </aside>

    <aside class="offcanvas offcanvas-start app-sidebar-mobile text-white p-3" tabindex="-1" id="appSidebarMobile" style="width: 260px;">
        <div class="offcanvas-header px-0 pt-0">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appSidebarMobile"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <div class="d-flex align-items-center gap-2 mb-3">
                <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="34" height="34" class="rounded-circle border border-secondary-subtle">
                <h5 class="mb-0"><?= e($brandDisplayName) ?></h5>
            </div>
            <div class="small text-secondary mb-3"><?= e(strtoupper((string) ($u['role'] ?? ''))) ?></div>
            <nav class="nav flex-column gap-1">
                <a class="nav-link text-white" href="<?= e(url('/dashboard')) ?>"><i class="fa-solid fa-house me-2"></i>Dashboard</a>
                <?php if (($u['role'] ?? null) === 'super_admin'): ?>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/tenants')) ?>"><i class="fa-solid fa-building me-2"></i>Tenants</a>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/backups/runner')) ?>"><i class="fa-solid fa-database me-2"></i>Backup Runner</a>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/settings')) ?>"><i class="fa-solid fa-gear me-2"></i>Settings</a>
                <?php else: ?>
                    <?php if (user_can_module('pos')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/pos')) ?>"><i class="fa-solid fa-cart-plus me-2"></i>Create Transaction</a>
                    <?php endif; ?>
                    <?php if (user_can_module('transactions')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/transactions')) ?>"><i class="fa-solid fa-receipt me-2"></i>Transactions</a>
                    <?php endif; ?>
                    <?php if (user_can_module('ingredients')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/ingredients')) ?>"><i class="fa-solid fa-boxes-stacked me-2"></i>Inventory Items</a>
                    <?php endif; ?>
                    <?php if (user_can_module('products')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/products')) ?>"><i class="fa-solid fa-tags me-2"></i>Products</a>
                    <?php endif; ?>
                    <?php if (user_can_module('expenses')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/expenses')) ?>"><i class="fa-solid fa-money-bill-wave me-2"></i>Expenses<?= $premiumNavBadge(true) ?></a>
                    <?php endif; ?>
                    <?php if (user_can_module('damaged_items')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/damaged-items')) ?>"><i class="fa-solid fa-triangle-exclamation me-2"></i>Damaged Items<?= $premiumNavBadge(true) ?></a>
                    <?php endif; ?>
                    <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/reports')) ?>"><i class="fa-solid fa-chart-line me-2"></i>Reports</a>
                    <?php endif; ?>
                    <?php if (user_can_module('notifications')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/notifications')) ?>"><i class="fa-solid fa-bell me-2"></i>Notifications<?= $premiumNavBadge(true) ?></a>
                    <?php endif; ?>
                    <hr class="border-secondary my-2 opacity-50">
                    <?php if (user_can_module('activity_logs')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/activity-logs')) ?>"><i class="fa-solid fa-clock-rotate-left me-2"></i>Activity Log</a>
                    <?php endif; ?>
                    <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/staff')) ?>"><i class="fa-solid fa-users me-2"></i>Staff<?= $premiumNavBadge(true) ?></a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.receipt-settings.edit')) ?>"><i class="fa-solid fa-file-lines me-2"></i>Receipt Config<?= $premiumNavBadge(true) ?></a>
                        <?php if ($currentBranchIsMain): ?>
                            <a class="nav-link text-white" href="<?= e(url('/tenant/branches')) ?>"><i class="fa-solid fa-code-branch me-2"></i>Branches<?= $premiumNavBadge(true) ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
            <?php if (($u['role'] ?? '') === 'tenant_admin' && $branchSwitcherRows !== []): ?>
                <div class="mt-auto pt-3 border-top border-secondary-subtle">
                    <form method="POST" action="<?= e(route('tenant.branches.switch')) ?>" id="branchSwitcherForm" class="m-0 vstack gap-1">
                        <?= csrf_field() ?>
                        <label for="activeBranchSelect" class="small text-muted text-nowrap">Branch:</label>
                        <select id="activeBranchSelect" name="branch_id" class="form-select form-select-sm" style="min-width: 180px;">
                            <?php foreach ($branchSwitcherRows as $b): ?>
                                <?php
                                $bid = (int) ($b['id'] ?? 0);
                                $isActive = (bool) ($b['is_active'] ?? false);
                                $isSelected = $bid === (int) ($u['tenant_id'] ?? 0);
                                $expRaw = trim((string) ($b['license_expires_at'] ?? ''));
                                $isExpired = false;
                                if ($expRaw !== '') {
                                    $expTs = strtotime($expRaw);
                                    $isExpired = $expTs !== false && date('Y-m-d', $expTs) < date('Y-m-d');
                                }
                                $label = (string) ($b['name'] ?? '');
                                if ((bool) ($b['is_main_branch'] ?? false)) {
                                    $label .= ' (Main)';
                                }
                                if (! $isActive) {
                                    $label .= ' [Closed]';
                                } elseif ($isExpired) {
                                    $label .= ' [Expired]';
                                }
                                ?>
                                <option
                                    value="<?= $bid ?>"
                                    <?= $isSelected ? 'selected' : '' ?>
                                    <?= $isActive ? '' : 'disabled' ?>
                                    data-expired="<?= $isExpired ? '1' : '0' ?>"
                                ><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <main class="app-main flex-grow-1 d-flex flex-column min-vh-100 min-w-0 p-3 p-md-4">
        <?php /* Load jQuery + DataTables + initServerDataTable before $content so inline scripts in views run after they exist */ ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?= e(url('js/receipt-print.js')) ?>"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
        <script>
        (() => {
            if (typeof Swal !== 'undefined' && !window.__swalAutoCloseWrapped) {
                const nativeFire = Swal.fire.bind(Swal);
                Swal.fire = function (options = {}, ...rest) {
                    const config = typeof options === 'object' && options !== null ? { ...options } : options;
                    if (typeof config === 'object' && !config.showCancelButton && config.timer == null) {
                        config.timer = 5000;
                        config.timerProgressBar = true;
                    }
                    return nativeFire(config, ...rest);
                };
                window.__swalAutoCloseWrapped = true;
            }
            window.initServerDataTable = (selector, options = {}) => {
                const el = $(selector);
                if (!el.length) return null;
                const { ajax: userAjax, printButton, ...rest } = options;
                const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                // Use GET for server-side DataTables so the request hits the same route as the page.
                // POST would 404 on routes registered as GET-only (e.g. /tenant/transactions), or hit the wrong
                // handler (e.g. POST /tenant/ingredients matches "store", not the table JSON). GET is read-only; no CSRF.
                let ajaxConfig = userAjax;
                if (typeof userAjax === 'string') {
                    ajaxConfig = {
                        url: userAjax,
                        type: 'GET',
                        data(d) {
                            /* no-op; DataTables fills d */
                        },
                    };
                } else if (typeof userAjax === 'object' && userAjax !== null) {
                    const method = (userAjax.type || 'GET').toUpperCase();
                    ajaxConfig = {
                        ...userAjax,
                        type: method,
                        data(d) {
                            if (method === 'POST') {
                                d._token = csrfToken();
                            }
                            if (typeof userAjax.data === 'function') {
                                userAjax.data(d);
                            } else if (userAjax.data && typeof userAjax.data === 'object') {
                                Object.assign(d, userAjax.data);
                            }
                        },
                    };
                }

                const defaultResponsive = {
                    details: {
                        type: 'column',
                        target: 0,
                        renderer: $.fn.dataTable.Responsive.renderer.tableAll({ tableClass: 'table table-sm' }),
                    },
                };
                const dtOpts = {
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    searchDelay: 300,
                    order: [[1, 'desc']],
                    language: { search: 'Search:', lengthMenu: 'Show _MENU_ rows', processing: 'Loading...' },
                    createdRow: (row) => { row.querySelectorAll('td').forEach((cell) => cell.classList.add('align-middle')); },
                    ...rest,
                    ajax: ajaxConfig,
                };
                if (rest.responsive === false) {
                    delete dtOpts.responsive;
                } else if (rest.responsive && typeof rest.responsive === 'object') {
                    dtOpts.responsive = { ...defaultResponsive, ...rest.responsive };
                } else {
                    dtOpts.responsive = defaultResponsive;
                }
                if (printButton) {
                    dtOpts.dom = rest.dom || 'Bfrtip';
                    if (!dtOpts.buttons) {
                        dtOpts.buttons = [
                            {
                                extend: 'print',
                                text: 'Print table',
                                className: 'btn btn-sm btn-outline-secondary mb-2',
                                title: document.title,
                            },
                        ];
                    }
                }
                return el.DataTable(dtOpts);
            };
        })();
        </script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.mpgApplyEmbeddedShellReceiptUi === 'function') {
                window.mpgApplyEmbeddedShellReceiptUi();
            }
        });
        </script>
        <div class="app-main-scroll flex-grow-1 d-flex flex-column min-h-0 min-w-0 w-100">
        <div class="app-top-toolbar d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
            <div class="mobile-sidebar-toggle">
                <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="30" height="30" class="rounded-circle border border-secondary-subtle">
                    <span class="fw-semibold small d-none d-sm-inline"><?= e($brandDisplayName) ?></span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <a href="<?= e(url('/profile')) ?>" class="btn btn-outline-secondary btn-sm" title="Profile" aria-label="Profile"><i class="fa-solid fa-user"></i></a>
                <form method="POST" action="<?= e(url('/logout')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Logout" aria-label="Logout"><i class="fa-solid fa-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
        <div class="modern-page-header">
            <h4 class="mb-1"><?= e($title ?? 'Dashboard') ?></h4>
            <div class="modern-page-note">Manage and monitor your kiosk operations.</div>
        </div>
        <?php require dirname(__DIR__).'/partials/alerts.php'; ?>
        <div class="app-content-area flex-grow-1 min-h-0 min-w-0 overflow-auto"><?= $content ?? '' ?></div>
        <?php
        $footerCreditClass = 'text-muted mt-auto pt-3 border-top';
        require dirname(__DIR__).'/partials/footer_credit.php';
        ?>
        </div>
        <script>
        (() => {
            const form = document.getElementById('branchSwitcherForm');
            const select = document.getElementById('activeBranchSelect');
            if (!form || !select) return;
            let previousValue = select.value;
            let reverting = false;

            const hasSelect2 = typeof window.jQuery !== 'undefined'
                && typeof window.jQuery.fn !== 'undefined'
                && typeof window.jQuery.fn.select2 === 'function';
            let $select = null;
            if (hasSelect2) {
                $select = window.jQuery(select);
                const offcanvas = document.getElementById('appSidebarMobile');
                $select.select2({
                    theme: 'bootstrap-5',
                    width: 'style',
                    minimumResultsForSearch: Infinity,
                    dropdownParent: window.jQuery(offcanvas || form),
                });
                $select.on('select2:open', () => {
                    document.body.classList.add('branch-select-open');
                });
                $select.on('select2:close', () => {
                    document.body.classList.remove('branch-select-open');
                });
            }

            const restorePrevious = () => {
                reverting = true;
                select.value = previousValue;
                if ($select) {
                    $select.val(previousValue).trigger('change.select2');
                }
                setTimeout(() => { reverting = false; }, 0);
            };

            const selectedBranchIsExpired = () => {
                const opt = select.options[select.selectedIndex];
                return (opt?.dataset?.expired || '0') === '1';
            };

            const showExpiredWarning = () => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Expired branch',
                        text: 'This branch subscription is expired. Please renew first.',
                        confirmButtonColor: '#f59e0b',
                    });
                } else {
                    alert('This branch subscription is expired. Please renew first.');
                }
            };

            const handleBranchSelection = () => {
                if (reverting) return;
                if (select.value === previousValue) return;
                if (selectedBranchIsExpired()) {
                    showExpiredWarning();
                    restorePrevious();
                    return;
                }
                previousValue = select.value;
                form.submit();
            };

            select.addEventListener('focus', () => {
                previousValue = select.value;
            });
            select.addEventListener('mousedown', () => {
                previousValue = select.value;
            });
            select.addEventListener('touchstart', () => {
                previousValue = select.value;
            }, { passive: true });
            select.addEventListener('change', handleBranchSelection);
            if ($select) {
                $select.on('change', handleBranchSelection);
            }

            form.addEventListener('submit', (e) => {
                if (selectedBranchIsExpired()) {
                    e.preventDefault();
                    showExpiredWarning();
                    restorePrevious();
                }
            });
        })();
        </script>
    </main>
</div>
<script>
(() => {
    const pricingUrl = <?= json_encode($tenantPlansUrl) ?>;
    function mpgPremiumPlansSwal(title, html, icon) {
        if (typeof Swal === 'undefined') {
            if (confirm('View plans & pricing?')) window.location.href = pricingUrl;
            return Promise.resolve();
        }
        return Swal.fire({
            icon: icon || 'warning',
            title: title || 'Premium',
            html: html,
            confirmButtonText: 'View plans & pricing',
            cancelButtonText: 'Close',
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
        }).then((r) => {
            if (r.isConfirmed) window.location.href = pricingUrl;
        });
    }
    function mpgWireTrialReceiptModal(modalId, storageKey) {
        const modal = document.getElementById(modalId);
        if (!modal || modal.getAttribute('data-mpg-trial-print') !== '1') return;
        modal.addEventListener('shown.bs.modal', () => {
            if (typeof Swal === 'undefined') return;
            if (sessionStorage.getItem(storageKey) === '1') return;
            sessionStorage.setItem(storageKey, '1');
            mpgPremiumPlansSwal(
                'Premium',
                '<p class="mb-0 text-start">Receipt <strong>printing</strong> is a <strong>Premium</strong> feature. Free Trial lets you create and view transactions; you can still read the receipt on screen.</p>',
                'info'
            );
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        mpgWireTrialReceiptModal('receiptModal', 'mpg_swal_pos_receipt_print');
        mpgWireTrialReceiptModal('transactionReceiptModal', 'mpg_swal_tx_receipt_print');
    });
})();
</script>
<?php if (! empty($scripts)) { echo $scripts; } ?>
</body>
</html>
