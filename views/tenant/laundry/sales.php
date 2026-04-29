<p class="small text-muted mb-3 laundry-sales-hint" id="laundrySalesHintKanban">
    <strong>Kanban:</strong> drag cards in sequence only: <strong>Pending</strong> → <strong>Washing - Drying</strong> → <strong>Unpaid</strong> → <strong>Paid</strong>.
    Payment modal appears when moving from <strong>Unpaid</strong> to <strong>Paid</strong> for regular transactions; if you cancel, the card stays in Unpaid.
    You can move back to <strong>Pending</strong> only while the load is still in Washing/Drying (before Unpaid/Paid).
    Use <strong>View details</strong> or double-click a card to see inclusions, add-ons, and payment info.
</p>
<p class="small text-muted mb-3 laundry-sales-hint d-none" id="laundrySalesHintTable">
    <strong>Table:</strong> use <strong>Details</strong> for full line items, payments, and totals. This layout is used for quick POS checking and payment updates.
    Status workflow <strong>ON</strong> shows <strong>Pending → Washing - Drying → Unpaid → Paid</strong>. Status workflow <strong>OFF</strong> uses payment-only flow (<strong>Unpaid/Paid</strong>).
</p>
<?php
$currentUser = auth_user();
$isCashier = (($currentUser['role'] ?? '') === 'cashier');
$isTenantAdmin = (($currentUser['role'] ?? '') === 'tenant_admin');
$tenantScopeId = (int) ($currentUser['tenant_id'] ?? 0);
$machineAssignmentEnabled = (bool) ($machine_assignment_enabled ?? true);
$laundryStatusTrackingEnabled = (bool) ($laundry_status_tracking_enabled ?? true);
$trackMachineMovementEnabled = (bool) ($track_machine_movement_enabled ?? false);
$defaultDryingMinutes = $default_drying_minutes ?? null;
$editableOrderDate = (bool) ($editable_order_date ?? false);
$pickupSmsEnabled = (bool) ($pickup_sms_enabled ?? false);
$pickupEmailEnabled = (bool) ($pickup_email_enabled ?? true);
$order_types_list = $order_types ?? [];
$rewardConfig = is_array($reward_config ?? null) ? $reward_config : null;
$rewardThreshold = $rewardConfig !== null ? max(1.0, (float) ($rewardConfig['minimum_points_to_redeem'] ?? $rewardConfig['reward_points_cost'] ?? 10)) : 0.0;
$rewardOrderTypeCode = $rewardConfig !== null ? (string) ($rewardConfig['reward_order_type_code'] ?? '') : '';
$rewardPointsPerDropoffLoad = $rewardConfig !== null ? max(0.0, (float) ($rewardConfig['points_per_dropoff_load'] ?? 1)) : 0.0;
$laundryPaymentMethodLabel = static function (string $pm): string {
    $pm = strtolower(trim($pm));

    return match ($pm) {
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'paymaya' => 'PayMaya',
        'online_banking' => 'Online Banking',
        'qr_payment' => 'QR Payment',
        'card' => 'Card',
        'split_payment' => 'Split Payment (Cash + Online)',
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
$laundryAddonStockOptionLabel = static function (array $item) use ($laundryStockOptionLabel): string {
    return $laundryStockOptionLabel($item).' · Price: '.format_money((float) ($item['unit_cost'] ?? 0));
};

$ordersList = $orders ?? [];
$transactionsScope = (string) ($transactions_scope ?? 'today');
$transactionsMode = (string) ($transactions_mode ?? 'paged');
$transactionsStatusFilter = (string) ($transactions_status_filter ?? 'all');
$transactionsPage = max(1, (int) ($transactions_page ?? 1));
$transactionsPerPage = max(1, (int) ($transactions_per_page ?? 50));
$transactionsTotal = max(0, (int) ($transactions_total ?? count($ordersList)));
$transactionsTotalPages = max(1, (int) ($transactions_total_pages ?? 1));
$buildSalesLink = static function (array $updates = []): string {
    $query = $_GET ?? [];
    foreach ($updates as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = (string) $value;
    }
    $base = route('tenant.laundry-sales.index');
    $qs = http_build_query($query);

    return $qs !== '' ? ($base.'?'.$qs) : $base;
};
$kanbanPending = [];
$kanbanWashingRinsing = [];
$kanbanDrying = [];
$kanbanUnpaid = [];
$kanbanPaid = [];
$voidedToday = [];
foreach ($ordersList as $_o) {
    $st = (string) ($_o['status'] ?? '');
    $ps = (string) ($_o['payment_status'] ?? 'unpaid');
    $orderMode = strtolower(trim((string) ($_o['order_mode'] ?? '')));
    $isVoid = ! empty($_o['is_void']) || $st === 'void';
    if ($isVoid) {
        $voidedToday[] = $_o;
        continue;
    }
    if ($orderMode === 'add_on_only') {
        $st = ($ps === 'paid') ? 'paid' : 'open_ticket';
        $_o['status'] = $st;
    }
    if (! $laundryStatusTrackingEnabled && in_array($st, ['pending', 'washing_drying', 'running'], true)) {
        // Workflow OFF mode only uses Unpaid/Paid for board display.
        $st = ($ps === 'paid') ? 'paid' : 'open_ticket';
        $_o['status'] = $st;
    }
    if ($st === 'pending') {
        $kanbanPending[] = $_o;
    } elseif ($st === 'washing_drying' || $st === 'running') {
        $stage = strtolower(trim((string) ($_o['track_machine_stage'] ?? '')));
        if ($trackMachineMovementEnabled && in_array($stage, ['drying', 'drying_waiting_machine', 'drying_done'], true)) {
            $kanbanDrying[] = $_o;
        } else {
            $kanbanWashingRinsing[] = $_o;
        }
    } elseif ($st === 'open_ticket') {
        $kanbanUnpaid[] = $_o;
    } elseif ($st === 'paid') {
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
$machinesWasherSales = array_values(array_filter($machines ?? [], static function ($m): bool {
    $kind = strtolower(trim((string) ($m['machine_kind'] ?? '')));
    return $kind === 'washer';
}));
$machinesDryerSales = array_values(array_filter($machines ?? [], static function ($m): bool {
    $kind = strtolower(trim((string) ($m['machine_kind'] ?? '')));
    return $kind === 'dryer';
}));
$machineOptionLabel = static function (array $machine): string {
    $label = trim((string) ($machine['machine_label'] ?? ''));
    $credit = ! empty($machine['credit_required']) ? 'Uses overall credits' : 'Manual';
    $status = strtolower(trim((string) ($machine['status'] ?? 'available')));
    if ($status === 'running') {
        $credit .= ' · Running';
    }

    return trim($label.' - '.$credit);
};
$machineOptionDisabled = static function (array $machine): bool {
    // Keep zero-credit machines selectable so users can still choose and receive
    // a specific validation message from backend. Only hard-disable running machines.
    return strtolower(trim((string) ($machine['status'] ?? 'available'))) === 'running';
};
$toDateTimeLocal = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d\TH:i', $ts);
};
?>

<?php if ($isTenantAdmin): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="small fw-semibold text-secondary text-uppercase mb-2">Track Machine Movement</div>
            <form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" class="vstack gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="update_laundry_status_workflow" value="1">
                <input type="hidden" name="laundry_status_tracking_enabled" value="0">
                <input type="hidden" name="track_machine_movement" value="0">
                <div class="form-check mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="laundry_status_tracking_enabled"
                        id="laundryStatusTrackingEnabled"
                        value="1"
                        <?= $laundryStatusTrackingEnabled ? 'checked' : '' ?>
                        onchange="this.form.submit()"
                    >
                    <label class="form-check-label" for="laundryStatusTrackingEnabled">
                        Enable laundry status workflow
                    </label>
                </div>
                <div class="small text-muted lh-base">
                    Use this to turn on stage-based transaction handling instead of payment-only processing.
                    When enabled, each job follows laundry workflow statuses so staff can monitor progress clearly from processing to release.
                    This setting is the main switch that controls whether machine movement options (automatic or manual) are available.
                </div>
                <div class="small text-muted mb-2">
                    <ul class="mb-0 ps-3">
                        <li><strong>Workflow OFF:</strong> Jobs skip stage monitoring and use payment-only handling (Unpaid/Paid), best for simple counter operation without machine-stage tracking.</li>
                        <li><strong>Workflow ON + Manual machine movement:</strong> Staff manually controls machine assignment and job progress per stage, useful when operators decide washer/dryer usage based on actual floor conditions.</li>
                        <li><strong>Workflow ON + Automatic machine movement:</strong> The system drives stage flow (Pending → Washing - Rinsing → Drying → Unpaid → Paid) using configured timing rules, machine availability checks, and overall machine credit rules for consistent low-touch operations.</li>
                    </ul>
                </div>
                <div class="<?= $laundryStatusTrackingEnabled ? '' : 'd-none' ?>">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="form-check mb-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="track_machine_movement"
                                id="trackMachineMovementEnabled"
                                value="1"
                                <?= $trackMachineMovementEnabled ? 'checked' : '' ?>
                                onchange="this.form.submit()"
                            >
                            <label class="form-check-label" for="trackMachineMovementEnabled">
                                Automatic machine movement
                            </label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-muted mb-0" for="defaultDryingMinutes">Default drying minutes</label>
                            <input
                                class="form-control form-control-sm"
                                style="width: 7rem;"
                                type="number"
                                min="1"
                                step="1"
                                name="default_drying_minutes"
                                id="defaultDryingMinutes"
                                value="<?= $defaultDryingMinutes === null || $defaultDryingMinutes === '' ? '' : e((string) $defaultDryingMinutes) ?>"
                                onchange="this.form.submit()"
                                <?= $trackMachineMovementEnabled ? 'required' : '' ?>
                                placeholder="Manual"
                            >
                        </div>
                    </div>
                    <div class="small text-muted mt-2 lh-base">
                        Use this mode when you want Kiosk and Transactions to follow strict automatic control. When staff moves a job from Pending to Washing - Drying,
                        the system first checks for available machines required by the service flow. If no valid washer/dryer is available, the job cannot proceed.
                        For machines marked <strong>Needs credit</strong>, deduction is applied from <strong>Overall machine credits</strong> (global pool) instead of per-machine credit.
                        If overall credits are already zero or not enough for the required usage, the job is blocked and staff must restock overall credits first.
                        If a transaction is returned to Pending, the previously deducted overall credits are restored automatically to keep balances accurate and transparent.
                        Default drying minutes is required so every auto-moved drying stage has a clear expected finish time.
                    </div>
                </div>
            </form>
            <form method="POST" action="<?= e(route('tenant.machines.store')) ?>" class="vstack gap-2 mt-3 <?= $laundryStatusTrackingEnabled ? '' : 'd-none' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="update_machine_assignment" value="1">
                <input type="hidden" name="origin" value="sales">
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
                        Manual machine movement
                    </label>
                </div>
                <div class="small text-muted lh-base">
                    Use this when your team wants to manually choose the washer/dryer per job while still following workflow statuses.
                    Assignment remains controlled by staff, but availability checks still apply, and machines with <strong>Needs credit</strong>
                    still deduct from <strong>Overall machine credits</strong> when used.
                </div>
            </form>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-body">
            <div class="small fw-semibold text-secondary text-uppercase mb-2">Transaction Settings</div>
            <form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" class="vstack gap-2 mb-3">
                <?= csrf_field() ?>
                <input type="hidden" name="update_notification_activation" value="1">
                <input type="hidden" name="pickup_sms_enabled" value="0">
                <input type="hidden" name="pickup_email_enabled" value="0">
                <div class="form-check mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="pickup_sms_enabled"
                        id="pickupSmsEnabledTransactions"
                        value="1"
                        <?= $pickupSmsEnabled ? 'checked' : '' ?>
                        onchange="if(this.checked){document.getElementById('pickupEmailEnabledTransactions').checked=false;} this.form.submit();"
                    >
                    <label class="form-check-label" for="pickupSmsEnabledTransactions">
                        Enable SMS pickup-ready notification
                    </label>
                </div>
                <div class="form-check mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="pickup_email_enabled"
                        id="pickupEmailEnabledTransactions"
                        value="1"
                        <?= $pickupEmailEnabled ? 'checked' : '' ?>
                        onchange="if(this.checked){document.getElementById('pickupSmsEnabledTransactions').checked=false;} this.form.submit();"
                    >
                    <label class="form-check-label" for="pickupEmailEnabledTransactions">
                        Enable Email pickup-ready notification
                    </label>
                </div>
                <div class="small text-muted lh-base">
                    Choose how customers are notified when a laundry job reaches "done and ready for pick up". Select only one
                    activation channel to prevent duplicate alerts. SMS uses daily credits with optional super-admin extensions,
                    while Email uses your configured mail server. Default setup for new stores is Email enabled.
                </div>
            </form>
            <form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" class="d-flex flex-wrap gap-3 align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="update_editable_order_date" value="1">
                <input type="hidden" name="editable_order_date" value="0">
                <div class="form-check mb-0">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="editable_order_date"
                        id="editableOrderDate"
                        value="1"
                        <?= $editableOrderDate ? 'checked' : '' ?>
                        onchange="this.form.submit()"
                    >
                    <label class="form-check-label" for="editableOrderDate">
                        Editable Order Date &amp; Time
                    </label>
                </div>
                <div class="small text-muted">
                    ON: owner can edit transaction date/time directly in card/table; saves automatically after selection.
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="modal fade" id="laundryAddServiceModal" tabindex="-1" aria-labelledby="laundryAddServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" id="laundrySalesForm" class="laundry-sales-form modal-content">
            <?= csrf_field() ?>
            <div class="modal-header border-bottom-0 pb-0">
                <div>
                    <h5 class="modal-title" id="laundryAddServiceModalLabel">New service</h5>
                    <p class="small text-muted mb-0 mt-1">Use one-page POS entry: customer, loads, service, add-ons, payment timing, then save once.</p>
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
                                    <p class="small text-muted mb-1">Customer is required. Select a saved customer or click <strong>Walk-in Customer</strong>.</p>
                                    <input type="hidden" name="customer_id" id="customerIdHidden" value="">
                                    <input type="hidden" name="customer_selection" id="customerSelectionHidden" value="">
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
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="walkInCustomerBtn">Walk-in Customer</button>
                                    </div>
                                    <script type="application/json" id="laundryCustomersJsonData"><?= json_encode($customersForJson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                                    <?php if (($customers ?? []) === []): ?>
                                        <p class="small text-muted mb-0 mt-1">No saved customers yet. Add one in Customer Profile.</p>
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
                                                    data-show-addon-supplies="<?= ((int) ($ot['show_addon_supplies'] ?? 1)) === 1 ? '1' : '0' ?>"
                                                    data-include-in-rewards="<?= ((int) ($ot['include_in_rewards'] ?? 0)) === 1 ? '1' : '0' ?>"
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
                                            data-reward-points-per-load="<?= e((string) $rewardPointsPerDropoffLoad) ?>"
                                        >Reward</label>
                                    </div>
                                    <div class="small text-muted mt-1" id="rewardAvailabilityText">Select a saved customer to check reward availability.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label mb-1 fw-medium" for="numberOfLoadsInput">Number of loads</label>
                                    <input type="number" min="1" max="100" step="1" class="form-control" id="numberOfLoadsInput" name="number_of_loads" value="1" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label mb-1 fw-medium">Payment timing</label>
                                    <div class="btn-group flex-wrap" role="group" aria-label="Payment timing">
                                        <input type="radio" class="btn-check" name="payment_timing" id="paymentTimingNow" value="pay_now">
                                        <label class="btn btn-outline-success" for="paymentTimingNow">Pay Now</label>
                                        <input type="radio" class="btn-check" name="payment_timing" id="paymentTimingLater" value="pay_later" checked>
                                        <label class="btn btn-outline-secondary" for="paymentTimingLater">Pay Later</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mt-3" id="foldServiceWrap">
                                <input class="form-check-input" type="checkbox" name="include_fold_service" id="includeFoldService" value="1">
                                <label class="form-check-label" for="includeFoldService">Include fold service?</label>
                                <p class="small text-muted mb-0 ms-4">When checked, this order counts toward staff folding commission (full service types only).</p>
                            </div>
                            <input type="hidden" name="track_laundry_status" value="<?= $laundryStatusTrackingEnabled ? '1' : '0' ?>">
                            <p class="small text-muted mt-2 mb-0">
                                Status workflow: <strong><?= $laundryStatusTrackingEnabled ? 'Enabled' : 'Disabled' ?></strong> (set in Branch Settings).
                            </p>
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
                            <div class="small text-muted mt-1" id="laundrySummaryPaymentStatus">Payment: Pay Later (Unpaid)</div>
                        </div>
                    </div>

                    <input type="hidden" name="wash_qty" id="washQtyHidden" value="1">
                    <input type="hidden" name="dry_qty" id="dryQtyHidden" value="1">

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
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'addon') { continue; } ?>
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
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'addon') { continue; } ?>
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
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'addon') { continue; } ?>
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
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryAddonStockOptionLabel($item)) ?></option>
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
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryAddonStockOptionLabel($item)) ?></option>
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
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryAddonStockOptionLabel($item)) ?></option>
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
                                <div class="col-12 col-lg-4" id="otherWrap">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body py-3">
                                            <label class="form-label mb-2 small fw-medium" for="addonOtherSelect">Other add-on items</label>
                                            <select class="form-select form-select-sm mb-2" name="addon_other_item_id" id="addonOtherSelect">
                                                <option value="">Select product</option>
                                                <?php foreach (($other_items ?? []) as $item): ?>
                                                    <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                                                    <option value="<?= (int) ($item['id'] ?? 0) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>"><?= e($laundryAddonStockOptionLabel($item)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label class="form-label visually-hidden" for="otherQtyInput">Other qty</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Extra qty</span>
                                                <input type="number" min="0" step="0.01" value="0" class="form-control" name="other_qty" id="otherQtyInput" placeholder="0">
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
    .laundry-kanban-board {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(0, 1fr);
        gap: 0.75rem;
    }
    .laundry-kanban-board.laundry-kanban-board--compact {
        grid-auto-columns: minmax(0, 1fr);
    }
    .laundry-kanban-col {
        min-width: 0;
    }
    .laundry-kanban-card .card-body {
        min-width: 0;
    }
    .laundry-kanban-card-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .laundry-kanban-card-head-ref {
        min-width: 0;
        flex: 1 1 auto;
        overflow-wrap: anywhere;
    }
    .laundry-kanban-card-head-actions {
        min-width: 0;
        max-width: 100%;
        flex: 1 1 180px;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.1rem;
        text-align: end;
    }
    .laundry-kanban-date-input {
        width: 100%;
        min-width: 0;
        max-width: 190px;
    }
    @media (max-width: 1399.98px) {
        .laundry-kanban-card-head-actions {
            flex-basis: 100%;
            align-items: flex-start;
            text-align: start;
        }
        .laundry-kanban-date-input {
            max-width: 100%;
        }
    }
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
    #laundrySalesTableInteractive th,
    #laundrySalesTableInteractive td {
        text-align: center !important;
        vertical-align: middle;
    }
    #laundrySalesTableInteractive th[data-col-key="payment"],
    #laundrySalesTableInteractive td[data-col-key="payment"] {
        text-align: left !important;
    }
    #laundryTableColumnsMenu {
        max-height: min(70vh, 520px);
        overflow-y: auto;
        z-index: 1085;
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
                <?php if ($transactionsScope !== 'all'): ?>
                    <a href="<?= e($buildSalesLink(['tx_scope' => 'all', 'tx_mode' => 'paged', 'page' => 1])) ?>" class="btn btn-outline-primary btn-sm">
                        Show all transactions
                    </a>
                <?php else: ?>
                    <a href="<?= e($buildSalesLink(['tx_scope' => 'today', 'tx_mode' => null, 'page' => null])) ?>" class="btn btn-outline-secondary btn-sm">
                        Back to today view
                    </a>
                    <?php if ($transactionsMode !== 'all'): ?>
                        <a href="<?= e($buildSalesLink(['tx_scope' => 'all', 'tx_mode' => 'all', 'page' => null])) ?>" class="btn btn-outline-primary btn-sm">
                            Show all (no paging)
                        </a>
                    <?php else: ?>
                        <a href="<?= e($buildSalesLink(['tx_scope' => 'all', 'tx_mode' => 'paged', 'page' => 1])) ?>" class="btn btn-outline-secondary btn-sm">
                            Use pagination (25/page)
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="vr mx-1"></div>
                <a href="<?= e($buildSalesLink(['tx_status' => 'all', 'tx_payment' => null, 'page' => 1])) ?>" class="btn btn-sm <?= $transactionsStatusFilter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>">All</a>
                <a href="<?= e($buildSalesLink(['tx_status' => 'pending', 'tx_payment' => null, 'page' => 1])) ?>" class="btn btn-sm <?= $transactionsStatusFilter === 'pending' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Pending</a>
                <a href="<?= e($buildSalesLink(['tx_status' => 'washing_drying', 'tx_payment' => null, 'page' => 1])) ?>" class="btn btn-sm <?= $transactionsStatusFilter === 'washing_drying' ? 'btn-info' : 'btn-outline-info' ?>">Washing - Drying</a>
                <a href="<?= e($buildSalesLink(['tx_status' => 'unpaid', 'tx_payment' => null, 'page' => 1])) ?>" class="btn btn-sm <?= $transactionsStatusFilter === 'unpaid' ? 'btn-warning' : 'btn-outline-warning' ?>">Unpaid</a>
                <a href="<?= e($buildSalesLink(['tx_status' => 'paid', 'tx_payment' => null, 'page' => 1])) ?>" class="btn btn-sm <?= $transactionsStatusFilter === 'paid' ? 'btn-success' : 'btn-outline-success' ?>">Paid</a>
                <a href="<?= e($buildSalesLink(['tx_status' => 'void', 'tx_payment' => null, 'page' => 1])) ?>" class="btn btn-sm <?= $transactionsStatusFilter === 'void' ? 'btn-danger' : 'btn-outline-danger' ?>">Void</a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0 text-nowrap" for="laundrySalesViewSelect">Layout</label>
                <select class="form-select form-select-sm" id="laundrySalesViewSelect" style="width: auto; min-width: 11rem;" aria-label="Sales layout">
                    <option value="kanban">Kanban board</option>
                    <option value="table" selected>Table</option>
                </select>
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" id="laundryTableColumnsBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        Columns
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" id="laundryTableColumnsMenu" style="min-width: 16rem;">
                        <span class="small text-muted">Loading columns...</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="laundrySalesKanbanWrap" class="laundry-sales-view-panel">
        <div class="laundry-kanban-board<?= $laundryStatusTrackingEnabled ? '' : ' laundry-kanban-board--compact' ?>">
            <?php
            $kanbanCols = $laundryStatusTrackingEnabled
                ? [
                    'pending' => [
                        'title' => 'Pending',
                        'badge' => 'bg-secondary',
                        'border' => 'border-secondary border-opacity-50',
                        'hint' => 'Queued loads waiting to be placed on machine.',
                        'list' => $kanbanPending,
                    ],
                    ...($trackMachineMovementEnabled
                        ? [
                            'washing_rinsing' => [
                                'title' => 'Washing - Rinsing',
                                'badge' => 'bg-warning text-dark',
                                'border' => 'border-warning border-opacity-50',
                                'hint' => 'Wash-rinse cycle in progress. It will auto-move to Drying when timer ends.',
                                'list' => $kanbanWashingRinsing,
                            ],
                            'drying' => [
                                'title' => 'Drying',
                                'badge' => 'bg-info text-dark',
                                'border' => 'border-info border-opacity-50',
                                'hint' => 'Drying in progress. Move to Unpaid/Paid when finished.',
                                'list' => $kanbanDrying,
                            ],
                        ]
                        : [
                            'washing_drying' => [
                                'title' => 'Washing - Drying',
                                'badge' => 'bg-warning text-dark',
                                'border' => 'border-warning border-opacity-50',
                                'hint' => 'Cycle in progress. Move to Unpaid (or Paid if already paid) when cycle is finished.',
                                'list' => $kanbanWashingRinsing,
                            ],
                        ]),
                    'open_ticket' => [
                        'title' => 'Unpaid',
                        'badge' => 'bg-info text-dark',
                        'border' => 'border-info border-opacity-50',
                        'hint' => 'Ready for pickup but not paid yet. Drag to Paid to record payment.',
                        'list' => $kanbanUnpaid,
                    ],
                    'paid' => [
                        'title' => 'Paid',
                        'badge' => 'bg-primary',
                        'border' => 'border-primary border-opacity-50',
                        'hint' => 'Payment recorded.',
                        'list' => $kanbanPaid,
                    ],
                ]
                : [
                    'open_ticket' => [
                        'title' => 'Unpaid',
                        'badge' => 'bg-info text-dark',
                        'border' => 'border-info border-opacity-50',
                        'hint' => 'Not paid yet. Move to Paid after collecting payment.',
                        'list' => $kanbanUnpaid,
                    ],
                    'paid' => [
                        'title' => 'Paid',
                        'badge' => 'bg-primary',
                        'border' => 'border-primary border-opacity-50',
                        'hint' => 'Payment recorded.',
                        'list' => $kanbanPaid,
                    ],
                ];
            foreach ($kanbanCols as $colKey => $meta):
                $isDraggableCol = true;
            ?>
            <div class="laundry-kanban-col">
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
                                $groupRefDisplay = trim((string) ($order['group_reference_code'] ?? ''));
                                $otLabel = trim((string) ($order['order_type_label'] ?? ''));
                                $typeDisp = $otLabel !== '' ? $otLabel : ucwords(str_replace('_', ' ', (string) ($order['order_type'] ?? '')));
                                $pmRaw = strtolower(trim((string) ($order['payment_method'] ?? '')));
                                $paymentLabel = $laundryPaymentMethodLabel($pmRaw);
                                $paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
                                $isPaid = $paymentStatus === 'paid';
                                $orderTotalAmount = max(0.0, (float) ($order['total_amount'] ?? 0));
                                $orderModeRaw = strtolower(trim((string) ($order['order_mode'] ?? '')));
                                $rawTendered = $order['amount_tendered'] ?? null;
                                $tenderedForDisplayRaw = ($rawTendered !== null && $rawTendered !== '')
                                    ? (float) $rawTendered
                                    : (($isPaid && $pmRaw !== 'pending') ? $orderTotalAmount : 0.0);
                                $paidForDisplay = min($orderTotalAmount, max(0.0, $tenderedForDisplayRaw));
                                $balanceForDisplay = max(0.0, $orderTotalAmount - $paidForDisplay);
                                $serviceModeLabel = ! empty($order['is_reward'])
                                    ? (((float) ($order['total_amount'] ?? 0)) > 0 ? 'Rewards with Payment' : 'Reward')
                                    : (! empty($order['is_free']) ? 'Free' : 'Regular');
                                $dragClass = $isDraggableCol ? 'laundry-kanban-card--draggable' : '';
                                $createdAtDisplay = trim((string) ($order['created_at'] ?? ''));
                                $createdAtLocal = $toDateTimeLocal($createdAtDisplay);
                                $showMovementTimer = in_array($colKey, ['washing_rinsing', 'drying'], true);
                                $movementEndAtRaw = $colKey === 'washing_rinsing'
                                    ? trim((string) ($order['wash_rinse_end_at'] ?? ''))
                                    : trim((string) ($order['drying_end_at'] ?? ''));
                                $movementExpectedDisplay = '—';
                                if ($showMovementTimer && $movementEndAtRaw !== '') {
                                    $movementEndTs = strtotime($movementEndAtRaw);
                                    if ($movementEndTs !== false) {
                                        $movementExpectedDisplay = date('g:i A', $movementEndTs);
                                    }
                                }
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
                                    data-paid-total="<?= e((string) $paidForDisplay) ?>"
                                    data-balance="<?= e((string) $balanceForDisplay) ?>"
                                    data-service-kind="<?= e((string) ($order['order_type_service_kind'] ?? 'full_service')) ?>"
                                    data-reference-code="<?= e($refDisplay) ?>"
                                    data-group-reference-code="<?= e($groupRefDisplay) ?>"
                                    data-customer-name="<?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?>"
                                    data-order-type-label="<?= e($typeDisp) ?>"
                                    data-service-mode-label="<?= e($serviceModeLabel) ?>"
                                    data-created-at="<?= e($createdAtDisplay) ?>"
                                    data-date-update-url="<?= e(route('tenant.laundry-sales.date.update', ['id' => $oid])) ?>"
                                    data-payment-status="<?= e($paymentStatus) ?>"
                                    data-order-mode="<?= e($orderModeRaw) ?>"
                                    data-track-stage="<?= e((string) ($order['track_machine_stage'] ?? '')) ?>"
                                    data-movement-end-at="<?= e($movementEndAtRaw) ?>"
                                >
                                    <div class="card-body py-2 px-2">
                                        <div class="laundry-kanban-card-head mb-1">
                                            <span class="fw-semibold font-monospace laundry-kanban-card-head-ref"><?= e($refDisplay) ?></span>
                                            <div class="laundry-kanban-card-head-actions">
                                                <button type="button" class="btn btn-link btn-sm p-0 small text-decoration-none no-drag laundry-kanban-detail-trigger" data-detail-url="<?= e(route('tenant.laundry-sales.detail', ['id' => $oid])) ?>">View details</button>
                                                <?php if ($isTenantAdmin && $editableOrderDate): ?>
                                                    <input
                                                        type="datetime-local"
                                                        class="form-control form-control-sm no-drag js-order-date-input laundry-kanban-date-input"
                                                        value="<?= e($createdAtLocal) ?>"
                                                        data-order-id="<?= $oid ?>"
                                                        data-update-url="<?= e(route('tenant.laundry-sales.date.update', ['id' => $oid])) ?>"
                                                    >
                                                <?php else: ?>
                                                    <span class="small text-muted text-nowrap js-order-date-display" data-order-id="<?= $oid ?>"><?= e((string) ($order['created_at'] ?? '')) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($groupRefDisplay !== ''): ?>
                                            <div class="small text-muted mb-1">Group Ref: <span class="font-monospace"><?= e($groupRefDisplay) ?></span></div>
                                        <?php endif; ?>
                                        <div class="small fw-medium text-truncate" title="<?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?>"><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></div>
                                        <div class="small text-muted text-truncate"><?= e($typeDisp) ?></div>
                                        <div class="small text-muted mb-1"><?= e($laundryMachinesSummary($order)) ?></div>
                                        <div class="small mb-1">
                                            <span class="text-muted">Mode:</span>
                                            <span class="fw-semibold"><?= e($serviceModeLabel) ?></span>
                                        </div>
                                        <div class="small mb-1">
                                            <span class="text-muted">Payment:</span>
                                            <span class="badge <?= $isPaid ? 'bg-success' : 'bg-secondary' ?>"><?= $isPaid ? 'Paid' : 'Unpaid' ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center small">
                                            <span class="text-muted">Total</span>
                                            <span class="fw-semibold font-monospace"><?= e(format_money($orderTotalAmount)) ?></span>
                                        </div>
                                        <div class="small text-muted">
                                            Paid <?= e(format_money($paidForDisplay)) ?> · Balance <?= e(format_money($balanceForDisplay)) ?>
                                        </div>
                                        <?php if ($showMovementTimer): ?>
                                            <div class="small mt-1 pt-1 border-top js-movement-timer-block">
                                                <div><span class="text-muted">Expected finish:</span> <span class="fw-semibold js-movement-expected"><?= e($movementExpectedDisplay) ?></span></div>
                                                <div><span class="text-muted">Current timer:</span> <span class="fw-semibold js-movement-timer" data-end-at="<?= e($movementEndAtRaw) ?>">--:--</span></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($colKey === 'pending' || $colKey === 'paid' || $isTenantAdmin): ?>
                                            <div class="mt-1 pt-1 border-top d-flex align-items-center gap-2 flex-wrap">
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
                                                <?php if ($isPaid && $pmRaw !== 'pending' && $paidForDisplay > 0): ?>
                                                    <div class="text-muted">Tendered <?= e(format_money($paidForDisplay)) ?>
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
                        <th>#</th>
                        <th>Reference No.</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Fold</th>
                        <th>Load Status</th>
                        <th>Mode</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Add-ons</th>
                        <th class="text-end">Total</th>
                        <th class="text-center text-nowrap">Status Transition</th>
                        <th class="text-center text-nowrap">Void</th>
                        <th>Payment</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $tableRowNo = ($transactionsScope === 'all' && $transactionsMode === 'paged')
                        ? (($transactionsPage - 1) * $transactionsPerPage)
                        : 0; ?>
                    <?php foreach ($ordersList as $order): ?>
                        <?php
                        $tableRowNo++;
                        $oid = (int) ($order['id'] ?? 0);
                        $refDisplay = trim((string) ($order['reference_code'] ?? ''));
                        if ($refDisplay === '') {
                            $refDisplay = '—';
                        }
                        $paymentStatus = (string) ($order['payment_status'] ?? 'paid');
                        $isPaid = $paymentStatus === 'paid';
                        $orderTotalAmount = max(0.0, (float) ($order['total_amount'] ?? 0));
                        $orderModeRaw = strtolower(trim((string) ($order['order_mode'] ?? '')));
                        $statusRaw = (string) ($order['status'] ?? '');
                        if ($orderModeRaw === 'add_on_only' && $statusRaw !== 'void' && empty($order['is_void'])) {
                            $statusRaw = $isPaid ? 'paid' : 'open_ticket';
                        }
                        if (! $laundryStatusTrackingEnabled && in_array($statusRaw, ['pending', 'washing_drying', 'running'], true)) {
                            // Workflow OFF should behave as Unpaid/Paid only.
                            $statusRaw = $isPaid ? 'paid' : 'open_ticket';
                        }
                        $isVoid = ! empty($order['is_void']) || $statusRaw === 'void';
                        $isPending = $statusRaw === 'pending';
                        $isWashingDrying = ($statusRaw === 'washing_drying' || $statusRaw === 'running');
                        $isOpenTicket = ($statusRaw === 'open_ticket');
                        $isPaidStage = $statusRaw === 'paid';
                        $modeDisplay = ! empty($order['is_reward'])
                            ? (((float) ($order['total_amount'] ?? 0)) > 0 ? 'Rewards with Payment' : 'Reward')
                            : (! empty($order['is_free']) ? 'Free' : 'Regular');
                        $pmRaw = strtolower(trim((string) ($order['payment_method'] ?? '')));
                        $paymentLabel = $laundryPaymentMethodLabel($pmRaw);
                        $rawTendered = $order['amount_tendered'] ?? null;
                        $tenderedForDisplayRaw = ($rawTendered !== null && $rawTendered !== '')
                            ? (float) $rawTendered
                            : (($isPaid && $pmRaw !== 'pending') ? $orderTotalAmount : 0.0);
                        $paidForDisplay = min($orderTotalAmount, max(0.0, $tenderedForDisplayRaw));
                        $balanceForDisplay = max(0.0, $orderTotalAmount - $paidForDisplay);
                        if (! $laundryStatusTrackingEnabled) {
                            $statusExport = $isVoid
                                ? 'VOID'
                                : ($isPaid || $statusRaw === 'paid' ? 'Paid' : 'Unpaid');
                        } else {
                            $statusExport = $isVoid
                                ? 'VOID'
                                : ($isPending
                                    ? 'Pending'
                                    : ($isWashingDrying ? 'Washing - Drying' : ($isOpenTicket ? 'Unpaid' : 'Paid')));
                        }
                        $otLabel = trim((string) ($order['order_type_label'] ?? ''));
                        $typeDisp = $otLabel !== '' ? $otLabel : ucwords(str_replace('_', ' ', (string) ($order['order_type'] ?? '')));
                        $createdAtDisplay = trim((string) ($order['created_at'] ?? ''));
                        $createdAtLocal = $toDateTimeLocal($createdAtDisplay);
                        ?>
                        <tr
                            class="laundry-sales-table-row"
                            data-order-id="<?= $oid ?>"
                            data-status="<?= e($statusRaw) ?>"
                            data-detail-url="<?= e(route('tenant.laundry-sales.detail', ['id' => $oid])) ?>"
                            data-advance-url="<?= e(route('tenant.laundry-sales.advance', ['id' => $oid])) ?>"
                            data-complete-url="<?= e(route('tenant.laundry-sales.complete', ['id' => $oid])) ?>"
                            data-pay-url="<?= e(route('tenant.laundry-sales.pay', ['id' => $oid])) ?>"
                            data-void-url="<?= e(route('tenant.laundry-sales.void', ['id' => $oid])) ?>"
                            data-is-free="<?= ! empty($order['is_free']) ? '1' : '0' ?>"
                            data-is-reward="<?= ! empty($order['is_reward']) ? '1' : '0' ?>"
                            data-total="<?= e((string) (float) ($order['total_amount'] ?? 0)) ?>"
                            data-paid-total="<?= e((string) $paidForDisplay) ?>"
                            data-balance="<?= e((string) $balanceForDisplay) ?>"
                            data-service-kind="<?= e((string) ($order['order_type_service_kind'] ?? 'full_service')) ?>"
                            data-payment-status="<?= e($paymentStatus) ?>"
                            data-order-mode="<?= e($orderModeRaw) ?>"
                            data-date-update-url="<?= e(route('tenant.laundry-sales.date.update', ['id' => $oid])) ?>"
                            style="cursor: pointer;"
                        >
                            <td class="small text-muted"><?= e((string) $tableRowNo) ?></td>
                            <td class="font-monospace"><?= e($refDisplay) ?></td>
                            <td class="text-nowrap small">
                                <?php if ($isTenantAdmin && $editableOrderDate): ?>
                                    <input
                                        type="datetime-local"
                                        class="form-control form-control-sm js-order-date-input"
                                        value="<?= e($createdAtLocal) ?>"
                                        data-order-id="<?= $oid ?>"
                                        data-update-url="<?= e(route('tenant.laundry-sales.date.update', ['id' => $oid])) ?>"
                                        style="min-width: 180px;"
                                    >
                                <?php else: ?>
                                    <span class="js-order-date-display" data-order-id="<?= $oid ?>"><?= e((string) ($order['created_at'] ?? '')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) ($order['customer_name'] ?? 'Walk-in')) ?></td>
                            <td class="small"><?= e($typeDisp) ?></td>
                            <td class="small"><?= ! empty($order['include_fold_service']) ? 'Yes' : '—' ?></td>
                            <td>
                                <?php if ($isVoid): ?>
                                    <span class="badge text-bg-danger">VOID</span>
                                <?php else: ?>
                                    <span class="small"><?= e($statusExport) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= e($modeDisplay) ?></td>
                            <td class="text-end small"><?= e(format_money((float) ($order['subtotal'] ?? 0))) ?></td>
                            <td class="text-end small"><?= e(format_money((float) ($order['add_on_total'] ?? 0))) ?></td>
                            <td class="text-end small fw-semibold"><?= e(format_money($orderTotalAmount)) ?></td>
                            <td class="text-center text-nowrap small">
                                <?php if (! $laundryStatusTrackingEnabled && ! $isVoid && ! $isPaid): ?>
                                    <button type="button" class="btn btn-outline-success btn-sm py-0 laundry-table-action-pay">Paid</button>
                                <?php elseif (! $laundryStatusTrackingEnabled): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($laundryStatusTrackingEnabled && $isPending): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 laundry-table-action-start">Start Washing - Drying</button>
                                <?php elseif ($laundryStatusTrackingEnabled && $isWashingDrying): ?>
                                    <button type="button" class="btn btn-outline-info btn-sm py-0 laundry-table-action-open-ticket"><?= $isPaid ? 'Paid' : 'Unpaid' ?></button>
                                <?php elseif ($laundryStatusTrackingEnabled && $isOpenTicket && ! $isPaid): ?>
                                    <button type="button" class="btn btn-outline-success btn-sm py-0 laundry-table-action-pay">Paid</button>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center text-nowrap small">
                                <?php if ($isTenantAdmin && ! $isVoid): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm py-0 laundry-table-action-void">Void</button>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= e($isPaid && $pmRaw !== 'pending' ? $paymentLabel : 'Unpaid') ?>
                                · Paid <?= e(format_money($paidForDisplay)) ?>
                                · Balance <?= e(format_money($balanceForDisplay)) ?>
                                <?php if ($isPaid && isset($order['change_amount']) && (float) $order['change_amount'] > 0): ?>
                                    · Change <?= e(format_money((float) $order['change_amount'])) ?>
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
            <?php if ($transactionsScope === 'all' && $transactionsMode === 'paged' && $transactionsTotalPages > 1): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                    <div class="small text-muted">
                        Page <?= e((string) $transactionsPage) ?> of <?= e((string) $transactionsTotalPages) ?> ·
                        <?= e((string) $transactionsPerPage) ?> rows per page ·
                        Total <?= e((string) $transactionsTotal) ?> transactions
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="d-flex align-items-center gap-1">
                            <label for="transactionsPageSelect" class="small text-muted mb-0">Page</label>
                            <select
                                id="transactionsPageSelect"
                                class="form-select form-select-sm"
                                style="min-width: 6.5rem;"
                                onchange="if(this.value){ window.location.href = this.value; }"
                            >
                                <?php for ($p = 1; $p <= $transactionsTotalPages; $p++): ?>
                                    <option
                                        value="<?= e($buildSalesLink(['tx_scope' => 'all', 'tx_mode' => 'paged', 'page' => $p])) ?>"
                                        <?= $p === $transactionsPage ? 'selected' : '' ?>
                                    >
                                        <?= e((string) $p) ?> / <?= e((string) $transactionsTotalPages) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Transactions pagination">
                            <a
                                class="btn btn-outline-secondary<?= $transactionsPage <= 1 ? ' disabled' : '' ?>"
                                href="<?= $transactionsPage <= 1 ? '#' : e($buildSalesLink(['tx_scope' => 'all', 'tx_mode' => 'paged', 'page' => $transactionsPage - 1])) ?>"
                            >Previous</a>
                            <a
                                class="btn btn-outline-secondary<?= $transactionsPage >= $transactionsTotalPages ? ' disabled' : '' ?>"
                                href="<?= $transactionsPage >= $transactionsTotalPages ? '#' : e($buildSalesLink(['tx_scope' => 'all', 'tx_mode' => 'paged', 'page' => $transactionsPage + 1])) ?>"
                            >Next</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($transactionsScope === 'all' && $transactionsMode === 'all'): ?>
                <div class="small text-muted mt-2">
                    Showing all transactions in one list (no paging): <?= e((string) $transactionsTotal) ?> rows.
                </div>
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
                    <th>Load Status</th>
                    <th>Mode</th>
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
                    $orderModeRaw = strtolower(trim((string) ($order['order_mode'] ?? '')));
                    if ($orderModeRaw === 'add_on_only' && $statusRaw !== 'void' && empty($order['is_void'])) {
                        $statusRaw = $isPaid ? 'paid' : 'open_ticket';
                    }
                    if (! $laundryStatusTrackingEnabled && in_array($statusRaw, ['pending', 'washing_drying', 'running'], true)) {
                        // Export table should match OFF workflow labels too.
                        $statusRaw = $isPaid ? 'paid' : 'open_ticket';
                    }
                    $isVoid = ! empty($order['is_void']) || $statusRaw === 'void';
                    $isPending = $statusRaw === 'pending';
                    $isWashingDrying = ($statusRaw === 'washing_drying' || $statusRaw === 'running');
                    $isOpenTicket = ($statusRaw === 'open_ticket');
                    $isPaidStage = $statusRaw === 'paid';
                    $modeDisplay = ! empty($order['is_reward'])
                        ? (((float) ($order['total_amount'] ?? 0)) > 0 ? 'Rewards with Payment' : 'Reward')
                        : (! empty($order['is_free']) ? 'Free' : 'Regular');
                    $pmRaw = strtolower(trim((string) ($order['payment_method'] ?? '')));
                    $paymentLabel = $laundryPaymentMethodLabel($pmRaw);
                    $orderTotalAmount = max(0.0, (float) ($order['total_amount'] ?? 0));
                    $rawTendered = $order['amount_tendered'] ?? null;
                    $tenderedForDisplayRaw = ($rawTendered !== null && $rawTendered !== '')
                        ? (float) $rawTendered
                        : (($isPaid && $pmRaw !== 'pending') ? $orderTotalAmount : 0.0);
                    $paidForDisplay = min($orderTotalAmount, max(0.0, $tenderedForDisplayRaw));
                    $balanceForDisplay = max(0.0, $orderTotalAmount - $paidForDisplay);
                    $statusExport = $isVoid
                        ? 'VOID'
                        : ($isPending
                        ? 'Pending'
                        : ($isWashingDrying ? 'Washing - Drying' : ($isOpenTicket ? 'Unpaid' : 'Paid')));
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
                        <td><?= e($statusExport) ?></td>
                        <td><?= e($modeDisplay) ?></td>
                        <td class="text-end"><?= e(format_money((float) ($order['subtotal'] ?? 0))) ?></td>
                        <td class="text-end"><?= e(format_money((float) ($order['add_on_total'] ?? 0))) ?></td>
                        <td class="text-end fw-semibold"><?= e(format_money($orderTotalAmount)) ?></td>
                        <td>
                            <?= e($isPaid && $pmRaw !== 'pending' ? $paymentLabel : 'Unpaid') ?>
                            · Paid <?= e(format_money($paidForDisplay)) ?>
                            · Balance <?= e(format_money($balanceForDisplay)) ?>
                            <?php if ($isPaid && isset($order['change_amount']) && (float) $order['change_amount'] > 0): ?>
                                · Change <?= e(format_money((float) $order['change_amount'])) ?>
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
                    <h5 class="modal-title" id="laundryPayModalLabel">Record payment / deposit</h5>
                    <p class="small text-muted mb-0">Select method, enter amount to pay now (partial or full), and watch the live change text.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
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
                    <div class="col-6 col-md-4">
                        <input type="radio" class="btn-check" name="payment_method" id="laundry-pm-split" value="split_payment">
                        <label class="btn btn-outline-secondary border-2 w-100 py-3 laundry-pay-card" for="laundry-pm-split">
                            <span class="d-block fs-4 mb-1 text-warning"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i></span>
                            <span class="small fw-medium">Split payment</span>
                        </label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium" for="laundryPayDiscountPercentage">Discount Percentage</label>
                    <div class="input-group">
                        <input type="number" class="form-control form-control-lg font-monospace" name="discount_percentage" id="laundryPayDiscountPercentage" min="0" max="100" step="0.01" value="0" placeholder="0.00">
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text" id="laundryPayDiscountInfo">Discount amount (minus): -₱0.00</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium" for="laundryPayTendered">Amount to pay now</label>
                    <input type="number" class="form-control form-control-lg font-monospace" name="amount_tendered" id="laundryPayTendered" min="0" step="0.01" required placeholder="0.00">
                    <div class="form-text">You can enter a partial payment. Remaining balance can be paid later.</div>
                </div>
                <div class="border rounded-3 p-3 mb-3 d-none" id="laundryPaySplitWrap">
                    <div class="fw-semibold small text-uppercase text-muted mb-2">Split breakdown</div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label small mb-1" for="laundryPaySplitCash">Cash amount</label>
                            <input type="number" class="form-control font-monospace" name="split_cash_amount" id="laundryPaySplitCash" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small mb-1" for="laundryPaySplitOnlineAmount">Online amount</label>
                            <input type="number" class="form-control font-monospace" name="split_online_amount" id="laundryPaySplitOnlineAmount" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small mb-1" for="laundryPaySplitOnlineMethod">Online method</label>
                            <select class="form-select" name="split_online_method" id="laundryPaySplitOnlineMethod">
                                <option value="">Select method</option>
                                <option value="gcash">GCash</option>
                                <option value="paymaya">PayMaya</option>
                                <option value="online_banking">Online banking</option>
                                <option value="qr_payment">QR payment</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-text mt-2" id="laundryPaySplitInfo">Split total: ₱0.00</div>
                </div>
                <div class="mb-3 d-none" id="laundryPayReferenceWrap">
                    <label class="form-label fw-medium" for="laundryPayReferenceNo">Reference Number</label>
                    <input type="text" class="form-control form-control-lg" name="payment_reference_no" id="laundryPayReferenceNo" maxlength="120" placeholder="Enter payment reference number">
                </div>
                <div class="rounded-3 border p-3 bg-body-tertiary bg-opacity-25 mb-3">
                    <div class="small text-muted mb-0">Remaining balance to collect</div>
                    <div class="fs-5 fw-semibold font-monospace" id="laundryPayDueDisplay">₱0.00</div>
                </div>
                <div class="rounded-3 border p-3 bg-body-tertiary bg-opacity-25">
                    <div class="small text-muted mb-0">Change (amount paid − service total)</div>
                    <div class="fs-5 fw-semibold font-monospace text-success" id="laundryPayChangeDisplay">₱0.00</div>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm payment / deposit</button>
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
                <div class="mb-3 d-none" id="assignWashRinseMinutesWrap">
                    <label class="form-label mb-1" for="assignWashRinseMinutes">Wash-rinse minutes</label>
                    <input type="number" class="form-control" id="assignWashRinseMinutes" min="1" step="1" placeholder="e.g. 35">
                </div>
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
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-outline-danger d-none" id="laundryDetailVoidBtn">Void</button>
                <button type="button" class="btn btn-primary d-none" id="laundryDetailNextActionBtn">Next action</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
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
    const customerSelectionHidden = document.getElementById('customerSelectionHidden');
    const customerSearchInput = document.getElementById('customerSearchInput');
    const walkInCustomerBtn = document.getElementById('walkInCustomerBtn');
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
    const addonOtherSelect = document.getElementById('addonOtherSelect');
    const detergentQtyInput = document.getElementById('detergentQtyInput');
    const fabconQtyInput = document.getElementById('fabconQtyInput');
    const bleachQtyInput = document.getElementById('bleachQtyInput');
    const otherQtyInput = document.getElementById('otherQtyInput');
    const laundrySummaryBase = document.getElementById('laundrySummaryBase');
    const laundrySummaryFullServiceBlock = document.getElementById('laundrySummaryFullServiceBlock');
    const laundrySummaryInclusions = document.getElementById('laundrySummaryInclusions');
    const laundrySummaryAddonsBlock = document.getElementById('laundrySummaryAddonsBlock');
    const laundrySummaryAddons = document.getElementById('laundrySummaryAddons');
    const laundrySummaryTotal = document.getElementById('laundrySummaryTotal');
    const laundrySummaryPaymentStatus = document.getElementById('laundrySummaryPaymentStatus');
    const laundrySummaryInclusionsTitle = document.getElementById('laundrySummaryInclusionsTitle');
    const coreSuppliesHeading = document.getElementById('coreSuppliesHeading');
    const coreSuppliesHelp = document.getElementById('coreSuppliesHelp');
    const addonSuppliesSection = document.getElementById('addonSuppliesSection');
    const numberOfLoadsInput = document.getElementById('numberOfLoadsInput');
    const washQtyHidden = document.getElementById('washQtyHidden');
    const dryQtyHidden = document.getElementById('dryQtyHidden');
    const paymentTimingNow = document.getElementById('paymentTimingNow');
    const paymentTimingLater = document.getElementById('paymentTimingLater');
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
    const pointsPerDropoffLoad = parseFloat(rewardModeLabel?.getAttribute('data-reward-points-per-load') || '0') || 0;

    const selectedCustomerRewardBalance = () => {
        const id = customerIdHidden.value || '';
        if (!id) return 0;
        const row = customerList.find((c) => String(c.id) === String(id));
        return parseFloat(row?.rewards_balance || '0') || 0;
    };

    const selectedOrderTypeEarnsRewards = () => {
        const opt = orderTypeField?.selectedOptions?.[0];
        if (!opt) return false;
        const inc = (opt.getAttribute('data-include-in-rewards') || '').trim();
        if (inc === '1') return true;
        if (inc === '0') return false;
        return (opt.getAttribute('data-service-kind') || '') === 'full_service';
    };

    const modalLoadsForRewardPreview = () => Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);

    const pendingRewardEarnForPreview = () => {
        if (freeModeRadio?.checked || rewardModeRadio?.checked) return 0;
        if (!selectedOrderTypeEarnsRewards()) return 0;
        const loads = modalLoadsForRewardPreview();
        const mult = pointsPerDropoffLoad > 0 ? pointsPerDropoffLoad : 1;
        return loads * mult;
    };

    const projectedCustomerRewardBalance = () => selectedCustomerRewardBalance() + pendingRewardEarnForPreview();

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
                const projected = projectedCustomerRewardBalance();
                rewardAvailabilityText.textContent = `No reward available yet (${projected.toFixed(2)} / ${rewardThreshold.toFixed(2)}).`;
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
                    if (customerSelectionHidden) customerSelectionHidden.value = 'walk_in';
                    customerSearchInput.value = '';
                    customerSearchInput.placeholder = 'Walk-in customer';
                } else {
                    customerIdHidden.value = id;
                    if (customerSelectionHidden) customerSelectionHidden.value = 'saved';
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
            if (customerSelectionHidden) customerSelectionHidden.value = '';
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
    walkInCustomerBtn?.addEventListener('click', () => {
        customerIdHidden.value = '';
        if (customerSelectionHidden) customerSelectionHidden.value = 'walk_in';
        customerSearchInput.value = 'Walk-in customer';
        customerSearchInput.placeholder = 'Walk-in customer';
        syncRewardModeAvailability();
        hideCustomerPanel();
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
        const raw = (opt?.getAttribute('data-show-addon-supplies') || '').trim();
        if (raw === '1') return true;
        if (raw === '0') return false;
        const kind = selectedServiceKind();
        return kind === 'full_service' || kind === 'wash_only';
    };

    const usesCoreSupplySelectors = () => {
        const b = selectedSupplyBlock();
        return b === 'full_service' || b === 'full_service_2x' || b === 'wash_supplies' || b === 'rinse_supplies';
    };

    const baseSubtotal = () => {
        if (freeModeRadio?.checked || rewardModeRadio?.checked) return 0;
        const sk = selectedServiceKind();
        const price = selectedPricePerLoad();
        const loads = Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);
        const wq = loads;
        const dq = loads;
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
        const loads = Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);
        if (washQtyHidden) washQtyHidden.value = String(loads);
        if (dryQtyHidden) dryQtyHidden.value = String(loads);
        const base = baseSubtotal();
        const serviceModeLabel = rewardModeRadio?.checked ? 'Reward' : (freeModeRadio?.checked ? 'Free' : 'Base');
        const blk = selectedSupplyBlock();
        const coreOn = usesCoreSupplySelectors();
        if (laundrySummaryInclusionsTitle) {
            if (blk === 'wash_supplies') {
                laundrySummaryInclusionsTitle.textContent = 'Wash service supplies (stock)';
            } else if (blk === 'full_service_2x') {
                laundrySummaryInclusionsTitle.textContent = 'Full service inclusions 2x (stock)';
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
                    const baseInclusionQty = blk === 'full_service_2x' ? 2 : 1;
                    const i1 = document.createElement('li');
                    i1.textContent = `Service stock: ${baseInclusionQty} × detergent (${detName || 'Select product'})`;
                    const i2 = document.createElement('li');
                    i2.textContent = `Service stock: ${baseInclusionQty} × fabric conditioner (${fabName || 'Select product'})`;
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
            ['Other', addonOtherSelect, otherQtyInput],
        ];
        rows.forEach(([label, sel, qtyEl]) => {
            if (freeModeRadio?.checked || rewardModeRadio?.checked) return;
            const q = parseFloat(qtyEl?.value || '0') || 0;
            if (q <= 0) return;
            const uc = selectedUnitCost(sel);
            const line = q * uc;
            addOnSum += line;
            const name = sel?.selectedOptions?.[0]?.text?.trim() || '—';
            addonLines.push(`${label} (${name}): ${q} × Price ${money(uc)} = ${money(line)}`);
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
        if (laundrySummaryPaymentStatus) {
            const payNow = !!paymentTimingNow?.checked;
            laundrySummaryPaymentStatus.textContent = payNow
                ? 'Payment: Pay Now (Paid)'
                : 'Payment: Pay Later (Unpaid)';
        }
        syncRewardModeAvailability();
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
            } else if (blk === 'full_service_2x') {
                coreSuppliesHeading.textContent = 'Full service inclusions 2x (stock)';
                coreSuppliesHelp.textContent = 'Choose inventory for included 2× detergent, 2× fabric conditioner, and optional 1× bleach. Charged add-ons are separate below.';
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
        const inclusionMandatory = isDropOff && coreOn && !isRinseSupplyBlock;
        if (inclusionDetergentSelect) {
            inclusionDetergentSelect.required = inclusionMandatory;
        }
        if (inclusionFabconSelect) {
            inclusionFabconSelect.required = inclusionMandatory;
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
        addonDetergentSelect, addonFabconSelect, addonBleachSelect, addonOtherSelect,
        detergentQtyInput, fabconQtyInput, bleachQtyInput, otherQtyInput,
        numberOfLoadsInput, paymentTimingNow, paymentTimingLater,
    ].forEach((el) => {
        if (el) el.addEventListener('input', updateSummary);
        if (el) el.addEventListener('change', updateSummary);
    });
    form.addEventListener('submit', (e) => {
        const sel = (customerSelectionHidden?.value || '').trim();
        if (sel !== 'saved' && sel !== 'walk_in') {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Customer required',
                text: 'Select an existing customer or click Walk-in Customer before saving.',
            });
        }
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
        if (customerSelectionHidden) customerSelectionHidden.value = '';
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
    const laundryStatusTrackingEnabled = <?= $laundryStatusTrackingEnabled ? 'true' : 'false' ?>;
    const machineAssignmentEnabled = <?= $machineAssignmentEnabled ? 'true' : 'false' ?>;
    const trackMachineMovementEnabled = <?= $trackMachineMovementEnabled ? 'true' : 'false' ?>;
    const movementTickUrl = <?= json_encode(route('tenant.laundry-sales.movement-tick'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const jobOrderDoneAudioUrl = <?= json_encode(url('audio/job-order-done-ready.mp3'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const ownerCanEditOrderDate = <?= ($isTenantAdmin && $editableOrderDate) ? 'true' : 'false' ?>;

    const viewSelect = document.getElementById('laundrySalesViewSelect');
    const kanbanWrap = document.getElementById('laundrySalesKanbanWrap');
    const tableWrap = document.getElementById('laundrySalesTableWrap');
    const tableInteractive = document.getElementById('laundrySalesTableInteractive');
    const tableColumnsBtn = document.getElementById('laundryTableColumnsBtn');
    const tableColumnsMenu = document.getElementById('laundryTableColumnsMenu');
    const hintKanban = document.getElementById('laundrySalesHintKanban');
    const hintTable = document.getElementById('laundrySalesHintTable');
    const VIEW_STORAGE_KEY = 'laundrySalesViewMode';
    const TABLE_COLUMNS_STORAGE_KEY = 'laundrySalesTableColumns:<?= $tenantScopeId ?>';
    const tableColumnDefs = [
        { key: 'row_no', label: '#' },
        { key: 'reference_no', label: 'Reference No.' },
        { key: 'date', label: 'Date' },
        { key: 'customer', label: 'Customer' },
        { key: 'type', label: 'Type' },
        { key: 'fold', label: 'Fold' },
        { key: 'load_status', label: 'Load Status' },
        { key: 'mode', label: 'Mode' },
        { key: 'subtotal', label: 'Subtotal' },
        { key: 'addons', label: 'Add-ons' },
        { key: 'total', label: 'Total' },
        { key: 'status_transition', label: 'Status Transition' },
        { key: 'void_action', label: 'Void' },
        { key: 'payment', label: 'Payment' },
    ];
    const LOCKED_LEADING_COLUMN_KEY = 'row_no';
    const defaultColumnOrder = tableColumnDefs.map((d) => d.key);
    const tableHiddenCols = new Set();
    let tableColumnOrder = [...defaultColumnOrder];
    let columnSortable = null;
    if (window.bootstrap?.Dropdown && tableColumnsBtn) {
        window.bootstrap.Dropdown.getOrCreateInstance(tableColumnsBtn, {
            popperConfig: (defaultConfig) => ({
                ...defaultConfig,
                strategy: 'fixed',
            }),
        });
    }
    const normalizeTableColumnOrder = (orderInput) => {
        const allowed = new Set(tableColumnDefs.map((d) => d.key));
        const incoming = Array.isArray(orderInput) ? orderInput : [];
        const normalized = incoming
            .filter((key) => typeof key === 'string' && allowed.has(key))
            .filter((key, idx, arr) => arr.indexOf(key) === idx);
        const missing = tableColumnDefs.map((d) => d.key).filter((key) => !normalized.includes(key));
        const merged = [...normalized, ...missing]
            .filter((key) => key !== LOCKED_LEADING_COLUMN_KEY);
        if (allowed.has(LOCKED_LEADING_COLUMN_KEY)) {
            merged.unshift(LOCKED_LEADING_COLUMN_KEY);
        }
        return merged;
    };

    const annotateTableColumnKeys = () => {
        if (!tableInteractive) return;
        const defaultOrder = tableColumnDefs.map((d) => d.key);
        tableInteractive.querySelectorAll('thead tr, tbody tr').forEach((row) => {
            const cells = Array.from(row.children || []);
            defaultOrder.forEach((key, idx) => {
                const cell = cells[idx];
                if (cell) cell.dataset.colKey = key;
            });
        });
    };

    const saveHiddenCols = () => {
        try {
            localStorage.setItem(TABLE_COLUMNS_STORAGE_KEY, JSON.stringify({
                hidden: Array.from(tableHiddenCols),
                order: tableColumnOrder,
            }));
        } catch {
        }
    };
    const loadHiddenCols = () => {
        try {
            const raw = localStorage.getItem(TABLE_COLUMNS_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : null;
            const hidden = Array.isArray(parsed)
                ? parsed // backward compatibility with old array format
                : (Array.isArray(parsed?.hidden) ? parsed.hidden : []);
            const order = Array.isArray(parsed?.order) ? parsed.order : [];
            tableHiddenCols.clear();
            hidden.forEach((key) => {
                if (typeof key !== 'string') return;
                if (tableColumnDefs.some((d) => d.key === key)) {
                    tableHiddenCols.add(key);
                }
            });
            if (order.length) {
                tableColumnOrder = normalizeTableColumnOrder(order);
            }
        } catch {
        }
    };
    const setColumnVisible = (key, visible) => {
        if (!tableInteractive) return;
        tableInteractive.querySelectorAll('thead tr, tbody tr').forEach((row) => {
            const cell = row.querySelector(`[data-col-key="${key}"]`);
            if (cell) cell.classList.toggle('d-none', !visible);
        });
    };
    const applyTableColumnOrder = () => {
        if (!tableInteractive) return;
        tableColumnOrder = normalizeTableColumnOrder(tableColumnOrder);
        const wanted = tableColumnOrder.filter((key) => tableColumnDefs.some((d) => d.key === key));
        tableInteractive.querySelectorAll('thead tr, tbody tr').forEach((row) => {
            const map = {};
            Array.from(row.children || []).forEach((cell) => {
                const key = cell?.dataset?.colKey;
                if (key) map[key] = cell;
            });
            wanted.forEach((key) => {
                if (map[key]) row.appendChild(map[key]);
            });
        });
    };
    const applyTableColumnVisibility = () => {
        tableColumnDefs.forEach((def) => {
            setColumnVisible(def.key, !tableHiddenCols.has(def.key));
        });
    };
    const resetTableColumnsToDefault = () => {
        tableHiddenCols.clear();
        tableColumnOrder = [...defaultColumnOrder];
        applyTableColumnOrder();
        applyTableColumnVisibility();
        saveHiddenCols();
        renderTableColumnsMenu();
    };
    const renderTableColumnsMenu = () => {
        if (!tableColumnsMenu) return;
        tableColumnsMenu.innerHTML = '';
        if (columnSortable) {
            columnSortable.destroy();
            columnSortable = null;
        }
        const tools = document.createElement('div');
        tools.className = 'd-flex justify-content-between align-items-center px-2 pb-2 mb-2 border-bottom';
        const toolsLabel = document.createElement('span');
        toolsLabel.className = 'small text-muted';
        toolsLabel.textContent = 'Show / hide and reorder columns';
        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'btn btn-link btn-sm text-decoration-none p-0';
        resetBtn.textContent = 'Reset default';
        resetBtn.addEventListener('click', (ev) => {
            ev.preventDefault();
            ev.stopPropagation();
            resetTableColumnsToDefault();
        });
        tools.appendChild(toolsLabel);
        tools.appendChild(resetBtn);
        tableColumnsMenu.appendChild(tools);
        const list = document.createElement('div');
        list.id = 'laundryTableColumnsList';
        tableColumnOrder.forEach((key) => {
            const def = tableColumnDefs.find((d) => d.key === key);
            if (!def) return;
            const row = document.createElement('div');
            row.className = 'dropdown-item d-flex align-items-center justify-content-between gap-2 small px-2 py-1';
            row.dataset.colKey = def.key;
            const left = document.createElement('label');
            left.className = 'd-flex align-items-center gap-2 m-0 flex-grow-1';
            left.style.cursor = 'pointer';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input m-0';
            input.checked = !tableHiddenCols.has(def.key);
            input.addEventListener('change', () => {
                if (input.checked) {
                    tableHiddenCols.delete(def.key);
                } else {
                    const visibleCount = tableColumnDefs.filter((d) => !tableHiddenCols.has(d.key)).length;
                    if (visibleCount <= 1) {
                        input.checked = true;
                        if (typeof window.mpgAlert === 'function') {
                            window.mpgAlert('At least one column must remain visible.', {
                                title: 'Columns',
                                icon: 'info',
                            });
                        }
                        return;
                    }
                    tableHiddenCols.add(def.key);
                }
                applyTableColumnVisibility();
                saveHiddenCols();
            });
            const text = document.createElement('span');
            text.textContent = def.label;
            left.appendChild(input);
            left.appendChild(text);
            row.appendChild(left);

            if (def.key === LOCKED_LEADING_COLUMN_KEY) {
                input.checked = true;
                input.disabled = true;
                const fixedTag = document.createElement('span');
                fixedTag.className = 'badge text-bg-light border text-muted fw-normal';
                fixedTag.textContent = 'Start';
                row.appendChild(fixedTag);
            } else {
                const dragHandle = document.createElement('button');
                dragHandle.type = 'button';
                dragHandle.className = 'btn btn-outline-secondary btn-sm py-0 px-2 laundry-col-drag-handle';
                dragHandle.title = 'Drag to reorder';
                dragHandle.setAttribute('aria-label', `Drag ${def.label}`);
                dragHandle.innerHTML = '<i class="fa-solid fa-grip-vertical"></i>';
                dragHandle.style.cursor = 'grab';
                row.appendChild(dragHandle);
            }

            list.appendChild(row);
        });
        tableColumnsMenu.appendChild(list);
        if (window.Sortable && list.children.length > 1) {
            columnSortable = new window.Sortable(list, {
                animation: 150,
                handle: '.laundry-col-drag-handle',
                ghostClass: 'bg-light',
                onEnd: () => {
                    tableColumnOrder = normalizeTableColumnOrder(Array.from(list.querySelectorAll('[data-col-key]'))
                        .map((el) => String(el.getAttribute('data-col-key') || '').trim())
                        .filter((k) => k !== ''));
                    applyTableColumnOrder();
                    applyTableColumnVisibility();
                    saveHiddenCols();
                },
            });
        }
    };
    annotateTableColumnKeys();
    loadHiddenCols();
    applyTableColumnOrder();
    applyTableColumnVisibility();
    renderTableColumnsMenu();

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
        viewSelect.value = 'table';
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
    const tenderedEl = document.getElementById('laundryPayTendered');
    const changeEl = document.getElementById('laundryPayChangeDisplay');
    const discountPercentageEl = document.getElementById('laundryPayDiscountPercentage');
    const discountInfoEl = document.getElementById('laundryPayDiscountInfo');
    const splitWrapEl = document.getElementById('laundryPaySplitWrap');
    const splitCashEl = document.getElementById('laundryPaySplitCash');
    const splitOnlineAmountEl = document.getElementById('laundryPaySplitOnlineAmount');
    const splitOnlineMethodEl = document.getElementById('laundryPaySplitOnlineMethod');
    const splitInfoEl = document.getElementById('laundryPaySplitInfo');
    const cashRadio = document.getElementById('laundry-pm-cash');
    const splitRadio = document.getElementById('laundry-pm-split');

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

    const refreshOrderDateDisplays = (orderId, displayValue, localValue) => {
        const id = String(orderId || '').trim();
        if (!id) return;
        document.querySelectorAll(`.js-order-date-display[data-order-id="${id}"]`).forEach((el) => {
            el.textContent = String(displayValue || '').trim();
        });
        document.querySelectorAll(`.js-order-date-input[data-order-id="${id}"]`).forEach((el) => {
            if (el instanceof HTMLInputElement) {
                el.value = String(localValue || '').trim();
                el.dataset.savedValue = String(localValue || '').trim();
            }
        });
        document.querySelectorAll(`[data-order-id="${id}"]`).forEach((el) => {
            if (el instanceof HTMLElement) {
                if (displayValue) {
                    el.setAttribute('data-created-at', String(displayValue));
                }
                if (localValue) {
                    el.setAttribute('data-created-at-local', String(localValue));
                }
            }
        });
    };

    const wireOrderDateEditors = () => {
        if (!ownerCanEditOrderDate) return;
        document.querySelectorAll('.js-order-date-input').forEach((input) => {
            if (!(input instanceof HTMLInputElement) || input.dataset.bound === '1') return;
            input.dataset.bound = '1';
            input.dataset.savedValue = input.value;
            input.addEventListener('change', async () => {
                const updateUrl = String(input.dataset.updateUrl || '').trim();
                const orderId = String(input.dataset.orderId || '').trim();
                const selected = String(input.value || '').trim();
                if (!updateUrl || !orderId || !selected) {
                    return;
                }
                const body = new URLSearchParams();
                body.set('_token', csrfToken);
                body.set('order_datetime', selected);
                input.disabled = true;
                const previousValue = String(input.dataset.savedValue || '').trim();
                try {
                    const res = await fetch(updateUrl, {
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
                        const fallback = !res.ok ? `Request failed (${res.status}).` : 'Could not update order date/time.';
                        const msg = (typeof data.message === 'string' && data.message.trim()) ? data.message : fallback;
                        input.value = previousValue;
                        window.mpgAlert(msg, { title: 'Update failed', icon: 'error' });
                        return;
                    }
                    const display = String(data.created_at || '').trim();
                    const local = String(data.order_datetime_local || selected).trim();
                    refreshOrderDateDisplays(orderId, display, local);
                } catch {
                    input.value = previousValue;
                    window.mpgAlert('Network error while saving order date/time.', { title: 'Update failed', icon: 'error' });
                } finally {
                    input.disabled = false;
                }
            });
        });
    };
    wireOrderDateEditors();

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
            if (options.row instanceof HTMLTableRowElement) {
                options.row.setAttribute('data-status', String(toStatus || '').trim());
            } else {
                options.row?.remove?.();
            }
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
    const assignWashRinseMinutesWrap = document.getElementById('assignWashRinseMinutesWrap');
    const assignWasherSelect = document.getElementById('assignWasherSelect');
    const assignDryerSelect = document.getElementById('assignDryerSelect');
    const assignWashRinseMinutes = document.getElementById('assignWashRinseMinutes');
    const assignError = document.getElementById('laundryMachineAssignError');
    const openMachineAssignModal = (cardOrRow, options = {}) => new Promise((resolve) => {
        if (!assignModalEl || !assignForm || !assignWasherSelect || !assignDryerSelect) {
            resolve(false);
            return;
        }
        const serviceKind = String(cardOrRow?.getAttribute?.('data-service-kind') || 'full_service');
        const useTrackMovement = laundryStatusTrackingEnabled && trackMachineMovementEnabled;
        const needWashMinutes = useTrackMovement && ['full_service', 'wash_only', 'rinse_only'].includes(serviceKind);
        const needWasher = !useTrackMovement && ['full_service', 'wash_only', 'rinse_only'].includes(serviceKind);
        const needDryer = !useTrackMovement && ['full_service', 'dry_only'].includes(serviceKind);
        assignWashRinseMinutesWrap?.classList.toggle('d-none', !needWashMinutes);
        if (assignWashRinseMinutes) {
            assignWashRinseMinutes.required = needWashMinutes;
            assignWashRinseMinutes.value = '';
        }
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
                    assignError.textContent = 'Please select a washer. Overall machine credits are used for credit-required machines.';
                    assignError.classList.remove('d-none');
                }
                return;
            }
            if (needDryer && !assignDryerSelect.value) {
                if (assignError) {
                    assignError.textContent = 'Please select a dryer. Overall machine credits are used for credit-required machines.';
                    assignError.classList.remove('d-none');
                }
                return;
            }
            if (needWashMinutes) {
                const mins = Math.max(0, parseInt(assignWashRinseMinutes?.value || '0', 10) || 0);
                if (mins < 1) {
                    if (assignError) {
                        assignError.textContent = 'Enter wash-rinse minutes greater than 0.';
                        assignError.classList.remove('d-none');
                    }
                    return;
                }
            }
            const params = {
                washer_machine_id: needWasher ? assignWasherSelect.value : '',
                dryer_machine_id: needDryer ? assignDryerSelect.value : '',
                wash_rinse_minutes: needWashMinutes ? String(assignWashRinseMinutes?.value || '') : '',
            };
            const ok = await postAdvance(cardOrRow, 'washing_drying', { ...options, params, errorTarget: assignError });
            if (ok) {
                if (trackMachineMovementEnabled && cardOrRow instanceof HTMLElement && needWashMinutes) {
                    const mins = Math.max(0, parseInt(assignWashRinseMinutes?.value || '0', 10) || 0);
                    if (mins > 0) {
                        const endAt = new Date(Date.now() + (mins * 60 * 1000));
                        applyMovementTimerToCard(cardOrRow, endAt, 'washing_rinsing');
                    }
                }
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

    const parseEndAt = (raw) => {
        const s = String(raw || '').trim();
        if (!s) return null;
        const normalized = s.includes('T') ? s : s.replace(' ', 'T');
        const d = new Date(normalized);
        if (!Number.isFinite(d.getTime())) return null;
        return d;
    };
    const formatDuration = (secondsRaw) => {
        const seconds = Math.max(0, Math.floor(secondsRaw));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) {
            return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };
    const formatClockTime = (d) => {
        if (!(d instanceof Date) || !Number.isFinite(d.getTime())) return '—';
        const hh = d.getHours();
        const mm = d.getMinutes();
        const ap = hh >= 12 ? 'PM' : 'AM';
        const hr12 = hh % 12 === 0 ? 12 : (hh % 12);
        return `${hr12}:${String(mm).padStart(2, '0')} ${ap}`;
    };
    const ensureMovementTimerBlock = (card) => {
        if (!(card instanceof HTMLElement)) return null;
        const blocks = Array.from(card.querySelectorAll('.js-movement-timer-block'));
        if (blocks.length > 1) {
            blocks.slice(1).forEach((el) => el.remove());
        }
        let block = blocks[0] || null;
        if (block) return block;
        const body = card.querySelector('.card-body');
        if (!body) return null;
        block = document.createElement('div');
        block.className = 'small mt-1 pt-1 border-top js-movement-timer-block';
        block.innerHTML = '<div><span class="text-muted">Expected finish:</span> <span class="fw-semibold js-movement-expected">—</span></div>'
            + '<div><span class="text-muted">Current timer:</span> <span class="fw-semibold js-movement-timer" data-end-at="">--:--</span></div>';
        const actionRow = body.querySelector('.mt-1.pt-1.border-top.d-flex.align-items-center.gap-2.flex-wrap');
        if (actionRow) {
            body.insertBefore(block, actionRow);
        } else {
            body.appendChild(block);
        }
        return block;
    };
    const applyMovementTimerToCard = (card, endAtDate, stage) => {
        if (!(card instanceof HTMLElement) || !(endAtDate instanceof Date) || !Number.isFinite(endAtDate.getTime())) return;
        const block = ensureMovementTimerBlock(card);
        if (!block) return;
        const expectedEl = block.querySelector('.js-movement-expected');
        const timerEl = block.querySelector('.js-movement-timer');
        const endAtRaw = `${endAtDate.getFullYear()}-${String(endAtDate.getMonth() + 1).padStart(2, '0')}-${String(endAtDate.getDate()).padStart(2, '0')} ${String(endAtDate.getHours()).padStart(2, '0')}:${String(endAtDate.getMinutes()).padStart(2, '0')}:${String(endAtDate.getSeconds()).padStart(2, '0')}`;
        if (expectedEl) expectedEl.textContent = formatClockTime(endAtDate);
        if (timerEl) timerEl.setAttribute('data-end-at', endAtRaw);
        card.setAttribute('data-movement-end-at', endAtRaw);
        card.setAttribute('data-track-stage', stage);
    };
    const clearMovementTimerFromCard = (card, stage) => {
        if (!(card instanceof HTMLElement)) return;
        const block = ensureMovementTimerBlock(card);
        if (!block) return;
        const expectedEl = block.querySelector('.js-movement-expected');
        const timerEl = block.querySelector('.js-movement-timer');
        if (expectedEl) expectedEl.textContent = '—';
        if (timerEl) {
            timerEl.setAttribute('data-end-at', '');
            timerEl.textContent = stage === 'drying_waiting_machine' ? 'Waiting for machine...' : 'Waiting...';
        }
        card.setAttribute('data-movement-end-at', '');
        card.setAttribute('data-track-stage', stage);
    };
    const playCompletionAlarm = () => {
        try {
            const audio = new Audio(jobOrderDoneAudioUrl);
            audio.preload = 'auto';
            audio.play().catch(() => {});
            return () => {
                try {
                    audio.pause();
                    audio.currentTime = 0;
                } catch {}
            };
        } catch {
            return () => {};
        }
    };

    const moveDryingCardToNextStatus = async (card) => {
        const ok = await postAdvance(card, 'open_ticket', {});
        if (!ok) return false;
        const nextPaymentStatus = String(card.getAttribute('data-payment-status') || 'unpaid').trim() === 'paid' ? 'paid' : 'unpaid';
        const nextStatus = nextPaymentStatus === 'paid' ? 'paid' : 'open_ticket';
        card.setAttribute('data-status', nextStatus);
        card.setAttribute('data-track-stage', 'completed');
        const targetListId = nextPaymentStatus === 'paid' ? 'kanban-paid' : 'kanban-open_ticket';
        const targetList = document.getElementById(targetListId);
        if (targetList) {
            targetList.appendChild(card);
            ensureEmptyPlaceholder(targetList);
        }
        const dryingList = document.getElementById('kanban-drying');
        if (dryingList) ensureEmptyPlaceholder(dryingList);
        card.dataset.movementDoneNotified = '1';
        document.dispatchEvent(new CustomEvent('mpg:ajax-success', {
            detail: { method: 'POST', payload: { success: true, auto_transition: true } },
        }));
        return true;
    };

    const notifyDryingCompleted = async (card) => {
        if (!card || card.dataset.movementDoneNotified === '1') return;
        card.dataset.movementDoneNotified = '1';
        const reference = String(card.getAttribute('data-reference-code') || card.getAttribute('data-order-id') || '').trim() || '—';
        const nextStatus = String(card.getAttribute('data-payment-status') || '').toLowerCase() === 'paid' ? 'paid' : 'unpaid';
        const stopAlarm = playCompletionAlarm();
        try {
            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: `Transaction ${reference} is completed`,
                    text: `Moving to ${nextStatus}...`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#198754',
                });
            } else {
                window.alert(`Transaction ${reference} is completed. Moving to ${nextStatus}.`);
            }
        } finally {
            stopAlarm();
        }
        const moved = await moveDryingCardToNextStatus(card);
        if (!moved) {
            card.dataset.movementDoneNotified = '0';
        }
    };

    const refreshMovementTimers = () => {
        const nowMs = Date.now();
        document.querySelectorAll('.js-movement-timer').forEach((el) => {
            const endAt = parseEndAt(el.getAttribute('data-end-at') || '');
            if (!endAt) {
                el.textContent = 'Waiting...';
                return;
            }
            const remainSec = Math.floor((endAt.getTime() - nowMs) / 1000);
            if (remainSec <= 0) {
                el.textContent = '00:00';
                const card = el.closest('.laundry-kanban-card');
                const list = card?.closest?.('[data-kanban-column]');
                const col = String(list?.getAttribute?.('data-kanban-column') || '').trim();
                const stage = String(card?.getAttribute?.('data-track-stage') || '').trim();
                if (trackMachineMovementEnabled && col === 'drying' && stage === 'drying_done') {
                    notifyDryingCompleted(card);
                }
                return;
            }
            el.textContent = formatDuration(remainSec);
        });
    };
    refreshMovementTimers();
    window.setInterval(refreshMovementTimers, 1000);
    const applyMovementStageFromTick = (card, state) => {
        if (!(card instanceof HTMLElement) || !state) return;
        const stage = String(state.track_machine_stage || '').trim();
        const pay = String(state.payment_status || '').trim();
        card.setAttribute('data-track-stage', stage);
        card.setAttribute('data-payment-status', pay === 'paid' ? 'paid' : 'unpaid');
        if (stage === 'washing_rinsing') {
            const list = document.getElementById('kanban-washing_rinsing');
            if (list && card.parentElement !== list) list.appendChild(card);
            const endAt = parseEndAt(state.wash_rinse_end_at || '');
            if (endAt) applyMovementTimerToCard(card, endAt, 'washing_rinsing');
            if (list) ensureEmptyPlaceholder(list);
        } else if (stage === 'drying' || stage === 'drying_waiting_machine' || stage === 'drying_done') {
            const list = document.getElementById('kanban-drying');
            if (list && card.parentElement !== list) list.appendChild(card);
            const endAt = parseEndAt(state.drying_end_at || '');
            if (endAt) applyMovementTimerToCard(card, endAt, stage);
            else clearMovementTimerFromCard(card, stage);
            if (list) ensureEmptyPlaceholder(list);
        }
    };
    const pollMovementRealtime = async () => {
        if (!trackMachineMovementEnabled || !laundryStatusTrackingEnabled || !movementTickUrl) return;
        try {
            const res = await fetch(movementTickUrl, {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const { data } = await parseJsonBody(res);
            if (!res.ok || data.success !== true || !Array.isArray(data.orders)) return;
            const byId = new Map();
            data.orders.forEach((row) => {
                const id = String(row?.id || '').trim();
                if (id !== '') byId.set(id, row);
            });
            document.querySelectorAll('.laundry-kanban-card[data-order-id]').forEach((card) => {
                const id = String(card.getAttribute('data-order-id') || '').trim();
                if (!id) return;
                if (!byId.has(id)) return;
                applyMovementStageFromTick(card, byId.get(id));
            });
            ensureEmptyPlaceholder(document.getElementById('kanban-washing_rinsing'));
            ensureEmptyPlaceholder(document.getElementById('kanban-drying'));
        } catch {}
    };
    pollMovementRealtime();
    window.setInterval(pollMovementRealtime, 3000);

    const payModalReady = modalEl && form && dueEl && tenderedEl && changeEl;

    let baseTotal = 0;
    let totalDue = 0;
    let discountAmount = 0;
    let paySourceEl = null;
    const paymentReferenceWrapEl = document.getElementById('laundryPayReferenceWrap');
    const paymentReferenceNoEl = document.getElementById('laundryPayReferenceNo');
    const splitPaymentOnlineMethods = new Set(['gcash', 'paymaya', 'online_banking', 'qr_payment', 'card']);
    const selectedPaymentMethod = () => {
        const selected = form?.querySelector('input[name="payment_method"]:checked');
        return String(selected?.value || '').trim().toLowerCase();
    };
    const isSplitPaymentSelected = () => selectedPaymentMethod() === 'split_payment';

    const recalcPaymentTotals = () => {
        if (!payModalReady) return;
        const rawDiscount = parseFloat(discountPercentageEl?.value || '0');
        const discountPercentage = Math.max(0, Math.min(100, Number.isFinite(rawDiscount) ? rawDiscount : 0));
        if (discountPercentageEl) {
            const normalized = Number(discountPercentageEl.value || 0);
            if (!Number.isFinite(normalized) || normalized !== discountPercentage) {
                discountPercentageEl.value = discountPercentage.toFixed(2);
            }
        }
        discountAmount = Math.max(0, baseTotal * (discountPercentage / 100));
        totalDue = Math.max(0, baseTotal - discountAmount);
        if (discountInfoEl) {
            discountInfoEl.textContent = `Discount amount (minus): -${money(discountAmount)}`;
        }
        dueEl.textContent = money(totalDue);
        const splitMode = isSplitPaymentSelected();
        const method = selectedPaymentMethod();
        const needsReference = method !== '' && method !== 'cash';
        if (splitWrapEl) splitWrapEl.classList.toggle('d-none', !splitMode);
        if (paymentReferenceWrapEl) paymentReferenceWrapEl.classList.toggle('d-none', !needsReference);
        if (paymentReferenceNoEl) {
            if (!needsReference) {
                paymentReferenceNoEl.value = '';
            }
        }
        if (splitMode) {
            const splitCash = Math.max(0, parseFloat(splitCashEl?.value || '0') || 0);
            const splitOnline = Math.max(0, parseFloat(splitOnlineAmountEl?.value || '0') || 0);
            const splitTotal = splitCash + splitOnline;
            tenderedEl.value = splitTotal.toFixed(2);
            tenderedEl.readOnly = true;
            tenderedEl.min = '0';
            if (splitInfoEl) {
                const diff = Math.abs(splitTotal - totalDue);
                splitInfoEl.textContent = diff <= 0.01
                    ? `Split total: ${money(splitTotal)} (matches due)`
                    : `Split total: ${money(splitTotal)} (remaining ${money(Math.max(0, totalDue - splitTotal))})`;
            }
            if (splitOnlineMethodEl) {
                splitOnlineMethodEl.required = splitOnline > 0.000001;
                const currentMethod = String(splitOnlineMethodEl.value || '').trim().toLowerCase();
                if (splitOnline <= 0.000001) {
                    splitOnlineMethodEl.value = '';
                } else if (!splitPaymentOnlineMethods.has(currentMethod)) {
                    splitOnlineMethodEl.value = 'gcash';
                }
            }
        } else {
            tenderedEl.readOnly = false;
            tenderedEl.min = '0';
            if (splitCashEl) splitCashEl.value = '0';
            if (splitOnlineAmountEl) splitOnlineAmountEl.value = '0';
            if (splitOnlineMethodEl) {
                splitOnlineMethodEl.value = '';
                splitOnlineMethodEl.required = false;
            }
            if (splitInfoEl) splitInfoEl.textContent = `Split total: ${money(0)}`;
        }
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
        const cardBalance = Math.max(0, parseFloat(card.getAttribute('data-balance') || '0') || 0);
        const cardTotal = Math.max(0, parseFloat(card.getAttribute('data-total') || '0') || 0);
        baseTotal = cardBalance > 0 ? cardBalance : cardTotal;
        if (discountPercentageEl) discountPercentageEl.value = '0';
        if (splitCashEl) splitCashEl.value = '0';
        if (splitOnlineAmountEl) splitOnlineAmountEl.value = '0';
        if (splitOnlineMethodEl) splitOnlineMethodEl.value = '';
        recalcPaymentTotals();
        tenderedEl.value = totalDue.toFixed(2);
        if (cashRadio) cashRadio.checked = true;
        recalcPaymentTotals();
        updateChange();
        const Modal = window.bootstrap?.Modal;
        if (Modal) {
            Modal.getOrCreateInstance(modalEl).show();
        }
    };

    if (payModalReady) {
    tenderedEl.addEventListener('input', updateChange);
    discountPercentageEl?.addEventListener('input', () => {
        recalcPaymentTotals();
        updateChange();
    });
    splitCashEl?.addEventListener('input', () => {
        recalcPaymentTotals();
        updateChange();
    });
    splitOnlineAmountEl?.addEventListener('input', () => {
        recalcPaymentTotals();
        updateChange();
    });
    form.querySelectorAll('input[name="payment_method"]').forEach((el) => {
        el.addEventListener('change', () => {
            recalcPaymentTotals();
            if (!isSplitPaymentSelected() && (!tenderedEl.value || parseFloat(tenderedEl.value || '0') < totalDue)) {
                tenderedEl.value = totalDue.toFixed(2);
            }
            updateChange();
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = form.action;
        if (!url) return;
        recalcPaymentTotals();
        const splitMode = isSplitPaymentSelected();
        if (splitMode) {
            const splitCash = Math.max(0, parseFloat(splitCashEl?.value || '0') || 0);
            const splitOnline = Math.max(0, parseFloat(splitOnlineAmountEl?.value || '0') || 0);
            const splitTotal = splitCash + splitOnline;
            if (splitTotal <= 0) {
                showProcessError('Enter split payment amounts greater than 0.');
                return;
            }
            if (splitCash <= 0.000001 && (splitTotal - totalDue) > 0.01) {
                showProcessError(`Split payment cannot exceed due (${money(totalDue)}) unless there is a cash part for change.`);
                return;
            }
        }
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
        const nextPaymentStatus = String(data.payment_status || 'paid').trim() || 'paid';
        const nextStatus = String(data.status || (nextPaymentStatus === 'paid' ? 'paid' : 'open_ticket')).trim();
        if (sourceEl instanceof HTMLTableRowElement) {
            sourceEl.setAttribute('data-payment-status', nextPaymentStatus);
            sourceEl.setAttribute('data-status', nextStatus);
            const paymentCell = sourceEl.children[10];
            if (paymentCell instanceof HTMLElement) {
                paymentCell.textContent = nextPaymentStatus === 'paid' ? 'Paid' : 'Unpaid';
            }
            updateTableRowStatusUI(sourceEl);
        } else {
            if (sourceEl instanceof HTMLElement) {
                sourceEl.setAttribute('data-payment-status', nextPaymentStatus);
                sourceEl.setAttribute('data-status', nextStatus);
                const targetListId = nextPaymentStatus === 'paid' ? 'kanban-paid' : 'kanban-open_ticket';
                const targetList = document.getElementById(targetListId);
                if (targetList) {
                    targetList.appendChild(sourceEl);
                    ensureEmptyPlaceholder(targetList);
                }
                const paidList = document.getElementById('kanban-paid');
                const openTicketList = document.getElementById('kanban-open_ticket');
                if (paidList) ensureEmptyPlaceholder(paidList);
                if (openTicketList) ensureEmptyPlaceholder(openTicketList);
                const paymentBadge = sourceEl.querySelector('.small.mb-1 .badge');
                if (paymentBadge instanceof HTMLElement) {
                    paymentBadge.className = nextPaymentStatus === 'paid' ? 'badge bg-success' : 'badge bg-warning text-dark';
                    paymentBadge.textContent = nextPaymentStatus === 'paid' ? 'Paid' : 'Unpaid';
                }
            } else {
                sourceEl?.remove?.();
            }
        }
        paySourceEl = null;
        document.dispatchEvent(new CustomEvent('mpg:ajax-success', {
            detail: { method: 'POST', payload: data, response: res },
        }));
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        form.action = '';
        paySourceEl = null;
        tenderedEl.value = '';
        if (discountPercentageEl) discountPercentageEl.value = '0';
        if (splitCashEl) splitCashEl.value = '0';
        if (splitOnlineAmountEl) splitOnlineAmountEl.value = '0';
        if (splitOnlineMethodEl) {
            splitOnlineMethodEl.value = '';
            splitOnlineMethodEl.required = false;
        }
        baseTotal = 0;
        totalDue = 0;
        discountAmount = 0;
        dueEl.textContent = money(0);
        changeEl.textContent = money(0);
        if (discountInfoEl) discountInfoEl.textContent = `Discount amount: ${money(0)}`;
        if (splitInfoEl) splitInfoEl.textContent = `Split total: ${money(0)}`;
        if (splitWrapEl) splitWrapEl.classList.add('d-none');
        tenderedEl.readOnly = false;
        if (cashRadio) cashRadio.checked = true;
    });

    const kanbanIds = trackMachineMovementEnabled
        ? ['kanban-pending', 'kanban-washing_rinsing', 'kanban-drying', 'kanban-open_ticket', 'kanban-paid']
        : ['kanban-pending', 'kanban-washing_drying', 'kanban-open_ticket', 'kanban-paid'];
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
                if (!laundryStatusTrackingEnabled) {
                    if (fromCol === 'open_ticket' && toCol === 'paid') return true;
                    return false;
                }
                if (trackMachineMovementEnabled) {
                    if (fromCol === 'pending') return toCol === 'washing_rinsing';
                    if (fromCol === 'washing_rinsing') return toCol === 'pending';
                    if (fromCol === 'drying') {
                        if (toCol === 'pending') return true;
                        const ps = String(drag.getAttribute('data-payment-status') || '').toLowerCase();
                        const expected = ps === 'paid' ? 'paid' : 'open_ticket';
                        return expected === toCol;
                    }
                    if (fromCol === 'open_ticket') return toCol === 'paid';
                    return false;
                }
                if (fromCol === 'washing_drying') {
                    if (toCol === 'pending') return true;
                    const ps = String(drag.getAttribute('data-payment-status') || '').toLowerCase();
                    const expected = ps === 'paid' ? 'paid' : 'open_ticket';
                    return expected === toCol;
                }
                const nextMap = {
                    pending: 'washing_drying',
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

                if (!laundryStatusTrackingEnabled) {
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
                    return;
                }

                if (trackMachineMovementEnabled) {
                    if (fromCol === 'pending' && toCol === 'washing_rinsing') {
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
                    if (fromCol === 'washing_rinsing' && toCol === 'pending') {
                        const ok = await postAdvance(item, 'pending', {});
                        if (!ok) {
                            evt.to.removeChild(item);
                            const ref = evt.from.children[evt.oldIndex] || null;
                            evt.from.insertBefore(item, ref);
                        }
                        ensureEmptyPlaceholder(evt.from);
                        ensureEmptyPlaceholder(evt.to);
                        return;
                    }
                    if (fromCol === 'washing_rinsing') {
                        evt.to.removeChild(item);
                        const ref = evt.from.children[evt.oldIndex] || null;
                        evt.from.insertBefore(item, ref);
                        ensureEmptyPlaceholder(evt.to);
                        ensureEmptyPlaceholder(evt.from);
                        showProcessError('Washing - Rinsing auto-advances to Drying when timer ends.');
                        return;
                    }
                    if (fromCol === 'drying' && toCol === 'pending') {
                        const ok = await postAdvance(item, 'pending', {});
                        if (!ok) {
                            evt.to.removeChild(item);
                            const ref = evt.from.children[evt.oldIndex] || null;
                            evt.from.insertBefore(item, ref);
                        }
                        ensureEmptyPlaceholder(evt.from);
                        ensureEmptyPlaceholder(evt.to);
                        return;
                    }
                    if (fromCol === 'drying' && (toCol === 'open_ticket' || toCol === 'paid')) {
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
                    return;
                }

                if (fromCol === 'pending' && toCol === 'washing_drying') {
                    const ok = machineAssignmentEnabled
                        ? await openMachineAssignModal(item, {})
                        : await postAdvance(item, 'washing_drying', {});
                    if (!ok) {
                        evt.to.removeChild(item);
                        const ref = evt.from.children[evt.oldIndex] || null;
                        evt.from.insertBefore(item, ref);
                    }
                    ensureEmptyPlaceholder(evt.from);
                    ensureEmptyPlaceholder(evt.to);
                    return;
                }

                if (fromCol === 'washing_drying' && (toCol === 'open_ticket' || toCol === 'paid')) {
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
                if (fromCol === 'washing_drying' && toCol === 'pending') {
                    const ok = await postAdvance(item, 'pending', {});
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
    const detailNextActionBtn = document.getElementById('laundryDetailNextActionBtn');
    const detailVoidBtn = document.getElementById('laundryDetailVoidBtn');
    const detailModalApi = (window.bootstrap?.Modal && detailModalEl) ? window.bootstrap.Modal.getOrCreateInstance(detailModalEl) : null;
    const isTenantAdminUser = <?= $isTenantAdmin ? 'true' : 'false' ?>;
    let detailActiveRow = null;

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
            split_payment: 'Split Payment (Cash + Online)',
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
        const stage = String(o.track_machine_stage || '');
        if (!laundryStatusTrackingEnabled) {
            if (st === 'paid' || ps === 'paid') return 'Paid';
            return 'Unpaid';
        }
        if (st === 'pending') return 'Pending';
        if (st === 'washing_drying' || st === 'running') {
            if (stage === 'washing_rinsing') return 'Washing - Rinsing';
            if (stage === 'drying') return 'Drying';
            if (stage === 'drying_waiting_machine') return 'Drying (Waiting for Machine)';
            return 'Washing - Drying';
        }
        if (st === 'open_ticket') return 'Unpaid';
        if (st === 'paid') return 'Paid';
        return st || '—';
    };
    const statusFieldLabelFromOrder = (o) => {
        const statusText = statusLabelFromOrder(o);
        return (statusText === 'Paid' || statusText === 'Unpaid') ? 'Payment Status' : 'Load Status';
    };

    const renderOrderDetail = (payload) => {
        const o = payload.order || {};
        const inc = payload.inclusions || {};
        const addons = payload.add_ons || [];
        const statusLabel = statusLabelFromOrder(o);
        const statusFieldLabel = statusFieldLabelFromOrder(o);
        const referenceNo = String(o.reference_code || '').trim() || '—';
        const groupReferenceNo = String(o.group_reference_code || '').trim();
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
        const orderTotal = Math.max(0, parseFloat(o.total_amount || 0) || 0);
        const paidAmt = Math.min(orderTotal, Math.max(0, parseFloat(tendered || 0) || 0));
        const balanceAmt = Math.max(0, orderTotal - paidAmt);
        const chg = o.change_amount;
        const paidBlock = (String(o.payment_status) === 'paid')
            ? `<dt class="col-sm-4 text-muted">Payment</dt><dd class="col-sm-8">${escapeHtml(paymentMethodLabel(o.payment_method))} · Paid ${money(paidAmt)} · Balance ${money(balanceAmt)}${parseFloat(chg) > 0 ? ` · Change ${money(parseFloat(chg))}` : ''}</dd>`
            : `<dt class="col-sm-4 text-muted">Payment</dt><dd class="col-sm-8 text-muted">Unpaid · Paid ${money(paidAmt)} · Balance ${money(balanceAmt)}</dd>`;

        return `
            <div class="row g-2 small">
              <div class="col-md-6">
                <dl class="row mb-0">
                  <dt class="col-sm-4 text-muted">Date</dt><dd class="col-sm-8">${escapeHtml(String(o.created_at || '—'))}</dd>
                  <dt class="col-sm-4 text-muted">Group Ref</dt><dd class="col-sm-8 font-monospace">${escapeHtml(groupReferenceNo || '—')}</dd>
                  <dt class="col-sm-4 text-muted">Customer</dt><dd class="col-sm-8">${escapeHtml(String(o.customer_name || 'Walk-in'))}</dd>
                  <dt class="col-sm-4 text-muted">Order type</dt><dd class="col-sm-8">${escapeHtml(String(o.order_type_label || o.order_type || '—'))}</dd>
                  <dt class="col-sm-4 text-muted">${escapeHtml(statusFieldLabel)}</dt><dd class="col-sm-8">${escapeHtml(statusLabel)}</dd>
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

    const detailNextActionFromRow = (row) => {
        if (!(row instanceof HTMLTableRowElement)) return null;
        const statusRaw = String(row.getAttribute('data-status') || '').trim();
        const paymentStatus = String(row.getAttribute('data-payment-status') || 'unpaid').trim();
        const isPaid = paymentStatus === 'paid';
        const isVoid = statusRaw === 'void';
        if (isVoid) return null;
        if (!laundryStatusTrackingEnabled) {
            return !isPaid ? { type: 'pay', label: 'Paid' } : null;
        }
        if (statusRaw === 'pending') return { type: 'start', label: 'Start Washing - Drying' };
        if (statusRaw === 'washing_drying' || statusRaw === 'running') return { type: 'open_ticket', label: isPaid ? 'Paid' : 'Unpaid' };
        if (statusRaw === 'open_ticket' && !isPaid) return { type: 'pay', label: 'Paid' };
        return null;
    };
    const syncDetailModalActions = () => {
        if (!(detailNextActionBtn instanceof HTMLButtonElement) || !(detailVoidBtn instanceof HTMLButtonElement)) return;
        const row = detailActiveRow;
        const nextAction = detailNextActionFromRow(row);
        if (nextAction) {
            detailNextActionBtn.classList.remove('d-none');
            detailNextActionBtn.textContent = nextAction.label;
            detailNextActionBtn.dataset.actionType = nextAction.type;
        } else {
            detailNextActionBtn.classList.add('d-none');
            detailNextActionBtn.textContent = 'Next action';
            detailNextActionBtn.dataset.actionType = '';
        }
        const canVoid = isTenantAdminUser && row instanceof HTMLTableRowElement && String(row.getAttribute('data-status') || '').trim() !== 'void';
        detailVoidBtn.classList.toggle('d-none', !canVoid);
    };

    const openOrderDetail = async (url, sourceRow = null) => {
        if (!detailModalEl || !detailBodyEl) return;
        detailActiveRow = sourceRow instanceof HTMLTableRowElement ? sourceRow : null;
        syncDetailModalActions();
        detailBodyEl.innerHTML = '<div class="text-muted small">Loading…</div>';
        if (detailTitleEl) detailTitleEl.textContent = 'Transaction details';
        if (detailModalApi) detailModalApi.show();
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
            syncDetailModalActions();
        } catch {
            detailBodyEl.innerHTML = '<p class="text-danger mb-0">Network error.</p>';
        }
    };

    const updateTableRowStatusUI = (row) => {
        if (!(row instanceof HTMLTableRowElement)) return;
        const statusRaw = String(row.getAttribute('data-status') || '').trim();
        const paymentStatus = String(row.getAttribute('data-payment-status') || 'unpaid').trim();
        const isPaid = paymentStatus === 'paid';
        const isVoid = statusRaw === 'void';
        const isPending = statusRaw === 'pending';
        const isWashingDrying = statusRaw === 'washing_drying' || statusRaw === 'running';
        const isOpenTicket = statusRaw === 'open_ticket';
        const isPaidStage = statusRaw === 'paid';

        const statusCell = row.querySelector('[data-col-key="load_status"]');
        const actionCell = row.querySelector('[data-col-key="status_transition"]');
        if (!(statusCell instanceof HTMLElement) || !(actionCell instanceof HTMLElement)) return;

        const statusLabel = !laundryStatusTrackingEnabled
            ? (isPaid || isPaidStage ? 'Paid' : 'Unpaid')
            : (isPending
                ? 'Pending'
                : (isWashingDrying
                    ? 'Washing - Drying'
                    : (isOpenTicket ? 'Unpaid' : 'Paid')));

        statusCell.innerHTML = isVoid
            ? '<span class="badge text-bg-danger">VOID</span>'
            : `<span class="small">${statusLabel}</span>`;

        if (!laundryStatusTrackingEnabled) {
            actionCell.innerHTML = (!isVoid && !isPaid)
                ? '<button type="button" class="btn btn-outline-success btn-sm py-0 laundry-table-action-pay">Paid</button>'
                : '<span class="text-muted">—</span>';
            return;
        }
        if (laundryStatusTrackingEnabled && isPending) {
            actionCell.innerHTML = '<button type="button" class="btn btn-outline-primary btn-sm py-0 laundry-table-action-start">Start Washing - Drying</button>';
            return;
        }
        if (laundryStatusTrackingEnabled && isWashingDrying) {
            actionCell.innerHTML = `<button type="button" class="btn btn-outline-info btn-sm py-0 laundry-table-action-open-ticket">${isPaid ? 'Paid' : 'Unpaid'}</button>`;
            return;
        }
        if (laundryStatusTrackingEnabled && isOpenTicket && !isPaid) {
            actionCell.innerHTML = '<button type="button" class="btn btn-outline-success btn-sm py-0 laundry-table-action-pay">Paid</button>';
            return;
        }
        actionCell.innerHTML = '<span class="text-muted">—</span>';
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
    const sharedPrintFooterHtml = 'This is not an official receipt<br>For reference only';
    const sharedPrintFooterLines = [
        'This is not an official receipt',
        'For reference only',
    ];
    const renderReceiptPreviewHtml = (receiptData, includePaymentBlock = false) => {
        const headerName = cfgText('display_name') || cfgText('store_name') || 'Laundry Shop';
        const style = includePaymentBlock ? cfgText('business_style') : '';
        const phone = includePaymentBlock ? cfgText('phone') : '';
        const address = includePaymentBlock ? cfgText('address') : '';
        const email = includePaymentBlock ? cfgText('email') : '';
        const tin = includePaymentBlock ? cfgText('tax_id') : '';
        const paymentMethodRaw = String(receiptData.paymentMethod || '').toLowerCase();
        const includePaymentDetails = includePaymentBlock && paymentMethodRaw !== 'free' && paymentMethodRaw !== 'reward';
        const paymentRow = includePaymentDetails
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
                <hr class="my-1"><div class="text-center">${sharedPrintFooterHtml}</div>
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
        const paymentMethodRaw = String(payload.paymentMethod || '').toLowerCase();
        const includePaymentDetails = includePaymentBlock && paymentMethodRaw !== 'free' && paymentMethodRaw !== 'reward';
        if (includePaymentDetails) {
            pushText(`Payment: ${payload.paymentMethod || '—'}\n`);
            if (payload.amountTenderedText) pushText(`Tendered: ${escposMoney(payload.amountTenderedText, '')}\n`);
            if (payload.changeText) pushText(`Change: ${escposMoney(payload.changeText, '')}\n`);
        }
        if (payload.savedAt) pushText(`Saved: ${payload.savedAt}\n`);
        pushText(line);
        push(0x1b, 0x61, 0x01);
        sharedPrintFooterLines.forEach((row) => pushText(`${row}\n`));
        push(0x1b, 0x61, 0x00);
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
            return escposReceiptBytesSingle(payload, false, "Customer's Copy");
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
                        ? '<div class="small text-muted mt-2">This will print 1 copy: Customer\'s Copy only.</div>'
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
        const row = btn.closest('.laundry-sales-table-row');
        if (u) openOrderDetail(u, row);
    });

    document.addEventListener('click', (e) => {
        const row = e.target.closest('.laundry-sales-table-row');
        if (!row) return;
        if (e.target.closest('button, a, input, select, textarea, label')) return;
        const u = row.getAttribute('data-detail-url');
        if (!u) return;
        e.preventDefault();
        openOrderDetail(u, row);
    });

    document.addEventListener('click', (e) => {
        const startBtn = e.target.closest('.laundry-table-action-start');
        if (!startBtn) return;
        e.preventDefault();
        const tr = startBtn.closest('tr');
        if (!tr) return;
        if (!machineAssignmentEnabled) {
            postAdvance(tr, 'washing_drying', { reloadOnSuccess: true, row: tr }).then((ok) => {
                if (ok) updateTableRowStatusUI(tr);
            });
            return;
        }
        openMachineAssignModal(tr, { reloadOnSuccess: true, row: tr }).then((ok) => {
            if (ok) updateTableRowStatusUI(tr);
        });
    });

    document.addEventListener('click', (e) => {
        const openTicketBtn = e.target.closest('.laundry-table-action-open-ticket');
        if (!openTicketBtn) return;
        e.preventDefault();
        const tr = openTicketBtn.closest('tr');
        if (!tr) return;
        postAdvance(tr, 'open_ticket', { reloadOnSuccess: true, row: tr }).then((ok) => {
            if (ok) updateTableRowStatusUI(tr);
        });
    });

    document.addEventListener('click', async (e) => {
        const payBtn = e.target.closest('.laundry-table-action-pay');
        if (!payBtn) return;
        e.preventDefault();
        const tr = payBtn.closest('tr');
        if (!tr) return;
        openPayModalFromCard(tr);
    });

    const voidModalEl = document.getElementById('laundryVoidModal');
    const voidForm = document.getElementById('laundryVoidForm');
    const voidReasonEl = document.getElementById('laundryVoidReason');
    const voidModalApi = (window.bootstrap?.Modal && voidModalEl) ? window.bootstrap.Modal.getOrCreateInstance(voidModalEl) : null;
    let pendingVoidUrl = '';
    let pendingVoidRowEl = null;
    const openVoidModalForRow = (rowEl) => {
        pendingVoidRowEl = rowEl;
        pendingVoidUrl = pendingVoidRowEl?.getAttribute('data-void-url') || '';
        if (!pendingVoidUrl) return;
        if (!voidModalApi || !voidReasonEl) {
            showProcessError('Void modal is not available.');
            return;
        }
        voidReasonEl.value = '';
        voidModalApi.show();
        setTimeout(() => voidReasonEl.focus(), 120);
    };

    document.addEventListener('click', (e) => {
        const voidBtn = e.target.closest('.laundry-table-action-void, .laundry-kanban-action-void');
        if (!voidBtn) return;
        e.preventDefault();
        openVoidModalForRow(voidBtn.closest('tr, .laundry-kanban-card'));
    });

    detailNextActionBtn?.addEventListener('click', async () => {
        if (!detailActiveRow) return;
        const actionType = String(detailNextActionBtn.dataset.actionType || '').trim();
        if (!actionType) return;
        if (actionType === 'start') {
            const ok = machineAssignmentEnabled
                ? await openMachineAssignModal(detailActiveRow, { reloadOnSuccess: true, row: detailActiveRow })
                : await postAdvance(detailActiveRow, 'washing_drying', { reloadOnSuccess: true, row: detailActiveRow });
            if (ok) {
                updateTableRowStatusUI(detailActiveRow);
                syncDetailModalActions();
            }
            return;
        }
        if (actionType === 'open_ticket') {
            const ok = await postAdvance(detailActiveRow, 'open_ticket', { reloadOnSuccess: true, row: detailActiveRow });
            if (ok) {
                updateTableRowStatusUI(detailActiveRow);
                syncDetailModalActions();
            }
            return;
        }
        if (actionType === 'pay') {
            detailModalApi?.hide();
            openPayModalFromCard(detailActiveRow);
        }
    });

    detailVoidBtn?.addEventListener('click', () => {
        if (!detailActiveRow) return;
        detailModalApi?.hide();
        openVoidModalForRow(detailActiveRow);
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
