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
    .kiosk-item-card img {
        display: block;
        margin: 0 auto;
    }
    .kiosk-card-selected {
        border-width: 2px !important;
        border-color: #0d6efd !important;
        background: linear-gradient(180deg, rgba(13,110,253,0.16), rgba(13,110,253,0.08)) !important;
        box-shadow: 0 0 0 2px rgba(13,110,253,0.20), 0 10px 18px -12px rgba(13,110,253,0.45);
        transform: translateY(-1px);
    }
    .kiosk-card-selected-low-stock {
        border-width: 2px !important;
        border-color: #dc3545 !important;
        background: linear-gradient(180deg, rgba(220,53,69,0.22), rgba(220,53,69,0.10)) !important;
        box-shadow: 0 0 0 2px rgba(220,53,69,0.20), 0 10px 18px -12px rgba(220,53,69,0.45);
    }
    .kiosk-summary-receipt {
        border: 1px solid #7dd3fc;
        border-radius: 10px;
        padding: 0.75rem;
        background: linear-gradient(180deg, rgba(34, 211, 238, 0.18), rgba(59, 130, 246, 0.08));
        font-size: 0.92rem;
    }
    .kiosk-summary-receipt .line {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.2rem 0;
        border-bottom: 1px dotted rgba(2, 132, 199, 0.35);
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
</style>

<form method="POST" action="<?= e(route('tenant.laundry-sales.store')) ?>" id="staffKioskOrderForm" data-mpg-native-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="origin" value="staff_portal">
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
    <input type="hidden" name="split_cash_amount" id="kioskSplitCashAmount" value="">
    <input type="hidden" name="split_online_amount" id="kioskSplitOnlineAmount" value="">
    <input type="hidden" name="split_online_method" id="kioskSplitOnlineMethod" value="">
    <input type="hidden" name="track_laundry_status" id="kioskTrackLaundryStatus" value="<?= ! empty($laundry_status_tracking_enabled) ? '1' : '0' ?>">
    <input type="hidden" name="use_machines" id="kioskUseMachines" value="0">
    <input type="hidden" name="service_weight" id="kioskServiceWeightHidden" value="">
    <input type="hidden" name="wash_qty" value="1">
    <input type="hidden" name="dry_qty" value="1">
    <input type="hidden" name="include_fold_service" id="kioskFoldService" value="0">
    <input type="hidden" name="inclusion_detergent_item_id" id="kioskInclusionDetergent" value="">
    <input type="hidden" name="inclusion_fabcon_item_id" id="kioskInclusionFabcon" value="">
    <input type="hidden" name="inclusion_bleach_item_id" id="kioskInclusionBleach" value="">
    <input type="hidden" name="addon_detergent_item_id" id="kioskAddonDetergentId" value="">
    <input type="hidden" name="addon_fabcon_item_id" id="kioskAddonFabconId" value="">
    <input type="hidden" name="addon_bleach_item_id" id="kioskAddonBleachId" value="">
    <input type="hidden" name="detergent_qty" id="kioskAddonDetergentQty" value="0">
    <input type="hidden" name="fabcon_qty" id="kioskAddonFabconQty" value="0">
    <input type="hidden" name="bleach_qty" id="kioskAddonBleachQty" value="0">

    <div class="small text-muted mb-2">Single-page mode: tap selections below, then submit via Pay Now or Pay Later.</div>

    <div class="card mb-3 kiosk-step" data-step="1">
        <div class="card-body">
            <h6 class="mb-2">Order type</h6>
            <div class="d-flex flex-wrap gap-2 mb-3" id="kioskOrderTypeCards">
                <?php foreach ($orderTypes as $ot): ?>
                    <button
                        type="button"
                        class="btn btn-outline-primary kiosk-order-type-btn kiosk-selectable-btn kiosk-item-card"
                        data-code="<?= e((string) ($ot['code'] ?? '')) ?>"
                        data-service-kind="<?= e((string) ($ot['service_kind'] ?? 'full_service')) ?>"
                        data-supply-block="<?= e((string) ($ot['supply_block'] ?? 'none')) ?>"
                        data-show-addon-supplies="<?= ! empty($ot['show_addon_supplies']) ? '1' : '0' ?>"
                        data-required-weight="<?= (! empty($ot['required_weight']) || (string) ($ot['service_kind'] ?? '') === 'dry_cleaning') ? '1' : '0' ?>"
                        data-price-per-load="<?= e((string) (float) ($ot['price_per_load'] ?? 0)) ?>"
                    >
                        <img src="<?= e($orderTypeCardImageSrc($ot)) ?>" alt="<?= e((string) ($ot['label'] ?? 'Order type')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                        <div class="small fw-semibold"><?= e((string) ($ot['label'] ?? 'Order')) ?></div>
                    </button>
                <?php endforeach; ?>
            </div>

            <h6 class="mb-2">Customer</h6>
            <?php if ($freeCustomerLocked): ?>
                <div class="alert alert-warning py-2 small mb-2">
                    <strong>Free Mode:</strong> transactions are always assigned to Walk-in customer.
                </div>
            <?php else: ?>
                <input type="search" class="form-control mb-2" id="kioskCustomerSearch" placeholder="Search customer name">
                <div class="small text-muted mb-2">Top 10 customers by transactions are shown. Search to find the rest.</div>
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
            <div class="row g-2 mt-2">
                <div class="col-md-4">
                    <label class="form-label mb-1" for="kioskNumberOfLoads">Number of loads</label>
                    <input type="number" min="1" max="100" step="1" class="form-control" id="kioskNumberOfLoads" name="number_of_loads" value="1" required>
                    <div class="small text-muted mt-1">Estimated Total: <strong id="kioskEstimatedTotalAmount">₱0.00</strong></div>
                </div>
                <div class="col-md-4 d-none" id="kioskWeightWrap">
                    <label class="form-label mb-1" for="kioskServiceWeightInput">Weight</label>
                    <input type="number" min="0.01" step="0.01" class="form-control" id="kioskServiceWeightInput" placeholder="e.g. 7.5">
                </div>
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
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-supply-det-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Detergent')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="kioskSupplyFabWrap">
                    <label class="form-label mb-1">Fabric conditioner</label>
                    <div class="d-flex flex-wrap gap-2" id="kioskSupplyFabCards">
                        <?php foreach ($fabconItems as $item): ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-supply-fab-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Fabcon')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="kioskSupplyBleachWrap">
                    <label class="form-label mb-1">Bleach (optional)</label>
                    <div class="d-flex flex-wrap gap-2" id="kioskSupplyBleachCards">
                        <?php foreach ($bleachItems as $item): ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-supply-bleach-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Bleach')) ?>" class="rounded border mb-1" style="width:52px;height:52px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                            </button>
                        <?php endforeach; ?>
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
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-det-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Detergent')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="number" min="0" step="0.01" class="form-control mt-2" id="kioskAddonDetQtyInput" placeholder="Qty">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on fabcon</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonFabCards">
                        <?php foreach ($fabconItems as $item): ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-fab-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Fabcon')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="number" min="0" step="0.01" class="form-control mt-2" id="kioskAddonFabQtyInput" placeholder="Qty">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on bleach</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonBleachCards">
                        <?php foreach ($bleachItems as $item): ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-bleach-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-unit-cost="<?= e((string) (float) ($item['unit_cost'] ?? 0)) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
                                <img src="<?= e($inventoryCardImageSrc($item)) ?>" alt="<?= e((string) ($item['name'] ?? 'Bleach')) ?>" class="rounded border mb-1" style="width:44px;height:44px;object-fit:cover;">
                                <div class="small fw-semibold"><?= e((string) ($item['name'] ?? '')) ?></div>
                                <div class="small text-muted kiosk-item-stock">Stock: <?= e($stockQtyLabel((float) ($item['stock_quantity'] ?? 0))) ?></div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="number" min="0" step="0.01" class="form-control mt-2" id="kioskAddonBleachQtyInput" placeholder="Qty">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 kiosk-step" data-step="5">
        <div class="card-body">
            <h6 class="mb-2">Include Fold Service?</h6>
            <p class="small text-muted mb-3">Available for all order types.</p>
            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Include fold service">
                <button type="button" class="btn btn-outline-primary kiosk-selectable-btn" id="kioskFoldNoBtn">No</button>
                <button type="button" class="btn btn-outline-primary kiosk-selectable-btn" id="kioskFoldYesBtn">Yes</button>
            </div>
        </div>
    </div>

    <div class="card kiosk-step" data-step="6">
        <div class="card-body">
            <h6 class="mb-2">Summary</h6>
            <div class="small text-muted mb-2">Review before saving.</div>
            <div class="fw-semibold" id="kioskSelectionSummary">No selection yet.</div>
            <div class="mt-3">
                <div class="d-flex flex-wrap gap-2" role="group" aria-label="Payment timing" id="kioskRegularSubmitWrap">
                    <button type="submit" class="btn btn-outline-success kiosk-payment-timing-btn" data-timing="pay_now">Pay Now</button>
                    <button type="submit" class="btn btn-primary kiosk-payment-timing-btn" data-timing="pay_later">Pay Later</button>
                </div>
                <div class="d-none" id="kioskFreeRewardSubmitWrap">
                    <button type="submit" class="btn btn-primary" id="kioskSaveTransactionBtn">Save Transaction</button>
                </div>
            </div>
        </div>
    </div>

</form>

<div class="modal fade" id="kioskPaymentMethodModal" tabindex="-1" aria-labelledby="kioskPaymentMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title" id="kioskPaymentMethodModalLabel">Select payment method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="rounded-3 bg-body-secondary bg-opacity-50 p-3 mb-3">
                    <div class="small text-muted mb-0">Amount to pay</div>
                    <div class="fs-5 fw-semibold font-monospace" id="kioskPayNowDueDisplay">₱0.00</div>
                </div>
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
                        <label class="form-label mb-1" for="kioskAmountPaidInput">Amount paid</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="kioskAmountPaidInput" placeholder="0.00">
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
    const kioskReferenceCode = <?= json_embed($referencePreview) ?>;

    const steps = Array.from(document.querySelectorAll('.kiosk-step'));
    const paymentMethod = document.getElementById('kioskPaymentMethod');
    const paymentMethodModalEl = document.getElementById('kioskPaymentMethodModal');
    const paymentMethodModal = paymentMethodModalEl ? new bootstrap.Modal(paymentMethodModalEl) : null;
    const paymentMethodConfirmBtn = document.getElementById('kioskPaymentMethodConfirmBtn');
    const amountTenderedHidden = document.getElementById('kioskAmountTendered');
    const changeAmountHidden = document.getElementById('kioskChangeAmount');
    const payNowDueDisplay = document.getElementById('kioskPayNowDueDisplay');
    const singlePaymentFieldsWrap = document.getElementById('kioskSinglePaymentFields');
    const amountPaidInput = document.getElementById('kioskAmountPaidInput');
    const changeDisplay = document.getElementById('kioskChangeDisplay');
    const splitCashAmountHidden = document.getElementById('kioskSplitCashAmount');
    const splitOnlineAmountHidden = document.getElementById('kioskSplitOnlineAmount');
    const splitOnlineMethodHidden = document.getElementById('kioskSplitOnlineMethod');
    const splitFieldsWrap = document.getElementById('kioskSplitPaymentFields');
    const splitCashAmountInput = document.getElementById('kioskSplitCashAmountInput');
    const splitOnlineAmountInput = document.getElementById('kioskSplitOnlineAmountInput');
    const splitOnlineMethodSelect = document.getElementById('kioskSplitOnlineMethodSelect');
    const regularSubmitWrap = document.getElementById('kioskRegularSubmitWrap');
    const freeRewardSubmitWrap = document.getElementById('kioskFreeRewardSubmitWrap');
    const orderTypeCode = document.getElementById('kioskOrderTypeCode');
    const serviceMode = document.getElementById('kioskServiceMode');
    const rewardRedemption = document.getElementById('kioskRewardRedemption');
    const customerId = document.getElementById('kioskCustomerId');
    const customerSelection = document.getElementById('kioskCustomerSelection');
    const paymentTiming = document.getElementById('kioskPaymentTiming');
    const numberOfLoadsInput = document.getElementById('kioskNumberOfLoads');
    const serviceWeightHidden = document.getElementById('kioskServiceWeightHidden');
    const serviceWeightInput = document.getElementById('kioskServiceWeightInput');
    const weightWrap = document.getElementById('kioskWeightWrap');
    const foldService = document.getElementById('kioskFoldService');
    const inclusionDet = document.getElementById('kioskInclusionDetergent');
    const inclusionFab = document.getElementById('kioskInclusionFabcon');
    const inclusionBleach = document.getElementById('kioskInclusionBleach');
    const summary = document.getElementById('kioskSelectionSummary');
    const estimatedTotalAmount = document.getElementById('kioskEstimatedTotalAmount');
    const customerSearch = document.getElementById('kioskCustomerSearch');
    const customerCards = document.getElementById('kioskCustomerCards');
    const form = document.getElementById('staffKioskOrderForm');
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
    const addonDetQty = document.getElementById('kioskAddonDetergentQty');
    const addonFabQty = document.getElementById('kioskAddonFabconQty');
    const addonBleachQty = document.getElementById('kioskAddonBleachQty');
    const addonDetQtyInput = document.getElementById('kioskAddonDetQtyInput');
    const addonFabQtyInput = document.getElementById('kioskAddonFabQtyInput');
    const addonBleachQtyInput = document.getElementById('kioskAddonBleachQtyInput');
    const foldNoBtn = document.getElementById('kioskFoldNoBtn');
    const foldYesBtn = document.getElementById('kioskFoldYesBtn');
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
    let selectedShowAddonSupplies = false;
    let selectedRequiredWeight = false;
    let selectedPricePerLoad = 0;
    let selectedCustomerLabel = 'No customer selected';
    let selectedPayNowMethod = 'cash';
    let isSubmittingOrder = false;
    let rewardPromptOpen = false;
    let rewardIntentCustomerId = '';
    const rewardThreshold = parseFloat(modeRewardBtn?.getAttribute('data-reward-threshold') || '0') || 0;
    const rewardOrderTypeCode = modeRewardBtn?.getAttribute('data-reward-order-type') || '';

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

    const isRewardAvailable = () => {
        const balance = selectedRewardBalance();
        return rewardThreshold > 0 && rewardOrderTypeCode !== '' && balance + 1e-9 >= rewardThreshold;
    };

    const clearRewardRedemption = () => {
        if (rewardRedemption) rewardRedemption.value = '0';
        rewardIntentCustomerId = '';
    };

    const syncRewardMode = () => {
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
                rewardStatus.textContent = `No reward available yet (${balance.toFixed(2)} / ${rewardThreshold.toFixed(2)}).`;
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

    const recomputeVisibleSteps = () => {
        steps.forEach((el) => {
            const step = String(el.getAttribute('data-step') || '').trim();
            if (step === '3') {
                // Show Add-ons section only when selected order type enables it.
                const showAddons = selectedShowAddonSupplies === true;
                el.classList.toggle('d-none', !showAddons);
                if (!showAddons) {
                    addonDetId.value = '';
                    addonFabId.value = '';
                    addonBleachId.value = '';
                    addonDetQty.value = '0';
                    addonFabQty.value = '0';
                    addonBleachQty.value = '0';
                    if (addonDetQtyInput) addonDetQtyInput.value = '';
                    if (addonFabQtyInput) addonFabQtyInput.value = '';
                    if (addonBleachQtyInput) addonBleachQtyInput.value = '';
                    setButtonState('.kiosk-addon-det-btn', null);
                    setButtonState('.kiosk-addon-fab-btn', null);
                    setButtonState('.kiosk-addon-bleach-btn', null);
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
        const byId = (selector) => {
            const map = new Map();
            document.querySelectorAll(selector).forEach((btn) => {
                const id = String(btn.getAttribute('data-id') || '');
                if (id !== '' && !map.has(id)) map.set(id, btn);
            });
            return map;
        };

        const syncCategory = (supplySelector, addonSelector, inclusionInput, addonInput, addonQtyInputEl) => {
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
                const inclusionReserved = selectedInclusionId === selectedAddonId ? 1 : 0;
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
                const inclusionReserved = selectedInclusionId === id ? 1 : 0;
                const addonReserved = selectedAddonId === id ? selectedAddonQty : 0;

                const supplyRemainingIfPicked = stock - addonReserved;
                const addonRemainingIfPicked = stock - inclusionReserved;

                supplyBtns.forEach((btn) => {
                    if (String(btn.getAttribute('data-id') || '') !== id) return;
                    const isSelected = String(inclusionInput?.value || '') === id;
                    const shouldDisable = !isSelected && supplyRemainingIfPicked <= 1e-9;
                    btn.disabled = shouldDisable;
                    btn.classList.toggle('disabled', shouldDisable);
                });
                addonBtns.forEach((btn) => {
                    if (String(btn.getAttribute('data-id') || '') !== id) return;
                    const isSelected = String(addonInput?.value || '') === id;
                    const shouldDisable = !isSelected && addonRemainingIfPicked <= 1e-9;
                    btn.disabled = shouldDisable;
                    btn.classList.toggle('disabled', shouldDisable);
                });
            });
        };

        syncCategory('.kiosk-supply-det-btn', '.kiosk-addon-det-btn', inclusionDet, addonDetId, addonDetQtyInput);
        syncCategory('.kiosk-supply-fab-btn', '.kiosk-addon-fab-btn', inclusionFab, addonFabId, addonFabQtyInput);
        syncCategory('.kiosk-supply-bleach-btn', '.kiosk-addon-bleach-btn', inclusionBleach, addonBleachId, addonBleachQtyInput);
    };

    const getCurrentTotals = () => {
        const loadsLabel = Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);
        const weightValue = Math.max(0, parseFloat(serviceWeightInput?.value || '0') || 0);
        const addonDetBtn = document.querySelector(`.kiosk-addon-det-btn[data-id="${addonDetId.value}"]`);
        const addonFabBtn = document.querySelector(`.kiosk-addon-fab-btn[data-id="${addonFabId.value}"]`);
        const addonBleachBtn = document.querySelector(`.kiosk-addon-bleach-btn[data-id="${addonBleachId.value}"]`);
        const addOnTotal = (serviceMode.value === 'free' || serviceMode.value === 'reward' || !selectedShowAddonSupplies)
            ? 0
            : (
                (parseFloat(addonDetQty.value || '0') || 0) * (parseFloat(addonDetBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonFabQty.value || '0') || 0) * (parseFloat(addonFabBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonBleachQty.value || '0') || 0) * (parseFloat(addonBleachBtn?.getAttribute('data-unit-cost') || '0') || 0)
            );
        const baseSubtotal = (serviceMode.value === 'free' || serviceMode.value === 'reward')
            ? 0
            : (selectedRequiredWeight ? (weightValue * selectedPricePerLoad) : (loadsLabel * selectedPricePerLoad));
        const totalPrice = baseSubtotal + addOnTotal;
        return { loadsLabel, weightValue, addOnTotal, baseSubtotal, totalPrice };
    };
    const formatPeso = (value) => `₱${(Math.max(0, Number(value) || 0)).toFixed(2)}`;
    const getPaymentDue = () => Math.max(0, Number(getCurrentTotals().totalPrice || 0));
    const updatePayNowAmountsUI = () => {
        const due = getPaymentDue();
        const isSplit = selectedPayNowMethod === 'split_payment';
        if (payNowDueDisplay) {
            payNowDueDisplay.textContent = formatPeso(due);
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

    const askPrintAfterSave = async (payload) => {
        let shouldPrint = false;
        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                icon: 'success',
                title: 'Transaction saved',
                text: 'Do you want to print via Bluetooth?',
                showCancelButton: true,
                confirmButtonText: 'Print via Bluetooth',
                cancelButtonText: 'No, Just Save',
                confirmButtonColor: '#0d6efd',
            });
            shouldPrint = !!result.isConfirmed;
        } else {
            shouldPrint = window.confirm('Transaction saved. Print via Bluetooth?');
        }
        if (!shouldPrint) return { printed: false };
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
            const isFreeOrReward = serviceMode.value === 'free' || serviceMode.value === 'reward';
            const paymentLabel = isFreeOrReward ? 'Paid' : ((paymentTiming?.value || 'pay_later') === 'pay_now' ? 'Paid' : 'Unpaid');
            const printPayload = {
                referenceCode: String(data.reference_code || kioskReferenceCode || '').trim(),
                customerName: selectedCustomerLabel || 'No customer selected',
                orderType: selectedOrderLabel || 'Order',
                modeLabel: (serviceMode.value || 'regular').toUpperCase(),
                paymentLabel,
                isFreeOrReward,
                totalPrice: totals.totalPrice,
                savedAt: String(data.saved_at || '').trim(),
            };
            let printResult = { printed: false };
            try {
                printResult = await askPrintAfterSave(printPayload);
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
        addonDetId.value = addonDetId.value || '';
        addonFabId.value = addonFabId.value || '';
        addonBleachId.value = addonBleachId.value || '';
        addonDetQty.value = String(Math.max(0, parseFloat(addonDetQtyInput?.value || '0') || 0));
        addonFabQty.value = String(Math.max(0, parseFloat(addonFabQtyInput?.value || '0') || 0));
        addonBleachQty.value = String(Math.max(0, parseFloat(addonBleachQtyInput?.value || '0') || 0));
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
        if (selectedShowAddonSupplies && serviceMode.value !== 'free' && serviceMode.value !== 'reward' && parseFloat(addonDetQty.value) > 0 && addonDetId.value) {
            addonParts.push(`Detergent: ${addonDetQty.value}x ${addonDetBtn?.getAttribute('data-name') || ''}`.trim());
        }
        if (selectedShowAddonSupplies && serviceMode.value !== 'free' && serviceMode.value !== 'reward' && parseFloat(addonFabQty.value) > 0 && addonFabId.value) {
            addonParts.push(`Fabcon: ${addonFabQty.value}x ${addonFabBtn?.getAttribute('data-name') || ''}`.trim());
        }
        if (selectedShowAddonSupplies && serviceMode.value !== 'free' && serviceMode.value !== 'reward' && parseFloat(addonBleachQty.value) > 0 && addonBleachId.value) {
            addonParts.push(`Bleach: ${addonBleachQty.value}x ${addonBleachBtn?.getAttribute('data-name') || ''}`.trim());
        }
        const summaryMode = rewardRedemption?.value === '1' ? 'REWARD (FREE)' : (serviceMode.value || 'regular').toUpperCase();
        const paymentLabel = (serviceMode.value === 'free' || serviceMode.value === 'reward')
            ? 'Paid'
            : (paymentTiming?.value === 'pay_now' ? 'Pay Now (Paid)' : 'Pay Later (Unpaid)');
        const loadsLabel = Math.max(1, parseInt(numberOfLoadsInput?.value || '1', 10) || 1);
        const weightValue = Math.max(0, parseFloat(serviceWeightInput?.value || '0') || 0);
        if (serviceWeightHidden) {
            serviceWeightHidden.value = selectedRequiredWeight && weightValue > 0 ? String(weightValue) : '';
        }
        const addOnTotal = (serviceMode.value === 'free' || serviceMode.value === 'reward' || !selectedShowAddonSupplies)
            ? 0
            : (
                (parseFloat(addonDetQty.value || '0') || 0) * (parseFloat(addonDetBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonFabQty.value || '0') || 0) * (parseFloat(addonFabBtn?.getAttribute('data-unit-cost') || '0') || 0)
                + (parseFloat(addonBleachQty.value || '0') || 0) * (parseFloat(addonBleachBtn?.getAttribute('data-unit-cost') || '0') || 0)
            );
        const baseSubtotal = (serviceMode.value === 'free' || serviceMode.value === 'reward')
            ? 0
            : (selectedRequiredWeight ? (weightValue * selectedPricePerLoad) : (loadsLabel * selectedPricePerLoad));
        const totalPrice = baseSubtotal + addOnTotal;
        if (estimatedTotalAmount) {
            estimatedTotalAmount.textContent = `₱${totalPrice.toFixed(2)}`;
        }
        summary.innerHTML = `
            <div class="kiosk-summary-receipt">
                <div class="line"><span class="label">Order Type</span><span class="value">${esc(selectedOrderLabel || 'No order type')}</span></div>
                <div class="line"><span class="label">Mode</span><span class="value">${esc(summaryMode)}</span></div>
                <div class="line"><span class="label">Loads</span><span class="value">${esc(loadsLabel)}</span></div>
                ${selectedRequiredWeight ? `<div class="line"><span class="label">Weight</span><span class="value">${esc(weightValue.toFixed(2))}</span></div>` : ''}
                <div class="line"><span class="label">Payment</span><span class="value">${esc(paymentLabel)}</span></div>
                <div class="line"><span class="label">Base Subtotal</span><span class="value">₱${esc(baseSubtotal.toFixed(2))}</span></div>
                <div class="line"><span class="label">Add-ons Total</span><span class="value">₱${esc(addOnTotal.toFixed(2))}</span></div>
                <div class="line"><span class="label">Total Price</span><span class="value">₱${esc(totalPrice.toFixed(2))}</span></div>
                <div class="line"><span class="label">Reference No.</span><span class="value">${esc(kioskReferenceCode || '-')}</span></div>
                <div class="line"><span class="label">Customer</span><span class="value">${esc(selectedCustomerLabel || 'No customer selected')}</span></div>
                <div class="line"><span class="label">Service Supplies</span><span class="value">${listHtml(supplyLabelParts)}</span></div>
                <div class="line"><span class="label">Add-ons</span><span class="value">${listHtml(addonParts)}</span></div>
                <div class="line"><span class="label">Include Fold Service</span><span class="value">${foldService.value === '1' ? 'Yes' : 'No'}</span></div>
            </div>
        `;
        updatePayNowAmountsUI();
    };
    const syncSubmitButtonsByMode = () => {
        const isFreeOrReward = serviceMode.value === 'free' || serviceMode.value === 'reward';
        regularSubmitWrap?.classList.toggle('d-none', isFreeOrReward);
        freeRewardSubmitWrap?.classList.toggle('d-none', !isFreeOrReward);
        if (isFreeOrReward && paymentTiming) {
            paymentTiming.value = 'pay_later';
        }
        if (isFreeOrReward && paymentMethod) {
            paymentMethod.value = 'pending';
        }
    };

    const computeSupplyStep = () => {
        const blk = selectedSupplyBlock;
        const noSupplies = blk === 'none';
        noSuppliesMessage?.classList.toggle('d-none', !noSupplies);
        supplyDetWrap?.classList.toggle('d-none', blk === 'none' || blk === 'rinse_supplies');
        supplyFabWrap?.classList.toggle('d-none', blk === 'none');
        supplyBleachWrap?.classList.toggle('d-none', blk === 'none' || blk === 'rinse_supplies');
        if (blk === 'none') {
            inclusionDet.value = '';
            inclusionFab.value = '';
            inclusionBleach.value = '';
            addonDetId.value = '';
            addonFabId.value = '';
            addonBleachId.value = '';
            addonDetQty.value = '0';
            addonFabQty.value = '0';
            addonBleachQty.value = '0';
            if (addonDetQtyInput) addonDetQtyInput.value = '';
            if (addonFabQtyInput) addonFabQtyInput.value = '';
            if (addonBleachQtyInput) addonBleachQtyInput.value = '';
            setButtonState('.kiosk-supply-det-btn', null);
            setButtonState('.kiosk-supply-fab-btn', null);
            setButtonState('.kiosk-supply-bleach-btn', null);
            setButtonState('.kiosk-addon-det-btn', null);
            setButtonState('.kiosk-addon-fab-btn', null);
            setButtonState('.kiosk-addon-bleach-btn', null);
        }
        recomputeVisibleSteps();
    };

    const renderStep = () => {
        recomputeVisibleSteps();
        updateSummary();
    };

    const validateStep = (step) => {
        if (step === 1 && !orderTypeCode.value) {
            warnAndFocus(
                'Please select an order type.',
                document.querySelector('.kiosk-order-type-btn'),
                document.getElementById('kioskOrderTypeCards')
            );
            return false;
        }
        if (step === 2) {
            if ((selectedSupplyBlock === 'full_service' || selectedSupplyBlock === 'wash_supplies') && (!inclusionDet.value || !inclusionFab.value)) {
                warnAndFocus(
                    'Please select detergent and fabcon.',
                    document.querySelector('.kiosk-supply-det-btn') || document.querySelector('.kiosk-supply-fab-btn'),
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
            warnAndFocus(
                'Please select an order type.',
                document.querySelector('.kiosk-order-type-btn'),
                document.getElementById('kioskOrderTypeCards')
            );
            return false;
        }
        const customerMode = (customerSelection?.value || '').trim();
        if (customerMode !== 'saved' && customerMode !== 'walk_in') {
            warnCustomerRequired();
            return false;
        }
        if ((selectedSupplyBlock === 'full_service' || selectedSupplyBlock === 'wash_supplies') && (!inclusionDet.value || !inclusionFab.value)) {
            warnAndFocus(
                'Please select detergent and fabcon before saving.',
                document.querySelector('.kiosk-supply-det-btn') || document.querySelector('.kiosk-supply-fab-btn'),
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

    document.querySelectorAll('.kiosk-order-type-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            orderTypeCode.value = btn.dataset.code || '';
            selectedOrderLabel = btn.textContent?.trim() || '';
            selectedServiceKind = btn.dataset.serviceKind || 'full_service';
            selectedSupplyBlock = btn.dataset.supplyBlock || defaultSupplyBlockForKind(selectedServiceKind);
            if (selectedSupplyBlock === 'none' && (selectedServiceKind === 'full_service' || selectedServiceKind === 'wash_only' || selectedServiceKind === 'rinse_only')) {
                selectedSupplyBlock = defaultSupplyBlockForKind(selectedServiceKind);
            }
            const addonRaw = (btn.dataset.showAddonSupplies || '').trim();
            selectedShowAddonSupplies = addonRaw === '1'
                || (addonRaw !== '0' && defaultShowAddonForKind(selectedServiceKind));
            selectedRequiredWeight = (btn.dataset.requiredWeight || '0') === '1' || selectedServiceKind === 'dry_cleaning';
            selectedPricePerLoad = parseFloat(btn.dataset.pricePerLoad || '0') || 0;
            if (weightWrap) weightWrap.classList.toggle('d-none', !selectedRequiredWeight);
            if (serviceWeightInput) {
                serviceWeightInput.required = selectedRequiredWeight;
                if (!selectedRequiredWeight) serviceWeightInput.value = '';
            }
            setButtonState('.kiosk-order-type-btn', btn);
            computeMachineNeed();
            computeSupplyStep();
            updateSummary();
        });
    });

    document.querySelectorAll('.kiosk-customer-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const nextCustomerId = btn.dataset.id || '';
            if (rewardIntentCustomerId !== '' && rewardIntentCustomerId !== nextCustomerId) {
                clearRewardRedemption();
                serviceMode.value = 'regular';
                setButtonState('.kiosk-service-mode-btn', modeRegularBtn, 'btn-primary', 'btn-outline-secondary');
            }
            customerId.value = btn.dataset.id || '';
            if (customerSelection) {
                customerSelection.value = customerId.value ? 'saved' : 'walk_in';
            }
            selectedCustomerLabel = btn.textContent?.trim() || 'Walk-in customer';
            setButtonState('.kiosk-customer-btn', btn, 'btn-primary', 'btn-outline-secondary');
            syncRewardMode();
            updateSummary();
            maybePromptReward(btn);
        });
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

    customerSearch?.addEventListener('input', () => {
        const q = (customerSearch.value || '').trim().toLowerCase();
        document.querySelectorAll('.kiosk-customer-btn').forEach((btn, idx) => {
            if (idx === 0) return; // keep walk-in always visible
            const txt = (btn.textContent || '').toLowerCase();
            if (q === '') {
                btn.classList.toggle('d-none', (btn.dataset.topTen || '0') !== '1');
                return;
            }
            btn.classList.toggle('d-none', !txt.includes(q));
        });
    });

    foldNoBtn?.addEventListener('click', () => {
        foldService.value = '0';
        setButtonState('#kioskFoldNoBtn, #kioskFoldYesBtn', foldNoBtn);
        updateSummary();
    });
    foldYesBtn?.addEventListener('click', () => {
        foldService.value = '1';
        setButtonState('#kioskFoldNoBtn, #kioskFoldYesBtn', foldYesBtn);
        updateSummary();
    });
    [addonDetQtyInput, addonFabQtyInput, addonBleachQtyInput].forEach((el) => {
        el?.addEventListener('change', updateSummary);
        el?.addEventListener('input', updateSummary);
    });
    numberOfLoadsInput?.addEventListener('focus', () => {
        if (!hasExplicitCustomerSelection()) {
            warnCustomerRequired();
        }
    });
    numberOfLoadsInput?.addEventListener('change', updateSummary);
    numberOfLoadsInput?.addEventListener('input', updateSummary);
    serviceWeightInput?.addEventListener('change', updateSummary);
    serviceWeightInput?.addEventListener('input', updateSummary);
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
        inclusionDet.value = btn.getAttribute('data-id') || '';
    });
    bindCardChoice('.kiosk-supply-fab-btn', (btn) => {
        inclusionFab.value = btn.getAttribute('data-id') || '';
    });
    bindCardChoice('.kiosk-supply-bleach-btn', (btn) => {
        inclusionBleach.value = btn ? (btn.getAttribute('data-id') || '') : '';
    }, 'btn-primary', 'btn-outline-primary', true);
    bindCardChoice('.kiosk-addon-det-btn', (btn) => {
        addonDetId.value = btn ? (btn.getAttribute('data-id') || '') : '';
        if (addonDetQtyInput) {
            addonDetQtyInput.value = btn ? '1' : '';
        }
    }, 'btn-primary', 'btn-outline-primary', true);
    bindCardChoice('.kiosk-addon-fab-btn', (btn) => {
        addonFabId.value = btn ? (btn.getAttribute('data-id') || '') : '';
        if (addonFabQtyInput) {
            addonFabQtyInput.value = btn ? '1' : '';
        }
    }, 'btn-primary', 'btn-outline-primary', true);
    bindCardChoice('.kiosk-addon-bleach-btn', (btn) => {
        addonBleachId.value = btn ? (btn.getAttribute('data-id') || '') : '';
        if (addonBleachQtyInput) {
            addonBleachQtyInput.value = btn ? '1' : '';
        }
    }, 'btn-primary', 'btn-outline-primary', true);

    form?.addEventListener('submit', (e) => {
        if (!validateBeforeSubmit()) {
            e.preventDefault();
            return;
        }
        if (serviceMode.value === 'free' || serviceMode.value === 'reward') {
            e.preventDefault();
            if (paymentMethod) paymentMethod.value = 'pending';
            if (paymentTiming) paymentTiming.value = 'pay_later';
            if (amountTenderedHidden) amountTenderedHidden.value = '';
            if (changeAmountHidden) changeAmountHidden.value = '';
            resetSplitPaymentValues();
            submitKioskOrder();
            return;
        }
        if ((paymentTiming?.value || 'pay_later') === 'pay_now') {
            e.preventDefault();
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
    splitCashAmountInput?.addEventListener('input', updatePayNowAmountsUI);
    splitOnlineAmountInput?.addEventListener('input', updatePayNowAmountsUI);
    paymentMethodConfirmBtn?.addEventListener('click', () => {
        if (!form || !paymentMethod) return;
        const dueAmount = getPaymentDue();
        if (selectedPayNowMethod === 'split_payment') {
            const cashAmount = Math.max(0, parseFloat(splitCashAmountInput?.value || '0') || 0);
            const onlineAmount = Math.max(0, parseFloat(splitOnlineAmountInput?.value || '0') || 0);
            const onlineMethod = String(splitOnlineMethodSelect?.value || '').trim();
            const totals = getCurrentTotals();
            const expectedTotal = Math.max(0, Number(totals.totalPrice || 0));
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
    syncRewardMode();
    syncSubmitButtonsByMode();
    computeSupplyStep();
    computeMachineNeed();
    renderStep();
})();
</script>
