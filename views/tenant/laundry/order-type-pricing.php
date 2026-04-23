<?php
/** @var list<array<string, mixed>> $order_types */
$rows = $order_types ?? [];
$rewardSystemActive = ! empty($reward_system_active);
$kindLabels = [
    'full_service' => 'Full service',
    'wash_only' => 'Wash only',
    'dry_only' => 'Dry only',
    'rinse_only' => 'Rinse only',
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
        <h5 class="mb-1">Order Type Pricing</h5>
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
                        <label class="form-label fw-medium" for="new_ot_price">Price / load (₱)</label>
                        <input type="number" class="form-control" name="price_per_load" id="new_ot_price" min="0" step="0.01" value="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-medium" for="new_ot_sort">Sort</label>
                        <input type="number" class="form-control" name="sort_order" id="new_ot_sort" value="0" step="1">
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
<?php endif; ?>

<?php foreach ($rows as $r): ?>
    <?php
    $rid = (int) ($r['id'] ?? 0);
    $code = (string) ($r['code'] ?? '');
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <span class="small text-muted font-monospace"><?= e($code) ?></span>
                </div>
                <form method="POST" action="<?= e(route('tenant.laundry-order-pricing.types.destroy', ['id' => $rid])) ?>" class="d-inline"
                      onsubmit="return confirm('Delete this order type? Only allowed if no transactions use it.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </div>
            <form method="POST" action="<?= e(route('tenant.laundry-order-pricing.types.update', ['id' => $rid])) ?>" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label fw-medium" for="label_<?= $rid ?>">Display name</label>
                    <input type="text" class="form-control" name="label" id="label_<?= $rid ?>"
                           value="<?= e((string) ($r['label'] ?? '')) ?>" required maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium" for="kind_<?= $rid ?>">Service behavior</label>
                    <select class="form-select" name="service_kind" id="kind_<?= $rid ?>">
                        <?php foreach ($kindLabels as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= (($r['service_kind'] ?? '') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-medium" for="price_<?= $rid ?>">Price / load (₱)</label>
                    <input type="number" class="form-control" name="price_per_load" id="price_<?= $rid ?>"
                           min="0" step="0.01" value="<?= e((string) (float) ($r['price_per_load'] ?? 0)) ?>" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-medium" for="sort_<?= $rid ?>">Sort</label>
                    <input type="number" class="form-control" name="sort_order" id="sort_<?= $rid ?>"
                           value="<?= (int) ($r['sort_order'] ?? 0) ?>" step="1">
                </div>
                <div class="col-md-1">
                    <div class="form-check form-switch pt-4">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act_<?= $rid ?>"
                            <?= ! empty($r['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="act_<?= $rid ?>">Active</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-medium" for="supply_<?= $rid ?>">Service supply block</label>
                    <select class="form-select" name="supply_block" id="supply_<?= $rid ?>">
                        <?php foreach ($supplyLabels as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= (($r['supply_block'] ?? 'none') === $k) ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="d-flex flex-wrap gap-3 mt-4 pt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_addon_supplies" value="1" id="addon_<?= $rid ?>"
                                <?= ! empty($r['show_addon_supplies']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="addon_<?= $rid ?>">Show add-on supplies (extra charged qty)</label>
                        </div>
                        <?php if ($rewardSystemActive): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_in_rewards" value="1" id="inc_rewards_<?= $rid ?>"
                                <?= ! empty($r['include_in_rewards']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="inc_rewards_<?= $rid ?>">Include to Reward System</label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">Save changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    const kind = document.getElementById('new_ot_kind');
    const supply = document.getElementById('new_supply_block');
    const addon = document.getElementById('new_show_addon');
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
        }
    };
    kind.addEventListener('change', sync);
    modal?.addEventListener('hidden.bs.modal', () => {
        setTimeout(sync, 0);
    });
    document.querySelectorAll('form[action*="/types/"][action*="/update"]').forEach((form) => {
        const k = form.querySelector('select[name="service_kind"]');
        const inc = form.querySelector('input[name="include_in_rewards"]');
        if (!k || !inc) return;
        const rowSync = () => {
            if (k.value === 'full_service') {
                inc.checked = true;
            } else {
                inc.checked = false;
            }
        };
        k.addEventListener('change', rowSync);
    });
})();
</script>
