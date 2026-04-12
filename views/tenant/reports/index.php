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
$lineItemsSum = (float) ($stats['line_items_sum'] ?? 0);
$reconciliationDelta = (float) ($stats['reconciliation_delta'] ?? 0);
$freeOrdersCount = (int) ($stats['free_orders_count'] ?? 0);
$freeLineSum = (float) ($stats['free_line_sum'] ?? 0);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 d-print-none">
    <div class="small text-muted">Record / audit: print the summary below.</div>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="fa-solid fa-print me-1"></i>Print report summary
    </button>
</div>

<div class="reports-print-scope">
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

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted"><?= $isTodayOnly ? 'Sales today' : 'Sales (selected range)' ?></small>
                <h3 class="mb-0"><?= e(format_money((float) ($stats['sales_total'] ?? 0))) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted"><?= $isTodayOnly ? 'Expenses today' : 'Expenses (selected range)' ?></small>
                <h3 class="mb-0"><?= e(format_money((float) ($stats['expenses_total'] ?? 0))) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <small class="text-muted"><?= $isTodayOnly ? 'Net today' : 'Net (selected range)' ?></small>
                <div class="small text-muted mb-1">Sales − expenses</div>
                <?php
                $net = (float) ($stats['net_sales'] ?? 0);
                $netClass = $net < 0 ? 'text-danger' : '';
                ?>
                <h3 class="mb-0 <?= $netClass ?>"><?= e(format_money($net)) ?></h3>
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
    ['key' => 'free', 'label' => 'FREE (employee) '.$suffix],
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

<div class="card mb-3 border-info">
    <div class="card-body">
        <h6 class="card-title mb-2"><i class="fa-solid fa-scale-balanced me-1"></i>Audit: sales vs line items</h6>
        <p class="small text-muted mb-2">
            <strong>Sales</strong> above is <code>SUM(transactions.total_amount)</code> (completed).
            <strong>Sum of line items</strong> is <code>SUM(transaction_items.line_total)</code> for the same date range.
            They should match; if not, you may have rounding, an edited order, or a different range than your manual sum.
        </p>
        <div class="row g-2 small">
            <div class="col-md-4">
                <div class="text-muted">Sales (transaction totals)</div>
                <div class="fw-semibold font-monospace"><?= e(format_money((float) ($stats['sales_total'] ?? 0))) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">Sum of line items</div>
                <div class="fw-semibold font-monospace"><?= e(format_money($lineItemsSum)) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted">Difference (sales − lines)</div>
                <?php
                $deltaClass = abs($reconciliationDelta) >= 0.01 ? 'text-warning' : 'text-success';
                ?>
                <div class="fw-semibold font-monospace <?= $deltaClass ?>"><?= e(format_money($reconciliationDelta)) ?></div>
            </div>
        </div>
        <?php if ($freeOrdersCount > 0): ?>
            <p class="small text-muted mb-0 mt-2">
                <strong>FREE (employee)</strong> in period: <strong><?= (int) $freeOrdersCount ?></strong> transaction(s)
                — sales <strong>₱0</strong>; sum of line items on FREE orders:
                <strong><?= e(format_money($freeLineSum)) ?></strong>
                (should be <strong>0</strong> after FREE checkout zeroes the total).
            </p>
        <?php endif; ?>
    </div>
</div>

<?php
$repChart = [
    'labels' => array_values((array) ($chart['labels'] ?? [])),
    'sales' => array_map(static fn ($v) => round_money((float) $v), array_values((array) ($chart['sales'] ?? []))),
    'expenses' => array_map(static fn ($v) => round_money((float) $v), array_values((array) ($chart['expenses'] ?? []))),
    'profit' => array_map(static fn ($v) => round_money((float) $v), array_values((array) ($chart['profit'] ?? []))),
];
$repChartJson = json_encode($repChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE);
?>
<div class="card mb-3 d-print-none">
    <div class="card-body">
        <h6 class="card-title mb-3">Sales trend</h6>
        <p class="small text-muted mb-2">Daily completed sales, manual expenses, and net (sales − expenses) for the selected period above.</p>
        <div style="min-height: 280px; position: relative;">
            <canvas id="reportsSalesChart" aria-label="Sales trend chart" role="img"></canvas>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js"></script>
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
        type: 'line',
        data: {
            labels: raw.labels,
            datasets: [
                {
                    label: 'Sales',
                    data: raw.sales,
                    borderColor: 'rgb(13, 110, 253)',
                    backgroundColor: 'rgba(13, 110, 253, 0.08)',
                    fill: false,
                    tension: 0.2,
                    pointRadius: 2,
                },
                {
                    label: 'Expenses',
                    data: raw.expenses,
                    borderColor: 'rgb(253, 126, 20)',
                    backgroundColor: 'rgba(253, 126, 20, 0.08)',
                    fill: false,
                    tension: 0.2,
                    pointRadius: 2,
                },
                {
                    label: 'Net',
                    data: raw.profit,
                    borderColor: 'rgb(25, 135, 84)',
                    backgroundColor: 'rgba(25, 135, 84, 0.08)',
                    fill: false,
                    tension: 0.2,
                    pointRadius: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
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

<?php
$dailyDates = array_values((array) ($chart['dates'] ?? []));
$dailySales = array_values((array) ($chart['sales'] ?? []));
$dailyExpenses = array_values((array) ($chart['expenses'] ?? []));
$dailyNet = array_values((array) ($chart['profit'] ?? []));
$nDaily = count($dailyDates);
?>
<div class="card mb-3">
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

<div class="card mt-3">
    <div class="card-body">
        <h6 class="mb-1">Products sold</h6>
        <p class="small text-muted mb-3">Same <strong>Period</strong> as the selector above (change period there, then Apply or pick a preset to refresh this page).</p>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                <tr>
                    <th>Product</th>
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

<script>
(() => {
    const urlBase = <?= json_encode(url('/tenant/reports/daily-outs')) ?>;
    const rangeFrom = <?= json_encode($rangeFrom) ?>;
    const rangeTo = <?= json_encode($rangeTo) ?>;
    const tb = document.getElementById('dailyOutsTbody');
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
        try {
            const u = new URL(urlBase, window.location.origin);
            u.searchParams.set('from', rangeFrom);
            u.searchParams.set('to', rangeTo);
            const res = await fetch(u.toString(), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Could not load products sold.</td></tr>';
                return;
            }
            const rows = Array.isArray(body.data) ? body.data : [];
            if (!rows.length) {
                tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">No sales for this period.</td></tr>';
                return;
            }
            tb.innerHTML = rows.map((r) => {
                const amt = r.line_amount != null ? fmtMoney(r.line_amount) : fmtMoney(0);
                return `<tr><td>${esc(r.product_name)}</td><td class="text-end fw-semibold">${esc(fmtQty(r.qty))}</td><td class="text-end font-monospace">${esc(amt)}</td></tr>`;
            }).join('');
        } catch {
            tb.innerHTML = '<tr><td colspan="3" class="text-muted text-center py-3">Could not load products sold.</td></tr>';
        }
    };

    load();
})();
</script>

</div><?php /* end .reports-print-scope */ ?>

