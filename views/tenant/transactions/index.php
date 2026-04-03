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
                    <th>Status</th>
                    <th>Details</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
<div class="modal fade" id="transactionReceiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="transactionReceiptModalTitle">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionReceiptModalTitle">Receipt</h5>
            </div>
            <div class="modal-body">
                <div id="transactionReceiptPrintArea" class="receipt-print-area small"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="transactionReceiptPrintBtn"><i class="fa-solid fa-print me-1"></i>Print</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<style>
.receipt-print-area { display: flex; justify-content: center; }
.receipt-paper {
    width: 100%;
    max-width: 340px;
    margin: 0 auto;
    padding: .55rem .65rem;
    border: 1px dashed #adb5bd;
    border-radius: .35rem;
    background: #fff;
    font-family: "Courier New", Courier, monospace;
    font-size: 13px;
    line-height: 1.45;
    color: #111;
}
.receipt-center { text-align: center; }
.receipt-bold { font-weight: 700; }
.receipt-muted { color: #4b5563; }
.receipt-dash { border-top: 1px dashed #666; margin: .35rem 0; }
.receipt-row { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
.receipt-row .left { min-width: 0; flex: 1 1 auto; }
.receipt-row .right { flex: 0 0 auto; text-align: right; white-space: nowrap; }

@media print {
    body * {
        visibility: hidden !important;
    }
    #transactionReceiptModal,
    #transactionReceiptModal * {
        visibility: visible !important;
    }
    #transactionReceiptModal {
        position: fixed !important;
        inset: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        z-index: 9999 !important;
    }
    #transactionReceiptModal .modal-dialog {
        max-width: 360px !important;
        margin: 0 auto !important;
        transform: none !important;
    }
    #transactionReceiptModal .modal-content {
        border: 0 !important;
        box-shadow: none !important;
    }
    #transactionReceiptModal .modal-header,
    #transactionReceiptModal .modal-footer {
        display: none !important;
    }
    #transactionReceiptModal .modal-body {
        padding: 0 !important;
        overflow: visible !important;
    }
    #transactionReceiptPrintArea {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    #transactionReceiptPrintArea .receipt-paper {
        border: 0 !important;
        border-radius: 0 !important;
        max-width: 320px !important;
        padding: .25rem .35rem !important;
        font-size: 13px !important;
        line-height: 1.45 !important;
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
    const money = (n) => Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
        (r.items || []).forEach((it) => {
            lines.push(`<div class="receipt-row"><span class="left">${escapeHtml(it.name)}</span><span class="right">${money(it.line_total)}</span></div>`);
            lines.push(`<div class="receipt-row receipt-muted"><span class="left">${Number(it.quantity || 0)} x ${money(it.unit_price)}</span><span class="right"></span></div>`);
        });
        lines.push('<div class="receipt-dash"></div>');
        lines.push(`<div class="receipt-row receipt-bold"><span class="left">TOTAL</span><span class="right">${money(r.grand_total)}</span></div>`);
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
        if (footerNote && footerNote.toLowerCase().trim() !== defaultThanks) {
            lines.push(`<div class="receipt-center receipt-muted">${escapeHtml(footerNote).replace(/\\n/g, '<br>')}</div>`);
        }
        lines.push('</div>');
        return lines.join('');
    };

    const receiptModalEl = document.getElementById('transactionReceiptModal');
    const receiptPrintAreaEl = document.getElementById('transactionReceiptPrintArea');
    const receiptModal = receiptModalEl ? bootstrap.Modal.getOrCreateInstance(receiptModalEl) : null;
    document.getElementById('transactionReceiptPrintBtn')?.addEventListener('click', () => window.print());

    initServerDataTable('#transactionsTable', {
        ajax: {
            url: '<?= e(route('tenant.transactions.index')) ?>',
            data: { datatable: 1 }
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 40 },
            { targets: 4, responsivePriority: 35 },
            { targets: 5, responsivePriority: 3 },
            { targets: 6, responsivePriority: 45 },
            { targets: 7, orderable: false, searchable: false, responsivePriority: 55 },
            { targets: 8, orderable: false, searchable: false, responsivePriority: 5 },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'date' },
            { data: 'cashier' },
            { data: 'qty' },
            { data: 'total' },
            { data: 'status' },
            { data: 'details' },
            { data: 'action' },
        ],
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-reprint-receipt');
        if (!btn) return;
        const url = btn.getAttribute('data-receipt-url') || '';
        if (!url || !receiptPrintAreaEl || !receiptModal) return;
        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.success) {
                Swal.fire({ icon: 'error', title: 'Receipt unavailable', text: body.message || 'Could not load receipt.' });
                return;
            }
            receiptPrintAreaEl.innerHTML = buildReceiptHtml(body.receipt || {});
            receiptModal.show();
        } catch {
            Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not load receipt. Please try again.' });
        }
    });
})();
</script>
