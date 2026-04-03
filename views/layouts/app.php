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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($documentTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --app-toolbar-height: 56px; }
        .desktop-sidebar { display: none !important; }
        .table td, .table th { vertical-align: middle; }
        .dataTables_wrapper .dataTables_filter input { margin-left: .5rem; }
        table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:before,
        table.dataTable.dtr-inline.collapsed > tbody > tr > th.dtr-control:before {
            top: 50%; transform: translateY(-50%); background-color: #0d6efd;
        }
        /* All platforms: use hamburger + offcanvas menu */
        .mobile-sidebar-toggle { display: inline-flex; }
        .app-top-toolbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding-top: .25rem;
            padding-bottom: .25rem;
        }
        /* Touch-friendly controls (all modules): iOS/Android reliable taps */
        main .btn {
            cursor: pointer;
            touch-action: manipulation;
            -webkit-tap-highlight-color: rgba(13, 110, 253, 0.15);
        }
        @media (max-width: 767.98px) {
            main .table .btn-sm {
                min-width: 2.75rem;
                min-height: 2.75rem;
            }
            main .btn.w-100:not(.btn-sm):not(.btn-lg),
            main form .btn.btn-primary:not(.btn-sm):not(.btn-lg) {
                min-height: 2.75rem;
            }
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">
<div class="d-flex flex-grow-1 min-vh-100">
    <aside class="desktop-sidebar bg-dark text-white p-3">
        <h5 class="mb-3"><?= e($appName) ?></h5>
        <div class="small text-secondary mb-3"><?= e(strtoupper((string) ($u['role'] ?? ''))) ?></div>
        <nav class="nav flex-column gap-1">
            <a class="nav-link text-white <?= route_is('dashboard') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
            <?php if (($u['role'] ?? null) === 'super_admin'): ?>
                <a class="nav-link text-white <?= route_is('super-admin.tenants.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/tenants')) ?>">Tenants</a>
                <a class="nav-link text-white <?= route_is('super-admin.backups.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/backups/runner')) ?>">Backup Runner</a>
                <a class="nav-link text-white <?= route_is('super-admin.settings.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/settings')) ?>">Settings</a>
            <?php else: ?>
                <?php if (user_can_module('pos')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.pos.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/pos')) ?>">Create Transaction</a>
                <?php endif; ?>
                <?php if (user_can_module('transactions')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.transactions.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/transactions')) ?>">Transactions</a>
                <?php endif; ?>
                <?php if (user_can_module('ingredients')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.ingredients.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/ingredients')) ?>">Inventory Items</a>
                <?php endif; ?>
                <?php if (user_can_module('products')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.products.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/products')) ?>">Products</a>
                <?php endif; ?>
                <?php if (user_can_module('expenses')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.expenses.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/expenses')) ?>">Expenses</a>
                <?php endif; ?>
                <?php if (user_can_module('damaged_items')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.damaged-items.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/damaged-items')) ?>">Damaged Items</a>
                <?php endif; ?>
                <?php if (user_can_module('reports')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.reports.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/reports')) ?>">Reports</a>
                <?php endif; ?>
                <?php if (user_can_module('notifications')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.notifications.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/notifications')) ?>">Notifications</a>
                <?php endif; ?>
                <hr class="border-secondary my-2 opacity-50">
                <?php if (user_can_module('activity_logs')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.activity-logs.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/activity-logs')) ?>">Activity Log</a>
                <?php endif; ?>
                <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                    <a class="nav-link text-white <?= route_is('tenant.staff.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/staff')) ?>">Staff</a>
                    <a class="nav-link text-white <?= route_is('tenant.receipt-settings.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.receipt-settings.edit')) ?>">Receipt Data</a>
                    <?php if ($currentBranchIsMain): ?>
                        <a class="nav-link text-white <?= route_is('tenant.branches.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/branches')) ?>">Branches</a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    </aside>

    <aside class="offcanvas offcanvas-start bg-dark text-white p-3" tabindex="-1" id="appSidebarMobile" style="width: 260px;">
        <div class="offcanvas-header px-0 pt-0">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appSidebarMobile"></button>
        </div>
        <div class="offcanvas-body p-0 d-flex flex-column">
            <h5 class="mb-3"><?= e($appName) ?></h5>
            <div class="small text-secondary mb-3"><?= e(strtoupper((string) ($u['role'] ?? ''))) ?></div>
            <nav class="nav flex-column gap-1">
                <a class="nav-link text-white" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
                <?php if (($u['role'] ?? null) === 'super_admin'): ?>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/tenants')) ?>">Tenants</a>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/backups/runner')) ?>">Backup Runner</a>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/settings')) ?>">Settings</a>
                <?php else: ?>
                    <?php if (user_can_module('pos')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/pos')) ?>">Create Transaction</a>
                    <?php endif; ?>
                    <?php if (user_can_module('transactions')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/transactions')) ?>">Transactions</a>
                    <?php endif; ?>
                    <?php if (user_can_module('ingredients')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/ingredients')) ?>">Inventory Items</a>
                    <?php endif; ?>
                    <?php if (user_can_module('products')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/products')) ?>">Products</a>
                    <?php endif; ?>
                    <?php if (user_can_module('expenses')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/expenses')) ?>">Expenses</a>
                    <?php endif; ?>
                    <?php if (user_can_module('damaged_items')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/damaged-items')) ?>">Damaged Items</a>
                    <?php endif; ?>
                    <?php if (user_can_module('reports')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/reports')) ?>">Reports</a>
                    <?php endif; ?>
                    <?php if (user_can_module('notifications')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/notifications')) ?>">Notifications</a>
                    <?php endif; ?>
                    <hr class="border-secondary my-2 opacity-50">
                    <?php if (user_can_module('activity_logs')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/activity-logs')) ?>">Activity Log</a>
                    <?php endif; ?>
                    <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/staff')) ?>">Staff</a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.receipt-settings.edit')) ?>">Receipt Data</a>
                        <?php if ($currentBranchIsMain): ?>
                            <a class="nav-link text-white" href="<?= e(url('/tenant/branches')) ?>">Branches</a>
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

    <main class="flex-grow-1 d-flex flex-column min-vh-100 p-3 p-md-4">
        <?php /* Load jQuery + DataTables + initServerDataTable before $content so inline scripts in views run after they exist */ ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
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
                const { ajax: userAjax, ...rest } = options;
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

                return el.DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: {
                        details: {
                            type: 'column',
                            target: 0,
                            renderer: $.fn.dataTable.Responsive.renderer.tableAll({ tableClass: 'table table-sm' }),
                        },
                    },
                    autoWidth: false,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                    searchDelay: 300,
                    order: [[1, 'desc']],
                    language: { search: 'Search:', lengthMenu: 'Show _MENU_ rows', processing: 'Loading...' },
                    createdRow: (row) => { row.querySelectorAll('td').forEach((cell) => cell.classList.add('align-middle')); },
                    ...rest,
                    ajax: ajaxConfig,
                });
            };
        })();
        </script>
        <div class="app-main-scroll flex-grow-1 d-flex flex-column min-h-0">
        <div class="app-top-toolbar d-flex justify-content-between align-items-center mb-2">
            <div class="mobile-sidebar-toggle">
                <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <a href="<?= e(url('/profile')) ?>" class="btn btn-outline-secondary btn-sm" title="Profile" aria-label="Profile"><i class="fa-solid fa-user"></i></a>
                <form method="POST" action="<?= e(url('/logout')) ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Logout" aria-label="Logout"><i class="fa-solid fa-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
        <h4 class="mb-3"><?= e($title ?? 'Dashboard') ?></h4>
        <?php require dirname(__DIR__).'/partials/alerts.php'; ?>
        <div class="flex-grow-1 min-h-0 overflow-auto"><?= $content ?? '' ?></div>
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
<?php if (! empty($scripts)) { echo $scripts; } ?>
</body>
</html>
