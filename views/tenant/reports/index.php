<?php if (! empty($reports_maintenance)): ?>
    <div class="alert alert-warning mb-3 border-0 shadow-sm d-flex gap-3 align-items-start">
        <i class="fa-solid fa-screwdriver-wrench fa-lg mt-1" aria-hidden="true"></i>
        <div>
            <strong class="d-block">Maintenance notice</strong>
            <div class="mb-0"><?= nl2br(e($reports_maintenance['message'] ?? '')) ?></div>
        </div>
    </div>
<?php endif; ?>
<?php if (! empty($reports_subscription)): ?>
    <?php
    $dl = (int) ($reports_subscription['days_left'] ?? 0);
    $dayWord = $dl === 1 ? 'day' : 'days';
    ?>
    <div class="alert alert-info mb-3 border-0 shadow-sm d-flex gap-3 align-items-start">
        <i class="fa-solid fa-calendar-days fa-lg mt-1" aria-hidden="true"></i>
        <div>
            <strong class="d-block">Subscription ending soon</strong>
            <p class="mb-0">Your store subscription ends on <strong><?= e((string) ($reports_subscription['expires_label'] ?? '')) ?></strong>
                (<?= $dl ?> <?= $dayWord ?> remaining). Please contact the application owner to renew and avoid interruption.</p>
        </div>
    </div>
<?php endif; ?>

<?php
$rangeFrom = (string) ($stats['range_from'] ?? date('Y-m-d'));
$rangeTo = (string) ($stats['range_to'] ?? date('Y-m-d'));
$chartPreset = (string) ($stats['chart_preset'] ?? 'today');
$today = date('Y-m-d');
$isTodayOnly = ($rangeFrom === $today && $rangeTo === $today);
$presetOptions = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'last_3' => 'Last 3 days',
    'last_7' => 'Last 7 days',
    'last_14' => 'Last 14 days',
    'last_30' => 'Last 30 days',
    'this_month' => 'This month',
    'custom' => 'Custom range',
];
$showCustomDates = ($chartPreset === 'custom');
$manualExpensesTotal = (float) ($stats['manual_expenses_total'] ?? 0);
$freeReportsLimited = ! empty($free_reports_limited);
$premiumBadge = '<span class="badge text-bg-warning text-dark ms-2">Premium</span>';
?>
<?php if ($freeReportsLimited): ?>
    <div class="alert alert-warning mb-3 border-0 shadow-sm">
        <strong>Free Mode reports:</strong> you can view amounts only. Top customers, birthdays, graphs, daily sales, services sold, and inventory items out are Premium.
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
    <div class="small text-muted">Record / audit: print the summary below.</div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('/tenant/reports/export-excel?from='.$rangeFrom.'&to='.$rangeTo.'&preset='.$chartPreset)) ?>">
            <i class="fa-solid fa-file-excel me-1 text-success"></i>Export to Excel
        </a>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="fa-solid fa-print me-1"></i>Print report summary
        </button>
    </div>
</div>

<div class="reports-print-scope" style="overflow-x: hidden;">
<p class="d-none d-print-block small text-muted mb-2"><strong>Report period:</strong> <?= e($rangeFrom) ?> → <?= e($rangeTo) ?> (<?= e($presetOptions[$chartPreset] ?? $chartPreset) ?>)</p>
<div class="card mb-3 d-print-none">
    <div class="card-body">
        <form id="reportRangeForm" class="row g-2 align-items-end" method="get" action="<?= e(url('/tenant/reports')) ?>">
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1" for="report_preset">Period</label>
                <select name="preset" id="report_preset" class="form-select" autocomplete="off">
                    <?php foreach ($presetOptions as $val => $label): ?>
                        <option value="<?= e($val) ?>"<?= $chartPreset === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="report_custom_dates" class="col-12 <?= $showCustomDates ? '' : 'd-none' ?>">
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted mb-1">From date</label>
                        <input type="date" name="from" class="form-control" value="<?= e($rangeFrom) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted mb-1">To date</label>
                        <input type="date" name="to" class="form-control" value="<?= e($rangeTo) ?>">
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4 d-flex flex-wrap gap-2 align-items-end">
                <button type="submit" id="report_apply_btn" class="btn btn-primary<?= $showCustomDates ? '' : ' d-none' ?>">Apply</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/tenant/reports?preset=today')) ?>">Reset to today</a>
            </div>
        </form>
        <?php if ($isTodayOnly): ?>
            <div class="small text-muted mt-2">Showing totals for <strong>today</strong>.</div>
        <?php else: ?>
            <div class="small text-muted mt-2">Showing totals from <strong><?= e($rangeFrom) ?></strong> to <strong><?= e($rangeTo) ?></strong>.</div>
        <?php endif; ?>
    </div>
</div>

<?php if ($freeReportsLimited): ?>
<script>
(function () {
    var presetEl = document.getElementById('report_preset');
    var customWrap = document.getElementById('report_custom_dates');
    var applyBtn = document.getElementById('report_apply_btn');
    if (!presetEl || !customWrap || !applyBtn) {
        return;
    }
    presetEl.addEventListener('change', function () {
        var isCustom = this.value === 'custom';
        customWrap.classList.toggle('d-none', !isCustom);
        applyBtn.classList.toggle('d-none', !isCustom);
        if (!isCustom) {
            this.form.submit();
        }
    });
})();
</script>
<?php endif; ?>

<?php
$grossSales = (float) ($stats['gross_sales_total'] ?? 0);
$refunds = (float) ($stats['refunds_total'] ?? 0);
$discounts = (float) ($stats['discounts_total'] ?? 0);
$foldServiceAmount = (float) ($stats['fold_service_amount'] ?? 0);
$showFoldAmount = $foldServiceAmount > 0;
$foldAmount = (float) ($stats['fold_amount_total'] ?? 0);
$foldTarget = (string) ($stats['fold_commission_target'] ?? 'branch');
$orderTypeTotals = (array) ($stats['order_type_totals'] ?? []);
$inclusionItemsOut = (float) ($stats['inclusion_items_out_total'] ?? 0);
$addonItemsOut = (float) ($stats['addon_items_out_total'] ?? 0);
$totalItemsOut = (float) ($stats['total_items_out_total'] ?? ($inclusionItemsOut + $addonItemsOut));
$inventoryLedgerRows = (array) ($stats['inventory_ledger_rows'] ?? []);
$machineCreditLedgerRows = (array) ($stats['machine_credit_ledger_rows'] ?? []);
$machineIdleRows = (array) ($stats['machine_idle_rows'] ?? []);
$expenses = (float) ($stats['expenses_total'] ?? 0);
$netSales = (float) ($stats['net_sales'] ?? 0);
$grossProfit = (float) ($stats['gross_profit'] ?? ($netSales - $expenses));
$serviceModeSummary = (array) ($stats['service_mode_summary'] ?? []);
?>
<div class="row g-3 mb-3">
    <div class="col-lg col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Gross sales<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
                <h3 class="mb-0"><?= e(format_money($grossSales)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-lg col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Refunds<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
                <h3 class="mb-0"><?= e(format_money($refunds)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-lg col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Discounts<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
                <h3 class="mb-0"><?= e(format_money($discounts)) ?></h3>
            </div>
        </div>
    </div>
    <?php if ($showFoldAmount): ?>
        <div class="col-lg col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Fold amount<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
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
                <small class="text-muted">Expenses<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
                <h3 class="mb-0"><?= e(format_money($expenses)) ?></h3>
                <div class="small text-muted mt-1">From Expenses module only.</div>
            </div>
        </div>
    </div>
    <div class="col-lg col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Net sales<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
                <h3 class="mb-0 <?= $netSales < 0 ? 'text-danger' : '' ?>"><?= e(format_money($netSales)) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-lg col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted">Gross profit<?= $isTodayOnly ? ' today' : ' (selected range)' ?></small>
                <h3 class="mb-0 <?= $grossProfit < 0 ? 'text-danger' : '' ?>"><?= e(format_money($grossProfit)) ?></h3>
            </div>
        </div>
    </div>
</div>
<?php
$pm = (array) ($stats['payments_by_method'] ?? []);
$suffix = $isTodayOnly ? 'today' : '(selected range)';
$payCards = [
    ['key' => 'cash', 'label' => 'Cash '.$suffix],
    ['key' => 'card', 'label' => 'Card '.$suffix],
    ['key' => 'gcash', 'label' => 'GCash '.$suffix],
    ['key' => 'paymaya', 'label' => 'PayMaya '.$suffix],
    ['key' => 'online_banking', 'label' => 'Online banking '.$suffix],
    ['key' => 'qr_payment', 'label' => 'QR payment '.$suffix],
];
?>
<div class="row g-3 mb-3">
    <?php foreach ($payCards as $pc): ?>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted"><?= e($pc['label']) ?></small>
                    <h4 class="mb-0"><?= e(format_money((float) ($pm[$pc['key']] ?? 0))) ?></h4>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (! $freeReportsLimited): ?>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Order type totals<?= $isTodayOnly ? ' (today)' : ' (selected range)' ?></h6>
                    <?php if ($orderTypeTotals === []): ?>
                        <p class="small text-muted mb-0">No order type data for this period.</p>
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
                                <?php foreach ($orderTypeTotals as $row): ?>
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
                    <h6 class="card-title">Top customers (selected range)</h6>
                    <?php if (($top_customers ?? []) === []): ?>
                        <p class="small text-muted mb-0">No customer data for this period.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Customer</th><th class="text-end">Visits</th><th class="text-end">Spending</th></tr></thead>
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
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Birthdays in selected range</h6>
                    <?php if (($birthdays_in_range ?? []) === []): ?>
                        <p class="small text-muted mb-0">No birthdays in this period.</p>
                    <?php else: ?>
                        <ul class="mb-0">
                            <?php foreach (($birthdays_in_range ?? []) as $row): ?>
                                <li><?= e((string) ($row['name'] ?? '')) ?> - <?= e((string) ($row['birthday'] ?? '')) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Top customers<?= $premiumBadge ?></h6>
                    <p class="small text-muted mb-0">Top customer insights are available on Premium.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Birthdays<?= $premiumBadge ?></h6>
                    <p class="small text-muted mb-0">Birthday reports are available on Premium.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>


<?php if (! $freeReportsLimited): ?>
<?php
$repChartLabels = ['Gross sales', 'Refunds', 'Discounts'];
$repChartValues = [round_money($grossSales), round_money($refunds), round_money($discounts)];
if ($showFoldAmount) {
    $repChartLabels[] = 'Fold amount';
    $repChartValues[] = round_money($foldAmount);
}
$repChartLabels[] = 'Expenses';
$repChartLabels[] = 'Net sales';
$repChartLabels[] = 'Gross profit';
$repChartValues[] = round_money($expenses);
$repChartValues[] = round_money($netSales);
$repChartValues[] = round_money($grossProfit);
$repChart = [
    'labels' => $repChartLabels,
    'values' => $repChartValues,
];
$repChartJson = json_encode($repChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
?>
<div class="card mb-3 d-print-none">
    <div class="card-body">
        <h6 class="card-title mb-3">Report figures (selected period)</h6>
        <p class="small text-muted mb-2">Same metrics as Dashboard, recalculated using the selected date range / preset period.</p>
        <div style="min-height: 280px; position: relative;">
            <canvas id="reportsSalesChart" aria-label="Sales trend chart" role="img"></canvas>
        </div>
    </div>
</div>
<script src="<?= e(url('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script>
(function () {
    var presetEl = document.getElementById('report_preset');
    var customWrap = document.getElementById('report_custom_dates');
    var applyBtn = document.getElementById('report_apply_btn');
    if (presetEl && customWrap && applyBtn) {
        presetEl.addEventListener('change', function () {
            var isCustom = this.value === 'custom';
            customWrap.classList.toggle('d-none', !isCustom);
            applyBtn.classList.toggle('d-none', !isCustom);
            if (!isCustom) {
                this.form.submit();
            }
        });
    }
    function fmtMoney(v) {
        var n = Number(v);
        if (!isFinite(n)) {
            n = 0;
        }
        var s = n.toFixed(2).split('.');
        s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return s.join('.');
    }
    function paletteForCount(count) {
        var bgBase = [
            'rgba(13, 110, 253, 0.65)',
            'rgba(220, 53, 69, 0.65)',
            'rgba(253, 126, 20, 0.65)',
            'rgba(102, 16, 242, 0.65)',
            'rgba(111, 66, 193, 0.65)',
            'rgba(32, 201, 151, 0.65)',
            'rgba(25, 135, 84, 0.65)',
        ];
        var borderBase = ['#0d6efd', '#dc3545', '#fd7e14', '#6610f2', '#6f42c1', '#20c997', '#198754'];
        return {
            bg: bgBase.slice(0, Math.max(0, count)),
            border: borderBase.slice(0, Math.max(0, count)),
        };
    }
    var raw = <?= $repChartJson !== false ? $repChartJson : '{}' ?>;
    var canvas = document.getElementById('reportsSalesChart');
    if (!canvas || typeof Chart === 'undefined' || !raw.labels || !raw.labels.length) {
        return;
    }
    var ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: raw.labels,
            datasets: [{
                label: 'Amount',
                data: raw.values || [],
                backgroundColor: paletteForCount((raw.values || []).length).bg,
                borderColor: paletteForCount((raw.values || []).length).border,
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var label = ctx.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += fmtMoney(ctx.parsed.y);
                            return label;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (v) {
                            return fmtMoney(v);
                        },
                    },
                },
            },
        },
    });
})();
</script>
<?php endif; ?>

<?php if ($freeReportsLimited): ?>
<div class="card mb-3 d-print-none">
    <div class="card-body">
        <h6 class="card-title mb-2">Report figures<?= $premiumBadge ?></h6>
        <p class="small text-muted mb-0">Graphs are available on Premium.</p>
    </div>
</div>
<?php endif; ?>

<?php
$dailyDates = array_values((array) ($chart['dates'] ?? []));
$dailySales = array_values((array) ($chart['sales'] ?? []));
$dailyExpenses = array_values((array) ($chart['expenses'] ?? []));
$dailyNet = array_values((array) ($chart['profit'] ?? []));
$nDaily = count($dailyDates);
?>
<?php if (! $freeReportsLimited): ?>
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title">Inventory items out (Selected range)</h6>
                    <p class="small text-muted mb-2"><?= e(date('M j, Y', strtotime($rangeFrom))) ?> to <?= e(date('M j, Y', strtotime($rangeTo))) ?></p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                            <tr>
                                <th class="text-muted fw-normal">Inclusion items out</th>
                                <td class="text-end fw-semibold"><?= e(format_stock($inclusionItemsOut)) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted fw-normal">Add-on items out</th>
                                <td class="text-end fw-semibold"><?= e(format_stock($addonItemsOut)) ?></td>
                            </tr>
                            <tr>
                                <th>Total items out</th>
                                <td class="text-end fw-bold"><?= e(format_stock($totalItemsOut)) ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-1">Services sold</h6>
                    <p class="small text-muted mb-3">Same <strong>Period</strong> as the selector above (change period there, then Apply or pick a preset to refresh this page).</p>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr>
                                <th>Service type</th>
                                <th class="text-end" style="width: 100px;">Qty sold</th>
                                <th class="text-end" style="width: 120px;">Amount (₱)</th>
                            </tr>
                            </thead>
                            <tbody id="dailyOutsTbody">
                            <tr><td colspan="3" class="text-muted text-center py-3">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted mt-2">Based on completed transactions for <strong><?= e($rangeFrom) ?></strong><?= $rangeFrom !== $rangeTo ? ' – <strong>'.e($rangeTo).'</strong>' : '' ?>.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title mb-2">Daily sales</h6>
                    <p class="small text-muted mb-3">One row per calendar day in the selected period (e.g. Last 30 days = 30 rows), oldest to newest.</p>
                    <div class="table-responsive">
                        <?php if ($nDaily < 1): ?>
                            <p class="text-muted mb-0">No days in this range.</p>
                        <?php else: ?>
                            <table class="table table-striped table-sm mb-0">
                                <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col" class="text-end">Sales</th>
                                    <th scope="col" class="text-end">Expenses</th>
                                    <th scope="col" class="text-end">Net</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php for ($i = 0; $i < $nDaily; $i++): ?>
                                    <?php
                                    $rowDate = (string) ($dailyDates[$i] ?? '');
                                    $rowSales = (float) ($dailySales[$i] ?? 0);
                                    $rowExp = (float) ($dailyExpenses[$i] ?? 0);
                                    $rowNet = (float) ($dailyNet[$i] ?? 0);
                                    $dateLabel = $rowDate !== '' && strtotime($rowDate) !== false
                                        ? date('D, M j, Y', strtotime($rowDate))
                                        : $rowDate;
                                    $netClass = $rowNet < 0 ? 'text-danger' : '';
                                    ?>
                                    <tr>
                                        <td><?= e($dateLabel) ?></td>
                                        <td class="text-end font-monospace"><?= e(format_money($rowSales)) ?></td>
                                        <td class="text-end font-monospace"><?= e(format_money($rowExp)) ?></td>
                                        <td class="text-end font-monospace <?= $netClass ?>"><?= e(format_money($rowNet)) ?></td>
                                    </tr>
                                <?php endfor; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-2">Daily sales<?= $premiumBadge ?></h6>
            <p class="small text-muted mb-0">Daily sales breakdown is available on Premium.</p>
        </div>
    </div>
<?php endif; ?>

<?php if (! $freeReportsLimited): ?>
<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1">Inventory items out</h6>
                <p class="small text-muted mb-3">Transparency view of inventory consumed by paid orders for the same selected period.</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Item name</th>
                            <th class="text-end" style="width: 120px;">Qty out</th>
                            <th class="text-end" style="width: 140px;">Amount (₱)</th>
                        </tr>
                        </thead>
                        <tbody id="inventoryOutsTbody">
                        <tr><td colspan="3" class="text-muted text-center py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1">Items out by service mode<?= $isTodayOnly ? ' (today)' : ' (selected range)' ?><?= $premiumBadge ?></h6>
                <p class="small text-muted mb-3">Uses the same selected period as Inventory items out and Services sold.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Service mode</th>
                            <th class="text-end">Count</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($serviceModeSummary as $mode): ?>
                            <tr>
                                <td><?= e((string) ($mode['label'] ?? '')) ?></td>
                                <td class="text-end"><?= (int) ($mode['count'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1">Inclusion items out details</h6>
                <p class="small text-muted mb-3">Per-item out count for inclusion consumptions.</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Item name</th>
                            <th class="text-end" style="width: 120px;">Qty out</th>
                        </tr>
                        </thead>
                        <tbody id="inventoryOutsInclusionTbody">
                        <tr><td colspan="2" class="text-muted text-center py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1">Add-on items out details</h6>
                <p class="small text-muted mb-3">Per-item out count for paid add-ons.</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Item name</th>
                            <th class="text-end" style="width: 120px;">Qty out</th>
                        </tr>
                        </thead>
                        <tbody id="inventoryOutsAddonTbody">
                        <tr><td colspan="2" class="text-muted text-center py-3">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-1">Inventory stock ledger (Selected range)</h6>
                <p class="small text-muted mb-3">Opening + In - Out = Closing per item for the selected date range.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">Opening</th>
                            <th class="text-end">Stock In</th>
                            <th class="text-end">Stock Out</th>
                            <th class="text-end">Closing</th>
                            <th class="text-end">Stocks left</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($inventoryLedgerRows === []): ?>
                            <tr><td colspan="6" class="text-muted text-center py-3">No inventory ledger rows for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($inventoryLedgerRows as $row): ?>
                                <tr>
                                    <td><?= e((string) ($row['item_name'] ?? 'Item')) ?></td>
                                    <td class="text-end"><?= e(format_stock((float) ($row['opening'] ?? 0))) ?></td>
                                    <td class="text-end text-success"><?= e(format_stock((float) ($row['stock_in'] ?? 0))) ?></td>
                                    <td class="text-end text-danger"><?= e(format_stock((float) ($row['stock_out'] ?? 0))) ?></td>
                                    <td class="text-end fw-semibold"><?= e(format_stock((float) ($row['closing'] ?? 0))) ?></td>
                                    <td class="text-end fw-bold"><?= e(format_stock((float) ($row['closing'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-1">Machine idle time (Selected range)</h6>
                <p class="small text-muted mb-3">Idle gaps between logged machine usages. Helps detect long machine idle windows that may indicate unlogged transactions.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Machine</th>
                            <th class="text-end">Idle (hours)</th>
                            <th class="text-end">Idle gaps</th>
                            <th class="text-end">Longest idle (hours)</th>
                            <th>Longest idle range</th>
                            <th class="text-end">Usage logs</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($machineIdleRows === []): ?>
                            <tr><td colspan="6" class="text-muted text-center py-3">No machine idle records for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($machineIdleRows as $row): ?>
                                <tr>
                                    <td>
                                        <?= e((string) ($row['machine_label'] ?? 'Machine')) ?>
                                    </td>
                                    <td class="text-end fw-semibold"><?= e(number_format((float) ($row['idle_hours'] ?? 0), 2, '.', ',')) ?></td>
                                    <td class="text-end"><?= (int) ($row['idle_gaps'] ?? 0) ?></td>
                                    <td class="text-end"><?= e(number_format((float) ($row['longest_idle_hours'] ?? 0), 2, '.', ',')) ?></td>
                                    <td class="small"><?= e((string) ($row['longest_idle_range'] ?? '—')) ?></td>
                                    <td class="text-end"><?= (int) ($row['usage_logs'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-1">Machine credits ledger (Selected range)<?= $premiumBadge ?></h6>
                <p class="small text-muted mb-3">Opening + Restock - Usage = Closing per machine for the selected date range.</p>
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
                        <?php if ($machineCreditLedgerRows === []): ?>
                            <tr><td colspan="5" class="text-muted text-center py-3">No machine credit rows for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($machineCreditLedgerRows as $row): ?>
                                <tr>
                                    <td>
                                        <?= e((string) ($row['machine_label'] ?? 'Machine')) ?>
                                    </td>
                                    <td class="text-end"><?= ! empty($row['credit_required']) ? e(format_stock((float) ($row['opening'] ?? 0))) : '—' ?></td>
                                    <td class="text-end text-success"><?= ! empty($row['credit_required']) ? e(format_stock((float) ($row['restock'] ?? 0))) : '—' ?></td>
                                    <td class="text-end text-danger"><?= ! empty($row['credit_required']) ? e(format_stock((float) ($row['usage'] ?? 0))) : '—' ?></td>
                                    <td class="text-end fw-semibold"><?= ! empty($row['credit_required']) ? e(format_stock((float) ($row['closing'] ?? 0))) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const urlBase = <?= json_encode(url('/tenant/reports/daily-outs')) ?>;
    const rangeFrom = <?= json_encode($rangeFrom) ?>;
    const rangeTo = <?= json_encode($rangeTo) ?>;
    const tb = document.getElementById('dailyOutsTbody');
    const invTb = document.getElementById('inventoryOutsTbody');
    const inclusionTb = document.getElementById('inventoryOutsInclusionTbody');
    const addonTb = document.getElementById('inventoryOutsAddonTbody');
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const fmtQty = (q) => {
        const n = Number(q);
        if (!Number.isFinite(n)) return '0';
        return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
    };
    const fmtMoney = (v) => {
        const n = Number(v);
        if (!Number.isFinite(n)) return '0.00';
        const s = n.toFixed(2).split('.');
        s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return s.join('.');
    };

    const load = async () => {
        tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Loading…</td></tr>';
        if (invTb) {
            invTb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Loading…</td></tr>';
        }
        if (inclusionTb) {
            inclusionTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Loading…</td></tr>';
        }
        if (addonTb) {
            addonTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Loading…</td></tr>';
        }
        try {
            const u = new URL(urlBase, window.location.origin);
            u.searchParams.set('from', rangeFrom);
            u.searchParams.set('to', rangeTo);
            const res = await fetch(u.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Could not load products sold.</td></tr>';
                if (invTb) {
                    invTb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Could not load inventory out data.</td></tr>';
                }
                if (inclusionTb) {
                    inclusionTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Could not load inclusion items.</td></tr>';
                }
                if (addonTb) {
                    addonTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Could not load add-on items.</td></tr>';
                }
                return;
            }
            const rows = Array.isArray(body.data) ? body.data : [];
            if (!rows.length) {
                tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">No sales for this period.</td></tr>';
            } else {
                tb.innerHTML = rows.map((r) => {
                    const amt = r.line_amount != null ? fmtMoney(r.line_amount) : fmtMoney(0);
                    return `<tr><td>${esc(r.product_name)}</td><td class="text-end fw-semibold">${esc(fmtQty(r.qty))}</td><td class="text-end font-monospace">${esc(amt)}</td></tr>`;
                }).join('');
            }

            if (invTb) {
                const invRows = Array.isArray(body.inventory_out) ? body.inventory_out : [];
                if (!invRows.length) {
                    invTb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">No inventory out records for this period.</td></tr>';
                } else {
                    invTb.innerHTML = invRows.map((r) => {
                        const amt = r.amount_out != null ? fmtMoney(r.amount_out) : fmtMoney(0);
                        return `<tr><td>${esc(r.item_name)}</td><td class="text-end fw-semibold">${esc(fmtQty(r.qty_out))}</td><td class="text-end font-monospace">${esc(amt)}</td></tr>`;
                    }).join('');
                }
            }
            if (inclusionTb) {
                const rowsInc = Array.isArray(body.inventory_out_inclusion) ? body.inventory_out_inclusion : [];
                if (!rowsInc.length) {
                    inclusionTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">No inclusion out records for this period.</td></tr>';
                } else {
                    inclusionTb.innerHTML = rowsInc.map((r) => `<tr><td>${esc(r.item_name)}</td><td class="text-end fw-semibold">${esc(fmtQty(r.qty_out))}</td></tr>`).join('');
                }
            }
            if (addonTb) {
                const rowsAddon = Array.isArray(body.inventory_out_addon) ? body.inventory_out_addon : [];
                if (!rowsAddon.length) {
                    addonTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">No add-on out records for this period.</td></tr>';
                } else {
                    addonTb.innerHTML = rowsAddon.map((r) => `<tr><td>${esc(r.item_name)}</td><td class="text-end fw-semibold">${esc(fmtQty(r.qty_out))}</td></tr>`).join('');
                }
            }
        } catch {
            tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Could not load products sold.</td></tr>';
            if (invTb) {
                invTb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Could not load inventory out data.</td></tr>';
            }
            if (inclusionTb) {
                inclusionTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Could not load inclusion items.</td></tr>';
            }
            if (addonTb) {
                addonTb.innerHTML = '<tr><td colspan="2" class="text-muted text-center py-3">Could not load add-on items.</td></tr>';
            }
        }
    };

    load();
})();
</script>
<?php endif; ?>
<?php if ($freeReportsLimited): ?>
<div class="row g-3 mt-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1">Services sold<?= $premiumBadge ?></h6>
                <p class="small text-muted mb-0">Services sold breakdown is available on Premium.</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-1">Inventory items out<?= $premiumBadge ?></h6>
                <p class="small text-muted mb-0">Inventory-out details are available on Premium.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div><?php /* end .reports-print-scope */ ?>
