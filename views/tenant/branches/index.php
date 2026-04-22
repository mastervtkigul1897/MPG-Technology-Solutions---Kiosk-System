<?php
/** @var array<string,mixed> $root_tenant */
/** @var int $current_tenant_id */
/** @var array<int,array<string,mixed>> $branches */
/** @var int $branch_limit */
/** @var array<int,string> $clone_defaults */
/** @var bool $machine_assignment_enabled */
/** @var float|int|string $fold_service_amount */
/** @var string $fold_commission_target */
/** @var bool $is_main_branch_context */
/** @var int $payroll_cutoff_days */
/** @var float|int|string $payroll_hours_per_day */
/** @var bool $activate_commission */
/** @var int $daily_load_quota */
/** @var float|int|string $commission_rate_per_load */
/** @var bool $activate_ot_incentives */
$rootTenant = $root_tenant ?? [];
$rows = $branches ?? [];
$currentTenantId = (int) ($current_tenant_id ?? 0);
$limit = (int) ($branch_limit ?? 1);
$defaults = $clone_defaults ?? ['categories', 'ingredients', 'requirements'];
$machineAssignmentEnabled = (bool) ($machine_assignment_enabled ?? true);
$foldServiceAmount = max(0.0, (float) ($fold_service_amount ?? 0));
$foldCommissionTarget = strtolower(trim((string) ($fold_commission_target ?? 'staff')));
$payrollCutoffDays = max(1, (int) ($payroll_cutoff_days ?? 15));
$payrollHoursPerDay = max(1.0, (float) ($payroll_hours_per_day ?? 8));
$activateCommission = (bool) ($activate_commission ?? false);
$dailyLoadQuota = max(0, (int) ($daily_load_quota ?? 0));
$commissionRatePerLoad = max(0.0, (float) ($commission_rate_per_load ?? 0));
$activateOtIncentives = (bool) ($activate_ot_incentives ?? false);
$canManageBranches = (bool) ($is_main_branch_context ?? false);
?>

<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <h6 class="mb-1">Your branch account</h6>
        <div class="small text-muted">Main account: <?= e((string) ($rootTenant['name'] ?? '')) ?> · Allowed branches: <?= $limit ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <h6 class="mb-2">Laundry branch config</h6>
        <p class="small text-muted mb-3">Set once per branch. Machine assignment and fold service amount are managed here (branch-level).</p>
        <form method="POST" action="<?= e(route('tenant.branches.laundry-config.update')) ?>" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md-4">
                <div class="form-check mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="machine_assignment_enabled"
                        id="machineAssignmentEnabled"
                        value="1"
                        <?= $machineAssignmentEnabled ? 'checked' : '' ?>
                    >
                    <label class="form-check-label" for="machineAssignmentEnabled">
                        Enable machine assignment (auto by order type)
                    </label>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" for="foldServiceAmount">Fold service amount (per load)</label>
                <input
                    class="form-control"
                    type="number"
                    min="0"
                    step="0.01"
                    id="foldServiceAmount"
                    name="fold_service_amount"
                    value="<?= e((string) $foldServiceAmount) ?>"
                >
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" for="foldCommissionTarget">Fold commission goes to</label>
                <select class="form-select" id="foldCommissionTarget" name="fold_commission_target">
                    <option value="staff" <?= $foldCommissionTarget === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="branch" <?= $foldCommissionTarget === 'branch' ? 'selected' : '' ?>>Branch</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" for="payrollCutoffDays">Payroll cutoff days</label>
                <input class="form-control" type="number" min="1" max="31" step="1" id="payrollCutoffDays" name="payroll_cutoff_days" value="<?= e((string) $payrollCutoffDays) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1" for="payrollHoursPerDay">Required hours/day</label>
                <input class="form-control" type="number" min="1" max="24" step="0.5" id="payrollHoursPerDay" name="payroll_hours_per_day" value="<?= e((string) number_format($payrollHoursPerDay, 2, '.', '')) ?>">
            </div>
            <div class="col-12 col-md-3">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="activate_ot_incentives" id="activateOtIncentives" value="1" <?= $activateOtIncentives ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activateOtIncentives">Activate OT incentives</label>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="activate_commission" id="activateCommission" value="1" <?= $activateCommission ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activateCommission">Activate Commission</label>
                </div>
            </div>
            <div class="col-12 row g-3 ms-0 px-0" id="commissionConfigFields">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1" for="dailyLoadQuota">Daily load quota</label>
                    <input class="form-control" type="number" min="0" step="1" id="dailyLoadQuota" name="daily_load_quota" value="<?= e((string) $dailyLoadQuota) ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1" for="commissionRatePerLoad">Commission rate / excess load</label>
                    <input class="form-control" type="number" min="0" step="0.01" id="commissionRatePerLoad" name="commission_rate_per_load" value="<?= e((string) number_format($commissionRatePerLoad, 2, '.', '')) ?>">
                </div>
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button class="btn btn-primary">Save laundry config</button>
            </div>
        </form>
    </div>
</div>

<?php if ($canManageBranches): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <h6 class="mb-3">Create new branch</h6>
            <form method="POST" action="<?= e(route('tenant.branches.store')) ?>" class="vstack gap-3">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Branch name</label>
                        <input class="form-control" name="name" maxlength="255" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label mb-1">Branch slug</label>
                        <input class="form-control" name="slug" maxlength="120" placeholder="e.g. my-store-branch-2" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label mb-1">Copy data from source branch</label>
                        <select class="form-select" name="source_tenant_id">
                            <option value="" selected>Fresh New Branch (no data copy)</option>
                            <?php foreach ($rows as $row): ?>
                                <option value="<?= (int) ($row['id'] ?? 0) ?>">
                                    <?= e((string) ($row['name'] ?? '')) ?> (<?= e((string) ($row['slug'] ?? '')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="form-label d-block mb-1">Copy options</label>
                    <div class="small text-muted mb-2">Optional. Leave all unchecked for a clean new branch. Excluded by default: staff/accounts, profile/contact/location, transactions, expenses, logs.</div>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach (['categories' => 'Categories', 'ingredients' => 'Inventory items', 'requirements' => 'Requirements'] as $k => $label): ?>
                            <label class="form-check">
                                <input class="form-check-input" type="checkbox" name="clone[]" value="<?= e($k) ?>" <?= in_array($k, $defaults, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <div class="small text-muted mb-2">Owner login is shared for all branches in your account. Staff accounts remain per-branch.</div>
                    <button class="btn btn-success">Create branch</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4 table-responsive">
        <h6 class="mb-3">Branches</h6>
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Main</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $id = (int) ($row['id'] ?? 0); ?>
                    <?php $active = (bool) ($row['is_active'] ?? false); ?>
                    <?php $main = (bool) ($row['is_main_branch'] ?? false); ?>
                    <tr>
                        <td><?= $id ?></td>
                        <td><?= e((string) ($row['name'] ?? '')) ?></td>
                        <td><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><?= $main ? '<span class="badge text-bg-primary">Main</span>' : '<span class="badge text-bg-light text-dark border">Branch</span>' ?></td>
                        <td><?= $active ? '<span class="badge text-bg-success">Open</span>' : '<span class="badge text-bg-secondary">Closed</span>' ?></td>
                        <td class="text-end">
                            <?php if ($canManageBranches && ! $main && $active): ?>
                                <form method="POST" action="<?= e(route('tenant.branches.set-main', ['id' => $id])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-primary">Set main</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canManageBranches && ! ($main && $active)): ?>
                                <form method="POST"
                                      action="<?= e(route('tenant.branches.toggle-active', ['id' => $id])) ?>"
                                      class="d-inline js-branch-toggle-form"
                                      data-branch-name="<?= e((string) ($row['name'] ?? 'Branch')) ?>"
                                      data-close-confirm="<?= $active ? '1' : '0' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                    <button class="btn btn-sm <?= $active ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <?= $active ? 'Close branch' : 'Open branch' ?>
                                    </button>
                                </form>
                            <?php elseif ($canManageBranches): ?>
                                <span class="badge text-bg-light text-dark border">Main branch stays open</span>
                            <?php else: ?>
                                <span class="small text-muted">Config only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(() => {
    const activateCommission = document.getElementById('activateCommission');
    const commissionFields = document.getElementById('commissionConfigFields');
    const syncCommissionFields = () => {
        if (!activateCommission || !commissionFields) return;
        commissionFields.classList.toggle('d-none', !activateCommission.checked);
        commissionFields.querySelectorAll('input').forEach((el) => {
            el.disabled = !activateCommission.checked;
        });
    };
    activateCommission?.addEventListener('change', syncCommissionFields);
    syncCommissionFields();

    document.querySelectorAll('.js-branch-toggle-form[data-close-confirm="1"]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.mpgConfirmBypass === '1') {
                form.dataset.mpgConfirmBypass = '0';
                return;
            }
            e.preventDefault();
            const branchName = form.getAttribute('data-branch-name') || 'this branch';
            if (typeof Swal === 'undefined') {
                window.mpgConfirm(`Close ${branchName}?`, {
                    title: 'Close Branch?',
                    confirmButtonText: 'Yes, close branch',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#dc3545',
                }).then((ok) => {
                    if (ok) {
                        form.dataset.mpgConfirmBypass = '1';
                        if (typeof form.requestSubmit === 'function') form.requestSubmit();
                        else form.submit();
                    }
                });
                return;
            }
            const res = await Swal.fire({
                icon: 'warning',
                title: 'Close Branch?',
                text: `You are about to close ${branchName}. This branch will not be usable until reopened.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, close branch',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc3545',
            });
            if (res.isConfirmed) {
                form.dataset.mpgConfirmBypass = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }
        });
    });
})();
</script>
