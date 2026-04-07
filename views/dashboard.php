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

    <?php
    $rangeFrom = (string) ($stats['range_from'] ?? date('Y-m-d'));
    $rangeTo = (string) ($stats['range_to'] ?? date('Y-m-d'));
    $today = date('Y-m-d');
    $isTodayOnly = ($rangeFrom === $today && $rangeTo === $today);
    ?>
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="get" action="">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">From date</label>
                    <input type="date" name="from" class="form-control" value="<?= e($rangeFrom) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted mb-1">To date</label>
                    <input type="date" name="to" class="form-control" value="<?= e($rangeTo) ?>">
                </div>
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/dashboard')) ?>">Today</a>
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
                    <h3 class="mb-0"><?= number_format((float) ($stats['sales_today'] ?? 0), 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted"><?= $isTodayOnly ? 'Expenses today' : 'Expenses (selected range)' ?></small>
                    <h3 class="mb-0"><?= number_format((float) ($stats['expenses_today'] ?? 0), 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted"><?= $isTodayOnly ? 'Net sales today' : 'Net sales (selected range)' ?></small>
                    <div class="small text-muted mb-1">Sales − expenses</div>
                    <?php
                    $net = (float) ($stats['net_sales_today'] ?? 0);
                    $netClass = $net < 0 ? 'text-danger' : '';
                    ?>
                    <h3 class="mb-0 <?= $netClass ?>"><?= number_format($net, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <?php
    $pt = (array) ($stats['payments_today'] ?? []);
    $suffix = $isTodayOnly ? 'today' : '(selected range)';
    $payCards = [
        ['key' => 'cash', 'label' => 'Cash '.$suffix],
        ['key' => 'card', 'label' => 'Card '.$suffix],
        ['key' => 'gcash', 'label' => 'GCash '.$suffix],
        ['key' => 'paymaya', 'label' => 'PayMaya '.$suffix],
        ['key' => 'online_banking', 'label' => 'Online banking '.$suffix],
    ];
    ?>
    <div class="row g-3 mb-3">
        <?php foreach ($payCards as $pc): ?>
            <div class="col-6 col-lg-4 col-xl-2">
                <div class="card h-100">
                    <div class="card-body">
                        <small class="text-muted"><?= e($pc['label']) ?></small>
                        <h4 class="mb-0"><?= number_format((float) ($pt[$pc['key']] ?? 0), 2) ?></h4>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
