<?php
/** @var list<array<string, mixed>> $order_types */
$rows = $order_types ?? [];
$rewardSystemActive = ! empty($reward_system_active);
$kindLabels = [
    'full_service' => 'Full service',
    'wash_only' => 'Wash only',
    'dry_only' => 'Dry only',
    'rinse_only' => 'Rinse only',
    'dry_cleaning' => 'Dry Cleaning',
    'other' => 'Other',
];
$supplyLabels = [
    'none' => 'No service supply picks (hide detergent / fabcon / bleach)',
    'full_service' => 'Full service inclusions (1× det + fab + optional bleach)',
    'wash_supplies' => 'Wash supplies (1× det + fab + optional bleach for wash services)',
    'rinse_supplies' => 'Rinse supplies (optional 1× fabric conditioner only)',
];
?>
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">Order Pricing</h5>
        <p class="small text-muted mb-0">
            Add as many order types as you need. Each appears in <strong>Daily Sales → Add service</strong>. Use <strong>Service supply block</strong> to control which stock selectors appear (e.g. hide all chemicals for dry or rinse). Use <strong>Show add-on supplies</strong> for charged extras on top of the base price.
            <?php if ($rewardSystemActive): ?>
                When <strong>Activate Reward System</strong> is on (Rewards page), you can mark which order types add to the customer reward load on paid sales. Turn it off per type for legacy data entry or services that should not earn stamps.
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="card border-primary border-opacity-25 mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-1">Order types</h6>
            <p class="small text-muted mb-0">Add new services in a modal so the list stays easy to scan.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderTypeModal">
            <i class="fa-solid fa-plus me-1"></i> Add order type
        </button>
    </div>
</div>

<div class="modal fade" id="addOrderTypeModal" tabindex="-1" aria-labelledby="addOrderTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="<?= e(route('tenant.laundry-order-pricing.types.store')) ?>" class="modal-content" data-mpg-ajax-reset="true">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h6 class="modal-title" id="addOrderTypeModalLabel">Add order type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_ot_label">Display name</label>
                        <input type="text" class="form-control" name="label" id="new_ot_label" required maxlength="150" placeholder="e.g. Express full service">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_ot_kind">Service behavior</label>
                        <select class="form-select" name="service_kind" id="new_ot_kind" required>
                            <?php foreach ($kindLabels as $k => $lab): ?>
                                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_ot_price">Price (₱)</label>
                        <input type="number" class="form-control" name="price_per_load" id="new_ot_price" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_ot_sort">Sort</label>
                        <input type="text" class="form-control" id="new_ot_sort" value="Auto increment" disabled>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium" for="new_supply_block">Service supply block</label>
                        <select class="form-select" name="supply_block" id="new_supply_block">
                            <?php foreach ($supplyLabels as $k => $lab): ?>
                                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3 align-items-start">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_addon_supplies" value="1" id="new_show_addon" checked>
                                <label class="form-check-label" for="new_show_addon">Show add-on supplies (extra charged qty)</label>
                            </div>
                            <div class="form-check d-none" id="new_required_weight_wrap">
                                <input class="form-check-input" type="checkbox" name="required_weight" value="1" id="new_required_weight">
                                <label class="form-check-label" for="new_required_weight">Required Weight</label>
                            </div>
                            <?php if ($rewardSystemActive): ?>
                            <div class="form-check" id="new_include_rewards_wrap">
                                <input class="form-check-input" type="checkbox" name="include_in_rewards" value="1" id="new_include_rewards" checked>
                                <label class="form-check-label" for="new_include_rewards">Include to Reward System</label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($rewardSystemActive): ?>
                    <div class="col-12">
                        <p class="small text-muted mb-0">When checked, marking this order type as <strong>paid</strong> adds 1 toward the customer’s reward load (if rewards are active). Full service defaults to checked.</p>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <p class="small text-muted mb-0">A short internal <strong>code</strong> is generated from the name for history and receipts. It cannot be changed after creation.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add order type</button>
            </div>
        </form>
    </div>
</div>

<?php if ($rows === []): ?>
    <div class="alert alert-light border">No order types in the database yet. Open Daily Sales once or run migrations; defaults are created automatically.</div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Display name</th>
                    <th>Service behavior</th>
                    <th>Required Weight</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Sort</th>
                    <th>Active</th>
                    <th>Add-ons</th>
                    <?php if ($rewardSystemActive): ?><th>Include to Reward</th><?php endif; ?>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $rid = (int) ($r['id'] ?? 0); ?>
                    <tr>
                        <td class="small font-monospace"><?= e((string) ($r['code'] ?? '')) ?></td>
                        <td><?= e((string) ($r['label'] ?? '')) ?></td>
                        <td><?= e($kindLabels[(string) ($r['service_kind'] ?? '')] ?? (string) ($r['service_kind'] ?? '')) ?></td>
                        <td><?= ! empty($r['required_weight']) ? 'Yes' : 'No' ?></td>
                        <td class="text-end"><?= e(number_format((float) ($r['price_per_load'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= (int) ($r['sort_order'] ?? 0) ?></td>
                        <td><?= ! empty($r['is_active']) ? 'Yes' : 'No' ?></td>
                        <td><?= ! empty($r['show_addon_supplies']) ? 'Yes' : 'No' ?></td>
                        <?php if ($rewardSystemActive): ?><td><?= ! empty($r['include_in_rewards']) ? 'Yes' : 'No' ?></td><?php endif; ?>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary js-edit-order-type"
                                    data-id="<?= $rid ?>"
                                    data-label="<?= e((string) ($r['label'] ?? '')) ?>"
                                    data-service-kind="<?= e((string) ($r['service_kind'] ?? 'full_service')) ?>"
                                    data-price="<?= e((string) (float) ($r['price_per_load'] ?? 0)) ?>"
                                    data-sort-order="<?= e((string) (int) ($r['sort_order'] ?? 0)) ?>"
                                    data-is-active="<?= ! empty($r['is_active']) ? '1' : '0' ?>"
                                    data-supply-block="<?= e((string) ($r['supply_block'] ?? 'none')) ?>"
                                    data-show-addon-supplies="<?= ! empty($r['show_addon_supplies']) ? '1' : '0' ?>"
                                    data-required-weight="<?= ! empty($r['required_weight']) ? '1' : '0' ?>"
                                    data-include-in-rewards="<?= ! empty($r['include_in_rewards']) ? '1' : '0' ?>"
                                >
                                    Edit
                                </button>
                                <form method="POST" action="<?= e(route('tenant.laundry-order-pricing.types.destroy', ['id' => $rid])) ?>" onsubmit="return confirm('Delete this order type? Only allowed if no transactions use it.');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="editOrderTypeModal" tabindex="-1" aria-labelledby="editOrderTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" id="editOrderTypeForm" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h6 class="modal-title" id="editOrderTypeModalLabel">Edit order type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_ot_label">Display name</label>
                        <input type="text" class="form-control" name="label" id="edit_ot_label" required maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_ot_kind">Service behavior</label>
                        <select class="form-select" name="service_kind" id="edit_ot_kind" required>
                            <?php foreach ($kindLabels as $k => $lab): ?>
                                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_ot_price">Price (₱)</label>
                        <input type="number" class="form-control" name="price_per_load" id="edit_ot_price" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_ot_sort">Sort</label>
                        <input type="number" class="form-control" name="sort_order" id="edit_ot_sort" step="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-medium" for="edit_supply_block">Service supply block</label>
                        <select class="form-select" name="supply_block" id="edit_supply_block">
                            <?php foreach ($supplyLabels as $k => $lab): ?>
                                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3 align-items-start">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">Active</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_addon_supplies" value="1" id="edit_show_addon">
                                <label class="form-check-label" for="edit_show_addon">Show add-on supplies (extra charged qty)</label>
                            </div>
                            <div class="form-check d-none" id="edit_required_weight_wrap">
                                <input class="form-check-input" type="checkbox" name="required_weight" value="1" id="edit_required_weight">
                                <label class="form-check-label" for="edit_required_weight">Required Weight</label>
                            </div>
                            <?php if ($rewardSystemActive): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_in_rewards" value="1" id="edit_include_rewards">
                                <label class="form-check-label" for="edit_include_rewards">Include to Reward System</label>
                            </div>
                            <?php endif; ?>
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

<script>
(function () {
    const kind = document.getElementById('new_ot_kind');
    const supply = document.getElementById('new_supply_block');
    const addon = document.getElementById('new_show_addon');
    const requiredWeight = document.getElementById('new_required_weight');
    const requiredWeightWrap = document.getElementById('new_required_weight_wrap');
    const incRewards = document.getElementById('new_include_rewards');
    const modal = document.getElementById('addOrderTypeModal');
    if (!kind || !supply || !addon) return;
    const sync = () => {
        const v = kind.value;
        if (v === 'full_service') {
            supply.value = 'full_service';
            addon.checked = true;
            if (incRewards) incRewards.checked = true;
        } else if (v === 'wash_only') {
            supply.value = 'wash_supplies';
            addon.checked = true;
            if (incRewards) incRewards.checked = false;
        } else if (v === 'dry_only') {
            supply.value = 'none';
            addon.checked = false;
            if (incRewards) incRewards.checked = false;
        } else if (v === 'rinse_only') {
            supply.value = 'rinse_supplies';
            addon.checked = false;
            if (incRewards) incRewards.checked = false;
        } else if (v === 'dry_cleaning') {
            supply.value = 'none';
            addon.checked = false;
            if (incRewards) incRewards.checked = false;
        } else if (v === 'other') {
            supply.value = 'none';
            addon.checked = false;
            if (incRewards) incRewards.checked = false;
        }
        const isDryCleaning = v === 'dry_cleaning';
        const showWeightOption = isDryCleaning || v === 'other';
        if (requiredWeightWrap) requiredWeightWrap.classList.toggle('d-none', !showWeightOption);
        if (requiredWeight) {
            if (isDryCleaning) {
                requiredWeight.checked = true;
            } else if (v !== 'other') {
                requiredWeight.checked = false;
            }
        }
        supply.disabled = isDryCleaning;
    };
    kind.addEventListener('change', sync);
    modal?.addEventListener('hidden.bs.modal', () => {
        setTimeout(sync, 0);
    });
    const editModalEl = document.getElementById('editOrderTypeModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('editOrderTypeForm');
    const editKind = document.getElementById('edit_ot_kind');
    const editSupply = document.getElementById('edit_supply_block');
    const editAddon = document.getElementById('edit_show_addon');
    const editRequiredWeight = document.getElementById('edit_required_weight');
    const editRequiredWeightWrap = document.getElementById('edit_required_weight_wrap');
    const editIncludeRewards = document.getElementById('edit_include_rewards');
    const editBaseUrl = '<?= e(url('/tenant/laundry-order-pricing/types')) ?>';

    const syncEditDefaults = () => {
        if (!editKind || !editSupply || !editAddon) return;
        if (editKind.value === 'full_service') {
            editSupply.value = 'full_service';
            editAddon.checked = true;
            if (editIncludeRewards) editIncludeRewards.checked = true;
        } else if (editKind.value === 'wash_only') {
            editSupply.value = 'wash_supplies';
            editAddon.checked = true;
            if (editIncludeRewards) editIncludeRewards.checked = false;
        } else if (editKind.value === 'dry_only') {
            editSupply.value = 'none';
            editAddon.checked = false;
            if (editIncludeRewards) editIncludeRewards.checked = false;
        } else if (editKind.value === 'rinse_only') {
            editSupply.value = 'rinse_supplies';
            editAddon.checked = false;
            if (editIncludeRewards) editIncludeRewards.checked = false;
        } else if (editKind.value === 'dry_cleaning') {
            editSupply.value = 'none';
            editAddon.checked = false;
            if (editIncludeRewards) editIncludeRewards.checked = false;
        } else if (editKind.value === 'other') {
            editSupply.value = 'none';
            editAddon.checked = false;
            if (editIncludeRewards) editIncludeRewards.checked = false;
        }
        const isDryCleaning = editKind.value === 'dry_cleaning';
        const showWeightOption = isDryCleaning || editKind.value === 'other';
        if (editRequiredWeightWrap) editRequiredWeightWrap.classList.toggle('d-none', !showWeightOption);
        if (editRequiredWeight) {
            if (isDryCleaning) {
                editRequiredWeight.checked = true;
            } else if (editKind.value !== 'other') {
                editRequiredWeight.checked = false;
            }
        }
        editSupply.disabled = isDryCleaning;
    };

    editKind?.addEventListener('change', syncEditDefaults);
    document.querySelectorAll('.js-edit-order-type').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editForm) return;
            const id = btn.getAttribute('data-id') || '';
            if (!id) return;
            editForm.action = `${editBaseUrl}/${id}/update`;
            const setVal = (idName, attr) => {
                const el = document.getElementById(idName);
                if (el) el.value = btn.getAttribute(attr) || '';
            };
            setVal('edit_ot_label', 'data-label');
            setVal('edit_ot_kind', 'data-service-kind');
            setVal('edit_ot_price', 'data-price');
            setVal('edit_ot_sort', 'data-sort-order');
            setVal('edit_supply_block', 'data-supply-block');
            const setChecked = (idName, attr) => {
                const el = document.getElementById(idName);
                if (el) el.checked = (btn.getAttribute(attr) || '0') === '1';
            };
            setChecked('edit_is_active', 'data-is-active');
            setChecked('edit_show_addon', 'data-show-addon-supplies');
            setChecked('edit_required_weight', 'data-required-weight');
            setChecked('edit_include_rewards', 'data-include-in-rewards');
            editModal?.show();
        });
    });
})();
</script>
