<?php
$freeMachineLimitWashers = isset($free_machine_limit_washers) ? (int) $free_machine_limit_washers : 0;
$freeMachineLimitDryers = isset($free_machine_limit_dryers) ? (int) $free_machine_limit_dryers : 0;
$freeMachineLimited = $freeMachineLimitWashers > 0 || $freeMachineLimitDryers > 0;
$machineAssignmentEnabled = ! empty($machine_assignment_enabled);
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
        <p class="small text-muted mb-0">Register washers and dryers separately. Same list is used on Daily Sales when selecting machines.</p>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="small fw-semibold text-secondary text-uppercase mb-2">Feature Toggles</div>
        <form method="POST" action="<?= e(route('tenant.machines.store')) ?>" class="d-flex flex-wrap gap-3 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="update_machine_assignment" value="1">
            <input type="hidden" name="machine_assignment_enabled" value="0">
            <div class="form-check mb-0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="machine_assignment_enabled"
                    id="machineAssignmentEnabled"
                    value="1"
                    <?= $machineAssignmentEnabled ? 'checked' : '' ?>
                    onchange="this.form.submit()"
                >
                <label class="form-check-label" for="machineAssignmentEnabled">
                    Enable machine assignment (auto by order type)
                </label>
            </div>
        </form>
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
                    <input type="hidden" name="machine_code" value="">
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
                    <div class="col-md-4 d-none js-credit-field-wrap">
                        <label class="form-label mb-1">Credit</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="credit_balance" value="0">
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
                            <th class="text-end">Credit</th>
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
                                <td class="text-end"><?= ! empty($machine['credit_required']) ? e(rtrim(rtrim(number_format((float) ($machine['credit_balance'] ?? 0), 4, '.', ''), '0'), '.')) : '—' ?></td>
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
                                            data-code="<?= e((string) ($machine['machine_code'] ?? '')) ?>"
                                            data-label="<?= e((string) ($machine['machine_label'] ?? '')) ?>"
                                            data-credit-required="<?= ! empty($machine['credit_required']) ? '1' : '0' ?>"
                                            data-credit-balance="<?= e((string) (float) ($machine['credit_balance'] ?? 0)) ?>"
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
                    <input type="hidden" name="machine_code" value="">
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
                    <div class="col-md-4 d-none js-credit-field-wrap">
                        <label class="form-label mb-1">Credit</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="credit_balance" value="0">
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
                            <th class="text-end">Credit</th>
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
                                <td class="text-end"><?= ! empty($machine['credit_required']) ? e(rtrim(rtrim(number_format((float) ($machine['credit_balance'] ?? 0), 4, '.', ''), '0'), '.')) : '—' ?></td>
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
                                            data-code="<?= e((string) ($machine['machine_code'] ?? '')) ?>"
                                            data-label="<?= e((string) ($machine['machine_label'] ?? '')) ?>"
                                            data-credit-required="<?= ! empty($machine['credit_required']) ? '1' : '0' ?>"
                                            data-credit-balance="<?= e((string) (float) ($machine['credit_balance'] ?? 0)) ?>"
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
                <input type="hidden" name="machine_code" id="machineEditCode" value="">
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
                        <div class="col-md-4 d-none js-credit-field-wrap">
                            <label class="form-label mb-1" for="machineEditCreditBalance">Credit</label>
                            <input class="form-control" id="machineEditCreditBalance" type="number" min="0" step="0.01" name="credit_balance" value="0">
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
    const editCode = document.getElementById('machineEditCode');
    const editLabel = document.getElementById('machineEditLabel');
    const editCreditRequired = document.getElementById('machineEditCreditRequired');
    const editCreditBalance = document.getElementById('machineEditCreditBalance');
    const baseUrl = '<?= e(url('/tenant/machines')) ?>';

    const syncCreditFields = (form) => {
        if (!form) return;
        const creditRequired = form.querySelector('.js-credit-required');
        const wrap = form.querySelector('.js-credit-field-wrap');
        const input = wrap?.querySelector('input[name="credit_balance"]');
        const enabled = !!creditRequired?.checked;
        wrap?.classList.toggle('d-none', !enabled);
        if (input) {
            input.disabled = !enabled;
            if (!enabled) input.value = '0';
        }
    };

    document.querySelectorAll('form').forEach((form) => {
        if (!form.querySelector('.js-credit-required')) return;
        syncCreditFields(form);
        form.querySelector('.js-credit-required')?.addEventListener('change', () => syncCreditFields(form));
    });

    document.querySelectorAll('.js-edit-machine').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            if (!id || !editForm) return;
            editForm.action = `${baseUrl}/${id}`;
            if (editKind) editKind.value = btn.getAttribute('data-kind') || 'washer';
            if (editCode) editCode.value = btn.getAttribute('data-code') || '';
            if (editLabel) editLabel.value = btn.getAttribute('data-label') || '';
            if (editCreditRequired) editCreditRequired.checked = btn.getAttribute('data-credit-required') === '1';
            if (editCreditBalance) editCreditBalance.value = btn.getAttribute('data-credit-balance') || '0';
            syncCreditFields(editForm);
            editModal?.show();
        });
    });
})();
</script>
