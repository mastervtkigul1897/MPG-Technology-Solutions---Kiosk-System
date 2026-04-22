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
    <input type="hidden" name="customer_id" id="kioskCustomerId" value="">
    <input type="hidden" name="order_type" id="kioskOrderTypeCode" value="">
    <input type="hidden" name="service_mode" id="kioskServiceMode" value="regular">
    <input type="hidden" name="reward_redemption" id="kioskRewardRedemption" value="0">
    <input type="hidden" name="reference_code" id="kioskReferenceCode" value="<?= e($referencePreview) ?>">
    <input type="hidden" name="use_machines" id="kioskUseMachines" value="0">
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

    <div class="small text-muted mb-2" id="kioskStepLabel">Step 1 of 5</div>
    <div class="kiosk-step-context d-none" id="kioskStepContext"></div>

    <div class="card mb-3 kiosk-step" data-step="1">
        <div class="card-body">
            <h6 class="mb-2">1) Order type</h6>
            <div class="d-flex flex-wrap gap-2 mb-3" id="kioskOrderTypeCards">
                <?php foreach ($orderTypes as $ot): ?>
                    <button
                        type="button"
                        class="btn btn-outline-primary kiosk-order-type-btn kiosk-selectable-btn kiosk-item-card"
                        data-code="<?= e((string) ($ot['code'] ?? '')) ?>"
                        data-service-kind="<?= e((string) ($ot['service_kind'] ?? 'full_service')) ?>"
                        data-supply-block="<?= e((string) ($ot['supply_block'] ?? 'none')) ?>"
                        data-show-addon-supplies="<?= ! empty($ot['show_addon_supplies']) ? '1' : '0' ?>"
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
            <div class="btn-group flex-wrap" role="group">
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
        </div>
    </div>

    <div class="card mb-3 kiosk-step d-none" data-step="2">
        <div class="card-body">
            <h6 class="mb-2">2) Service supplies (depends on order type)</h6>
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

    <div class="card mb-3 kiosk-step d-none" data-step="3">
        <div class="card-body">
            <h6 class="mb-2">3) Add-ons (optional)</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label mb-1">Add-on detergent</label>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="kioskAddonDetCards">
                        <?php foreach ($detergentItems as $item): ?>
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-det-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
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
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-fab-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
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
                            <button type="button" class="btn btn-outline-primary kiosk-item-card kiosk-addon-bleach-btn kiosk-selectable-btn" data-id="<?= (int) ($item['id'] ?? 0) ?>" data-name="<?= e((string) ($item['name'] ?? '')) ?>" data-stock-qty="<?= e((string) (float) ($item['stock_quantity'] ?? 0)) ?>" data-low-stock-threshold="<?= e((string) (float) ($item['low_stock_threshold'] ?? 0)) ?>">
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

    <div class="card mb-3 kiosk-step d-none" data-step="5">
        <div class="card-body">
            <h6 class="mb-2">4) Include Fold Service?</h6>
            <p class="small text-muted mb-3">Available for all order types.</p>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary kiosk-selectable-btn" id="kioskFoldNoBtn">No</button>
                <button type="button" class="btn btn-outline-primary kiosk-selectable-btn" id="kioskFoldYesBtn">Yes</button>
            </div>
        </div>
    </div>

    <div class="card kiosk-step d-none" data-step="6">
        <div class="card-body">
            <h6 class="mb-2">5) Summary</h6>
            <div class="small text-muted mb-2">Review before saving.</div>
            <div class="fw-semibold" id="kioskSelectionSummary">No selection yet.</div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="submit" class="btn btn-lg btn-primary px-4" id="kioskCreateTransactionBtn">
                    <i class="fa-solid fa-circle-check me-1"></i> Save transaction
                </button>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <button type="button" class="btn btn-outline-secondary" id="kioskPrevBtn">Previous</button>
        <button type="button" class="btn btn-primary" id="kioskNextBtn">Next</button>
    </div>
</form>

<script>
(() => {
    const kioskReferenceCode = <?= json_embed($referencePreview) ?>;

    const steps = Array.from(document.querySelectorAll('.kiosk-step'));
    const stepLabel = document.getElementById('kioskStepLabel');
    const prevBtn = document.getElementById('kioskPrevBtn');
    const nextBtn = document.getElementById('kioskNextBtn');
    const orderTypeCode = document.getElementById('kioskOrderTypeCode');
    const serviceMode = document.getElementById('kioskServiceMode');
    const rewardRedemption = document.getElementById('kioskRewardRedemption');
    const customerId = document.getElementById('kioskCustomerId');
    const foldService = document.getElementById('kioskFoldService');
    const inclusionDet = document.getElementById('kioskInclusionDetergent');
    const inclusionFab = document.getElementById('kioskInclusionFabcon');
    const inclusionBleach = document.getElementById('kioskInclusionBleach');
    const summary = document.getElementById('kioskSelectionSummary');
    const customerSearch = document.getElementById('kioskCustomerSearch');
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
    const stepContext = document.getElementById('kioskStepContext');
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
            Swal.fire({
                icon: 'warning',
                title: 'Action needed',
                text,
                confirmButtonText: 'OK',
            });
            return;
        }
        window.mpgAlert(text, { title: 'Action needed', icon: 'warning' });
    };
    const listHtml = (items) => {
        if (!Array.isArray(items) || items.length === 0) return 'None';
        return `<ul>${items.map((x) => `<li>• ${esc(x)}</li>`).join('')}</ul>`;
    };

    let selectedOrderLabel = '';
    let selectedServiceKind = '';
    let selectedSupplyBlock = 'none';
    let selectedShowAddonSupplies = false;
    let selectedCustomerLabel = 'Walk-in customer';
    let currentStep = 1;
    let visibleSteps = [1, 2, 3, 5, 6];
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
        const noSupplies = selectedSupplyBlock === 'none';
        const showAddons = selectedShowAddonSupplies && serviceMode.value !== 'free' && serviceMode.value !== 'reward';
        if (noSupplies && !showAddons) {
            visibleSteps = [1, 5, 6];
        } else if (noSupplies) {
            visibleSteps = [1, 3, 5, 6];
        } else if (!showAddons) {
            visibleSteps = [1, 2, 5, 6];
        } else {
            visibleSteps = [1, 2, 3, 5, 6];
        }
        if (!visibleSteps.includes(currentStep)) {
            currentStep = visibleSteps[0] || 1;
        }
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
        if (serviceMode.value !== 'free' && serviceMode.value !== 'reward' && parseFloat(addonDetQty.value) > 0 && addonDetId.value) {
            addonParts.push(`Detergent: ${addonDetQty.value}x ${addonDetBtn?.getAttribute('data-name') || ''}`.trim());
        }
        if (serviceMode.value !== 'free' && serviceMode.value !== 'reward' && parseFloat(addonFabQty.value) > 0 && addonFabId.value) {
            addonParts.push(`Fabcon: ${addonFabQty.value}x ${addonFabBtn?.getAttribute('data-name') || ''}`.trim());
        }
        if (serviceMode.value !== 'free' && serviceMode.value !== 'reward' && parseFloat(addonBleachQty.value) > 0 && addonBleachId.value) {
            addonParts.push(`Bleach: ${addonBleachQty.value}x ${addonBleachBtn?.getAttribute('data-name') || ''}`.trim());
        }
        const summaryMode = rewardRedemption?.value === '1' ? 'REWARD (FREE)' : (serviceMode.value || 'regular').toUpperCase();
        summary.innerHTML = `
            <div class="kiosk-summary-receipt">
                <div class="line"><span class="label">Order Type</span><span class="value">${esc(selectedOrderLabel || 'No order type')}</span></div>
                <div class="line"><span class="label">Mode</span><span class="value">${esc(summaryMode)}</span></div>
                <div class="line"><span class="label">Reference No.</span><span class="value">${esc(kioskReferenceCode || '-')}</span></div>
                <div class="line"><span class="label">Customer</span><span class="value">${esc(selectedCustomerLabel || 'Walk-in customer')}</span></div>
                <div class="line"><span class="label">Service Supplies</span><span class="value">${listHtml(supplyLabelParts)}</span></div>
                <div class="line"><span class="label">Add-ons</span><span class="value">${listHtml(addonParts)}</span></div>
                <div class="line"><span class="label">Include Fold Service</span><span class="value">${foldService.value === '1' ? 'Yes' : 'No'}</span></div>
            </div>
        `;
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
        steps.forEach((el) => {
            const s = Number(el.getAttribute('data-step') || '0');
            const showThisStep = (s === currentStep) && visibleSteps.includes(s);
            el.classList.toggle('d-none', !showThisStep);
        });
        const idx = Math.max(0, visibleSteps.indexOf(currentStep));
        const total = visibleSteps.length;
        if (stepLabel) stepLabel.textContent = `Step ${idx + 1} of ${total}`;
        if (stepContext) {
            if (idx <= 0) {
                stepContext.classList.add('d-none');
                stepContext.textContent = '';
            } else {
                const parts = [];
                parts.push(`Order: ${selectedOrderLabel || '—'}`);
                parts.push(`Customer: ${selectedCustomerLabel || 'Walk-in customer'}`);
                if (inclusionDet.value || inclusionFab.value || inclusionBleach.value) {
                    const sup = [];
                    const detBtn = document.querySelector(`.kiosk-supply-det-btn[data-id="${inclusionDet.value}"]`);
                    const fabBtn = document.querySelector(`.kiosk-supply-fab-btn[data-id="${inclusionFab.value}"]`);
                    const blBtn = document.querySelector(`.kiosk-supply-bleach-btn[data-id="${inclusionBleach.value}"]`);
                    if (inclusionDet.value) sup.push(`Detergent: ${detBtn?.getAttribute('data-name') || ''}`);
                    if (inclusionFab.value) sup.push(`Fabcon: ${fabBtn?.getAttribute('data-name') || ''}`);
                    if (inclusionBleach.value) sup.push(`Bleach: ${blBtn?.getAttribute('data-name') || ''}`);
                    if (sup.length) parts.push(`Supplies: ${sup.join(' | ')}`);
                }
                if (foldService.value === '1') {
                    parts.push('Fold: Yes');
                }
                stepContext.textContent = parts.join('   •   ');
                stepContext.classList.remove('d-none');
            }
        }
        if (prevBtn) prevBtn.disabled = idx <= 0;
        if (nextBtn) nextBtn.classList.toggle('d-none', idx >= total - 1);
        updateSummary();
    };

    const validateStep = (step) => {
        if (step === 1 && !orderTypeCode.value) {
            showWarn('Please select an order type.');
            return false;
        }
        if (step === 2) {
            if ((selectedSupplyBlock === 'full_service' || selectedSupplyBlock === 'wash_supplies') && (!inclusionDet.value || !inclusionFab.value)) {
                showWarn('Please select detergent and fabcon.');
                return false;
            }
        }
        return true;
    };

    const validateBeforeSubmit = () => {
        updateSummary();
        if (!orderTypeCode.value) {
            showWarn('Please select an order type.');
            return false;
        }
        if ((selectedSupplyBlock === 'full_service' || selectedSupplyBlock === 'wash_supplies') && (!inclusionDet.value || !inclusionFab.value)) {
            showWarn('Please select detergent and fabcon before saving.');
            return false;
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
            selectedSupplyBlock = btn.dataset.supplyBlock || 'none';
            selectedShowAddonSupplies = (btn.dataset.showAddonSupplies || '0') === '1';
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
            const pickedMode = btn.dataset.mode || 'regular';
            if (pickedMode === 'reward') {
                applyRewardRedemption();
                return;
            }
            clearRewardRedemption();
            serviceMode.value = pickedMode;
            setButtonState('.kiosk-service-mode-btn', btn, 'btn-primary', 'btn-outline-secondary');
            syncRewardMode();
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

    prevBtn?.addEventListener('click', () => {
        const idx = visibleSteps.indexOf(currentStep);
        if (idx > 0) {
            currentStep = visibleSteps[idx - 1];
        }
        renderStep();
    });
    nextBtn?.addEventListener('click', () => {
        if (!validateStep(currentStep)) return;
        const idx = visibleSteps.indexOf(currentStep);
        if (idx >= 0 && idx < visibleSteps.length - 1) {
            currentStep = visibleSteps[idx + 1];
        }
        renderStep();
    });

    form?.addEventListener('submit', (e) => {
        if (!validateBeforeSubmit()) {
            e.preventDefault();
        }
    });
    foldNoBtn?.click();
    syncRewardMode();
    computeSupplyStep();
    computeMachineNeed();
    renderStep();
})();
</script>
