<?php
/** @var string $title */
/** @var string $content */
$u = $user ?? auth_user();
$appName = \App\Core\App::config('name') ?? 'Laundry System';
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
$brandLogoPath = url('images/branding/mpglms-logo.png');
$brandDisplayName = (string) $appName;
$tenantBrandName = '';
if (($u['role'] ?? '') !== 'super_admin' && ! empty($u['tenant_id'])) {
    try {
        $pdoBrand = \App\Core\App::db();
        $stBrand = $pdoBrand->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
        $stBrand->execute([(int) $u['tenant_id']]);
        $tenantBrandName = trim((string) ($stBrand->fetchColumn() ?: ''));
    } catch (\Throwable) {
        $tenantBrandName = '';
    }
}
if ($tenantBrandName !== '') {
    $brandDisplayName = $tenantBrandName;
}
$isTimeOnlyCashier = (($u['role'] ?? '') === 'cashier')
    && in_array(strtolower(trim((string) ($u['staff_type'] ?? 'full_time'))), ['utility', 'driver'], true);

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
    && \App\Core\Auth::isTenantFreePlanRestricted($u);
$tenantPlansUrl = url('/tenant/plans');
$subscriptionRenewUrl = 'https://www.facebook.com/mpgtechnologysolutionscom';
$paidSubscriptionExpired = (($u['role'] ?? '') !== 'super_admin')
    && ! empty($u['tenant_id'])
    && \App\Core\Auth::isTenantPaidSubscriptionExpired($u);
$timeWidget = [
    'enabled' => false,
    'is_clocked_in' => false,
    'clock_in_at' => null,
    'worked_seconds' => 0,
];
if (($u['role'] ?? '') !== 'super_admin' && ! empty($u['tenant_id']) && ! empty($u['id'])) {
    try {
        $pdo = \App\Core\App::db();
        \App\Core\LaundrySchema::ensure($pdo);
        $today = date('Y-m-d');
        $stClock = $pdo->prepare(
            'SELECT id, clock_in_at, clock_out_at
             FROM laundry_time_logs
             WHERE tenant_id = ? AND user_id = ? AND DATE(clock_in_at) = ?
             ORDER BY id DESC
             LIMIT 1'
        );
        $stClock->execute([(int) $u['tenant_id'], (int) $u['id'], $today]);
        $clockRow = $stClock->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (is_array($clockRow)) {
            $clockInRaw = (string) ($clockRow['clock_in_at'] ?? '');
            $clockOutRaw = (string) ($clockRow['clock_out_at'] ?? '');
            $clockInTs = $clockInRaw !== '' ? strtotime($clockInRaw) : false;
            $clockOutTs = $clockOutRaw !== '' ? strtotime($clockOutRaw) : false;
            $worked = 0;
            if ($clockInTs !== false) {
                $worked = max(0, (int) (($clockOutTs !== false ? $clockOutTs : time()) - $clockInTs));
            }
            $timeWidget = [
                'enabled' => true,
                'is_clocked_in' => $clockInTs !== false && $clockOutTs === false,
                'clock_in_at' => $clockInTs !== false ? date('c', $clockInTs) : null,
                'worked_seconds' => $worked,
            ];
        } else {
            $timeWidget['enabled'] = true;
        }
    } catch (\Throwable) {
        $timeWidget = [
            'enabled' => false,
            'is_clocked_in' => false,
            'clock_in_at' => null,
            'worked_seconds' => 0,
        ];
    }
}
$swalClockInRequired = (bool) session_flash('swal_clock_in_required');
$isStoreOwner = (($u['role'] ?? '') === 'tenant_admin');
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
    <link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
    <meta name="theme-color" content="#2563eb">
    <link href="<?= e(url('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('vendor/fonts/manrope/manrope.css')) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('vendor/fontawesome/css/all.min.css')) ?>">
    <link href="<?= e(url('vendor/select2/select2.min.css')) ?>" rel="stylesheet" />
    <link href="<?= e(url('vendor/select2/select2-bootstrap-5-theme.min.css')) ?>" rel="stylesheet" />
    <link rel="stylesheet" href="<?= e(url('vendor/datatables/dataTables.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('vendor/datatables/responsive.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('css/app-theme.css')) ?>">
    <script src="<?= e(url('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script>
(() => {
    const nativeAlert = window.alert ? window.alert.bind(window) : () => {};
    const nativeConfirm = window.confirm ? window.confirm.bind(window) : () => false;

    window.mpgAlert = function(message, opts = {}) {
        const text = String(message || 'Notice');
        if (typeof Swal === 'undefined') {
            nativeAlert(text);
            return Promise.resolve();
        }
        return Swal.fire({
            icon: opts.icon || 'info',
            title: opts.title || 'Notice',
            text,
            confirmButtonText: opts.confirmButtonText || 'OK',
            confirmButtonColor: opts.confirmButtonColor || '#0d6efd',
        });
    };

    window.mpgConfirm = function(message, opts = {}) {
        const text = String(message || 'Are you sure?');
        if (typeof Swal === 'undefined') {
            return Promise.resolve(nativeConfirm(text));
        }
        return Swal.fire({
            icon: opts.icon || 'warning',
            title: opts.title || 'Confirm action',
            text,
            showCancelButton: true,
            confirmButtonText: opts.confirmButtonText || 'Yes',
            cancelButtonText: opts.cancelButtonText || 'Cancel',
            confirmButtonColor: opts.confirmButtonColor || '#dc3545',
        }).then((r) => r.isConfirmed === true);
    };

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.swalConfirmBypass === '1') {
            form.dataset.swalConfirmBypass = '0';
            return;
        }
        const attr = form.getAttribute('onsubmit') || '';
        const m = attr.match(/confirm\((['"])(.*?)\1\)/i);
        if (!m) return;
        e.preventDefault();
        e.stopPropagation();
        const msg = m[2] || 'Are you sure?';
        window.mpgConfirm(msg).then((ok) => {
            if (!ok) return;
            form.dataset.swalConfirmBypass = '1';
            const originalOnSubmit = form.getAttribute('onsubmit');
            if (originalOnSubmit !== null) {
                form.removeAttribute('onsubmit');
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            setTimeout(() => {
                if (originalOnSubmit !== null) {
                    form.setAttribute('onsubmit', originalOnSubmit);
                }
            }, 0);
        });
    }, true);
})();
</script>
</head>
<body class="app-theme laundry-luxe bg-light d-flex flex-column min-vh-100">
<div class="water-decor" aria-hidden="true">
    <span class="bubble bubble-1"></span>
    <span class="bubble bubble-2"></span>
    <span class="bubble bubble-3"></span>
    <span class="bubble bubble-4"></span>
    <span class="bubble bubble-5"></span>
</div>
<div class="app-shell d-flex flex-grow-1 min-vh-100">
    <aside class="desktop-sidebar bg-dark text-white p-3">
        <div class="d-flex align-items-center gap-2 mb-3">
            <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="36" height="36" class="rounded-circle border border-secondary-subtle">
            <h5 class="mb-0"><?= e($brandDisplayName) ?></h5>
        </div>
        <div class="small text-secondary mb-3"><?= e(strtoupper((string) ($u['role'] ?? ''))) ?></div>
        <nav class="nav flex-column gap-1">
            <?php if (($u['role'] ?? '') !== 'cashier'): ?>
                <a class="nav-link text-white <?= route_is('dashboard') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/dashboard')) ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>
            <?php endif; ?>
            <?php if (($u['role'] ?? null) === 'super_admin'): ?>
                <a class="nav-link text-white <?= route_is('super-admin.tenants.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/tenants')) ?>"><i class="fa-solid fa-building"></i><span>Tenants</span></a>
                <a class="nav-link text-white <?= route_is('super-admin.backups.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/backups/runner')) ?>"><i class="fa-solid fa-database"></i><span>Backup Runner</span></a>
                <a class="nav-link text-white <?= route_is('super-admin.settings.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/super-admin/settings')) ?>"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
            <?php else: ?>
                <?php if ($isTimeOnlyCashier): ?>
                    <div class="small text-secondary px-2 py-1">Time in/out only account</div>
                <?php else: ?>
                <?php if (user_can_module('pos')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.staff-portal.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.staff-portal.index')) ?>"><i class="fa-solid fa-table-cells-large"></i><span>Staff Kiosk Portal</span></a>
                    <a class="nav-link text-white <?= route_is('tenant.laundry-sales.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.laundry-sales.index')) ?>"><i class="fa-solid fa-soap"></i><span>Loads Status</span></a>
                <?php endif; ?>
                <?php if (user_can_module('transactions')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.customers.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.customers.index')) ?>"><i class="fa-solid fa-users"></i><span>Customer Profile<?= $premiumNavBadge(true) ?></span></a>
                <?php endif; ?>
                <?php if (user_can_module('ingredients')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.laundry-inventory.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.laundry-inventory.index')) ?>"><i class="fa-solid fa-box-open"></i><span>Inventory Management</span></a>
                <?php endif; ?>
                <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                    <a class="nav-link text-white <?= route_is('tenant.machines.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.machines.index')) ?>"><i class="fa-solid fa-gears"></i><span>Machines</span></a>
                    <a class="nav-link text-white <?= route_is('tenant.laundry-order-pricing.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.laundry-order-pricing.index')) ?>"><i class="fa-solid fa-sliders"></i><span>Order Pricing</span></a>
                    <a class="nav-link text-white <?= route_is('tenant.attendance.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.attendance.index')) ?>"><i class="fa-solid fa-user-clock"></i><span>Attendance</span></a>
                    <a class="nav-link text-white <?= route_is('tenant.payroll.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.payroll.index')) ?>"><i class="fa-solid fa-money-check-dollar"></i><span>Payroll<?= $premiumNavBadge(true) ?></span></a>
                    <a class="nav-link text-white <?= route_is('tenant.redeem-config.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.redeem-config.index')) ?>"><i class="fa-solid fa-gift"></i><span>Rewards<?= $premiumNavBadge(true) ?></span></a>
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
                <hr class="border-secondary my-2 opacity-50">
                <?php if (user_can_module('activity_logs')): ?>
                    <a class="nav-link text-white <?= route_is('tenant.activity-logs.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/activity-logs')) ?>"><i class="fa-solid fa-clock-rotate-left"></i><span>Activity Log</span></a>
                <?php endif; ?>
                <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                    <a class="nav-link text-white <?= route_is('tenant.staff.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/staff')) ?>"><i class="fa-solid fa-users"></i><span>Staff</span></a>
                    <a class="nav-link text-white <?= route_is('tenant.receipt-settings.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(route('tenant.receipt-settings.edit')) ?>"><i class="fa-solid fa-file-lines"></i><span>Receipt Config<?= $premiumNavBadge(true) ?></span></a>
                    <?php if ($currentBranchIsMain): ?>
                        <a class="nav-link text-white <?= route_is('tenant.branches.') ? 'bg-secondary rounded' : '' ?>" href="<?= e(url('/tenant/branches')) ?>"><i class="fa-solid fa-code-branch"></i><span>Branches</span></a>
                    <?php endif; ?>
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
                <?php if (($u['role'] ?? '') !== 'cashier'): ?>
                    <a class="nav-link text-white" href="<?= e(url('/dashboard')) ?>"><i class="fa-solid fa-house me-2"></i>Dashboard</a>
                <?php endif; ?>
                <?php if (($u['role'] ?? null) === 'super_admin'): ?>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/tenants')) ?>"><i class="fa-solid fa-building me-2"></i>Tenants</a>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/backups/runner')) ?>"><i class="fa-solid fa-database me-2"></i>Backup Runner</a>
                    <a class="nav-link text-white" href="<?= e(url('/super-admin/settings')) ?>"><i class="fa-solid fa-gear me-2"></i>Settings</a>
                <?php else: ?>
                    <?php if ($isTimeOnlyCashier): ?>
                        <div class="small text-secondary px-1 py-1">Time in/out only account</div>
                    <?php else: ?>
                    <?php if (user_can_module('pos')): ?>
                        <a class="nav-link text-white" href="<?= e(route('tenant.staff-portal.index')) ?>"><i class="fa-solid fa-table-cells-large me-2"></i>Staff Kiosk Portal</a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.laundry-sales.index')) ?>"><i class="fa-solid fa-soap me-2"></i>Loads Status</a>
                    <?php endif; ?>
                    <?php if (user_can_module('transactions')): ?>
                        <a class="nav-link text-white" href="<?= e(route('tenant.customers.index')) ?>"><i class="fa-solid fa-users me-2"></i>Customer Profile<?= $premiumNavBadge(true) ?></a>
                    <?php endif; ?>
                    <?php if (user_can_module('ingredients')): ?>
                        <a class="nav-link text-white" href="<?= e(route('tenant.laundry-inventory.index')) ?>"><i class="fa-solid fa-box-open me-2"></i>Inventory Management</a>
                    <?php endif; ?>
                    <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                        <a class="nav-link text-white" href="<?= e(route('tenant.machines.index')) ?>"><i class="fa-solid fa-gears me-2"></i>Machines</a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.laundry-order-pricing.index')) ?>"><i class="fa-solid fa-sliders me-2"></i>Order Pricing</a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.attendance.index')) ?>"><i class="fa-solid fa-user-clock me-2"></i>Attendance</a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.payroll.index')) ?>"><i class="fa-solid fa-money-check-dollar me-2"></i>Payroll<?= $premiumNavBadge(true) ?></a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.redeem-config.index')) ?>"><i class="fa-solid fa-gift me-2"></i>Rewards<?= $premiumNavBadge(true) ?></a>
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
                    <hr class="border-secondary my-2 opacity-50">
                    <?php if (user_can_module('activity_logs')): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/activity-logs')) ?>"><i class="fa-solid fa-clock-rotate-left me-2"></i>Activity Log</a>
                    <?php endif; ?>
                    <?php if (($u['role'] ?? null) === 'tenant_admin'): ?>
                        <a class="nav-link text-white" href="<?= e(url('/tenant/staff')) ?>"><i class="fa-solid fa-users me-2"></i>Staff</a>
                        <a class="nav-link text-white" href="<?= e(route('tenant.receipt-settings.edit')) ?>"><i class="fa-solid fa-file-lines me-2"></i>Receipt Config<?= $premiumNavBadge(true) ?></a>
                        <?php if ($currentBranchIsMain): ?>
                            <a class="nav-link text-white" href="<?= e(url('/tenant/branches')) ?>"><i class="fa-solid fa-code-branch me-2"></i>Branches</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
            <?php if (($u['role'] ?? '') === 'tenant_admin' && $branchSwitcherRows !== []): ?>
                <div class="mt-auto pt-3 border-top border-secondary-subtle">
                    <form method="POST" action="<?= e(route('tenant.branches.switch')) ?>" id="branchSwitcherForm" class="m-0 vstack gap-1" data-mpg-ajax="off">
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
        <script src="<?= e(url('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
        <?php
        $mpgReceiptClientConfig = [];
        if (! empty($u['tenant_id'])) {
            try {
                $pdo = \App\Core\App::db();
                \App\Core\TenantReceiptFields::ensure($pdo);
                $st = $pdo->prepare(
                    'SELECT name, receipt_ble_printer_match_rules,
                            receipt_is_bir_registered, receipt_tax_type, receipt_serial_number,
                            receipt_bir_accreditation_no, receipt_min, receipt_permit_no,
                            receipt_display_name, receipt_business_style, receipt_tax_id,
                            receipt_phone, receipt_address, receipt_email, receipt_footer_note,
                            receipt_escpos_line_width, receipt_escpos_extra_feeds, receipt_escpos_cut_mode,
                            receipt_lan_print_copies
                     FROM tenants
                     WHERE id = ?
                     LIMIT 1'
                );
                $st->execute([(int) $u['tenant_id']]);
                $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
                $isBirRegistered = (int) ($row['receipt_is_bir_registered'] ?? 0) === 1;
                $taxType = strtolower(trim((string) ($row['receipt_tax_type'] ?? 'non_vat')));
                $hasBirIdentity = trim((string) ($row['receipt_serial_number'] ?? '')) !== ''
                    && trim((string) ($row['receipt_bir_accreditation_no'] ?? '')) !== ''
                    && trim((string) ($row['receipt_min'] ?? '')) !== ''
                    && trim((string) ($row['receipt_permit_no'] ?? '')) !== '';
                $birCompliant = $isBirRegistered && in_array($taxType, ['vat', 'non_vat'], true) && $hasBirIdentity;
                $mpgReceiptClientConfig = [
                    'store_name' => (string) ($row['name'] ?? ''),
                    'ble_printer_match_rules' => (string) ($row['receipt_ble_printer_match_rules'] ?? ''),
                    'is_bir_compliant' => $birCompliant ? 1 : 0,
                    'display_name' => (string) ($row['receipt_display_name'] ?? ''),
                    'business_style' => (string) ($row['receipt_business_style'] ?? ''),
                    'tax_id' => (string) ($row['receipt_tax_id'] ?? ''),
                    'phone' => (string) ($row['receipt_phone'] ?? ''),
                    'address' => (string) ($row['receipt_address'] ?? ''),
                    'email' => (string) ($row['receipt_email'] ?? ''),
                    'footer_note' => (string) ($row['receipt_footer_note'] ?? ''),
                    'escpos_line_width' => (int) ($row['receipt_escpos_line_width'] ?? 32),
                    'escpos_extra_feeds' => (int) ($row['receipt_escpos_extra_feeds'] ?? 4),
                    'escpos_cut_mode' => (string) ($row['receipt_escpos_cut_mode'] ?? 'partial'),
                    'lan_print_copies' => max(1, min(10, (int) ($row['receipt_lan_print_copies'] ?? 1))),
                ];
            } catch (\Throwable) {
                $mpgReceiptClientConfig = [];
            }
        }
        ?>
        <script>
            window.MPG_RECEIPT_CONFIG = <?= json_encode(
                $mpgReceiptClientConfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?>;
        </script>
        <script src="<?= e(url('js/receipt-print.js')) ?>"></script>
        <script src="<?= e(url('vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
        <script src="<?= e(url('js/mpg-ajax-actions.js')) ?>"></script>
        <script src="<?= e(url('vendor/select2/select2.min.js')) ?>"></script>
        <script src="<?= e(url('vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
        <script src="<?= e(url('vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
        <script src="<?= e(url('vendor/datatables/dataTables.responsive.min.js')) ?>"></script>
        <script src="<?= e(url('vendor/datatables/responsive.bootstrap5.min.js')) ?>"></script>
        <link rel="stylesheet" href="<?= e(url('vendor/datatables/buttons.bootstrap5.min.css')) ?>">
        <script src="<?= e(url('vendor/datatables/dataTables.buttons.min.js')) ?>"></script>
        <script src="<?= e(url('vendor/datatables/buttons.bootstrap5.min.js')) ?>"></script>
        <script src="<?= e(url('vendor/datatables/buttons.print.min.js')) ?>"></script>
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
            <div class="d-flex align-items-center gap-2 toolbar-left-group">
            <div class="mobile-sidebar-toggle">
                <button class="btn btn-dark btn-sm toolbar-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
                <div class="d-flex align-items-center gap-2">
                    <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="30" height="30" class="rounded-circle border border-secondary-subtle">
                    <span class="fw-semibold small d-none d-sm-inline"><?= e($brandDisplayName) ?></span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end toolbar-actions">
                <?php if ($paidSubscriptionExpired): ?>
                    <div class="d-flex align-items-center gap-2 me-1 toolbar-subscription-group">
                        <span class="badge rounded-pill text-bg-danger px-3 py-2 toolbar-subscription-badge" style="font-weight:600;">
                            <i class="fa-solid fa-circle-exclamation me-1"></i>
                            <span>Subscription expired, currently you are using Free Mode</span>
                        </span>
                        <a href="<?= e($subscriptionRenewUrl) ?>" class="btn btn-danger btn-sm fw-semibold toolbar-btn">
                            Renew
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (! empty($timeWidget['enabled'])): ?>
                    <?php
                    $twClockedIn = ! empty($timeWidget['is_clocked_in']);
                    $twWorked = (int) ($timeWidget['worked_seconds'] ?? 0);
                    $twH = (int) floor($twWorked / 3600);
                    $twM = (int) floor(($twWorked % 3600) / 60);
                    $twS = (int) ($twWorked % 60);
                    $twWorkedLabel = sprintf('%02d:%02d:%02d', $twH, $twM, $twS);
                    ?>
                    <div
                        id="topTimeWidget"
                        class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-white toolbar-time-widget"
                        data-worked-seconds="<?= (int) ($timeWidget['worked_seconds'] ?? 0) ?>"
                        data-is-clocked-in="<?= $twClockedIn ? '1' : '0' ?>"
                        data-clock-in-at="<?= e((string) ($timeWidget['clock_in_at'] ?? '')) ?>"
                    >
                        <div class="small">
                            <div class="fw-semibold font-monospace" id="topTimeWidgetWorked"><?= e($twWorkedLabel) ?></div>
                        </div>
                        <div class="d-flex gap-1">
                            <?php if (! $twClockedIn): ?>
                                <form method="POST" action="<?= e(route('dashboard.time-in')) ?>" class="m-0 js-top-attendance-photo-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="photo_data" value="">
                                    <button type="button" class="btn btn-success btn-sm px-2 py-1 toolbar-btn js-top-attendance-photo-trigger"><i class="fa-solid fa-right-to-bracket"></i></button>
                                    <button type="submit" class="d-none js-top-attendance-hidden-submit" aria-hidden="true"></button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="<?= e(route('dashboard.time-out')) ?>" class="m-0 js-top-attendance-photo-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="photo_data" value="">
                                    <button type="button" class="btn btn-danger btn-sm px-2 py-1 toolbar-btn js-top-attendance-photo-trigger"><i class="fa-solid fa-right-from-bracket"></i></button>
                                    <button type="submit" class="d-none js-top-attendance-hidden-submit" aria-hidden="true"></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (\App\Core\Auth::isTenantFreeTrialActive($u ?? null)): ?>
                    <?php $trialDays = \App\Core\Auth::tenantFreeTrialDaysRemaining($u ?? null); ?>
                    <div class="d-flex align-items-center gap-2 me-1 toolbar-subscription-group">
                        <span class="badge rounded-pill bg-dark border border-light border-opacity-25 px-3 py-2 toolbar-subscription-badge" style="font-weight:600;">
                            <i class="fa-regular fa-calendar me-1"></i>
                            <span>Trial expires in <?= $trialDays !== null ? (int) $trialDays.' day'.(((int) $trialDays) === 1 ? '' : 's') : 'soon' ?></span>
                        </span>
                        <a href="<?= e($tenantPlansUrl) ?>" class="btn btn-success btn-sm fw-semibold toolbar-btn">Upgrade</a>
                    </div>
                <?php endif; ?>
                <a href="<?= e(url('/profile')) ?>" class="btn btn-outline-secondary btn-sm toolbar-btn" title="Profile" aria-label="Profile"><i class="fa-solid fa-user"></i></a>
                <form method="POST" action="<?= e(url('/logout')) ?>" class="m-0" data-mpg-ajax="off">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm toolbar-btn" title="Logout" aria-label="Logout"><i class="fa-solid fa-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
        <div class="modern-page-header">
            <h4 class="mb-1"><?= e($title ?? 'Dashboard') ?></h4>
            <div class="modern-page-note">Manage and monitor your laundry operations.</div>
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

            const handleBranchSelection = () => {
                if (reverting) return;
                if (select.value === previousValue) return;
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
        })();
        </script>
    </main>
</div>
<script>
(() => {
    const timeWidget = document.getElementById('topTimeWidget');
    const workedEl = document.getElementById('topTimeWidgetWorked');
    if (timeWidget && workedEl) {
        let workedSeconds = parseInt(timeWidget.getAttribute('data-worked-seconds') || '0', 10);
        const isClockedIn = (timeWidget.getAttribute('data-is-clocked-in') || '0') === '1';
        const fmt = (n) => String(Math.max(0, Math.floor(n))).padStart(2, '0');
        const render = () => {
            const h = Math.floor(workedSeconds / 3600);
            const m = Math.floor((workedSeconds % 3600) / 60);
            const s = workedSeconds % 60;
            workedEl.textContent = `${fmt(h)}:${fmt(m)}:${fmt(s)}`;
        };
        render();
        if (isClockedIn) {
            setInterval(() => {
                workedSeconds += 1;
                render();
            }, 1000);
        }
    }

    const pricingUrl = <?= json_encode($tenantPlansUrl) ?>;
    function mpgPremiumPlansSwal(title, html, icon) {
        if (typeof Swal === 'undefined') {
            return window.mpgConfirm('View plans & pricing?', {
                title: title || 'Premium',
                confirmButtonText: 'View plans & pricing',
                cancelButtonText: 'Close',
                confirmButtonColor: '#f59e0b',
            }).then((ok) => {
                if (ok) window.location.href = pricingUrl;
            });
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
                '<p class="mb-0 text-start">Receipt <strong>printing</strong> is a <strong>Premium</strong> feature. The Free version lets you create and view transactions; you can still read the receipt on screen.</p>',
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
<?php if (! $isStoreOwner && ! empty($swalClockInRequired)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Swal === 'undefined') return;
    Swal.fire({
        icon: 'warning',
        title: 'Time in required',
        html: 'Your attendance timer is not running. Please tap <strong>Time in</strong> on the timer above or on the Dashboard before using other modules.',
        confirmButtonText: 'OK',
        confirmButtonColor: '#198754',
    });
});
</script>
<?php endif; ?>
<script>
(() => {
    // Prevent modal stacking issues caused by parent stacking contexts (tables/cards/containers).
    // Always portal modals to <body> and keep newest modal/backdrop on top.
    const BASE_Z = 2200;
    document.addEventListener('show.bs.modal', (event) => {
        const modal = event.target;
        if (!(modal instanceof HTMLElement) || !modal.classList.contains('modal')) return;

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const openCount = document.querySelectorAll('.modal.show').length;
        modal.style.zIndex = String(BASE_Z + (openCount * 20));

        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            const latest = backdrops.length ? backdrops[backdrops.length - 1] : null;
            if (latest instanceof HTMLElement) {
                latest.style.zIndex = String(BASE_Z - 10 + (openCount * 20));
            }
        }, 0);
    });
})();
</script>
<script>
(() => {
    const isStoreOwner = <?= json_encode($isStoreOwner, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (isStoreOwner) return;
    const w = document.getElementById('topTimeWidget');
    if (!w || (w.getAttribute('data-is-clocked-in') || '0') === '1') return;
    const pathAllowed = (pathname) => {
        if (pathname === '/dashboard' || pathname.startsWith('/dashboard/')) return true;
        if (pathname === '/logout') return true;
        if (pathname === '/subscription-ended') return true;
        if (pathname === '/tenant/plans' || pathname.startsWith('/tenant/plans/')) return true;
        return false;
    };
    const mustBlock = (pathname) => {
        if (pathname.includes('/tenant/')) return true;
        if (pathname === '/profile' || pathname.startsWith('/profile/')) return true;
        if (pathname === '/password' || pathname.startsWith('/password/')) return true;
        return false;
    };
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[href]');
        if (!a || a.hasAttribute('data-allow-no-clock-in')) return;
        let pathname;
        try {
            pathname = new URL(a.href, window.location.href).pathname;
        } catch (_) {
            return;
        }
        if (pathAllowed(pathname)) return;
        if (!mustBlock(pathname)) return;
        e.preventDefault();
        e.stopPropagation();
        window.mpgAlert('You must time in first (use the timer in the header) before opening that page.', {
            title: 'Time in required',
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#198754',
        });
    }, true);
})();
</script>
<div class="modal fade" id="topAttendancePhotoModal" tabindex="-1" aria-labelledby="topAttendancePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="topAttendancePhotoModalLabel">Capture attendance photo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <video id="topAttendancePhotoVideo" class="w-100 rounded border bg-dark-subtle" autoplay playsinline muted style="min-height:220px;"></video>
                <canvas id="topAttendancePhotoCanvas" class="d-none"></canvas>
                <div class="small text-muted mt-2" id="topAttendancePhotoHint">Allow camera access, then capture. File upload is disabled by policy.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-dark" id="topAttendancePhotoRetryBtn">Retry camera</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="topAttendancePhotoCaptureBtn">Capture & continue</button>
            </div>
        </div>
    </div>
</div>
<script>
(() => {
    const forms = Array.from(document.querySelectorAll('.js-top-attendance-photo-form'));
    if (!forms.length || typeof bootstrap === 'undefined') return;

    const modalEl = document.getElementById('topAttendancePhotoModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const video = document.getElementById('topAttendancePhotoVideo');
    const canvas = document.getElementById('topAttendancePhotoCanvas');
    const captureBtn = document.getElementById('topAttendancePhotoCaptureBtn');
    const retryBtn = document.getElementById('topAttendancePhotoRetryBtn');
    const hint = document.getElementById('topAttendancePhotoHint');
    let stream = null;
    let pendingForm = null;

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
        }
        if (video) video.srcObject = null;
    };

    const describeCameraError = (err) => {
        const name = String(err?.name || '');
        if (name === 'NotAllowedError' || name === 'SecurityError') {
            return 'Camera permission denied. Allow camera in browser and macOS privacy settings.';
        }
        if (name === 'NotReadableError' || name === 'TrackStartError') {
            return 'Camera is busy or unavailable. Close Zoom/Meet/Photo Booth and retry.';
        }
        if (name === 'OverconstrainedError') {
            return 'Requested camera mode is not supported on this device.';
        }
        if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
            return 'No camera device detected.';
        }
        return 'Unable to access camera right now.';
    };

    const openCamera = async () => {
        if (!video) return false;
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            if (hint) hint.textContent = 'Webcam API not available in this browser/session.';
            return false;
        }
        stopCamera();
        const attempts = [
            { width: { ideal: 1280 }, height: { ideal: 720 } },
            { facingMode: 'user' },
            true,
        ];
        let lastErr = null;
        for (const v of attempts) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: v, audio: false });
                video.srcObject = stream;
                await video.play().catch(() => {});
                if (hint) hint.textContent = 'Tap "Capture & continue" when your face is visible.';
                return true;
            } catch (err) {
                lastErr = err;
            }
        }
        if (hint) hint.textContent = `${describeCameraError(lastErr)} (Error: ${String(lastErr?.name || 'unknown')}).`;
        return false;
    };

    const openForForm = async (form, ev = null) => {
        if (ev) ev.preventDefault();
        pendingForm = form;
        modal?.show();
        await openCamera();
    };

    forms.forEach((form) => {
        const trigger = form.querySelector('.js-top-attendance-photo-trigger');
        trigger?.addEventListener('click', async (e) => {
            await openForForm(form, e);
        });
    });

    captureBtn?.addEventListener('click', () => {
        if (!pendingForm || !video || !canvas) return;
        const w = video.videoWidth || 640;
        const h = video.videoHeight || 480;
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.drawImage(video, 0, 0, w, h);
        const image = canvas.toDataURL('image/jpeg', 0.92);
        const hidden = pendingForm.querySelector('input[name="photo_data"]');
        if (!hidden) return;
        hidden.value = image;
        modal?.hide();
        stopCamera();
        const submitBtn = pendingForm.querySelector('.js-top-attendance-hidden-submit');
        if (submitBtn instanceof HTMLElement) submitBtn.click();
        else if (typeof pendingForm.requestSubmit === 'function') pendingForm.requestSubmit();
        else pendingForm.submit();
    });

    retryBtn?.addEventListener('click', async () => {
        stopCamera();
        await openCamera();
    });

    modalEl?.addEventListener('hidden.bs.modal', () => {
        stopCamera();
    });
})();
</script>
<?php if (! empty($scripts)) { echo $scripts; } ?>
</body>
</html>
