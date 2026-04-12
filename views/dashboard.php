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

    <p class="small text-muted mb-3">Totals below are for <strong>today</strong> only. For date ranges and trends, open <strong>Reports</strong> (store owner).</p>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Sales today</small>
                    <h3 class="mb-0"><?= e(format_money((float) ($stats['sales_today'] ?? 0))) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Expenses today</small>
                    <h3 class="mb-0"><?= e(format_money((float) ($stats['expenses_today'] ?? 0))) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Net today</small>
                    <div class="small text-muted mb-1">Sales − expenses</div>
                    <?php
                    $net = (float) ($stats['net_sales_today'] ?? 0);
                    $netClass = $net < 0 ? 'text-danger' : '';
                    ?>
                    <h3 class="mb-0 <?= $netClass ?>"><?= e(format_money($net)) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php
    $pt = (array) ($stats['payments_today'] ?? []);
    $payCards = [
        ['key' => 'cash', 'label' => 'Cash today'],
        ['key' => 'card', 'label' => 'Card today'],
        ['key' => 'gcash', 'label' => 'GCash today'],
        ['key' => 'paymaya', 'label' => 'PayMaya today'],
        ['key' => 'online_banking', 'label' => 'Online banking today'],
        ['key' => 'free', 'label' => 'FREE (employee) today'],
    ];
    ?>
    <div class="row g-3 mb-3">
        <?php foreach ($payCards as $pc): ?>
            <div class="col-6 col-lg-4 col-xl-2">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted"><?= e($pc['label']) ?></small>
                        <h4 class="mb-0"><?= e(format_money((float) ($pt[$pc['key']] ?? 0))) ?></h4>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php
    $freeMeals = (array) ($free_meals_today ?? []);
    ?>
    <div class="card mb-3 border-secondary">
        <div class="card-body">
            <h6 class="card-title mb-3"><i class="fa-solid fa-gift me-1"></i>FREE (employee / owner) meals today</h6>
            <?php if ($freeMeals === []): ?>
                <p class="text-muted small mb-0">No FREE transactions today.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th scope="col">Time</th>
                            <th scope="col">TX #</th>
                            <th scope="col">Products ordered</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($freeMeals as $fm): ?>
                            <?php
                            $tRaw = (string) ($fm['created_at'] ?? '');
                            $tLabel = $tRaw !== '' && strtotime($tRaw) !== false
                                ? date('g:i A', strtotime($tRaw))
                                : $tRaw;
                            $lines = [];
                            foreach ((array) ($fm['items'] ?? []) as $it) {
                                $nm = (string) ($it['name'] ?? '');
                                $q = (float) ($it['qty'] ?? 0);
                                $qFmt = fmod($q, 1.0) === 0.0 ? (string) (int) $q : rtrim(rtrim(sprintf('%.4f', $q), '0'), '.');
                                if ($nm !== '') {
                                    $lines[] = e($nm).' × '.$qFmt;
                                }
                            }
                            $prodCell = $lines !== [] ? implode('; ', $lines) : '—';
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= e($tLabel) ?></td>
                                <td class="font-monospace"><?= (int) ($fm['id'] ?? 0) ?></td>
                                <td class="small"><?= $prodCell ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
