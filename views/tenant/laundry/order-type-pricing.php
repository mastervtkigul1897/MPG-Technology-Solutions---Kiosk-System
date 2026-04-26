<?php
/** @var list<array<string, mixed>> $order_types */
/** @var array<string,int> $order_type_transaction_counts */
$rows = $order_types ?? [];
$txCounts = $order_type_transaction_counts ?? [];
$coreOrderTypeCodes = $core_order_type_codes ?? ['drop_off', 'wash_only', 'dry_only', 'rinse_only', 'free_fold', 'fold_with_price'];
$kindLabels = [
    'full_service' => 'Full service',
    'wash_only' => 'Wash only',
    'dry_only' => 'Dry only',
    'rinse_only' => 'Rinse only',
    'dry_cleaning' => 'Dry Cleaning',
    'fold_only' => 'Fold',
    'other' => 'Other',
];
$showInLabels = [
    'both' => 'Both',
    'drop_off' => 'Drop Off',
    'self_service' => 'Self Service',
];
?>
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">Order Pricing</h5>
        <p class="small text-muted mb-0">
            Every shop keeps the four built-in types (<strong>Drop-off</strong>, <strong>Wash only</strong>, <strong>Dry only</strong>, <strong>Rinse only</strong>); they cannot be deleted. Add optional types such as <strong>Dry Cleaning</strong> or <strong>Other</strong> as needed—only those extras can be removed. Each type appears in <strong>Daily Sales → Add service</strong>. Use <strong>Detergent/Fabcon/Bleach qty</strong> to define inventory deduction per order type. Use <strong>Show add-on supplies</strong> for charged extras on top of the base price.
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
        <form method="POST" action="<?= e(route('tenant.laundry-order-pricing.types.store')) ?>" class="modal-content" data-mpg-ajax="off">
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
                        <label class="form-label fw-medium" for="new_show_in_order_mode">Show in</label>
                        <select class="form-select" name="show_in_order_mode" id="new_show_in_order_mode" required>
                            <?php foreach ($showInLabels as $modeKey => $modeLabel): ?>
                                <option value="<?= e($modeKey) ?>" <?= $modeKey === 'both' ? 'selected' : '' ?>><?= e($modeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_fold_service_amount">Fold service amount (₱ per load)</label>
                        <input type="number" class="form-control" name="fold_service_amount" id="new_fold_service_amount" min="0" step="0.01" value="10" required>
                    </div>
                    <div class="col-md-6" id="new_fold_commission_wrap">
                        <label class="form-label fw-medium" for="new_fold_commission_target">Fold commission goes to</label>
                        <select class="form-select" name="fold_commission_target" id="new_fold_commission_target">
                            <option value="branch" selected>Branch</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="new_fold_staff_share_wrap">
                        <label class="form-label fw-medium" for="new_fold_staff_share_amount">Fold staff share (₱ per qty)</label>
                        <input type="number" class="form-control" name="fold_staff_share_amount" id="new_fold_staff_share_amount" min="0" step="0.01" value="10" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_ot_price">Price (₱)</label>
                        <input type="number" class="form-control" name="price_per_load" id="new_ot_price" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium" for="new_detergent_qty">Detergent qty</label>
                        <input type="number" class="form-control" name="detergent_qty" id="new_detergent_qty" min="0" step="0.001" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium" for="new_fabcon_qty">Fabcon qty</label>
                        <input type="number" class="form-control" name="fabcon_qty" id="new_fabcon_qty" min="0" step="0.001" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium" for="new_bleach_qty">Bleach qty</label>
                        <input type="number" class="form-control" name="bleach_qty" id="new_bleach_qty" min="0" step="0.001" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_max_weight_kg">Maximum Weight (kg)</label>
                        <input type="number" class="form-control" name="max_weight_kg" id="new_max_weight_kg" min="0" step="0.001" value="0" placeholder="0 = no limit">
                    </div>
                    <div class="col-md-6 d-none" id="new_excess_fee_wrap">
                        <label class="form-label fw-medium" for="new_excess_weight_fee_per_kg">Excess Weight Additional Fee (₱/kg)</label>
                        <input type="number" class="form-control" name="excess_weight_fee_per_kg" id="new_excess_weight_fee_per_kg" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-3 align-items-start">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="show_addon_supplies" value="1" id="new_show_addon" checked>
                                <label class="form-check-label" for="new_show_addon">Show add-on supplies (extra charged qty)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_in_rewards" value="1" id="new_include_in_rewards" checked>
                                <label class="form-check-label" for="new_include_in_rewards">Add to rewards</label>
                            </div>
                            <div class="form-check d-none" id="new_required_weight_wrap">
                                <input class="form-check-input" type="checkbox" name="required_weight" value="1" id="new_required_weight">
                                <label class="form-check-label" for="new_required_weight">Calculate Price Per Weight</label>
                            </div>
                        </div>
                    </div>
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
                    <th>Show in</th>
                    <th>Calculate Price Per Weight</th>
                    <th class="text-end">Max Weight (kg)</th>
                    <th class="text-end">Excess Fee / kg</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Det Qty</th>
                    <th class="text-end">Fab Qty</th>
                    <th class="text-end">Bleach Qty</th>
                    <th class="text-end">Fold Price</th>
                    <th>Fold Commission</th>
                    <th class="text-end">Fold Staff Share</th>
                    <th>Active</th>
                    <th>Add-ons</th>
                    <th>Add to rewards</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php $rid = (int) ($r['id'] ?? 0); ?>
                    <?php $rowCode = (string) ($r['code'] ?? ''); ?>
                    <?php $rowServiceKind = (string) ($r['service_kind'] ?? ''); ?>
                    <?php $isNoWeightRow = in_array($rowServiceKind, ['dry_cleaning', 'fold_only'], true); ?>
                    <?php $isFoldRow = $rowCode === 'fold_with_price'; ?>
                    <?php $isCoreOrderType = in_array($rowCode, $coreOrderTypeCodes, true); ?>
                    <tr>
                        <td class="small font-monospace"><?= e((string) ($r['code'] ?? '')) ?></td>
                        <td><?= e((string) ($r['label'] ?? '')) ?></td>
                        <td><?= e($kindLabels[$rowServiceKind] ?? $rowServiceKind) ?></td>
                        <td><?= e($showInLabels[(string) ($r['show_in_order_mode'] ?? 'both')] ?? 'Both') ?></td>
                        <td><?= ! empty($r['required_weight']) ? 'Yes' : 'No' ?></td>
                        <td class="text-end"><?= $isNoWeightRow ? 'N/A' : e(number_format((float) ($r['max_weight_kg'] ?? 0), 3)) ?></td>
                        <td class="text-end"><?= $isNoWeightRow ? 'N/A' : e(number_format((float) ($r['excess_weight_fee_per_kg'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= e(number_format((float) ($r['price_per_load'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= e(number_format((float) ($r['detergent_qty'] ?? 0), 3)) ?></td>
                        <td class="text-end"><?= e(number_format((float) ($r['fabcon_qty'] ?? 0), 3)) ?></td>
                        <td class="text-end"><?= e(number_format((float) ($r['bleach_qty'] ?? 0), 3)) ?></td>
                        <td class="text-end"><?= $isFoldRow ? e(number_format((float) ($r['fold_service_amount'] ?? 10), 2)) : 'N/A' ?></td>
                        <td><?= $isFoldRow ? e(ucfirst((string) ($r['fold_commission_target'] ?? 'branch'))) : 'N/A' ?></td>
                        <td class="text-end"><?= $isFoldRow ? e(number_format((float) ($r['fold_staff_share_amount'] ?? 10), 2)) : 'N/A' ?></td>
                        <td><?= ! empty($r['is_active']) ? 'Yes' : 'No' ?></td>
                        <td><?= ! empty($r['show_addon_supplies']) ? 'Yes' : 'No' ?></td>
                        <td><?= ! empty($r['include_in_rewards']) ? 'Yes' : 'No' ?></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-primary js-edit-order-type"
                                    data-id="<?= $rid ?>"
                                    data-code="<?= e($rowCode) ?>"
                                    data-label="<?= e((string) ($r['label'] ?? '')) ?>"
                                    data-service-kind="<?= e((string) ($r['service_kind'] ?? 'full_service')) ?>"
                                    data-show-in-order-mode="<?= e((string) ($r['show_in_order_mode'] ?? 'both')) ?>"
                                    data-price="<?= e((string) (float) ($r['price_per_load'] ?? 0)) ?>"
                                    data-detergent-qty="<?= e((string) (float) ($r['detergent_qty'] ?? 0)) ?>"
                                    data-fabcon-qty="<?= e((string) (float) ($r['fabcon_qty'] ?? 0)) ?>"
                                    data-bleach-qty="<?= e((string) (float) ($r['bleach_qty'] ?? 0)) ?>"
                                    data-fold-service-amount="<?= e((string) (float) ($r['fold_service_amount'] ?? 10)) ?>"
                                    data-fold-commission-target="<?= e((string) ($r['fold_commission_target'] ?? 'branch')) ?>"
                                    data-fold-staff-share-amount="<?= e((string) (float) ($r['fold_staff_share_amount'] ?? 10)) ?>"
                                    data-max-weight-kg="<?= e((string) (float) ($r['max_weight_kg'] ?? 0)) ?>"
                                    data-excess-weight-fee-per-kg="<?= e((string) (float) ($r['excess_weight_fee_per_kg'] ?? 0)) ?>"
                                    data-is-active="<?= ! empty($r['is_active']) ? '1' : '0' ?>"
                                    data-show-addon-supplies="<?= ! empty($r['show_addon_supplies']) ? '1' : '0' ?>"
                                    data-required-weight="<?= ! empty($r['required_weight']) ? '1' : '0' ?>"
                                    data-include-in-rewards="<?= ! empty($r['include_in_rewards']) ? '1' : '0' ?>"
                                >
                                    Edit
                                </button>
                                <?php if (! $isCoreOrderType): ?>
                                <form
                                    method="POST"
                                    action="<?= e(route('tenant.laundry-order-pricing.types.destroy', ['id' => $rid])) ?>"
                                    class="js-order-type-delete-form"
                                    data-mpg-ajax="off"
                                    data-related-order-count="<?= (int) ($txCounts[$rowCode] ?? 0) ?>"
                                >
                                    <?= csrf_field() ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-order-type-delete-btn">Delete</button>
                                </form>
                                <?php else: ?>
                                    <?php
                                    $coreDeleteHint = 'You cannot delete this order type because it is defined by the system.';
                                    ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-link text-info p-0 js-core-order-type-info"
                                        data-message="<?= e($coreDeleteHint) ?>"
                                        aria-label="<?= e($coreDeleteHint) ?>"
                                        title="More info"
                                    ><i class="fa-solid fa-circle-info" aria-hidden="true"></i></button>
                                <?php endif; ?>
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
        <form method="POST" id="editOrderTypeForm" class="modal-content" data-mpg-ajax="off">
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
                        <label class="form-label fw-medium" for="edit_show_in_order_mode">Show in</label>
                        <select class="form-select" name="show_in_order_mode" id="edit_show_in_order_mode" required>
                            <?php foreach ($showInLabels as $modeKey => $modeLabel): ?>
                                <option value="<?= e($modeKey) ?>"><?= e($modeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_fold_service_amount">Fold service amount (₱ per load)</label>
                        <input type="number" class="form-control" name="fold_service_amount" id="edit_fold_service_amount" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6" id="edit_fold_commission_wrap">
                        <label class="form-label fw-medium" for="edit_fold_commission_target">Fold commission goes to</label>
                        <select class="form-select" name="fold_commission_target" id="edit_fold_commission_target">
                            <option value="branch">Branch</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="edit_fold_staff_share_wrap">
                        <label class="form-label fw-medium" for="edit_fold_staff_share_amount">Fold staff share (₱ per qty)</label>
                        <input type="number" class="form-control" name="fold_staff_share_amount" id="edit_fold_staff_share_amount" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_ot_price">Price (₱)</label>
                        <input type="number" class="form-control" name="price_per_load" id="edit_ot_price" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium" for="edit_detergent_qty">Detergent qty</label>
                        <input type="number" class="form-control" name="detergent_qty" id="edit_detergent_qty" min="0" step="0.001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium" for="edit_fabcon_qty">Fabcon qty</label>
                        <input type="number" class="form-control" name="fabcon_qty" id="edit_fabcon_qty" min="0" step="0.001">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-medium" for="edit_bleach_qty">Bleach qty</label>
                        <input type="number" class="form-control" name="bleach_qty" id="edit_bleach_qty" min="0" step="0.001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="edit_max_weight_kg">Maximum Weight (kg)</label>
                        <input type="number" class="form-control" name="max_weight_kg" id="edit_max_weight_kg" min="0" step="0.001" placeholder="0 = no limit">
                    </div>
                    <div class="col-md-6 d-none" id="edit_excess_fee_wrap">
                        <label class="form-label fw-medium" for="edit_excess_weight_fee_per_kg">Excess Weight Additional Fee (₱/kg)</label>
                        <input type="number" class="form-control" name="excess_weight_fee_per_kg" id="edit_excess_weight_fee_per_kg" min="0" step="0.01">
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
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_in_rewards" value="1" id="edit_include_in_rewards">
                                <label class="form-check-label" for="edit_include_in_rewards">Add to rewards</label>
                            </div>
                            <div class="form-check d-none" id="edit_required_weight_wrap">
                                <input class="form-check-input" type="checkbox" name="required_weight" value="1" id="edit_required_weight">
                                <label class="form-check-label" for="edit_required_weight">Calculate Price Per Weight</label>
                            </div>
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
    const addon = document.getElementById('new_show_addon');
    const newIncludeInRewards = document.getElementById('new_include_in_rewards');
    const requiredWeight = document.getElementById('new_required_weight');
    const requiredWeightWrap = document.getElementById('new_required_weight_wrap');
    const newDetergentQty = document.getElementById('new_detergent_qty');
    const newFabconQty = document.getElementById('new_fabcon_qty');
    const newBleachQty = document.getElementById('new_bleach_qty');
    const newFoldAmount = document.getElementById('new_fold_service_amount');
    const newFoldCommissionWrap = document.getElementById('new_fold_commission_wrap');
    const newFoldCommission = document.getElementById('new_fold_commission_target');
    const newFoldStaffShareWrap = document.getElementById('new_fold_staff_share_wrap');
    const newFoldStaffShare = document.getElementById('new_fold_staff_share_amount');
    const newMaxWeight = document.getElementById('new_max_weight_kg');
    const newExcessFeeWrap = document.getElementById('new_excess_fee_wrap');
    const newExcessFee = document.getElementById('new_excess_weight_fee_per_kg');
    const modal = document.getElementById('addOrderTypeModal');
    if (!kind || !addon) return;
    const syncExcessFeeVisibility = (maxEl, wrapEl, feeEl) => {
        const maxW = Math.max(0, parseFloat(maxEl?.value || '0') || 0);
        if (wrapEl) wrapEl.classList.toggle('d-none', maxW <= 0);
        if (maxW <= 0 && feeEl) feeEl.value = '0';
    };
    const syncWeightFieldVisibility = (maxEl, wrapEl, feeEl, isDryCleaning) => {
        const maxCol = maxEl?.closest('.col-md-6') || null;
        if (maxCol) maxCol.classList.toggle('d-none', isDryCleaning);
        if (isDryCleaning) {
            if (maxEl) maxEl.value = '0';
            if (feeEl) feeEl.value = '0';
            if (wrapEl) wrapEl.classList.add('d-none');
            return;
        }
        syncExcessFeeVisibility(maxEl, wrapEl, feeEl);
    };
    const syncFoldFieldVisibility = (amountEl, commissionWrapEl, commissionEl, staffShareWrapEl, staffShareEl, hideFoldFields) => {
        const amountCol = amountEl?.closest('.col-md-6') || null;
        if (amountCol) amountCol.classList.toggle('d-none', hideFoldFields);
        if (commissionWrapEl) commissionWrapEl.classList.toggle('d-none', hideFoldFields);
        if (staffShareWrapEl) staffShareWrapEl.classList.toggle('d-none', hideFoldFields);
        if (hideFoldFields) {
            if (amountEl) amountEl.value = '0';
            if (commissionEl) commissionEl.value = 'branch';
            if (staffShareEl) staffShareEl.value = '0';
        }
    };
    const syncServiceSupplyQtyVisibility = (detEl, fabEl, bleachEl, isDryCleaning) => {
        const detCol = detEl?.closest('.col-md-4') || null;
        const fabCol = fabEl?.closest('.col-md-4') || null;
        const bleachCol = bleachEl?.closest('.col-md-4') || null;
        if (detCol) detCol.classList.toggle('d-none', isDryCleaning);
        if (fabCol) fabCol.classList.toggle('d-none', isDryCleaning);
        if (bleachCol) bleachCol.classList.toggle('d-none', isDryCleaning);
        if (isDryCleaning) {
            if (detEl) detEl.value = '0';
            if (fabEl) fabEl.value = '0';
            if (bleachEl) bleachEl.value = '0';
        }
    };
    const syncFoldStaffShareVisibility = (commissionEl, staffShareWrapEl, staffShareEl) => {
        const show = (commissionEl?.value || 'branch') === 'staff';
        if (staffShareWrapEl) staffShareWrapEl.classList.toggle('d-none', !show);
        if (!show && staffShareEl) staffShareEl.value = '0';
    };

    const sync = () => {
        const v = kind.value;
        if (v === 'full_service') {
            addon.checked = true;
            if (newIncludeInRewards) newIncludeInRewards.checked = true;
        } else if (v === 'wash_only') {
            addon.checked = true;
            if (newIncludeInRewards) newIncludeInRewards.checked = false;
        } else if (v === 'dry_only') {
            addon.checked = false;
            if (newIncludeInRewards) newIncludeInRewards.checked = false;
        } else if (v === 'rinse_only') {
            addon.checked = false;
            if (newIncludeInRewards) newIncludeInRewards.checked = false;
        } else if (v === 'dry_cleaning' || v === 'fold_only') {
            addon.checked = false;
            if (newIncludeInRewards) newIncludeInRewards.checked = false;
        } else if (v === 'other') {
            addon.checked = false;
            if (newIncludeInRewards) newIncludeInRewards.checked = false;
        }
        const isNoWeightKind = v === 'dry_cleaning' || v === 'fold_only';
        const hideFoldFields = v !== 'fold_only';
        const showWeightOption = v === 'dry_cleaning' || v === 'other';
        if (requiredWeightWrap) requiredWeightWrap.classList.toggle('d-none', !showWeightOption);
        if (requiredWeight) {
            if (v === 'dry_cleaning') {
                requiredWeight.checked = true;
            } else if (v !== 'other') {
                requiredWeight.checked = false;
            }
        }
        syncWeightFieldVisibility(newMaxWeight, newExcessFeeWrap, newExcessFee, isNoWeightKind);
        syncFoldFieldVisibility(newFoldAmount, newFoldCommissionWrap, newFoldCommission, newFoldStaffShareWrap, newFoldStaffShare, hideFoldFields);
        syncServiceSupplyQtyVisibility(newDetergentQty, newFabconQty, newBleachQty, isNoWeightKind);
        syncFoldStaffShareVisibility(newFoldCommission, newFoldStaffShareWrap, newFoldStaffShare);
    };
    kind.addEventListener('change', sync);
    newFoldCommission?.addEventListener('change', () => syncFoldStaffShareVisibility(newFoldCommission, newFoldStaffShareWrap, newFoldStaffShare));
    newMaxWeight?.addEventListener('input', () => syncWeightFieldVisibility(newMaxWeight, newExcessFeeWrap, newExcessFee, kind.value === 'dry_cleaning' || kind.value === 'fold_only'));
    newMaxWeight?.addEventListener('change', () => syncWeightFieldVisibility(newMaxWeight, newExcessFeeWrap, newExcessFee, kind.value === 'dry_cleaning' || kind.value === 'fold_only'));
    modal?.addEventListener('hidden.bs.modal', () => {
        setTimeout(sync, 0);
    });
    const editModalEl = document.getElementById('editOrderTypeModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('editOrderTypeForm');
    const editKind = document.getElementById('edit_ot_kind');
    const editAddon = document.getElementById('edit_show_addon');
    const editRequiredWeight = document.getElementById('edit_required_weight');
    const editRequiredWeightWrap = document.getElementById('edit_required_weight_wrap');
    const editDetergentQty = document.getElementById('edit_detergent_qty');
    const editFabconQty = document.getElementById('edit_fabcon_qty');
    const editBleachQty = document.getElementById('edit_bleach_qty');
    const editFoldAmount = document.getElementById('edit_fold_service_amount');
    const editFoldCommissionWrap = document.getElementById('edit_fold_commission_wrap');
    const editFoldCommission = document.getElementById('edit_fold_commission_target');
    const editFoldStaffShareWrap = document.getElementById('edit_fold_staff_share_wrap');
    const editFoldStaffShare = document.getElementById('edit_fold_staff_share_amount');
    const editPriceInput = document.getElementById('edit_ot_price');
    const editMaxWeight = document.getElementById('edit_max_weight_kg');
    const editExcessFeeWrap = document.getElementById('edit_excess_fee_wrap');
    const editExcessFee = document.getElementById('edit_excess_weight_fee_per_kg');
    const editBaseUrl = '<?= e(url('/tenant/laundry-order-pricing/types')) ?>';
    let currentEditOrderTypeCode = '';

    const syncEditDefaults = () => {
        if (!editKind || !editAddon) return;
        if (editKind.value === 'full_service') {
            editAddon.checked = true;
        } else if (editKind.value === 'wash_only') {
            editAddon.checked = true;
        } else if (editKind.value === 'dry_only') {
            editAddon.checked = false;
        } else if (editKind.value === 'rinse_only') {
            editAddon.checked = false;
        } else if (editKind.value === 'dry_cleaning' || editKind.value === 'fold_only') {
            editAddon.checked = false;
        } else if (editKind.value === 'other') {
            editAddon.checked = false;
        }
        const isNoWeightKind = editKind.value === 'dry_cleaning' || editKind.value === 'fold_only';
        const hideFoldFields = editKind.value !== 'fold_only' || currentEditOrderTypeCode === 'free_fold';
        const isFreeFoldCode = currentEditOrderTypeCode === 'free_fold';
        const priceCol = editPriceInput?.closest('.col-md-6') || null;
        if (priceCol) priceCol.classList.toggle('d-none', isFreeFoldCode);
        if (isFreeFoldCode && editPriceInput) editPriceInput.value = '0';
        const showWeightOption = editKind.value === 'dry_cleaning' || editKind.value === 'other';
        if (editRequiredWeightWrap) editRequiredWeightWrap.classList.toggle('d-none', !showWeightOption);
        if (editRequiredWeight) {
            if (editKind.value === 'dry_cleaning') {
                editRequiredWeight.checked = true;
            } else if (editKind.value !== 'other') {
                editRequiredWeight.checked = false;
            }
        }
        syncWeightFieldVisibility(editMaxWeight, editExcessFeeWrap, editExcessFee, isNoWeightKind);
        syncFoldFieldVisibility(editFoldAmount, editFoldCommissionWrap, editFoldCommission, editFoldStaffShareWrap, editFoldStaffShare, hideFoldFields);
        syncServiceSupplyQtyVisibility(editDetergentQty, editFabconQty, editBleachQty, isNoWeightKind);
        syncFoldStaffShareVisibility(editFoldCommission, editFoldStaffShareWrap, editFoldStaffShare);
    };

    editKind?.addEventListener('change', syncEditDefaults);
    editFoldCommission?.addEventListener('change', () => syncFoldStaffShareVisibility(editFoldCommission, editFoldStaffShareWrap, editFoldStaffShare));
    editMaxWeight?.addEventListener('input', () => syncWeightFieldVisibility(editMaxWeight, editExcessFeeWrap, editExcessFee, editKind?.value === 'dry_cleaning' || editKind?.value === 'fold_only'));
    editMaxWeight?.addEventListener('change', () => syncWeightFieldVisibility(editMaxWeight, editExcessFeeWrap, editExcessFee, editKind?.value === 'dry_cleaning' || editKind?.value === 'fold_only'));
    document.querySelectorAll('.js-edit-order-type').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!editForm) return;
            const id = btn.getAttribute('data-id') || '';
            if (!id) return;
            currentEditOrderTypeCode = String(btn.getAttribute('data-code') || '').trim();
            editForm.action = `${editBaseUrl}/${id}/update`;
            const setVal = (idName, attr) => {
                const el = document.getElementById(idName);
                if (el) el.value = btn.getAttribute(attr) || '';
            };
            setVal('edit_ot_label', 'data-label');
            setVal('edit_ot_kind', 'data-service-kind');
            setVal('edit_show_in_order_mode', 'data-show-in-order-mode');
            setVal('edit_ot_price', 'data-price');
            setVal('edit_detergent_qty', 'data-detergent-qty');
            setVal('edit_fabcon_qty', 'data-fabcon-qty');
            setVal('edit_bleach_qty', 'data-bleach-qty');
            setVal('edit_fold_service_amount', 'data-fold-service-amount');
            setVal('edit_fold_commission_target', 'data-fold-commission-target');
            setVal('edit_fold_staff_share_amount', 'data-fold-staff-share-amount');
            setVal('edit_max_weight_kg', 'data-max-weight-kg');
            setVal('edit_excess_weight_fee_per_kg', 'data-excess-weight-fee-per-kg');
            const setChecked = (idName, attr) => {
                const el = document.getElementById(idName);
                if (el) el.checked = (btn.getAttribute(attr) || '0') === '1';
            };
            setChecked('edit_is_active', 'data-is-active');
            setChecked('edit_show_addon', 'data-show-addon-supplies');
            setChecked('edit_required_weight', 'data-required-weight');
            setChecked('edit_include_in_rewards', 'data-include-in-rewards');
            syncEditDefaults();
            editModal?.show();
        });
    });
    sync();
})();
</script>
<script>
(function () {
    const confirmDelete = async (title, content, confirmText, options = {}) => {
        const asHtml = !!options.asHtml;
        if (typeof Swal !== 'undefined') {
            const cfg = {
                icon: 'warning',
                title,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'No',
                confirmButtonColor: '#dc3545',
            };
            if (asHtml) {
                cfg.html = content;
            } else {
                cfg.text = content;
            }
            const r = await Swal.fire(cfg);
            return !!(r && (r.isConfirmed === true || r.value === true));
        }
        const plain = asHtml
            ? content.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '').replace(/\n{3,}/g, '\n\n').trim()
            : content;
        return window.confirm(`${title}\n\n${plain}`);
    };

    /** POST without firing submit listeners (jQuery mpg-ajax, etc.). */
    const postNavigateDelete = (actionUrl, csrfToken, includePurge) => {
        const dyn = document.createElement('form');
        dyn.method = 'POST';
        dyn.action = actionUrl;
        const tok = document.createElement('input');
        tok.type = 'hidden';
        tok.name = '_token';
        tok.value = csrfToken;
        dyn.appendChild(tok);
        if (includePurge) {
            const p = document.createElement('input');
            p.type = 'hidden';
            p.name = 'confirm_purge_orders';
            p.value = '1';
            dyn.appendChild(p);
        }
        document.body.appendChild(dyn);
        dyn.submit();
    };

    document.querySelectorAll('.js-order-type-delete-form').forEach((form) => {
        const btn = form.querySelector('.js-order-type-delete-btn');
        if (!(btn instanceof HTMLButtonElement)) return;
        btn.addEventListener('click', async () => {
            const tokenEl = form.querySelector('input[name="_token"]');
            const csrfToken = tokenEl instanceof HTMLInputElement ? tokenEl.value : '';
            if (!csrfToken || !form.action) return;

            const n = parseInt(form.getAttribute('data-related-order-count') || '0', 10);
            let ok;
            let includePurge = false;
            if (n > 0) {
                ok = await confirmDelete(
                    'Delete order type and transactions?',
                    `Deleting this order type will also permanently delete <strong>${n}</strong> related transaction(s) already saved for it (including older records). This cannot be undone.<br><br>You still want to proceed?`,
                    'Yes',
                    { asHtml: true }
                );
                if (!ok) return;
                includePurge = true;
            } else {
                ok = await confirmDelete(
                    'Delete this order type?',
                    'This order type has no saved transactions yet. It will be removed from pricing and POS lists. This cannot be undone. Continue?',
                    'Yes'
                );
                if (!ok) return;
            }
            postNavigateDelete(form.action, csrfToken, includePurge);
        });
    });
})();
</script>
<script>
(function () {
    const showInfo = async (message) => {
        const text = String(message || 'No additional details.');
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'info',
                title: 'System order type',
                text,
                confirmButtonText: 'OK',
                confirmButtonColor: '#0d6efd',
            });
            return;
        }
        window.alert(text);
    };

    document.querySelectorAll('.js-core-order-type-info').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const message = btn.getAttribute('data-message') || '';
            await showInfo(message);
        });
    });
})();
</script>
