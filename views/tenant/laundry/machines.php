<?php
$freeMachineLimitWashers = isset($free_machine_limit_washers) ? (int) $free_machine_limit_washers : 0;
$freeMachineLimitDryers = isset($free_machine_limit_dryers) ? (int) $free_machine_limit_dryers : 0;
$freeMachineLimited = $freeMachineLimitWashers > 0 || $freeMachineLimitDryers > 0;
$machineAssignmentEnabled = ! empty($machine_assignment_enabled);
$globalMachineCredit = (float) ($machine_global_credit_balance ?? 0);
?>
<?php if ($freeMachineLimited): ?>
<div class="alert alert-info mb-3 border-0 shadow-sm">
    <strong>Free Mode:</strong> machine access is limited to <?= $freeMachineLimitWashers ?> washers and <?= $freeMachineLimitDryers ?> dryers.
    Additional machines are Premium.
</div>
<?php endif; ?>
<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-1">Machines</h6>
        <p class="small text-muted mb-2">
            Set up all washers and dryers here before running daily operations. This machine master list is shared across
            Transactions, Kanban movement, and Kiosk order handling, so every status change and assignment reads from this same source.
        </p>
        <ul class="small text-muted mb-0 ps-3">
            <li><strong>Create clear labels:</strong> use unique names like <strong>Washer #1</strong> and <strong>Dryer #1</strong> so staff can assign jobs quickly without confusion.</li>
            <li><strong>Use Needs credit correctly:</strong> check <strong>Needs credit</strong> only for machines that should consume from the global pool; leave it off for non-credit machines.</li>
            <li><strong>Global credit logic:</strong> there is only one shared credit balance for all credit-required machines. Per-machine credit loading is removed to avoid fragmented balances.</li>
            <li><strong>Automatic movement behavior:</strong> during workflow transitions, the system checks machine availability first, then validates overall machine credits before allowing progress.</li>
            <li><strong>Blocking scenarios:</strong> if no required machine is available, or if overall machine credits are zero/insufficient, the transaction cannot proceed until corrected.</li>
            <li><strong>Rollback protection:</strong> if a transaction is returned to <strong>Pending</strong>, previously deducted overall machine credits are restored to keep balances accurate.</li>
            <li><strong>Kiosk impact:</strong> Kiosk machine choices and validation always follow this machine list, status availability, and global credit rules in real time.</li>
            <li><strong>Best practice:</strong> keep labels clean, monitor running machines, and maintain enough <strong>overall machine credits</strong> to avoid assignment delays during peak hours.</li>
        </ul>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="text-center mb-3">
            <div class="small text-muted text-uppercase">Overall machine credits</div>
            <div class="display-5 fw-bold"><?= e(format_stock($globalMachineCredit)) ?></div>
        </div>
        <form method="POST" action="<?= e(route('tenant.machines.store')) ?>" class="row g-2 align-items-end justify-content-center">
            <?= csrf_field() ?>
            <input type="hidden" name="update_global_machine_credit" value="1">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Action</label>
                <select class="form-select" name="credit_action">
                    <option value="add">Add credits</option>
                    <option value="deduct">Deduct credits</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1">Credits</label>
                <input class="form-control" type="number" min="0.01" step="0.01" name="credit_amount" placeholder="0.00" required>
            </div>
            <div class="col-12 col-md-2">
                <button class="btn btn-primary w-100" type="submit">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="small text-muted mb-0">
            Assignment mode is managed in <strong>Transactions → Track Machine Movement</strong>.
            <strong>Automatic machine movement</strong> uses this machine list for timed stage progression,
            while <strong>Manual machine movement</strong> lets staff choose machines per job in Kiosk/Daily Sales flow.
        </div>
    </div>
</div>

<div class="row g-3 align-items-stretch">
    <div class="col-lg-6">
        <div class="card h-100 border-primary border-opacity-25">
            <div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-droplet me-1 text-primary"></i>Washer — add &amp; list</h6>
                <form method="POST" action="<?= e(route('tenant.machines.store')) ?>" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="machine_kind" value="washer">
                    <div class="col-md-12">
                        <label class="form-label mb-1">Machine label</label>
                        <input class="form-control" name="machine_label" placeholder="Washer #1" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input js-credit-required" type="checkbox" name="credit_required" value="1">
                            <label class="form-check-label">Needs credit</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save washer</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Label</th>
                            <th>Needs credit</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($machines_washer ?? []) as $machine): ?>
                            <?php $isRunning = (string) ($machine['status'] ?? '') === 'running'; ?>
                            <tr>
                                <td>
                                    <?= e((string) ($machine['machine_label'] ?? '')) ?>
                                    <?php if ($freeMachineLimited): ?>
                                        <span class="badge text-bg-warning text-dark ms-1">Free-limited</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= ! empty($machine['credit_required']) ? 'Yes' : 'No' ?></td>
                                <td>
                                    <span class="badge <?= $isRunning ? 'bg-warning text-dark' : 'bg-success' ?>">
                                        <?= e($isRunning ? 'Running' : 'Available') ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary js-edit-machine"
                                            data-id="<?= (int) ($machine['id'] ?? 0) ?>"
                                            data-kind="washer"
                                            data-label="<?= e((string) ($machine['machine_label'] ?? '')) ?>"
                                            data-credit-required="<?= ! empty($machine['credit_required']) ? '1' : '0' ?>"
                                            title="Edit machine"
                                        ><i class="fa fa-pen"></i></button>
                                        <?php if (! $isRunning): ?>
                                            <form method="POST" action="<?= e(route('tenant.machines.destroy', ['id' => (int) ($machine['id'] ?? 0)])) ?>" onsubmit="return confirm('Delete this machine?');">
                                                <?= csrf_field() ?>
                                                <?= method_field('DELETE') ?>
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete machine"><i class="fa fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100 border-info border-opacity-25">
            <div class="card-body">
                <h6 class="mb-3"><i class="fa-solid fa-wind me-1 text-info"></i>Dryer — add &amp; list</h6>
                <form method="POST" action="<?= e(route('tenant.machines.store')) ?>" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="machine_kind" value="dryer">
                    <div class="col-md-12">
                        <label class="form-label mb-1">Machine label</label>
                        <input class="form-control" name="machine_label" placeholder="Dryer #1" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input js-credit-required" type="checkbox" name="credit_required" value="1">
                            <label class="form-check-label">Needs credit</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-info text-dark" type="submit">Save dryer</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Label</th>
                            <th>Needs credit</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($machines_dryer ?? []) as $machine): ?>
                            <?php $isRunning = (string) ($machine['status'] ?? '') === 'running'; ?>
                            <tr>
                                <td>
                                    <?= e((string) ($machine['machine_label'] ?? '')) ?>
                                    <?php if ($freeMachineLimited): ?>
                                        <span class="badge text-bg-warning text-dark ms-1">Free-limited</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= ! empty($machine['credit_required']) ? 'Yes' : 'No' ?></td>
                                <td>
                                    <span class="badge <?= $isRunning ? 'bg-warning text-dark' : 'bg-success' ?>">
                                        <?= e($isRunning ? 'Running' : 'Available') ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary js-edit-machine"
                                            data-id="<?= (int) ($machine['id'] ?? 0) ?>"
                                            data-kind="dryer"
                                            data-label="<?= e((string) ($machine['machine_label'] ?? '')) ?>"
                                            data-credit-required="<?= ! empty($machine['credit_required']) ? '1' : '0' ?>"
                                            title="Edit machine"
                                        ><i class="fa fa-pen"></i></button>
                                        <?php if (! $isRunning): ?>
                                            <form method="POST" action="<?= e(route('tenant.machines.destroy', ['id' => (int) ($machine['id'] ?? 0)])) ?>" onsubmit="return confirm('Delete this machine?');">
                                                <?= csrf_field() ?>
                                                <?= method_field('DELETE') ?>
                                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete machine"><i class="fa fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="machineEditModal" tabindex="-1" aria-labelledby="machineEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="machineEditForm" action="">
                <?= csrf_field() ?>
                <?= method_field('PUT') ?>
                <input type="hidden" name="machine_kind" id="machineEditKind" value="washer">
                <div class="modal-header">
                    <h6 class="modal-title" id="machineEditModalLabel">Edit machine</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div>
                        <label class="form-label mb-1" for="machineEditLabel">Machine label</label>
                        <input class="form-control" id="machineEditLabel" name="machine_label" required>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check mb-2">
                                <input class="form-check-input js-credit-required" id="machineEditCreditRequired" type="checkbox" name="credit_required" value="1">
                                <label class="form-check-label" for="machineEditCreditRequired">Needs credit</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const editModalEl = document.getElementById('machineEditModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('machineEditForm');
    const editKind = document.getElementById('machineEditKind');
    const editLabel = document.getElementById('machineEditLabel');
    const editCreditRequired = document.getElementById('machineEditCreditRequired');
    const baseUrl = '<?= e(url('/tenant/machines')) ?>';

    document.querySelectorAll('.js-edit-machine').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            if (!id || !editForm) return;
            editForm.action = `${baseUrl}/${id}`;
            if (editKind) editKind.value = btn.getAttribute('data-kind') || 'washer';
            if (editLabel) editLabel.value = btn.getAttribute('data-label') || '';
            if (editCreditRequired) editCreditRequired.checked = btn.getAttribute('data-credit-required') === '1';
            editModal?.show();
        });
    });
})();
</script>
