<?php
/** @var array<int,array<string,mixed>> $products */
/** @var array<int,array<string,mixed>> $productPayload */
/** @var array{search?:string} $filters */
/** @var array{current_page:int,last_page:int,total:int,per_page:int} $pagination */
?>
<?php
function posProductImageSrc(string $name, ?string $imagePath = null): string
{
    $path = trim((string) $imagePath);
    if ($path !== '') {
        return url($path);
    }

    $clean = preg_replace('/\s+/', ' ', trim($name)) ?: '';
    $letters = preg_replace('/[^A-Za-z0-9]/', '', $clean) ?: '';
    $letter = mb_strtoupper((string) mb_substr($letters, 0, 1)) ?: '?';

    $hash = md5(mb_strtolower($clean));
    $colors = ['#0d6efd', '#198754', '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#6610f2', '#0dcaf0', '#d63384', '#343a40'];
    $idx1 = hexdec(substr($hash, 0, 2)) % count($colors);
    $idx2 = hexdec(substr($hash, 2, 2)) % count($colors);
    $c1 = $colors[$idx1] ?? '#0d6efd';
    $c2 = $colors[$idx2] ?? '#0a58ca';

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">'
        . '<defs>'
        . '<linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="'.$c1.'"/>'
        . '<stop offset="1" stop-color="'.$c2.'"/>'
        . '</linearGradient>'
        . '</defs>'
        . '<rect width="256" height="256" rx="48" fill="url(#g)"/>'
        . '<text x="50%" y="54%" text-anchor="middle" font-family="Arial" font-size="92" font-weight="700" fill="#ffffff">'.$letter.'</text>'
        . '</svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}
?>

<div class="row g-3 modern-section">
    <div class="col-md-8 pos-products-col">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="<?= e(url('/tenant/pos')) ?>" class="mb-3">
                    <label class="form-label mb-1" for="searchInput">Search products</label>
                    <input id="searchInput" type="text" class="form-control" name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Type to filter…" autocomplete="off">
                </form>

                <?php
                $productPayloadById = [];
                foreach ($productPayload as $payload) {
                    $productPayloadById[(int) ($payload['id'] ?? 0)] = $payload;
                }
                $groups = [];
                foreach ($products as $product) {
                    $key = (string) ($product['category_key'] ?? '');
                    $key = trim($key) !== '' ? $key : 'other';
                    if (! isset($groups[$key])) {
                        $label = ucwords(str_replace(['_', '-'], ' ', $key));
                        $groups[$key] = ['label' => $label, 'items' => []];
                    }
                    $groups[$key]['items'][] = $product;
                }
                $keys = array_keys($groups);
                ?>

                <?php if (! empty($keys)) { ?>
                    <div class="accordion" id="posCategoryAccordion">
                        <?php foreach ($keys as $i => $key): ?>
                            <?php $label = (string) ($groups[$key]['label'] ?? $key); ?>
                            <?php $collapseId = 'posCatCollapse'.$i; ?>
                            <div class="accordion-item border-0 shadow-sm mb-2">
                                <h2 class="accordion-header" id="posCatHeader<?= (int) $i ?>">
                                    <button type="button"
                                        class="accordion-button collapsed"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#<?= e($collapseId) ?>"
                                        aria-expanded="false"
                                            aria-controls="<?= e($collapseId) ?>">
                                        <span class="me-2"><?= e($label) ?></span>
                                        <span class="badge text-bg-secondary rounded-pill" style="font-size: .75rem;"><?= (int) count($groups[$key]['items'] ?? []) ?></span>
                                    </button>
                                </h2>
                                <div id="<?= e($collapseId) ?>"
                                     class="accordion-collapse collapse"
                                     aria-labelledby="posCatHeader<?= (int) $i ?>"
                                     data-bs-parent="#posCategoryAccordion">
                                    <div class="accordion-body pt-2">
                                        <div class="row g-3">
                                            <?php foreach (($groups[$key]['items'] ?? []) as $product): ?>
                                                <?php $pid = (int) ($product['id'] ?? 0); ?>
                                                <?php $pname = (string) ($product['name'] ?? ''); ?>
                                                <?php $requirementCount = count((array) (($productPayloadById ?? [])[$pid]['ingredients'] ?? [])); ?>
                                                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                                                    <div class="pos-product-card card h-100 shadow-sm border border-primary"
                                                         data-product-id="<?= (int) $pid ?>">
                                                        <div class="card-body p-2 p-sm-3 d-flex flex-column gap-2 gap-sm-3">
                                                            <div class="pos-product-image-wrap">
                                                                <img src="<?= posProductImageSrc($pname, (string) ($product['image_path'] ?? '')) ?>"
                                                                     alt="<?= e($pname) ?>"
                                                                     class="pos-product-image rounded">
                                                                <span class="badge text-bg-primary pos-cart-count d-none" aria-label="Items in cart">Qty: 0</span>
                                                            </div>
                                                            <div class="d-flex flex-column gap-1">
                                                                <div class="pos-product-title fw-semibold"><?= e($pname) ?></div>
                                                                <div class="d-flex flex-wrap gap-1">
                                                                    <span class="badge text-bg-success">PHP <?= e(format_money((float) ($product['price'] ?? 0))) ?></span>
                                                                    <span class="badge text-bg-light border text-dark">
                                                                        <?= (int) $requirementCount ?> <?= $requirementCount === 1 ? 'Requirement' : 'Requirements' ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex gap-2 mt-auto">
                                                                <button type="button"
                                                                        class="btn btn-sm btn-outline-primary flex-grow-1 view-details pos-btn-label"
                                                                        data-product-id="<?= (int) $pid ?>"
                                                                        title="View details"
                                                                        aria-label="View requirements for <?= e($pname) ?>">
                                                                    <i class="fa fa-eye me-1"></i>Details
                                                                </button>
                                                                <button type="button"
                                                                        class="btn btn-sm btn-primary flex-grow-1 add-cart pos-btn-label"
                                                                        data-product-id="<?= (int) $pid ?>"
                                                                        title="Add to cart"
                                                                        aria-label="Add <?= e($pname) ?> to cart">
                                                                    <i class="fa fa-plus me-1"></i>Add
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php } else { ?>
                    <div class="alert alert-info">No products found.</div>
                <?php } ?>

                <?php require dirname(__DIR__, 2).'/partials/pagination.php'; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4 pos-order-summary-anchor">
        <div class="card pos-order-summary-float" id="orderSummaryCard">
            <div class="card-body pos-summary-body">
                <h6 class="mb-2">Transaction Summary</h6>
                <div id="cartWrap" class="small text-muted mb-2">No items yet.</div>
                <form id="checkoutForm" method="POST" action="<?= e(url('/tenant/pos/checkout')) ?>" class="pos-checkout-form">
                    <?= csrf_field() ?>
                    <div id="checkoutItems"></div>
                    <div class="pos-checkout-actions">
                        <div class="d-flex justify-content-between mb-2"><span>Total</span><strong id="cartTotal">0.00</strong></div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success w-100" id="checkoutBtn">
                                <span class="d-inline-flex align-items-center justify-content-center me-2" style="width: 1.25em;">
                                    <i class="fa-solid fa-money-bill-wave"></i>
                                </span>
                                <span>Checkout to Pay</span>
                            </button>
                            <button type="button" class="btn btn-danger w-100" id="savePendingBtn">
                                <span class="d-inline-flex align-items-center justify-content-center me-2" style="width: 1.25em;">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </span>
                                <span>Checkout as Pending</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="pos-mobile-cart-dock d-md-none">
    <div class="pos-mobile-cart-dock-inner">
        <div class="small text-muted">Cart: <strong id="mobileCartCount">0</strong> item(s)</div>
        <div class="fw-semibold">Total: PHP <span id="mobileCartTotalMini">0.00</span></div>
        <button type="button" class="btn btn-primary btn-sm w-100 mt-2" data-bs-toggle="modal" data-bs-target="#mobileCartModal">
            <i class="fa fa-shopping-cart me-1"></i>View cart / Checkout
        </button>
    </div>
</div>

<div class="modal fade" id="mobileCartModal" tabindex="-1" aria-labelledby="mobileCartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="mobileCartModalLabel">Transaction Summary</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="mobileCartWrap" class="small text-muted mb-2">No items yet.</div>
                <form id="mobileCheckoutForm" method="POST" action="<?= e(url('/tenant/pos/checkout')) ?>" class="pos-checkout-form">
                    <?= csrf_field() ?>
                    <div id="mobileCheckoutItems"></div>
                    <div class="pos-checkout-actions">
                        <div class="d-flex justify-content-between mb-2"><span>Total</span><strong id="mobileCartTotal">0.00</strong></div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success w-100" id="mobileCheckoutBtn">
                                <span class="d-inline-flex align-items-center justify-content-center me-2" style="width: 1.25em;">
                                    <i class="fa-solid fa-money-bill-wave"></i>
                                </span>
                                <span>Checkout to Pay</span>
                            </button>
                            <button type="button" class="btn btn-danger w-100" id="mobileSavePendingBtn">
                                <span class="d-inline-flex align-items-center justify-content-center me-2" style="width: 1.25em;">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </span>
                                <span>Checkout as Pending</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ingredientsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" id="ingredientTitle">Requirements</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="ingredientBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="receiptModalTitle"<?= empty($receipt_print_allowed) ? ' data-mpg-trial-print="1"' : '' ?>>
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content mpg-receipt-modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title" id="receiptModalTitle">Receipt</h5>
            </div>
            <div class="modal-body">
                <div id="receiptPrintArea" class="receipt-print-area small"></div>
            </div>
            <div class="modal-footer flex-column flex-sm-row flex-wrap gap-2 justify-content-stretch justify-content-sm-center w-100 mpg-receipt-modal-footer">
                <div class="d-grid d-sm-flex gap-2 w-100 flex-sm-grow-0 flex-sm-wrap justify-content-sm-center">
                    <button type="button" class="btn btn-primary text-white w-100 mpg-receipt-action-btn <?= empty($receipt_print_allowed) ? 'd-none' : '' ?>" id="receiptPrintBtn"><i class="fa-solid fa-print me-1"></i>Print</button>
                    <button type="button" class="btn btn-success text-white <?= (empty($thermal_receipt_network_enabled) || empty($receipt_print_allowed)) ? 'd-none' : '' ?> w-100 mpg-receipt-action-btn" id="receiptPrintWifiBtn" title="Server sends raw data to printer on LAN (phone/tablet/APK when host is configured)"><i class="fa-solid fa-wifi me-1"></i>Wi‑Fi / LAN</button>
                    <button type="button" class="btn btn-primary text-white mpg-btn-bluetooth-thermal w-100 mpg-receipt-action-btn <?= empty($receipt_print_allowed) ? 'd-none' : '' ?>" id="receiptPrintBleBtn" title="Bluetooth print"><i class="fa-brands fa-bluetooth-b me-1"></i>Bluetooth print</button>
                    <button type="button" class="btn btn-secondary text-white w-100 mpg-receipt-action-btn" id="receiptOkBtn" data-bs-dismiss="modal">OK</button>
                </div>
                <div class="w-100 d-flex flex-wrap gap-2 align-items-center justify-content-center mpg-btn-bluetooth-thermal <?= empty($receipt_print_allowed) ? 'd-none' : '' ?>">
                    <span class="small text-muted" id="receiptBleSavedHint">No saved Bluetooth printer yet.</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="receiptBleChangeBtn">Change Bluetooth printer</button>
                </div>
                <?php if (empty($receipt_print_allowed)): ?>
                    <div class="w-100 small text-warning text-center fw-semibold">Premium: receipt printing is not included on Free Trial — you can still view the receipt below.</div>
                    <div class="w-100 d-grid">
                        <a href="<?= e(url('/tenant/plans')) ?>" class="btn btn-warning btn-sm fw-semibold"><i class="fa-solid fa-tags me-1"></i>View plans & pricing</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pendingMetaModal" tabindex="-1" aria-labelledby="pendingMetaModalTitle" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pendingMetaModalTitle">Checkout as Pending</h5>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-3">Enter creditor details. Name is required; contact number is optional.</div>
                <div class="mb-2">
                    <label class="form-label mb-1" for="pendingNameInput">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="pendingNameInput" placeholder="e.g. Juan Dela Cruz" required>
                </div>
                <div class="mb-0">
                    <label class="form-label mb-1" for="pendingContactInput">Contact number (optional)</label>
                    <input type="text" class="form-control" id="pendingContactInput" placeholder="e.g. 09xxxxxxxxx">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="pendingMetaSaveBtn">
                    <i class="fa fa-floppy-disk me-1"></i>Save Pending
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Thermal receipt print: 60mm roll, black & white, one continuous “page” (no blank sheet) */
@media print {
    @page {
        size: 60mm auto;
        margin: 0;
    }
    html, body {
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
        background: #fff !important;
        color: #000 !important;
        print-color-adjust: economy;
        -webkit-print-color-adjust: economy;
    }
    body * {
        visibility: hidden !important;
    }
    #receiptModal,
    #receiptModal * {
        visibility: visible !important;
        box-shadow: none !important;
        text-shadow: none !important;
    }
    #receiptModal {
        position: absolute !important;
        inset: 0 auto auto 0 !important;
        width: 60mm !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        border: 0 !important;
        filter: none !important;
    }
    #receiptModal .modal-dialog {
        max-width: 60mm !important;
        width: 60mm !important;
        margin: 0 !important;
        height: auto !important;
    }
    #receiptModal .modal-content {
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
        height: auto !important;
    }
    #receiptModal .modal-header,
    #receiptModal .modal-footer {
        display: none !important;
    }
    #receiptModal .modal-body {
        padding: 0 !important;
        overflow: visible !important;
        max-height: none !important;
        background: #fff !important;
    }
    #receiptPrintArea {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        justify-content: flex-start !important;
    }
    #receiptPrintArea .receipt-paper,
    #receiptModal .receipt-paper {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 1.5mm 2mm !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: #fff !important;
        color: #000 !important;
        font-family: "Courier New", Courier, monospace !important;
        font-size: 9px !important;
        line-height: 1.22 !important;
        page-break-after: avoid !important;
        break-after: avoid-page !important;
        page-break-inside: auto;
    }
    #receiptModal .receipt-paper * {
        color: #000 !important;
        background: transparent !important;
    }
    #receiptModal .receipt-muted {
        color: #333 !important;
    }
    #receiptModal .receipt-dash {
        border-top-color: #000 !important;
    }
    #receiptModal .receipt-paper.receipt-unpaid-prep .receipt-unpaid-watermark {
        color: rgba(0, 0, 0, 0.18) !important;
        print-color-adjust: exact !important;
        -webkit-print-color-adjust: exact !important;
    }
    .pos-order-summary-float {
        position: static !important;
        max-height: none !important;
        width: auto !important;
    }
}

/* Checkout summary layout */
.pos-order-summary-anchor {
    align-self: stretch;
    padding-top: 0;
}

.pos-order-summary-float {
    position: static;
    z-index: 1;
    background: #fff;
    border: 1px solid #dee2e6;
    box-shadow: 0 8px 22px rgba(0, 0, 0, .12);
    overflow: auto;
}

.pos-summary-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    gap: .35rem;
}

#cartWrap {
    flex: 1 1 auto;
    min-height: 3.25rem;
    max-height: none;
    overflow: visible;
}

#cartWrap.pos-cart-scrollable,
#mobileCartWrap.pos-cart-scrollable {
    max-height: 24rem;
    overflow: auto;
    padding-right: .2rem;
}

.pos-checkout-form {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.pos-checkout-actions {
    position: sticky;
    bottom: 0;
    background: #fff;
    padding-top: .5rem;
    border-top: 1px solid #eef1f4;
    z-index: 2;
}

@media (min-width: 992px) {
    .pos-products-col {
        flex: 0 0 100%;
        max-width: 100%;
        padding-right: calc(min(340px, 29vw) + .9rem);
    }
    .pos-order-summary-anchor {
        position: static;
        flex: 0 0 0;
        max-width: 0;
        width: 0;
        padding: 0;
        margin: 0;
        overflow: visible;
    }
    .pos-order-summary-float {
        position: fixed;
        z-index: 1032;
        top: calc(var(--app-toolbar-height, 56px) + 8rem);
        right: 1rem;
        width: min(340px, 29vw);
        max-height: calc(100vh - 1.5rem);
    }
}

@media (min-width: 768px) and (max-width: 991.98px) {
    .pos-products-col {
        flex: 0 0 100%;
        max-width: 100%;
        padding-right: calc(min(300px, 36vw) + .85rem);
    }
    .pos-order-summary-anchor {
        position: static;
        flex: 0 0 0;
        max-width: 0;
        width: 0;
        padding: 0;
        margin: 0;
        overflow: visible;
    }
    .pos-order-summary-float {
        position: fixed;
        z-index: 1032;
        top: calc(var(--app-toolbar-height, 56px) + 7.5rem);
        right: .75rem;
        width: min(300px, 36vw);
        max-height: calc(100vh - 1.5rem);
    }
}

/* When branch dropdown is open, move floating checkout lower
   so the popout list remains fully visible. */
body.branch-select-open .pos-order-summary-float {
    top: calc(var(--app-toolbar-height, 56px) + 11rem);
}
body.branch-select-open .pos-order-summary-anchor {
    top: auto;
}

@media (max-width: 767.98px) {
    .pos-order-summary-anchor { display: none; }
    .pos-mobile-cart-dock {
        position: fixed;
        left: .5rem;
        right: .5rem;
        bottom: .5rem;
        z-index: 1035;
    }
    .pos-mobile-cart-dock-inner {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: .75rem;
        box-shadow: 0 8px 22px rgba(0, 0, 0, .12);
        padding: .6rem .7rem;
    }
    .app-main-scroll {
        padding-bottom: 6.5rem;
    }
}

@media (max-width: 767.98px) and (orientation: landscape) {
    .pos-mobile-cart-dock { bottom: .4rem; }
    .app-main-scroll {
        padding-bottom: 6rem;
    }
}

.pos-cart-table thead th {
    font-size: .82rem;
    color: #6c757d;
}
.pos-cart-table td {
    vertical-align: middle;
}
.pos-cart-actions {
    display: inline-flex;
    gap: .25rem;
    justify-content: flex-end;
    align-items: center;
}

.pos-product-image-wrap {
    width: 100%;
    aspect-ratio: 4 / 3;
    overflow: hidden;
    border-radius: .6rem;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.pos-product-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
}

.pos-product-title {
    font-size: 1rem;
    line-height: 1.2;
    min-height: 2.4em;
}

.pos-btn-label {
    white-space: nowrap;
}

.pos-cart-count {
    position: absolute;
    top: .4rem;
    right: .4rem;
    font-size: .75rem;
    z-index: 2;
}

@media (max-width: 575.98px) {
    .pos-product-title {
        min-height: 0;
        font-size: .95rem;
    }
}

/* Grocery-style receipt layout */
.receipt-print-area {
    display: flex;
    justify-content: center;
}
.receipt-paper {
    width: 100%;
    max-width: 60mm;
    margin: 0 auto;
    padding: .45rem .55rem;
    border: 1px dashed #adb5bd;
    border-radius: .35rem;
    background: #fff;
    font-family: "Courier New", Courier, monospace;
    font-size: 10px;
    line-height: 1.25;
    color: #111;
}
.receipt-bottom-spacer {
    height: 2.5em;
    min-height: 2.5em;
}
.receipt-center { text-align: center; }
.receipt-bold { font-weight: 700; }
.receipt-muted { color: #4b5563; }
.receipt-dash {
    border-top: 1px dashed #666;
    margin: .22rem 0;
}
.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .5rem;
}
.receipt-row .left {
    min-width: 0;
    flex: 1 1 auto;
}
.receipt-row .right {
    flex: 0 0 auto;
    text-align: right;
    white-space: nowrap;
}
.receipt-item-name {
    font-weight: 600;
    margin-bottom: 0.06rem;
    word-break: break-word;
}
.receipt-item-price-line {
    margin-bottom: 0.35rem;
}
.receipt-email-one-line {
    display: block;
    width: 100%;
    white-space: nowrap;
}
.receipt-unpaid-prep {
    position: relative;
    overflow: hidden;
}
.receipt-unpaid-watermark {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    font-size: 2.1rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: rgba(0, 0, 0, 0.11);
    transform: rotate(-22deg);
    user-select: none;
}
.receipt-unpaid-banner {
    letter-spacing: 0.04em;
}
.receipt-compliance-note {
    margin: .28rem 0;
    padding: .2rem .28rem;
    border: 1px dashed #666;
    border-radius: .2rem;
    text-align: center;
    line-height: 1.2;
}
.receipt-compliance-note .title {
    font-weight: 700;
    letter-spacing: 0.02em;
}
.receipt-compliance-note .sub {
    font-weight: 600;
}
.receipt-legal-box {
    margin: .2rem 0;
    padding: .16rem .28rem;
    border: 1px dashed #666;
    border-radius: .2rem;
}
</style>

<script>
(() => {
    const thermalCfg = <?= json_embed([
        'networkUrl' => $thermal_receipt_network_url ?? '',
        'escposUrl' => $thermal_receipt_escpos_url ?? '',
        'networkEnabled' => ! empty($thermal_receipt_network_enabled),
        'lanCopies' => max(1, min(10, (int) ($thermal_receipt_lan_copies ?? 1))),
    ]) ?>;
    let lastReceiptObject = null;

    const postReceiptJson = async (url, receipt) => {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const params = new URLSearchParams();
        params.set('_token', csrf);
        params.set('receipt_json', JSON.stringify(receipt));
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: params,
        });
        return res.json().catch(() => ({}));
    };

    const fetchEscposBytes = async (receipt) => {
        const body = await postReceiptJson(thermalCfg.escposUrl, receipt);
        if (!body || !body.success || !body.escpos_base64) {
            throw new Error(body?.message || 'Could not build ESC/POS data.');
        }
        const bin = atob(body.escpos_base64);
        const out = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i += 1) out[i] = bin.charCodeAt(i);
        return out;
    };

    const products = <?= json_embed($productPayload) ?>;
    const currentSearch = <?= json_embed($filters['search'] ?? '') ?>;
    const byId = Object.fromEntries(products.map(p => [p.id, p]));
    const MONEY_DECIMALS = 2;
    const MONEY_EPS = 1e-12;
    const roundMoneyVal = (n) => {
        const x = Number(n);
        if (!Number.isFinite(x)) return 0;
        const f = 10 ** MONEY_DECIMALS;
        return Math.round(x * f) / f;
    };
    const toMoneyInputStr = (n) => roundMoneyVal(n).toFixed(MONEY_DECIMALS);
    const money = (n) => Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: MONEY_DECIMALS, maximumFractionDigits: MONEY_DECIMALS });
    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    const cart = {};
    const wrap = document.getElementById('cartWrap');
    const mobileWrap = document.getElementById('mobileCartWrap');
    const itemsEl = document.getElementById('checkoutItems');
    const mobileItemsEl = document.getElementById('mobileCheckoutItems');
    const totalEl = document.getElementById('cartTotal');
    const mobileTotalEl = document.getElementById('mobileCartTotal');
    const mobileTotalMiniEl = document.getElementById('mobileCartTotalMini');
    const mobileCountEl = document.getElementById('mobileCartCount');
    const mobileCartModalEl = document.getElementById('mobileCartModal');
    const mobileCartModal = mobileCartModalEl ? bootstrap.Modal.getOrCreateInstance(mobileCartModalEl) : null;
    let searchTimer = null;

    const getOutOfStockIngredient = (product, qty) => {
        return (product.ingredients || []).find(i => (i.stock_quantity - (i.qty_required * qty)) < 0);
    };
    const getOutOfStockFlavor = (flavor, qty) => {
        const stock = Number(flavor?.stock_quantity || 0);
        const req = Number(flavor?.qty_required || 1);
        return (stock - (qty * req)) < 0;
    };
    const willReachLow = (product, qty) => {
        return (product.ingredients || []).some(i => (i.stock_quantity - (i.qty_required * qty)) <= i.low_stock_threshold);
    };
    const alertOut = (p, i) => Swal.fire({ icon: 'warning', title: 'Cannot add quantity', text: `${p.name}: ${i.name} is already out of stock or not enough for this quantity.` });
    const alertCheckoutOut = (p, i) => Swal.fire({
        icon: 'warning',
        title: 'Out of stock',
        text: `${p.name}: ${i.name} does not have enough stock for checkout.`,
    });

    const renderCart = () => {
        const entries = Object.values(cart);
        const renderNoItems = '<div class="text-muted">No items yet.</div>';
        const shouldScroll = entries.length >= 10;
        if (!entries.length) {
            if (wrap) wrap.innerHTML = renderNoItems;
            if (mobileWrap) mobileWrap.innerHTML = renderNoItems;
            if (itemsEl) itemsEl.innerHTML = '';
            if (mobileItemsEl) mobileItemsEl.innerHTML = '';
            if (totalEl) totalEl.textContent = '0.00';
            if (mobileTotalEl) mobileTotalEl.textContent = '0.00';
            if (mobileTotalMiniEl) mobileTotalMiniEl.textContent = '0.00';
            if (mobileCountEl) mobileCountEl.textContent = '0';
        } else {
            let total = 0;
            const tableHtml = `<div class="table-responsive"><table class="table table-sm pos-cart-table mb-0"><thead><tr><th>Item</th><th class="text-center" style="width:72px;">Qty</th><th class="text-end" style="width:104px;">Subtotal</th><th class="text-end" style="width:148px;">Actions</th></tr></thead><tbody>${
                entries.map(item => {
                    const subtotal = item.price * item.quantity;
                    total += subtotal;
                    const lineName = item.flavor_name ? `${item.name} - ${item.flavor_name}` : item.name;
                    return `<tr>
                        <td>${lineName}</td>
                        <td class="text-center fw-semibold">${item.quantity}</td>
                        <td class="text-end">PHP ${money(subtotal)}</td>
                        <td class="text-end">
                            <div class="pos-cart-actions">
                                <button class="btn btn-sm btn-outline-secondary minus" data-key="${item.cart_key}" title="Minus quantity">-</button>
                                <button class="btn btn-sm btn-outline-secondary plus" data-key="${item.cart_key}" title="Add quantity">+</button>
                                <button class="btn btn-sm btn-outline-danger rem" data-key="${item.cart_key}" title="Remove item"><i class="fa fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>`;
                }).join('')
            }</tbody></table></div>`;
            const hiddenInputs = entries.map((item, idx) => `<input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}"><input type="hidden" name="items[${idx}][quantity]" value="${item.quantity}"><input type="hidden" name="items[${idx}][flavor_ingredient_id]" value="${Number(item.flavor_ingredient_id || 0)}">`).join('');
            if (wrap) wrap.innerHTML = tableHtml;
            if (mobileWrap) mobileWrap.innerHTML = tableHtml;
            if (itemsEl) itemsEl.innerHTML = hiddenInputs;
            if (mobileItemsEl) mobileItemsEl.innerHTML = hiddenInputs;
            const totalText = money(total);
            if (totalEl) totalEl.textContent = totalText;
            if (mobileTotalEl) mobileTotalEl.textContent = totalText;
            if (mobileTotalMiniEl) mobileTotalMiniEl.textContent = totalText;
            const totalQty = entries.reduce((sum, item) => sum + Number(item.quantity || 0), 0);
            if (mobileCountEl) mobileCountEl.textContent = String(totalQty);
        }
        if (wrap) wrap.classList.toggle('pos-cart-scrollable', shouldScroll);
        if (mobileWrap) mobileWrap.classList.toggle('pos-cart-scrollable', shouldScroll);

        // Update card visuals (danger + button color) based on current cart quantities.
        document.querySelectorAll('.pos-product-card[data-product-id]').forEach(card => {
            const pid = Number(card.dataset.productId);
            const p = byId[pid];
            if (!p) return;
            const currentQty = Object.values(cart)
                .filter((ci) => Number(ci.product_id || 0) === p.id)
                .reduce((sum, ci) => sum + Number(ci.quantity || 0), 0);
            const danger = currentQty > 0 && willReachLow(p, currentQty);
            card.classList.toggle('border-danger', danger);
            card.classList.toggle('border-primary', !danger);
            const addBtn = card.querySelector('.add-cart');
            if (addBtn) {
                addBtn.classList.remove('btn-danger', 'btn-primary');
                addBtn.classList.add(willReachLow(p, currentQty + 1) ? 'btn-danger' : 'btn-primary');
            }
            const countBadge = card.querySelector('.pos-cart-count');
            if (countBadge) {
                if (currentQty > 0) {
                    countBadge.textContent = `Qty: ${currentQty}`;
                    countBadge.classList.remove('d-none');
                } else {
                    countBadge.textContent = 'Qty: 0';
                    countBadge.classList.add('d-none');
                }
            }
        });
    };

    const productsRoot = document.getElementById('posCategoryAccordion') ?? document.body;
    productsRoot.addEventListener('click', async (e) => {
        const add = e.target.closest('.add-cart');
        const details = e.target.closest('.view-details');
        if (add) {
            const id = Number(add.dataset.productId);
            const p = byId[id];
            if (!p) return;
            let flavorId = 0;
            let flavorName = '';
            if (p.has_flavor_options && Array.isArray(p.flavors) && p.flavors.length) {
                const cards = p.flavors
                    .map((f) => `<button type="button" class="btn btn-outline-primary text-start w-100 swal-flavor-card" data-id="${Number(f.id || 0)}">${escapeHtml(f.name || '')}</button>`)
                    .join('');
                let pickedFlavorId = 0;
                await Swal.fire({
                    title: `Select flavor for ${p.name}`,
                    html: `<div class="text-start"><div class="d-grid gap-2" id="swalFlavorCards">${cards}</div></div>`,
                    showConfirmButton: false,
                    showCancelButton: false,
                    allowOutsideClick: true,
                    didOpen: () => {
                        const cardEls = Array.from(document.querySelectorAll('#swalFlavorCards .swal-flavor-card'));
                        cardEls.forEach((btn) => {
                            btn.addEventListener('click', () => {
                                pickedFlavorId = Number(btn.getAttribute('data-id') || 0);
                                Swal.close();
                            });
                        });
                    },
                });
                flavorId = Number(pickedFlavorId || 0);
                if (flavorId < 1) return;
                const flavorObj = (p.flavors || []).find((f) => Number(f.id || 0) === flavorId) || null;
                if (!flavorObj) return;
                flavorName = String(flavorObj.name || '');
            }
            const cartKey = `${id}:${flavorId}`;
            const nextQty = (cart[cartKey]?.quantity || 0) + 1;
            const out = getOutOfStockIngredient(p, nextQty);
            if (out) return alertOut(p, out);
            if (flavorId > 0) {
                const flavorObj = (p.flavors || []).find((f) => Number(f.id || 0) === flavorId) || null;
                if (getOutOfStockFlavor(flavorObj, nextQty)) {
                    return Swal.fire({ icon: 'warning', title: 'Out of stock', text: `${p.name}: ${flavorName} flavor is out of stock.` });
                }
            }
            cart[cartKey] = cart[cartKey] || {
                cart_key: cartKey,
                product_id: p.id,
                flavor_ingredient_id: flavorId,
                flavor_name: flavorName,
                name: p.name,
                price: p.price,
                quantity: 0,
            };
            cart[cartKey].quantity += 1;
            renderCart();
        }
        if (details) {
            const id = Number(details.dataset.productId);
            const p = byId[id];
            if (!p) return;
            document.getElementById('ingredientTitle').textContent = `${p.name} Requirements`;
            document.getElementById('ingredientBody').innerHTML = `<table class="table table-sm"><thead><tr><th>Required item</th></tr></thead><tbody>${
                (p.ingredients || []).length
                    ? p.ingredients.map(i => `<tr><td>${i.name}</td></tr>`).join('')
                    : '<tr><td class="text-muted text-center">No requirements for this product.</td></tr>'
            }</tbody></table>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('ingredientsModal')).show();
        }
    });

    const onCartActionClick = (e) => {
        const plus = e.target.closest('.plus');
        const minus = e.target.closest('.minus');
        const rem = e.target.closest('.rem');
        if (plus) {
            const key = String(plus.dataset.key || '');
            const item = cart[key];
            if (!item) return;
            const id = Number(item.product_id);
            const p = byId[id];
            if (!p) return;
            const next = item.quantity + 1;
            const out = getOutOfStockIngredient(p, next);
            if (out) return alertOut(p, out);
            if (Number(item.flavor_ingredient_id || 0) > 0) {
                const flavorObj = (p.flavors || []).find((f) => Number(f.id || 0) === Number(item.flavor_ingredient_id || 0)) || null;
                if (getOutOfStockFlavor(flavorObj, next)) {
                    return Swal.fire({ icon: 'warning', title: 'Out of stock', text: `${p.name}: ${item.flavor_name} flavor is out of stock.` });
                }
            }
            cart[key].quantity = next;
            renderCart();
        }
        if (minus) {
            const key = String(minus.dataset.key || '');
            if (!cart[key]) return;
            cart[key].quantity = Math.max(1, cart[key].quantity - 1);
            renderCart();
        }
        if (rem) {
            delete cart[String(rem.dataset.key || '')];
            renderCart();
        }
    };
    wrap?.addEventListener('click', onCartActionClick);
    mobileWrap?.addEventListener('click', onCartActionClick);

    const buildReceiptHtml = (r) => {
        const c = r.contact || {};
        const displayName = String(r.display_name || '').trim() || String(r.store_name || 'Store').trim() || 'Store';
        const businessStyle = String(r.business_style || '').trim();
        const taxId = String(r.tax_id || '').trim();
        const serialNumber = String(r.serial_number || '').trim();
        const dtiNumber = String(r.dti_number || '').trim();
        const birAccreditationNo = String(r.bir_accreditation_no || '').trim();
        const minNo = String(r.min || '').trim();
        const permitNo = String(r.permit_no || '').trim();
        const isBirRegistered = r.is_bir_registered === true;
        const taxType = String(r.tax_type || 'non_vat').trim().toLowerCase() === 'vat' ? 'VAT Registered' : 'Non-VAT Registered';
        const footerNote = String(r.footer_note || '').trim();
        const isUnpaidPrep = !!(r.unpaid_prep_receipt || r.kitchen_slip || r.unpaid_watermark);
        const lines = [];
        lines.push(`<div class="receipt-paper${isUnpaidPrep ? ' receipt-unpaid-prep' : ''}">`);
        if (isUnpaidPrep) {
            lines.push('<div class="receipt-unpaid-watermark" aria-hidden="true">UNPAID</div>');
            const pn = String(r.pending_customer_name || '').trim();
            if (pn) lines.push(`<div class="receipt-center receipt-bold">For: ${escapeHtml(pn)}</div>`);
            const pc = String(r.pending_customer_contact || '').trim();
            if (pc) lines.push(`<div class="receipt-center receipt-muted">Contact: ${escapeHtml(pc)}</div>`);
            if (pn || pc) lines.push('<div class="receipt-dash"></div>');
        }
        lines.push(`<div class="receipt-center receipt-bold">${escapeHtml(displayName)}</div>`);
        if (businessStyle && !isUnpaidPrep) {
            lines.push(`<div class="receipt-center receipt-muted">${escapeHtml(businessStyle)}</div>`);
        }
        if (!isUnpaidPrep) {
            const legalLines = [];
            if (isBirRegistered) {
                if (birAccreditationNo) legalLines.push(`<div class="receipt-center">BIR Accreditation No: ${escapeHtml(birAccreditationNo)}</div>`);
                if (taxId) legalLines.push(`<div class="receipt-center">TIN: ${escapeHtml(taxId)}</div>`);
                if (serialNumber) legalLines.push(`<div class="receipt-center">Serial No: ${escapeHtml(serialNumber)}</div>`);
                if (minNo) legalLines.push(`<div class="receipt-center">MIN: ${escapeHtml(minNo)}</div>`);
                if (permitNo) legalLines.push(`<div class="receipt-center">Permit No: ${escapeHtml(permitNo)}</div>`);
            } else if (taxId) {
                legalLines.push(`<div class="receipt-center">TIN: ${escapeHtml(taxId)}</div>`);
            }
            if (dtiNumber) legalLines.push(`<div class="receipt-center">DTI No: ${escapeHtml(dtiNumber)}</div>`);
            if (legalLines.length) {
                lines.push(`<div class="receipt-legal-box">${legalLines.join('')}</div>`);
            }
            lines.push(`<div class="receipt-center">Tax Type: ${escapeHtml(taxType)}</div>`);
        }
        lines.push('<div class="receipt-dash"></div>');
        if (!isUnpaidPrep) {
            const contactBits = [];
            if (c.phone) contactBits.push(`<div>Phone: ${escapeHtml(c.phone)}</div>`);
            if (c.address) contactBits.push(`<div>Address: ${escapeHtml(c.address).replace(/\n/g, '<br>')}</div>`);
            if (c.email) contactBits.push(`<div class="receipt-email-one-line">Email: ${escapeHtml(String(c.email).trim())}</div>`);
            if (contactBits.length) {
                lines.push(`<div>${contactBits.join('')}</div>`);
            } else {
                lines.push('<div class="receipt-muted">No store contact on file.</div>');
            }
            lines.push('<div class="receipt-dash"></div>');
        }
        lines.push('<div class="receipt-row receipt-bold"><span class="left">Item</span><span class="right">Amount</span></div>');
        lines.push('<div class="receipt-dash"></div>');
        const formatQty = (q) => {
            const x = Number(q);
            if (!Number.isFinite(x)) return '0';
            if (Math.abs(x - Math.round(x)) < 1e-9) return String(Math.round(x));
            let s = x.toFixed(4).replace(/\.?0+$/, '');
            return s || '0';
        };
        (r.items || []).forEach((it) => {
            const q = Number(it.quantity);
            const lt = Number(it.line_total);
            let unit = Number(it.unit_price);
            if (!Number.isFinite(unit) || Math.abs(unit) <= MONEY_EPS) {
                unit = Number.isFinite(q) && q > MONEY_EPS && Number.isFinite(lt) ? lt / q : 0;
            }
            const itemName = String(it.name || '');
            const flavorName = String(it.flavor_name || '').trim();
            lines.push(`<div class="receipt-item-name">${escapeHtml(itemName)}</div>`);
            if (flavorName) {
                lines.push(`<div class="receipt-item-name receipt-muted">  - ${escapeHtml(flavorName)}</div>`);
            }
            lines.push(`<div class="receipt-row receipt-item-price-line"><span class="left">${money(unit)} × ${formatQty(it.quantity)}</span><span class="right">${money(it.line_total)}</span></div>`);
        });
        lines.push('<div class="receipt-dash"></div>');
        lines.push(`<div class="receipt-row receipt-bold"><span class="left">TOTAL</span><span class="right">${money(r.grand_total)}</span></div>`);

        const tid = r.transaction_id != null ? `#${r.transaction_id}` : '';
        let when = '';
        if (r.created_at) {
            const d = new Date(String(r.created_at).replace(' ', 'T'));
            when = !Number.isNaN(d.getTime()) ? d.toLocaleString() : escapeHtml(r.created_at);
        }
        const meta = `${when || ''}${tid ? ` ${tid}` : ''}`.trim();

        if (isUnpaidPrep) {
            lines.push('<div class="receipt-dash"></div>');
            lines.push('<div class="receipt-center receipt-bold">UNPAID</div>');
            lines.push('<div class="receipt-bottom-spacer" aria-hidden="true"></div>');
        } else {
            const totalForVat = Number(r.grand_total || 0);
            const vatApplicable = r.vat_applicable !== false;
            if (vatApplicable) {
                const vatAmount = totalForVat > 0 ? (totalForVat * (12 / 112)) : 0;
                const vatableSales = Math.max(0, totalForVat - vatAmount);
                lines.push(`<div class="receipt-row"><span class="left">VATABLE SALES</span><span class="right">${money(vatableSales)}</span></div>`);
                lines.push(`<div class="receipt-row"><span class="left">VAT (12%)</span><span class="right">${money(vatAmount)}</span></div>`);
            }
            const tendered = r.amount_tendered != null ? Number(r.amount_tendered) : null;
            const change = r.change_amount != null ? Number(r.change_amount) : null;
            const ch0 = change != null ? change : 0;
            const pm = String(r.payment_method || '').trim().toLowerCase();
            const pmLabel = pm ? pm.toUpperCase().replace(/_/g, ' ') : '';
            if (pmLabel) lines.push(`<div class="receipt-row"><span class="left">PAYMENT</span><span class="right">${escapeHtml(pmLabel)}</span></div>`);
            if (pm === 'split' && Array.isArray(r.split_payments) && r.split_payments.length) {
                lines.push('<div class="receipt-row receipt-muted"><span class="left">SPLIT DETAILS</span><span class="right"></span></div>');
                r.split_payments.forEach((sp) => {
                    const sm = String(sp?.method || '').trim().toUpperCase().replace(/_/g, ' ');
                    const sa = Number(sp?.amount || 0);
                    if (!sm || !Number.isFinite(sa) || sa <= 0) return;
                    lines.push(`<div class="receipt-row"><span class="left">- ${escapeHtml(sm)}</span><span class="right">${money(sa)}</span></div>`);
                });
                const splitReceived = r.amount_tendered != null ? Number(r.amount_tendered) : null;
                if (splitReceived != null && Number.isFinite(splitReceived) && splitReceived > 0) {
                    lines.push(`<div class="receipt-row receipt-bold"><span class="left">TOTAL RECEIVED (SPLIT)</span><span class="right">${money(splitReceived)}</span></div>`);
                }
            }
            const refunded = r.refunded_amount != null ? Number(r.refunded_amount) : 0;
            const added = r.added_paid_amount != null ? Number(r.added_paid_amount) : 0;
            const ap = r.amount_paid != null ? Number(r.amount_paid) : 0;
            const basePaid = pm === 'cash'
                ? (ap > MONEY_EPS ? ap : (tendered != null ? Math.max(0, tendered - ch0) : null))
                : (r.amount_paid != null ? Number(r.amount_paid) : (tendered != null ? tendered : null));
            const baseAfterRefund = basePaid != null && Number.isFinite(basePaid) ? Math.max(0, basePaid - refunded) : null;
            const remainingRefundAfterBase = basePaid != null && Number.isFinite(basePaid) ? Math.max(0, refunded - basePaid) : refunded;
            const addedAfterRefund = Number.isFinite(added) ? Math.max(0, added - remainingRefundAfterBase) : 0;
            const netPaid = (baseAfterRefund != null && Number.isFinite(baseAfterRefund))
                ? Math.max(0, baseAfterRefund + addedAfterRefund)
                : null;
            if (pm === 'cash' && basePaid != null) {
                lines.push(`<div class="receipt-row"><span class="left">NET TO ORDER</span><span class="right">${money(basePaid)}</span></div>`);
                if (tendered != null && Number.isFinite(tendered) && Math.abs(tendered - basePaid) > MONEY_EPS) {
                    lines.push(`<div class="receipt-row receipt-muted"><span class="left">Cash tendered</span><span class="right">${money(tendered)}</span></div>`);
                }
            } else if (basePaid != null) {
                lines.push(`<div class="receipt-row"><span class="left">AMOUNT PAID</span><span class="right">${money(baseAfterRefund ?? basePaid)}</span></div>`);
            }
            if (refunded != null && Number.isFinite(refunded) && refunded > 0) {
                lines.push(`<div class="receipt-row"><span class="left">REFUND</span><span class="right">-${money(refunded)}</span></div>`);
            }
            if (addedAfterRefund != null && Number.isFinite(addedAfterRefund) && addedAfterRefund > 0) {
                lines.push(`<div class="receipt-row"><span class="left">ADDITIONAL PAID</span><span class="right">${money(addedAfterRefund)}</span></div>`);
            }
            if (netPaid != null) {
                lines.push(`<div class="receipt-row receipt-bold"><span class="left">NET PAID</span><span class="right">${money(netPaid)}</span></div>`);
            }
            const hasAdjust = (Number.isFinite(refunded) && refunded > 0) || (Number.isFinite(added) && added > 0);
            const finalChange = hasAdjust ? 0 : change;
            if (finalChange != null && Number.isFinite(finalChange)) {
                if (pm === 'split') {
                    if (finalChange > MONEY_EPS) {
                        lines.push(`<div class="receipt-row"><span class="left">CASH CHANGE</span><span class="right">${money(finalChange)}</span></div>`);
                    }
                } else {
                    lines.push(`<div class="receipt-row"><span class="left">CHANGE</span><span class="right">${money(finalChange)}</span></div>`);
                }
            }
            lines.push('<div class="receipt-dash"></div>');
            if (isBirRegistered) {
                lines.push('<div class="receipt-center receipt-bold">THIS SERVES AS AN OFFICIAL RECEIPT</div>');
                if (tid) lines.push(`<div class="receipt-row"><span class="left">OR No</span><span class="right">${escapeHtml(tid.replace(/^#/, ''))}</span></div>`);
                if (when) lines.push(`<div class="receipt-row"><span class="left">Date/Time</span><span class="right">${escapeHtml(when)}</span></div>`);
                const cashierName = String(r.cashier_name || '').trim();
                if (cashierName) lines.push(`<div class="receipt-row"><span class="left">Cashier Name</span><span class="right">${escapeHtml(cashierName)}</span></div>`);
                if (tid) lines.push(`<div class="receipt-row"><span class="left">Transaction No</span><span class="right">${escapeHtml(tid.replace(/^#/, ''))}</span></div>`);
            } else {
                lines.push('<div class="receipt-compliance-note"><div class="title">THIS IS NOT AN OFFICIAL RECEIPT</div><div class="sub">FOR INTERNAL / REFERENCE PURPOSES ONLY</div></div>');
            }
            if (!isBirRegistered && meta) lines.push(`<div class="receipt-center receipt-muted">${meta}</div>`);
            lines.push('<div class="receipt-center">&nbsp;</div>');
            lines.push('<div class="receipt-center receipt-bold">Thank you for your purchase!</div>');
            if (footerNote) {
                lines.push('<div class="receipt-center">&nbsp;</div>');
                const footerLines = footerNote
                    .split(/\r\n|\n|\r/g)
                    .map((x) => String(x || '').trim())
                    .filter(Boolean);
                if (footerLines.length) {
                    lines.push(`<div class="receipt-center receipt-bold">${footerLines.map((x) => escapeHtml(x)).join('<br>')}</div>`);
                }
            }
            lines.push('<div class="receipt-bottom-spacer" aria-hidden="true"></div>');
        }
        lines.push('</div>');
        return lines.join('');
    };
    const fitReceiptEmailLines = (rootEl) => {
        if (!rootEl) return;
        rootEl.querySelectorAll('.receipt-email-one-line').forEach((el) => {
            el.style.fontSize = '';
            let size = Number.parseFloat(window.getComputedStyle(el).fontSize || '10');
            if (!Number.isFinite(size) || size <= 0) size = 10;
            const min = 3;
            for (let i = 0; i < 40 && el.clientWidth > 0 && el.scrollWidth > el.clientWidth && size > min; i += 1) {
                size -= 0.25;
                el.style.fontSize = `${size}px`;
            }
        });
    };
    const scheduleFitReceiptEmailLines = (rootEl) => {
        if (!rootEl) return;
        requestAnimationFrame(() => fitReceiptEmailLines(rootEl));
        setTimeout(() => fitReceiptEmailLines(rootEl), 60);
    };
    const receiptModalElForEmailFit = document.getElementById('receiptModal');
    if (receiptModalElForEmailFit) {
        receiptModalElForEmailFit.addEventListener('shown.bs.modal', () => {
            scheduleFitReceiptEmailLines(document.getElementById('receiptPrintArea'));
        });
    }

    const receiptThermalCssUrl = <?= json_embed(url('css/receipt-thermal-print-doc.css')) ?>;
    const printReceiptDedicated = (rootEl) => {
        if (typeof window.printReceiptThermalDoc === 'function') {
            window.printReceiptThermalDoc(rootEl, {
                cssUrl: receiptThermalCssUrl,
                onPopupBlocked: () => {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'info',
                            title: 'Pop-up may have been blocked',
                            text: 'Printing in this page instead. If you still need a new window, allow pop-ups for this site.',
                        });
                    }
                },
            });
            return;
        }
        if (!rootEl) return;
        const markup = rootEl.innerHTML;
        if (!markup || !String(markup).trim()) {
            window.print();
            return;
        }
        window.print();
    };

    document.getElementById('receiptPrintBtn').addEventListener('click', () => {
        printReceiptDedicated(document.getElementById('receiptPrintArea'));
    });

    document.getElementById('receiptPrintWifiBtn')?.addEventListener('click', async () => {
        if (!thermalCfg.networkEnabled) return;
        const r = lastReceiptObject;
        if (!r) return Swal.fire({ icon: 'warning', title: 'No receipt', text: 'Complete a sale first.' });
        Swal.fire({ title: 'Sending to printer…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const body = await postReceiptJson(thermalCfg.networkUrl, r);
            Swal.close();
            if (!body.success) throw new Error(body.message || 'Failed');
            const n = body.copies != null ? Number(body.copies) : thermalCfg.lanCopies;
            const copies = Number.isFinite(n) && n > 0 ? n : 1;
            const sentText = copies > 1
                ? `Raw data sent to network printer (${copies} copies).`
                : 'Raw data sent to network printer.';
            Swal.fire({ icon: 'success', title: 'Sent', text: sentText, timer: 1800, showConfirmButton: false });
            const rmEl = document.getElementById('receiptModal');
            if (rmEl) {
                const inst = bootstrap.Modal.getInstance(rmEl) ?? bootstrap.Modal.getOrCreateInstance(rmEl);
                inst.hide();
            }
        } catch (err) {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Network print failed', text: String(err?.message || err) });
        }
    });

    document.getElementById('receiptPrintBleBtn')?.addEventListener('click', async () => {
        const r = lastReceiptObject;
        if (!r) return Swal.fire({ icon: 'warning', title: 'No receipt', text: 'Complete a sale first.' });
        try {
            Swal.fire({ title: 'Preparing data…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const bytes = await fetchEscposBytes(r);
            Swal.close();
            if (typeof window.mpgWriteEscposBluetooth !== 'function') {
                throw new Error('Bluetooth print module not loaded. Refresh the page.');
            }
            const copies = Number.isFinite(Number(thermalCfg.lanCopies)) ? Math.max(1, Number(thermalCfg.lanCopies)) : 1;
            const base = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes || []);
            let payload = base;
            if (copies > 1 && base.length > 0) {
                payload = new Uint8Array(base.length * copies);
                for (let i = 0; i < copies; i += 1) {
                    payload.set(base, i * base.length);
                }
            }
            await window.mpgWriteEscposBluetooth(payload);
            const rmEl = document.getElementById('receiptModal');
            if (rmEl) {
                const inst = bootstrap.Modal.getInstance(rmEl) ?? bootstrap.Modal.getOrCreateInstance(rmEl);
                inst.hide();
            }
        } catch (err) {
            Swal.close();
            if (err?.name === 'NotFoundError' || err?.name === 'SecurityError') return;
            Swal.fire({ icon: 'error', title: 'Bluetooth printing failed', text: String(err?.message || err) });
        }
    });
    const receiptBleSavedHint = document.getElementById('receiptBleSavedHint');
    const refreshReceiptBleHint = () => {
        if (!receiptBleSavedHint) return;
        const hasSaved = typeof window.mpgHasRememberedBluetoothDevice === 'function'
            ? !!window.mpgHasRememberedBluetoothDevice()
            : false;
        receiptBleSavedHint.textContent = hasSaved
            ? 'Saved Bluetooth printer will be used automatically.'
            : 'No saved Bluetooth printer yet.';
    };
    document.getElementById('receiptBleChangeBtn')?.addEventListener('click', () => {
        if (typeof window.mpgClearEscposBluetoothDevice === 'function') {
            window.mpgClearEscposBluetoothDevice();
        }
        refreshReceiptBleHint();
        Swal.fire({ icon: 'info', title: 'Bluetooth printer reset', text: 'Next Bluetooth print will ask you to select a printer.' });
    });
    refreshReceiptBleHint();

    const runCheckout = async (form) => {
        if (!Object.keys(cart).length) return Swal.fire({ icon: 'warning', title: 'Cart is empty' });

        // Final client-side validation before checkout request.
        // This catches insufficient stocks in the current cart quantities.
        for (const id of Object.keys(cart)) {
            const item = cart[id];
            const p = byId[Number(item.product_id || 0)];
            if (!p || !item) continue;
            const out = getOutOfStockIngredient(p, Number(item.quantity || 0));
            if (out) {
                return alertCheckoutOut(p, out);
            }
            if (Number(item.flavor_ingredient_id || 0) > 0) {
                const flavorObj = (p.flavors || []).find((f) => Number(f.id || 0) === Number(item.flavor_ingredient_id || 0)) || null;
                if (getOutOfStockFlavor(flavorObj, Number(item.quantity || 0))) {
                    return Swal.fire({ icon: 'warning', title: 'Out of stock', text: `${p.name}: ${item.flavor_name} flavor is out of stock.` });
                }
            }
        }

        const payment = await Swal.fire({
            icon: 'question',
            title: 'Payment details',
            html: `
                <div class="text-start">
                    <label class="form-label mb-1">Mode of payment</label>
                    <input type="hidden" id="swalPaymentMethod" value="cash">
                    <div class="d-grid gap-2 mb-2" id="swalPaymentCards">
                        <button type="button" class="btn btn-primary text-start swal-payment-card" data-method="cash"><i class="fa-solid fa-money-bill-wave me-2"></i>Cash</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-payment-card" data-method="card"><i class="fa-solid fa-credit-card me-2"></i>Card</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-payment-card" data-method="gcash"><i class="fa-solid fa-mobile-screen-button me-2"></i>GCash</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-payment-card" data-method="paymaya"><i class="fa-solid fa-wallet me-2"></i>PayMaya</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-payment-card" data-method="online_banking"><i class="fa-solid fa-building-columns me-2"></i>Online Banking</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-payment-card" data-method="split"><i class="fa-solid fa-layer-group me-2"></i>Split payment</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-payment-card" data-method="free"><i class="fa-solid fa-gift me-2"></i>Free (Employee)</button>
                    </div>
                    <div id="swalSplitWrap" class="border rounded p-2 mb-2 d-none">
                        <div class="small fw-semibold mb-2">Split amounts</div>
                        <div id="swalSplitRows" class="vstack gap-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="swalAddSplitRow">
                            <i class="fa-solid fa-plus me-1"></i>Add split row
                        </button>
                        <div class="form-text">If no cash row is used, split total must match exact grand total.</div>
                    </div>
                    <label class="form-label mb-1">Amount received</label>
                    <input id="swalAmountReceived" class="form-control" placeholder="0.00" inputmode="decimal" autocomplete="off">
                    <div id="swalQuickAmounts" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div class="form-text">For non-cash payments, this will be set to exact total.</div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Proceed',
            didOpen: () => {
                const methodEl = document.getElementById('swalPaymentMethod');
                const paymentCards = Array.from(document.querySelectorAll('#swalPaymentCards .swal-payment-card'));
                const amtEl = document.getElementById('swalAmountReceived');
                const quickWrap = document.getElementById('swalQuickAmounts');
                const splitWrap = document.getElementById('swalSplitWrap');
                const splitRowsEl = document.getElementById('swalSplitRows');
                const addSplitBtn = document.getElementById('swalAddSplitRow');
                const splitMethodOptionsNoCash = `
                    <option value="card">Card</option>
                    <option value="gcash">GCash</option>
                    <option value="paymaya">PayMaya</option>
                    <option value="online_banking">Online Banking</option>`;
                const total = Object.values(cart).reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.quantity || 0)), 0);
                const syncSplitTotalToAmountReceived = () => {
                    if (!splitRowsEl || !amtEl) return;
                    const splitSum = Array.from(splitRowsEl.querySelectorAll('.swal-split-amount'))
                        .reduce((s, inputEl) => s + (Number(String(inputEl.value || '').trim()) || 0), 0);
                    amtEl.value = toMoneyInputStr(splitSum);
                    amtEl.readOnly = true;
                    amtEl.disabled = false;
                    amtEl.removeAttribute('disabled');
                    amtEl.setAttribute('readonly', 'readonly');
                };
                const addSplitRow = (method = 'cash', amount = '', lockedCashRow = false) => {
                    if (!splitRowsEl) return;
                    const row = document.createElement('div');
                    row.className = 'd-flex gap-2 align-items-center';
                    row.innerHTML = `
                        <select class="form-select form-select-sm swal-split-method" style="max-width: 170px;"></select>
                        <input type="text" class="form-control form-control-sm swal-split-amount" placeholder="0.00" inputmode="decimal" autocomplete="off" enterkeyhint="done" spellcheck="false">
                        <button type="button" class="btn btn-sm btn-outline-danger swal-split-remove" title="Remove"><i class="fa-solid fa-xmark"></i></button>
                    `;
                    const methodEl = row.querySelector('.swal-split-method');
                    const amountEl = row.querySelector('.swal-split-amount');
                    const removeBtn = row.querySelector('.swal-split-remove');
                    if (methodEl) {
                        if (lockedCashRow) {
                            methodEl.innerHTML = '<option value="cash">Cash</option>';
                            methodEl.value = 'cash';
                            methodEl.disabled = true;
                        } else {
                            methodEl.innerHTML = splitMethodOptionsNoCash;
                            methodEl.value = method;
                        }
                    }
                    if (amountEl) {
                        amountEl.value = amount;
                        amountEl.disabled = false;
                        amountEl.readOnly = false;
                        amountEl.removeAttribute('disabled');
                        amountEl.removeAttribute('readonly');
                        amountEl.style.pointerEvents = 'auto';
                        amountEl.style.userSelect = 'auto';
                        amountEl.addEventListener('input', () => {
                            const raw = String(amountEl.value || '');
                            let cleaned = raw.replace(/[^\d.]/g, '');
                            const firstDot = cleaned.indexOf('.');
                            if (firstDot !== -1) {
                                cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
                            }
                            amountEl.value = cleaned;
                            syncSplitTotalToAmountReceived();
                        });
                    }
                    if (lockedCashRow && removeBtn) {
                        removeBtn.disabled = true;
                        removeBtn.classList.add('d-none');
                    } else {
                        removeBtn?.addEventListener('click', () => {
                            row.remove();
                            syncSplitTotalToAmountReceived();
                        });
                    }
                    splitRowsEl.appendChild(row);
                    syncSplitTotalToAmountReceived();
                };
                addSplitBtn?.addEventListener('click', () => addSplitRow('gcash', ''));
                const setActiveMethodCard = (method) => {
                    paymentCards.forEach((btn) => {
                        const isActive = String(btn.getAttribute('data-method') || '') === method;
                        btn.classList.toggle('btn-primary', isActive);
                        btn.classList.toggle('text-white', isActive);
                        btn.classList.toggle('btn-outline-primary', !isActive);
                    });
                };
                const sync = () => {
                    const method = String(methodEl?.value || 'cash');
                    setActiveMethodCard(method);
                    if (splitWrap) splitWrap.classList.toggle('d-none', method !== 'split');
                    if (method !== 'cash' && method !== 'free') {
                        if (method === 'split') {
                            amtEl.value = toMoneyInputStr(total);
                            amtEl.disabled = false;
                            amtEl.readOnly = true;
                            amtEl.removeAttribute('disabled');
                            amtEl.setAttribute('readonly', 'readonly');
                            if (quickWrap) quickWrap.innerHTML = '';
                            if (splitRowsEl) {
                                splitRowsEl.innerHTML = '';
                                addSplitRow('cash', toMoneyInputStr(0), true);
                            }
                        } else {
                            amtEl.value = toMoneyInputStr(total);
                            amtEl.disabled = true;
                            amtEl.readOnly = false;
                            amtEl.removeAttribute('readonly');
                            if (quickWrap) quickWrap.innerHTML = '';
                        }
                    } else if (method === 'free') {
                        amtEl.value = toMoneyInputStr(0);
                        amtEl.disabled = true;
                        amtEl.readOnly = false;
                        amtEl.removeAttribute('readonly');
                        if (quickWrap) quickWrap.innerHTML = '';
                    } else {
                        // CASH: keep the field disabled unless user explicitly taps "Enter amount".
                        amtEl.disabled = true;
                        if (!amtEl.value) amtEl.value = toMoneyInputStr(total);
                        if (quickWrap) {
                            const opts = [50, 100, 200, 500, 1000];
                            quickWrap.innerHTML = opts.map(v => `<button type="button" class="btn btn-sm btn-outline-secondary" data-amt="${v}">${v}</button>`).join('')
                                + `<button type="button" class="btn btn-sm btn-outline-primary" data-amt="custom">Enter amount</button>`;
                            quickWrap.querySelectorAll('button[data-amt]').forEach((b) => {
                                b.addEventListener('click', () => {
                                    const val = String(b.getAttribute('data-amt') || '');
                                    if (val === 'custom') {
                                        amtEl.value = '';
                                        amtEl.disabled = false;
                                        setTimeout(() => amtEl?.focus(), 50);
                                    } else {
                                        const n = Number(val);
                                        if (Number.isFinite(n)) {
                                            amtEl.value = toMoneyInputStr(n);
                                            // Keep the field disabled; quick buttons are the intended input.
                                        }
                                    }
                                });
                            });
                        }
                    }
                };
                paymentCards.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const method = String(btn.getAttribute('data-method') || 'cash');
                        if (methodEl) methodEl.value = method;
                        sync();
                    });
                });
                amtEl?.addEventListener('focus', () => {
                    const method = String(methodEl?.value || 'cash');
                    if (method === 'split') {
                        amtEl.disabled = false;
                        amtEl.readOnly = true;
                        amtEl.removeAttribute('disabled');
                        amtEl.setAttribute('readonly', 'readonly');
                    }
                });
                sync();
                // Focus only when Enter amount is tapped.
            },
            preConfirm: () => {
                const method = String(document.getElementById('swalPaymentMethod')?.value || 'cash');
                const total = Object.values(cart).reduce((sum, item) => sum + (Number(item.price || 0) * Number(item.quantity || 0)), 0);
                const raw = String(document.getElementById('swalAmountReceived')?.value || '').trim();
                const received = Number(raw);
                let splitPayments = [];
                if (method === 'split') {
                    const rows = Array.from(document.querySelectorAll('#swalSplitRows .d-flex'));
                    splitPayments = rows.map((row) => {
                        const m = String(row.querySelector('.swal-split-method')?.value || '').trim().toLowerCase();
                        const a = Number(String(row.querySelector('.swal-split-amount')?.value || '').trim());
                        return { method: m, amount: a };
                    }).filter((r) => r.method && Number.isFinite(r.amount) && r.amount > 0);
                    if (!splitPayments.length) {
                        Swal.showValidationMessage('Add at least one split payment amount.');
                        return false;
                    }
                    const splitSum = splitPayments.reduce((s, r) => s + Number(r.amount || 0), 0);
                    const hasCash = splitPayments.some((r) => r.method === 'cash');
                    if (splitSum < total) {
                        Swal.showValidationMessage('Split payment total is less than grand total.');
                        return false;
                    }
                    if (!hasCash && Math.abs(splitSum - total) > 0.009) {
                        Swal.showValidationMessage('Without cash, split payment must equal exact grand total.');
                        return false;
                    }
                    return { method, received: splitSum, splitPayments };
                }
                const finalReceived = method === 'cash' ? received : (method === 'free' ? 0 : total);
                if (!Number.isFinite(finalReceived) || finalReceived < 0) {
                    Swal.showValidationMessage('Enter a valid amount received.');
                    return false;
                }
                if (method === 'cash' && finalReceived < total) {
                    Swal.showValidationMessage('Amount received is less than total.');
                    return false;
                }
                return { method, received: finalReceived, splitPayments: [] };
            },
        });
        if (!payment.isConfirmed) return;
        const paymentMethod = payment.value.method;
        const tendered = Number(payment.value.received || 0);
        const splitPayments = Array.isArray(payment.value.splitPayments) ? payment.value.splitPayments : [];

        if (!form) return Swal.fire({ icon: 'error', title: 'Checkout form missing' });
        const fd = new FormData(form);
        fd.set('payment_method', String(paymentMethod));
        fd.set('amount_tendered', String(tendered));
        if (paymentMethod === 'split') {
            fd.set('split_payments', JSON.stringify(splitPayments));
        }
        try {
            Swal.fire({
                title: 'Preparing receipt…',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
            const res = await fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            Swal.close();
            if (!res.ok || !body.success) {
                return Swal.fire({ icon: 'error', title: 'Checkout failed', text: body.message || 'Please try again.' });
            }
            lastReceiptObject = body.receipt || null;
            const rmTitle = document.getElementById('receiptModalTitle');
            if (rmTitle) rmTitle.textContent = 'Receipt (customer)';
            const receiptArea = document.getElementById('receiptPrintArea');
            if (receiptArea) {
                receiptArea.innerHTML = buildReceiptHtml(body.receipt);
            }
            const receiptModalEl = document.getElementById('receiptModal');
            const receiptModal = bootstrap.Modal.getOrCreateInstance(receiptModalEl);
            receiptModal.show();
            scheduleFitReceiptEmailLines(receiptArea);
            if (mobileCartModal) mobileCartModal.hide();
            Object.keys(cart).forEach((k) => { delete cart[k]; });
            renderCart();
        } catch {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not complete checkout.' });
        }
    };
    document.getElementById('checkoutBtn')?.addEventListener('click', async () => {
        await runCheckout(document.getElementById('checkoutForm'));
    });
    document.getElementById('mobileCheckoutBtn')?.addEventListener('click', async () => {
        await runCheckout(document.getElementById('mobileCheckoutForm'));
    });

    const csrf = document.querySelector('input[name="_token"]')?.value || '';
    const pendingStoreUrl = <?= json_embed(url('/tenant/pos/pending')) ?>;

    const formatWhen = (raw) => {
        if (!raw) return '';
        const d = new Date(String(raw).replace(' ', 'T'));
        return !Number.isNaN(d.getTime()) ? d.toLocaleString() : String(raw);
    };

    const pendingMetaModalEl = document.getElementById('pendingMetaModal');
    const pendingMetaModal = pendingMetaModalEl ? bootstrap.Modal.getOrCreateInstance(pendingMetaModalEl) : null;
    const pendingNameInput = document.getElementById('pendingNameInput');
    const pendingContactInput = document.getElementById('pendingContactInput');
    const pendingMetaSaveBtn = document.getElementById('pendingMetaSaveBtn');

    const runSavePending = async () => {
        if (!Object.keys(cart).length) return Swal.fire({ icon: 'warning', title: 'Cart is empty' });
        if (!pendingMetaModal) return;
        if (pendingNameInput) pendingNameInput.value = '';
        if (pendingContactInput) pendingContactInput.value = '';
        pendingMetaModal.show();
        setTimeout(() => pendingNameInput?.focus(), 120);
    };
    document.getElementById('savePendingBtn')?.addEventListener('click', runSavePending);
    document.getElementById('mobileSavePendingBtn')?.addEventListener('click', runSavePending);

    pendingMetaSaveBtn?.addEventListener('click', async () => {
        const name = String(pendingNameInput?.value || '').trim();
        const contact = String(pendingContactInput?.value || '').trim();
        if (!name) {
            pendingNameInput?.focus();
            return Swal.fire({ icon: 'warning', title: 'Name is required', text: 'Please enter the creditor name.' });
        }

        const entries = Object.values(cart).map((c) => ({ product_id: c.product_id, quantity: c.quantity, flavor_ingredient_id: Number(c.flavor_ingredient_id || 0) }));
        const fd = new FormData();
        if (csrf) fd.set('_token', csrf);
        fd.set('pending_name', name);
        if (contact) fd.set('pending_contact', contact);
        entries.forEach((it, idx) => {
            fd.set(`items[${idx}][product_id]`, String(it.product_id));
            fd.set(`items[${idx}][quantity]`, String(it.quantity));
            fd.set(`items[${idx}][flavor_ingredient_id]`, String(Number(it.flavor_ingredient_id || 0)));
        });
        try {
            const res = await fetch(pendingStoreUrl, {
                method: 'POST',
                body: fd,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                return Swal.fire({ icon: 'error', title: 'Save pending failed', text: body.message || 'Please try again.' });
            }
            Object.keys(cart).forEach((k) => { delete cart[k]; });
            renderCart();
            pendingMetaModal?.hide();
            if (mobileCartModal) mobileCartModal.hide();
            if (body.unpaid_prep_receipt) {
                lastReceiptObject = body.unpaid_prep_receipt;
                const rmTitle = document.getElementById('receiptModalTitle');
                if (rmTitle) rmTitle.textContent = 'Unpaid order (Bluetooth / print)';
                const ra = document.getElementById('receiptPrintArea');
                if (ra) ra.innerHTML = buildReceiptHtml(body.unpaid_prep_receipt);
                const receiptModalEl = document.getElementById('receiptModal');
                const receiptModal = bootstrap.Modal.getOrCreateInstance(receiptModalEl);
                receiptModal.show();
                scheduleFitReceiptEmailLines(ra);
            } else {
                Swal.fire({ icon: 'success', title: 'Saved as pending', text: `Pending #${body.pending_id}` });
            }
        } catch {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not save pending.' });
        }
    });

    const searchInput = document.getElementById('searchInput');
    searchInput?.addEventListener('input', () => {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            const value = searchInput.value.trim();
            if (value === (currentSearch || '').trim()) return;
            const url = new URL(window.location.href);
            if (value) url.searchParams.set('search', value);
            else url.searchParams.delete('search');
            window.location.assign(url.toString());
        }, 300);
    });

    renderCart();
})();
</script>
