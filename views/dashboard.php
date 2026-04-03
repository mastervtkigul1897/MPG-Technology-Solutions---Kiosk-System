<?php

/** @var bool $is_super */
if (! empty($is_super)): ?>
    <div class="card">
        <div class="card-body">
            <h5>Total Tenants: <?= (int) ($stats['tenants_count'] ?? 0) ?></h5>
        </div>
    </div>
<?php else: ?>
    <?php if (! empty($dashboard_maintenance)): ?>
        <div class="alert alert-warning mb-3 border-0 shadow-sm d-flex gap-3 align-items-start">
            <i class="fa-solid fa-screwdriver-wrench fa-lg mt-1" aria-hidden="true"></i>
            <div>
                <strong class="d-block">Maintenance notice</strong>
                <div class="mb-0"><?= nl2br(e($dashboard_maintenance['message'] ?? '')) ?></div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (! empty($dashboard_subscription)): ?>
        <?php
        $dl = (int) ($dashboard_subscription['days_left'] ?? 0);
        $dayWord = $dl === 1 ? 'day' : 'days';
        ?>
        <div class="alert alert-info mb-3 border-0 shadow-sm d-flex gap-3 align-items-start">
            <i class="fa-solid fa-calendar-days fa-lg mt-1" aria-hidden="true"></i>
            <div>
                <strong class="d-block">Subscription ending soon</strong>
                <p class="mb-0">Your store subscription ends on <strong><?= e((string) ($dashboard_subscription['expires_label'] ?? '')) ?></strong>
                    (<?= $dl ?> <?= $dayWord ?> remaining). Please contact the application owner to renew and avoid interruption.</p>
            </div>
        </div>
    <?php endif; ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Total Orders Today</small><h4><?= (int) ($stats['orders_today'] ?? 0) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Total Damage Today</small><h4><?= number_format((float) ($stats['damages_today'] ?? 0), 2) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Total Inventory Items</small><h4><?= (int) ($stats['ingredients_count'] ?? 0) ?></h4></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><small>Total Products</small><h4><?= (int) ($stats['products_count'] ?? 0) ?></h4></div></div></div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <h6>Orders / Sales / Expense / Profit (Last 7 Days)</h6>
            <canvas id="dashChart" height="100"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h6>Orders and Sales by Period</h6>
            <?php
            $periodLabels = ['today' => 'Today', 'yesterday' => 'Yesterday', 'last_3_days' => 'Last 3 Days', 'last_7_days' => 'Last 7 Days', 'last_30_days' => 'Last 30 Days'];
            ?>
            <div class="table-responsive">
                <table class="table table-striped js-mobile-collapsible">
                    <thead><tr><th>Period</th><th>Orders</th><th>Sales</th><th>Expense</th><th>Profit</th></tr></thead>
                    <tbody>
                    <?php foreach ($periodLabels as $key => $label): ?>
                        <tr>
                            <td><?= e($label) ?></td>
                            <td><?= (int) ($periods[$key]['orders'] ?? 0) ?></td>
                            <td><?= number_format((float) ($periods[$key]['sales'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($periods[$key]['expenses'] ?? 0), 2) ?></td>
                            <td><?= number_format((float) ($periods[$key]['profit'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    new Chart(document.getElementById('dashChart'), {
        data: {
            labels: <?= json_embed($chart['labels'] ?? []) ?>,
            datasets: [
                { type: 'bar', label: 'Orders', data: <?= json_embed($chart['orders'] ?? []) ?> },
                { type: 'line', label: 'Sales', data: <?= json_embed($chart['sales'] ?? []) ?> },
                { type: 'line', label: 'Expense', data: <?= json_embed($chart['expenses'] ?? []) ?> },
                { type: 'line', label: 'Profit', data: <?= json_embed($chart['profit'] ?? []) ?> }
            ]
        }
    });
    </script>
<?php endif; ?>
