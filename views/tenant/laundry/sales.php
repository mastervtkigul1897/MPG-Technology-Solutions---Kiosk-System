<p class="small text-muted mb-3 laundry-sales-hint" id="laundrySalesHintKanban">
    <strong>Kanban:</strong> drag cards in sequence only: <strong>Pending - Waiting</strong> → <strong>Washing - Drying</strong> → <strong>Finishing - To Be Picked Up</strong> → <strong>Paid - Completed</strong>.
    Payment modal only appears when moving from <strong>Finishing - To Be Picked Up</strong> to <strong>Paid - Completed</strong> for regular transactions; if you cancel, the card stays in Finishing - To Be Picked Up.
    Backward moves and skipped steps are blocked.
    Use <strong>View details</strong> or double-click a card to see inclusions, add-ons, and payment info.
</p>
<p class="small text-muted mb-3 laundry-sales-hint d-none" id="laundrySalesHintTable">
    <strong>Table:</strong> use <strong>Details</strong> for full line items. Use <strong>Start Washing - Drying</strong>, then <strong>Finishing - To Be Picked Up</strong>, then <strong>Paid - Completed</strong>.
    Sequence is forward-only and follows the same status flow as Kanban.
</p>
<?php
$currentUser = auth_user();
$isCashier = (($currentUser['role'] ?? '') === 'cashier');
$isTenantAdmin = (($currentUser['role'] ?? '') === 'tenant_admin');
$machineAssignmentEnabled = (bool) ($machine_assignment_enabled ?? true);
$order_types_list = $order_types ?? [];
$rewardConfig = is_array($reward_config ?? null) ? $reward_config : null;
$rewardThreshold = $rewardConfig !== null ? max(1.0, (float) ($rewardConfig['minimum_points_to_redeem'] ?? $rewardConfig['reward_points_cost'] ?? 10)) : 0.0;
$rewardOrderTypeCode = $rewardConfig !== null ? (string) ($rewardConfig['reward_order_type_code'] ?? '') : '';
$laundryPaymentMethodLabel = static function (string $pm): string {
    $pm = strtolower(trim($pm));

    return match ($pm) {
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'paymaya' => 'PayMaya',
        'online_banking' => 'Online Banking',
        'qr_payment' => 'QR Payment',
        'card' => 'Card',
        'pending' => '—',
        default => $pm !== '' ? ucfirst(str_replace('_', ' ', $pm)) : '—',
    };
};
$laundryStockOptionLabel = static function (array $item): string {
    $name = (string) ($item['name'] ?? '');
    $sq = (float) ($item['stock_quantity'] ?? 0);
    $s = abs($sq - round($sq)) < 0.0001
        ? (string) (int) round($sq)
        : rtrim(rtrim(sprintf('%.4f', $sq), '0'), '.');

    return $name.' · stock: '.$s;
};

$ordersList = $orders ?? [];
$kanbanPending = [];
$kanbanWashing = [];
$kanbanOpenTicket = [];
$kanbanPaid = [];
$voidedToday = [];
foreach ($ordersList as $_o) {
    $st = (string) ($_o['status'] ?? '');
    $ps = (string) ($_o['payment_status'] ?? 'unpaid');
    $isVoid = ! empty($_o['is_void']) || $st === 'void';
    if ($isVoid) {
        $voidedToday[] = $_o;
        continue;
    }
    if ($st === 'pending') {
        $kanbanPending[] = $_o;
    } elseif ($st === 'washing_drying' || $st === 'running') {
        $kanbanWashing[] = $_o;
    } elseif ($st === 'open_ticket' || ($st === 'completed' && $ps !== 'paid')) {
        $kanbanOpenTicket[] = $_o;
    } elseif ($st === 'paid' || ($st === 'completed' && $ps === 'paid')) {
        $kanbanPaid[] = $_o;
    }
}

$laundryMachinesSummary = static function (array $order): string {
    $wL = trim((string) ($order['washer_machine_label'] ?? ''));
    $dL = trim((string) ($order['dryer_machine_label'] ?? ''));
    $legL = trim((string) ($order['legacy_machine_label'] ?? ''));
    $legK = trim((string) ($order['legacy_machine_kind'] ?? ''));
    if ($wL !== '' || $dL !== '') {
        $parts = [];
        if ($wL !== '') {
            $parts[] = 'W: '.$wL;
        }
        if ($dL !== '') {
            $parts[] = 'D: '.$dL;
        }

        return implode(' · ', $parts);
    }
    if ($legL !== '') {
        return ($legK !== '' ? ucfirst($legK).': ' : '').$legL;
    }

    return '—';
};

$customersForJson = [];
foreach (($customers ?? []) as $_c) {
    $cid = (int) ($_c['id'] ?? 0);
    if ($cid < 1) {
        continue;
    }
    $customersForJson[] = [
        'id' => $cid,
        'name' => (string) ($_c['name'] ?? ''),
        'rewards_balance' => (float) ($_c['rewards_balance'] ?? 0),
    ];
}
$machinesWasherSales = array_values(array_filter($machines ?? [], static fn ($m) => ($m['machine_kind'] ?? 'washer') === 'washer'));
$machinesDryerSales = array_values(array_filter($machines ?? [], static fn ($m) => ($m['machine_kind'] ?? 'washer') === 'dryer'));
$machineOptionLabel = static function (array $machine): string {
    $label = trim((string) ($machine['machine_label'] ?? ''));
    $code = trim((string) ($machine['machine_code'] ?? ''));
    $balance = rtrim(rtrim(number_format((float) ($machine['credit_balance'] ?? 0), 4, '.', ''), '0'), '.');
    if ($balance === '') {
        $balance = '0';
    }
    $credit = ! empty($machine['credit_required'])
        ? ($balance === '0' ? 'No credit' : 'Credit '.$balance)
        : 'Manual';

    return trim($label.' '.($code !== '' ? '('.$code.') ' : '').'- '.$credit);
};
$machineOptionDisabled = static function (array $machine): bool {
    return ! empty($machine['credit_required']) && (float) ($machine['credit_balance'] ?? 0) <= 0;
};
?>

<div class="modal fade" id="laundryAddServiceModal" tabindex="-1" aria-labelledby="laundryAddServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" id="laundrySalesForm" class="laundry-sales-form modal-content">
            <?= csrf_field() ?>
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title" id="laundryAddServiceModalLabel">New service</h5>
                    <p class="small text-muted mb-0 mt-1">Choose order type and add-ons. Saving creates a Pending - Waiting load; machine selection happens when moving it to Washing - Drying.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="rounded-3 border bg-body-secondary bg-opacity-10 p-3 p-md-4">
                            <h6 class="text-uppercase small text-secondary fw-semibold mb-3 letter-spacing-sm">Customer &amp; service</h6>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label mb-1 fw-medium" for="customerSearchInput">Customer</label>
                                    <p class="small text-muted mb-1">Click the field, then type to filter by name. Default is <strong>Walk-in customer</strong> (no customer selected).</p>
                                    <input type="hidden" name="customer_id" id="customerIdHidden" value="">
                                    <input
                                        type="search"
                                        class="form-control"
                                        id="customerSearchInput"
                                        autocomplete="off"
                                        placeholder="Walk-in customer"
                                        value=""
                                        aria-autocomplete="list"
                                        aria-controls="laundryCustomerDropdownPanel"
                                        aria-expanded="false"
                                    >
                                    <script type="application/json" id="laundryCustomersJsonData"><?= json_encode($customersForJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                                    <?php if (($customers ?? []) === []): ?>
                                        <p class="small text-muted mb-0 mt-1">No saved customers yet. Add them under <a href="<?= e(route('tenant.customers.index')) ?>">Customer Profile</a>.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label mb-1 fw-medium" for="orderTypeField">Order type</label>
                                    <?php if ($order_types_list === []): ?>
                                        <p class="small text-danger mb-0">No order types configured. Add them under <strong>Order Pricing</strong>.</p>
                                    <?php else: ?>
                                        <select class="form-select" name="order_type" id="orderTypeField" required>
                                            <?php foreach ($order_types_list as $ot): ?>
                                                <option
                                                    value="<?= e((string) ($ot['code'] ?? '')) ?>"
                                                    data-service-kind="<?= e((string) ($ot['service_kind'] ?? 'full_service')) ?>"
                                                    data-price="<?= e((string) (float) ($ot['price_per_load'] ?? 0)) ?>"
                                                    data-supply-block="<?= e((string) ($ot['supply_block'] ?? 'none')) ?>"
                                                    data-show-addon-supplies="<?= ! empty($ot['show_addon_supplies']) ? '1' : '0' ?>"
                                                ><?= e((string) ($ot['label'] ?? '')) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label class="form-label mb-1 fw-medium">Service mode</label>
                                    <div class="btn-group flex-wrap" role="group" aria-label="Service mode">
                                        <input type="radio" class="btn-check" name="service_mode" id="serviceModeRegular" value="regular" checked>
                                        <label class="btn btn-outline-primary" for="serviceModeRegular">Regular</label>
                                        <input type="radio" class="btn-check" name="service_mode" id="serviceModeFree" value="free">
                                        <label class="btn btn-outline-secondary" for="serviceModeFree">Free</label>
                                        <input type="radio" class="btn-check" name="service_mode" id="serviceModeReward" value="reward">
                                        <label
                                            class="btn btn-outline-success"
                                            for="serviceModeReward"
                                            id="serviceModeRewardLabel"
                                            data-reward-threshold="<?= e((string) $rewardThreshold) ?>"
                                            data-reward-order-type="<?= e($rewardOrderTypeCode) ?>"
                                        >Reward</label>
                                    </div>
                                    <div class="small text-muted mt-1" id="rewardAvailabilityText">Select a saved customer to check reward availability.</div>
                                </div>
                            </div>
                            <div class="form-check mt-3" id="foldServiceWrap">
                                <input class="form-check-input" type="checkbox" name="include_fold_service" id="includeFoldService" value="1">
                                <label class="form-check-label" for="includeFoldService">Include fold service?</label>
                                <p class="small text-muted mb-0 ms-4">When checked, this order counts toward staff folding commission (full service types only).</p>
                            </div>
                            <input type="hidden" name="use_machines" value="<?= $machineAssignmentEnabled ? '1' : '0' ?>">
                            <?php if (! $machineAssignmentEnabled): ?>
                                <p class="small text-muted mt-2 mb-0">Machine assignment is disabled in Branch Settings. This transaction will be saved without machine selection.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="rounded-3 border border-secondary-subtle bg-white p-3 p-md-3" id="laundryOrderSummaryCard">
                            <h6 class="text-uppercase small text-secondary fw-semibold mb-2 letter-spacing-sm">Order summary</h6>
                            <ul class="list-unstyled small mb-2" id="laundrySummaryBase">
                                <li class="text-muted">Select options to see pricing.</li>
                            </ul>
                            <div class="border-top pt-2 mb-2 d-none" id="laundrySummaryFullServiceBlock">
                                <div class="fw-semibold small mb-1" id="laundrySummaryInclusionsTitle">Service supplies (stock)</div>
                                <ul class="list-unstyled small mb-0 text-muted" id="laundrySummaryInclusions"></ul>
                            </div>
                            <div class="border-top pt-2 mb-2 d-none" id="laundrySummaryAddonsBlock">
                                <div class="fw-semibold small mb-1">Add-ons (charged)</div>
                                <ul class="list-unstyled small mb-0" id="laundrySummaryAddons"></ul>
                            </div>
                            <div class="d-flex justify-content-between align-items-center fw-semibold border-top pt-2">
                                <span>Estimated total</span>
                                <span id="laundrySummaryTotal" class="font-monospace">₱0.00</span>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="wash_qty" value="1">
                    <input type="hidden" name="dry_qty" value="1">

                    <div class="col-12" id="fullServiceInclusionsWrap">
                        <div class="rounded-3 border p-3 p-md-4">
                            <h6 class="text-uppercase small text-secondary fw-semibold mb-1 letter-spacing-sm" id="coreSuppliesHeading">Service supplies (stock)</h6>
                            <p class="small text-muted mb-3" id="coreSuppliesHelp">Choose which inventory items are used for this service (1× each where applicable). Charged add-ons are separate below.</p>
                            <div class="row g-3">
                                <div class="col-12 col-lg-4" id="inclusionDetergentCol">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <label class="form-label mb-2 small fw-medium" for="inclusionDetergentSelect">Detergent</label>
                                            <select class="form-select form-select-sm mb-2" name="inclusion_detergent_item_id" id="inclusionDetergentSelect">
                                                <option value="">Select product</option>
                                                <?php foreach (($detergent_items ?? []) as $item): ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>"><?= e($laundryStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label mb-1 small text-secondary" for="inclusionDetergentQtyDisplay">Quantity</label>
                                            <input type="text" class="form-control form-control-sm bg-body-secondary" id="inclusionDetergentQtyDisplay" value="1" readonly tabindex="-1" autocomplete="off" aria-readonly="true">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4" id="inclusionFabconCol">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-1 mb-2">
                                                <label class="form-label mb-0 small fw-medium" for="inclusionFabconSelect">Fabric conditioner</label>
                                                <span id="inclusionFabconOptionalHint" class="small text-muted d-none">Optional</span>
                                            </div>
                                            <select class="form-select form-select-sm mb-2" name="inclusion_fabcon_item_id" id="inclusionFabconSelect">
                                                <option value="" id="inclusionFabconPlaceholderOpt">Select product</option>
                                                <?php foreach (($fabcon_items ?? []) as $item): ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>"><?= e($laundryStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label mb-1 small text-secondary" for="inclusionFabconQtyDisplay">Quantity</label>
                                            <input type="text" class="form-control form-control-sm bg-body-secondary" id="inclusionFabconQtyDisplay" value="1" readonly tabindex="-1" autocomplete="off" aria-readonly="true">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4" id="inclusionBleachCol">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-1 mb-2">
                                                <label class="form-label mb-0 small fw-medium" for="inclusionBleachSelect">Bleach</label>
                                                <span class="small text-muted">Optional</span>
                                            </div>
                                            <select class="form-select form-select-sm mb-2" name="inclusion_bleach_item_id" id="inclusionBleachSelect">
                                                <option value="">None</option>
                                                <?php foreach (($bleach_items ?? []) as $item): ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>"><?= e($laundryStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label mb-1 small text-secondary" for="inclusionBleachQtyDisplay">Quantity</label>
                                            <input type="text" class="form-control form-control-sm bg-body-secondary" id="inclusionBleachQtyDisplay" value="1" readonly tabindex="-1" autocomplete="off" aria-readonly="true">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12" id="addonSuppliesSection">
                        <div class="rounded-3 border p-3 p-md-4 bg-light bg-opacity-50">
                            <h6 class="text-uppercase small text-secondary fw-semibold mb-1 letter-spacing-sm">Add-on (extra)</h6>
                            <p class="small text-muted mb-3">Extra supplies billed on top of the base service price. Quantities here are charged in addition to any included stock use above.</p>
                            <div class="row g-3">
                                <div class="col-12 col-lg-4" id="detergentWrap">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <label class="form-label mb-2 small fw-medium" for="addonDetergentSelect">Detergent</label>
                                            <select class="form-select form-select-sm mb-2" name="addon_detergent_item_id" id="addonDetergentSelect">
                                                <option value="">Select product</option>
                                                <?php foreach (($detergent_items ?? []) as $item): ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label visually-hidden" for="detergentQtyInput">Detergent extra qty</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Extra qty</span>
                                                <input type="number" min="0" step="0.01" value="0" class="form-control" name="detergent_qty" id="detergentQtyInput" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4" id="fabconWrap">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <label class="form-label mb-2 small fw-medium" for="addonFabconSelect">Fabric conditioner</label>
                                            <select class="form-select form-select-sm mb-2" name="addon_fabcon_item_id" id="addonFabconSelect">
                                                <option value="">Select product</option>
                                                <?php foreach (($fabcon_items ?? []) as $item): ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label visually-hidden" for="fabconQtyInput">Fabcon extra qty</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Extra qty</span>
                                                <input type="number" min="0" step="0.01" value="0" class="form-control" name="fabcon_qty" id="fabconQtyInput" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-lg-4" id="bleachWrap">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <label class="form-label mb-2 small fw-medium" for="addonBleachSelect">Bleach</label>
                                            <select class="form-select form-select-sm mb-2" name="addon_bleach_item_id" id="addonBleachSelect">
                                                <option value="">Select product</option>
                                                <?php foreach (($bleach_items ?? []) as $item): ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label visually-hidden" for="bleachQtyInput">Bleach qty</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Extra qty</span>
                                                <input type="number" min="0" step="0.01" value="0" class="form-control" name="bleach_qty" id="bleachQtyInput" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top bg-body-tertiary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4" type="submit" <?= ($order_types_list ?? []) === [] ? 'disabled' : '' ?>>
                    <i class="fa-solid fa-floppy-disk me-2"></i>Save transaction
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .letter-spacing-sm { letter-spacing: 0.04em; }
    .laundry-kanban-list {
        min-height: 220px;
        max-height: min(70vh, 640px);
        overflow-y: auto;
    }
    .laundry-kanban-card--draggable { cursor: grab; }
    .laundry-kanban-card--draggable:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.45; }
    .laundry-sales-table-scroll {
        max-height: min(70vh, 640px);
        overflow: auto;
    }
    #laundryCustomerDropdownPanel.laundry-customer-dd-panel {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.12);
    }
    .laundry-customer-dd-item:hover,
    .laundry-customer-dd-item:focus {
        background-color: var(--bs-secondary-bg);
    }
</style>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if (! $isCashier): ?>
                    <a href="<?= e(route('tenant.staff-portal.index')) ?>" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-plus me-1"></i>Add service
                    </a>
                    <button type="button" id="exportSalesExcelBtn" class="btn btn-outline-success btn-sm">
                        <i class="fa-solid fa-file-excel me-1"></i>Export Excel
                    </button>
                    <button type="button" id="exportSalesPdfBtn" class="btn btn-outline-danger btn-sm">
                        <i class="fa-solid fa-file-pdf me-1"></i>Export PDF
                    </button>
                <?php else: ?>
                    <span class="badge text-bg-info">Use Staff Kiosk Portal for new transactions</span>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0 text-nowrap" for="laundrySalesViewSelect">Layout</label>
                <select class="form-select form-select-sm" id="laundrySalesViewSelect" style="width: auto; min-width: 11rem;" aria-label="Sales layout">
                    <option value="kanban">Kanban board</option>
                    <option value="table">Table</option>
                </select>
            </div>
        </div>

        <div id="laundrySalesKanbanWrap" class="laundry-sales-view-panel">
        <div class="row g-3 laundry-kanban-board">
            <?php
            $kanbanCols = [
                'pending' => [
                    'title' => 'Pending - Waiting',
                    'badge' => 'bg-secondary',
                    'border' => 'border-secondary border-opacity-50',
                    'hint' => 'Queued loads waiting to be placed on machine.',
                    'list' => $kanbanPending,
                ],
                'washing_drying' => [
                    'title' => 'Washing - Drying',
                    'badge' => 'bg-warning text-dark',
                    'border' => 'border-warning border-opacity-50',
                    'hint' => 'Cycle in progress. Move to Finishing - To Be Picked Up when cycle is finished (releases machines).',
                    'list' => $kanbanWashing,
                ],
                'open_ticket' => [
                    'title' => 'Finishing - To Be Picked Up',
                    'badge' => 'bg-info text-dark',
                    'border' => 'border-info border-opacity-50',
                    'hint' => 'Ready for pickup. Drag to Paid - Completed to finalize.',
                    'list' => $kanbanOpenTicket,
                ],
                'paid' => [
                    'title' => 'Paid - Completed',
                    'badge' => 'bg-success',
                    'border' => 'border-success border-opacity-50',
                    'hint' => 'Transaction completed.',
                    'list' => $kanbanPaid,
                ],
            ];
            foreach ($kanbanCols as $colKey => $meta):
                $isDraggableCol = $colKey !== 'paid';
            ?>
            <div class="col-lg-3">
                <div class="card h-100 border-2 <?= e($meta['border']) ?>">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-1">
                        <span class="fw-semibold"><span class="badge <?= e($meta['badge']) ?> me-1"><?= e($meta['title']) ?></span></span>
                        <span class="badge bg-secondary rounded-pill"><?= count($meta['list']) ?></span>
                    </div>
                    <p class="small text-muted px-3 pt-2 mb-0"><?= e($meta['hint']) ?></p>
                    <div class="card-body p-2 pt-2">
                        <div
                            class="laundry-kanban-list rounded-2 border border-dashed p-2 bg-body-secondary bg-opacity-25"
                            id="kanban-<?= e($colKey) ?>"
                            data-kanban-column="<?= e($colKey) ?>"
                        >
                            <?php foreach ($meta['list'] as $order):
                                $oid = (int) ($order['id'] ?? 0);
                                $refDisplay = trim((string) ($order['reference_code'] ?? ''));
                                if ($refDisplay === '') {
                                    $refDisplay = '—';
                                }
                                $otLabel = trim((string) ($order['order_type_label'] ?? ''));
                                $typeDisp = $otLabel !== '' ? $otLabel : ucwords(str_replace('_', ' ', (string) ($order['order_type'] ?? '')));
                                $pmRaw = strtolower(trim((string) ($order['payment_method'] ?? '')));
                                $paymentLabel = $laundryPaymentMethodLabel($pmRaw);
                                $serviceModeLabel = ! empty($order['is_reward'])
                                    ? 'Reward'
                                    : (! empty($order['is_free']) ? 'Free' : 'Regular');
                                $dragClass = $isDraggableCol ? 'laundry-kanban-card--draggable' : '';
                                $createdAtDisplay = trim((string) ($order['created_at'] ?? ''));
                                ?>
                                <div
                                    class="card mb-2 shadow-sm laundry-kanban-card border-0 <?= e($dragClass) ?>"
                                    data-order-id="<?= $oid ?>"
                                    data-detail-url="<?= e(route('tenant.laundry-sales.detail', ['id' => $oid])) ?>"
                                    data-advance-url="<?= e(route('tenant.laundry-sales.advance', ['id' => $oid])) ?>"
                                    data-complete-url="<?= e(route('tenant.laundry-sales.complete', ['id' => $oid])) ?>"
                                    data-pay-url="<?= e(route('tenant.laundry-sales.pay', ['id' => $oid])) ?>"
                                    data-void-url="<?= e(route('tenant.laundry-sales.void', ['id' => $oid])) ?>"
                                    data-is-free="<?= ! empty($order['is_free']) ? '1' : '0' ?>"
                                    data-is-reward="<?= ! empty($order['is_reward']) ? '1' : '0' ?>"
                                    data-total="<?= e((string) (float) ($order['total_amount'] ?? 0)) ?>"
                                    data-service-kind="<?= e((string) ($order['order_type_service_kind'] ?? 'full_service')) ?>"
                                    data-reference-code="<?= e($refDisplay) ?>"
                                    data-customer-name="<?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?>"
                                    data-order-type-label="<?= e($typeDisp) ?>"
                                    data-service-mode-label="<?= e($serviceModeLabel) ?>"
                                    data-created-at="<?= e($createdAtDisplay) ?>"
                                >
                                    <div class="card-body py-2 px-2">
                                        <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
                                            <span class="fw-semibold font-monospace"><?= e($refDisplay) ?></span>
                                            <div class="d-flex flex-column align-items-end gap-0 text-end">
                                                <button type="button" class="btn btn-link btn-sm p-0 small text-decoration-none no-drag laundry-kanban-detail-trigger" data-detail-url="<?= e(route('tenant.laundry-sales.detail', ['id' => $oid])) ?>">View details</button>
                                                <span class="small text-muted text-nowrap"><?= e((string) ($order['created_at'] ?? '')) ?></span>
                                            </div>
                                        </div>
                                        <div class="small fw-medium text-truncate" title="<?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?>"><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></div>
                                        <div class="small text-muted text-truncate"><?= e($typeDisp) ?></div>
                                        <div class="small text-muted mb-1"><?= e($laundryMachinesSummary($order)) ?></div>
                                        <div class="small mb-1">
                                            <span class="text-muted">Mode:</span>
                                            <span class="fw-semibold"><?= e($serviceModeLabel) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center small">
                                            <span class="text-muted">Total</span>
                                            <span class="fw-semibold font-monospace"><?= e(format_money((float) ($order['total_amount'] ?? 0))) ?></span>
                                        </div>
                                        <?php if ($colKey === 'pending' || $colKey === 'paid' || $isTenantAdmin): ?>
                                            <div class="mt-1 pt-1 border-top d-flex align-items-center gap-2 flex-wrap">
                                                <?php if ($colKey === 'pending'): ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 no-drag laundry-kanban-action-reprint">Print Reference</button>
                                                <?php endif; ?>
                                                <?php if ($colKey === 'paid'): ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 no-drag laundry-kanban-action-print-receipt">Print Receipt</button>
                                                <?php endif; ?>
                                                <?php if ($isTenantAdmin): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 no-drag laundry-kanban-action-void">Void</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($colKey === 'paid'): ?>
                                            <div class="small mt-1 pt-1 border-top">
                                                <span class="text-muted"><?= e($paymentLabel) ?></span>
                                                <?php if (isset($order['amount_tendered']) && $order['amount_tendered'] !== null && $order['amount_tendered'] !== ''): ?>
                                                    <div class="text-muted">Tendered <?= e(format_money((float) $order['amount_tendered'])) ?>
                                                        <?php if (isset($order['change_amount']) && (float) $order['change_amount'] > 0): ?>
                                                            · Change <?= e(format_money((float) $order['change_amount'])) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($meta['list'] === []): ?>
                                <div class="text-center text-muted small py-4 kanban-empty-placeholder">No transactions</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($voidedToday !== []): ?>
            <div class="card mt-3 border-danger-subtle">
                <div class="card-body">
                    <h6 class="mb-2 text-danger">Voided transactions (today)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                            <tr>
                                <th>Reference No.</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Void reason</th>
                                <th class="text-end">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($voidedToday as $order): ?>
                                <?php
                                $refDisplay = trim((string) ($order['reference_code'] ?? ''));
                                if ($refDisplay === '') {
                                    $refDisplay = '—';
                                }
                                $otLabel = trim((string) ($order['order_type_label'] ?? ''));
                                $typeDisp = $otLabel !== '' ? $otLabel : ucwords(str_replace('_', ' ', (string) ($order['order_type'] ?? '')));
                                ?>
                                <tr>
                                    <td class="font-monospace"><?= e($refDisplay) ?></td>
                                    <td class="small text-nowrap"><?= e((string) ($order['created_at'] ?? '')) ?></td>
                                    <td><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></td>
                                    <td class="small"><?= e($typeDisp) ?></td>
                                    <td class="small"><?= e(trim((string) ($order['void_reason'] ?? '')) !== '' ? (string) $order['void_reason'] : '—') ?></td>
                                    <td class="text-end fw-semibold"><?= e(format_money((float) ($order['total_amount'] ?? 0))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div>

        <div id="laundrySalesTableWrap" class="laundry-sales-view-panel d-none">
            <div class="table-responsive border rounded-2 laundry-sales-table-scroll">
                <table class="table table-sm table-hover align-middle mb-0" id="laundrySalesTableInteractive">
                    <thead class="table-light">
                    <tr>
                        <th>Reference No.</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Fold</th>
                        <th>Machines</th>
                        <th>Load Status</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Add-ons</th>
                        <th class="text-end">Total</th>
                        <th>Payment</th>
                        <th class="text-end text-nowrap">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ordersList as $order): ?>
                        <?php
                        $oid = (int) ($order['id'] ?? 0);
                        $refDisplay = trim((string) ($order['reference_code'] ?? ''));
                        if ($refDisplay === '') {
                            $refDisplay = '—';
                        }
                        $paymentStatus = (string) ($order['payment_status'] ?? 'paid');
                        $isPaid = $paymentStatus === 'paid';
                        $statusRaw = (string) ($order['status'] ?? '');
                        $isVoid = ! empty($order['is_void']) || $statusRaw === 'void';
                        $isPending = $statusRaw === 'pending';
                        $isWashingDrying = ($statusRaw === 'washing_drying' || $statusRaw === 'running');
                        $isOpenTicket = ($statusRaw === 'open_ticket' || ($statusRaw === 'completed' && ! $isPaid));
                        $pmRaw = strtolower(trim((string) ($order['payment_method'] ?? '')));
                        $paymentLabel = $laundryPaymentMethodLabel($pmRaw);
                        $statusExport = $isVoid
                            ? 'VOID'
                            : ($isPending
                            ? 'Pending - Waiting'
                            : ($isWashingDrying ? 'Washing - Drying' : ($isOpenTicket ? 'Finishing - To Be Picked Up' : 'Paid - Completed')));
                        $otLabel = trim((string) ($order['order_type_label'] ?? ''));
                        $typeDisp = $otLabel !== '' ? $otLabel : ucwords(str_replace('_', ' ', (string) ($order['order_type'] ?? '')));
                        ?>
                        <tr
                            class="laundry-sales-table-row"
                            data-order-id="<?= $oid ?>"
                            data-detail-url="<?= e(route('tenant.laundry-sales.detail', ['id' => $oid])) ?>"
                            data-advance-url="<?= e(route('tenant.laundry-sales.advance', ['id' => $oid])) ?>"
                            data-complete-url="<?= e(route('tenant.laundry-sales.complete', ['id' => $oid])) ?>"
                            data-pay-url="<?= e(route('tenant.laundry-sales.pay', ['id' => $oid])) ?>"
                            data-void-url="<?= e(route('tenant.laundry-sales.void', ['id' => $oid])) ?>"
                            data-is-free="<?= ! empty($order['is_free']) ? '1' : '0' ?>"
                            data-is-reward="<?= ! empty($order['is_reward']) ? '1' : '0' ?>"
                            data-total="<?= e((string) (float) ($order['total_amount'] ?? 0)) ?>"
                            data-service-kind="<?= e((string) ($order['order_type_service_kind'] ?? 'full_service')) ?>"
                        >
                            <td class="font-monospace"><?= e($refDisplay) ?></td>
                            <td class="text-nowrap small"><?= e((string) ($order['created_at'] ?? '')) ?></td>
                            <td><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></td>
                            <td class="small"><?= e($typeDisp) ?></td>
                            <td class="small"><?= ! empty($order['include_fold_service']) ? 'Yes' : '—' ?></td>
                            <td class="small"><?= e($laundryMachinesSummary($order)) ?></td>
                            <td>
                                <?php if ($isVoid): ?>
                                    <span class="badge text-bg-danger">VOID</span>
                                <?php else: ?>
                                    <span class="small"><?= e($statusExport) ?></span>
                                <?php endif; ?>
                                <?php if (! empty($order['is_reward'])): ?><span class="badge text-bg-info text-dark ms-1">Reward</span><?php endif; ?>
                                <?php if (! empty($order['is_free'])): ?><span class="badge text-bg-secondary ms-1">Free</span><?php endif; ?>
                            </td>
                            <td class="text-end small"><?= e(format_money((float) ($order['subtotal'] ?? 0))) ?></td>
                            <td class="text-end small"><?= e(format_money((float) ($order['add_on_total'] ?? 0))) ?></td>
                            <td class="text-end small fw-semibold"><?= e(format_money((float) ($order['total_amount'] ?? 0))) ?></td>
                            <td class="small">
                                <?php if ($isPaid && $pmRaw !== 'pending'): ?>
                                    <?= e($paymentLabel) ?>
                                    <?php if (isset($order['amount_tendered']) && $order['amount_tendered'] !== null && $order['amount_tendered'] !== ''): ?>
                                        · Tendered <?= e(format_money((float) $order['amount_tendered'])) ?>
                                        <?php if (isset($order['change_amount']) && (float) $order['change_amount'] > 0): ?>
                                            · Change <?= e(format_money((float) $order['change_amount'])) ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Unpaid
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap small">
                                <button type="button" class="btn btn-link btn-sm p-0 me-1 laundry-table-detail-trigger" data-detail-url="<?= e(route('tenant.laundry-sales.detail', ['id' => $oid])) ?>">Details</button>
                                <?php if ($isPending): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 laundry-table-action-start">Start Washing - Drying</button>
                                <?php elseif ($isWashingDrying): ?>
                                    <button type="button" class="btn btn-outline-info btn-sm py-0 laundry-table-action-open-ticket">Finishing - To Be Picked Up</button>
                                <?php elseif ($isOpenTicket && ! $isPaid): ?>
                                    <button type="button" class="btn btn-outline-success btn-sm py-0 laundry-table-action-pay">Paid - Completed</button>
                                <?php endif; ?>
                                <?php if (! $isVoid && $isTenantAdmin): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 laundry-table-action-void">Void</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($ordersList === []): ?>
                <p class="text-muted small mb-0 mt-2">No transactions yet.</p>
            <?php endif; ?>
        </div>

        <div class="visually-hidden" aria-hidden="true">
            <table class="table table-striped table-hover mb-0 align-middle" id="laundrySalesTable">
                <thead class="table-light">
                <tr>
                    <th>Reference No.</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Fold</th>
                    <th>Machines</th>
                    <th>Load Status</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Add-ons</th>
                    <th class="text-end">Total</th>
                    <th>Payment</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ordersList as $order): ?>
                    <?php
                    $refDisplay = trim((string) ($order['reference_code'] ?? ''));
                    if ($refDisplay === '') {
                        $refDisplay = '—';
                    }
                    $paymentStatus = (string) ($order['payment_status'] ?? 'paid');
                    $isPaid = $paymentStatus === 'paid';
                    $statusRaw = (string) ($order['status'] ?? '');
                    $isVoid = ! empty($order['is_void']) || $statusRaw === 'void';
                    $isPending = $statusRaw === 'pending';
                    $isWashingDrying = ($statusRaw === 'washing_drying' || $statusRaw === 'running');
                    $isOpenTicket = ($statusRaw === 'open_ticket' || ($statusRaw === 'completed' && ! $isPaid));
                    $pmRaw = strtolower(trim((string) ($order['payment_method'] ?? '')));
                    $paymentLabel = $laundryPaymentMethodLabel($pmRaw);
                    $statusExport = $isVoid
                        ? 'VOID'
                        : ($isPending
                        ? 'Pending - Waiting'
                        : ($isWashingDrying ? 'Washing - Drying' : ($isOpenTicket ? 'Finishing - To Be Picked Up' : 'Paid - Completed')));
                    ?>
                    <tr>
                        <td><?= e($refDisplay) ?></td>
                        <td class="text-nowrap small"><?= e((string) ($order['created_at'] ?? '')) ?></td>
                        <td><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></td>
                        <td><?php
                            $otLabel = trim((string) ($order['order_type_label'] ?? ''));
                            echo e($otLabel !== '' ? $otLabel : ucwords(str_replace('_', ' ', (string) ($order['order_type'] ?? ''))));
                            ?></td>
                        <td class="small"><?= ! empty($order['include_fold_service']) ? 'Yes' : '—' ?></td>
                        <td class="small"><?= e($laundryMachinesSummary($order)) ?></td>
                        <td><?= e($statusExport) ?></td>
                        <td class="text-end"><?= e(format_money((float) ($order['subtotal'] ?? 0))) ?></td>
                        <td class="text-end"><?= e(format_money((float) ($order['add_on_total'] ?? 0))) ?></td>
                        <td class="text-end fw-semibold"><?= e(format_money((float) ($order['total_amount'] ?? 0))) ?></td>
                        <td>
                            <?php if ($isPaid && $pmRaw !== 'pending'): ?>
                                <?= e($paymentLabel) ?>
                                <?php if (isset($order['amount_tendered']) && $order['amount_tendered'] !== null && $order['amount_tendered'] !== ''): ?>
                                    · Tendered <?= e(format_money((float) $order['amount_tendered'])) ?>
                                    <?php if (isset($order['change_amount']) && (float) $order['change_amount'] > 0): ?>
                                        · Change <?= e(format_money((float) $order['change_amount'])) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php else: ?>
                                Unpaid
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="laundryPayModal" tabindex="-1" aria-labelledby="laundryPayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content" id="laundryPayForm" autocomplete="off">
            <?= csrf_field() ?>
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title" id="laundryPayModalLabel">Record payment</h5>
                    <p class="small text-muted mb-0">Select method, enter amount received; change is computed automatically.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="rounded-3 bg-body-secondary bg-opacity-50 p-3 mb-3">
                    <div class="small text-muted mb-0">Service total (amount due)</div>
                    <div class="fs-5 fw-semibold font-monospace" id="laundryPayDueDisplay">₱0.00</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium" for="laundryPayDiscountPercent">Discount (%)</label>
                    <input
                        type="number"
                        class="form-control form-control-lg font-monospace"
                        name="discount_percentage"
                        id="laundryPayDiscountPercent"
                        min="0"
                        max="100"
                        step="0.01"
                        value="0"
                        required
                        placeholder="0"
                    >
                    <div class="form-text">Default is 0%. Total due updates automatically.</div>
                </div>
                <label class="form-label small fw-semibold mb-2">Payment method</label>
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-4">
                        <input type="radio" class="btn-check" name="payment_method" id="laundry-pm-cash" value="cash" checked>
                        <label class="btn btn-outline-secondary border-2 w-100 py-3 laundry-pay-card" for="laundry-pm-cash">
                            <span class="d-block fs-4 mb-1 text-success"><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i></span>
                            <span class="small fw-medium">Cash</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-4">
                        <input type="radio" class="btn-check" name="payment_method" id="laundry-pm-gcash" value="gcash">
                        <label class="btn btn-outline-secondary border-2 w-100 py-3 laundry-pay-card" for="laundry-pm-gcash">
                            <span class="d-block fs-4 mb-1 text-primary"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></span>
                            <span class="small fw-medium">GCash</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-4">
                        <input type="radio" class="btn-check" name="payment_method" id="laundry-pm-paymaya" value="paymaya">
                        <label class="btn btn-outline-secondary border-2 w-100 py-3 laundry-pay-card" for="laundry-pm-paymaya">
                            <span class="d-block fs-4 mb-1" style="color:#0c6;"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span>
                            <span class="small fw-medium">PayMaya</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-4">
                        <input type="radio" class="btn-check" name="payment_method" id="laundry-pm-bank" value="online_banking">
                        <label class="btn btn-outline-secondary border-2 w-100 py-3 laundry-pay-card" for="laundry-pm-bank">
                            <span class="d-block fs-4 mb-1 text-secondary"><i class="fa-solid fa-building-columns" aria-hidden="true"></i></span>
                            <span class="small fw-medium">Online banking</span>
                        </label>
                    </div>
                    <div class="col-6 col-md-4">
                        <input type="radio" class="btn-check" name="payment_method" id="laundry-pm-qr" value="qr_payment">
                        <label class="btn btn-outline-secondary border-2 w-100 py-3 laundry-pay-card" for="laundry-pm-qr">
                            <span class="d-block fs-4 mb-1 text-dark"><i class="fa-solid fa-qrcode" aria-hidden="true"></i></span>
                            <span class="small fw-medium">QR payment</span>
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium" for="laundryPayTendered">Amount paid (tendered)</label>
                    <input type="number" class="form-control form-control-lg font-monospace" name="amount_tendered" id="laundryPayTendered" min="0" step="0.01" required placeholder="0.00">
                </div>
                <div class="rounded-3 border p-3 bg-body-tertiary bg-opacity-25">
                    <div class="small text-muted mb-0">Change (amount paid − service total)</div>
                    <div class="fs-5 fw-semibold font-monospace text-success" id="laundryPayChangeDisplay">₱0.00</div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm payment</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="laundryProcessErrorModal" tabindex="-1" aria-labelledby="laundryProcessErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title" id="laundryProcessErrorModalLabel">Could not complete action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <p class="mb-0 text-break" id="laundryProcessErrorMessage"></p>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="laundryVoidModal" tabindex="-1" aria-labelledby="laundryVoidModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="laundryVoidForm" autocomplete="off">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title" id="laundryVoidModalLabel">Void transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <label class="form-label fw-medium" for="laundryVoidReason">Reason for voiding this transaction</label>
                <textarea class="form-control" id="laundryVoidReason" rows="3" required></textarea>
                <div class="form-text">Reason is required.</div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Void transaction</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="laundryMachineAssignModal" tabindex="-1" aria-labelledby="laundryMachineAssignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="laundryMachineAssignForm">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title" id="laundryMachineAssignModalLabel">Select machine</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <p class="small text-muted">Choose the available machine before moving this load to Washing - Drying.</p>
                <div class="mb-3" id="assignWasherWrap">
                    <label class="form-label mb-1" for="assignWasherSelect">Washer</label>
                    <select class="form-select" id="assignWasherSelect" name="washer_machine_id">
                        <option value="">Select washer</option>
                        <?php foreach ($machinesWasherSales as $machine): ?>
                            <option value="<?= (int) ($machine['id'] ?? 0) ?>" <?= $machineOptionDisabled($machine) ? 'disabled' : '' ?>><?= e($machineOptionLabel($machine)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-0" id="assignDryerWrap">
                    <label class="form-label mb-1" for="assignDryerSelect">Dryer</label>
                    <select class="form-select" id="assignDryerSelect" name="dryer_machine_id">
                        <option value="">Select dryer</option>
                        <?php foreach ($machinesDryerSales as $machine): ?>
                            <option value="<?= (int) ($machine['id'] ?? 0) ?>" <?= $machineOptionDisabled($machine) ? 'disabled' : '' ?>><?= e($machineOptionLabel($machine)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-danger py-2 mt-3 mb-0 d-none" id="laundryMachineAssignError"></div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Start Washing - Drying</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="laundryOrderDetailModal" tabindex="-1" aria-labelledby="laundryOrderDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title" id="laundryOrderDetailTitle">Transaction details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0" id="laundryOrderDetailBody">
                <div class="text-muted small">Loading…</div>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(url('vendor/xlsx/xlsx.full.min.js')) ?>"></script>
<script src="<?= e(url('vendor/jspdf/jspdf.umd.min.js')) ?>"></script>
<script src="<?= e(url('vendor/jspdf/jspdf.plugin.autotable.min.js')) ?>"></script>
<script src="<?= e(url('vendor/sortablejs/Sortable.min.js')) ?>"></script>
<script>
(() => {
    const modal = document.getElementById('laundryAddServiceModal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    const payModal = document.getElementById('laundryPayModal');
    if (payModal && payModal.parentElement !== document.body) {
        document.body.appendChild(payModal);
    }
    const errModal = document.getElementById('laundryProcessErrorModal');
    if (errModal && errModal.parentElement !== document.body) {
        document.body.appendChild(errModal);
    }
    const voidModal = document.getElementById('laundryVoidModal');
    if (voidModal && voidModal.parentElement !== document.body) {
        document.body.appendChild(voidModal);
    }
    const assignModal = document.getElementById('laundryMachineAssignModal');
    if (assignModal && assignModal.parentElement !== document.body) {
        document.body.appendChild(assignModal);
    }
    const detailModal = document.getElementById('laundryOrderDetailModal');
    if (detailModal && detailModal.parentElement !== document.body) {
        document.body.appendChild(detailModal);
    }
})();

(() => {
    const table = document.getElementById('laundrySalesTable');
    const excelBtn = document.getElementById('exportSalesExcelBtn');
    const pdfBtn = document.getElementById('exportSalesPdfBtn');
    if (!table || !excelBtn || !pdfBtn) return;

    excelBtn.addEventListener('click', () => {
        if (!window.XLSX) return;
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.table_to_sheet(table);
        XLSX.utils.book_append_sheet(wb, ws, 'DailySales');
        XLSX.writeFile(wb, `laundry_daily_sales_${new Date().toISOString().slice(0, 10)}.xlsx`);
    });

    pdfBtn.addEventListener('click', () => {
        if (!window.jspdf || !window.jspdf.jsPDF) return;
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
        doc.setFontSize(12);
        doc.text('Laundry Daily Sales Report', 40, 40);
        doc.autoTable({
            html: '#laundrySalesTable',
            startY: 55,
            styles: { fontSize: 8, cellPadding: 4 },
            headStyles: { fillColor: [25, 135, 84] },
        });
        doc.save(`laundry_daily_sales_${new Date().toISOString().slice(0, 10)}.pdf`);
    });
})();

(() => {
    const orderTypeField = document.getElementById('orderTypeField');
    const fullServiceInclusionsWrap = document.getElementById('fullServiceInclusionsWrap');
    const inclusionDetergentSelect = document.getElementById('inclusionDetergentSelect');
    const inclusionFabconSelect = document.getElementById('inclusionFabconSelect');
    const inclusionBleachSelect = document.getElementById('inclusionBleachSelect');
    const detergentWrap = document.getElementById('detergentWrap');
    const fabconWrap = document.getElementById('fabconWrap');
    const bleachWrap = document.getElementById('bleachWrap');
    const form = document.getElementById('laundrySalesForm');
    const modalEl = document.getElementById('laundryAddServiceModal');
    const customerIdHidden = document.getElementById('customerIdHidden');
    const customerSearchInput = document.getElementById('customerSearchInput');
    const regularModeRadio = document.getElementById('serviceModeRegular');
    const freeModeRadio = document.getElementById('serviceModeFree');
    const rewardModeRadio = document.getElementById('serviceModeReward');
    const rewardModeLabel = document.getElementById('serviceModeRewardLabel');
    const rewardAvailabilityText = document.getElementById('rewardAvailabilityText');
    const foldServiceWrap = document.getElementById('foldServiceWrap');
    const includeFoldService = document.getElementById('includeFoldService');
    const addonDetergentSelect = document.getElementById('addonDetergentSelect');
    const addonFabconSelect = document.getElementById('addonFabconSelect');
    const addonBleachSelect = document.getElementById('addonBleachSelect');
    const detergentQtyInput = document.getElementById('detergentQtyInput');
    const fabconQtyInput = document.getElementById('fabconQtyInput');
    const bleachQtyInput = document.getElementById('bleachQtyInput');
    const laundrySummaryBase = document.getElementById('laundrySummaryBase');
    const laundrySummaryFullServiceBlock = document.getElementById('laundrySummaryFullServiceBlock');
    const laundrySummaryInclusions = document.getElementById('laundrySummaryInclusions');
    const laundrySummaryAddonsBlock = document.getElementById('laundrySummaryAddonsBlock');
    const laundrySummaryAddons = document.getElementById('laundrySummaryAddons');
    const laundrySummaryTotal = document.getElementById('laundrySummaryTotal');
    const laundrySummaryInclusionsTitle = document.getElementById('laundrySummaryInclusionsTitle');
    const coreSuppliesHeading = document.getElementById('coreSuppliesHeading');
    const coreSuppliesHelp = document.getElementById('coreSuppliesHelp');
    const addonSuppliesSection = document.getElementById('addonSuppliesSection');
    if (!orderTypeField || !fullServiceInclusionsWrap
        || !detergentWrap || !fabconWrap || !bleachWrap
        || !form || !modalEl
        || !customerIdHidden || !customerSearchInput) return;

    let customerList = [];
    try {
        const jsonEl = document.getElementById('laundryCustomersJsonData');
        if (jsonEl && jsonEl.textContent) {
            customerList = JSON.parse(jsonEl.textContent.trim());
        }
    } catch {
        customerList = [];
    }
    if (!Array.isArray(customerList)) customerList = [];

    let customerPanel = null;
    let customerPanelOpen = false;
    const rewardThreshold = parseFloat(rewardModeLabel?.getAttribute('data-reward-threshold') || '0') || 0;
    const rewardOrderTypeCode = rewardModeLabel?.getAttribute('data-reward-order-type') || '';

    const selectedCustomerRewardBalance = () => {
        const id = customerIdHidden.value || '';
        if (!id) return 0;
        const row = customerList.find((c) => String(c.id) === String(id));
        return parseFloat(row?.rewards_balance || '0') || 0;
    };

    const syncRewardModeAvailability = () => {
        const balance = selectedCustomerRewardBalance();
        const available = rewardThreshold > 0 && rewardOrderTypeCode !== '' && balance + 1e-9 >= rewardThreshold;
        if (rewardModeRadio) {
            rewardModeRadio.disabled = !available;
            if (!available && rewardModeRadio.checked && regularModeRadio) {
                regularModeRadio.checked = true;
            }
        }
        if (rewardModeLabel) {
            rewardModeLabel.classList.toggle('disabled', !available);
        }
        if (rewardAvailabilityText) {
            if (!customerIdHidden.value) {
                rewardAvailabilityText.textContent = 'Select a saved customer to check reward availability.';
            } else if (available) {
                rewardAvailabilityText.textContent = `Reward available (${balance.toFixed(2)} count). Selecting Reward uses the configured reward service.`;
            } else {
                rewardAvailabilityText.textContent = `No reward available yet (${balance.toFixed(2)} / ${rewardThreshold.toFixed(2)}).`;
            }
        }
    };

    const hideCustomerPanel = () => {
        customerPanelOpen = false;
        if (customerPanel) customerPanel.classList.add('d-none');
        customerSearchInput.setAttribute('aria-expanded', 'false');
    };

    const positionCustomerPanel = () => {
        if (!customerPanel || !customerPanelOpen) return;
        const r = customerSearchInput.getBoundingClientRect();
        customerPanel.style.position = 'fixed';
        customerPanel.style.left = `${Math.max(8, r.left)}px`;
        customerPanel.style.top = `${r.bottom + 4}px`;
        customerPanel.style.width = `${Math.max(220, r.width)}px`;
        customerPanel.style.zIndex = '2100';
        customerPanel.style.maxHeight = 'min(280px, 45vh)';
        customerPanel.style.overflowY = 'auto';
    };

    const ensureCustomerPanel = () => {
        if (customerPanel) return;
        customerPanel = document.getElementById('laundryCustomerDropdownPanel');
        if (!customerPanel) {
            customerPanel = document.createElement('div');
            customerPanel.id = 'laundryCustomerDropdownPanel';
            document.body.appendChild(customerPanel);
        }
        customerPanel.className = 'laundry-customer-dd-panel border rounded shadow-sm bg-body d-none';
        customerPanel.setAttribute('role', 'listbox');
    };

    const renderCustomerPanel = () => {
        if (!customerPanel) return;
        const q = (customerSearchInput.value || '').trim().toLowerCase();
        customerPanel.innerHTML = '';
        const addBtn = (id, name) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'laundry-customer-dd-item w-100 text-start border-0 bg-transparent py-2 px-3';
            b.setAttribute('role', 'option');
            b.textContent = name;
            b.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (id === '') {
                    customerIdHidden.value = '';
                    customerSearchInput.value = '';
                    customerSearchInput.placeholder = 'Walk-in customer';
                } else {
                    customerIdHidden.value = id;
                    customerSearchInput.value = name;
                    customerSearchInput.placeholder = 'Walk-in customer';
                }
                syncRewardModeAvailability();
                hideCustomerPanel();
            });
            customerPanel.appendChild(b);
        };
        addBtn('', 'Walk-in customer');
        customerList.forEach((c) => {
            const nm = String(c.name || '').trim();
            if (!nm) return;
            if (!q || nm.toLowerCase().includes(q)) {
                addBtn(String(c.id), nm);
            }
        });
    };

    const showCustomerPanel = () => {
        ensureCustomerPanel();
        customerPanelOpen = true;
        renderCustomerPanel();
        customerPanel.classList.remove('d-none');
        customerSearchInput.setAttribute('aria-expanded', 'true');
        positionCustomerPanel();
    };

    const onDocMouseDown = (e) => {
        if (!customerPanelOpen || !customerPanel) return;
        const t = e.target;
        if (t === customerSearchInput || customerPanel.contains(t)) return;
        hideCustomerPanel();
    };

    const onScrollOrResize = () => positionCustomerPanel();

    customerSearchInput.addEventListener('focus', () => {
        showCustomerPanel();
    });
    customerSearchInput.addEventListener('input', () => {
        if (customerSearchInput.value.trim() === '') {
            customerIdHidden.value = '';
            customerSearchInput.placeholder = 'Walk-in customer';
            syncRewardModeAvailability();
        }
        ensureCustomerPanel();
        if (!customerPanelOpen) showCustomerPanel();
        else {
            renderCustomerPanel();
            positionCustomerPanel();
        }
    });
    customerSearchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideCustomerPanel();
    });
    document.addEventListener('mousedown', onDocMouseDown);
    window.addEventListener('scroll', onScrollOrResize, true);
    window.addEventListener('resize', onScrollOrResize);

    const money = (n) => {
        const x = Number(n);
        if (Number.isNaN(x)) return '₱0.00';
        return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(x);
    };

    const selectedServiceKind = () => {
        const opt = orderTypeField?.selectedOptions?.[0];
        return opt?.getAttribute('data-service-kind') || 'full_service';
    };

    const selectedPricePerLoad = () => {
        const opt = orderTypeField?.selectedOptions?.[0];
        return parseFloat(opt?.getAttribute('data-price') || '0') || 0;
    };

    const selectedSupplyBlock = () => {
        const opt = orderTypeField?.selectedOptions?.[0];
        return opt?.getAttribute('data-supply-block') || 'none';
    };

    const selectedShowAddonSupplies = () => {
        const opt = orderTypeField?.selectedOptions?.[0];
        return (opt?.getAttribute('data-show-addon-supplies') || '0') === '1';
    };

    const usesCoreSupplySelectors = () => {
        const b = selectedSupplyBlock();
        return b === 'full_service' || b === 'wash_supplies' || b === 'rinse_supplies';
    };

    const baseSubtotal = () => {
        if (freeModeRadio?.checked || rewardModeRadio?.checked) return 0;
        const sk = selectedServiceKind();
        const price = selectedPricePerLoad();
        const wq = 1;
        const dq = 1;
        if (sk === 'dry_only') return dq * price;
        if (sk === 'wash_only' || sk === 'rinse_only') return wq * price;
        return Math.max(1, Math.max(wq, dq)) * price;
    };

    const selectedUnitCost = (sel) => {
        const opt = sel?.selectedOptions?.[0];
        if (!opt || !opt.value) return 0;
        return parseFloat(opt.getAttribute('data-unit-cost') || '0') || 0;
    };

    const updateSummary = () => {
        if (!laundrySummaryBase || !laundrySummaryTotal) return;
        const base = baseSubtotal();
        const serviceModeLabel = rewardModeRadio?.checked ? 'Reward' : (freeModeRadio?.checked ? 'Free' : 'Base');
        const blk = selectedSupplyBlock();
        const coreOn = usesCoreSupplySelectors();
        if (laundrySummaryInclusionsTitle) {
            if (blk === 'wash_supplies') {
                laundrySummaryInclusionsTitle.textContent = 'Wash service supplies (stock)';
            } else if (blk === 'rinse_supplies') {
                laundrySummaryInclusionsTitle.textContent = 'Rinse service supplies (stock)';
            } else {
                laundrySummaryInclusionsTitle.textContent = 'Full service inclusions (stock)';
            }
        }
        laundrySummaryBase.innerHTML = '';
        const li1 = document.createElement('li');
        li1.textContent = `${serviceModeLabel} service: ${money(base)}`;
        laundrySummaryBase.appendChild(li1);

        if (laundrySummaryFullServiceBlock && laundrySummaryInclusions) {
            if (coreOn) {
                laundrySummaryFullServiceBlock.classList.remove('d-none');
                laundrySummaryInclusions.innerHTML = '';
                if (blk === 'rinse_supplies') {
                    const fabVal = inclusionFabconSelect?.value || '';
                    if (fabVal) {
                        const fabName = inclusionFabconSelect?.selectedOptions?.[0]?.text?.trim() || '—';
                        const li = document.createElement('li');
                        li.textContent = `Service stock: 1 × fabric conditioner (${fabName})`;
                        laundrySummaryInclusions.appendChild(li);
                    } else {
                        const li = document.createElement('li');
                        li.className = 'text-muted';
                        li.textContent = 'No fabric conditioner selected for stock use (optional).';
                        laundrySummaryInclusions.appendChild(li);
                    }
                } else {
                    const detName = inclusionDetergentSelect?.selectedOptions?.[0]?.text?.trim() || '—';
                    const fabName = inclusionFabconSelect?.selectedOptions?.[0]?.text?.trim() || '—';
                    const i1 = document.createElement('li');
                    i1.textContent = `Service stock: 1 × detergent (${detName || 'Select product'})`;
                    const i2 = document.createElement('li');
                    i2.textContent = `Service stock: 1 × fabric conditioner (${fabName || 'Select product'})`;
                    laundrySummaryInclusions.appendChild(i1);
                    laundrySummaryInclusions.appendChild(i2);
                    const bleachVal = inclusionBleachSelect?.value || '';
                    if (bleachVal) {
                        const blName = inclusionBleachSelect?.selectedOptions?.[0]?.text?.trim() || '—';
                        const i3 = document.createElement('li');
                        i3.textContent = `Service stock: 1 × bleach (${blName})`;
                        laundrySummaryInclusions.appendChild(i3);
                    }
                }
            } else {
                laundrySummaryFullServiceBlock.classList.add('d-none');
                laundrySummaryInclusions.innerHTML = '';
            }
        }

        let addOnSum = 0;
        const addonLines = [];
        const rows = [
            ['Detergent', addonDetergentSelect, detergentQtyInput],
            ['Fabric conditioner', addonFabconSelect, fabconQtyInput],
            ['Bleach', addonBleachSelect, bleachQtyInput],
        ];
        rows.forEach(([label, sel, qtyEl]) => {
            if (freeModeRadio?.checked || rewardModeRadio?.checked) return;
            const q = parseFloat(qtyEl?.value || '0') || 0;
            if (q <= 0) return;
            const uc = selectedUnitCost(sel);
            const line = q * uc;
            addOnSum += line;
            const name = sel?.selectedOptions?.[0]?.text?.trim() || '—';
            addonLines.push(`${label} (${name}): ${q} × ${money(uc)} = ${money(line)}`);
        });

        if (laundrySummaryAddonsBlock && laundrySummaryAddons) {
            if (addonLines.length) {
                laundrySummaryAddonsBlock.classList.remove('d-none');
                laundrySummaryAddons.innerHTML = '';
                addonLines.forEach((line) => {
                    const li = document.createElement('li');
                    li.textContent = line;
                    laundrySummaryAddons.appendChild(li);
                });
            } else {
                laundrySummaryAddonsBlock.classList.add('d-none');
                laundrySummaryAddons.innerHTML = '';
            }
        }

        laundrySummaryTotal.textContent = money(base + addOnSum);
    };

    const toggle = () => {
        const sk = selectedServiceKind();
        const isDropOff = sk === 'full_service';
        const coreOn = usesCoreSupplySelectors();
        const blk = selectedSupplyBlock();
        const showAddon = selectedShowAddonSupplies();
        const showAddonChemicals = showAddon && !freeModeRadio?.checked && !rewardModeRadio?.checked;

        if (coreSuppliesHeading && coreSuppliesHelp) {
            if (blk === 'wash_supplies') {
                coreSuppliesHeading.textContent = 'Wash service supplies (stock)';
                coreSuppliesHelp.textContent = 'Pick detergent, fabric conditioner, and optional bleach used for this wash (1× stock each). Add charged extras in the section below.';
            } else if (blk === 'full_service') {
                coreSuppliesHeading.textContent = 'Full service inclusions (stock)';
                coreSuppliesHelp.textContent = 'Choose inventory for included 1× detergent, 1× fabric conditioner, and optional 1× bleach. Charged add-ons are separate below.';
            } else if (blk === 'rinse_supplies') {
                coreSuppliesHeading.textContent = 'Rinse service supplies (stock)';
                coreSuppliesHelp.textContent = 'Optionally pick a fabric conditioner for 1× stock use during rinse (no detergent or bleach in this block). Charged add-ons are separate below.';
            }
        }

        const isRinseSupplyBlock = blk === 'rinse_supplies';

        fullServiceInclusionsWrap.style.display = coreOn ? '' : 'none';

        const detCol = document.getElementById('inclusionDetergentCol');
        const fabCol = document.getElementById('inclusionFabconCol');
        const blCol = document.getElementById('inclusionBleachCol');
        const fabOptHint = document.getElementById('inclusionFabconOptionalHint');
        const fabPh = document.getElementById('inclusionFabconPlaceholderOpt');
        if (detCol) detCol.style.display = isRinseSupplyBlock ? 'none' : '';
        if (fabCol) fabCol.style.display = '';
        if (blCol) blCol.style.display = isRinseSupplyBlock ? 'none' : '';
        if (inclusionDetergentSelect) {
            inclusionDetergentSelect.required = coreOn && !isRinseSupplyBlock;
        }
        if (inclusionFabconSelect) {
            inclusionFabconSelect.required = coreOn && !isRinseSupplyBlock;
        }
        if (fabOptHint) {
            fabOptHint.classList.toggle('d-none', !isRinseSupplyBlock);
        }
        if (fabPh) {
            fabPh.textContent = isRinseSupplyBlock ? 'None' : 'Select product';
        }
        if (addonSuppliesSection) {
            addonSuppliesSection.style.display = showAddonChemicals ? '' : 'none';
        }
        detergentWrap.style.display = showAddonChemicals ? '' : 'none';
        fabconWrap.style.display = showAddonChemicals ? '' : 'none';
        bleachWrap.style.display = showAddonChemicals ? '' : 'none';

        if (foldServiceWrap && includeFoldService) {
            foldServiceWrap.style.display = isDropOff ? '' : 'none';
            if (!isDropOff) {
                includeFoldService.checked = false;
                includeFoldService.disabled = true;
            } else {
                includeFoldService.disabled = false;
            }
        }
        updateSummary();
    };

    orderTypeField.addEventListener('change', toggle);
    [regularModeRadio, freeModeRadio, rewardModeRadio].forEach((el) => {
        if (!el) return;
        el.addEventListener('change', () => {
            if (rewardModeRadio?.checked && rewardOrderTypeCode && orderTypeField) {
                orderTypeField.value = rewardOrderTypeCode;
            }
            toggle();
        });
    });
    [
        inclusionDetergentSelect, inclusionFabconSelect, inclusionBleachSelect,
        addonDetergentSelect, addonFabconSelect, addonBleachSelect,
        detergentQtyInput, fabconQtyInput, bleachQtyInput,
    ].forEach((el) => {
        if (el) el.addEventListener('input', updateSummary);
        if (el) el.addEventListener('change', updateSummary);
    });
    toggle();
    syncRewardModeAvailability();

    modalEl.addEventListener('shown.bs.modal', () => {
        toggle();
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
        hideCustomerPanel();
        form.reset();
        customerIdHidden.value = '';
        customerSearchInput.value = '';
        customerSearchInput.placeholder = 'Walk-in customer';
        if (regularModeRadio) regularModeRadio.checked = true;
        if (orderTypeField && orderTypeField.options && orderTypeField.options.length) {
            orderTypeField.selectedIndex = 0;
        }
        if (includeFoldService) includeFoldService.checked = false;
        syncRewardModeAvailability();
        toggle();
    });
})();
</script>
<script>
(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const viewSelect = document.getElementById('laundrySalesViewSelect');
    const kanbanWrap = document.getElementById('laundrySalesKanbanWrap');
    const tableWrap = document.getElementById('laundrySalesTableWrap');
    const hintKanban = document.getElementById('laundrySalesHintKanban');
    const hintTable = document.getElementById('laundrySalesHintTable');
    const VIEW_STORAGE_KEY = 'laundrySalesViewMode';

    const applyViewMode = (mode) => {
        const isTable = mode === 'table';
        if (kanbanWrap) kanbanWrap.classList.toggle('d-none', isTable);
        if (tableWrap) tableWrap.classList.toggle('d-none', !isTable);
        if (hintKanban) hintKanban.classList.toggle('d-none', isTable);
        if (hintTable) hintTable.classList.toggle('d-none', !isTable);
        try {
            localStorage.setItem(VIEW_STORAGE_KEY, isTable ? 'table' : 'kanban');
        } catch {
        }
    };

    if (viewSelect) {
        try {
            const saved = localStorage.getItem(VIEW_STORAGE_KEY);
            if (saved === 'table' || saved === 'kanban') {
                viewSelect.value = saved;
            }
        } catch {
        }
        applyViewMode(viewSelect.value === 'table' ? 'table' : 'kanban');
        viewSelect.addEventListener('change', () => {
            applyViewMode(viewSelect.value === 'table' ? 'table' : 'kanban');
        });
    }

    const modalEl = document.getElementById('laundryPayModal');
    const form = document.getElementById('laundryPayForm');
    const dueEl = document.getElementById('laundryPayDueDisplay');
    const discountEl = document.getElementById('laundryPayDiscountPercent');
    const tenderedEl = document.getElementById('laundryPayTendered');
    const changeEl = document.getElementById('laundryPayChangeDisplay');
    const cashRadio = document.getElementById('laundry-pm-cash');

    const errModalEl = document.getElementById('laundryProcessErrorModal');
    const errMsgEl = document.getElementById('laundryProcessErrorMessage');

    const showProcessError = (msg) => {
        const text = String(msg || 'Something went wrong.');
        if (errMsgEl) errMsgEl.textContent = text;
        const Modal = window.bootstrap?.Modal;
        if (Modal && errModalEl) {
            Modal.getOrCreateInstance(errModalEl).show();
        } else {
            window.mpgAlert(text, { title: 'Could not complete action', icon: 'error' });
        }
    };

    const parseJsonBody = async (res) => {
        const rawText = await res.text();
        let data;
        try {
            data = rawText ? JSON.parse(rawText) : {};
        } catch {
            data = {
                success: false,
                message: rawText.trim() ? rawText.slice(0, 500) : 'Invalid server response (not JSON).',
            };
        }
        return { data, rawText };
    };

    const postAdvance = async (cardOrUrl, toStatus, options = {}) => {
        const url = typeof cardOrUrl === 'string'
            ? cardOrUrl
            : cardOrUrl?.getAttribute?.('data-advance-url');
        if (!url) return false;
        const body = new URLSearchParams();
        body.set('_token', csrfToken);
        body.set('to_status', String(toStatus || '').trim());
        const params = options.params || {};
        Object.keys(params).forEach((key) => {
            body.set(key, String(params[key] ?? ''));
        });
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
            credentials: 'same-origin',
        });
        const { data } = await parseJsonBody(res);
        if (!res.ok || data.success !== true) {
            const fallback = !res.ok ? `Request failed (${res.status}).` : 'Could not update status.';
            const m = typeof data.message === 'string' && data.message.trim() ? data.message : fallback;
            if (options.errorTarget) {
                options.errorTarget.textContent = m;
                options.errorTarget.classList.remove('d-none');
            } else {
                showProcessError(m);
            }
            return false;
        }
        if (options.reloadOnSuccess) {
            options.row?.remove?.();
            document.dispatchEvent(new CustomEvent('mpg:ajax-success', {
                detail: { method: 'POST', payload: data, response: res },
            }));
        }
        return true;
    };

    const assignModalEl = document.getElementById('laundryMachineAssignModal');
    const assignForm = document.getElementById('laundryMachineAssignForm');
    const assignWasherWrap = document.getElementById('assignWasherWrap');
    const assignDryerWrap = document.getElementById('assignDryerWrap');
    const assignWasherSelect = document.getElementById('assignWasherSelect');
    const assignDryerSelect = document.getElementById('assignDryerSelect');
    const assignError = document.getElementById('laundryMachineAssignError');
    const openMachineAssignModal = (cardOrRow, options = {}) => new Promise((resolve) => {
        if (!assignModalEl || !assignForm || !assignWasherSelect || !assignDryerSelect) {
            resolve(false);
            return;
        }
        const serviceKind = String(cardOrRow?.getAttribute?.('data-service-kind') || 'full_service');
        const needWasher = ['full_service', 'wash_only', 'rinse_only'].includes(serviceKind);
        const needDryer = ['full_service', 'dry_only'].includes(serviceKind);
        assignWasherWrap?.classList.toggle('d-none', !needWasher);
        assignDryerWrap?.classList.toggle('d-none', !needDryer);
        assignWasherSelect.required = needWasher;
        assignDryerSelect.required = needDryer;
        assignWasherSelect.value = '';
        assignDryerSelect.value = '';
        if (assignError) {
            assignError.textContent = '';
            assignError.classList.add('d-none');
        }
        let settled = false;
        const Modal = window.bootstrap?.Modal;
        const modal = Modal ? Modal.getOrCreateInstance(assignModalEl) : null;
        const cleanup = (result) => {
            if (settled) return;
            settled = true;
            assignForm.removeEventListener('submit', onSubmit);
            assignModalEl.removeEventListener('hidden.bs.modal', onHidden);
            resolve(result);
        };
        const onHidden = () => cleanup(false);
        const onSubmit = async (ev) => {
            ev.preventDefault();
            if (assignError) {
                assignError.textContent = '';
                assignError.classList.add('d-none');
            }
            if (needWasher && !assignWasherSelect.value) {
                if (assignError) {
                    assignError.textContent = 'Please select a washer with available credit, or load credit first.';
                    assignError.classList.remove('d-none');
                }
                return;
            }
            if (needDryer && !assignDryerSelect.value) {
                if (assignError) {
                    assignError.textContent = 'Please select a dryer with available credit, or load credit first.';
                    assignError.classList.remove('d-none');
                }
                return;
            }
            const params = {
                washer_machine_id: needWasher ? assignWasherSelect.value : '',
                dryer_machine_id: needDryer ? assignDryerSelect.value : '',
            };
            const ok = await postAdvance(cardOrRow, 'washing_drying', { ...options, params, errorTarget: assignError });
            if (ok) {
                if (modal) modal.hide();
                cleanup(true);
            }
        };
        assignForm.addEventListener('submit', onSubmit);
        assignModalEl.addEventListener('hidden.bs.modal', onHidden);
        if (modal) {
            modal.show();
        } else {
            cleanup(false);
        }
    });

    const money = (n) => {
        const x = Number(n);
        if (Number.isNaN(x)) return '₱0.00';
        return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(x);
    };

    const payModalReady = modalEl && form && dueEl && discountEl && tenderedEl && changeEl;

    let baseTotal = 0;
    let totalDue = 0;
    let paySourceEl = null;

    const normalizeDiscountPercent = () => {
        if (!discountEl) return 0;
        const raw = parseFloat(discountEl.value || '0');
        if (!Number.isFinite(raw)) return 0;
        return Math.min(100, Math.max(0, raw));
    };

    const recalcPaymentTotals = () => {
        if (!payModalReady) return;
        const discountPercent = normalizeDiscountPercent();
        const discountAmount = baseTotal * (discountPercent / 100);
        totalDue = Math.max(0, baseTotal - discountAmount);
        dueEl.textContent = money(totalDue);
        tenderedEl.min = String(totalDue.toFixed(2));
    };

    const updateChange = () => {
        if (!payModalReady) return;
        const t = parseFloat(tenderedEl.value || '0') || 0;
        const ch = Math.max(0, t - totalDue);
        changeEl.textContent = money(ch);
    };

    const ensureEmptyPlaceholder = (listEl) => {
        if (!listEl) return;
        const hasCard = listEl.querySelector('.laundry-kanban-card');
        let ph = listEl.querySelector('.kanban-empty-placeholder');
        if (!hasCard && !ph) {
            ph = document.createElement('div');
            ph.className = 'text-center text-muted small py-4 kanban-empty-placeholder';
            ph.textContent = 'No transactions';
            listEl.appendChild(ph);
        }
        if (hasCard && ph) {
            ph.remove();
        }
    };

    const isNoPaymentMode = (el) => {
        if (!el) return false;
        return el.getAttribute('data-is-free') === '1' || el.getAttribute('data-is-reward') === '1';
    };

    const submitNoPaymentCompletion = async (sourceEl) => {
        if (!sourceEl) return false;
        const url = sourceEl.getAttribute('data-pay-url') || '';
        if (!url) {
            showProcessError('Payment endpoint is missing.');
            return false;
        }
        const body = new URLSearchParams();
        body.set('_token', csrfToken);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
            credentials: 'same-origin',
        });
        const { data } = await parseJsonBody(res);
        if (!res.ok || data.success !== true) {
            const fallback = !res.ok ? `Request failed (${res.status}).` : 'Could not complete transaction.';
            const m = typeof data.message === 'string' && data.message.trim() ? data.message : fallback;
            showProcessError(m);
            return false;
        }
        sourceEl.remove();
        document.dispatchEvent(new CustomEvent('mpg:ajax-success', {
            detail: { method: 'POST', payload: data, response: res },
        }));
        return true;
    };

    const openPayModalFromCard = (card) => {
        if (!payModalReady || !card) return;
        paySourceEl = card;
        form.action = card.getAttribute('data-pay-url') || '';
        baseTotal = parseFloat(card.getAttribute('data-total') || '0') || 0;
        discountEl.value = '0';
        recalcPaymentTotals();
        tenderedEl.value = totalDue.toFixed(2);
        if (cashRadio) cashRadio.checked = true;
        updateChange();
        const Modal = window.bootstrap?.Modal;
        if (Modal) {
            Modal.getOrCreateInstance(modalEl).show();
        }
    };

    if (payModalReady) {
    discountEl.addEventListener('input', () => {
        recalcPaymentTotals();
        updateChange();
    });
    tenderedEl.addEventListener('input', updateChange);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = form.action;
        if (!url) return;
        const fd = new FormData(form);
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: fd,
            credentials: 'same-origin',
        });
        const { data } = await parseJsonBody(res);
        if (!res.ok || data.success !== true) {
            const fallback = !res.ok ? `Request failed (${res.status}).` : 'Could not save payment.';
            const m = typeof data.message === 'string' && data.message.trim() ? data.message : fallback;
            showProcessError(m);
            return;
        }
        const sourceEl = paySourceEl;
        const Modal = window.bootstrap?.Modal;
        if (Modal && modalEl) {
            Modal.getOrCreateInstance(modalEl).hide();
        }
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'success',
                title: 'Payment recorded',
                text: (typeof data.message === 'string' && data.message.trim()) ? data.message : 'Payment submitted successfully.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#198754',
            });
        }
        sourceEl?.remove?.();
        paySourceEl = null;
        document.dispatchEvent(new CustomEvent('mpg:ajax-success', {
            detail: { method: 'POST', payload: data, response: res },
        }));
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        form.action = '';
        paySourceEl = null;
        discountEl.value = '0';
        tenderedEl.value = '';
        baseTotal = 0;
        totalDue = 0;
        dueEl.textContent = money(0);
        changeEl.textContent = money(0);
        if (cashRadio) cashRadio.checked = true;
    });

    const kanbanIds = ['kanban-pending', 'kanban-washing_drying', 'kanban-open_ticket', 'kanban-paid'];
    if (typeof Sortable !== 'undefined') {
    kanbanIds.forEach((id) => {
        const el = document.getElementById(id);
        if (!el) return;
        new Sortable(el, {
            group: 'laundry-kanban',
            animation: 150,
            ghostClass: 'sortable-ghost',
            draggable: '.laundry-kanban-card--draggable',
            filter: '.kanban-empty-placeholder, .no-drag',
            onMove: (evt) => {
                const drag = evt.dragged;
                if (!drag.classList.contains('laundry-kanban-card--draggable')) return false;
                const fromCol = evt.from.dataset.kanbanColumn;
                const toCol = evt.to.dataset.kanbanColumn;
                if (fromCol === 'paid') return false;
                if (toCol === 'paid' && isNoPaymentMode(drag)) {
                    return true;
                }
                const nextMap = {
                    pending: 'washing_drying',
                    washing_drying: 'open_ticket',
                    open_ticket: 'paid',
                };
                return nextMap[fromCol] === toCol;
            },
            onEnd: async (evt) => {
                const item = evt.item;
                if (!item.classList.contains('laundry-kanban-card')) return;
                const fromCol = evt.from.dataset.kanbanColumn;
                const toCol = evt.to.dataset.kanbanColumn;
                if (fromCol === toCol) return;

                if (toCol === 'paid' && isNoPaymentMode(item)) {
                    evt.to.removeChild(item);
                    const ref = evt.from.children[evt.oldIndex] || null;
                    evt.from.insertBefore(item, ref);
                    ensureEmptyPlaceholder(evt.to);
                    ensureEmptyPlaceholder(evt.from);
                    await submitNoPaymentCompletion(item);
                    return;
                }

                if (fromCol === 'pending' && toCol === 'washing_drying') {
                    const ok = await openMachineAssignModal(item, {});
                    if (!ok) {
                        evt.to.removeChild(item);
                        const ref = evt.from.children[evt.oldIndex] || null;
                        evt.from.insertBefore(item, ref);
                    }
                    ensureEmptyPlaceholder(evt.from);
                    ensureEmptyPlaceholder(evt.to);
                    return;
                }

                if (fromCol === 'washing_drying' && toCol === 'open_ticket') {
                    const ok = await postAdvance(item, 'open_ticket', {});
                    if (!ok) {
                        evt.to.removeChild(item);
                        const ref = evt.from.children[evt.oldIndex] || null;
                        evt.from.insertBefore(item, ref);
                    }
                    ensureEmptyPlaceholder(evt.from);
                    ensureEmptyPlaceholder(evt.to);
                    return;
                }

                if (fromCol === 'open_ticket' && toCol === 'paid') {
                    evt.to.removeChild(item);
                    const ref = evt.from.children[evt.oldIndex] || null;
                    evt.from.insertBefore(item, ref);
                    ensureEmptyPlaceholder(evt.to);
                    ensureEmptyPlaceholder(evt.from);
                    openPayModalFromCard(item);
                    return;
                }

                evt.to.removeChild(item);
                const ref = evt.from.children[evt.oldIndex] || null;
                evt.from.insertBefore(item, ref);
            },
        });
    });
    }
    }

    const detailModalEl = document.getElementById('laundryOrderDetailModal');
    const detailBodyEl = document.getElementById('laundryOrderDetailBody');
    const detailTitleEl = document.getElementById('laundryOrderDetailTitle');

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const paymentMethodLabel = (pm) => {
        const m = String(pm || '').toLowerCase().trim();
        const map = {
            cash: 'Cash',
            gcash: 'GCash',
            paymaya: 'PayMaya',
            online_banking: 'Online banking',
            qr_payment: 'QR payment',
            card: 'Card',
            pending: '—',
        };
        if (map[m]) return map[m];
        if (!m) return '—';
        return m.charAt(0).toUpperCase() + m.slice(1).replace(/_/g, ' ');
    };

    const machinesSummaryFromOrder = (o) => {
        const wL = String(o.washer_machine_label || '').trim();
        const dL = String(o.dryer_machine_label || '').trim();
        const legL = String(o.legacy_machine_label || '').trim();
        const legK = String(o.legacy_machine_kind || '').trim();
        if (wL || dL) {
            const p = [];
            if (wL) p.push(`W: ${wL}`);
            if (dL) p.push(`D: ${dL}`);
            return p.join(' · ');
        }
        if (legL) {
            return `${legK ? legK.charAt(0).toUpperCase() + legK.slice(1) + ': ' : ''}${legL}`;
        }
        return '—';
    };

    const statusLabelFromOrder = (o) => {
        const st = String(o.status || '');
        const ps = String(o.payment_status || '');
        if (st === 'pending') return 'Pending - Waiting';
        if (st === 'washing_drying' || st === 'running') return 'Washing - Drying';
        if (st === 'open_ticket' || (st === 'completed' && ps !== 'paid')) return 'Finishing - To Be Picked Up';
        if (st === 'paid' || (st === 'completed' && ps === 'paid')) return 'Paid - Completed';
        return st || '—';
    };

    const renderOrderDetail = (payload) => {
        const o = payload.order || {};
        const inc = payload.inclusions || {};
        const addons = payload.add_ons || [];
        const referenceNo = String(o.reference_code || '').trim() || '—';
        if (detailTitleEl) detailTitleEl.textContent = `Reference No. ${referenceNo}`;

        const inclusionLine = (key, title) => {
            const row = inc[key];
            if (!row || !row.name) {
                return `<dt class="col-sm-4 text-muted">${escapeHtml(title)}</dt><dd class="col-sm-8">Not recorded</dd>`;
            }
            const cat = row.category ? ` <span class="text-muted">(${escapeHtml(String(row.category))})</span>` : '';
            return `<dt class="col-sm-4 text-muted">${escapeHtml(title)}</dt><dd class="col-sm-8">${escapeHtml(String(row.name))}${cat}</dd>`;
        };

        let addonsHtml = '';
        if (addons.length) {
            addonsHtml = '<h6 class="text-uppercase small text-secondary fw-semibold mt-3">Add-ons (charged)</h6><div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit</th><th class="text-end">Line</th></tr></thead><tbody>';
            addons.forEach((a) => {
                const name = a.item_name || '—';
                const q = a.quantity != null ? String(a.quantity) : '—';
                const u = money(parseFloat(a.unit_price));
                const t = money(parseFloat(a.total_price));
                addonsHtml += `<tr><td>${escapeHtml(name)}</td><td class="text-end font-monospace">${escapeHtml(q)}</td><td class="text-end font-monospace">${u}</td><td class="text-end font-monospace">${t}</td></tr>`;
            });
            addonsHtml += '</tbody></table></div>';
        } else {
            addonsHtml = '<h6 class="text-uppercase small text-secondary fw-semibold mt-3">Add-ons</h6><p class="small text-muted mb-0">None</p>';
        }

        const tendered = o.amount_tendered;
        const chg = o.change_amount;
        const paidBlock = (String(o.payment_status) === 'paid')
            ? `<dt class="col-sm-4 text-muted">Payment</dt><dd class="col-sm-8">${escapeHtml(paymentMethodLabel(o.payment_method))}${tendered != null && tendered !== '' ? ` · Tendered ${money(parseFloat(tendered))}` : ''}${parseFloat(chg) > 0 ? ` · Change ${money(parseFloat(chg))}` : ''}</dd>`
            : '<dt class="col-sm-4 text-muted">Payment</dt><dd class="col-sm-8 text-muted">Unpaid</dd>';

        return `
            <div class="row g-2 small">
              <div class="col-md-6">
                <dl class="row mb-0">
                  <dt class="col-sm-4 text-muted">Date</dt><dd class="col-sm-8">${escapeHtml(String(o.created_at || '—'))}</dd>
                  <dt class="col-sm-4 text-muted">Customer</dt><dd class="col-sm-8">${escapeHtml(String(o.customer_name || 'Walk-in'))}</dd>
                  <dt class="col-sm-4 text-muted">Order type</dt><dd class="col-sm-8">${escapeHtml(String(o.order_type_label || o.order_type || '—'))}</dd>
                  <dt class="col-sm-4 text-muted">Load Status</dt><dd class="col-sm-8">${escapeHtml(statusLabelFromOrder(o))}</dd>
                  <dt class="col-sm-4 text-muted">Machines</dt><dd class="col-sm-8">${escapeHtml(machinesSummaryFromOrder(o))}</dd>
                  <dt class="col-sm-4 text-muted">Wash loads</dt><dd class="col-sm-8 font-monospace">${escapeHtml(String(o.wash_qty ?? '0'))}</dd>
                  <dt class="col-sm-4 text-muted">Dry loads</dt><dd class="col-sm-8 font-monospace">${escapeHtml(String(o.dry_minutes ?? '0'))}</dd>
                  <dt class="col-sm-4 text-muted">Fold service</dt><dd class="col-sm-8">${o.include_fold_service ? 'Yes' : 'No'}</dd>
                </dl>
              </div>
              <div class="col-md-6">
                <dl class="row mb-0">
                  <dt class="col-sm-4 text-muted">Subtotal</dt><dd class="col-sm-8 font-monospace">${money(parseFloat(o.subtotal))}</dd>
                  <dt class="col-sm-4 text-muted">Add-ons total</dt><dd class="col-sm-8 font-monospace">${money(parseFloat(o.add_on_total))}</dd>
                  <dt class="col-sm-4 text-muted">Total</dt><dd class="col-sm-8 fw-semibold font-monospace">${money(parseFloat(o.total_amount))}</dd>
                  ${paidBlock}
                </dl>
              </div>
            </div>
            <h6 class="text-uppercase small text-secondary fw-semibold mt-3">Service inclusions (stock)</h6>
            <p class="small text-muted mb-2">Products recorded for included consumption on this order.</p>
            <dl class="row small mb-0">
              ${inclusionLine('detergent', 'Detergent')}
              ${inclusionLine('fabcon', 'Fabric conditioner')}
              ${inclusionLine('bleach', 'Bleach')}
            </dl>
            ${addonsHtml}
        `;
    };

    const openOrderDetail = async (url) => {
        if (!detailModalEl || !detailBodyEl) return;
        detailBodyEl.innerHTML = '<div class="text-muted small">Loading…</div>';
        if (detailTitleEl) detailTitleEl.textContent = 'Transaction details';
        const Modal = window.bootstrap?.Modal;
        if (Modal) Modal.getOrCreateInstance(detailModalEl).show();
        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const { data } = await parseJsonBody(res);
            if (!res.ok || data.success !== true) {
                const m = typeof data.message === 'string' && data.message.trim() ? data.message : 'Could not load transaction.';
                detailBodyEl.innerHTML = `<p class="text-danger mb-0">${escapeHtml(m)}</p>`;
                return;
            }
            detailBodyEl.innerHTML = renderOrderDetail(data);
        } catch {
            detailBodyEl.innerHTML = '<p class="text-danger mb-0">Network error.</p>';
        }
    };

    const receiptCfg = (window.MPG_RECEIPT_CONFIG && typeof window.MPG_RECEIPT_CONFIG === 'object')
        ? window.MPG_RECEIPT_CONFIG
        : {};
    const cfgText = (key) => String(receiptCfg?.[key] || '').trim();
    const cfgNum = (key, fallback) => {
        const n = Number(receiptCfg?.[key]);
        return Number.isFinite(n) ? n : fallback;
    };
    const lanPrintCopies = Math.max(1, Math.min(10, cfgNum('lan_print_copies', 1)));
    const isBirCompliant = String(receiptCfg?.is_bir_compliant || '0') === '1';
    const parseMoneyValue = (n) => {
        const x = Number(n);
        return Number.isFinite(x) ? x : 0;
    };
    const buildOrderReceiptData = (card, detail) => {
        const order = detail?.order || {};
        const inclusions = detail?.inclusions || {};
        const addOns = Array.isArray(detail?.add_ons) ? detail.add_ons : [];
        const addOnLines = addOns
            .map((row) => {
                const name = String(row?.item_name || '').trim();
                const qtyRaw = Number(row?.quantity);
                const qty = Number.isFinite(qtyRaw) ? qtyRaw : 0;
                if (!name) return '';
                return qty > 0 ? `${name} (${qty})` : name;
            })
            .filter(Boolean);
        const detergent = String(inclusions?.detergent?.name || '').trim();
        const fabcon = String(inclusions?.fabcon?.name || '').trim();
        const bleach = String(inclusions?.bleach?.name || '').trim();
        const modeLabel = String(card?.getAttribute('data-service-mode-label') || '').trim() || 'Regular';
        const inclusionDetQty = detergent ? 1 : 0;
        const inclusionFabQty = fabcon ? 1 : 0;
        const inclusionBleachQty = bleach ? 1 : 0;
        const includeFoldService = !!order?.include_fold_service;
        return {
            referenceCode: String(card?.getAttribute('data-reference-code') || '').trim() || '—',
            customerName: String(order?.customer_name || card?.getAttribute('data-customer-name') || '').trim() || 'Walk-in',
            serviceLabel: String(order?.order_type_label || card?.getAttribute('data-order-type-label') || '').trim() || '-',
            modeLabel,
            savedAt: String(order?.created_at || card?.getAttribute('data-created-at') || '').trim(),
            totalText: money(parseMoneyValue(order?.total_amount ?? card?.getAttribute('data-total'))),
            detergentName: detergent ? `${detergent} (${inclusionDetQty})` : 'None',
            fabconName: fabcon ? `${fabcon} (${inclusionFabQty})` : 'None',
            bleachName: bleach ? `${bleach} (${inclusionBleachQty})` : 'None',
            addonsText: addOnLines.length ? addOnLines.join(', ') : 'None',
            includedFold: includeFoldService ? 'Yes' : 'No',
            paymentMethod: paymentMethodLabel(order?.payment_method || ''),
            amountTenderedText: order?.amount_tendered != null && order?.amount_tendered !== '' ? money(parseMoneyValue(order.amount_tendered)) : '',
            changeText: order?.change_amount != null && order?.change_amount !== '' ? money(parseMoneyValue(order.change_amount)) : '',
        };
    };
    const renderReceiptPreviewHtml = (receiptData, includePaymentBlock = false) => {
        const headerName = cfgText('display_name') || cfgText('store_name') || 'Laundry Shop';
        const style = includePaymentBlock ? cfgText('business_style') : '';
        const phone = includePaymentBlock ? cfgText('phone') : '';
        const address = includePaymentBlock ? cfgText('address') : '';
        const email = includePaymentBlock ? cfgText('email') : '';
        const tin = includePaymentBlock ? cfgText('tax_id') : '';
        const footer = isBirCompliant
            ? cfgText('footer_note')
            : 'This is not an official receipt';
        const paymentRow = includePaymentBlock
            ? `<div><span>Payment:</span><strong>${escapeHtml(receiptData.paymentMethod || '—')}</strong></div>`
                + (receiptData.amountTenderedText ? `<div><span>Tendered:</span><strong>${escapeHtml(receiptData.amountTenderedText)}</strong></div>` : '')
                + (receiptData.changeText ? `<div><span>Change:</span><strong>${escapeHtml(receiptData.changeText)}</strong></div>` : '')
            : '';
        return `
            <div class="text-start font-monospace border rounded p-2 bg-body-tertiary" style="max-width:340px;margin:0 auto;font-size:12px;line-height:1.35;">
                <div class="text-center fw-bold">${escapeHtml(headerName)}</div>
                ${style ? `<div class="text-center">${escapeHtml(style)}</div>` : ''}
                ${tin ? `<div class="text-center">TIN: ${escapeHtml(tin)}</div>` : ''}
                ${phone ? `<div class="text-center">${escapeHtml(phone)}</div>` : ''}
                ${address ? `<div class="text-center">${escapeHtml(address)}</div>` : ''}
                ${email ? `<div class="text-center">${escapeHtml(email)}</div>` : ''}
                <hr class="my-1">
                <div class="text-center">REFERENCE NO.</div>
                <div class="text-center fw-bold fs-5">${escapeHtml(receiptData.referenceCode)}</div>
                <hr class="my-1">
                <div><span>Customer:</span><strong> ${escapeHtml(receiptData.customerName)}</strong></div>
                <div><span>Service:</span><strong> ${escapeHtml(receiptData.serviceLabel)}</strong></div>
                <div><span>Mode:</span><strong> ${escapeHtml(receiptData.modeLabel)}</strong></div>
                <div><span>Total:</span><strong> ${escapeHtml(receiptData.totalText)}</strong></div>
                <div><span>Detergent:</span><strong> ${escapeHtml(receiptData.detergentName)}</strong></div>
                <div><span>Fabcon:</span><strong> ${escapeHtml(receiptData.fabconName)}</strong></div>
                <div><span>Bleach:</span><strong> ${escapeHtml(receiptData.bleachName)}</strong></div>
                <div><span>Add Ons:</span><strong> ${escapeHtml(receiptData.addonsText)}</strong></div>
                <div><span>Included Fold:</span><strong> ${escapeHtml(receiptData.includedFold)}</strong></div>
                ${paymentRow}
                ${receiptData.savedAt ? `<div><span>Date:</span><strong> ${escapeHtml(receiptData.savedAt)}</strong></div>` : ''}
                ${footer ? `<hr class="my-1"><div class="text-center">${escapeHtml(footer)}</div>` : ''}
            </div>
        `;
    };
    const escposReceiptBytesSingle = (payload, includePaymentBlock = false, copyLabel = '') => {
        const encoder = new TextEncoder();
        const chunks = [];
        const push = (...bytes) => chunks.push(Uint8Array.from(bytes));
        const pushText = (text) => chunks.push(encoder.encode(String(text || '')));
        const lineWidth = Math.max(24, Math.min(48, cfgNum('escpos_line_width', 32)));
        const line = `${'-'.repeat(lineWidth)}\n`;
        const extraFeeds = Math.max(1, Math.min(16, cfgNum('escpos_extra_feeds', 4)));
        const cutMode = cfgText('escpos_cut_mode') || 'partial';
        const headerName = cfgText('display_name') || cfgText('store_name') || 'LAUNDRY RECEIPT';
        const style = includePaymentBlock ? cfgText('business_style') : '';
        const phone = includePaymentBlock ? cfgText('phone') : '';
        const address = includePaymentBlock ? cfgText('address') : '';
        const email = includePaymentBlock ? cfgText('email') : '';
        const tin = includePaymentBlock ? cfgText('tax_id') : '';
        const footer = isBirCompliant
            ? cfgText('footer_note')
            : 'This is not an official receipt';

        push(0x1b, 0x40);
        push(0x1b, 0x61, 0x01);
        pushText(`${headerName}\n`);
        if (style) pushText(`${style}\n`);
        if (tin) pushText(`TIN: ${tin}\n`);
        if (phone) pushText(`${phone}\n`);
        if (address) pushText(`${address}\n`);
        if (email) pushText(`${email}\n`);
        pushText(line);
        pushText('REFERENCE NO.\n');
        push(0x1d, 0x21, 0x11);
        pushText(`${payload.referenceCode || '-'}\n`);
        push(0x1d, 0x21, 0x00);
        if (copyLabel) {
            pushText(`${copyLabel}\n`);
        }
        pushText(line);
        const escposMoney = (v, fallback = 'PHP 0.00') => {
            const raw = String(v || '').trim();
            if (!raw) return fallback;
            // Avoid UTF-8 peso symbol on printers using legacy code pages (prevents "Ôé¼" mojibake).
            return raw.replaceAll('₱', 'PHP ').replace(/\s+/g, ' ').trim();
        };
        push(0x1b, 0x61, 0x00);
        pushText(`Customer: ${payload.customerName || 'Walk-in'}\n`);
        pushText(`Service: ${payload.serviceLabel || '-'}\n`);
        pushText(`Mode: ${payload.modeLabel || '-'}\n`);
        pushText(`Total: ${escposMoney(payload.totalText)}\n`);
        pushText(`Detergent: ${payload.detergentName || 'None'}\n`);
        pushText(`Fabcon: ${payload.fabconName || 'None'}\n`);
        pushText(`Bleach: ${payload.bleachName || 'None'}\n`);
        pushText(`Add Ons: ${payload.addonsText || 'None'}\n`);
        pushText(`Included Fold: ${payload.includedFold || 'No'}\n`);
        if (includePaymentBlock) {
            pushText(`Payment: ${payload.paymentMethod || '—'}\n`);
            if (payload.amountTenderedText) pushText(`Tendered: ${escposMoney(payload.amountTenderedText, '')}\n`);
            if (payload.changeText) pushText(`Change: ${escposMoney(payload.changeText, '')}\n`);
        }
        if (payload.savedAt) pushText(`Saved: ${payload.savedAt}\n`);
        if (footer) {
            pushText(line);
            push(0x1b, 0x61, 0x01);
            pushText(`${footer}\n`);
            push(0x1b, 0x61, 0x00);
        }
        pushText('\n'.repeat(extraFeeds));
        if (cutMode === 'full') {
            push(0x1d, 0x56, 0x00);
        } else if (cutMode === 'partial') {
            push(0x1d, 0x56, 0x01);
        }

        const totalLength = chunks.reduce((acc, part) => acc + part.length, 0);
        const merged = new Uint8Array(totalLength);
        let offset = 0;
        chunks.forEach((part) => {
            merged.set(part, offset);
            offset += part.length;
        });
        return merged;
    };
    const escposReceiptBytes = (payload, includePaymentBlock = false) => {
        if (!includePaymentBlock) {
            const customerCopy = escposReceiptBytesSingle(payload, false, "Customer's Copy");
            const shopCopy = escposReceiptBytesSingle(payload, false, 'Shop Copy');
            const merged = new Uint8Array(customerCopy.length + shopCopy.length);
            merged.set(customerCopy, 0);
            merged.set(shopCopy, customerCopy.length);
            return merged;
        }
        const copyCount = lanPrintCopies;
        const single = escposReceiptBytesSingle(payload, true, '');
        if (copyCount <= 1 || single.length === 0) return single;
        const merged = new Uint8Array(single.length * copyCount);
        for (let i = 0; i < copyCount; i += 1) {
            merged.set(single, i * single.length);
        }
        return merged;
    };

    const fetchOrderDetailByCard = async (card) => {
        const detailUrl = card?.getAttribute('data-detail-url') || '';
        if (!detailUrl) throw new Error('Missing transaction detail URL.');
        const res = await fetch(detailUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const { data } = await parseJsonBody(res);
        if (!res.ok || data.success !== true) {
            const m = typeof data.message === 'string' && data.message.trim() ? data.message : 'Could not load transaction details.';
            throw new Error(m);
        }
        return data;
    };
    const printReceiptWithPreview = async (card, includePaymentBlock) => {
        if (!card) return;
        const detail = await fetchOrderDetailByCard(card);
        const payload = buildOrderReceiptData(card, detail);
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: includePaymentBlock ? 'Receipt preview' : 'Reference preview',
                html: renderReceiptPreviewHtml(payload, includePaymentBlock)
                    + (!includePaymentBlock
                        ? '<div class="small text-muted mt-2">This will print 2 copies: Customer\'s Copy and Shop Copy.</div>'
                        : `<div class="small text-muted mt-2">Copies to print: ${lanPrintCopies} (from Receipt Config).</div>`),
                showCancelButton: true,
                confirmButtonText: 'Print via Bluetooth',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#0d6efd',
                width: 420,
            });
            if (!result.isConfirmed) return;
        }
        if (typeof window.mpgWriteEscposBluetooth !== 'function') {
            throw new Error('Bluetooth thermal printing is not available in this browser/device.');
        }
        await window.mpgWriteEscposBluetooth(escposReceiptBytes(payload, includePaymentBlock));
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'success',
                title: includePaymentBlock ? 'Receipt printed' : 'Reference printed',
                text: `Reference ${payload.referenceCode} sent to thermal printer.`,
                confirmButtonColor: '#198754',
            });
        }
    };
    document.addEventListener('click', async (e) => {
        const reprintBtn = e.target.closest('.laundry-kanban-action-reprint');
        if (!reprintBtn) return;
        e.preventDefault();
        const card = reprintBtn.closest('.laundry-kanban-card');
        try {
            await printReceiptWithPreview(card, false);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Bluetooth print failed.';
            if (typeof Swal !== 'undefined') {
                await Swal.fire({ icon: 'warning', title: 'Print Reference failed', text: message, confirmButtonColor: '#dc3545' });
            } else {
                showProcessError(`Print Reference failed: ${message}`);
            }
        }
    });
    document.addEventListener('click', async (e) => {
        const printBtn = e.target.closest('.laundry-kanban-action-print-receipt');
        if (!printBtn) return;
        e.preventDefault();
        const card = printBtn.closest('.laundry-kanban-card');
        try {
            await printReceiptWithPreview(card, true);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Bluetooth print failed.';
            if (typeof Swal !== 'undefined') {
                await Swal.fire({ icon: 'warning', title: 'Receipt print failed', text: message, confirmButtonColor: '#dc3545' });
            } else {
                showProcessError(`Receipt print failed: ${message}`);
            }
        }
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.laundry-kanban-detail-trigger, .laundry-table-detail-trigger');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        const u = btn.getAttribute('data-detail-url');
        if (u) openOrderDetail(u);
    });

    document.addEventListener('click', (e) => {
        const startBtn = e.target.closest('.laundry-table-action-start');
        if (!startBtn) return;
        e.preventDefault();
        const tr = startBtn.closest('tr');
        if (!tr) return;
        openMachineAssignModal(tr, { reloadOnSuccess: true, row: tr });
    });

    document.addEventListener('click', (e) => {
        const openTicketBtn = e.target.closest('.laundry-table-action-open-ticket');
        if (!openTicketBtn) return;
        e.preventDefault();
        const tr = openTicketBtn.closest('tr');
        if (!tr) return;
        postAdvance(tr, 'open_ticket', { reloadOnSuccess: true, row: tr });
    });

    document.addEventListener('click', async (e) => {
        const payBtn = e.target.closest('.laundry-table-action-pay');
        if (!payBtn) return;
        e.preventDefault();
        const tr = payBtn.closest('tr');
        if (!tr) return;
        if (isNoPaymentMode(tr)) {
            await submitNoPaymentCompletion(tr);
            return;
        }
        openPayModalFromCard(tr);
    });

    const voidModalEl = document.getElementById('laundryVoidModal');
    const voidForm = document.getElementById('laundryVoidForm');
    const voidReasonEl = document.getElementById('laundryVoidReason');
    const voidModalApi = (window.bootstrap?.Modal && voidModalEl) ? window.bootstrap.Modal.getOrCreateInstance(voidModalEl) : null;
    let pendingVoidUrl = '';
    let pendingVoidRowEl = null;

    document.addEventListener('click', (e) => {
        const voidBtn = e.target.closest('.laundry-table-action-void, .laundry-kanban-action-void');
        if (!voidBtn) return;
        e.preventDefault();
        pendingVoidRowEl = voidBtn.closest('tr, .laundry-kanban-card');
        pendingVoidUrl = pendingVoidRowEl?.getAttribute('data-void-url') || '';
        if (!pendingVoidUrl) return;
        if (!voidModalApi || !voidReasonEl) {
            showProcessError('Void modal is not available.');
            return;
        }
        voidReasonEl.value = '';
        voidModalApi.show();
        setTimeout(() => voidReasonEl.focus(), 120);
    });

    voidForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!pendingVoidUrl || !voidReasonEl) return;
        const reason = String(voidReasonEl.value || '').trim();
        if (!reason) {
            showProcessError('Void reason is required.');
            voidReasonEl.focus();
            return;
        }
        const body = new URLSearchParams();
        body.set('_token', csrfToken);
        body.set('void_reason', reason);
        const res = await fetch(pendingVoidUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/html,application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
            credentials: 'same-origin',
        });
        const { data } = await parseJsonBody(res);
        if (!res.ok || data.success === false) {
            const m = typeof data.message === 'string' && data.message.trim() ? data.message : `Void failed (${res.status}).`;
            showProcessError(m);
            return;
        }
        voidModalApi?.hide();
        pendingVoidRowEl?.remove?.();
        pendingVoidRowEl = null;
        pendingVoidUrl = '';
        document.dispatchEvent(new CustomEvent('mpg:ajax-success', {
            detail: { method: 'POST', payload: data, response: res },
        }));
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Voided',
                text: (typeof data.message === 'string' && data.message.trim()) ? data.message : 'Transaction marked VOID.',
                confirmButtonColor: '#198754',
            });
        }
    });

    voidModalEl?.addEventListener('hidden.bs.modal', () => {
        pendingVoidUrl = '';
        pendingVoidRowEl = null;
        if (voidReasonEl) {
            voidReasonEl.value = '';
        }
    });

    document.querySelectorAll('.laundry-kanban-card').forEach((card) => {
        card.addEventListener('dblclick', (e) => {
            if (e.target.closest('.no-drag')) return;
            const u = card.getAttribute('data-detail-url');
            if (u) openOrderDetail(u);
        });
    });

    document.querySelectorAll('.laundry-sales-table-row').forEach((row) => {
        row.addEventListener('dblclick', (e) => {
            if (e.target.closest('button')) return;
            const u = row.getAttribute('data-detail-url');
            if (u) openOrderDetail(u);
        });
    });
})();
</script>
