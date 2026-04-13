<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
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
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <div class="small text-muted">Date:</div>
        <input type="date" class="form-control form-control-sm" id="txDateFilter" style="min-width: 180px;">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="txDateTodayBtn">Today</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="txDateClearBtn">All dates</button>
    </div>
</div>

<div class="card">
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

<div class="modal fade" id="transactionReceiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="transactionReceiptModalTitle">
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
                    <button type="button" class="btn btn-primary text-white w-100 mpg-receipt-action-btn" id="transactionReceiptPrintBtn"><i class="fa-solid fa-print me-1"></i>Print</button>
                    <button type="button" class="btn btn-success text-white <?= empty($thermal_receipt_network_enabled) ? 'd-none' : '' ?> w-100 mpg-receipt-action-btn" id="transactionReceiptPrintWifiBtn" title="Server sends raw data to printer on LAN (phone/tablet/APK when host is configured)"><i class="fa-solid fa-wifi me-1"></i>Wi‑Fi / LAN</button>
                    <button type="button" class="btn btn-primary text-white mpg-btn-bluetooth-thermal w-100 mpg-receipt-action-btn" id="transactionReceiptPrintBleBtn" title="Bluetooth print"><i class="fa-brands fa-bluetooth-b me-1"></i>Bluetooth print</button>
                    <button type="button" class="btn btn-secondary text-white w-100 mpg-receipt-action-btn" data-bs-dismiss="modal">Close</button>
                </div>
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
    max-width: 55mm;
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

@media print {
    /* Thermal receipt: 55mm roll, black & white, avoid extra blank page */
    @page {
        size: 55mm auto;
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
        width: 55mm !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        border: 0 !important;
        z-index: 9999 !important;
        filter: none !important;
    }
    #transactionReceiptModal .modal-dialog {
        max-width: 55mm !important;
        width: 55mm !important;
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
        const footerNote = String(r.footer_note || '').trim();
        const lines = [];
        lines.push('<div class="receipt-paper">');
        lines.push(`<div class="receipt-center receipt-bold">${escapeHtml(displayName)}</div>`);
        if (businessStyle) lines.push(`<div class="receipt-center receipt-muted">${escapeHtml(businessStyle)}</div>`);
        if (taxId) lines.push(`<div class="receipt-center">TIN: ${escapeHtml(taxId)}</div>`);
        lines.push('<div class="receipt-dash"></div>');
        const contactBits = [];
        if (c.phone) contactBits.push(`<div>Phone: ${escapeHtml(c.phone)}</div>`);
        if (c.address) contactBits.push(`<div>${escapeHtml(c.address).replace(/\\n/g, '<br>')}</div>`);
        if (c.email) contactBits.push(`<div>Email: ${escapeHtml(c.email)}</div>`);
        lines.push(contactBits.length ? `<div>${contactBits.join('')}</div>` : '<div class="receipt-muted">No store contact on file.</div>');
        lines.push('<div class="receipt-dash"></div>');
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
            lines.push(`<div class="receipt-item-name">${escapeHtml(it.name)}</div>`);
            lines.push(`<div class="receipt-row receipt-item-price-line"><span class="left">${money(unit)} × ${formatQty(it.quantity)}</span><span class="right">${money(it.line_total)}</span></div>`);
        });
        lines.push('<div class="receipt-dash"></div>');
        lines.push(`<div class="receipt-row receipt-bold"><span class="left">TOTAL</span><span class="right">${money(r.grand_total)}</span></div>`);
        // PH VAT (12%) breakdown (VAT-inclusive total): VAT = Total * 12/112; VATable Sales = Total - VAT
        const totalForVat = Number(r.grand_total || 0);
        const vatAmount = totalForVat > 0 ? (totalForVat * (12 / 112)) : 0;
        const vatableSales = Math.max(0, totalForVat - vatAmount);
        lines.push(`<div class="receipt-row"><span class="left">VATABLE SALES</span><span class="right">${money(vatableSales)}</span></div>`);
        lines.push(`<div class="receipt-row"><span class="left">VAT (12%)</span><span class="right">${money(vatAmount)}</span></div>`);
        const tendered = r.amount_tendered != null ? Number(r.amount_tendered) : null;
        const ch0 = r.change_amount != null ? Number(r.change_amount) : 0;
        const pm = String(r.payment_method || '').trim().toLowerCase();
        const pmLabel = pm ? pm.toUpperCase().replace(/_/g, ' ') : '';
        if (pmLabel) lines.push(`<div class="receipt-row"><span class="left">PAYMENT</span><span class="right">${escapeHtml(pmLabel)}</span></div>`);
        const refunded = r.refunded_amount != null ? Number(r.refunded_amount) : 0;
        const added = r.added_paid_amount != null ? Number(r.added_paid_amount) : 0;
        // Base paid (first payment, net to order): cash prefers amount_paid (364) so tendered−change stays correct after edits zero out change.
        const ap = r.amount_paid != null ? Number(r.amount_paid) : 0;
        const basePaid = pm === 'cash'
            ? (ap > MONEY_EPS ? ap : (tendered != null ? Math.max(0, tendered - ch0) : null))
            : (r.amount_paid != null ? Number(r.amount_paid) : (tendered != null ? tendered : null));
        // Apply refunds FIFO: refund reduces base first, then additional.
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
        // After any edit adjustment (refund/additional), treat this receipt as final settlement.
        // Change from the original payment is no longer meaningful.
        const hasAdjust = (Number.isFinite(refunded) && refunded > 0) || (Number.isFinite(added) && added > 0);
        const change = hasAdjust ? 0 : (r.change_amount != null ? Number(r.change_amount) : null);
        if (change != null && Number.isFinite(change)) lines.push(`<div class="receipt-row"><span class="left">CHANGE</span><span class="right">${money(change)}</span></div>`);
        lines.push('<div class="receipt-dash"></div>');
        const tid = r.transaction_id != null ? `#${r.transaction_id}` : '';
        let when = '';
        if (r.created_at) {
            const d = new Date(String(r.created_at).replace(' ', 'T'));
            when = !Number.isNaN(d.getTime()) ? d.toLocaleString() : escapeHtml(r.created_at);
        }
        const meta = `${when || ''}${tid ? ` ${tid}` : ''}`.trim();
        if (meta) lines.push(`<div class="receipt-center receipt-muted">${meta}</div>`);
        const defaultThanks = 'thank you for your purchase!';
        lines.push('<div class="receipt-center">Thank you for your purchase!</div>');
        lines.push('<div class="receipt-bottom-spacer" aria-hidden="true"></div>');
        if (footerNote && footerNote.toLowerCase().trim() !== defaultThanks) {
            lines.push(`<div class="receipt-center receipt-muted">${escapeHtml(footerNote).replace(/\\n/g, '<br>')}</div>`);
        }
        lines.push('</div>');
        return lines.join('');
    };

    const receiptModalEl = document.getElementById('transactionReceiptModal');
    const receiptPrintAreaEl = document.getElementById('transactionReceiptPrintArea');
    const receiptModal = receiptModalEl ? bootstrap.Modal.getOrCreateInstance(receiptModalEl) : null;

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
            Swal.fire({ title: 'Preparing data…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const bytes = await fetchEscposBytes(r);
            Swal.close();
            if (typeof window.mpgWriteEscposBluetooth !== 'function') {
                throw new Error('Bluetooth print module not loaded. Refresh the page.');
            }
            await window.mpgWriteEscposBluetooth(bytes);
            Swal.fire({ icon: 'success', title: 'Sent to Bluetooth printer', timer: 1800, showConfirmButton: false });
        } catch (err) {
            Swal.close();
            if (err?.name === 'NotFoundError' || err?.name === 'SecurityError') return;
            Swal.fire({ icon: 'error', title: 'Bluetooth printing failed', text: String(err?.message || err) });
        }
    });

    const statusFilterEl = document.getElementById('txStatusFilter');
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
            receiptPrintAreaEl.innerHTML = buildReceiptHtml(body.receipt || {});
            receiptModal.show();
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
            title: `Pay Pending #${id}`,
            html: `
                <div class="text-start">
                    <label class="form-label mb-1 text-muted small" for="swalCreditorName">Name of Creditor</label>
                    <input id="swalCreditorName" type="text" class="form-control form-control-sm mb-2 text-muted bg-light" value="${creditorDisplay}" readonly disabled tabindex="-1" aria-readonly="true">
                    <label class="form-label mb-1 text-muted small" for="swalCreditorContact">Contact number <span class="fw-normal">(optional)</span></label>
                    <input id="swalCreditorContact" type="text" class="form-control form-control-sm mb-3 text-muted bg-light" value="${contactDisplay}" readonly disabled tabindex="-1" aria-readonly="true">
                    <label class="form-label mb-1">Mode of payment</label>
                    <select id="swalPayPaymentMethod" class="form-select mb-2">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="gcash">GCash</option>
                        <option value="paymaya">PayMaya</option>
                        <option value="online_banking">Online Banking</option>
                    </select>
                    <label class="form-label mb-1">Amount received</label>
                    <input id="swalPayAmountReceived" class="form-control" placeholder="0.00" inputmode="decimal" autocomplete="off">
                    <div class="form-text">For non-cash payments, this will be set to exact total.</div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirm payment',
            didOpen: () => {
                const methodEl = document.getElementById('swalPayPaymentMethod');
                const amtEl = document.getElementById('swalPayAmountReceived');
                const total = pendingTotal;
                const sync = () => {
                    const method = String(methodEl?.value || 'cash');
                    if (method !== 'cash') {
                        amtEl.value = toMoneyInputStr(total);
                        amtEl.disabled = true;
                    } else {
                        amtEl.disabled = false;
                        if (!amtEl.value) amtEl.value = toMoneyInputStr(total);
                    }
                };
                methodEl?.addEventListener('change', sync);
                sync();
                setTimeout(() => amtEl?.focus(), 50);
            },
            preConfirm: () => {
                const method = String(document.getElementById('swalPayPaymentMethod')?.value || 'cash');
                const total = pendingTotal;
                const raw = String(document.getElementById('swalPayAmountReceived')?.value || '').trim();
                const received = Number(raw);
                const finalReceived = method === 'cash' ? received : total;
                if (!Number.isFinite(finalReceived) || finalReceived < 0) {
                    Swal.showValidationMessage('Enter a valid amount received.');
                    return false;
                }
                if (method === 'cash' && finalReceived < total) {
                    Swal.showValidationMessage('Amount received is less than total.');
                    return false;
                }
                return { method, received: finalReceived };
            },
        });
        if (!ask.isConfirmed) return;
        const paymentMethod = ask.value.method;
        const tendered = Number(ask.value.received || 0);

        const fd = new FormData();
        if (csrf) fd.set('_token', csrf);
        fd.set('amount_tendered', String(tendered));
        fd.set('payment_method', String(paymentMethod));

        try {
            Swal.fire({
                title: 'Preparing receipt…',
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
            lastTxReceiptObject = body.receipt || null;
            receiptPrintAreaEl.innerHTML = buildReceiptHtml(body.receipt || {});
            receiptModal?.show();
            try { $('#transactionsTable').DataTable().ajax.reload(null, false); } catch {}
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
                            <div class="fw-semibold">${escapeHtml(it.product_name)}</div>
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
