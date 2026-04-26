<?php
$foldTarget = (string) ($fold_commission_target ?? 'per_order_type');
$foldRate = (float) ($fold_service_amount ?? 0);
$payrollLocked = ! empty($payroll_locked) || ! empty($premium_trial_browse_lock);
$cutoffStartText = (string) ($cutoff_start ?? '');
$cutoffEndText = (string) ($cutoff_end ?? '');
$shopName = trim((string) ($shop_name ?? 'Laundry Shop'));
$commissionActive = ! empty($activate_commission);
$otActive = ! empty($activate_ot_incentives);
?>
<style>
.payroll-invoice-templates,
#payroll-print-mount {
    display: none;
}

.payroll-print-button {
    white-space: nowrap;
}

.payroll-invoice-page {
    background: #fff;
    color: #1d2733;
    font-family: Arial, sans-serif;
    font-size: 13px;
    line-height: 1.45;
    max-width: 780px;
    margin: 0 auto;
}

.payroll-invoice-header {
    align-items: flex-start;
    border-bottom: 3px solid #0ea5d7;
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding-bottom: 18px;
}

.payroll-invoice-kicker {
    color: #607486;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.payroll-invoice-title {
    color: #102f45;
    font-size: 28px;
    font-weight: 800;
    margin: 4px 0 6px;
}

.payroll-invoice-meta {
    border: 1px solid #d7e8f1;
    border-radius: 8px;
    min-width: 230px;
    padding: 12px 14px;
}

.payroll-invoice-meta-row {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 6px;
}

.payroll-invoice-meta-row:last-child {
    margin-bottom: 0;
}

.payroll-invoice-section {
    margin-top: 22px;
}

.payroll-invoice-section h3 {
    color: #102f45;
    font-size: 15px;
    margin: 0 0 10px;
}

.payroll-invoice-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.payroll-invoice-box {
    border: 1px solid #d7e8f1;
    border-radius: 8px;
    padding: 12px 14px;
}

.payroll-invoice-label {
    color: #607486;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.payroll-invoice-value {
    color: #12263a;
    font-size: 16px;
    font-weight: 700;
    margin-top: 3px;
}

.payroll-invoice-table {
    border-collapse: collapse;
    width: 100%;
}

.payroll-invoice-table th,
.payroll-invoice-table td {
    border-bottom: 1px solid #d7e8f1;
    padding: 10px 8px;
}

.payroll-invoice-table th {
    color: #607486;
    font-size: 11px;
    letter-spacing: .06em;
    text-align: left;
    text-transform: uppercase;
}

.payroll-invoice-table td:last-child,
.payroll-invoice-table th:last-child {
    text-align: right;
}

.payroll-invoice-total {
    align-items: center;
    background: #e9f8fd;
    border: 1px solid #bce8f6;
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    margin-top: 18px;
    padding: 14px 16px;
}

.payroll-invoice-total strong {
    color: #0b4f6c;
    font-size: 24px;
}

.payroll-invoice-signatures {
    display: grid;
    gap: 50px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 56px;
}

.payroll-invoice-signature-line {
    border-top: 1px solid #7b8b99;
    color: #607486;
    font-size: 12px;
    padding-top: 8px;
    text-align: center;
}

.payroll-invoice-note {
    color: #607486;
    font-size: 12px;
    margin-top: 22px;
}

@media print {
    @page {
        margin: 14mm;
        size: A4;
    }

    body * {
        visibility: hidden !important;
    }

    #payroll-print-mount,
    #payroll-print-mount * {
        visibility: visible !important;
    }

    #payroll-print-mount {
        display: block !important;
        left: 0;
        position: absolute;
        top: 0;
        width: 100%;
    }

    .payroll-invoice-page {
        max-width: none;
    }
}
</style>
<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<?php if ($payrollLocked): ?>
<div class="card">
    <div class="card-body">
        <h6 class="mb-1">Payroll <span class="badge text-bg-warning text-dark ms-1">Premium</span></h6>
        <p class="small text-muted mb-0">Payroll salary details are hidden in Free Mode.</p>
    </div>
</div>
<?php else: ?>
<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-1">Payroll (auto-calculated)</h6>
        <p class="small text-muted mb-2">
            Payroll is computed per cutoff using branch definitions and each staff setup.
            Cutoff: <strong><?= e((string) ($cutoff_start ?? '')) ?></strong> to <strong><?= e((string) ($cutoff_end ?? '')) ?></strong>
            (<?= (int) ($cutoff_days ?? 15) ?> days), Required hours/day: <strong><?= e((string) number_format((float) ($hours_per_day ?? 8), 2)) ?></strong>.
        </p>
        <div class="d-flex flex-wrap gap-3">
            <?php if ($foldTarget === 'per_order_type'): ?>
                <div class="small text-muted">Fold amount per load: <strong>Per order type</strong></div>
                <div class="small text-muted">Fold commission target: <strong>Per order type</strong></div>
            <?php else: ?>
                <div class="small text-muted">Fold amount per load: <strong><?= e(format_money($foldRate)) ?></strong></div>
                <div class="small text-muted">Fold commission target: <strong><?= e(ucfirst($foldTarget)) ?></strong></div>
            <?php endif; ?>
            <div class="small text-muted">OT incentives: <strong><?= $otActive ? 'Active' : 'Off' ?></strong></div>
            <?php if ($commissionActive): ?>
                <div class="small text-muted">Commission pool: <strong><?= e(format_money((float) ($branch_commission_pool ?? 0))) ?></strong></div>
                <div class="small text-muted">Quota/rate: <strong><?= (int) ($daily_load_quota ?? 0) ?> loads · <?= e(format_money((float) ($commission_rate_per_load ?? 0))) ?></strong></div>
            <?php endif; ?>
            <div class="small text-muted">Overall total salary (cutoff): <strong><?= e(format_money((float) ($overall_total_salary ?? 0))) ?></strong></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Staff</th>
                <th>Work days</th>
                <th class="text-end">Scheduled days</th>
                <th class="text-end">Required hours</th>
                <th class="text-end">Rendered hours</th>
                <th class="text-end">Pay days</th>
                <th class="text-end">Day rate</th>
                <th class="text-end">OT rate/hr</th>
                <th class="text-end">OT credit</th>
                <th class="text-end">OT pay</th>
                <th class="text-end">Fold sales</th>
                <th class="text-end">Fold commission</th>
                <?php if ($commissionActive): ?>
                    <th class="text-end">Quota commission</th>
                <?php endif; ?>
                <th class="text-end">Base pay</th>
                <th class="text-end">Total salary</th>
                <?php if (! $payrollLocked): ?>
                    <th class="text-end">Actions</th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($rows ?? []) as $index => $row): ?>
                <?php $invoiceTemplateId = 'payroll-invoice-template-'.(int) $index; ?>
                <tr>
                    <td><?= e((string) ($row['staff_name'] ?? '')) ?></td>
                    <td class="small"><?= e((string) ($row['work_days_text'] ?? 'Mon-Sun')) ?></td>
                    <td class="text-end"><?= (int) ($row['scheduled_days'] ?? 0) ?></td>
                    <td class="text-end"><?= e((string) number_format((float) ($row['required_hours'] ?? 0), 2)) ?></td>
                    <td class="text-end"><?= e((string) number_format((float) ($row['rendered_hours'] ?? 0), 2)) ?></td>
                    <td class="text-end"><?= e((string) number_format((float) ($row['pay_units'] ?? 0), 2)) ?></td>
                    <td class="text-end"><?= e(format_money((float) ($row['day_rate'] ?? 0))) ?></td>
                    <td class="text-end"><?= e(format_money((float) ($row['overtime_rate_per_hour'] ?? 0))) ?></td>
                    <td class="text-end"><?= e((string) number_format((float) ($row['overtime_hours_credit'] ?? 0), 2)) ?>h</td>
                    <td class="text-end"><?= e(format_money((float) ($row['overtime_pay'] ?? 0))) ?></td>
                    <td class="text-end"><?= (int) ($row['loads_folded'] ?? 0) ?></td>
                    <td class="text-end"><?= e(format_money((float) ($row['folding_fee_total'] ?? 0))) ?></td>
                    <?php if ($commissionActive): ?>
                        <td class="text-end"><?= ! empty($row['commission_eligible']) ? e(format_money((float) ($row['quota_commission'] ?? 0))) : '<span class="text-muted">Not eligible</span>' ?></td>
                    <?php endif; ?>
                    <td class="text-end"><?= e(format_money((float) ($row['base_pay'] ?? 0))) ?></td>
                    <td class="text-end fw-semibold"><?= e(format_money((float) ($row['total_salary'] ?? 0))) ?></td>
                    <?php if (! $payrollLocked): ?>
                        <td class="text-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary payroll-print-button"
                                data-payroll-invoice-template="<?= e($invoiceTemplateId) ?>"
                            >
                                Print invoice
                            </button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= ($payrollLocked ? 14 : 15) + ($commissionActive ? 1 : 0) ?>" class="text-center text-muted py-4">No payroll records found for this cutoff.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (! $payrollLocked): ?>
<div class="payroll-invoice-templates" aria-hidden="true">
    <?php foreach (($rows ?? []) as $index => $row): ?>
        <?php
        $invoiceTemplateId = 'payroll-invoice-template-'.(int) $index;
        $invoiceNumber = 'PR-'.date('Ymd', strtotime($cutoffEndText ?: 'now')).'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
        $staffName = (string) ($row['staff_name'] ?? '');
        $scheduledDays = (int) ($row['scheduled_days'] ?? 0);
        $requiredHours = number_format((float) ($row['required_hours'] ?? 0), 2);
        $renderedHours = number_format((float) ($row['rendered_hours'] ?? 0), 2);
        $otCredit = number_format((float) ($row['overtime_hours_credit'] ?? 0), 2);
        $loadsFolded = (int) ($row['loads_folded'] ?? 0);
        $overtimePayAmount = (float) ($row['overtime_pay'] ?? 0);
        $foldCommissionAmount = (float) ($row['folding_fee_total'] ?? 0);
        $quotaCommissionAmount = (float) ($row['quota_commission'] ?? 0);
        ?>
        <div id="<?= e($invoiceTemplateId) ?>">
            <div class="payroll-invoice-page">
                <div class="payroll-invoice-header">
                    <div>
                        <div class="payroll-invoice-kicker"><?= e($shopName) ?></div>
                        <div class="payroll-invoice-title">Payroll Invoice</div>
                        <div>Salary release slip for staff compensation.</div>
                    </div>
                    <div class="payroll-invoice-meta">
                        <div class="payroll-invoice-meta-row">
                            <span>Invoice No.</span>
                            <strong><?= e($invoiceNumber) ?></strong>
                        </div>
                        <div class="payroll-invoice-meta-row">
                            <span>Issued</span>
                            <strong><?= e(date('M d, Y')) ?></strong>
                        </div>
                        <div class="payroll-invoice-meta-row">
                            <span>Cutoff</span>
                            <strong><?= e($cutoffStartText) ?> - <?= e($cutoffEndText) ?></strong>
                        </div>
                    </div>
                </div>

                <div class="payroll-invoice-section payroll-invoice-grid">
                    <div class="payroll-invoice-box">
                        <div class="payroll-invoice-label">Staff</div>
                        <div class="payroll-invoice-value"><?= e($staffName) ?></div>
                    </div>
                    <div class="payroll-invoice-box">
                        <div class="payroll-invoice-label">Work days</div>
                        <div class="payroll-invoice-value"><?= e((string) ($row['work_days_text'] ?? 'Mon-Sun')) ?></div>
                    </div>
                    <div class="payroll-invoice-box">
                        <div class="payroll-invoice-label">Scheduled days</div>
                        <div class="payroll-invoice-value"><?= $scheduledDays ?></div>
                    </div>
                    <div class="payroll-invoice-box">
                        <div class="payroll-invoice-label">Rendered hours</div>
                        <div class="payroll-invoice-value"><?= e($renderedHours) ?>h</div>
                    </div>
                </div>

                <div class="payroll-invoice-section">
                    <h3>Salary Breakdown</h3>
                    <table class="payroll-invoice-table">
                        <thead>
                        <tr>
                            <th>Description</th>
                            <th>Basis</th>
                            <th>Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr>
                            <td>Base pay</td>
                            <td><?= e($renderedHours) ?> rendered hrs / <?= e($requiredHours) ?> required hrs, day rate <?= e(format_money((float) ($row['day_rate'] ?? 0))) ?></td>
                            <td><?= e(format_money((float) ($row['base_pay'] ?? 0))) ?></td>
                        </tr>
                        <?php if ($overtimePayAmount > 0): ?>
                            <tr>
                                <td>Overtime pay</td>
                                <td><?= e($otCredit) ?> credited hrs at <?= e(format_money((float) ($row['overtime_rate_per_hour'] ?? 0))) ?>/hr</td>
                                <td><?= e(format_money($overtimePayAmount)) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($foldCommissionAmount > 0): ?>
                            <tr>
                                <td>Fold commission</td>
                                <td><?= $loadsFolded ?> fold qty within cutoff</td>
                                <td><?= e(format_money($foldCommissionAmount)) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($quotaCommissionAmount > 0): ?>
                            <tr>
                                <td>Quota commission</td>
                                <td>Equal share of branch excess-load commission pool</td>
                                <td><?= e(format_money($quotaCommissionAmount)) ?></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="payroll-invoice-total">
                        <span>Total salary for release</span>
                        <strong><?= e(format_money((float) ($row['total_salary'] ?? 0))) ?></strong>
                    </div>
                </div>

                <div class="payroll-invoice-signatures">
                    <div class="payroll-invoice-signature-line">Prepared / Released by</div>
                    <div class="payroll-invoice-signature-line">Received by staff</div>
                </div>
                <div class="payroll-invoice-note">
                    This payroll invoice confirms the salary amount prepared for release for the selected cutoff period.
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div id="payroll-print-mount"></div>
<script>
document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-payroll-invoice-template]');
    if (!button) {
        return;
    }

    const template = document.getElementById(button.dataset.payrollInvoiceTemplate);
    const mount = document.getElementById('payroll-print-mount');
    if (!template || !mount) {
        return;
    }

    mount.innerHTML = template.innerHTML;
    window.print();
});
</script>
<?php endif; ?>
