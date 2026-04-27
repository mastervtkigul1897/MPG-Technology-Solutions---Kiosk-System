<?php
// Premium pricing baseline
$premium_monthly_price = 249;
$premium_monthly_discount_pct = 0; // requested: no discount for 1-month plan
$premium_monthly_list_compare = (float) $premium_monthly_price;

// Fixed package pricing requested by owner.
$premium_3_list_compare = (float) $premium_monthly_price * 3.0; // 747 @ 249/mo
$premium_6_list_compare = (float) $premium_monthly_price * 6.0; // 1,494
$premium_3_total = 599;
$premium_6_total = 1099;

// Annual pricing policy: first year 1,999; renewal years 1,499.
$premium_12_list_compare = (float) $premium_monthly_price * 12.0; // 2,988 @ 249/mo
$premium_12_total = 1999; // first year
$premium_12_renewal_total = 1499; // renewal after first paid year
$premium_3_discount_pct = $premium_3_list_compare > 0.0
    ? max(0, min(99, (int) round(100.0 - (100.0 * (float) $premium_3_total / $premium_3_list_compare))))
    : 0;
$premium_6_discount_pct = $premium_6_list_compare > 0.0
    ? max(0, min(99, (int) round(100.0 - (100.0 * (float) $premium_6_total / $premium_6_list_compare))))
    : 0;
$premium_12_discount_pct = $premium_12_list_compare > 0.0
    ? max(0, min(99, (int) round(100.0 - (100.0 * (float) $premium_12_total / $premium_12_list_compare))))
    : 0;
$premium_12_renewal_discount_pct = $premium_12_list_compare > 0.0
    ? max(0, min(99, (int) round(100.0 - (100.0 * (float) $premium_12_renewal_total / $premium_12_list_compare))))
    : 0;
$eq_per_month = static function (float $total, int $months): float {
    return $months > 0 ? $total / $months : 0.0;
};
$fmt_peso = static function (float $n): string {
    return 'PHP ' . number_format($n, 2, '.', ',');
};
$fmt_peso_int = static function (float $n): string {
    return 'PHP ' . number_format($n, 0, '.', ',');
};
$eq3 = $eq_per_month((float) $premium_3_total, 3);
$eq6 = $eq_per_month((float) $premium_6_total, 6);
$eq12 = $eq_per_month((float) $premium_12_total, 12);
$eq12_renewal = $eq_per_month((float) $premium_12_renewal_total, 12);
$save_monthly_3 = (float) $premium_monthly_price - $eq3;
$save_monthly_6 = (float) $premium_monthly_price - $eq6;
$save_monthly_12 = (float) $premium_monthly_price - $eq12;
$save_monthly_12_renewal = (float) $premium_monthly_price - $eq12_renewal;
$branch_addon_monthly = (float) $premium_monthly_price * 0.5;
$branch_addon_3 = (float) $premium_3_total * 0.5;
$branch_addon_6 = (float) $premium_6_total * 0.5;
$branch_addon_12 = 750.0;
$branch_addon_12_renewal = 750.0;
?>
<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <div class="card h-100 border border-warning">
            <div class="card-body">
                <span class="badge text-bg-warning text-dark mb-2">FREE</span>
                <h6 class="mb-1">Lifetime Free</h6>
                <p class="display-6 mb-2">PHP 0</p>
                <ul class="small mb-0 ps-3">
                    <li>7 days premium access from account creation</li>
                    <li>After 7 days, account stays on free access (no subscription fee)</li>
                    <li>One owner login and one staff login</li>
                    <li>Up to 1 washer and 1 dryer</li>
                    <li><span class="badge text-bg-warning text-dark">Premium</span> Customer profile, payroll, rewards, expenses, damaged items, receipt config</li>
                    <li><span class="badge text-bg-warning text-dark">Premium</span> Dashboard insights and detailed reports</li>
                    <li class="text-success"><i class="fa-solid fa-xmark me-1"></i>Priority support response</li>
                    <li class="text-success"><i class="fa-solid fa-xmark me-1"></i>99.99% uptime commitment</li>
                    <li class="text-success"><i class="fa-solid fa-xmark me-1"></i>Automatic data backup</li>
                    <li class="text-success"><i class="fa-solid fa-xmark me-1"></i>Customizable to fit your business needs</li>
                </ul>
                <a href="<?= e(url('/register')) ?>" class="btn btn-warning text-dark w-100 mt-3 fw-semibold">
                    <i class="fa-solid fa-store me-1"></i>Create Store
                </a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100 border border-secondary-subtle">
            <div class="card-body">
                <span class="badge text-bg-success mb-2">100% Money-Back Guarantee</span>
                <h6 class="mb-1">Risk-Free Purchase (15 Days)</h6>
                <p class="small text-muted mb-0">
                    If you are not satisfied with the features and overall performance, you may request a full refund within 15 days from the date of purchase. We believe this customer-first policy is best for your business.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="mb-2 d-flex align-items-center gap-2">
    <span class="badge text-bg-primary">PREMIUM</span>
    <span class="small text-muted">Choose your preferred duration</span>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <?php if ($premium_monthly_discount_pct > 0): ?>
                    <span class="badge text-bg-danger mb-2"><?= (int) $premium_monthly_discount_pct ?>% OFF</span>
                <?php else: ?>
                    <span class="badge text-bg-secondary mb-2">Standard rate</span>
                <?php endif; ?>
                <h6 class="mb-1">1 Month</h6>
                <p class="display-6 mb-1"><?= e($fmt_peso_int((float) $premium_monthly_price)) ?></p>
                <div class="rounded-3 border border-secondary-subtle bg-light px-3 py-2 mb-3 small">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Billing</span>
                        <span class="fw-semibold">Monthly</span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Per month</span>
                        <span class="fs-6 fw-semibold text-body"><?= e($fmt_peso((float) $premium_monthly_price)) ?><span class="text-muted fw-normal small">/mo</span></span>
                    </div>
                </div>
                <ul class="small mb-0 ps-3">
                    <li>Unlimited products</li>
                    <li>Receipt printing enabled</li>
                    <li>Edit receipt/transaction data</li>
                    <li>Add staff accounts</li>
                    <li>New branch add-on: +<?= e($fmt_peso_int($branch_addon_monthly)) ?> (50% of plan price)</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Priority support response</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>99.99% uptime commitment</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Automatic data backup</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Customizable to fit your business needs</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <span class="badge text-bg-danger mb-2"><?= (int) $premium_3_discount_pct ?>% OFF</span>
                <h6 class="mb-1">3 Months</h6>
                <div class="small text-muted"><s><?= e($fmt_peso_int($premium_3_list_compare)) ?></s></div>
                <p class="display-6 mb-1"><?= e($fmt_peso_int((float) $premium_3_total)) ?></p>
                <div class="rounded-3 border border-secondary-subtle bg-light px-3 py-2 mb-3 small">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Billed upfront</span>
                        <span class="fw-semibold"><?= e($fmt_peso_int((float) $premium_3_total)) ?> <span class="text-muted fw-normal">· 3 months</span></span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Effective rate</span>
                        <span class="fs-6 fw-semibold text-body"><?= e($fmt_peso($eq3)) ?><span class="text-muted fw-normal small">/mo</span></span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle text-success">
                        <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>
                        <span class="fw-semibold">Save ~<?= e($fmt_peso($save_monthly_3)) ?>/mo</span>
                        <span class="text-muted"> vs monthly (PHP <?= e(number_format($premium_monthly_price, 0, '', ',')) ?>/mo)</span>
                    </div>
                </div>
                <ul class="small mb-0 ps-3">
                    <li>Unlimited products</li>
                    <li>Receipt printing enabled</li>
                    <li>Edit receipt/transaction data</li>
                    <li>Add staff accounts</li>
                    <li>New branch add-on: +<?= e($fmt_peso_int($branch_addon_3)) ?> (50% of plan price)</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Priority support response</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>99.99% uptime commitment</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Automatic data backup</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Customizable to fit your business needs</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <span class="badge text-bg-danger mb-2"><?= (int) $premium_6_discount_pct ?>% OFF</span>
                <h6 class="mb-1">6 Months</h6>
                <div class="small text-muted"><s><?= e($fmt_peso_int($premium_6_list_compare)) ?></s></div>
                <p class="display-6 mb-1"><?= e($fmt_peso_int((float) $premium_6_total)) ?></p>
                <div class="rounded-3 border border-secondary-subtle bg-light px-3 py-2 mb-3 small">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Billed upfront</span>
                        <span class="fw-semibold"><?= e($fmt_peso_int((float) $premium_6_total)) ?> <span class="text-muted fw-normal">· 6 months</span></span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Effective rate</span>
                        <span class="fs-6 fw-semibold text-body"><?= e($fmt_peso($eq6)) ?><span class="text-muted fw-normal small">/mo</span></span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle text-success">
                        <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>
                        <span class="fw-semibold">Save ~<?= e($fmt_peso($save_monthly_6)) ?>/mo</span>
                        <span class="text-muted"> vs monthly (PHP <?= e(number_format($premium_monthly_price, 0, '', ',')) ?>/mo)</span>
                    </div>
                </div>
                <ul class="small mb-0 ps-3">
                    <li>Unlimited products</li>
                    <li>Receipt printing enabled</li>
                    <li>Edit receipt/transaction data</li>
                    <li>Add staff accounts</li>
                    <li>New branch add-on: +<?= e($fmt_peso_int($branch_addon_6)) ?> (50% of plan price)</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Priority support response</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>99.99% uptime commitment</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Automatic data backup</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Customizable to fit your business needs</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card h-100 border border-primary">
            <div class="card-body">
                <span class="badge text-bg-primary mb-2">Best value</span>
                <span class="badge text-bg-danger mb-2 ms-1"><?= (int) $premium_12_discount_pct ?>% OFF</span>
                <h6 class="mb-1">1 Year</h6>
                <div class="small text-muted"><s><?= e($fmt_peso_int($premium_12_list_compare)) ?></s></div>
                <p class="display-6 mb-1"><?= e($fmt_peso_int((float) $premium_12_total)) ?></p>
                <div class="rounded-3 border border-secondary-subtle bg-light px-3 py-2 mb-3 small">
                    <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Billed upfront</span>
                        <span class="fw-semibold"><?= e($fmt_peso_int((float) $premium_12_total)) ?> <span class="text-muted fw-normal">· 12 months</span></span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                        <span class="text-muted">Effective rate</span>
                        <span class="fs-6 fw-semibold text-body"><?= e($fmt_peso($eq12)) ?><span class="text-muted fw-normal small">/mo</span></span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle text-success">
                        <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>
                        <span class="fw-semibold">Save ~<?= e($fmt_peso($save_monthly_12)) ?>/mo</span>
                        <span class="text-muted"> vs monthly (PHP <?= e(number_format($premium_monthly_price, 0, '', ',')) ?>/mo)</span>
                    </div>
                    <div class="mt-2 pt-2 border-top border-secondary-subtle">
                        <div class="d-flex justify-content-between align-items-baseline gap-2 flex-wrap">
                            <span class="text-muted">Renewal (after 1st year)</span>
                            <span class="fw-semibold"><?= e($fmt_peso_int((float) $premium_12_renewal_total)) ?>/year</span>
                        </div>
                        <div class="small text-success mt-1">
                            <i class="fa-solid fa-circle-check me-1" aria-hidden="true"></i>
                            Renewal saves ~<?= e($fmt_peso($save_monthly_12_renewal)) ?>/mo (<?= (int) $premium_12_renewal_discount_pct ?>% OFF vs monthly list)
                        </div>
                    </div>
                </div>
                <ul class="small mb-0 ps-3">
                    <li>Unlimited products</li>
                    <li>Receipt printing enabled</li>
                    <li>Edit receipt/transaction data</li>
                    <li>Add staff accounts</li>
                    <li>New branch add-on: +<?= e($fmt_peso_int($branch_addon_12)) ?></li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Priority support response</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>99.99% uptime commitment</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Automatic data backup</li>
                    <li class="text-success"><i class="fa-solid fa-check me-1"></i>Customizable to fit your business needs</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="https://www.facebook.com/mpgtechnologysolutionscom/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary w-100">
        <i class="fa-brands fa-facebook me-1"></i>Interested? Message us on Facebook
    </a>
</div>

<?php if (empty($pricing_in_app)): ?>
<div class="mt-4 d-grid">
    <a href="<?= e(url('/login')) ?>" class="btn btn-primary w-100">Login</a>
</div>
<?php else: ?>
<div class="mt-4 d-flex flex-wrap gap-2">
    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-outline-secondary">Back to dashboard</a>
</div>
<?php endif; ?>
