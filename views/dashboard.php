<?php
/** @var bool $is_super */
if (! empty($is_super)): ?>
    <?php
    $superStats = (array) ($stats ?? []);
    $usersColumns = (array) ($users_columns ?? []);
    $usersRows = (array) ($users_rows ?? []);
    ?>
    <div class="row g-3 mb-3">
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Overall Total Shops</small>
                    <h3 class="mb-0"><?= (int) ($superStats['overall_total_shops'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Free Users</small>
                    <h3 class="mb-0"><?= (int) ($superStats['free_users'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Premium Users</small>
                    <h3 class="mb-0"><?= (int) ($superStats['premium_users'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Total Verified Shops</small>
                    <h3 class="mb-0"><?= (int) ($superStats['total_verified_shops'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">1 Month Users</small>
                    <h4 class="mb-0"><?= (int) ($superStats['one_month_users'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">3 Months Users</small>
                    <h4 class="mb-0"><?= (int) ($superStats['three_month_users'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">6 Months Users</small>
                    <h4 class="mb-0"><?= (int) ($superStats['six_month_users'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">12 Months Users</small>
                    <h4 class="mb-0"><?= (int) ($superStats['twelve_month_users'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Total Main Branches</small>
                    <h4 class="mb-0"><?= (int) ($superStats['total_main_branches'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Total Sub Branches</small>
                    <h4 class="mb-0"><?= (int) ($superStats['total_sub_branches'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <h5 class="mb-0">Users Table (All columns except password)</h5>
                <span class="small text-muted">Showing latest <?= count($usersRows) ?> rows</span>
            </div>
            <?php if ($usersColumns === []): ?>
                <p class="text-muted mb-0">Users table columns are unavailable.</p>
            <?php elseif ($usersRows === []): ?>
                <p class="text-muted mb-0">No users found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <?php foreach ($usersColumns as $col): ?>
                                <th><?= e((string) $col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($usersRows as $row): ?>
                            <tr>
                                <?php foreach ($usersColumns as $col): ?>
                                    <td><?= e((string) ($row[$col] ?? '')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <style>
        .dashboard-content-no-x {
            overflow-x: hidden;
        }
        .dashboard-figures-card {
            position: relative;
            z-index: 0;
            overflow: hidden;
        }
        .dashboard-figures-chart-wrap {
            position: relative;
            height: 320px;
            max-height: 320px;
            overflow: hidden;
        }
        .dashboard-figures-chart-wrap canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
            max-height: 100%;
        }
    </style>
    <div class="dashboard-content-no-x">
    <?php
    $dashUser = auth_user();
    $isTimeOnlyCashierDashboard = (($dashUser['role'] ?? '') === 'cashier')
        && in_array(strtolower(trim((string) ($dashUser['staff_type'] ?? 'full_time'))), ['utility', 'driver'], true);
    $attendanceFeatureEnabled = ! empty($can_use_attendance);
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="mb-1">Time attendance (today)</h6>
                    <div class="small text-muted">
                        <?php if (! $attendanceFeatureEnabled): ?>
                            Attendance is locked. Upgrade to Premium or activate premium trial to use time in/time out.
                        <?php else: ?>
                            <?= $isTimeOnlyCashierDashboard ? 'Time in/time out only access for this account.' : 'Staff and store owner can time in/out here.' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <?php if (! $attendanceFeatureEnabled): ?>
                        <a href="<?= e(route('tenant.plans')) ?>" class="btn btn-outline-warning btn-sm">
                            <i class="fa-solid fa-crown me-1"></i>Premium required
                        </a>
                    <?php else: ?>
                        <?php if (empty($clock_open)): ?>
                            <form method="POST" action="<?= e(route('dashboard.time-in')) ?>" class="m-0 js-attendance-photo-form" data-kind="in">
                                <?= csrf_field() ?>
                                <input type="hidden" name="photo_data" value="">
                                <button class="btn btn-success <?= $isTimeOnlyCashierDashboard ? 'btn-lg px-4 py-2' : 'btn-sm' ?> js-attendance-photo-trigger" type="button"><i class="fa-solid fa-right-to-bracket me-1"></i>Time in</button>
                                <button type="submit" class="d-none js-attendance-hidden-submit" aria-hidden="true"></button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?= e(route('dashboard.time-out')) ?>" class="m-0 js-attendance-photo-form" data-kind="out">
                                <?= csrf_field() ?>
                                <input type="hidden" name="photo_data" value="">
                                <button class="btn btn-danger <?= $isTimeOnlyCashierDashboard ? 'btn-lg px-4 py-2' : 'btn-sm' ?> js-attendance-photo-trigger" type="button"><i class="fa-solid fa-right-from-bracket me-1"></i>Time out</button>
                                <button type="submit" class="d-none js-attendance-hidden-submit" aria-hidden="true"></button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (! $isTimeOnlyCashierDashboard && ($clock_rows_today ?? []) !== []): ?>
                <div class="table-responsive mt-3">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Time in</th>
                                <th>In photo</th>
                                <th>Time out</th>
                                <th>Out photo</th>
                                <th>Duration</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($clock_rows_today ?? []) as $tr): ?>
                            <?php
                            $inRaw = (string) ($tr['clock_in_at'] ?? '');
                            $outRaw = (string) ($tr['clock_out_at'] ?? '');
                            $inTs = $inRaw !== '' ? strtotime($inRaw) : false;
                            $outTs = $outRaw !== '' ? strtotime($outRaw) : false;
                            $durSec = ($inTs !== false) ? (($outTs !== false ? $outTs : time()) - $inTs) : 0;
                            $durSec = max(0, (int) $durSec);
                            $durH = (int) floor($durSec / 3600);
                            $durM = (int) floor(($durSec % 3600) / 60);
                            $durLabel = sprintf('%dh %dm', $durH, $durM);
                            $autoNote = ($outTs !== false && $durSec < (8 * 3600)) ? 'Below 8 hours.' : '';
                            $note = trim((string) ($tr['note'] ?? '')) !== '' ? (string) ($tr['note'] ?? '') : $autoNote;
                            $inPhoto = trim((string) ($tr['clock_in_photo_path'] ?? ''));
                            $outPhoto = trim((string) ($tr['clock_out_photo_path'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e((string) ($tr['staff_name'] ?? '')) ?></td>
                                <td><?= e($inTs !== false ? date('h:i A', $inTs) : '-') ?></td>
                                <td>
                                    <?php if ($inPhoto !== ''): ?>
                                        <button type="button" class="btn p-0 border-0 bg-transparent js-attendance-photo-preview-trigger" data-photo-src="<?= e(url($inPhoto)) ?>" aria-label="View time-in photo">
                                            <img src="<?= e(url($inPhoto)) ?>" alt="Time-in photo" class="rounded border" style="width:44px;height:44px;object-fit:cover;">
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($outTs !== false ? date('h:i A', $outTs) : '—') ?></td>
                                <td>
                                    <?php if ($outPhoto !== ''): ?>
                                        <button type="button" class="btn p-0 border-0 bg-transparent js-attendance-photo-preview-trigger" data-photo-src="<?= e(url($outPhoto)) ?>" aria-label="View time-out photo">
                                            <img src="<?= e(url($outPhoto)) ?>" alt="Time-out photo" class="rounded border" style="width:44px;height:44px;object-fit:cover;">
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($durLabel) ?></td>
                                <td class="small <?= ($outTs !== false && $durSec < (8 * 3600)) ? 'text-warning' : 'text-muted' ?>"><?= e($note !== '' ? $note : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (! $isTimeOnlyCashierDashboard): ?>
    <?php
    $freeDashboardLimited = ! empty($free_dashboard_limited);
    $premiumBadge = '<span class="badge text-bg-warning text-dark ms-2">Premium</span>';
    $foldServiceAmount = (float) ($stats['fold_service_amount'] ?? 0);
    $showFoldAmount = $foldServiceAmount > 0;
    $foldAmount = (float) ($stats['fold_amount_today'] ?? 0);
    $foldTarget = (string) ($stats['fold_commission_target'] ?? 'branch');
    $orderTypeTotalsToday = (array) ($order_type_totals_today ?? []);
    $grossSales = (float) ($stats['sales_today'] ?? 0) + $foldAmount;
    $refunds = (float) ($stats['refunds_today'] ?? 0);
    $discounts = (float) ($stats['discounts_today'] ?? 0);
    $expenses = (float) ($stats['expenses_today'] ?? 0);
    $netSales = $grossSales - $refunds - $discounts - $expenses;
    $grossProfit = $netSales;
    ?>
    <div class="row g-3 mb-3">
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Gross sales</small>
                    <h3 class="mb-0"><?= e(format_money($grossSales)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Refunds</small>
                    <h3 class="mb-0"><?= e(format_money($refunds)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Discounts</small>
                    <h3 class="mb-0"><?= e(format_money($discounts)) ?></h3>
                </div>
            </div>
        </div>
        <?php if ($showFoldAmount): ?>
            <div class="col-lg col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted">Fold amount</small>
                        <h3 class="mb-0"><?= e(format_money($foldAmount)) ?></h3>
                        <div class="small text-muted mt-1">
                            <?= $foldTarget === 'branch' ? 'Included in sales (Branch target).' : 'Commission goes to staff (not added to sales).' ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Expenses</small>
                    <h3 class="mb-0"><?= e(format_money($expenses)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Net Sales</small>
                    <h3 class="mb-0 <?= $netSales < 0 ? 'text-danger' : '' ?>"><?= e(format_money($netSales)) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Gross profit</small>
                    <h3 class="mb-0 <?= $grossProfit < 0 ? 'text-danger' : '' ?>"><?= e(format_money($grossProfit)) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php
    $paymentBreakdown = (array) ($payment_breakdown ?? []);
    $paymentCards = [
        ['key' => 'cash', 'label' => 'Cash today'],
        ['key' => 'card', 'label' => 'Card today'],
        ['key' => 'gcash', 'label' => 'GCash today'],
        ['key' => 'paymaya', 'label' => 'PayMaya today'],
        ['key' => 'online_banking', 'label' => 'Online banking today'],
        ['key' => 'qr_payment', 'label' => 'QR payment today'],
    ];
    ?>
    <div class="row g-3 mb-3">
        <?php foreach ($paymentCards as $pc): ?>
            <div class="col-6 col-md-4 col-lg">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted"><?= e($pc['label']) ?></small>
                        <h4 class="mb-0"><?= e(format_money((float) ($paymentBreakdown[$pc['key']] ?? 0))) ?></h4>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card mt-3 dashboard-figures-card">
        <div class="card-body">
            <h6 class="card-title">Today dashboard figures<?= $freeDashboardLimited ? $premiumBadge : '' ?></h6>
            <?php if ($freeDashboardLimited): ?>
                <p class="small text-muted mb-0">Graphs are available on Premium.</p>
            <?php else: ?>
                <div class="dashboard-figures-chart-wrap">
                    <canvas id="salesTrendChart" aria-label="Today dashboard figures chart" role="img"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="row g-3 mt-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title"><?= ! empty($laundry_status_tracking_enabled) ? 'Load status' : 'Payment status' ?></h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">Amount</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($load_status_summary ?? []) as $row): ?>
                                <tr>
                                    <td><?= e((string) ($row['label'] ?? '')) ?></td>
                                    <td class="text-end"><?= (int) ($row['count'] ?? 0) ?></td>
                                    <td class="text-end"><?= e(format_money((float) ($row['amount'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                        <h6 class="card-title mb-0">Machine credits</h6>
                        <form method="GET" action="<?= e(url('/dashboard')) ?>" class="d-flex flex-wrap align-items-end gap-2">
                            <div>
                                <label class="form-label form-label-sm small mb-1" for="machineCreditFrom">Start</label>
                                <input
                                    type="date"
                                    class="form-control form-control-sm"
                                    id="machineCreditFrom"
                                    name="machine_from"
                                    value="<?= e((string) ($machine_credit_from ?? date('Y-m-d'))) ?>"
                                >
                            </div>
                            <div>
                                <label class="form-label form-label-sm small mb-1" for="machineCreditTo">End</label>
                                <input
                                    type="date"
                                    class="form-control form-control-sm"
                                    id="machineCreditTo"
                                    name="machine_to"
                                    value="<?= e((string) ($machine_credit_to ?? date('Y-m-d'))) ?>"
                                >
                            </div>
                            <div>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
                            </div>
                        </form>
                    </div>
                    <?php if (($machine_credit_balances ?? []) === []): ?>
                        <p class="small text-muted mb-0">No machines registered.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th class="text-end">Opening</th>
                                    <th class="text-end">Restock</th>
                                    <th class="text-end">Usage (Out)</th>
                                    <th class="text-end">Closing</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($machine_credit_balances ?? []) as $machine): ?>
                                    <tr>
                                        <td>
                                            <?= e((string) ($machine['machine_label'] ?? '')) ?>
                                            <span class="small text-muted">(<?= e((string) ($machine['machine_code'] ?? '')) ?>)</span>
                                        </td>
                                        <td class="text-end"><?= ! empty($machine['credit_required']) ? e(format_stock((float) ($machine['opening'] ?? 0))) : '—' ?></td>
                                        <td class="text-end"><?= ! empty($machine['credit_required']) ? e(format_stock((float) ($machine['restock'] ?? 0))) : '—' ?></td>
                                        <td class="text-end"><?= ! empty($machine['credit_required']) ? e(format_stock((float) ($machine['usage'] ?? 0))) : '—' ?></td>
                                        <td class="text-end"><?= ! empty($machine['credit_required']) ? e(format_stock((float) ($machine['closing'] ?? 0))) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Order type totals (Today)</h6>
                    <?php if ($orderTypeTotalsToday === []): ?>
                        <p class="small text-muted mb-0">No order type data today.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Order type</th>
                                    <th class="text-end">Total ordered</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($orderTypeTotalsToday as $row): ?>
                                    <tr>
                                        <td><?= e((string) ($row['label'] ?? $row['code'] ?? 'Order type')) ?></td>
                                        <td class="text-end"><?= e(format_stock((float) ($row['qty'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Birthdays today<?= $freeDashboardLimited ? $premiumBadge : '' ?></h6>
                    <?php if ($freeDashboardLimited): ?>
                        <p class="small text-muted mb-0">Birthday reminders are available on Premium.</p>
                    <?php elseif (($birthdays_today ?? []) === []): ?>
                        <p class="small text-muted mb-0">No birthday today.</p>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach (($birthdays_today ?? []) as $row): ?>
                                <li><?= e((string) ($row['name'] ?? '')) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Low stock alerts<?= $freeDashboardLimited ? $premiumBadge : '' ?></h6>
                    <?php if ($freeDashboardLimited): ?>
                        <p class="small text-muted mb-0">Low stock alerts are available on Premium.</p>
                    <?php elseif (($low_stock_items ?? []) === []): ?>
                        <p class="small text-muted mb-0">No low stock items.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach (($low_stock_items ?? []) as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= e((string) ($item['name'] ?? '')) ?></span>
                                    <span class="badge bg-danger">
                                        <?= e(format_stock((float) ($item['stock_quantity'] ?? 0))) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Top customers today<?= $freeDashboardLimited ? $premiumBadge : '' ?></h6>
                    <?php if ($freeDashboardLimited): ?>
                        <p class="small text-muted mb-0">Top customer insights are available on Premium.</p>
                    <?php elseif (($top_customers ?? []) === []): ?>
                        <p class="small text-muted mb-0">No customer data yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th class="text-end">Visits</th>
                                    <th class="text-end">Spending</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (($top_customers ?? []) as $row): ?>
                                    <tr>
                                        <td><?= e((string) ($row['name'] ?? '')) ?></td>
                                        <td class="text-end"><?= (int) ($row['frequency'] ?? 0) ?></td>
                                        <td class="text-end"><?= e(format_money((float) ($row['total_spending'] ?? 0))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Items out by service mode (Today)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Service mode</th>
                                <th class="text-end">Count</th>
                                <th>Inventory items out</th>
                                <th>Machines used</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($service_mode_summary ?? []) as $mode): ?>
                                <?php
                                $items = array_values((array) ($mode['items'] ?? []));
                                $machines = array_values((array) ($mode['machines'] ?? []));
                                ?>
                                <tr>
                                    <td><?= e((string) ($mode['label'] ?? '')) ?></td>
                                    <td class="text-end"><?= (int) ($mode['count'] ?? 0) ?></td>
                                    <td class="small"><?= e($items !== [] ? implode(', ', $items) : '—') ?></td>
                                    <td class="small"><?= e($machines !== [] ? implode(', ', $machines) : '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <div class="modal fade" id="attendancePhotoModal" tabindex="-1" aria-labelledby="attendancePhotoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="attendancePhotoModalLabel">Capture attendance photo</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <video id="attendancePhotoVideo" class="w-100 rounded border bg-dark-subtle" autoplay playsinline muted style="min-height: 220px;"></video>
                    <canvas id="attendancePhotoCanvas" class="d-none"></canvas>
                    <div class="small text-muted mt-2" id="attendancePhotoHint">Allow camera access, then tap Capture and continue. File upload is disabled by policy.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" id="attendancePhotoRetryBtn">Retry camera</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="attendancePhotoCaptureBtn">Capture & continue</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="attendancePhotoPreviewModal" tabindex="-1" aria-labelledby="attendancePhotoPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="attendancePhotoPreviewModalLabel">Attendance photo</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="attendancePhotoPreviewImage" src="" alt="Attendance preview" class="img-fluid rounded border" style="max-height:72vh;object-fit:contain;">
                </div>
            </div>
        </div>
    </div>
    <script src="<?= e(url('vendor/chartjs/chart.umd.min.js')) ?>"></script>
    <script>
    (() => {
        const trendEl = document.getElementById('salesTrendChart');
        if (trendEl && window.Chart) {
            const showFoldAmount = <?= json_encode($showFoldAmount, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const foldAmount = <?= json_encode((float) ($stats['fold_amount_today'] ?? 0), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const grossSales = (
                <?= json_encode((float) ($stats['sales_today'] ?? 0), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                + foldAmount
            );
            const refunds = <?= json_encode((float) ($stats['refunds_today'] ?? 0), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const discounts = <?= json_encode((float) ($stats['discounts_today'] ?? 0), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const expenses = <?= json_encode((float) ($stats['expenses_today'] ?? 0), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            const netSales = grossSales - refunds - discounts - expenses;
            const grossProfit = netSales;
            const labels = ['Gross sales', 'Refunds', 'Discounts'];
            const values = [grossSales, refunds, discounts];
            const backgroundColor = [
                'rgba(13, 110, 253, 0.65)',
                'rgba(220, 53, 69, 0.65)',
                'rgba(253, 126, 20, 0.65)',
            ];
            const borderColor = ['#0d6efd', '#dc3545', '#fd7e14'];
            if (showFoldAmount) {
                labels.push('Fold amount');
                values.push(foldAmount);
                backgroundColor.push('rgba(102, 16, 242, 0.65)');
                borderColor.push('#6610f2');
            }
            labels.push('Expenses', 'Net Sales', 'Gross profit');
            values.push(expenses, netSales, grossProfit);
            backgroundColor.push('rgba(111, 66, 193, 0.65)', 'rgba(32, 201, 151, 0.65)', 'rgba(25, 135, 84, 0.65)');
            borderColor.push('#6f42c1', '#20c997', '#198754');

            new Chart(trendEl, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Today',
                        data: values,
                        backgroundColor,
                        borderColor,
                        borderWidth: 1,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        }

    })();
    </script>
    <script>
    (() => {
        const forms = Array.from(document.querySelectorAll('.js-attendance-photo-form'));
        if (!forms.length || typeof bootstrap === 'undefined') return;

        const modalEl = document.getElementById('attendancePhotoModal');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const video = document.getElementById('attendancePhotoVideo');
        const canvas = document.getElementById('attendancePhotoCanvas');
        const captureBtn = document.getElementById('attendancePhotoCaptureBtn');
        const retryBtn = document.getElementById('attendancePhotoRetryBtn');
        const hint = document.getElementById('attendancePhotoHint');
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
                if (hint) hint.textContent = 'Webcam API is not available in this browser/session.';
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

        const openFlowForForm = async (form, e = null) => {
            const hidden = form.querySelector('input[name="photo_data"]');
            if (hidden && hidden.value && hidden.value.length > 128) return;
            if (e) e.preventDefault();
            pendingForm = form;
            modal?.show();
            await openCamera();
        };

        forms.forEach((form) => {
            const trigger = form.querySelector('.js-attendance-photo-trigger');
            if (trigger) {
                trigger.addEventListener('click', async (e) => {
                    await openFlowForForm(form, e);
                });
            }
            form.addEventListener('submit', async (e) => {
                await openFlowForForm(form, e);
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
            const realSubmit = pendingForm.querySelector('.js-attendance-hidden-submit');
            if (realSubmit instanceof HTMLElement) {
                realSubmit.click();
            } else {
                pendingForm.submit();
            }
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
    <script>
    (() => {
        if (typeof bootstrap === 'undefined') return;
        const modalEl = document.getElementById('attendancePhotoPreviewModal');
        const imgEl = document.getElementById('attendancePhotoPreviewImage');
        if (!modalEl || !imgEl) return;
        const modal = new bootstrap.Modal(modalEl);
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.js-attendance-photo-preview-trigger');
            if (!btn) return;
            e.preventDefault();
            const src = btn.getAttribute('data-photo-src') || '';
            if (!src) return;
            imgEl.src = src;
            modal.show();
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            imgEl.src = '';
        });
    })();
    </script>
<?php endif; ?>
