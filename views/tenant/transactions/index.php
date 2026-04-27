<div class="modern-page-header d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="small text-muted">Filter by status:</div>
        <div style="min-width: 220px;">
            <select class="form-select form-select-sm" id="txStatusFilter" aria-label="Filter transactions by status">
                <option value="">All</option>
                <option value="completed">Completed</option>
                <option value="pending">Pending</option>
                <option value="void">Cancelled</option>
            </select>
        </div>
        <div class="small text-muted ms-1">Payment:</div>
        <div style="min-width: 220px;">
            <select class="form-select form-select-sm" id="txPaymentFilter" aria-label="Filter transactions by payment method">
                <option value="">All</option>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="gcash">GCash</option>
                <option value="paymaya">PayMaya</option>
                <option value="online_banking">Online Banking</option>
                <option value="split">Split payment</option>
                <option value="free">Free</option>
            </select>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="small text-muted">Date:</div>
        <input type="date" class="form-control form-control-sm" id="txDateFilter" style="min-width: 180px;">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="txDateTodayBtn">Today</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="txDateClearBtn">All dates</button>
    </div>
</div>

<div class="card modern-section">
    <div class="card-body table-responsive">
        <table class="table table-striped w-100" id="transactionsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Cashier</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Paid</th>
                    <th>Adjustment/Change</th>
                    <th>Status</th>
                    <th>Details</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="transactionReceiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="transactionReceiptModalTitle"<?= empty($receipt_print_allowed) ? ' data-mpg-trial-print="1"' : '' ?>>
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-md-down">
        <div class="modal-content mpg-receipt-modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title" id="transactionReceiptModalTitle">Receipt</h5>
            </div>
            <div class="modal-body">
                <div id="transactionReceiptPrintArea" class="receipt-print-area small"></div>
            </div>
            <div class="modal-footer flex-column flex-sm-row flex-wrap gap-2 justify-content-stretch justify-content-sm-center w-100 mpg-receipt-modal-footer">
                <div class="d-grid d-sm-flex gap-2 w-100 flex-sm-grow-0 flex-sm-wrap justify-content-sm-center">
                    <button type="button" class="btn btn-primary text-white w-100 mpg-receipt-action-btn <?= empty($receipt_print_allowed) ? 'd-none' : '' ?>" id="transactionReceiptPrintBtn"><i class="fa-solid fa-print me-1"></i>Print</button>
                    <button type="button" class="btn btn-success text-white <?= (empty($thermal_receipt_network_enabled) || empty($receipt_print_allowed)) ? 'd-none' : '' ?> w-100 mpg-receipt-action-btn" id="transactionReceiptPrintWifiBtn" title="Server sends raw data to printer on LAN (phone/tablet/APK when host is configured)"><i class="fa-solid fa-wifi me-1"></i>Wi‑Fi / LAN</button>
                    <button type="button" class="btn btn-primary text-white mpg-btn-bluetooth-thermal w-100 mpg-receipt-action-btn <?= empty($receipt_print_allowed) ? 'd-none' : '' ?>" id="transactionReceiptPrintBleBtn" title="Bluetooth print"><i class="fa-brands fa-bluetooth-b me-1"></i>Bluetooth print</button>
                    <button type="button" class="btn btn-secondary text-white w-100 mpg-receipt-action-btn" data-bs-dismiss="modal">Close</button>
                </div>
                <div class="w-100 d-flex flex-wrap gap-2 align-items-center justify-content-center mpg-btn-bluetooth-thermal <?= empty($receipt_print_allowed) ? 'd-none' : '' ?>">
                    <span class="small text-muted" id="transactionReceiptBleSavedHint">No saved Bluetooth printer yet.</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="transactionReceiptBleChangeBtn">Change Bluetooth printer</button>
                </div>
                <?php if (empty($receipt_print_allowed)): ?>
                    <div class="w-100 small text-warning text-center fw-semibold">Premium: receipt printing is not included on the Free version — you can still view the receipt below.</div>
                    <div class="w-100 d-grid">
                        <a href="<?= e(url('/tenant/plans')) ?>" class="btn btn-warning btn-sm fw-semibold"><i class="fa-solid fa-tags me-1"></i>View plans & pricing</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editTransactionModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="editTransactionModalTitle">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTransactionModalTitle">Checkout update</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">
                    This will update the order like checking out again: it adjusts inventory and creates an activity log.
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-8">
                        <div id="editTxMeta" class="small text-muted mb-2"></div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="mb-0">Cart items</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="editAddLineBtn">
                                <i class="fa fa-plus me-1"></i>Add item
                            </button>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end" style="width:120px;">Qty</th>
                                        <th class="text-end" style="width:140px;">Price</th>
                                        <th class="text-end" style="width:110px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="editRemoveTbody">
                                    <tr><td colspan="4" class="text-muted text-center py-3">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h6 class="mb-2">Add items</h6>
                        <div id="editAddWrap" class="vstack gap-2"></div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="fw-semibold">Order summary</div>
                                    <span class="badge text-bg-light border" id="editSummaryStatus">—</span>
                                </div>
                                <div class="small text-muted mb-3" id="editSummaryMeta">—</div>

                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted">Items</span>
                                    <span id="editSummaryCount">0</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span class="fw-semibold">PHP <span id="editSummarySubtotal">0.00</span></span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">Total</span>
                                    <span class="fw-bold">PHP <span id="editSummaryTotal">0.00</span></span>
                                </div>
                                <div class="small text-muted mt-2" id="editSummaryHint">
                                    Use “Void” to remove an item from this updated checkout.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="editSaveBtn">
                    <i class="fa fa-credit-card me-1"></i>Confirm update
                </button>
            </div>
        </div>
    </div>
</div>
<style>
.receipt-print-area { display: flex; justify-content: center; }
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
.receipt-dash { border-top: 1px dashed #666; margin: .22rem 0; }
.receipt-row { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
.receipt-row .left { min-width: 0; flex: 1 1 auto; }
.receipt-row .right { flex: 0 0 auto; text-align: right; white-space: nowrap; }
.receipt-item-name { font-weight: 600; margin-bottom: 0.06rem; word-break: break-word; }
.receipt-item-price-line { margin-bottom: 0.35rem; }
.receipt-email-one-line { display: block; width: 100%; white-space: nowrap; }
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

@media print {
    /* Thermal receipt: 60mm roll, black & white, avoid extra blank page */
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
    #transactionReceiptModal,
    #transactionReceiptModal * {
        visibility: visible !important;
        box-shadow: none !important;
        text-shadow: none !important;
    }
    #transactionReceiptModal {
        position: absolute !important;
        inset: 0 auto auto 0 !important;
        width: 60mm !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        border: 0 !important;
        z-index: 9999 !important;
        filter: none !important;
    }
    #transactionReceiptModal .modal-dialog {
        max-width: 60mm !important;
        width: 60mm !important;
        margin: 0 !important;
        transform: none !important;
        height: auto !important;
    }
    #transactionReceiptModal .modal-content {
        border: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
        color: #000 !important;
        height: auto !important;
    }
    #transactionReceiptModal .modal-header,
    #transactionReceiptModal .modal-footer {
        display: none !important;
    }
    #transactionReceiptModal .modal-body {
        padding: 0 !important;
        overflow: visible !important;
        max-height: none !important;
        background: #fff !important;
    }
    #transactionReceiptPrintArea {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        justify-content: flex-start !important;
    }
    #transactionReceiptPrintArea .receipt-paper,
    #transactionReceiptModal .receipt-paper {
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
    #transactionReceiptModal .receipt-paper * {
        color: #000 !important;
        background: transparent !important;
    }
    #transactionReceiptModal .receipt-muted {
        color: #333 !important;
    }
    #transactionReceiptModal .receipt-dash {
        border-top-color: #000 !important;
    }
    #transactionReceiptModal .receipt-paper.receipt-unpaid-prep .receipt-unpaid-watermark {
        color: rgba(0, 0, 0, 0.18) !important;
        print-color-adjust: exact !important;
        -webkit-print-color-adjust: exact !important;
    }
}
</style>
<script>
(() => {
    const escapeHtml = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
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

    const thermalCfg = <?= json_embed([
        'networkUrl' => $thermal_receipt_network_url ?? '',
        'escposUrl' => $thermal_receipt_escpos_url ?? '',
        'networkEnabled' => ! empty($thermal_receipt_network_enabled),
        'lanCopies' => max(1, min(10, (int) ($thermal_receipt_lan_copies ?? 1))),
    ]) ?>;
    let lastTxReceiptObject = null;

    const postReceiptJson = async (url, receipt) => {
        const tok = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const params = new URLSearchParams();
        params.set('_token', tok);
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
        if (businessStyle && !isUnpaidPrep) lines.push(`<div class="receipt-center receipt-muted">${escapeHtml(businessStyle)}</div>`);
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
            lines.push(contactBits.length ? `<div>${contactBits.join('')}</div>` : '<div class="receipt-muted">No store contact on file.</div>');
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
            const ch0 = r.change_amount != null ? Number(r.change_amount) : 0;
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
            if (netPaid != null) lines.push(`<div class="receipt-row receipt-bold"><span class="left">NET PAID</span><span class="right">${money(netPaid)}</span></div>`);
            const hasAdjust = (Number.isFinite(refunded) && refunded > 0) || (Number.isFinite(added) && added > 0);
            const change = hasAdjust ? 0 : (r.change_amount != null ? Number(r.change_amount) : null);
            if (change != null && Number.isFinite(change)) {
                if (pm === 'split') {
                    if (change > MONEY_EPS) {
                        lines.push(`<div class="receipt-row"><span class="left">CASH CHANGE</span><span class="right">${money(change)}</span></div>`);
                    }
                } else {
                    lines.push(`<div class="receipt-row"><span class="left">CHANGE</span><span class="right">${money(change)}</span></div>`);
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

    const receiptModalEl = document.getElementById('transactionReceiptModal');
    const receiptPrintAreaEl = document.getElementById('transactionReceiptPrintArea');
    const receiptModal = receiptModalEl ? bootstrap.Modal.getOrCreateInstance(receiptModalEl) : null;
    if (receiptModalEl) {
        receiptModalEl.addEventListener('shown.bs.modal', () => {
            scheduleFitReceiptEmailLines(receiptPrintAreaEl);
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

    document.getElementById('transactionReceiptPrintBtn')?.addEventListener('click', () => {
        printReceiptDedicated(receiptPrintAreaEl);
    });

    document.getElementById('transactionReceiptPrintWifiBtn')?.addEventListener('click', async () => {
        if (!thermalCfg.networkEnabled) return;
        const r = lastTxReceiptObject;
        if (!r) return Swal.fire({ icon: 'warning', title: 'No receipt', text: 'Open a receipt first.' });
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
            const txRmEl = document.getElementById('transactionReceiptModal');
            if (txRmEl) {
                const inst = bootstrap.Modal.getInstance(txRmEl) ?? bootstrap.Modal.getOrCreateInstance(txRmEl);
                inst.hide();
            }
        } catch (err) {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Network print failed', text: String(err?.message || err) });
        }
    });

    document.getElementById('transactionReceiptPrintBleBtn')?.addEventListener('click', async () => {
        const r = lastTxReceiptObject;
        if (!r) return Swal.fire({ icon: 'warning', title: 'No receipt', text: 'Open a receipt first.' });
        try {
            if (typeof window.mpgPrimeEscposBluetoothPermission === 'function') {
                await window.mpgPrimeEscposBluetoothPermission();
            }
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
            const txRmEl = document.getElementById('transactionReceiptModal');
            if (txRmEl) {
                const inst = bootstrap.Modal.getInstance(txRmEl) ?? bootstrap.Modal.getOrCreateInstance(txRmEl);
                inst.hide();
            }
        } catch (err) {
            Swal.close();
            if (err?.name === 'NotFoundError' || err?.name === 'SecurityError') return;
            Swal.fire({ icon: 'error', title: 'Bluetooth printing failed', text: String(err?.message || err) });
        }
    });
    const txBleSavedHint = document.getElementById('transactionReceiptBleSavedHint');
    const refreshTxBleHint = () => {
        if (!txBleSavedHint) return;
        const hasSaved = typeof window.mpgHasRememberedBluetoothDevice === 'function'
            ? !!window.mpgHasRememberedBluetoothDevice()
            : false;
        txBleSavedHint.textContent = hasSaved
            ? 'Saved Bluetooth printer will be used automatically.'
            : 'No saved Bluetooth printer yet.';
    };
    document.getElementById('transactionReceiptBleChangeBtn')?.addEventListener('click', () => {
        if (typeof window.mpgClearEscposBluetoothDevice === 'function') {
            window.mpgClearEscposBluetoothDevice();
        }
        refreshTxBleHint();
        Swal.fire({ icon: 'info', title: 'Bluetooth printer reset', text: 'Next Bluetooth print will ask you to select a printer.' });
    });
    refreshTxBleHint();

    const statusFilterEl = document.getElementById('txStatusFilter');
    const paymentFilterEl = document.getElementById('txPaymentFilter');
    const dateFilterEl = document.getElementById('txDateFilter');
    const dateTodayBtn = document.getElementById('txDateTodayBtn');
    const dateClearBtn = document.getElementById('txDateClearBtn');

    // Default date filter must be today.
    if (dateFilterEl && !dateFilterEl.value) {
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        dateFilterEl.value = `${yyyy}-${mm}-${dd}`;
    }

    initServerDataTable('#transactionsTable', {
        ajax: {
            url: '<?= e(route('tenant.transactions.index')) ?>',
            data: (d) => {
                d.datatable = 1;
                d.status = statusFilterEl?.value || '';
                d.payment_method = paymentFilterEl?.value || '';
                d.date = dateFilterEl?.value || '';
            }
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 40 },
            { targets: 4, responsivePriority: 35 },
            { targets: 5, responsivePriority: 3 },
            { targets: 6, responsivePriority: 60 },
            { targets: 7, responsivePriority: 65 },
            { targets: 8, responsivePriority: 70 },
            { targets: 9, responsivePriority: 45 },
            { targets: 10, orderable: false, searchable: false, responsivePriority: 55 },
            { targets: 11, orderable: false, searchable: false, responsivePriority: 5 },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'date' },
            { data: 'cashier' },
            { data: 'qty' },
            { data: 'total' },
            { data: 'payment_method' },
            { data: 'amount_paid' },
            { data: 'change_amount' },
            { data: 'status' },
            { data: 'details' },
            { data: 'action' },
        ],
    });

    statusFilterEl?.addEventListener('change', () => {
        try { $('#transactionsTable').DataTable().ajax.reload(null, true); } catch {}
    });
    paymentFilterEl?.addEventListener('change', () => {
        try { $('#transactionsTable').DataTable().ajax.reload(null, true); } catch {}
    });
    dateFilterEl?.addEventListener('change', () => {
        try { $('#transactionsTable').DataTable().ajax.reload(null, true); } catch {}
    });
    dateTodayBtn?.addEventListener('click', () => {
        if (!dateFilterEl) return;
        const now = new Date();
        const yyyy = now.getFullYear();
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const dd = String(now.getDate()).padStart(2, '0');
        dateFilterEl.value = `${yyyy}-${mm}-${dd}`;
        try { $('#transactionsTable').DataTable().ajax.reload(null, true); } catch {}
    });
    dateClearBtn?.addEventListener('click', () => {
        if (!dateFilterEl) return;
        dateFilterEl.value = '';
        try { $('#transactionsTable').DataTable().ajax.reload(null, true); } catch {}
    });

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const pendingPayBaseUrl = <?= json_embed(url('/tenant/pos/pending')) ?>;

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-reprint-receipt');
        if (!btn) return;
        const url = btn.getAttribute('data-receipt-url') || '';
        if (!url || !receiptPrintAreaEl || !receiptModal) return;
        try {
            Swal.fire({
                title: 'Loading receipt…',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            Swal.close();
            if (!res.ok || !body.success) {
                Swal.fire({ icon: 'error', title: 'Receipt unavailable', text: body.message || 'Could not load receipt.' });
                return;
            }
            lastTxReceiptObject = body.receipt || null;
            const txRmTitle = document.getElementById('transactionReceiptModalTitle');
            if (txRmTitle) {
                txRmTitle.textContent = (body.receipt && (body.receipt.unpaid_prep_receipt || body.receipt.kitchen_slip || body.receipt.unpaid_watermark))
                    ? 'Unpaid order (Bluetooth / print)'
                    : 'Receipt (customer)';
            }
            receiptPrintAreaEl.innerHTML = buildReceiptHtml(body.receipt || {});
            receiptModal.show();
            scheduleFitReceiptEmailLines(receiptPrintAreaEl);
        } catch {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not load receipt. Please try again.' });
        }
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-pay-pending');
        if (!btn) return;
        const id = Number(btn.dataset.id || 0);
        if (!id) return;

        const pendingName = String(btn.dataset.name || '').trim();
        const pendingContact = String(btn.dataset.contact || '').trim();
        const pendingTotal = Number(String(btn.dataset.total || '0').trim()) || 0;
        const creditorDisplay = pendingName ? escapeHtml(pendingName) : '—';
        const contactDisplay = pendingContact ? escapeHtml(pendingContact) : '—';
        const ask = await Swal.fire({
            icon: 'question',
            title: `Pay/Deposit Pending #${id}`,
            html: `
                <div class="text-start">
                    <label class="form-label mb-1 text-muted small" for="swalCreditorName">Name of Creditor</label>
                    <input id="swalCreditorName" type="text" class="form-control form-control-sm mb-2 text-muted bg-light" value="${creditorDisplay}" readonly disabled tabindex="-1" aria-readonly="true">
                    <label class="form-label mb-1 text-muted small" for="swalCreditorContact">Contact number <span class="fw-normal">(optional)</span></label>
                    <input id="swalCreditorContact" type="text" class="form-control form-control-sm mb-3 text-muted bg-light" value="${contactDisplay}" readonly disabled tabindex="-1" aria-readonly="true">
                    <label class="form-label mb-1">Mode of payment</label>
                    <input type="hidden" id="swalPayPendingPaymentMethod" value="cash">
                    <div class="d-grid gap-2 mb-2" id="swalPayPendingPaymentCards">
                        <button type="button" class="btn btn-primary text-start swal-pay-pending-payment-card" data-method="cash"><i class="fa-solid fa-money-bill-wave me-2"></i>Cash</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-pay-pending-payment-card" data-method="card"><i class="fa-solid fa-credit-card me-2"></i>Card</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-pay-pending-payment-card" data-method="gcash"><i class="fa-solid fa-mobile-screen-button me-2"></i>GCash</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-pay-pending-payment-card" data-method="paymaya"><i class="fa-solid fa-wallet me-2"></i>PayMaya</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-pay-pending-payment-card" data-method="online_banking"><i class="fa-solid fa-building-columns me-2"></i>Online Banking</button>
                        <button type="button" class="btn btn-outline-primary text-start swal-pay-pending-payment-card" data-method="split"><i class="fa-solid fa-layer-group me-2"></i>Split payment</button>
                    </div>
                    <div id="swalPayPendingSplitWrap" class="border rounded p-2 mb-2 d-none">
                        <div class="small fw-semibold mb-2">Split amounts</div>
                        <div id="swalPayPendingSplitRows" class="vstack gap-2"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="swalPayPendingAddSplitRow">
                            <i class="fa-solid fa-plus me-1"></i>Add split row
                        </button>
                        <div class="form-text">If no cash row is used, split total must match exact grand total.</div>
                    </div>
                    <label class="form-label mb-1">Amount to pay now (Deposit allowed)</label>
                    <input id="swalPayAmountReceived" class="form-control" placeholder="0.00" inputmode="decimal" autocomplete="off">
                    <div id="swalPayPendingQuickAmounts" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div class="form-text">You can collect partial payment and settle the remaining balance later.</div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirm payment / deposit',
            didOpen: () => {
                const methodEl = document.getElementById('swalPayPendingPaymentMethod');
                const paymentCards = Array.from(document.querySelectorAll('#swalPayPendingPaymentCards .swal-pay-pending-payment-card'));
                const amtEl = document.getElementById('swalPayAmountReceived');
                const quickWrap = document.getElementById('swalPayPendingQuickAmounts');
                const splitWrap = document.getElementById('swalPayPendingSplitWrap');
                const splitRowsEl = document.getElementById('swalPayPendingSplitRows');
                const addSplitBtn = document.getElementById('swalPayPendingAddSplitRow');
                const splitMethodOptionsNoCash = `
                    <option value="card">Card</option>
                    <option value="gcash">GCash</option>
                    <option value="paymaya">PayMaya</option>
                    <option value="online_banking">Online Banking</option>`;
                const total = pendingTotal;
                const syncSplitTotalToAmountReceived = () => {
                    if (!splitRowsEl || !amtEl) return;
                    const splitSum = Array.from(splitRowsEl.querySelectorAll('.swal-pay-pending-split-amount'))
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
                        <select class="form-select form-select-sm swal-pay-pending-split-method" style="max-width: 170px;"></select>
                        <input type="text" class="form-control form-control-sm swal-pay-pending-split-amount" placeholder="0.00" inputmode="decimal" autocomplete="off" enterkeyhint="done" spellcheck="false">
                        <button type="button" class="btn btn-sm btn-outline-danger swal-pay-pending-split-remove" title="Remove"><i class="fa-solid fa-xmark"></i></button>
                    `;
                    const methodEl = row.querySelector('.swal-pay-pending-split-method');
                    const amountEl = row.querySelector('.swal-pay-pending-split-amount');
                    const removeBtn = row.querySelector('.swal-pay-pending-split-remove');
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
                    paymentCards.forEach((b) => {
                        const isActive = String(b.getAttribute('data-method') || '') === method;
                        b.classList.toggle('btn-primary', isActive);
                        b.classList.toggle('text-white', isActive);
                        b.classList.toggle('btn-outline-primary', !isActive);
                    });
                };
                const sync = () => {
                    const method = String(methodEl?.value || 'cash');
                    setActiveMethodCard(method);
                    if (splitWrap) splitWrap.classList.toggle('d-none', method !== 'split');
                    if (method !== 'cash') {
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
                            if (!String(amtEl.value || '').trim()) {
                                amtEl.value = toMoneyInputStr(total);
                            }
                            amtEl.disabled = false;
                            amtEl.readOnly = false;
                            amtEl.removeAttribute('readonly');
                            if (quickWrap) quickWrap.innerHTML = '';
                        }
                    } else {
                        amtEl.disabled = true;
                        if (!amtEl.value) amtEl.value = toMoneyInputStr(total);
                        if (quickWrap) {
                            const opts = [50, 100, 200, 500, 1000];
                            quickWrap.innerHTML = opts.map((v) => `<button type="button" class="btn btn-sm btn-outline-secondary" data-amt="${v}">${v}</button>`).join('')
                                + '<button type="button" class="btn btn-sm btn-outline-primary" data-amt="custom">Enter amount</button>';
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
            },
            preConfirm: () => {
                const method = String(document.getElementById('swalPayPendingPaymentMethod')?.value || 'cash');
                const total = pendingTotal;
                const raw = String(document.getElementById('swalPayAmountReceived')?.value || '').trim();
                const received = Number(raw);
                let splitPayments = [];
                if (method === 'split') {
                    const rows = Array.from(document.querySelectorAll('#swalPayPendingSplitRows .d-flex'));
                    splitPayments = rows.map((row) => {
                        const m = String(row.querySelector('.swal-pay-pending-split-method')?.value || '').trim().toLowerCase();
                        const a = Number(String(row.querySelector('.swal-pay-pending-split-amount')?.value || '').trim());
                        return { method: m, amount: a };
                    }).filter((r) => r.method && Number.isFinite(r.amount) && r.amount > 0);
                    if (!splitPayments.length) {
                        Swal.showValidationMessage('Add at least one split payment amount.');
                        return false;
                    }
                    const splitSum = splitPayments.reduce((s, r) => s + Number(r.amount || 0), 0);
                    const hasCash = splitPayments.some((r) => r.method === 'cash');
                    if (splitSum <= 0) {
                        Swal.showValidationMessage('Enter split payment amounts greater than 0.');
                        return false;
                    }
                    if (!hasCash && splitSum - total > 0.009) {
                        Swal.showValidationMessage('Without cash, split payment cannot exceed remaining balance.');
                        return false;
                    }
                    return { method, received: splitSum, splitPayments };
                }
                const finalReceived = method === 'cash' ? received : received;
                if (!Number.isFinite(finalReceived) || finalReceived < 0) {
                    Swal.showValidationMessage('Enter a valid amount received.');
                    return false;
                }
                if (finalReceived <= 0) {
                    Swal.showValidationMessage('Enter an amount paid greater than 0.');
                    return false;
                }
                return { method, received: finalReceived, splitPayments: [] };
            },
        });
        if (!ask.isConfirmed) return;
        const paymentMethod = ask.value.method;
        const tendered = Number(ask.value.received || 0);
        const splitPayments = Array.isArray(ask.value.splitPayments) ? ask.value.splitPayments : [];

        const fd = new FormData();
        if (csrf) fd.set('_token', csrf);
        fd.set('amount_tendered', String(tendered));
        fd.set('payment_method', String(paymentMethod));
        if (paymentMethod === 'split') {
            fd.set('split_payments', JSON.stringify(splitPayments));
        }

        try {
            Swal.fire({
                title: 'Recording payment/deposit…',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading(),
            });
            const res = await fetch(`${pendingPayBaseUrl}/${id}/pay`, {
                method: 'POST',
                body: fd,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            Swal.close();
            if (!res.ok || !body.success) {
                return Swal.fire({ icon: 'error', title: 'Payment failed', text: body.message || 'Please try again.' });
            }
            lastTxReceiptObject = null;
            try { $('#transactionsTable').DataTable().ajax.reload(null, false); } catch {}
            const rem = Number(body.remaining_balance || 0);
            const hasRemaining = Number.isFinite(rem) && rem > 0.000001;
            await Swal.fire({
                icon: 'success',
                title: hasRemaining ? 'Deposit recorded' : 'Payment completed',
                html: hasRemaining
                    ? `<p class="mb-1 text-start">Remaining balance: <strong>${money(rem)}</strong>.</p><p class="mb-0 text-start">You can collect the next payment later from this Pending order.</p>`
                    : '<p class="mb-0 text-start">When you are ready to give the customer their official receipt, open the completed order in the list and tap <strong>receipt</strong> to print (standard receipt as usual).</p>',
            });
        } catch {
            Swal.close();
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not complete payment.' });
        }
    });

    // Edit items (staff allowed)
    const editModalEl = document.getElementById('editTransactionModal');
    const editModal = editModalEl ? bootstrap.Modal.getOrCreateInstance(editModalEl) : null;
    const editRemoveTbody = document.getElementById('editRemoveTbody');
    const editAddWrap = document.getElementById('editAddWrap');
    const editTxMeta = document.getElementById('editTxMeta');
    const editSaveBtn = document.getElementById('editSaveBtn');
    const addLineBtn = document.getElementById('editAddLineBtn');
    const editSummaryStatus = document.getElementById('editSummaryStatus');
    const editSummaryMeta = document.getElementById('editSummaryMeta');
    const editSummaryCount = document.getElementById('editSummaryCount');
    const editSummarySubtotal = document.getElementById('editSummarySubtotal');
    const editSummaryTotal = document.getElementById('editSummaryTotal');
    // baseline_paid is the CURRENT net paid position (cash received minus refunds),
    // used to decide whether additional payment or refund is required for this update.
    let currentEdit = { id: 0, products: [], baseline_paid: 0, status: '', payment_method: 'cash', amount_paid: 0, amount_tendered: null, change_amount: null, refunded_amount: 0, added_paid_amount: 0 };

    const getUnitPriceFor = (pid) => {
        const p = (currentEdit.products || []).find(x => Number(x.id) === Number(pid));
        return p ? Number(p.price || 0) : 0;
    };

    const recalcSummary = () => {
        const existing = Array.from(document.querySelectorAll('.edit-existing-qty'))
            .map((el) => {
                const pid = Number(el.dataset.productId || 0);
                const qty = Math.max(0, Math.floor(Number(el.value || 0)));
                const unit = Number(el.dataset.unitPrice || 0) || getUnitPriceFor(pid);
                return pid ? { pid, qty, unit } : null;
            })
            .filter(Boolean);
        const adds = Array.from(document.querySelectorAll('.edit-add-line'))
            .map((line) => {
                const pid = Number(line.querySelector('.edit-add-product')?.value || 0);
                const qty = Math.max(0, Math.floor(Number(line.querySelector('.edit-add-qty')?.value || 0)));
                const unit = getUnitPriceFor(pid);
                return pid ? { pid, qty: Math.max(1, qty || 1), unit } : null;
            })
            .filter(Boolean);
        let count = 0;
        let subtotal = 0;
        [...existing, ...adds].forEach((x) => {
            if (!x) return;
            if (x.qty <= 0) return;
            count += x.qty;
            subtotal += (x.unit * x.qty);
        });
        if (editSummaryCount) editSummaryCount.textContent = String(count);
        if (editSummarySubtotal) editSummarySubtotal.textContent = money(subtotal);
        if (editSummaryTotal) editSummaryTotal.textContent = money(subtotal);
    };

    const renderAddLine = (products, initial) => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center edit-add-line';
        row.innerHTML = `
            <div class="col-12 col-md-7">
                <select class="form-select form-select-sm edit-add-product">
                    <option value="">Select product…</option>
                    ${products.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (PHP ${money(p.price)})</option>`).join('')}
                </select>
            </div>
            <div class="col-6 col-md-3">
                <input type="number" min="1" step="1" class="form-control form-control-sm text-end edit-add-qty" value="${initial?.quantity ?? 1}">
            </div>
            <div class="col-6 col-md-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger edit-add-remove"><i class="fa fa-trash"></i></button>
            </div>
        `;
        if (initial?.product_id) {
            row.querySelector('.edit-add-product').value = String(initial.product_id);
        }
        row.querySelector('.edit-add-remove').addEventListener('click', () => {
            row.remove();
            recalcSummary();
        });
        row.querySelector('.edit-add-product')?.addEventListener('change', recalcSummary);
        row.querySelector('.edit-add-qty')?.addEventListener('input', recalcSummary);
        return row;
    };

    addLineBtn?.addEventListener('click', () => {
        if (!editAddWrap) return;
        editAddWrap.appendChild(renderAddLine(currentEdit.products || [], null));
        recalcSummary();
    });

    const setEditEnabled = (enabled) => {
        if (editSaveBtn) editSaveBtn.disabled = !enabled;
        if (addLineBtn) addLineBtn.disabled = !enabled;
        // existing qty inputs get toggled after render
        document.querySelectorAll('.edit-existing-qty').forEach((el) => {
            el.disabled = !enabled || el.dataset.isVoided === '1';
        });
        // add line inputs/selects
        document.querySelectorAll('.edit-add-product, .edit-add-qty, .edit-add-remove').forEach((el) => {
            el.disabled = !enabled;
        });
    };

    const openEdit = async (url) => {
        if (!editModal || !editRemoveTbody || !editAddWrap || !editTxMeta) return;
        editRemoveTbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">Loading…</td></tr>';
        editAddWrap.innerHTML = '';
        editTxMeta.textContent = '';
        currentEdit = { id: 0, products: [] };
        setEditEnabled(false);
        if (editSummaryStatus) editSummaryStatus.textContent = '—';
        if (editSummaryMeta) editSummaryMeta.textContent = '—';
        if (editSummaryCount) editSummaryCount.textContent = '0';
        if (editSummarySubtotal) editSummarySubtotal.textContent = '0.00';
        if (editSummaryTotal) editSummaryTotal.textContent = '0.00';
        editModal.show();
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                editRemoveTbody.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-3">${escapeHtml(body.message || 'Could not load transaction.')}</td></tr>`;
                return;
            }
            const tx = body.transaction || {};
            const items = Array.isArray(body.items) ? body.items : [];
            const products = Array.isArray(body.products) ? body.products : [];
            currentEdit = {
                id: Number(tx.id || 0),
                products,
                status: String(tx.status || ''),
                payment_method: String(tx.payment_method || 'cash') || 'cash',
                amount_paid: Number(tx.amount_paid || 0),
                amount_tendered: (tx.amount_tendered != null ? Number(tx.amount_tendered) : null),
                change_amount: (tx.change_amount != null ? Number(tx.change_amount) : null),
                refunded_amount: (tx.refunded_amount != null ? Number(tx.refunded_amount) : 0),
                added_paid_amount: (tx.added_paid_amount != null ? Number(tx.added_paid_amount) : 0),
            };
            // Net received so far = (cash: amount_paid = net to order; fallback tendered−change) + added − refunded
            const pmNow = String(currentEdit.payment_method || 'cash').toLowerCase();
            const ap0 = Number(currentEdit.amount_paid || 0);
            const baseNet = pmNow === 'cash'
                ? (ap0 > MONEY_EPS ? ap0 : Math.max(0, Number(currentEdit.amount_tendered || 0) - Number(currentEdit.change_amount || 0)))
                : Math.max(0, ap0);
            currentEdit.baseline_paid = Math.max(0, baseNet + Number(currentEdit.added_paid_amount || 0) - Number(currentEdit.refunded_amount || 0));
            const status = String(tx.status || '');
            editTxMeta.textContent = `Transaction #${tx.id} • Status: ${status || '—'} • Total: PHP ${money(tx.total_amount)} • ${tx.created_at ? new Date(String(tx.created_at).replace(' ', 'T')).toLocaleString() : ''}`;
            if (editSummaryStatus) editSummaryStatus.textContent = status || '—';
            if (editSummaryMeta) {
                const when = tx.created_at ? new Date(String(tx.created_at).replace(' ', 'T')).toLocaleString() : '';
                editSummaryMeta.textContent = when ? `Transaction #${tx.id} • ${when}` : `Transaction #${tx.id}`;
            }

            editRemoveTbody.innerHTML = items.length
                ? items.map(it => `
                    <tr>
                        <td>
                            <div class="fw-semibold">${escapeHtml(it.product_name)}${it.flavor_name ? ` - ${escapeHtml(it.flavor_name)}` : ''}</div>
                            <div class="small text-muted">#${it.product_id}</div>
                        </td>
                        <td class="text-end">
                            <input type="number" min="0" step="1"
                                class="form-control form-control-sm text-end edit-existing-qty"
                                data-item-id="${it.item_id}"
                                data-product-id="${it.product_id}"
                                data-original-qty="${Number(it.quantity || 0)}"
                                data-is-voided="${Number(it.quantity || 0) <= 0 ? '1' : '0'}"
                                data-unit-price="${Number(it.unit_price || 0)}"
                                value="${Number(it.quantity || 0)}">
                        </td>
                        <td class="text-end small text-muted">PHP ${money(it.unit_price)}</td>
                        <td class="text-end">
                            ${Number(it.quantity || 0) <= 0
                                ? `<button type="button" class="btn btn-sm btn-outline-secondary edit-void-btn" data-item-id="${it.item_id}" disabled>Voided</button>`
                                : `<button type="button" class="btn btn-sm btn-outline-danger edit-void-btn" data-item-id="${it.item_id}">Void</button>`
                            }
                        </td>
                    </tr>
                `).join('')
                : '<tr><td colspan="4" class="text-muted text-center py-3">No items found.</td></tr>';

            // start with one add line
            editAddWrap.appendChild(renderAddLine(products, null));

            // Wire void buttons (one-way)
            editRemoveTbody.querySelectorAll('.edit-void-btn').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const itemId = String(btn.dataset.itemId || '');
                    const input = editRemoveTbody.querySelector(`.edit-existing-qty[data-item-id="${itemId}"]`);
                    if (!input) return;
                    const isVoided = input.dataset.isVoided === '1';
                    if (isVoided) return;
                    input.value = '0';
                    input.dataset.isVoided = '1';
                    input.disabled = true;
                    btn.classList.remove('btn-outline-danger');
                    btn.classList.add('btn-outline-secondary');
                    btn.textContent = 'Voided';
                    btn.disabled = true;
                    recalcSummary();
                });
            });
            editRemoveTbody.querySelectorAll('.edit-existing-qty').forEach((el) => el.addEventListener('input', recalcSummary));

            // FREE payment: do not allow voiding/removing items.
            const pmLower2 = String(currentEdit.payment_method || 'cash').toLowerCase();
            if (pmLower2 === 'free') {
                editRemoveTbody.querySelectorAll('.edit-void-btn').forEach((btn) => {
                    btn.disabled = true;
                    btn.classList.remove('btn-outline-danger');
                    btn.classList.add('btn-outline-secondary');
                    if (btn.textContent.trim().toLowerCase() === 'void') btn.textContent = 'Locked';
                });
                // Prevent manual qty edits that could remove items (void).
                editRemoveTbody.querySelectorAll('.edit-existing-qty').forEach((el) => {
                    el.disabled = true;
                });
            }

            const canEdit = status === 'completed' || status === 'pending';
            setEditEnabled(canEdit);
            if (!canEdit) {
                // show message row but keep items visible
                editAddWrap.innerHTML = '';
            }
            // Ensure voided items are disabled on load.
            editRemoveTbody.querySelectorAll('.edit-existing-qty').forEach((el) => {
                if (el.dataset.isVoided === '1') el.disabled = true;
            });
            recalcSummary();
        } catch {
            editRemoveTbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-3">Could not load transaction.</td></tr>';
        }
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.js-edit-transaction');
        if (!btn) return;
        const url = btn.getAttribute('data-edit-url') || '';
        if (!url) return;
        openEdit(url);
    });

    editSaveBtn?.addEventListener('click', async () => {
        if (!currentEdit.id) return;
        const existingQtys = Array.from(document.querySelectorAll('.edit-existing-qty'))
            .map((el) => ({
                item_id: Number(el.dataset.itemId || 0),
                quantity: Number(el.value || 0),
            }))
            .filter((x) => x.item_id);
        const addLines = Array.from(document.querySelectorAll('.edit-add-line'));
        const adds = addLines.map(line => {
            const pid = Number(line.querySelector('.edit-add-product')?.value || 0);
            const qty = Number(line.querySelector('.edit-add-qty')?.value || 0);
            return pid ? { product_id: pid, quantity: Math.max(1, qty || 1) } : null;
        }).filter(Boolean);

        const hasExistingChange = existingQtys.some(x => Number.isFinite(x.quantity));
        if (!hasExistingChange && !adds.length) {
            return Swal.fire({ icon: 'warning', title: 'No changes', text: 'Adjust quantities or add replacements.' });
        }

        const isPendingEdit = String(currentEdit.status || '') === 'pending';
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Confirm update',
            text: isPendingEdit
                ? 'This will update the pending order and adjust inventory. Continue?'
                : 'This will update the completed transaction and adjust inventory. Continue?',
            showCancelButton: true,
            confirmButtonText: 'Yes, save',
        });
        if (!confirm.isConfirmed) return;

        // Ask for refund/additional payment when totals change (paid orders only).
        const newTotal = (() => {
            const existing = Array.from(document.querySelectorAll('.edit-existing-qty'))
                .map((el) => {
                    const pid = Number(el.dataset.productId || 0);
                    const qty = Math.max(0, Math.floor(Number(el.value || 0)));
                    const unit = Number(el.dataset.unitPrice || 0) || getUnitPriceFor(pid);
                    return pid ? (unit * qty) : 0;
                })
                .reduce((a, b) => a + b, 0);
            const adds = Array.from(document.querySelectorAll('.edit-add-line'))
                .map((line) => {
                    const pid = Number(line.querySelector('.edit-add-product')?.value || 0);
                    if (!pid) return 0;
                    const qty = Math.max(1, Math.floor(Number(line.querySelector('.edit-add-qty')?.value || 1)));
                    const unit = getUnitPriceFor(pid);
                    return unit * qty;
                })
                .reduce((a, b) => a + b, 0);
            return roundMoneyVal(existing + adds);
        })();

        const baselinePaid = roundMoneyVal(Number(currentEdit.baseline_paid || 0));
        const diff = roundMoneyVal(newTotal - baselinePaid);
        let refundAmount = 0;
        let additionalPaidAmount = 0;

        if (diff !== 0 && !isPendingEdit) {
            const isDecrease = diff < 0;
            const deltaAbs = Math.abs(diff);
            const title = isDecrease ? 'Refund required' : 'Additional payment required';
            const label = isDecrease ? 'Refund amount' : 'Additional paid amount';
            const confirmText = isDecrease ? 'Confirm refund' : 'Confirm payment';
            const paymentMethod = String(currentEdit.payment_method || 'cash').toLowerCase();

            const ask = await Swal.fire({
                icon: 'question',
                title,
                html: `
                    <div class="text-start">
                        <div class="mb-2 small text-muted">
                            Net paid so far: PHP ${money(baselinePaid)}<br>
                            New total: PHP ${money(newTotal)}<br>
                            Difference: PHP ${money(deltaAbs)}
                        </div>
                        <label class="form-label mb-1">${label}</label>
                        <input id="swalAdjustAmount" class="form-control" placeholder="0.00" inputmode="decimal" autocomplete="off">
                        <div class="form-text">Enter the exact amount for this adjustment.</div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: confirmText,
                didOpen: () => {
                    const el = document.getElementById('swalAdjustAmount');
                    // Default exact difference. For non-cash, still record the adjustment.
                    el.value = toMoneyInputStr(deltaAbs);
                    setTimeout(() => el?.focus(), 50);
                    // show payment method context
                    if (paymentMethod && paymentMethod !== 'cash') {
                        const ft = el?.parentElement?.querySelector('.form-text');
                        if (ft) ft.textContent = 'Non-cash payment detected: adjustment will be recorded.';
                    }
                },
                preConfirm: () => {
                    const raw = String(document.getElementById('swalAdjustAmount')?.value || '').trim();
                    const n = Number(raw);
                    if (!Number.isFinite(n) || n < 0) {
                        Swal.showValidationMessage('Enter a valid amount.');
                        return false;
                    }
                    // Keep accounting consistent: require exact difference.
                    if (Math.abs(roundMoneyVal(n) - roundMoneyVal(deltaAbs)) > MONEY_EPS) {
                        Swal.showValidationMessage(`Amount must equal PHP ${money(deltaAbs)}.`);
                        return false;
                    }
                    return n;
                },
            });
            if (!ask.isConfirmed) return;
            if (isDecrease) refundAmount = Number(ask.value || 0);
            else additionalPaidAmount = Number(ask.value || 0);
        }

        const fd = new FormData();
        if (csrf) fd.set('_token', csrf);
        existingQtys.forEach((it, idx) => {
            fd.set(`existing_items[${idx}][item_id]`, String(it.item_id));
            fd.set(`existing_items[${idx}][quantity]`, String(Math.max(0, Math.floor(it.quantity || 0))));
        });
        adds.forEach((it, idx) => {
            fd.set(`add_items[${idx}][product_id]`, String(it.product_id));
            fd.set(`add_items[${idx}][quantity]`, String(it.quantity));
        });
        if (refundAmount > 0) fd.set('refund_amount', String(refundAmount));
        if (additionalPaidAmount > 0) fd.set('additional_paid_amount', String(additionalPaidAmount));

        try {
            const res = await fetch(`<?= e(url('/tenant/transactions')) ?>/${currentEdit.id}/edit-items`, {
                method: 'POST',
                body: fd,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                return Swal.fire({ icon: 'error', title: 'Update failed', text: body.message || 'Please try again.' });
            }
            Swal.fire({ icon: 'success', title: 'Updated', text: 'Transaction updated and logged.' });
            editModal?.hide();
            // Reload DataTable
            try {
                $('#transactionsTable').DataTable().ajax.reload(null, false);
            } catch {}
        } catch {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not update transaction.' });
        }
    });
})();
</script>
