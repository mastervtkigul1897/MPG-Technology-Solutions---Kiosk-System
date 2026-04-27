<?php
/** @var bool $traffic_table_ready */
/** @var array<int,array<string,mixed>> $daily_traffic_rows */
/** @var array<string,int> $traffic_totals */
/** @var array<int,array<string,mixed>> $recent_visitors */
$tableReady = ! empty($traffic_table_ready);
$dailyRows = is_array($daily_traffic_rows ?? null) ? $daily_traffic_rows : [];
$totals = is_array($traffic_totals ?? null) ? $traffic_totals : [];
$recentVisitors = is_array($recent_visitors ?? null) ? $recent_visitors : [];
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <h6 class="mb-2">Traffic Tracking</h6>
        <p class="small text-muted mb-0">Tracks stakeholder website visits. Super admin visits are excluded automatically.</p>
    </div>
</div>

<?php if (! $tableReady): ?>
    <div class="alert alert-warning">Traffic logs table is not available yet. Run storage migrations first.</div>
<?php else: ?>
    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Today Visits</small>
                    <h4 class="mb-0"><?= (int) ($totals['today_visits'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Today Unique Visitors</small>
                    <h4 class="mb-0"><?= (int) ($totals['today_unique_visitors'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Daily Login Count</small>
                    <h4 class="mb-0"><?= (int) ($totals['today_login_count'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Daily Main Page Visits</small>
                    <h4 class="mb-0"><?= (int) ($totals['today_main_page_visits'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Last 7 Days Visits</small>
                    <h4 class="mb-0"><?= (int) ($totals['last_7_days_visits'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Last 30 Days Visits</small>
                    <h4 class="mb-0"><?= (int) ($totals['last_30_days_visits'] ?? 0) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <h6 class="mb-2">Daily Traffic (30 days)</h6>
            <?php if ($dailyRows === []): ?>
                <p class="small text-muted mb-0">No daily traffic records yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-end">Total Visits</th>
                                <th class="text-end">Unique Visitors</th>
                                <th class="text-end">Login Count</th>
                                <th class="text-end">Main Page Visits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dailyRows as $row): ?>
                                <tr>
                                    <td><?= e((string) ($row['visit_date'] ?? '')) ?></td>
                                    <td class="text-end"><?= (int) ($row['total_visits'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($row['unique_visitors'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($row['login_count'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($row['main_page_visits'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-3 p-md-4">
            <h6 class="mb-2">Recent Visitor Log</h6>
            <?php if ($recentVisitors === []): ?>
                <p class="small text-muted mb-0">No traffic logs yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Visited At</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Shop</th>
                                <th>Path</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentVisitors as $row): ?>
                                <?php
                                $name = trim((string) ($row['user_name'] ?? ''));
                                $email = trim((string) ($row['user_email'] ?? ''));
                                $userLabel = $name !== '' ? $name : ($email !== '' ? $email : 'Guest');
                                ?>
                                <tr>
                                    <td class="text-nowrap"><?= e((string) ($row['created_at'] ?? '')) ?></td>
                                    <td><?= e($userLabel) ?></td>
                                    <td><?= e((string) ($row['role'] ?? 'guest')) ?></td>
                                    <td><?= e((string) ($row['shop_name'] ?? '—')) ?></td>
                                    <td class="text-break"><?= e((string) ($row['path'] ?? '')) ?></td>
                                    <td class="text-nowrap"><?= e((string) ($row['ip_address'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
