<?php
$orderTypes = $order_types ?? [];
$customers = $customers ?? [];
$detergentItems = $detergent_items ?? [];
$fabconItems = $fabcon_items ?? [];
$bleachItems = $bleach_items ?? [];
$nextTransactionId = max(1, (int) ($next_transaction_id ?? 1));
$referencePreview = trim((string) ($reference_preview ?? ''));
$freeCustomerLocked = ! empty($free_customer_locked);
$rewardConfig = is_array($reward_config ?? null) ? $reward_config : null;
$rewardThreshold = $rewardConfig !== null ? max(1.0, (float) ($rewardConfig['minimum_points_to_redeem'] ?? $rewardConfig['reward_points_cost'] ?? 10)) : 0.0;
$rewardOrderTypeCode = $rewardConfig !== null ? (string) ($rewardConfig['reward_order_type_code'] ?? '') : '';
$rewardPointsPerDropoffLoad = $rewardConfig !== null ? max(0.0, (float) ($rewardConfig['points_per_dropoff_load'] ?? 1)) : 0.0;
$enableBluetoothPrint = ! empty($enable_bluetooth_print);
$trackGasulUsage = ! empty($track_gasul_usage);
$isTenantAdmin = ((auth_user()['role'] ?? '') === 'tenant_admin');
$inventoryCardImageSrc = static function (array $item): string {
    $name = trim((string) ($item['name'] ?? 'Item'));
    $path = trim((string) ($item['image_path'] ?? ''));
    if ($path !== '') {
        return url($path);
    }
    $initial = strtoupper(substr($name !== '' ? $name : 'I', 0, 1));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
        .'<rect width="120" height="120" rx="16" fill="#dbeafe"/>'
        .'<text x="60" y="68" font-size="44" text-anchor="middle" fill="#1d4ed8" font-family="Arial, sans-serif">'.$initial.'</text>'
        .'</svg>';

    return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
};
$orderTypeCardImageSrc = static function (array $ot): string {
    $label = trim((string) ($ot['label'] ?? 'Order'));
    $initial = strtoupper(substr($label !== '' ? $label : 'O', 0, 1));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">'
        .'<rect width="120" height="120" rx="16" fill="#dbeafe"/>'
        .'<text x="60" y="68" font-size="44" text-anchor="middle" fill="#1d4ed8" font-family="Arial, sans-serif">'.$initial.'</text>'
        .'</svg>';

    return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
};
$stockQtyLabel = static function (float $qty): string {
    if (abs($qty - round($qty)) < 0.0001) {
        return (string) (int) round($qty);
    }

    return rtrim(rtrim(sprintf('%.2f', $qty), '0'), '.');
};
?>
<style>
    .kiosk-selectable-btn {
        transition: all .15s ease-in-out;
    }
    .kiosk-item-card {
        min-width: 120px;
        max-width: 140px;
        min-height: 116px;
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        text-align: center;
        white-space: normal;
        line-height: 1.2;
    }
    .kiosk-order-type-wrap {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
    }
    .kiosk-item-card img {
        display: block;
        margin: 0 auto;
    }
    .kiosk-order-type-qty {
        display: inline-block;
        margin-top: 0.2rem;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.92);
        color: #0d3b66;
        border: 1px solid rgba(13, 59, 102, 0.28);
        font-size: 0.8rem;
        line-height: 1.1;
        font-weight: 700;
    }
    .kiosk-order-type-price {
        font-size: 0.78rem;
        color: #1f5f7a;
    }
    .kiosk-order-type-adjust {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        margin-top: 0.2rem;
    }
    .kiosk-order-type-adjust .btn {
        min-width: 26px;
        height: 24px;
        line-height: 1;
        padding: 0;
        font-weight: 700;
    }
    .kiosk-qty-adjust {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        margin-top: 0.35rem;
        width: 100%;
    }
    .kiosk-qty-adjust .btn {
        min-width: 26px;
        height: 24px;
        line-height: 1;
        padding: 0;
        font-weight: 700;
    }
    /* Keep lower section +/- controls aligned near cards like top controls. */
    .kiosk-step[data-step="2"] #kioskSupplyDetWrap,
    .kiosk-step[data-step="2"] #kioskSupplyFabWrap,
    .kiosk-step[data-step="2"] #kioskSupplyBleachWrap {
        width: fit-content;
        max-width: 100%;
    }
    .kiosk-step[data-step="2"] #kioskSupplyDetCards,
    .kiosk-step[data-step="2"] #kioskSupplyFabCards,
    .kiosk-step[data-step="2"] #kioskSupplyBleachCards {
        display: inline-flex;
        flex-wrap: wrap;
    }
    .kiosk-step[data-step="2"] .kiosk-qty-adjust {
        justify-content: center;
        width: 100%;
    }
    .kiosk-step[data-step="3"] .kiosk-qty-adjust {
        justify-content: flex-start;
        width: auto;
    }
    .kiosk-fold-qty-wrap {
        margin-top: 0.5rem;
    }
    .kiosk-card-selected {
        border-width: 2px !important;
        border-color: var(--bs-primary, #0d6efd) !important;
        background: linear-gradient(180deg, #2bb3f2 0%, #129ae9 100%) !important;
        color: #fff !important;
        box-shadow: 0 0 0 2px rgba(18, 154, 233, 0.24), 0 10px 18px -12px rgba(18, 154, 233, 0.5);
        transform: translateY(-1px);
    }
    .kiosk-selectable-btn.kiosk-card-selected.btn-primary {
        background: linear-gradient(180deg, #2bb3f2 0%, #129ae9 100%) !important;
        border-color: var(--bs-primary, #0d6efd) !important;
        color: #fff !important;
    }
    .kiosk-card-selected .kiosk-order-type-price,
    .kiosk-card-selected .kiosk-item-stock,
    .kiosk-card-selected .small,
    .kiosk-card-selected .fw-semibold {
        color: #fff !important;
    }
    .kiosk-card-selected img {
        border-color: rgba(255, 255, 255, 0.45) !important;
    }
    .kiosk-card-selected-low-stock {
        border-width: 2px !important;
        border-color: var(--bs-primary, #0d6efd) !important;
        background: linear-gradient(180deg, #2bb3f2 0%, #129ae9 100%) !important;
        color: #fff !important;
        box-shadow: 0 0 0 2px rgba(18, 154, 233, 0.26), 0 10px 18px -12px rgba(18, 154, 233, 0.5);
    }
    .kiosk-summary-receipt {
        border: 1px solid #7dd3fc;
        border-radius: 10px;
        padding: 0;
        overflow: hidden;
        background: linear-gradient(180deg, rgba(34, 211, 238, 0.18), rgba(59, 130, 246, 0.08));
        font-size: 0.92rem;
    }
    .kiosk-summary-receipt .line {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.45rem 0.85rem;
        border-bottom: 1px dotted rgba(2, 132, 199, 0.28);
    }
    .kiosk-summary-receipt .line:nth-child(odd) {
        background-color: rgba(255, 255, 255, 0.58);
    }
    .kiosk-summary-receipt .line:nth-child(even) {
        background-color: rgba(255, 255, 255, 0.22);
    }
    .kiosk-summary-receipt .line:last-child {
        border-bottom: 0;
    }
    .kiosk-summary-receipt .label {
        color: #495057;
        font-weight: 600;
        flex: 0 0 42%;
    }
    .kiosk-summary-receipt .value {
        color: #212529;
        text-align: right;
        flex: 1;
    }
    .kiosk-summary-receipt ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .kiosk-summary-receipt li + li {
        margin-top: 0.2rem;
    }
    .kiosk-step-context {
        border: 1px solid rgba(13, 110, 253, 0.22);
        background: rgba(255, 255, 255, 0.66);
        border-radius: 10px;
        padding: 0.55rem 0.7rem;
        margin-bottom: 0.65rem;
        font-size: 0.9rem;
    }
    #staffKioskOrderForm {
        padding-bottom: 5.5rem;
    }
    .kiosk-floating-actions {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1020;
        background: rgba(255, 255, 255, 0.96);
        border-top: 1px solid rgba(13, 110, 253, 0.22);
        box-shadow: 0 -6px 18px rgba(0, 0, 0, 0.08);
        backdrop-filter: blur(4px);
    }
    .kiosk-floating-actions-inner {
        max-width: 1140px;
        margin: 0 auto;
        padding: 0.55rem 0.75rem calc(0.55rem + env(safe-area-inset-bottom));
    }
    .kiosk-floating-total {
        display: inline-flex;
        align-items: baseline;
        gap: 0.35rem;
        font-size: 0.9rem;
        margin-bottom: 0.45rem;
    }
    .kiosk-floating-total strong {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 1rem;
    }
    @media (min-width: 768px) {
        #staffKioskOrderForm {
            padding-bottom: 1rem;
        }
        .kiosk-floating-actions {
            left: auto;
            right: 1rem;
            bottom: 1rem;
            border: 1px solid rgba(13, 110, 253, 0.22);
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
        }
        .kiosk-floating-actions-inner {
            max-width: none;
            margin: 0;
            padding: 0.65rem 0.75rem;
        }
    }
    @media (max-width: 767.98px) {
        .kiosk-floating-actions .btn {
            flex: 1 1 auto;
        }
    }
</style>

<form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" id="staffKioskOrderForm" data-mpg-native-submit data-reward-points-per-load="<?= e((string) $rewardPointsPerDropoffLoad) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="origin" value="staff_portal">
    <input type="hidden" name="order_mode" id="kioskOrderMode" value="drop_off">
    <input type="hidden" name="self_service_lines" id="kioskSelfServiceLines" value="">
    <input type="hidden" name="customer_id" id="kioskCustomerId" value="">
    <input type="hidden" name="customer_selection" id="kioskCustomerSelection" value="">
    <input type="hidden" name="order_type" id="kioskOrderTypeCode" value="">
    <input type="hidden" name="service_mode" id="kioskServiceMode" value="regular">
    <input type="hidden" name="reward_redemption" id="kioskRewardRedemption" value="0">
    <input type="hidden" name="reference_code" id="kioskReferenceCode" value="<?= e($referencePreview) ?>">
    <input type="hidden" name="payment_timing" id="kioskPaymentTiming" value="pay_later">
    <input type="hidden" name="payment_method" id="kioskPaymentMethod" value="pending">
    <input type="hidden" name="amount_tendered" id="kioskAmountTendered" value="">
    <input type="hidden" name="change_amount" id="kioskChangeAmount" value="">
    <input type="hidden" name="discount_percentage" id="kioskDiscountPercentage" value="0">
    <input type="hidden" name="split_cash_amount" id="kioskSplitCashAmount" value="">
    <input type="hidden" name="split_online_amount" id="kioskSplitOnlineAmount" value="">
    <input type="hidden" name="split_online_method" id="kioskSplitOnlineMethod" value="">
    <input type="hidden" name="enable_bluetooth_print" value="0">
    <input type="hidden" name="track_laundry_status" id="kioskTrackLaundryStatus" value="<?= ! empty($laundry_status_tracking_enabled) ? '1' : '0' ?>">
    <input type="hidden" name="use_machines" id="kioskUseMachines" value="0">
    <input type="hidden" name="service_weight" id="kioskServiceWeightHidden" value="">
    <input type="hidden" name="actual_weight_kg" id="kioskActualWeightHidden" value="">
    <input type="hidden" name="wash_qty" id="kioskWashQtyHidden" value="1">
    <input type="hidden" name="dry_qty" value="1">
    <input type="hidden" name="include_fold_service" id="kioskFoldService" value="0">
    <input type="hidden" name="inclusion_detergent_item_id" id="kioskInclusionDetergent" value="">
    <input type="hidden" name="inclusion_fabcon_item_id" id="kioskInclusionFabcon" value="">
    <input type="hidden" name="inclusion_bleach_item_id" id="kioskInclusionBleach" value="">
    <input type="hidden" name="inclusion_detergent_qty" id="kioskInclusionDetergentQty" value="0">
    <input type="hidden" name="inclusion_fabcon_qty" id="kioskInclusionFabconQty" value="0">
    <input type="hidden" name="inclusion_bleach_qty" id="kioskInclusionBleachQty" value="0">
    <input type="hidden" name="addon_detergent_item_id" id="kioskAddonDetergentId" value="">
    <input type="hidden" name="addon_fabcon_item_id" id="kioskAddonFabconId" value="">
    <input type="hidden" name="addon_bleach_item_id" id="kioskAddonBleachId" value="">
    <input type="hidden" name="addon_other_item_id" id="kioskAddonOtherId" value="">
    <input type="hidden" name="detergent_qty" id="kioskAddonDetergentQty" value="0">
    <input type="hidden" name="fabcon_qty" id="kioskAddonFabconQty" value="0">
    <input type="hidden" name="bleach_qty" id="kioskAddonBleachQty" value="0">
    <input type="hidden" name="other_qty" id="kioskAddonOtherQty" value="0">
    <input type="hidden" name="track_gasul" id="kioskTrackGasul" value="<?= $trackGasulUsage ? '1' : '0' ?>">
    <input type="hidden" name="fold_service_qty" id="kioskFoldServiceQty" value="0">

    <?php if ($isTenantAdmin): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="small fw-semibold text-secondary text-uppercase mb-2">Kiosk Settings</div>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="kioskTrackGasulToggle" value="1" <?= $trackGasulUsage ? 'checked' : '' ?>>
                    <label class="form-check-label" for="kioskTrackGasulToggle">
                        Track Gasul Usage
                    </label>
                    <div class="small text-muted mt-1">
                        ON: show Gasul in Add-on Other items and require selection before saving. OFF: Gasul is optional.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="small text-muted mb-2">Single-page mode: tap selections below, then submit via Pay Now or Pay Later.</div>

    <div class="card mb-3 kiosk-step" data-step="1">
        <div class="card-body">
            <h6 class="mb-2">Order mode</h6>
            <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Order mode">
                <button type="button" class="btn btn-primary kiosk-order-mode-btn kiosk-selectable-btn" data-mode="drop_off" id="kioskOrderModeDropOff">Drop Off</button>
                <button type="button" class="btn btn-outline-secondary kiosk-order-mode-btn kiosk-selectable-btn" data-mode="self_service" id="kioskOrderModeSelfService">Self Service</button>
            </div>
            <h6 class="mb-2">Order type</h6>
            <div class="d-flex flex-wrap gap-2 mb-3" id="kioskOrderTypeCards">
                <?php foreach ($orderTypes as $ot): ?>
                    <div class="kiosk-order-type-wrap">
                        <button
                            type="button"
                            class="btn btn-outline-primary kiosk-order-type-btn kiosk-selectable-btn kiosk-item-card"
                            data-code="<?= e((string) ($ot['code'] ?? '')) ?>"
                            data-service-kind="<?= e((string) ($ot['service_kind'] ?? 'full_service')) ?>"
                            data-show-in-order-mode="<?= e((string) ($ot['show_in_order_mode'] ?? 'both')) ?>"
                            data-supply-block="<?= e((string) ($ot['supply_block'] ?? 'none')) ?>"
                            data-show-addon-supplies="<?= ((int) ($ot['show_addon_supplies'] ?? 1)) === 1 ? '1' : '0' ?>"
                            data-required-weight="<?= (! empty($ot['required_weight']) || (string) ($ot['service_kind'] ?? '') === 'dry_cleaning') ? '1' : '0' ?>"
                            data-price-per-load="<?= e((string) (float) ($ot['price_per_load'] ?? 0)) ?>"
                            data-detergent-qty="<?= e((string) (float) ($ot['detergent_qty'] ?? 0)) ?>"
                            data-fabcon-qty="<?= e((string) (float) ($ot['fabcon_qty'] ?? 0)) ?>"
                            data-bleach-qty="<?= e((string) (float) ($ot['bleach_qty'] ?? 0)) ?>"
                            data-fold-service-amount="<?= e((string) (float) ($ot['fold_service_amount'] ?? 10)) ?>"
                            data-fold-commission-target="<?= e((string) ($ot['fold_commission_target'] ?? 'branch')) ?>"
                            data-max-weight-kg="<?= e((string) (float) ($ot['max_weight_kg'] ?? 0)) ?>"
                            data-excess-weight-fee-per-kg="<?= e((string) (float) ($ot['excess_weight_fee_per_kg'] ?? 0)) ?>"
                            data-include-in-rewards="<?= ((int) ($ot['include_in_rewards'] ?? 0)) === 1 ? '1' : '0' ?>"
                        >
                            <img src="<?= e($orderTypeCardImageSrc($ot)) ?>" alt="<?= e((string) ($ot['label'] ?? 'Order type')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                            <div class="small fw-semibold"><?= e((string) ($ot['label'] ?? 'Order')) ?></div>
                            <?php $cardPrice = (string) ($ot['code'] ?? '') === 'fold_with_price'
                                ? (float) ($ot['fold_service_amount'] ?? ($ot['price_per_load'] ?? 0))
                                : (float) ($ot['price_per_load'] ?? 0); ?>
                            <div class="kiosk-order-type-price">Price: <?= e(format_money($cardPrice)) ?></div>
                            <div class="d-none kiosk-order-type-qty" data-qty="0">Qty: 0</div>
                        </button>
                        <div class="kiosk-order-type-adjust d-none">
                            <button type="button" class="btn btn-outline-secondary btn-sm kiosk-order-type-adjust-btn" data-action="decrement" aria-label="Decrease quantity">-</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm kiosk-order-type-adjust-btn" data-action="increment" aria-label="Increase quantity">+</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <input type="hidden" id="kioskNumberOfLoads" name="number_of_loads" value="1">
            <div class="row g-2 mt-2">
                <div class="col-md-4 d-none" id="kioskWeightWrap">
                    <label class="form-label mb-1" for="kioskServiceWeightInput">Weight</label>
                    <input type="number" min="0.01" step="0.01" class="form-control" id="kioskServiceWeightInput" placeholder="e.g. 7.5">
                </div>
                <div class="col-md-4 d-none" id="kioskActualWeightWrap">
                    <label class="form-label mb-1" for="kioskActualWeightInput">Actual Weight (kg)</label>
                    <input type="number" min="0.01" step="0.01" class="form-control" id="kioskActualWeightInput" placeholder="e.g. 10">
                    <div class="small text-muted mt-1 mb-2" id="kioskExcessFeeHint"></div>
                </div>
            </div>
            <div id="kioskCustomerSection">
                <h6 class="mb-2">Customer</h6>
                <?php if ($freeCustomerLocked): ?>
                    <div class="alert alert-warning py-2 small mb-2">
                        <strong>Free Mode:</strong> transactions are always assigned to Walk-in customer.
                    </div>
                <?php else: ?>
                    <input type="search" class="form-control mb-2" id="kioskCustomerSearch" placeholder="Search customer name">
                    <div class="small text-muted mb-2">Top 10 customers by transactions are shown. Search to find the rest.</div>
                    <div class="small text-muted mb-2 d-none" id="kioskCustomerNoMatchWrap">
                        No customer found for "<span id="kioskCustomerNoMatchText"></span>".
                        <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 align-baseline ms-1" id="kioskCustomerNoMatchCreateBtn">Create customer</button>
                    </div>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2" id="kioskCustomerCards">
                    <button type="button" class="btn <?= $freeCustomerLocked ? 'btn-primary kiosk-card-selected' : 'btn-outline-secondary' ?> kiosk-customer-btn kiosk-selectable-btn" data-id="">Walk-in customer</button>
                    <?php if (! $freeCustomerLocked): ?>
                        <?php foreach ($customers as $idx => $c): ?>
                            <?php $topTen = ((int) $idx) < 10; ?>
                            <button
                                type="button"
                                class="btn btn-outline-secondary kiosk-customer-btn kiosk-selectable-btn <?= $topTen ? '' : 'd-none' ?>"
                                data-id="<?= (int) ($c['id'] ?? 0) ?>"
                                data-top-ten="<?= $topTen ? '1' : '0' ?>"
                                data-rewards-balance="<?= e((string) (float) ($c['rewards_balance'] ?? 0)) ?>"
                            ><?= e((string) ($c['name'] ?? '')) ?></button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div id="kioskServiceModeSection">
                <h6 class="mb-2 mt-3">Service mode</h6>
                <div class="d-flex flex-wrap gap-2" role="group" aria-label="Service mode">
                    <button type="button" class="btn btn-primary kiosk-service-mode-btn kiosk-selectable-btn" data-mode="regular" id="kioskModeRegular">Regular</button>
                    <button type="button" class="btn btn-outline-secondary kiosk-service-mode-btn kiosk-selectable-btn" data-mode="free" id="kioskModeFree">Free</button>
                    <button
                        type="button"
                        class="btn btn-outline-success kiosk-service-mode-btn kiosk-selectable-btn"
                        data-mode="reward"
                        id="kioskModeReward"
                        data-reward-threshold="<?= e((string) $rewardThreshold) ?>"
                        data-reward-order-type="<?= e($rewardOrderTypeCode) ?>"
                    >Reward</button>
                </div>
                <div class="small text-muted mt-1" id="kioskRewardStatus">Select a saved customer to check reward availability.</div>
                <div class="small text-muted">Note: Rewards are counted only after the order is paid.</div>
            </div>
        </div>
    </div>

    <div class="card mb-3 kiosk-step" data-step="2">
        <div class="card-body">
            <h6 class="mb-2">Service Inclusion</h6>
            <div id="kioskNoSuppliesMessage" class="small text-muted d-none">No required service supplies for this order type.</div>
            <div class="vstack gap-3" id="kioskSuppliesWrap">
                <div id="kioskSupplyDetWrap">
                    <label class="form-label mb-1">Detergent</label>
                    <div class="d-flex flex-wrap gap-2" id="kioskSupplyDetCards">
                        <?php foreach ($detergentItems as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'addon') { continue; } ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-supply-det-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Detergent')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="kiosk-order-type-qty d-none kiosk-supply-qty-badge" data-inclusion-kind="det">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="kiosk-qty-adjust mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-inclusion-qty-btn" data-target="det" data-action="decrement" aria-label="Decrease detergent inclusion qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-inclusion-qty-btn" data-target="det" data-action="increment" aria-label="Increase detergent inclusion qty">+</button>
                    </div>
                </div>
                <div id="kioskSupplyFabWrap">
                    <label class="form-label mb-1">Fabric conditioner</label>
                    <div class="d-flex flex-wrap gap-2" id="kioskSupplyFabCards">
                        <?php foreach ($fabconItems as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'addon') { continue; } ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-supply-fab-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Fabcon')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="kiosk-order-type-qty d-none kiosk-supply-qty-badge" data-inclusion-kind="fab">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="kiosk-qty-adjust mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-inclusion-qty-btn" data-target="fab" data-action="decrement" aria-label="Decrease fabric conditioner inclusion qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-inclusion-qty-btn" data-target="fab" data-action="increment" aria-label="Increase fabric conditioner inclusion qty">+</button>
                    </div>
                </div>
                <div id="kioskSupplyBleachWrap">
                    <label class="form-label mb-1">Bleach (optional)</label>
                    <div class="d-flex flex-wrap gap-2" id="kioskSupplyBleachCards">
                        <?php foreach ($bleachItems as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'addon') { continue; } ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-supply-bleach-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Bleach')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="kiosk-order-type-qty d-none kiosk-supply-qty-badge" data-inclusion-kind="bleach">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="kiosk-qty-adjust mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-inclusion-qty-btn" data-target="bleach" data-action="decrement" aria-label="Decrease bleach inclusion qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-inclusion-qty-btn" data-target="bleach" data-action="increment" aria-label="Increase bleach inclusion qty">+</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 kiosk-step" data-step="3">
        <div class="card-body">
            <h6 class="mb-2">Add-ons (optional)</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on detergent</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonDetCards">
                        <?php foreach ($detergentItems as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-det-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Detergent')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted">Price: <?= e(format_money((float) ($item['unit_cost'] ?? 0))) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="d-none kiosk-order-type-qty kiosk-addon-qty-badge" data-addon-kind="det">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="kioskAddonDetQtyInput" value="0">
                    <div class="kiosk-qty-adjust">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="det" data-action="decrement" aria-label="Decrease detergent qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="det" data-action="increment" aria-label="Increase detergent qty">+</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on fabcon</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonFabCards">
                        <?php foreach ($fabconItems as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-fab-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Fabcon')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted">Price: <?= e(format_money((float) ($item['unit_cost'] ?? 0))) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="d-none kiosk-order-type-qty kiosk-addon-qty-badge" data-addon-kind="fab">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="kioskAddonFabQtyInput" value="0">
                    <div class="kiosk-qty-adjust">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="fab" data-action="decrement" aria-label="Decrease fabcon qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="fab" data-action="increment" aria-label="Increase fabcon qty">+</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on bleach</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonBleachCards">
                        <?php foreach ($bleachItems as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-bleach-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Bleach')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted">Price: <?= e(format_money((float) ($item['unit_cost'] ?? 0))) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="d-none kiosk-order-type-qty kiosk-addon-qty-badge" data-addon-kind="bleach">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="kioskAddonBleachQtyInput" value="0">
                    <div class="kiosk-qty-adjust">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="bleach" data-action="decrement" aria-label="Decrease bleach qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="bleach" data-action="increment" aria-label="Increase bleach qty">+</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on other items</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonOtherCards">
                        <?php foreach (($other_items ?? []) as $item): ?>
                            <?php $showIn = strtolower(trim((string) ($item['show_item_in'] ?? 'both'))); if ($showIn === 'inclusion') { continue; } ?>
                            <?php $isGasul = strtolower(trim((string) ($item['name'] ?? ''))) === 'gasul'; ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-other-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>" data-gasul-item="<?= $isGasul ? '1' : '0' ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Other')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted">Price: <?= e(format_money((float) ($item['unit_cost'] ?? 0))) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                                <div class="d-none kiosk-order-type-qty kiosk-addon-qty-badge" data-addon-kind="other">Qty: 0</div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="kioskAddonOtherQtyInput" value="0">
                    <div class="kiosk-qty-adjust">
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="other" data-action="decrement" aria-label="Decrease other qty">-</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm kiosk-addon-qty-btn" data-target="other" data-action="increment" aria-label="Increase other qty">+</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card kiosk-step" data-step="6">
        <div class="card-body">
            <h6 class="mb-2">Summary</h6>
            <div class="small text-muted mb-2">Review before saving.</div>
            <div class="fw-semibold" id="kioskSelectionSummary">No selection yet.</div>
            <div class="mt-3">
                <p class="small text-muted mb-2 mb-md-3" id="kioskBluetoothPrintNote">
                    Bluetooth printing preference is saved to this branch when you save a transaction.
                </p>
            </div>
        </div>
    </div>

    <div class="kiosk-floating-actions">
        <div class="kiosk-floating-actions-inner">
            <div class="kiosk-floating-total">
                <span class="text-muted">Grand Total:</span>
                <strong id="kioskFloatingGrandTotal">₱0.00</strong>
            </div>
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="kioskEnableBluetoothPrintToggle" name="enable_bluetooth_print" value="1" <?= $enableBluetoothPrint ? 'checked' : '' ?>>
                <label class="form-check-label small" for="kioskEnableBluetoothPrintToggle">
                    Enable Bluetooth Thermal Printing
                </label>
            </div>
            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Payment timing" id="kioskRegularSubmitWrap">
                <button type="submit" class="btn btn-outline-success kiosk-payment-timing-btn" data-timing="pay_now">Pay Now</button>
                <button type="submit" class="btn btn-primary kiosk-payment-timing-btn" data-timing="pay_later">Pay Later</button>
            </div>
            <div class="d-none" id="kioskFreeRewardSubmitWrap">
                <button type="submit" class="btn btn-primary" id="kioskSaveTransactionBtn">Save Transaction</button>
            </div>
        </div>
    </div>

</form>

<div class="modal fade" id="kioskCreateCustomerModal" tabindex="-1" aria-labelledby="kioskCreateCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="kioskCreateCustomerForm" autocomplete="off">
            <div class="modal-header">
                <h6 class="modal-title" id="kioskCreateCustomerModalLabel">Create customer</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label mb-1" for="kioskCreateCustomerName">Customer name</label>
                    <input class="form-control" id="kioskCreateCustomerName" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1" for="kioskCreateCustomerContact">Contact (optional)</label>
                    <input class="form-control" id="kioskCreateCustomerContact" name="contact">
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1" for="kioskCreateCustomerEmail">Email (optional)</label>
                    <input type="email" class="form-control" id="kioskCreateCustomerEmail" name="email">
                </div>
                <div class="mb-0">
                    <label class="form-label mb-1" for="kioskCreateCustomerBirthday">Birthday</label>
                    <input type="date" class="form-control" id="kioskCreateCustomerBirthday" name="birthday">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="kioskCreateCustomerSubmitBtn">Save customer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="kioskPaymentMethodModal" tabindex="-1" aria-labelledby="kioskPaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title" id="kioskPaymentMethodModalLabel">Select payment method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="row g-2">
                    <div class="col-6 col-md-4"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="cash"><i class="fa-solid fa-money-bill-wave me-1"></i>Cash</button></div>
                    <div class="col-6 col-md-4"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="gcash"><i class="fa-solid fa-mobile-screen-button me-1"></i>GCash</button></div>
                    <div class="col-6 col-md-4"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="paymaya"><i class="fa-solid fa-wallet me-1"></i>PayMaya</button></div>
                    <div class="col-6 col-md-4"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="online_banking"><i class="fa-solid fa-building-columns me-1"></i>Online Banking</button></div>
                    <div class="col-6 col-md-4"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="qr_payment"><i class="fa-solid fa-qrcode me-1"></i>QR Payment</button></div>
                    <div class="col-6 col-md-4"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="card"><i class="fa-solid fa-credit-card me-1"></i>Card</button></div>
                    <div class="col-12"><button type="button" class="btn btn-outline-secondary w-100 kiosk-pay-method-option" data-method="split_payment"><i class="fa-solid fa-money-bill-transfer me-1"></i>Split Payment (Cash + Online)</button></div>
                </div>
                <div class="row g-2 mt-2" id="kioskSinglePaymentFields">
                    <div class="col-12">
                        <label class="form-label mb-1" for="kioskDiscountPercentageInput">Discount Percentage</label>
                        <div class="input-group">
                            <input type="number" min="0" max="100" step="0.01" class="form-control" id="kioskDiscountPercentageInput" placeholder="0.00" value="0">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text" id="kioskDiscountAmountDisplay">Discount amount (minus): -₱0.00</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1" for="kioskAmountPaidInput">Amount paid</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="kioskAmountPaidInput" placeholder="0.00">
                    </div>
                    <div class="col-12">
                        <div class="rounded-2 border px-3 py-2 bg-body-tertiary bg-opacity-25">
                            <div class="small text-muted mb-0">Service Total Amount</div>
                            <div class="fw-semibold font-monospace" id="kioskPayNowDueDisplay">₱0.00</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="rounded-2 border px-3 py-2 bg-body-tertiary bg-opacity-25">
                            <div class="small text-muted mb-0">Change</div>
                            <div class="fw-semibold font-monospace text-success" id="kioskChangeDisplay">₱0.00</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-none" id="kioskSplitPaymentFields">
                    <div class="small text-muted mb-2">Enter split amounts (must equal total amount).</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label mb-1" for="kioskSplitCashAmountInput">Cash amount</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="kioskSplitCashAmountInput" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label mb-1" for="kioskSplitOnlineAmountInput">Online amount</label>
                            <input type="number" min="0" step="0.01" class="form-control" id="kioskSplitOnlineAmountInput" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1" for="kioskSplitOnlineMethodSelect">Online payment method</label>
                            <select class="form-select" id="kioskSplitOnlineMethodSelect">
                                <option value="">Select online method</option>
                                <option value="gcash">GCash</option>
                                <option value="paymaya">PayMaya</option>
                                <option value="online_banking">Online Banking</option>
                                <option value="qr_payment">QR Payment</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="kioskPaymentMethodConfirmBtn">Confirm & Save</button>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const KIOSK_SCROLL_TOP_AFTER_SAVE_KEY = 'kioskScrollTopAfterSave';
    const kioskReferenceCode = <?= json_embed($referencePreview) ?>;
    let kioskEnableBluetoothPrint = <?= $enableBluetoothPrint ? 'true' : 'false' ?>;
    try {
        if (window.sessionStorage.getItem(KIOSK_SCROLL_TOP_AFTER_SAVE_KEY) === '1') {
            window.sessionStorage.removeItem(KIOSK_SCROLL_TOP_AFTER_SAVE_KEY);
            window.scrollTo({ top: 0, behavior: 'auto' });
        }
    } catch (_) {
        // Ignore storage access errors and continue normally.
    }

    const steps = Array.from(document.querySelectorAll('.kiosk-step'));
    const paymentMethod = document.getElementById('kioskPaymentMethod');
    const paymentMethodModalEl = document.getElementById('kioskPaymentMethodModal');
    const paymentMethodModal = paymentMethodModalEl ? new bootstrap.Modal(paymentMethodModalEl) : null;
    const paymentMethodConfirmBtn = document.getElementById('kioskPaymentMethodConfirmBtn');
    const amountTenderedHidden = document.getElementById('kioskAmountTendered');
    const changeAmountHidden = document.getElementById('kioskChangeAmount');
    const discountPercentageHidden = document.getElementById('kioskDiscountPercentage');
    const payNowDueDisplay = document.getElementById('kioskPayNowDueDisplay');
    const singlePaymentFieldsWrap = document.getElementById('kioskSinglePaymentFields');
    const discountPercentageInput = document.getElementById('kioskDiscountPercentageInput');
    const discountAmountDisplay = document.getElementById('kioskDiscountAmountDisplay');
    const amountPaidInput = document.getElementById('kioskAmountPaidInput');
    const changeDisplay = document.getElementById('kioskChangeDisplay');
    const splitCashAmountHidden = document.getElementById('kioskSplitCashAmount');
    const splitOnlineAmountHidden = document.getElementById('kioskSplitOnlineAmount');
    const splitOnlineMethodHidden = document.getElementById('kioskSplitOnlineMethod');
    const splitFieldsWrap = document.getElementById('kioskSplitPaymentFields');
    const splitCashAmountInput = document.getElementById('kioskSplitCashAmountInput');
    const splitOnlineAmountInput = document.getElementById('kioskSplitOnlineAmountInput');
    const splitOnlineMethodSelect = document.getElementById('kioskSplitOnlineMethodSelect');
    const bluetoothPrintToggle = document.getElementById('kioskEnableBluetoothPrintToggle');
    const bluetoothPrintNote = document.getElementById('kioskBluetoothPrintNote');
    const regularSubmitWrap = document.getElementById('kioskRegularSubmitWrap');
    const freeRewardSubmitWrap = document.getElementById('kioskFreeRewardSubmitWrap');
    const orderModeInput = document.getElementById('kioskOrderMode');
    const selfServiceLinesInput = document.getElementById('kioskSelfServiceLines');
    const orderTypeCode = document.getElementById('kioskOrderTypeCode');
    const serviceMode = document.getElementById('kioskServiceMode');
    const rewardRedemption = document.getElementById('kioskRewardRedemption');
    const customerId = document.getElementById('kioskCustomerId');
    const customerSelection = document.getElementById('kioskCustomerSelection');
    const paymentTiming = document.getElementById('kioskPaymentTiming');
    const numberOfLoadsInput = document.getElementById('kioskNumberOfLoads');
    const washQtyHidden = document.getElementById('kioskWashQtyHidden');
    const serviceWeightHidden = document.getElementById('kioskServiceWeightHidden');
    const serviceWeightInput = document.getElementById('kioskServiceWeightInput');
    const weightWrap = document.getElementById('kioskWeightWrap');
    const actualWeightHidden = document.getElementById('kioskActualWeightHidden');
    const actualWeightInput = document.getElementById('kioskActualWeightInput');
    const actualWeightWrap = document.getElementById('kioskActualWeightWrap');
    const excessFeeHint = document.getElementById('kioskExcessFeeHint');
    const foldService = document.getElementById('kioskFoldService');
    const foldServiceQtyHidden = document.getElementById('kioskFoldServiceQty');
    const foldQtyInput = document.getElementById('kioskFoldQtyInput');
    const foldQtyBadge = document.getElementById('kioskFoldQtyBadge');
    const foldQtyWrap = foldQtyInput?.closest('.kiosk-fold-qty-wrap') || null;
    const foldQtyDecBtn = document.getElementById('kioskFoldQtyDecrementBtn');
    const foldQtyIncBtn = document.getElementById('kioskFoldQtyIncrementBtn');
    const inclusionDet = document.getElementById('kioskInclusionDetergent');
    const inclusionFab = document.getElementById('kioskInclusionFabcon');
    const inclusionBleach = document.getElementById('kioskInclusionBleach');
    const inclusionDetQty = document.getElementById('kioskInclusionDetergentQty');
    const inclusionFabQty = document.getElementById('kioskInclusionFabconQty');
    const inclusionBleachQty = document.getElementById('kioskInclusionBleachQty');
    const summary = document.getElementById('kioskSelectionSummary');
    const estimatedTotalAmount = document.getElementById('kioskEstimatedTotalAmount');
    const floatingGrandTotal = document.getElementById('kioskFloatingGrandTotal');
    const syncBluetoothPrintNote = () => {
        if (!bluetoothPrintNote) return;
        bluetoothPrintNote.textContent = kioskEnableBluetoothPrint
            ? 'Bluetooth printing is ON for this transaction. This preference will be saved in Branches.'
            : 'Bluetooth printing is OFF for this transaction. This preference will be saved in Branches.';
    };
    let bluetoothPrefSaving = false;
    let bluetoothPrefSaveSeq = 0;
    const saveBluetoothPrintPreference = async (enabled) => {
        if (!bluetoothPrintToggle || bluetoothPrefSaving) return;
        bluetoothPrefSaving = true;
        const reqSeq = ++bluetoothPrefSaveSeq;
        const previousDisabled = bluetoothPrintToggle.disabled;
        bluetoothPrintToggle.disabled = true;
        try {
            const payload = new FormData();
            payload.set('_token', csrfToken);
            payload.set('enable_bluetooth_print', enabled ? '1' : '0');
            const res = await fetch('<?= e(route('tenant.laundry-sales.bluetooth-printing')) ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: payload,
            });
            const { data } = await parseJsonBody(res);
            const success = !!(res.ok && data && (data.success === true || data.status === 'ok'));
            if (!success) {
                throw new Error((data && typeof data.message === 'string' && data.message.trim() !== '') ? data.message : 'Could not save Bluetooth printing setting.');
            }
            kioskEnableBluetoothPrint = !!enabled;
            syncBluetoothPrintNote();
        } catch (error) {
            if (bluetoothPrintToggle) {
                bluetoothPrintToggle.checked = !enabled;
            }
            kioskEnableBluetoothPrint = !enabled;
            syncBluetoothPrintNote();
            const message = error instanceof Error ? error.message : 'Could not save Bluetooth printing setting.';
            if (typeof Swal !== 'undefined') {
                await Swal.fire({ icon: 'warning', title: 'Setting not saved', text: message, confirmButtonColor: '#dc3545' });
            } else {
                showWarn(message);
            }
        } finally {
            if (reqSeq === bluetoothPrefSaveSeq && bluetoothPrintToggle) {
                bluetoothPrintToggle.disabled = previousDisabled;
            }
            bluetoothPrefSaving = false;
        }
    };
    bluetoothPrintToggle?.addEventListener('change', () => {
        const enabled = !!bluetoothPrintToggle.checked;
        kioskEnableBluetoothPrint = enabled;
        syncBluetoothPrintNote();
        void saveBluetoothPrintPreference(enabled);
    });
    syncBluetoothPrintNote();
    const customerSection = document.getElementById('kioskCustomerSection');
    const customerSearch = document.getElementById('kioskCustomerSearch');
    const customerCards = document.getElementById('kioskCustomerCards');
    const customerNoMatchWrap = document.getElementById('kioskCustomerNoMatchWrap');
    const customerNoMatchText = document.getElementById('kioskCustomerNoMatchText');
    const customerNoMatchCreateBtn = document.getElementById('kioskCustomerNoMatchCreateBtn');
    const createCustomerModalEl = document.getElementById('kioskCreateCustomerModal');
    const createCustomerModal = createCustomerModalEl ? new bootstrap.Modal(createCustomerModalEl) : null;
    const createCustomerForm = document.getElementById('kioskCreateCustomerForm');
    const createCustomerName = document.getElementById('kioskCreateCustomerName');
    const createCustomerSubmitBtn = document.getElementById('kioskCreateCustomerSubmitBtn');
    const form = document.getElementById('staffKioskOrderForm');
    const csrfToken = form?.querySelector('input[name="_token"]')?.value || '';
    const supplyDetCards = document.getElementById('kioskSupplyDetCards');
    const supplyFabCards = document.getElementById('kioskSupplyFabCards');
    const supplyBleachCards = document.getElementById('kioskSupplyBleachCards');
    const supplyDetWrap = document.getElementById('kioskSupplyDetWrap');
    const supplyFabWrap = document.getElementById('kioskSupplyFabWrap');
    const supplyBleachWrap = document.getElementById('kioskSupplyBleachWrap');
    const noSuppliesMessage = document.getElementById('kioskNoSuppliesMessage');
    const addonDetId = document.getElementById('kioskAddonDetergentId');
    const addonFabId = document.getElementById('kioskAddonFabconId');
    const addonBleachId = document.getElementById('kioskAddonBleachId');
    const addonOtherId = document.getElementById('kioskAddonOtherId');
    const addonDetQty = document.getElementById('kioskAddonDetergentQty');
    const addonFabQty = document.getElementById('kioskAddonFabconQty');
    const addonBleachQty = document.getElementById('kioskAddonBleachQty');
    const addonOtherQty = document.getElementById('kioskAddonOtherQty');
    const addonDetQtyInput = document.getElementById('kioskAddonDetQtyInput');
    const addonFabQtyInput = document.getElementById('kioskAddonFabQtyInput');
    const addonBleachQtyInput = document.getElementById('kioskAddonBleachQtyInput');
    const addonOtherQtyInput = document.getElementById('kioskAddonOtherQtyInput');
    const trackGasulHidden = document.getElementById('kioskTrackGasul');
    const trackGasulToggle = document.getElementById('kioskTrackGasulToggle');
    const kioskTrackGasulSettingEnabled = <?= $trackGasulUsage ? 'true' : 'false' ?>;
    const kioskOwnerCanSetTrackGasul = <?= $isTenantAdmin ? 'true' : 'false' ?>;
    const foldNoBtn = document.getElementById('kioskFoldNoBtn');
    const foldYesBtn = document.getElementById('kioskFoldYesBtn');
    const serviceModeSection = document.getElementById('kioskServiceModeSection');
    const orderModeDropOffBtn = document.getElementById('kioskOrderModeDropOff');
    const orderModeSelfServiceBtn = document.getElementById('kioskOrderModeSelfService');
    const modeRegularBtn = document.getElementById('kioskModeRegular');
    const modeFreeBtn = document.getElementById('kioskModeFree');
    const modeRewardBtn = document.getElementById('kioskModeReward');
    const rewardStatus = document.getElementById('kioskRewardStatus');

    const esc = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const showWarn = (message) => {
        const text = String(message || 'Please review your input.');
        if (typeof Swal !== 'undefined') {
            return Swal.fire({
                icon: 'warning',
                title: 'Action needed',
                text,
                confirmButtonText: 'OK',
            });
        }
        window.mpgAlert(text, { title: 'Action needed', icon: 'warning' });
        return Promise.resolve();
    };
    const listHtml = (items) => {
        if (!Array.isArray(items) || items.length === 0) return 'None';
        return `<ul>${items.map((x) => `<li>• ${esc(x)}</li>`).join('')}</ul>`;
    };
    const parseJsonBody = async (res) => {
        const raw = await res.text();
        let data = {};
        if (raw) {
            try {
                data = JSON.parse(raw);
            } catch (_) {
                data = {};
            }
        }
        return { data };
    };

    let selectedOrderLabel = '';
    let selectedServiceKind = '';
    let selectedSupplyBlock = 'none';
    let selectedDetergentQty = 0;
    let selectedFabconQty = 0;
    let selectedBleachQty = 0;
    let selectedShowAddonSupplies = false;
    let selectedRequiredWeight = false;
    let selectedPricePerLoad = 0;
    let selectedFoldServiceAmount = 0;
    let selectedMaxWeightKg = 0;
    let selectedExcessWeightFeePerKg = 0;
    let selectedCustomerLabel = 'No customer selected';
    let selectedPayNowMethod = 'cash';
    let isSubmittingOrder = false;
    let rewardPromptOpen = false;
    let rewardIntentCustomerId = '';
    const selfServiceOrderQuantities = new Map();
    const resolveOrderTypeBasePrice = (code, basePrice, foldServiceAmount) => {
        const normalizedCode = String(code || '').trim().toLowerCase();
        if (normalizedCode === 'fold_with_price') {
            return Math.max(0, Number(foldServiceAmount || 0));
        }
        return Math.max(0, Number(basePrice || 0));
    };
    const effectivePricePerLoad = (code, serviceKind, basePrice, foldServiceAmount = 0) => {
        const normalizedCode = String(code || '').trim().toLowerCase();
        if (normalizedCode === 'free_fold') {
            return 0;
        }
        return resolveOrderTypeBasePrice(code, basePrice, foldServiceAmount);
    };
    const syncOrderTypePriceLabels = () => {
        document.querySelectorAll('.kiosk-order-type-btn').forEach((btn) => {
            const code = String(btn.dataset.code || '');
            const serviceKind = String(btn.dataset.serviceKind || '');
            const basePrice = Math.max(0, parseFloat(btn.dataset.pricePerLoad || '0') || 0);
            const foldServiceAmount = Math.max(0, parseFloat(btn.dataset.foldServiceAmount || '0') || 0);
            const priceLabel = btn.querySelector('.kiosk-order-type-price');
            if (!priceLabel) return;
            priceLabel.textContent = `Price: ${formatPeso(effectivePricePerLoad(code, serviceKind, basePrice, foldServiceAmount))}`;
        });
    };
    const syncFoldOnlyPriceOverrides = () => {
        selfServiceOrderQuantities.forEach((entry, code) => {
            const basePrice = Math.max(0, Number(entry?.basePricePerLoad ?? entry?.pricePerLoad ?? 0));
            const serviceKind = String(entry?.serviceKind || '');
            entry.basePricePerLoad = basePrice;
            entry.pricePerLoad = effectivePricePerLoad(code, serviceKind, basePrice, Number(entry?.foldServiceAmount || 0));
            selfServiceOrderQuantities.set(code, entry);
        });
        const selectedCode = String(orderTypeCode?.value || '');
        const selectedBtn = selectedCode !== '' ? document.querySelector(`.kiosk-order-type-btn[data-code="${selectedCode}"]`) : null;
        const baseSelected = Math.max(0, parseFloat(selectedBtn?.getAttribute('data-price-per-load') || String(selectedPricePerLoad || 0)) || 0);
        const selectedFoldAmount = Math.max(0, parseFloat(selectedBtn?.getAttribute('data-fold-service-amount') || String(selectedFoldServiceAmount || 0)) || 0);
        selectedPricePerLoad = effectivePricePerLoad(selectedCode, selectedServiceKind, baseSelected, selectedFoldAmount);
        syncOrderTypePriceLabels();
    };
    const rewardThreshold = parseFloat(modeRewardBtn?.getAttribute('data-reward-threshold') || '0') || 0;
    const rewardOrderTypeCode = modeRewardBtn?.getAttribute('data-reward-order-type') || '';
    const pointsPerDropoffLoad = parseFloat(form?.getAttribute('data-reward-points-per-load') || '0') || 0;

    const selectedRewardBalance = () => {
        const id = customerId.value || '';
        if (!id) return 0;
        const btn = document.querySelector(`.kiosk-customer-btn[data-id="${id}"]`);
        return parseFloat(btn?.getAttribute('data-rewards-balance') || '0') || 0;
    };
    const hasExplicitCustomerSelection = () => {
        const customerMode = (customerSelection?.value || '').trim();
        return customerMode === 'saved' || customerMode === 'walk_in';
    };
    const isSelfServiceMode = () => (orderModeInput?.value || 'drop_off') === 'self_service';
    const normalizeShowInOrderMode = (raw) => {
        const v = String(raw || '').trim().toLowerCase();
        return (v === 'drop_off' || v === 'self_service' || v === 'both') ? v : 'both';
    };
    const isOrderTypeVisibleForMode = (btn, mode) => {
        const showMode = normalizeShowInOrderMode(btn?.getAttribute('data-show-in-order-mode') || 'both');
        return showMode === 'both' || showMode === mode;
    };
    const syncOrderTypeVisibilityByMode = () => {
        const currentMode = isSelfServiceMode() ? 'self_service' : 'drop_off';
        const selectedCode = String(orderTypeCode?.value || '').trim();
        let hasVisibleSelected = false;
        document.querySelectorAll('.kiosk-order-type-btn').forEach((btn) => {
            const visible = isOrderTypeVisibleForMode(btn, currentMode);
            const wrap = btn.closest('.kiosk-order-type-wrap');
            wrap?.classList.toggle('d-none', !visible);
            if (!visible) {
                const code = String(btn.getAttribute('data-code') || '').trim();
                selfServiceOrderQuantities.delete(code);
            }
            if (visible && selectedCode !== '' && String(btn.getAttribute('data-code') || '').trim() === selectedCode) {
                hasVisibleSelected = true;
            }
        });
        if (!hasVisibleSelected) {
            if (orderTypeCode) orderTypeCode.value = '';
            selectedOrderLabel = '';
            selectedServiceKind = 'full_service';
            selectedSupplyBlock = defaultSupplyBlockForKind(selectedServiceKind);
            selectedDetergentQty = 0;
            selectedFabconQty = 0;
            selectedBleachQty = 0;
            selectedShowAddonSupplies = defaultShowAddonForKind(selectedServiceKind);
            selectedRequiredWeight = false;
            selectedPricePerLoad = 0;
        }
    };
    const getSelectedFoldServiceAmount = () => Math.max(0, Number(selectedFoldServiceAmount || 0));
    const warnCustomerRequired = () => {
        const focusTarget = customerSearch || customerCards?.querySelector('.kiosk-customer-btn') || null;
        const customerSection = customerSearch?.closest('.card') || customerCards?.closest('.card') || focusTarget;
        const refocusCustomerArea = () => {
            if (customerSection && typeof customerSection.scrollIntoView === 'function') {
                customerSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            try {
                focusTarget?.focus?.({ preventScroll: true });
            } catch (_) {
                focusTarget?.focus?.();
            }
        };
        refocusCustomerArea();
        void showWarn('Customer is required. Select a customer or choose Walk-in customer first.')
            .then(() => {
                // After popup closes, force focus back to customer area
                // so viewport does not jump back to submit buttons.
                refocusCustomerArea();
            });
    };
    const warnAndFocus = (message, focusTarget, sectionTarget = null) => {
        const refocus = () => {
            const section = sectionTarget || focusTarget;
            if (section && typeof section.scrollIntoView === 'function') {
                section.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            try {
                focusTarget?.focus?.({ preventScroll: true });
            } catch (_) {
                focusTarget?.focus?.();
            }
        };
        refocus();
        void showWarn(message).then(() => refocus());
    };
    const defaultSupplyBlockForKind = (kind) => {
        switch (String(kind || '')) {
            case 'full_service':
                return 'full_service';
            case 'wash_only':
                return 'wash_supplies';
            case 'rinse_only':
                return 'rinse_supplies';
            default:
                return 'none';
        }
    };
    const defaultShowAddonForKind = (kind) => {
        return String(kind || '') === 'full_service' || String(kind || '') === 'wash_only';
    };
    const stackedInclusionQtyForKind = (kind) => {
        const target = String(kind || '');
        if (selfServiceOrderQuantities.size < 1) return 0;
        return Array.from(selfServiceOrderQuantities.values()).reduce((sum, entry) => {
            const qty = Math.max(0, Number(entry.qty || 0));
            if (qty <= 0) return sum;
            if (target === 'det') return sum + (qty * Math.max(0, Number(entry.detergentQty || 0)));
            if (target === 'fab') return sum + (qty * Math.max(0, Number(entry.fabconQty || 0)));
            if (target === 'bleach') return sum + (qty * Math.max(0, Number(entry.bleachQty || 0)));
            return sum;
        }, 0);
    };
    const dropOffHasStackedOrderTypes = () => !isSelfServiceMode() && selfServiceOrderQuantities.size > 0;
    const dropOffHasMultipleOrderTypes = () => {
        if (isSelfServiceMode()) return false;
        let activeCount = 0;
        selfServiceOrderQuantities.forEach((entry) => {
            const qty = Math.max(0, Number(entry?.qty || 0));
            if (qty > 0) activeCount += 1;
        });
        return activeCount > 1;
    };
    const inclusionQtyForKind = (kind) => {
        const inclusionKind = String(kind || '');
        if (isSelfServiceMode()) return 0;
        if (inclusionKind === 'det' && (inclusionDet?.value || '') !== '') {
            const manual = Math.max(0, parseFloat(inclusionDetQty?.value || '0') || 0);
            if (manual > 0) return manual;
        }
        if (inclusionKind === 'fab' && (inclusionFab?.value || '') !== '') {
            const manual = Math.max(0, parseFloat(inclusionFabQty?.value || '0') || 0);
            if (manual > 0) return manual;
        }
        if (inclusionKind === 'bleach' && (inclusionBleach?.value || '') !== '') {
            const manual = Math.max(0, parseFloat(inclusionBleachQty?.value || '0') || 0);
            if (manual > 0) return manual;
        }
        if (dropOffHasStackedOrderTypes()) {
            return stackedInclusionQtyForKind(inclusionKind);
        }
        if (inclusionKind === 'det') return Math.max(0, Number(selectedDetergentQty || 0));
        if (inclusionKind === 'fab') return Math.max(0, Number(selectedFabconQty || 0));
        if (inclusionKind === 'bleach') return Math.max(0, Number(selectedBleachQty || 0));
        return 0;
    };
    const selectedSupplyBtnByKind = (kind) => {
        const target = String(kind || '');
        if (target === 'det' && inclusionDet?.value) return document.querySelector(`.kiosk-supply-det-btn[data-id="${inclusionDet.value}"]`);
        if (target === 'fab' && inclusionFab?.value) return document.querySelector(`.kiosk-supply-fab-btn[data-id="${inclusionFab.value}"]`);
        if (target === 'bleach' && inclusionBleach?.value) return document.querySelector(`.kiosk-supply-bleach-btn[data-id="${inclusionBleach.value}"]`);
        return null;
    };
    const getSupplyStockByKind = (kind) => {
        const btn = selectedSupplyBtnByKind(kind);
        return Math.max(0, parseFloat(btn?.getAttribute('data-stock-qty') || '0') || 0);
    };
    const setInclusionQtyForKind = (kind, qty) => {
        const v = Math.max(0, Number(qty || 0));
        if (String(kind) === 'det' && inclusionDetQty) inclusionDetQty.value = String(v);
        if (String(kind) === 'fab' && inclusionFabQty) inclusionFabQty.value = String(v);
        if (String(kind) === 'bleach' && inclusionBleachQty) inclusionBleachQty.value = String(v);
    };
    const getOrderTypeIncreaseBlockReason = (entryLike) => {
        if (isSelfServiceMode()) return '';
        const detDelta = Math.max(0, Number(entryLike?.detergentQty || 0));
        const fabDelta = Math.max(0, Number(entryLike?.fabconQty || 0));
        const bleachDelta = Math.max(0, Number(entryLike?.bleachQty || 0));
        const checks = [
            { kind: 'det', name: 'Detergent', delta: detDelta },
            { kind: 'fab', name: 'Fabric conditioner', delta: fabDelta },
            { kind: 'bleach', name: 'Bleach', delta: bleachDelta },
        ];
        for (const c of checks) {
            if (c.delta <= 0) continue;
            const selectedBtn = selectedSupplyBtnByKind(c.kind);
            if (!selectedBtn) continue;
            const stock = getSupplyStockByKind(c.kind);
            const currentNeed = inclusionQtyForKind(c.kind);
            if (currentNeed + c.delta > stock + 1e-9) {
                return `${c.name} stock is not enough for another order quantity.`;
            }
        }
        return '';
    };
    const syncInclusionQtyBadges = () => {
        const selfMode = isSelfServiceMode();
        const sync = (selector, selectedId, kind) => {
            const qty = inclusionQtyForKind(kind);
            document.querySelectorAll(selector).forEach((btn) => {
                const badge = btn.querySelector('.kiosk-supply-qty-badge');
                const isSelected = selectedId !== '' && String(btn.getAttribute('data-id') || '') === selectedId;
                const shouldHighlight = !selfMode && isSelected;
                btn.classList.toggle('kiosk-card-selected', shouldHighlight);
                if (shouldHighlight) {
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
                if (!badge) return;
                const shouldShow = !selfMode && isSelected && qty > 0;
                badge.textContent = `Qty: ${qty}`;
                badge.classList.toggle('d-none', !shouldShow);
            });
        };
        sync('.kiosk-supply-det-btn', String(inclusionDet.value || ''), 'det');
        sync('.kiosk-supply-fab-btn', String(inclusionFab.value || ''), 'fab');
        sync('.kiosk-supply-bleach-btn', String(inclusionBleach.value || ''), 'bleach');
    };
    const syncPersistentSelectionHighlights = () => {
        const selfMode = isSelfServiceMode();
        const applyHighlight = (selector, selectedId, selectedQty = 1, allowInSelfService = true) => {
            const normalizedId = String(selectedId || '');
            const qty = Math.max(0, Number(selectedQty || 0));
            const shouldAllow = allowInSelfService || !selfMode;
            document.querySelectorAll(selector).forEach((btn) => {
                const isSelected = normalizedId !== '' && String(btn.getAttribute('data-id') || '') === normalizedId;
                const shouldHighlight = shouldAllow && isSelected && qty > 0;
                btn.classList.toggle('kiosk-card-selected', shouldHighlight);
                if (shouldHighlight) {
                    btn.classList.remove('btn-outline-primary');
                    btn.classList.add('btn-primary');
                } else {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                }
            });
        };

        applyHighlight('.kiosk-supply-det-btn', inclusionDet?.value || '', inclusionQtyForKind('det'), false);
        applyHighlight('.kiosk-supply-fab-btn', inclusionFab?.value || '', inclusionQtyForKind('fab'), false);
        applyHighlight('.kiosk-supply-bleach-btn', inclusionBleach?.value || '', inclusionQtyForKind('bleach'), false);
        applyHighlight('.kiosk-addon-det-btn', addonDetId?.value || '', parseFloat(addonDetQtyInput?.value || '0') || 0, true);
        applyHighlight('.kiosk-addon-fab-btn', addonFabId?.value || '', parseFloat(addonFabQtyInput?.value || '0') || 0, true);
        applyHighlight('.kiosk-addon-bleach-btn', addonBleachId?.value || '', parseFloat(addonBleachQtyInput?.value || '0') || 0, true);
        applyHighlight('.kiosk-addon-other-btn', addonOtherId?.value || '', parseFloat(addonOtherQtyInput?.value || '0') || 0, true);
    };

    const selectedOrderTypeEarnsRewards = () => {
        const btn = document.querySelector('.kiosk-order-type-btn.kiosk-card-selected');
        if (rewardOrderTypeCode) {
            if (!btn) {
                return String(orderTypeCode?.value || '').trim() === rewardOrderTypeCode;
            }
            return (btn.dataset.code || '').trim() === rewardOrderTypeCode;
        }
        if (!btn) {
            return String(selectedServiceKind || '') === 'full_service';
        }
        const inc = (btn.getAttribute('data-include-in-rewards') || '').trim();
        if (inc === '1') return true;
        if (inc === '0') return false;
        return (btn.dataset.serviceKind || '') === 'full_service';
    };

    const kioskLoadsForRewardPreview = () => {
        const stackedLoads = Array.from(selfServiceOrderQuantities.values()).reduce((sum, entry) => {
            return sum + Math.max(0, Number(entry.qty || 0));
        }, 0);
        if (stackedLoads > 0) return Math.max(1, stackedLoads);
        return Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);
    };

    const rewardEligibleLoadsForPreview = () => {
        if (rewardOrderTypeCode) {
            if (selfServiceOrderQuantities.size > 0) {
                const qty = Array.from(selfServiceOrderQuantities.values()).reduce((sum, entry) => {
                    if ((entry.code || '') !== rewardOrderTypeCode) return sum;
                    return sum + Math.max(0, Number(entry.qty || 0));
                }, 0);
                return qty > 0 ? Math.max(1, qty) : 0;
            }
            const selectedCode = String(orderTypeCode?.value || '').trim();
            if (selectedCode !== rewardOrderTypeCode) {
                return 0;
            }
        } else if (!selectedOrderTypeEarnsRewards()) {
            return 0;
        }

        return kioskLoadsForRewardPreview();
    };

    const pendingRewardEarnForPreview = () => {
        if (serviceMode.value === 'free' || serviceMode.value === 'reward') return 0;
        const loads = rewardEligibleLoadsForPreview();
        if (loads <= 0) return 0;
        const mult = pointsPerDropoffLoad > 0 ? pointsPerDropoffLoad : 1;
        return loads * mult;
    };

    const projectedRewardBalance = () => selectedRewardBalance() + pendingRewardEarnForPreview();

    const isRewardAvailable = () => {
        const balance = selectedRewardBalance();
        return rewardThreshold > 0 && rewardOrderTypeCode !== '' && balance + 1e-9 >= rewardThreshold;
    };

    const clearRewardRedemption = () => {
        if (rewardRedemption) rewardRedemption.value = '0';
        rewardIntentCustomerId = '';
    };

    const syncRewardMode = () => {
        if (isSelfServiceMode()) {
            if (modeRewardBtn) {
                modeRewardBtn.disabled = true;
                modeRewardBtn.classList.add('disabled');
            }
            if (modeFreeBtn) {
                modeFreeBtn.disabled = true;
                modeFreeBtn.classList.add('disabled');
            }
            if (rewardStatus) {
                rewardStatus.textContent = 'Self Service uses Regular mode only.';
            }
            syncSubmitButtonsByMode();
            return;
        }
        const balance = selectedRewardBalance();
        const available = isRewardAvailable();
        if (modeRewardBtn) {
            modeRewardBtn.disabled = !available;
            modeRewardBtn.classList.toggle('disabled', !available);
        }
        if (!available && rewardRedemption?.value === '1') {
            clearRewardRedemption();
        }
        if (!available && serviceMode.value === 'reward') {
            serviceMode.value = 'regular';
            setButtonState('.kiosk-service-mode-btn', modeRegularBtn, 'btn-primary', 'btn-outline-secondary');
        }
        if (rewardStatus) {
            if (!customerId.value) {
                rewardStatus.textContent = 'Select a saved customer to check reward availability.';
            } else if (rewardRedemption?.value === '1') {
                rewardStatus.textContent = `Reward will be redeemed on save (${balance.toFixed(2)} count).`;
            } else if (available) {
                rewardStatus.textContent = `Reward available (${balance.toFixed(2)} count).`;
            } else {
                const projected = projectedRewardBalance();
                const hasProjectedGain = projected > balance + 1e-9;
                rewardStatus.textContent = hasProjectedGain
                    ? `No reward available yet (current: ${balance.toFixed(2)} / ${rewardThreshold.toFixed(2)}). After this paid order: ${projected.toFixed(2)} / ${rewardThreshold.toFixed(2)}.`
                    : `No reward available yet (current: ${balance.toFixed(2)} / ${rewardThreshold.toFixed(2)}).`;
            }
        }
        syncSubmitButtonsByMode();
    };

    const applyRewardRedemption = () => {
        if (!isRewardAvailable()) {
            showWarn('This customer does not have an available reward yet.');
            return;
        }
        if (rewardRedemption) rewardRedemption.value = '1';
        rewardIntentCustomerId = customerId.value || '';
        serviceMode.value = 'reward';
        setButtonState('.kiosk-service-mode-btn', modeRewardBtn, 'btn-primary', 'btn-outline-secondary');
        if (rewardOrderTypeCode) {
            const rewardOrderBtn = document.querySelector(`.kiosk-order-type-btn[data-code="${rewardOrderTypeCode}"]`);
            rewardOrderBtn?.click();
        }
        syncRewardMode();
        syncSubmitButtonsByMode();
        computeSupplyStep();
        renderStep();
    };

    const maybePromptReward = (customerBtn) => {
        const pickedCustomerId = customerId.value || '';
        if (!pickedCustomerId || !isRewardAvailable() || rewardRedemption?.value === '1') return;
        if (rewardPromptOpen) return;
        rewardPromptOpen = true;
        const customerName = (customerBtn?.textContent || selectedCustomerLabel || 'Customer').trim();
        const message = `${customerName} is eligible to get the reward.`;
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Reward available',
                text: message,
                showCancelButton: true,
                confirmButtonText: 'Use Reward',
                cancelButtonText: 'Not now',
                confirmButtonColor: '#198754',
            }).then((result) => {
                rewardPromptOpen = false;
                if (result.isConfirmed && customerId.value === pickedCustomerId) {
                    applyRewardRedemption();
                }
            });
            return;
        }
        const useReward = window.confirm(`${message}\n\nUse Reward?`);
        rewardPromptOpen = false;
        if (useReward && customerId.value === pickedCustomerId) {
            applyRewardRedemption();
        }
    };
    const syncSelfServiceOrderQtyUI = () => {
        const lines = [];
        let firstCode = '';
        let firstLabel = '';
        let totalLoads = 0;
        document.querySelectorAll('.kiosk-order-type-btn').forEach((btn) => {
            const code = String(btn.getAttribute('data-code') || '').trim();
            const qtyInfo = btn.querySelector('.kiosk-order-type-qty');
            const wrap = btn.closest('.kiosk-order-type-wrap');
            const adjustWrap = wrap?.querySelector('.kiosk-order-type-adjust');
            const decrementBtn = wrap?.querySelector('.kiosk-order-type-adjust-btn[data-action="decrement"]');
            const entry = selfServiceOrderQuantities.get(code);
            const qty = Math.max(0, Number(entry?.qty || 0));
            btn.classList.toggle('kiosk-card-selected', qty > 0);
            if (qtyInfo) {
                qtyInfo.classList.toggle('d-none', qty <= 0);
                qtyInfo.setAttribute('data-qty', String(qty));
                qtyInfo.textContent = `Qty: ${qty}`;
            }
            if (adjustWrap) {
                adjustWrap.classList.remove('d-none');
            }
            if (decrementBtn instanceof HTMLButtonElement) {
                decrementBtn.disabled = qty <= 0;
            }
            if (qty > 0) {
                if (firstCode === '') {
                    firstCode = code;
                    firstLabel = entry?.label || btn.textContent?.trim() || '';
                }
                totalLoads += qty;
                lines.push({
                    code,
                    label: entry?.label || btn.textContent?.trim() || code,
                    service_kind: entry?.serviceKind || btn.getAttribute('data-service-kind') || 'full_service',
                    quantity: qty,
                    price_per_load: effectivePricePerLoad(
                        code,
                        entry?.serviceKind || btn.getAttribute('data-service-kind') || 'full_service',
                        Number(entry?.basePricePerLoad || entry?.pricePerLoad || btn.getAttribute('data-price-per-load') || 0),
                        Number(entry?.foldServiceAmount || btn.getAttribute('data-fold-service-amount') || 0)
                    ),
                });
            }
        });
        if (selfServiceLinesInput) {
            selfServiceLinesInput.value = lines.length > 0 ? JSON.stringify(lines) : '';
        }
        if (orderTypeCode) {
            orderTypeCode.value = firstCode;
        }
        if (washQtyHidden) {
            washQtyHidden.value = String(Math.max(1, totalLoads || 1));
        }
        if (numberOfLoadsInput) {
            numberOfLoadsInput.value = String(Math.max(1, totalLoads || 1));
        }
        selectedOrderLabel = firstLabel;
    };
    const applyOrderModeUI = () => {
        const selfMode = isSelfServiceMode();
        document.querySelectorAll('.kiosk-dropoff-only').forEach((el) => {
            el.classList.toggle('d-none', selfMode);
        });
        document.querySelectorAll('.self-service-only').forEach((el) => {
            el.classList.toggle('d-none', !selfMode);
        });
        document.querySelectorAll('.kiosk-order-type-adjust').forEach((el) => {
            el.classList.remove('d-none');
        });
        customerSection?.classList.remove('d-none');
        serviceModeSection?.classList.toggle('opacity-75', selfMode);
        if (selfMode) {
            serviceMode.value = 'regular';
            clearRewardRedemption();
            modeFreeBtn?.setAttribute('disabled', 'disabled');
            modeRewardBtn?.setAttribute('disabled', 'disabled');
            modeFreeBtn?.classList.add('disabled');
            modeRewardBtn?.classList.add('disabled');
            setButtonState('.kiosk-service-mode-btn', modeRegularBtn, 'btn-primary', 'btn-outline-secondary');
            if (customerSelection && customerSelection.value === '') customerSelection.value = 'walk_in';
        } else {
            modeFreeBtn?.removeAttribute('disabled');
            modeRewardBtn?.removeAttribute('disabled');
            modeFreeBtn?.classList.remove('disabled');
            modeRewardBtn?.classList.remove('disabled');
            if (customerSelection && customerSelection.value === '') customerSelection.value = 'walk_in';
            if (foldQtyInput) foldQtyInput.value = '';
            if (foldServiceQtyHidden) foldServiceQtyHidden.value = '0';
        }
        recomputeVisibleSteps();
        syncSubmitButtonsByMode();
        syncFoldQtyVisibility();
    };
    const syncFoldQtyVisibility = () => {
        const foldEnabled = (foldService?.value || '0') === '1';
        if (foldQtyWrap) {
            foldQtyWrap.classList.toggle('d-none', !foldEnabled);
        }
        if (foldQtyBadge) {
            foldQtyBadge.classList.toggle('d-none', !foldEnabled);
        }
        if (!foldEnabled && foldQtyInput) {
            foldQtyInput.value = '0';
        }
    };
    const syncAddonQtyBadge = (selector, selectedId, qty) => {
        document.querySelectorAll(selector).forEach((btn) => {
            const badge = btn.querySelector('.kiosk-addon-qty-badge');
            const isSelected = selectedId && btn.getAttribute('data-id') === selectedId;
            btn.classList.toggle('kiosk-card-selected', !!isSelected);
            if (isSelected) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
            if (!badge) return;
            const shouldShow = isSelected && qty > 0;
            badge.textContent = `Qty: ${qty}`;
            badge.classList.toggle('d-none', !shouldShow);
        });
    };
    const switchOrderMode = (mode) => {
        const normalized = mode === 'self_service' ? 'self_service' : 'drop_off';
        if (orderModeInput) orderModeInput.value = normalized;
        if (normalized !== 'drop_off') {
            clearRewardRedemption();
        }
        setButtonState('.kiosk-order-mode-btn', normalized === 'self_service' ? orderModeSelfServiceBtn : orderModeDropOffBtn, 'btn-primary', 'btn-outline-secondary');
        applyOrderModeUI();
        syncOrderTypeVisibilityByMode();
        syncSelfServiceOrderQtyUI();
        updateSummary();
    };

    const recomputeVisibleSteps = () => {
        steps.forEach((el) => {
            const step = String(el.getAttribute('data-step') || '').trim();
            if (isSelfServiceMode() && step === '2') {
                el.classList.add('d-none');
                return;
            }
            if (step === '2') {
                const showServiceInclusion = inclusionQtyForKind('det') > 0 || inclusionQtyForKind('fab') > 0 || inclusionQtyForKind('bleach') > 0;
                el.classList.toggle('d-none', !showServiceInclusion);
                return;
            }
            if (step === '3') {
                // Show Add-ons section only when selected order type enables it.
                const showAddons = isSelfServiceMode()
                    ? true
                    : (dropOffHasStackedOrderTypes()
                        ? Array.from(selfServiceOrderQuantities.values()).some((entry) => !!entry.showAddonSupplies)
                        : !!selectedShowAddonSupplies);
                el.classList.toggle('d-none', !showAddons);
                if (!showAddons) {
                    addonDetId.value = '';
                    addonFabId.value = '';
                    addonBleachId.value = '';
                    addonOtherId.value = '';
                    addonDetQty.value = '0';
                    addonFabQty.value = '0';
                    addonBleachQty.value = '0';
                    addonOtherQty.value = '0';
                    if (addonDetQtyInput) addonDetQtyInput.value = '';
                    if (addonFabQtyInput) addonFabQtyInput.value = '';
                    if (addonBleachQtyInput) addonBleachQtyInput.value = '';
                    if (addonOtherQtyInput) addonOtherQtyInput.value = '';
                    setButtonState('.kiosk-addon-det-btn', null);
                    setButtonState('.kiosk-addon-fab-btn', null);
                    setButtonState('.kiosk-addon-bleach-btn', null);
                    setButtonState('.kiosk-addon-other-btn', null);
                }
                return;
            }
            el.classList.remove('d-none');
        });
    };

    const computeMachineNeed = () => {
        const useMachines = document.getElementById('kioskUseMachines');
        if (useMachines) useMachines.value = '0';
        recomputeVisibleSteps();
    };

    const syncInventorySelectionAvailability = () => {
        const toNum = (raw) => Math.max(0, parseFloat(String(raw || '0')) || 0);
        const toStock = (btn) => toNum(btn?.dataset?.stockQty || '0');
        const fmtQty = (raw) => {
            const n = toNum(raw);
            if (Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n));
            return String(n.toFixed(2)).replace(/\.?0+$/, '');
        };
        const syncCardStockText = (btn, remaining) => {
            const indicator = btn?.querySelector?.('.kiosk-item-stock');
            if (!indicator) return;
            const safeRemaining = Math.max(0, remaining);
            const threshold = toNum(btn?.dataset?.lowStockThreshold || '0');
            const isLow = threshold > 0 && safeRemaining <= threshold + 1e-9;
            if (isLow) {
                indicator.textContent = `Low stock: ${fmtQty(safeRemaining)} remaining`;
                indicator.classList.remove('text-muted');
                indicator.classList.add('text-danger', 'fw-semibold');
                return;
            }
            indicator.textContent = `Stock: ${fmtQty(safeRemaining)}`;
            indicator.classList.remove('text-danger', 'fw-semibold');
            indicator.classList.add('text-muted');
        };
        const byId = (selector) => {
            const map = new Map();
            document.querySelectorAll(selector).forEach((btn) => {
                const id = String(btn.getAttribute('data-id') || '');
                if (id !== '' && !map.has(id)) map.set(id, btn);
            });
            return map;
        };

        const syncCategory = (supplySelector, addonSelector, inclusionInput, addonInput, addonQtyInputEl, inclusionKind) => {
            const supplyBtns = Array.from(document.querySelectorAll(supplySelector));
            const addonBtns = Array.from(document.querySelectorAll(addonSelector));
            const supplyById = byId(supplySelector);
            const addonById = byId(addonSelector);
            const allIds = new Set([...supplyById.keys(), ...addonById.keys()]);

            const selectedInclusionId = String(inclusionInput?.value || '');
            const selectedAddonId = String(addonInput?.value || '');
            let selectedAddonQty = selectedAddonId !== '' ? toNum(addonQtyInputEl?.value || '0') : 0;

            if (selectedAddonId !== '') {
                const basisBtn = supplyById.get(selectedAddonId) || addonById.get(selectedAddonId) || null;
                const stock = toStock(basisBtn);
                const inclusionReserved = selectedInclusionId === selectedAddonId ? inclusionQtyForKind(inclusionKind) : 0;
                const maxAddonQty = Math.max(0, stock - inclusionReserved);
                if (selectedAddonQty > maxAddonQty + 1e-9) {
                    selectedAddonQty = maxAddonQty;
                    if (addonQtyInputEl) {
                        addonQtyInputEl.value = selectedAddonQty > 0 ? String(selectedAddonQty) : '';
                    }
                    if (selectedAddonQty <= 0) {
                        if (addonInput) addonInput.value = '';
                        setButtonState(addonSelector, null);
                    }
                }
            }

            allIds.forEach((id) => {
                const basisBtn = supplyById.get(id) || addonById.get(id) || null;
                const stock = toStock(basisBtn);
                const inclusionReserved = selectedInclusionId === id ? inclusionQtyForKind(inclusionKind) : 0;
                const addonReserved = selectedAddonId === id ? selectedAddonQty : 0;

                const supplyRemainingIfPicked = stock - addonReserved;
                const addonRemainingIfPicked = stock - inclusionReserved;

                supplyBtns.forEach((btn) => {
                    if (String(btn.getAttribute('data-id') || '') !== id) return;
                    const isSelected = String(inclusionInput?.value || '') === id;
                    const shouldDisable = !isSelected && supplyRemainingIfPicked <= 1e-9;
                    btn.disabled = shouldDisable;
                    btn.classList.toggle('disabled', shouldDisable);
                    syncCardStockText(btn, supplyRemainingIfPicked);
                });
                addonBtns.forEach((btn) => {
                    if (String(btn.getAttribute('data-id') || '') !== id) return;
                    const isSelected = String(addonInput?.value || '') === id;
                    const shouldDisable = !isSelected && addonRemainingIfPicked <= 1e-9;
                    btn.disabled = shouldDisable;
                    btn.classList.toggle('disabled', shouldDisable);
                    syncCardStockText(btn, addonRemainingIfPicked);
                });
            });
        };

        syncCategory('.kiosk-supply-det-btn', '.kiosk-addon-det-btn', inclusionDet, addonDetId, addonDetQtyInput, 'det');
        syncCategory('.kiosk-supply-fab-btn', '.kiosk-addon-fab-btn', inclusionFab, addonFabId, addonFabQtyInput, 'fab');
        syncCategory('.kiosk-supply-bleach-btn', '.kiosk-addon-bleach-btn', inclusionBleach, addonBleachId, addonBleachQtyInput, 'bleach');
        syncCategory('.kiosk-addon-other-btn', '.kiosk-addon-other-btn', { value: '' }, addonOtherId, addonOtherQtyInput, 'other');
    };

    const getCurrentTotals = () => {
        const selfMode = isSelfServiceMode();
        const rewardMode = serviceMode.value === 'reward';
        const rewardServiceCode = String(rewardOrderTypeCode || '').trim();
        const stackedLoads = Array.from(selfServiceOrderQuantities.values()).reduce((sum, entry) => {
            return sum + Math.max(0, Number(entry.qty || 0));
        }, 0);
        const hasStackedLoads = stackedLoads > 0;
        const allowAddonSupplies = selfMode
            ? true
            : (hasStackedLoads
                ? Array.from(selfServiceOrderQuantities.values()).some((entry) => !!entry.showAddonSupplies)
                : !!selectedShowAddonSupplies);
        const loadsLabel = hasStackedLoads
            ? Math.max(1, stackedLoads)
            : Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);
        const foldQty = selfMode
            ? Math.max(0, parseInt(foldQtyInput?.value || '0', 10) || 0)
            : ((foldService?.value || '0') === '1' ? Math.max(1, loadsLabel) : 0);
        const foldTotal = foldQty * getSelectedFoldServiceAmount();
        const weightValue = Math.max(0, parseFloat(serviceWeightInput?.value || '0') || 0);
        const actualWeightValue = Math.max(0, parseFloat(actualWeightInput?.value || '0') || 0);
        const addonDetBtn = document.querySelector(`.kiosk-addon-det-btn[data-id="${addonDetId.value}"]`);
        const addonFabBtn = document.querySelector(`.kiosk-addon-fab-btn[data-id="${addonFabId.value}"]`);
        const addonBleachBtn = document.querySelector(`.kiosk-addon-bleach-btn[data-id="${addonBleachId.value}"]`);
        const addonOtherBtn = document.querySelector(`.kiosk-addon-other-btn[data-id="${addonOtherId.value}"]`);
        const addOnTotal = (serviceMode.value === 'free' || !allowAddonSupplies)
            ? 0
            : (
                (parseFloat(addonDetQty.value || '0') || 0) * (parseFloat(addonDetBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonFabQty.value || '0') || 0) * (parseFloat(addonFabBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonBleachQty.value || '0') || 0) * (parseFloat(addonBleachBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonOtherQty.value || '0') || 0) * (parseFloat(addonOtherBtn?.getAttribute('data-unit-cost') || '0') || 0)
            );
        const stackedSubtotal = hasStackedLoads
            ? Array.from(selfServiceOrderQuantities.values()).reduce((sum, entry) => {
                const qty = Math.max(0, Number(entry.qty || 0));
                const pricePerLoad = effectivePricePerLoad(
                    entry.code || '',
                    entry.serviceKind || '',
                    Number(entry.basePricePerLoad || entry.pricePerLoad || 0),
                    Number(entry.foldServiceAmount || 0)
                );
                if (rewardMode && rewardServiceCode !== '' && String(entry.code || '').trim() === rewardServiceCode) {
                    return sum;
                }
                return sum + (qty * pricePerLoad);
            }, 0)
            : 0;
        const selectedCode = String(orderTypeCode?.value || '').trim();
        const selectedIsRewardService = rewardMode && rewardServiceCode !== '' && selectedCode === rewardServiceCode;
        const baseSubtotal = (serviceMode.value === 'free')
            ? 0
            : (hasStackedLoads
                ? stackedSubtotal
                : (selectedRequiredWeight
                    ? (selectedIsRewardService ? 0 : (weightValue * effectivePricePerLoad(orderTypeCode?.value || '', selectedServiceKind, selectedPricePerLoad, selectedFoldServiceAmount)))
                    : (selectedIsRewardService ? 0 : (loadsLabel * effectivePricePerLoad(orderTypeCode?.value || '', selectedServiceKind, selectedPricePerLoad, selectedFoldServiceAmount)))));
        const allowedWeightKg = selectedMaxWeightKg > 0 ? (selectedMaxWeightKg * loadsLabel) : 0;
        const excessWeightKg = (selfMode || dropOffHasMultipleOrderTypes() || serviceMode.value === 'free' || selectedIsRewardService || selectedMaxWeightKg <= 0)
            ? 0
            : Math.max(0, actualWeightValue - allowedWeightKg);
        const excessChargeUnits = excessWeightKg > 0 ? Math.ceil(excessWeightKg) : 0;
        const excessFeeAmount = excessWeightKg > 0
            ? (excessChargeUnits * selectedExcessWeightFeePerKg)
            : 0;
        const serviceSubtotal = baseSubtotal + excessFeeAmount;
        const totalPrice = serviceSubtotal + addOnTotal;
        return {
            loadsLabel,
            weightValue,
            actualWeightValue,
            addOnTotal,
            baseSubtotal,
            allowedWeightKg,
            excessWeightKg,
            excessChargeUnits,
            excessFeeAmount,
            serviceSubtotal,
            totalPrice: totalPrice + foldTotal,
            foldQty,
            foldTotal,
        };
    };
    const formatPeso = (value) => `₱${(Math.max(0, Number(value) || 0)).toFixed(2)}`;
    const getDiscountPercentage = () => {
        const raw = Number(discountPercentageInput?.value || 0);
        if (!Number.isFinite(raw)) return 0;
        return Math.max(0, Math.min(100, raw));
    };
    const getDiscountAmount = () => {
        const total = Math.max(0, Number(getCurrentTotals().totalPrice || 0));
        return Math.max(0, total * (getDiscountPercentage() / 100));
    };
    const getPaymentDue = () => {
        const total = Math.max(0, Number(getCurrentTotals().totalPrice || 0));
        return Math.max(0, total - getDiscountAmount());
    };
    const updatePayNowAmountsUI = () => {
        const totals = getCurrentTotals();
        const baseTotal = Math.max(0, Number(totals.totalPrice || 0));
        const discountPct = getDiscountPercentage();
        if (discountPercentageInput && String(discountPercentageInput.value) !== String(discountPct)) {
            discountPercentageInput.value = discountPct.toFixed(2);
        }
        const discountAmount = Math.max(0, baseTotal * (discountPct / 100));
        const due = getPaymentDue();
        const isSplit = selectedPayNowMethod === 'split_payment';
        if (payNowDueDisplay) {
            payNowDueDisplay.textContent = formatPeso(due);
        }
        if (discountAmountDisplay) {
            discountAmountDisplay.textContent = `Discount amount (minus): -${formatPeso(discountAmount)}`;
        }
        if (singlePaymentFieldsWrap) {
            singlePaymentFieldsWrap.classList.toggle('d-none', isSplit);
        }
        let paid = Math.max(0, Number(amountPaidInput?.value || 0));
        if (isSplit) {
            const splitCash = Math.max(0, Number(splitCashAmountInput?.value || 0));
            const splitOnline = Math.max(0, Number(splitOnlineAmountInput?.value || 0));
            paid = splitCash + splitOnline;
        }
        const change = Math.max(0, paid - due);
        if (changeDisplay) {
            changeDisplay.textContent = formatPeso(change);
        }
    };
    const resetSplitPaymentValues = () => {
        if (splitCashAmountHidden) splitCashAmountHidden.value = '';
        if (splitOnlineAmountHidden) splitOnlineAmountHidden.value = '';
        if (splitOnlineMethodHidden) splitOnlineMethodHidden.value = '';
        if (splitCashAmountInput) splitCashAmountInput.value = '';
        if (splitOnlineAmountInput) splitOnlineAmountInput.value = '';
        if (splitOnlineMethodSelect) splitOnlineMethodSelect.value = '';
        updatePayNowAmountsUI();
    };

    const kioskPrintFooterLines = [
        'This is not an official receipt',
        'For reference only',
    ];

    const buildKioskEscposBytesSingle = (payload, copyLabel = '') => {
        const enc = new TextEncoder();
        const chunks = [];
        const push = (...bytes) => chunks.push(Uint8Array.from(bytes));
        const pushText = (text) => chunks.push(enc.encode(String(text || '')));
        const line = '--------------------------------\n';
        const centerText = (text = '') => {
            push(0x1b, 0x61, 0x01);
            pushText(`${String(text || '')}\n`);
            push(0x1b, 0x61, 0x00);
        };
        push(0x1b, 0x40);
        centerText('LAUNDRY RECEIPT');
        pushText(line);
        centerText('REFERENCE NO.');
        push(0x1d, 0x21, 0x11);
        centerText(`${payload.referenceCode || '-'}`);
        push(0x1d, 0x21, 0x00);
        if (copyLabel) {
            centerText(copyLabel);
        }
        pushText(line);
        pushText(`Customer: ${payload.customerName || 'Walk-in'}\n`);
        pushText(`Order: ${payload.orderType || '-'}\n`);
        pushText(`Mode: ${payload.modeLabel || 'Regular'}\n`);
        if (!payload.isFreeOrReward) {
            pushText(`Payment: ${payload.paymentLabel || '-'}\n`);
        }
        pushText(`Total: PHP ${Number(payload.totalPrice || 0).toFixed(2)}\n`);
        if (payload.savedAt) pushText(`Date: ${payload.savedAt}\n`);
        pushText(line);
        push(0x1b, 0x61, 0x01);
        kioskPrintFooterLines.forEach((row) => pushText(`${row}\n`));
        push(0x1b, 0x61, 0x00);
        pushText(line);
        centerText('Thank you!');
        pushText('\n\n\n');
        push(0x1d, 0x56, 0x01);
        const totalLength = chunks.reduce((acc, part) => acc + part.length, 0);
        const merged = new Uint8Array(totalLength);
        let offset = 0;
        chunks.forEach((part) => {
            merged.set(part, offset);
            offset += part.length;
        });
        return merged;
    };
    const buildKioskEscposBytes = (payload) => {
        const customerCopy = buildKioskEscposBytesSingle(payload, "Customer's Copy");
        const shopCopy = buildKioskEscposBytesSingle(payload, 'Shop Copy');
        const merged = new Uint8Array(customerCopy.length + shopCopy.length);
        merged.set(customerCopy, 0);
        merged.set(shopCopy, customerCopy.length);
        return merged;
    };

    /** When branch "Enable Bluetooth print" is on, send ESC/POS immediately (opens system Bluetooth flow). When off, skip printing. */
    const runKioskBluetoothPrintAfterSave = async (payload) => {
        if (!kioskEnableBluetoothPrint) {
            return { printed: false };
        }
        if (typeof window.mpgWriteEscposBluetooth !== 'function') {
            throw new Error('Bluetooth thermal printing is not available on this device/browser.');
        }
        await window.mpgWriteEscposBluetooth(buildKioskEscposBytes(payload));
        return { printed: true };
    };

    const submitKioskOrder = async () => {
        if (!form || isSubmittingOrder) return;
        isSubmittingOrder = true;
        try {
            const formData = new FormData(form);
            const body = new URLSearchParams();
            formData.forEach((value, key) => {
                body.append(key, String(value));
            });
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body,
            });
            const { data } = await parseJsonBody(res);
            if (!res.ok || data.success !== true) {
                const message = typeof data.message === 'string' && data.message.trim()
                    ? data.message
                    : 'Could not save transaction.';
                throw new Error(message);
            }
            const totals = getCurrentTotals();
            const isFreeOrReward = serviceMode.value === 'free' || (serviceMode.value === 'reward' && totals.totalPrice <= 1e-9);
            const paymentLabel = isFreeOrReward ? 'Paid' : ((paymentTiming?.value || 'pay_later') === 'pay_now' ? 'Paid' : 'Unpaid');
            const modeLabel = serviceMode.value === 'reward'
                ? (totals.totalPrice > 1e-9 ? 'REWARDS WITH PAYMENT' : 'REWARD')
                : ((serviceMode.value || 'regular').toUpperCase());
            const printPayload = {
                referenceCode: String(data.reference_code || kioskReferenceCode || '').trim(),
                customerName: selectedCustomerLabel || 'No customer selected',
                orderType: selectedOrderLabel || 'Order',
                modeLabel,
                paymentLabel,
                isFreeOrReward,
                totalPrice: totals.totalPrice,
                savedAt: String(data.saved_at || '').trim(),
            };
            let printResult = { printed: false };
            try {
                printResult = await runKioskBluetoothPrintAfterSave(printPayload);
            } catch (printError) {
                const m = printError instanceof Error ? printError.message : 'Bluetooth print failed.';
                if (typeof Swal !== 'undefined') {
                    await Swal.fire({ icon: 'warning', title: 'Saved but not printed', text: m, confirmButtonColor: '#dc3545' });
                } else {
                    showWarn(`Saved but not printed: ${m}`);
                }
            }
            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    icon: 'success',
                    title: printResult?.printed ? 'Saved and printed' : 'Saved successfully',
                    text: printResult?.printed
                        ? 'Transaction was saved and receipt was printed.'
                        : 'Transaction was saved.',
                    confirmButtonColor: '#198754',
                });
            } else {
                window.mpgAlert(
                    printResult?.printed ? 'Transaction was saved and receipt was printed.' : 'Transaction was saved.',
                    { title: printResult?.printed ? 'Saved and printed' : 'Saved successfully', icon: 'success' }
                );
            }
            try {
                window.sessionStorage.setItem(KIOSK_SCROLL_TOP_AFTER_SAVE_KEY, '1');
            } catch (_) {
                // Ignore storage access errors and continue with reload.
            }
            window.location.reload();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Could not save transaction.';
            showWarn(message);
        } finally {
            isSubmittingOrder = false;
        }
    };

    const updateSummary = () => {
        syncInventorySelectionAvailability();
        syncInclusionQtyBadges();
        const selfMode = isSelfServiceMode();
        const allowAddonSupplies = selfMode
            ? true
            : (dropOffHasStackedOrderTypes()
                ? Array.from(selfServiceOrderQuantities.values()).some((entry) => !!entry.showAddonSupplies)
                : !!selectedShowAddonSupplies);
        syncFoldQtyVisibility();
        addonDetId.value = addonDetId.value || '';
        addonFabId.value = addonFabId.value || '';
        addonBleachId.value = addonBleachId.value || '';
        addonOtherId.value = addonOtherId.value || '';
        addonDetQty.value = String(Math.max(0, parseFloat(addonDetQtyInput?.value || '0') || 0));
        addonFabQty.value = String(Math.max(0, parseFloat(addonFabQtyInput?.value || '0') || 0));
        addonBleachQty.value = String(Math.max(0, parseFloat(addonBleachQtyInput?.value || '0') || 0));
        addonOtherQty.value = String(Math.max(0, parseFloat(addonOtherQtyInput?.value || '0') || 0));
        syncPersistentSelectionHighlights();
        syncAddonQtyBadge('.kiosk-addon-det-btn', addonDetId.value, parseFloat(addonDetQty.value) || 0);
        syncAddonQtyBadge('.kiosk-addon-fab-btn', addonFabId.value, parseFloat(addonFabQty.value) || 0);
        syncAddonQtyBadge('.kiosk-addon-bleach-btn', addonBleachId.value, parseFloat(addonBleachQty.value) || 0);
        syncAddonQtyBadge('.kiosk-addon-other-btn', addonOtherId.value, parseFloat(addonOtherQty.value) || 0);
        const supplyLabelParts = [];
        const detBtn = document.querySelector(`.kiosk-supply-det-btn[data-id="${inclusionDet.value}"]`);
        const fabBtn = document.querySelector(`.kiosk-supply-fab-btn[data-id="${inclusionFab.value}"]`);
        const blBtn = document.querySelector(`.kiosk-supply-bleach-btn[data-id="${inclusionBleach.value}"]`);
        if (inclusionDet.value) supplyLabelParts.push(`Detergent: ${detBtn?.getAttribute('data-name') || ''}`);
        if (inclusionFab.value) supplyLabelParts.push(`Fabcon: ${fabBtn?.getAttribute('data-name') || ''}`);
        if (inclusionBleach.value) supplyLabelParts.push(`Bleach: ${blBtn?.getAttribute('data-name') || ''}`);
        const addonParts = [];
        const addonDetBtn = document.querySelector(`.kiosk-addon-det-btn[data-id="${addonDetId.value}"]`);
        const addonFabBtn = document.querySelector(`.kiosk-addon-fab-btn[data-id="${addonFabId.value}"]`);
        const addonBleachBtn = document.querySelector(`.kiosk-addon-bleach-btn[data-id="${addonBleachId.value}"]`);
        const addonOtherBtn = document.querySelector(`.kiosk-addon-other-btn[data-id="${addonOtherId.value}"]`);
        if (allowAddonSupplies && serviceMode.value !== 'free' && parseFloat(addonDetQty.value) > 0 && addonDetId.value) {
            const up = parseFloat(addonDetBtn?.getAttribute('data-unit-cost') || '0') || 0;
            addonParts.push(`Detergent: ${addonDetQty.value}× ${addonDetBtn?.getAttribute('data-name') || ''} · Price ${formatPeso(up)}`.trim());
        }
        if (allowAddonSupplies && serviceMode.value !== 'free' && parseFloat(addonFabQty.value) > 0 && addonFabId.value) {
            const up = parseFloat(addonFabBtn?.getAttribute('data-unit-cost') || '0') || 0;
            addonParts.push(`Fabcon: ${addonFabQty.value}× ${addonFabBtn?.getAttribute('data-name') || ''} · Price ${formatPeso(up)}`.trim());
        }
        if (allowAddonSupplies && serviceMode.value !== 'free' && parseFloat(addonBleachQty.value) > 0 && addonBleachId.value) {
            const up = parseFloat(addonBleachBtn?.getAttribute('data-unit-cost') || '0') || 0;
            addonParts.push(`Bleach: ${addonBleachQty.value}× ${addonBleachBtn?.getAttribute('data-name') || ''} · Price ${formatPeso(up)}`.trim());
        }
        if (allowAddonSupplies && serviceMode.value !== 'free' && parseFloat(addonOtherQty.value) > 0 && addonOtherId.value) {
            const up = parseFloat(addonOtherBtn?.getAttribute('data-unit-cost') || '0') || 0;
            addonParts.push(`Other: ${addonOtherQty.value}× ${addonOtherBtn?.getAttribute('data-name') || ''} · Price ${formatPeso(up)}`.trim());
        }
        const totals = getCurrentTotals();
        const summaryMode = rewardRedemption?.value === '1'
            ? (totals.totalPrice > 1e-9 ? 'REWARDS WITH PAYMENT' : 'REWARD')
            : (serviceMode.value || 'regular').toUpperCase();
        const paymentLabel = (serviceMode.value === 'free' || (serviceMode.value === 'reward' && totals.totalPrice <= 1e-9))
            ? 'Paid'
            : (paymentTiming?.value === 'pay_now' ? 'Pay Now (Paid)' : 'Pay Later (Unpaid)');
        const loadsLabel = totals.loadsLabel;
        const weightValue = totals.weightValue;
        const actualWeightValue = totals.actualWeightValue;
        if (serviceWeightHidden) {
            serviceWeightHidden.value = (!selfMode && selectedRequiredWeight && weightValue > 0) ? String(weightValue) : '';
        }
        const applyActualWeight = !selfMode && !dropOffHasMultipleOrderTypes() && selectedMaxWeightKg > 0;
        if (actualWeightHidden) {
            actualWeightHidden.value = (applyActualWeight && actualWeightValue > 0) ? String(actualWeightValue) : '';
        }
        const addOnTotal = totals.addOnTotal;
        const baseSubtotal = totals.baseSubtotal;
        const excessFeeAmount = totals.excessFeeAmount;
        const excessWeightKg = totals.excessWeightKg;
        const excessChargeUnits = totals.excessChargeUnits;
        const serviceSubtotal = totals.serviceSubtotal;
        const totalPrice = totals.totalPrice;
        const foldQty = totals.foldQty || 0;
        const foldTotal = totals.foldTotal || 0;
        if (estimatedTotalAmount) {
            estimatedTotalAmount.textContent = `₱${totalPrice.toFixed(2)}`;
        }
        if (floatingGrandTotal) {
            floatingGrandTotal.textContent = `₱${totalPrice.toFixed(2)}`;
        }
        if (actualWeightWrap) {
            const showActualWeight = selectedMaxWeightKg > 0 && !dropOffHasMultipleOrderTypes();
            actualWeightWrap.classList.toggle('d-none', selfMode || !showActualWeight);
            if (!showActualWeight) {
                if (actualWeightInput) actualWeightInput.value = '';
                if (actualWeightHidden) actualWeightHidden.value = '';
            }
        }
        if (excessFeeHint) {
            if (selectedMaxWeightKg > 0 && !dropOffHasMultipleOrderTypes()) {
                excessFeeHint.textContent = `Limit ${selectedMaxWeightKg.toFixed(2)} kg/load. Excess fee: ₱${selectedExcessWeightFeePerKg.toFixed(2)} per started kg.`;
            } else {
                excessFeeHint.textContent = '';
            }
        }
        const excessNotice = excessFeeAmount > 0
            ? `You have additional fee: ${formatPeso(excessFeeAmount)} (${excessWeightKg.toFixed(2)} kg excess, charged as ${excessChargeUnits} kg).`
            : '';
        const selfLines = Array.from(selfServiceOrderQuantities.values())
            .filter((entry) => Number(entry.qty || 0) > 0)
            .map((entry) => `${entry.label}: ${entry.qty}`)
            .join(', ');
        const customerSummary = selfMode ? 'N/A' : (selectedCustomerLabel || 'No customer selected');
        const dropOffStacked = dropOffHasStackedOrderTypes();
        const loadsSummary = selfMode ? (loadsLabel > 0 ? String(loadsLabel) : 'N/A') : String(loadsLabel);
        const serviceSuppliesSummary = selfMode ? 'N/A' : listHtml(supplyLabelParts);
        const addOnSummary = listHtml(addonParts);
        if (foldServiceQtyHidden) {
            foldServiceQtyHidden.value = String(Math.max(0, foldQty));
        }
        if (foldQtyBadge) {
            foldQtyBadge.textContent = `Qty: ${Math.max(0, foldQty)}`;
        }
        if (foldService) {
            foldService.value = (selfMode ? (foldQty > 0) : (foldService.value === '1')) ? '1' : '0';
        }
        summary.innerHTML = `
            <div class="kiosk-summary-receipt">
                <div class="line"><span class="label">Order Type</span><span class="value">${esc((selfMode || dropOffStacked) ? (selfLines || 'N/A') : (selectedOrderLabel || 'No order type'))}</span></div>
                <div class="line"><span class="label">Mode</span><span class="value">${esc(summaryMode)}</span></div>
                <div class="line"><span class="label">Loads</span><span class="value">${esc(loadsSummary)}</span></div>
                ${(selectedRequiredWeight && !selfMode) ? `<div class="line"><span class="label">Weight</span><span class="value">${esc(weightValue.toFixed(2))}</span></div>` : '<div class="line"><span class="label">Weight</span><span class="value">N/A</span></div>'}
                ${(selectedMaxWeightKg > 0 && !selfMode && !dropOffHasMultipleOrderTypes()) ? `<div class="line"><span class="label">Actual Weight</span><span class="value">${esc(actualWeightValue.toFixed(2))} kg</span></div>` : '<div class="line"><span class="label">Actual Weight</span><span class="value">N/A</span></div>'}
                <div class="line"><span class="label">Payment</span><span class="value">${esc(paymentLabel)}</span></div>
                <div class="line"><span class="label">Base Subtotal</span><span class="value">₱${esc(baseSubtotal.toFixed(2))}</span></div>
                ${selectedMaxWeightKg > 0 && !selfMode && !dropOffHasMultipleOrderTypes() ? `<div class="line"><span class="label">Excess Weight Fee</span><span class="value">₱${esc(excessFeeAmount.toFixed(2))}</span></div>` : '<div class="line"><span class="label">Excess Weight Fee</span><span class="value">N/A</span></div>'}
                ${selectedMaxWeightKg > 0 && !selfMode && !dropOffHasMultipleOrderTypes() ? `<div class="line"><span class="label">Service Subtotal</span><span class="value">₱${esc(serviceSubtotal.toFixed(2))}</span></div>` : '<div class="line"><span class="label">Service Subtotal</span><span class="value">N/A</span></div>'}
                <div class="line"><span class="label">Add-ons Total</span><span class="value">₱${esc(addOnTotal.toFixed(2))}</span></div>
                <div class="line"><span class="label">Total Price</span><span class="value">₱${esc(totalPrice.toFixed(2))}</span></div>
                ${excessNotice && !selfMode ? `<div class="line"><span class="label">Notice</span><span class="value">${esc(excessNotice)}</span></div>` : '<div class="line"><span class="label">Notice</span><span class="value">N/A</span></div>'}
                <div class="line"><span class="label">Reference No.</span><span class="value">${esc(kioskReferenceCode || '-')}</span></div>
                <div class="line"><span class="label">Customer</span><span class="value">${esc(customerSummary)}</span></div>
                <div class="line"><span class="label">Service Supplies</span><span class="value">${serviceSuppliesSummary}</span></div>
                <div class="line"><span class="label">Add-ons</span><span class="value">${addOnSummary}</span></div>
            </div>
        `;
        updatePayNowAmountsUI();
        syncRewardMode();
    };
    const syncSubmitButtonsByMode = () => {
        const totals = getCurrentTotals();
        const noPaymentMode = serviceMode.value === 'free' || (serviceMode.value === 'reward' && totals.totalPrice <= 1e-9);
        regularSubmitWrap?.classList.toggle('d-none', noPaymentMode);
        freeRewardSubmitWrap?.classList.toggle('d-none', !noPaymentMode);
        if (noPaymentMode && paymentTiming) {
            paymentTiming.value = 'pay_later';
        }
        if (noPaymentMode && paymentMethod) {
            paymentMethod.value = 'pending';
        }
    };

    const computeSupplyStep = () => {
        const detQty = inclusionQtyForKind('det');
        const fabQty = inclusionQtyForKind('fab');
        const bleachQty = inclusionQtyForKind('bleach');
        const noSupplies = detQty <= 0 && fabQty <= 0 && bleachQty <= 0;
        noSuppliesMessage?.classList.toggle('d-none', !noSupplies);
        supplyDetWrap?.classList.toggle('d-none', detQty <= 0);
        supplyFabWrap?.classList.toggle('d-none', fabQty <= 0);
        supplyBleachWrap?.classList.toggle('d-none', bleachQty <= 0);
        if (detQty <= 0) {
            inclusionDet.value = '';
            setButtonState('.kiosk-supply-det-btn', null);
        }
        if (fabQty <= 0) {
            inclusionFab.value = '';
            setButtonState('.kiosk-supply-fab-btn', null);
        }
        if (bleachQty <= 0) {
            inclusionBleach.value = '';
            setButtonState('.kiosk-supply-bleach-btn', null);
        }
        if (noSupplies) {
            inclusionDet.value = '';
            inclusionFab.value = '';
            inclusionBleach.value = '';
            addonDetId.value = '';
            addonFabId.value = '';
            addonBleachId.value = '';
            addonOtherId.value = '';
            addonDetQty.value = '0';
            addonFabQty.value = '0';
            addonBleachQty.value = '0';
            addonOtherQty.value = '0';
            if (addonDetQtyInput) addonDetQtyInput.value = '';
            if (addonFabQtyInput) addonFabQtyInput.value = '';
            if (addonBleachQtyInput) addonBleachQtyInput.value = '';
            if (addonOtherQtyInput) addonOtherQtyInput.value = '';
            setButtonState('.kiosk-supply-det-btn', null);
            setButtonState('.kiosk-supply-fab-btn', null);
            setButtonState('.kiosk-supply-bleach-btn', null);
            setButtonState('.kiosk-addon-det-btn', null);
            setButtonState('.kiosk-addon-fab-btn', null);
            setButtonState('.kiosk-addon-bleach-btn', null);
            setButtonState('.kiosk-addon-other-btn', null);
        }
        recomputeVisibleSteps();
    };

    const renderStep = () => {
        recomputeVisibleSteps();
        updateSummary();
    };

    const validateStep = (step) => {
        const getMissingRequiredSupplies = () => {
            const missing = [];
            if (inclusionQtyForKind('det') > 0 && !inclusionDet.value) missing.push('Detergent');
            if (inclusionQtyForKind('fab') > 0 && !inclusionFab.value) missing.push('Fabcon');
            if (inclusionQtyForKind('bleach') > 0 && !inclusionBleach.value) missing.push('Bleach');
            return missing;
        };
        const firstMissingSupplyTarget = (missing) => {
            if (!Array.isArray(missing) || missing.length === 0) {
                return document.querySelector('.kiosk-supply-det-btn') || document.querySelector('.kiosk-supply-fab-btn') || document.querySelector('.kiosk-supply-bleach-btn');
            }
            if (missing.includes('Detergent')) return document.querySelector('.kiosk-supply-det-btn');
            if (missing.includes('Fabcon')) return document.querySelector('.kiosk-supply-fab-btn');
            if (missing.includes('Bleach')) return document.querySelector('.kiosk-supply-bleach-btn');
            return document.querySelector('.kiosk-supply-det-btn') || document.querySelector('.kiosk-supply-fab-btn') || document.querySelector('.kiosk-supply-bleach-btn');
        };
        if (step === 1 && !orderTypeCode.value) {
            const msg = isSelfServiceMode()
                ? 'Please tap at least one order type for Self Service.'
                : 'Please select an order type.';
            warnAndFocus(
                msg,
                document.querySelector('.kiosk-order-type-btn'),
                document.getElementById('kioskOrderTypeCards')
            );
            return false;
        }
        if (isSelfServiceMode()) return true;
        if (step === 2) {
            const missingSupplies = getMissingRequiredSupplies();
            if (missingSupplies.length > 0) {
                warnAndFocus(
                    `Please select required service supplies: ${missingSupplies.join(', ')}.`,
                    firstMissingSupplyTarget(missingSupplies),
                    document.getElementById('kioskSuppliesWrap')
                );
                return false;
            }
        }
        return true;
    };

    const validateBeforeSubmit = () => {
        updateSummary();
        if (!orderTypeCode.value) {
            const msg = isSelfServiceMode()
                ? 'Please tap at least one order type for Self Service.'
                : 'Please select an order type.';
            warnAndFocus(
                msg,
                document.querySelector('.kiosk-order-type-btn'),
                document.getElementById('kioskOrderTypeCards')
            );
            return false;
        }
        if (isSelfServiceMode()) {
            if (!selfServiceLinesInput || selfServiceLinesInput.value.trim() === '') {
                warnAndFocus(
                    'Please tap at least one order type for Self Service.',
                    document.querySelector('.kiosk-order-type-btn'),
                    document.getElementById('kioskOrderTypeCards')
                );
                return false;
            }
            return true;
        }
        const customerMode = (customerSelection?.value || '').trim();
        if (customerMode !== 'saved' && customerMode !== 'walk_in') {
            warnCustomerRequired();
            return false;
        }
        const missingSuppliesBeforeSave = [];
        if (inclusionQtyForKind('det') > 0 && !inclusionDet.value) missingSuppliesBeforeSave.push('Detergent');
        if (inclusionQtyForKind('fab') > 0 && !inclusionFab.value) missingSuppliesBeforeSave.push('Fabcon');
        if (inclusionQtyForKind('bleach') > 0 && !inclusionBleach.value) missingSuppliesBeforeSave.push('Bleach');
        if (missingSuppliesBeforeSave.length > 0) {
            let firstMissingTarget = document.querySelector('.kiosk-supply-det-btn') || document.querySelector('.kiosk-supply-fab-btn') || document.querySelector('.kiosk-supply-bleach-btn');
            if (missingSuppliesBeforeSave.includes('Detergent')) firstMissingTarget = document.querySelector('.kiosk-supply-det-btn');
            else if (missingSuppliesBeforeSave.includes('Fabcon')) firstMissingTarget = document.querySelector('.kiosk-supply-fab-btn');
            else if (missingSuppliesBeforeSave.includes('Bleach')) firstMissingTarget = document.querySelector('.kiosk-supply-bleach-btn');
            warnAndFocus(
                `Please select required service supplies before saving: ${missingSuppliesBeforeSave.join(', ')}.`,
                firstMissingTarget,
                document.getElementById('kioskSuppliesWrap')
            );
            return false;
        }
        if (selectedRequiredWeight) {
            const w = Math.max(0, parseFloat(serviceWeightInput?.value || '0') || 0);
            if (w <= 0) {
                warnAndFocus(
                    'Weight is required for this service.',
                    serviceWeightInput,
                    weightWrap || serviceWeightInput
                );
                return false;
            }
        }
        if (selectedMaxWeightKg > 0 && !dropOffHasMultipleOrderTypes() && serviceMode.value !== 'free' && serviceMode.value !== 'reward') {
            const actualWeight = Math.max(0, parseFloat(actualWeightInput?.value || '0') || 0);
            if (actualWeight <= 0) {
                warnAndFocus(
                    'Actual weight is required for this service because a maximum weight limit is configured.',
                    actualWeightInput || serviceWeightInput,
                    actualWeightWrap || actualWeightInput || serviceWeightInput
                );
                return false;
            }
        }
        const trackGasulEnabled = trackGasulToggle
            ? trackGasulToggle.checked === true
            : String(trackGasulHidden?.value || '0') === '1';
        if (trackGasulEnabled) {
            const gasulBtn = document.querySelector('.kiosk-addon-other-btn[data-gasul-item="1"]');
            const gasulId = String(gasulBtn?.getAttribute('data-id') || '').trim();
            const selectedOtherId = String(addonOtherId?.value || '').trim();
            const selectedOtherQty = Math.max(0, parseFloat(addonOtherQty?.value || '0') || 0);
            if (!gasulBtn || gasulId === '') {
                warnAndFocus(
                    'Gasul item is not configured in Inventory Stocks. Please add Gasul first.',
                    document.getElementById('kioskTrackGasulToggle'),
                    document.getElementById('kioskAddonOtherCards')
                );
                return false;
            }
            if (selectedOtherId !== gasulId || selectedOtherQty <= 0) {
                warnAndFocus(
                    'Track Gasul is ON. Please select Gasul in Add-on Other items.',
                    gasulBtn,
                    document.getElementById('kioskAddonOtherCards')
                );
                return false;
            }
        }
        return true;
    };


    const setButtonState = (selector, activeBtn, activeCls = 'btn-primary', inactiveCls = 'btn-outline-primary') => {
        const formatStockQty = (qtyRaw) => {
            const qty = parseFloat(String(qtyRaw || '0'));
            if (!Number.isFinite(qty)) return '0';
            if (Math.abs(qty - Math.round(qty)) < 1e-6) return String(Math.round(qty));
            return String(qty.toFixed(2)).replace(/\.?0+$/, '');
        };
        const isLowStockBtn = (btn) => {
            const stock = parseFloat(btn?.dataset?.stockQty || '0') || 0;
            const threshold = parseFloat(btn?.dataset?.lowStockThreshold || '0') || 0;
            return threshold > 0 && stock <= threshold + 1e-9;
        };
        const syncStockIndicator = (btn, isSelected) => {
            const indicator = btn?.querySelector?.('.kiosk-item-stock');
            if (!indicator) return;
            const remaining = formatStockQty(btn.dataset.stockQty || '0');
            const isLow = isLowStockBtn(btn);
            if (isSelected && isLow) {
                indicator.textContent = `Low stock: ${remaining} remaining`;
                indicator.classList.remove('text-muted');
                indicator.classList.add('text-danger', 'fw-semibold');
                return;
            }
            indicator.textContent = `Stock: ${remaining}`;
            indicator.classList.remove('text-danger', 'fw-semibold');
            indicator.classList.add('text-muted');
        };

        document.querySelectorAll(selector).forEach((btn) => {
            btn.classList.remove(activeCls);
            btn.classList.add(inactiveCls);
            btn.classList.remove('kiosk-card-selected');
            btn.classList.remove('kiosk-card-selected-low-stock');
            btn.setAttribute('aria-pressed', 'false');
            syncStockIndicator(btn, false);
        });
        if (activeBtn) {
            activeBtn.classList.remove(inactiveCls);
            activeBtn.classList.add(activeCls);
            activeBtn.classList.add('kiosk-card-selected');
            if (isLowStockBtn(activeBtn)) {
                activeBtn.classList.add('kiosk-card-selected-low-stock');
            }
            activeBtn.setAttribute('aria-pressed', 'true');
            syncStockIndicator(activeBtn, true);
        }
    };

    /** Full service and Fold → fold on by default; any other service behavior → fold off. */
    const applyFoldDefaultForOrderType = () => {
        if (!foldService || !foldNoBtn || !foldYesBtn) return;
        if (selectedServiceKind === 'full_service' || selectedServiceKind === 'fold_only') {
            foldService.value = '1';
            setButtonState('#kioskFoldNoBtn, #kioskFoldYesBtn', foldYesBtn);
        } else {
            foldService.value = '0';
            setButtonState('#kioskFoldNoBtn, #kioskFoldYesBtn', foldNoBtn);
        }
    };

    document.querySelectorAll('.kiosk-order-type-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const code = String(btn.dataset.code || '').trim();
            if (code === '') return;
            orderTypeCode.value = btn.dataset.code || '';
            selectedOrderLabel = btn.textContent?.trim() || '';
            selectedServiceKind = btn.dataset.serviceKind || 'full_service';
            selectedSupplyBlock = btn.dataset.supplyBlock || defaultSupplyBlockForKind(selectedServiceKind);
            if (selectedSupplyBlock === 'none' && (selectedServiceKind === 'full_service' || selectedServiceKind === 'wash_only' || selectedServiceKind === 'rinse_only')) {
                selectedSupplyBlock = defaultSupplyBlockForKind(selectedServiceKind);
            }
            selectedDetergentQty = Math.max(0, parseFloat(btn.dataset.detergentQty || '0') || 0);
            selectedFabconQty = Math.max(0, parseFloat(btn.dataset.fabconQty || '0') || 0);
            selectedBleachQty = Math.max(0, parseFloat(btn.dataset.bleachQty || '0') || 0);
            const addonRaw = (btn.getAttribute('data-show-addon-supplies') || '').trim();
            if (addonRaw === '1') {
                selectedShowAddonSupplies = true;
            } else if (addonRaw === '0') {
                selectedShowAddonSupplies = false;
            } else {
                selectedShowAddonSupplies = defaultShowAddonForKind(selectedServiceKind);
            }
            selectedRequiredWeight = (btn.dataset.requiredWeight || '0') === '1' || selectedServiceKind === 'dry_cleaning';
            selectedFoldServiceAmount = Math.max(0, parseFloat(btn.dataset.foldServiceAmount || '0') || 0);
            selectedPricePerLoad = effectivePricePerLoad(
                code,
                selectedServiceKind,
                parseFloat(btn.dataset.pricePerLoad || '0') || 0,
                selectedFoldServiceAmount
            );
            selectedMaxWeightKg = Math.max(0, parseFloat(btn.dataset.maxWeightKg || '0') || 0);
            selectedExcessWeightFeePerKg = Math.max(0, parseFloat(btn.dataset.excessWeightFeePerKg || '0') || 0);
            const prev = selfServiceOrderQuantities.get(code) || {
                code,
                label: btn.textContent?.trim() || code,
                serviceKind: btn.dataset.serviceKind || 'full_service',
                basePricePerLoad: parseFloat(btn.dataset.pricePerLoad || '0') || 0,
                foldServiceAmount: Math.max(0, parseFloat(btn.dataset.foldServiceAmount || '0') || 0),
                pricePerLoad: effectivePricePerLoad(
                    code,
                    btn.dataset.serviceKind || 'full_service',
                    parseFloat(btn.dataset.pricePerLoad || '0') || 0,
                    parseFloat(btn.dataset.foldServiceAmount || '0') || 0
                ),
                detergentQty: Math.max(0, parseFloat(btn.dataset.detergentQty || '0') || 0),
                fabconQty: Math.max(0, parseFloat(btn.dataset.fabconQty || '0') || 0),
                bleachQty: Math.max(0, parseFloat(btn.dataset.bleachQty || '0') || 0),
                showAddonSupplies: !!selectedShowAddonSupplies,
                qty: 0,
            };
            const blockReason = getOrderTypeIncreaseBlockReason(prev);
            if (blockReason) {
                void showWarn(blockReason);
                return;
            }
            prev.qty = Math.max(0, Number(prev.qty || 0)) + 1;
            selfServiceOrderQuantities.set(code, prev);
            if (weightWrap) weightWrap.classList.toggle('d-none', !selectedRequiredWeight);
            if (serviceWeightInput) {
                serviceWeightInput.required = selectedRequiredWeight;
                if (!selectedRequiredWeight) serviceWeightInput.value = '';
            }
            applyFoldDefaultForOrderType();
            syncSelfServiceOrderQtyUI();
            computeMachineNeed();
            computeSupplyStep();
            updateSummary();
        });
    });
    document.querySelectorAll('.kiosk-order-type-adjust-btn').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const cardWrap = event.currentTarget.closest('.kiosk-order-type-wrap');
            const card = cardWrap?.querySelector('.kiosk-order-type-btn');
            if (!(card instanceof HTMLButtonElement)) return;
            const code = String(card.dataset.code || '').trim();
            if (code === '') return;
            const action = String(btn.getAttribute('data-action') || 'increment');
            const prev = selfServiceOrderQuantities.get(code) || {
                code,
                label: card.textContent?.trim() || code,
                serviceKind: card.dataset.serviceKind || 'full_service',
                basePricePerLoad: parseFloat(card.dataset.pricePerLoad || '0') || 0,
                foldServiceAmount: Math.max(0, parseFloat(card.dataset.foldServiceAmount || '0') || 0),
                pricePerLoad: effectivePricePerLoad(
                    code,
                    card.dataset.serviceKind || 'full_service',
                    parseFloat(card.dataset.pricePerLoad || '0') || 0,
                    parseFloat(card.dataset.foldServiceAmount || '0') || 0
                ),
                detergentQty: Math.max(0, parseFloat(card.dataset.detergentQty || '0') || 0),
                fabconQty: Math.max(0, parseFloat(card.dataset.fabconQty || '0') || 0),
                bleachQty: Math.max(0, parseFloat(card.dataset.bleachQty || '0') || 0),
                showAddonSupplies: ((card.getAttribute('data-show-addon-supplies') || '').trim() === '1'),
                qty: 0,
            };
            const currentQty = Math.max(0, Number(prev.qty || 0));
            if (action !== 'decrement') {
                const blockReason = getOrderTypeIncreaseBlockReason(prev);
                if (blockReason) {
                    void showWarn(blockReason);
                    return;
                }
            }
            const nextQty = action === 'decrement' ? Math.max(0, currentQty - 1) : currentQty + 1;
            if (nextQty <= 0) {
                selfServiceOrderQuantities.delete(code);
            } else {
                prev.qty = nextQty;
                selfServiceOrderQuantities.set(code, prev);
            }
            syncSelfServiceOrderQtyUI();
            if (nextQty > 0) {
                selectedOrderLabel = card.textContent?.trim() || selectedOrderLabel;
                selectedServiceKind = card.dataset.serviceKind || selectedServiceKind;
                selectedSupplyBlock = card.dataset.supplyBlock || defaultSupplyBlockForKind(selectedServiceKind);
                if (selectedSupplyBlock === 'none' && (selectedServiceKind === 'full_service' || selectedServiceKind === 'wash_only' || selectedServiceKind === 'rinse_only')) {
                    selectedSupplyBlock = defaultSupplyBlockForKind(selectedServiceKind);
                }
                selectedDetergentQty = Math.max(0, parseFloat(card.dataset.detergentQty || '0') || 0);
                selectedFabconQty = Math.max(0, parseFloat(card.dataset.fabconQty || '0') || 0);
                selectedBleachQty = Math.max(0, parseFloat(card.dataset.bleachQty || '0') || 0);
                const addonRaw = (card.getAttribute('data-show-addon-supplies') || '').trim();
                if (addonRaw === '1') {
                    selectedShowAddonSupplies = true;
                } else if (addonRaw === '0') {
                    selectedShowAddonSupplies = false;
                } else {
                    selectedShowAddonSupplies = defaultShowAddonForKind(selectedServiceKind);
                }
                selectedRequiredWeight = (card.dataset.requiredWeight || '0') === '1' || selectedServiceKind === 'dry_cleaning';
                selectedFoldServiceAmount = Math.max(0, parseFloat(card.dataset.foldServiceAmount || '0') || 0);
                selectedPricePerLoad = effectivePricePerLoad(
                    code,
                    selectedServiceKind,
                    parseFloat(card.dataset.pricePerLoad || '0') || 0,
                    selectedFoldServiceAmount
                );
                selectedMaxWeightKg = Math.max(0, parseFloat(card.dataset.maxWeightKg || '0') || 0);
                selectedExcessWeightFeePerKg = Math.max(0, parseFloat(card.dataset.excessWeightFeePerKg || '0') || 0);
                if (weightWrap) weightWrap.classList.toggle('d-none', !selectedRequiredWeight);
                if (serviceWeightInput) {
                    serviceWeightInput.required = selectedRequiredWeight;
                    if (!selectedRequiredWeight) serviceWeightInput.value = '';
                }
                applyFoldDefaultForOrderType();
            }
            computeMachineNeed();
            computeSupplyStep();
            updateSummary();
        });
    });
    const adjustAddonQtyByStep = (target, action) => {
        const map = {
            det: addonDetQtyInput,
            fab: addonFabQtyInput,
            bleach: addonBleachQtyInput,
            other: addonOtherQtyInput,
        };
        const input = map[target] || null;
        if (!input) return;
        const current = Math.max(0, parseFloat(input.value || '0') || 0);
        const step = 1;
        const next = action === 'decrement'
            ? Math.max(0, current - step)
            : current + step;
        input.value = next > 0 ? String(next) : '';
        updateSummary();
    };
    const adjustInclusionQtyByStep = (target, action) => {
        if (isSelfServiceMode()) return;
        const selectedId = target === 'det'
            ? String(inclusionDet?.value || '')
            : (target === 'fab' ? String(inclusionFab?.value || '') : String(inclusionBleach?.value || ''));
        if (selectedId === '') return;
        const current = Math.max(0, parseFloat(String(inclusionQtyForKind(target) || 0)) || 0);
        const stock = getSupplyStockByKind(target);
        const nextRaw = action === 'decrement' ? Math.max(0, current - 1) : (current + 1);
        const next = Math.max(0, Math.min(stock, nextRaw));
        setInclusionQtyForKind(target, next);
        updateSummary();
    };
    document.querySelectorAll('.kiosk-inclusion-qty-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = String(btn.getAttribute('data-target') || '').trim();
            const action = String(btn.getAttribute('data-action') || 'increment').trim();
            adjustInclusionQtyByStep(target, action);
        });
    });
    document.querySelectorAll('.kiosk-addon-qty-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const target = String(btn.getAttribute('data-target') || '').trim();
            const action = String(btn.getAttribute('data-action') || 'increment').trim();
            adjustAddonQtyByStep(target, action);
        });
    });
    const adjustFoldQty = (action) => {
        const current = Math.max(0, parseInt(foldQtyInput?.value || '0', 10) || 0);
        const next = action === 'decrement'
            ? Math.max(0, current - 1)
            : current + 1;
        if (foldQtyInput) {
            foldQtyInput.value = next > 0 ? String(next) : '';
        }
        updateSummary();
    };
    foldQtyDecBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        adjustFoldQty('decrement');
    });
    foldQtyIncBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        adjustFoldQty('increment');
    });
    foldQtyInput?.addEventListener('change', updateSummary);
    foldQtyInput?.addEventListener('input', updateSummary);
    document.querySelectorAll('.kiosk-order-mode-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-mode') || 'drop_off';
            switchOrderMode(mode);
        });
    });

    const handleCustomerPick = (btn) => {
        const nextCustomerId = btn.dataset.id || '';
        if (rewardIntentCustomerId !== '' && rewardIntentCustomerId !== nextCustomerId) {
            clearRewardRedemption();
            serviceMode.value = 'regular';
            setButtonState('.kiosk-service-mode-btn', modeRegularBtn, 'btn-primary', 'btn-outline-secondary');
        }
        customerId.value = nextCustomerId;
        if (customerSelection) {
            customerSelection.value = customerId.value ? 'saved' : 'walk_in';
        }
        selectedCustomerLabel = btn.textContent?.trim() || 'Walk-in customer';
        setButtonState('.kiosk-customer-btn', btn, 'btn-primary', 'btn-outline-secondary');
        syncRewardMode();
        updateSummary();
        maybePromptReward(btn);
    };
    const refreshCustomerSearchState = () => {
        if (!customerSearch || !customerCards) return;
        const q = (customerSearch.value || '').trim().toLowerCase();
        const buttons = Array.from(customerCards.querySelectorAll('.kiosk-customer-btn'));
        let matchCount = 0;
        buttons.forEach((btn, idx) => {
            if (idx === 0) {
                btn.classList.remove('d-none');
                return;
            }
            const txt = (btn.textContent || '').toLowerCase();
            if (q === '') {
                btn.classList.toggle('d-none', (btn.dataset.topTen || '0') !== '1');
                return;
            }
            const matched = txt.includes(q);
            btn.classList.toggle('d-none', !matched);
            if (matched) matchCount++;
        });
        if (customerNoMatchWrap && customerNoMatchText) {
            const hasSearch = q !== '';
            customerNoMatchWrap.classList.toggle('d-none', !(hasSearch && matchCount === 0));
            customerNoMatchText.textContent = (customerSearch.value || '').trim();
        }
    };
    customerCards?.addEventListener('click', (e) => {
        const btn = e.target.closest('.kiosk-customer-btn');
        if (!(btn instanceof HTMLButtonElement) || !customerCards.contains(btn)) return;
        handleCustomerPick(btn);
    });

    document.querySelectorAll('.kiosk-service-mode-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (btn.disabled) return;
            if (!hasExplicitCustomerSelection()) {
                warnCustomerRequired();
                return;
            }
            const pickedMode = btn.dataset.mode || 'regular';
            if (pickedMode === 'reward') {
                applyRewardRedemption();
                return;
            }
            clearRewardRedemption();
            serviceMode.value = pickedMode;
            setButtonState('.kiosk-service-mode-btn', btn, 'btn-primary', 'btn-outline-secondary');
            syncRewardMode();
            syncSubmitButtonsByMode();
            computeSupplyStep();
            renderStep();
        });
    });

    customerSearch?.addEventListener('input', refreshCustomerSearchState);
    const openCreateCustomerModal = () => {
        if (!createCustomerModal || !createCustomerForm) return;
        createCustomerForm.reset();
        if (createCustomerName && customerSearch) {
            createCustomerName.value = (customerSearch.value || '').trim();
        }
        createCustomerModal.show();
    };
    customerNoMatchCreateBtn?.addEventListener('click', openCreateCustomerModal);
    createCustomerForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(createCustomerForm);
        formData.set('_token', csrfToken);
        const pendingName = String(formData.get('name') || '').trim();
        if (createCustomerSubmitBtn) createCustomerSubmitBtn.disabled = true;
        try {
            const res = await fetch('<?= e(route('tenant.laundry-sales.customers.store')) ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: formData,
            });
            const { data } = await parseJsonBody(res);
            const payload = (data && typeof data === 'object' && data.data && typeof data.data === 'object') ? data.data : data;
            const customer = (payload && typeof payload === 'object' && payload.customer && typeof payload.customer === 'object') ? payload.customer : {};
            const success = Boolean(payload && (payload.success === true || customer.id || customer.name));
            if (!res.ok || !success) {
                const fallback = !res.ok ? `Request failed (${res.status}).` : 'Could not create customer.';
                const message = typeof payload?.message === 'string' && payload.message.trim() !== '' ? payload.message : fallback;
                showWarn(message);
                return;
            }
            const createdId = String(customer.id || '').trim();
            const createdName = String(customer.name || pendingName).trim();
            if (createdId !== '' && createdName !== '' && customerCards) {
                let createdBtn = customerCards.querySelector(`.kiosk-customer-btn[data-id="${createdId}"]`);
                if (!(createdBtn instanceof HTMLButtonElement)) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-outline-secondary kiosk-customer-btn kiosk-selectable-btn';
                    btn.dataset.id = createdId;
                    btn.dataset.topTen = '1';
                    btn.dataset.rewardsBalance = String(customer.rewards_balance || 0);
                    btn.textContent = createdName;
                    customerCards.appendChild(btn);
                    createdBtn = btn;
                }
                if (customerSearch) customerSearch.value = createdName;
                refreshCustomerSearchState();
                handleCustomerPick(createdBtn);
            }
            createCustomerModal.hide();
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Customer created',
                    text: 'Customer is now selected for this transaction.',
                    confirmButtonColor: '#198754',
                });
            }
        } finally {
            if (createCustomerSubmitBtn) createCustomerSubmitBtn.disabled = false;
        }
    });
    createCustomerModalEl?.addEventListener('hidden.bs.modal', () => {
        createCustomerForm?.reset();
    });

    foldNoBtn?.addEventListener('click', () => {
        foldService.value = '0';
        setButtonState('#kioskFoldNoBtn, #kioskFoldYesBtn', foldNoBtn);
        syncFoldQtyVisibility();
        updateSummary();
    });
    foldYesBtn?.addEventListener('click', () => {
        if (foldQtyInput) {
            const currentQty = Math.max(0, parseInt(foldQtyInput.value || '0', 10) || 0);
            foldQtyInput.value = String(currentQty + 1);
        }
        foldService.value = '1';
        setButtonState('#kioskFoldNoBtn, #kioskFoldYesBtn', foldYesBtn);
        syncFoldQtyVisibility();
        updateSummary();
    });
    [addonDetQtyInput, addonFabQtyInput, addonBleachQtyInput, addonOtherQtyInput].forEach((el) => {
        el?.addEventListener('change', updateSummary);
        el?.addEventListener('input', updateSummary);
    });
    serviceWeightInput?.addEventListener('change', updateSummary);
    serviceWeightInput?.addEventListener('input', updateSummary);
    actualWeightInput?.addEventListener('change', updateSummary);
    actualWeightInput?.addEventListener('input', updateSummary);
    document.querySelectorAll('.kiosk-payment-timing-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!hasExplicitCustomerSelection()) {
                warnCustomerRequired();
                return;
            }
            if (!paymentTiming) return;
            paymentTiming.value = btn.getAttribute('data-timing') || 'pay_later';
            document.querySelectorAll('.kiosk-payment-timing-btn').forEach((x) => {
                const isNow = (x.getAttribute('data-timing') || '') === 'pay_now';
                x.classList.remove('btn-primary', 'btn-outline-success', 'btn-outline-secondary');
                x.classList.add(isNow ? 'btn-outline-success' : 'btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-success', 'btn-outline-secondary');
            btn.classList.add('btn-primary');
            updateSummary();
        });
    });

    const bindCardChoice = (selector, onPick, activeCls = 'btn-primary', inactiveCls = 'btn-outline-primary', allowToggleOff = false) => {
        document.querySelectorAll(selector).forEach((btn) => {
            btn.addEventListener('click', () => {
                const isActive = btn.classList.contains(activeCls) && btn.classList.contains('kiosk-card-selected');
                if (allowToggleOff && isActive) {
                    setButtonState(selector, null, activeCls, inactiveCls);
                    onPick(null);
                    updateSummary();
                    return;
                }
                setButtonState(selector, btn, activeCls, inactiveCls);
                onPick(btn);
                updateSummary();
            });
        });
    };

    bindCardChoice('.kiosk-supply-det-btn', (btn) => {
        const nextId = btn.getAttribute('data-id') || '';
        const stock = Math.max(0, parseFloat(btn.getAttribute('data-stock-qty') || '0') || 0);
        const needed = inclusionQtyForKind('det');
        if (needed > stock + 1e-9) {
            void showWarn('Selected detergent stock is not enough for the current required quantity.');
            setButtonState('.kiosk-supply-det-btn', null);
            inclusionDet.value = '';
            return;
        }
        const sameSelected = nextId !== '' && inclusionDet.value === nextId;
        inclusionDet.value = nextId;
        if (sameSelected) {
            setInclusionQtyForKind('det', Math.min(stock, inclusionQtyForKind('det') + 1));
        } else {
            setInclusionQtyForKind('det', Math.max(1, Math.min(stock, needed || 1)));
        }
    });
    bindCardChoice('.kiosk-supply-fab-btn', (btn) => {
        const nextId = btn.getAttribute('data-id') || '';
        const stock = Math.max(0, parseFloat(btn.getAttribute('data-stock-qty') || '0') || 0);
        const needed = inclusionQtyForKind('fab');
        if (needed > stock + 1e-9) {
            void showWarn('Selected fabric conditioner stock is not enough for the current required quantity.');
            setButtonState('.kiosk-supply-fab-btn', null);
            inclusionFab.value = '';
            return;
        }
        const sameSelected = nextId !== '' && inclusionFab.value === nextId;
        inclusionFab.value = nextId;
        if (sameSelected) {
            setInclusionQtyForKind('fab', Math.min(stock, inclusionQtyForKind('fab') + 1));
        } else {
            setInclusionQtyForKind('fab', Math.max(1, Math.min(stock, needed || 1)));
        }
    });
    bindCardChoice('.kiosk-supply-bleach-btn', (btn) => {
        if (!btn) {
            inclusionBleach.value = '';
            setInclusionQtyForKind('bleach', 0);
            return;
        }
        const nextId = btn.getAttribute('data-id') || '';
        const stock = Math.max(0, parseFloat(btn.getAttribute('data-stock-qty') || '0') || 0);
        const needed = inclusionQtyForKind('bleach');
        if (needed > stock + 1e-9) {
            void showWarn('Selected bleach stock is not enough for the current required quantity.');
            setButtonState('.kiosk-supply-bleach-btn', null);
            inclusionBleach.value = '';
            return;
        }
        const sameSelected = nextId !== '' && inclusionBleach.value === nextId;
        inclusionBleach.value = nextId;
        if (sameSelected) {
            setInclusionQtyForKind('bleach', Math.min(stock, inclusionQtyForKind('bleach') + 1));
        } else {
            setInclusionQtyForKind('bleach', Math.max(1, Math.min(stock, needed || 1)));
        }
    }, 'btn-primary', 'btn-outline-primary', true);
    bindCardChoice('.kiosk-addon-det-btn', (btn) => {
        const nextId = btn ? (btn.getAttribute('data-id') || '') : '';
        const sameSelected = nextId !== '' && addonDetId.value === nextId;
        addonDetId.value = nextId;
        if (addonDetQtyInput) {
            if (!btn) {
                addonDetQtyInput.value = '';
            } else if (sameSelected) {
                const currentQty = Math.max(0, parseFloat(addonDetQtyInput.value || '0') || 0);
                addonDetQtyInput.value = String(currentQty + 1);
            } else {
                addonDetQtyInput.value = '1';
            }
        }
    }, 'btn-primary', 'btn-outline-primary', false);
    bindCardChoice('.kiosk-addon-fab-btn', (btn) => {
        const nextId = btn ? (btn.getAttribute('data-id') || '') : '';
        const sameSelected = nextId !== '' && addonFabId.value === nextId;
        addonFabId.value = nextId;
        if (addonFabQtyInput) {
            if (!btn) {
                addonFabQtyInput.value = '';
            } else if (sameSelected) {
                const currentQty = Math.max(0, parseFloat(addonFabQtyInput.value || '0') || 0);
                addonFabQtyInput.value = String(currentQty + 1);
            } else {
                addonFabQtyInput.value = '1';
            }
        }
    }, 'btn-primary', 'btn-outline-primary', false);
    bindCardChoice('.kiosk-addon-bleach-btn', (btn) => {
        const nextId = btn ? (btn.getAttribute('data-id') || '') : '';
        const sameSelected = nextId !== '' && addonBleachId.value === nextId;
        addonBleachId.value = nextId;
        if (addonBleachQtyInput) {
            if (!btn) {
                addonBleachQtyInput.value = '';
            } else if (sameSelected) {
                const currentQty = Math.max(0, parseFloat(addonBleachQtyInput.value || '0') || 0);
                addonBleachQtyInput.value = String(currentQty + 1);
            } else {
                addonBleachQtyInput.value = '1';
            }
        }
    }, 'btn-primary', 'btn-outline-primary', false);
    bindCardChoice('.kiosk-addon-other-btn', (btn) => {
        const nextId = btn ? (btn.getAttribute('data-id') || '') : '';
        const sameSelected = nextId !== '' && addonOtherId.value === nextId;
        addonOtherId.value = nextId;
        if (addonOtherQtyInput) {
            if (!btn) {
                addonOtherQtyInput.value = '';
            } else if (sameSelected) {
                const currentQty = Math.max(0, parseFloat(addonOtherQtyInput.value || '0') || 0);
                addonOtherQtyInput.value = String(currentQty + 1);
            } else {
                addonOtherQtyInput.value = '1';
            }
        }
    }, 'btn-primary', 'btn-outline-primary', false);
    let trackGasulPrefSaving = false;
    let trackGasulPrefSeq = 0;
    const saveTrackGasulUsagePreference = async (enabled) => {
        if (!trackGasulToggle || trackGasulPrefSaving) return true;
        trackGasulPrefSaving = true;
        const reqSeq = ++trackGasulPrefSeq;
        const previousDisabled = trackGasulToggle.disabled;
        trackGasulToggle.disabled = true;
        try {
            const payload = new FormData();
            payload.set('_token', csrfToken);
            payload.set('track_gasul_usage', enabled ? '1' : '0');
            const res = await fetch('<?= e(route('tenant.laundry-sales.track-gasul')) ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: payload,
            });
            const { data } = await parseJsonBody(res);
            const success = !!(res.ok && data && (data.success === true || data.status === 'ok'));
            if (!success) {
                throw new Error((data && typeof data.message === 'string' && data.message.trim() !== '') ? data.message : 'Could not save Track Gasul Usage setting.');
            }
            return true;
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Could not save Track Gasul Usage setting.';
            if (typeof Swal !== 'undefined') {
                await Swal.fire({ icon: 'warning', title: 'Setting not saved', text: message, confirmButtonColor: '#dc3545' });
            } else {
                showWarn(message);
            }
            return false;
        } finally {
            if (reqSeq === trackGasulPrefSeq && trackGasulToggle) {
                trackGasulToggle.disabled = previousDisabled;
            }
            trackGasulPrefSaving = false;
        }
    };
    const syncTrackGasulUI = () => {
        const enabled = trackGasulToggle
            ? (trackGasulToggle.checked === true)
            : String(trackGasulHidden?.value || (kioskTrackGasulSettingEnabled ? '1' : '0')) === '1';
        if (trackGasulHidden) {
            trackGasulHidden.value = enabled ? '1' : '0';
        }
        const gasulBtns = Array.from(document.querySelectorAll('.kiosk-addon-other-btn[data-gasul-item="1"]'));
        const selectedId = String(addonOtherId?.value || '').trim();
        const selectedBtn = selectedId !== ''
            ? document.querySelector(`.kiosk-addon-other-btn[data-id="${selectedId}"]`)
            : null;
        gasulBtns.forEach((btn) => {
            btn.classList.toggle('d-none', !enabled);
        });
        if (!enabled && selectedBtn instanceof HTMLElement && selectedBtn.getAttribute('data-gasul-item') === '1') {
            addonOtherId.value = '';
            addonOtherQty.value = '0';
            if (addonOtherQtyInput) addonOtherQtyInput.value = '';
            setButtonState('.kiosk-addon-other-btn', null);
        }
        updateSummary();
    };
    trackGasulToggle?.addEventListener('change', async () => {
        if (!kioskOwnerCanSetTrackGasul) {
            trackGasulToggle.checked = !trackGasulToggle.checked;
            return;
        }
        const enabled = trackGasulToggle.checked === true;
        syncTrackGasulUI();
        const saved = await saveTrackGasulUsagePreference(enabled);
        if (!saved) {
            trackGasulToggle.checked = !enabled;
            syncTrackGasulUI();
        }
    });
    syncTrackGasulUI();

    form?.addEventListener('submit', (e) => {
        if (!validateBeforeSubmit()) {
            e.preventDefault();
            return;
        }
        const totals = getCurrentTotals();
        if (serviceMode.value === 'free' || (serviceMode.value === 'reward' && totals.totalPrice <= 1e-9)) {
            e.preventDefault();
            if (paymentMethod) paymentMethod.value = 'pending';
            if (paymentTiming) paymentTiming.value = 'pay_later';
            if (amountTenderedHidden) amountTenderedHidden.value = '';
            if (changeAmountHidden) changeAmountHidden.value = '';
            if (discountPercentageHidden) discountPercentageHidden.value = '0';
            resetSplitPaymentValues();
            submitKioskOrder();
            return;
        }
        if ((paymentTiming?.value || 'pay_later') === 'pay_now') {
            e.preventDefault();
            if (discountPercentageInput) {
                discountPercentageInput.value = '0';
            }
            if (discountPercentageHidden) {
                discountPercentageHidden.value = '0';
            }
            if (amountPaidInput) {
                amountPaidInput.value = getPaymentDue().toFixed(2);
            }
            updatePayNowAmountsUI();
            paymentMethodModal?.show();
            return;
        }
        e.preventDefault();
        if (paymentMethod) paymentMethod.value = 'pending';
        if (amountTenderedHidden) amountTenderedHidden.value = '';
        if (changeAmountHidden) changeAmountHidden.value = '';
        if (discountPercentageHidden) discountPercentageHidden.value = '0';
        resetSplitPaymentValues();
        submitKioskOrder();
    });
    document.querySelectorAll('.kiosk-pay-method-option').forEach((btn) => {
        btn.addEventListener('click', () => {
            selectedPayNowMethod = btn.getAttribute('data-method') || 'cash';
            document.querySelectorAll('.kiosk-pay-method-option').forEach((x) => {
                x.classList.remove('btn-primary');
                x.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');
            const isSplit = selectedPayNowMethod === 'split_payment';
            splitFieldsWrap?.classList.toggle('d-none', !isSplit);
            if (!isSplit) {
                resetSplitPaymentValues();
            }
            updatePayNowAmountsUI();
        });
    });
    amountPaidInput?.addEventListener('input', updatePayNowAmountsUI);
    discountPercentageInput?.addEventListener('input', updatePayNowAmountsUI);
    splitCashAmountInput?.addEventListener('input', updatePayNowAmountsUI);
    splitOnlineAmountInput?.addEventListener('input', updatePayNowAmountsUI);
    paymentMethodConfirmBtn?.addEventListener('click', () => {
        if (!form || !paymentMethod) return;
        const dueAmount = getPaymentDue();
        if (discountPercentageHidden) {
            discountPercentageHidden.value = getDiscountPercentage().toFixed(2);
        }
        if (selectedPayNowMethod === 'split_payment') {
            const cashAmount = Math.max(0, parseFloat(splitCashAmountInput?.value || '0') || 0);
            const onlineAmount = Math.max(0, parseFloat(splitOnlineAmountInput?.value || '0') || 0);
            const onlineMethod = String(splitOnlineMethodSelect?.value || '').trim();
            const expectedTotal = dueAmount;
            if (cashAmount <= 0 || onlineAmount <= 0) {
                showWarn('For Split Payment, enter both Cash amount and Online amount.');
                return;
            }
            if (!onlineMethod) {
                showWarn('Select an online payment method for Split Payment.');
                return;
            }
            if (Math.abs((cashAmount + onlineAmount) - expectedTotal) > 0.009) {
                showWarn(`Split total must equal the transaction total (₱${expectedTotal.toFixed(2)}).`);
                return;
            }
            if (splitCashAmountHidden) splitCashAmountHidden.value = cashAmount.toFixed(2);
            if (splitOnlineAmountHidden) splitOnlineAmountHidden.value = onlineAmount.toFixed(2);
            if (splitOnlineMethodHidden) splitOnlineMethodHidden.value = onlineMethod;
            if (amountTenderedHidden) amountTenderedHidden.value = (cashAmount + onlineAmount).toFixed(2);
            if (changeAmountHidden) changeAmountHidden.value = Math.max(0, (cashAmount + onlineAmount) - dueAmount).toFixed(2);
        } else {
            const paidAmount = Math.max(0, parseFloat(amountPaidInput?.value || '0') || 0);
            if (paidAmount + 1e-9 < dueAmount) {
                showWarn(`Amount paid must be at least the total amount (₱${dueAmount.toFixed(2)}).`);
                return;
            }
            if (amountTenderedHidden) amountTenderedHidden.value = paidAmount.toFixed(2);
            if (changeAmountHidden) changeAmountHidden.value = Math.max(0, paidAmount - dueAmount).toFixed(2);
            resetSplitPaymentValues();
        }
        paymentMethod.value = selectedPayNowMethod || 'cash';
        paymentMethodModal?.hide();
        submitKioskOrder();
    });
    paymentMethodModalEl?.addEventListener('hidden.bs.modal', () => {
        splitFieldsWrap?.classList.add('d-none');
        updatePayNowAmountsUI();
    });
    foldNoBtn?.click();
    switchOrderMode('drop_off');
    syncFoldOnlyPriceOverrides();
    refreshCustomerSearchState();
    syncRewardMode();
    syncSubmitButtonsByMode();
    computeSupplyStep();
    computeMachineNeed();
    renderStep();
})();
</script>
