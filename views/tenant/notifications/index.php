<?php $branchExpiredNotice = $branchExpiredNotice ?? null; ?>
<?php if (is_array($branchExpiredNotice)): ?>
    <div class="alert alert-warning border-warning-subtle mb-3" role="alert">
        <div class="fw-semibold mb-1">Branch subscription expired</div>
        <div>
            Branch <strong><?= e((string) ($branchExpiredNotice['branch_name'] ?? '')) ?></strong> is expired.
            Please renew to reactivate this branch.
        </div>
    </div>
<?php endif; ?>
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped align-middle w-100" id="notificationsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Unit</th>
                    <th>Stock</th>
                    <th>Low Threshold</th>
                    <th>Restock/Edit</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const allowedUnits = <?= json_embed($allowed_units) ?>;
    const DECIMAL_SCALE = 16;
    const toDecInputStr = (v) => {
        const x = Number(v);
        if (!Number.isFinite(x)) return '0';
        const f = 10 ** DECIMAL_SCALE;
        let s = (Math.round(x * f) / f).toFixed(DECIMAL_SCALE);
        if (s.includes('.')) s = s.replace(/\.?0+$/, '') || '0';
        return s === '-0' ? '0' : s;
    };
    const encodeBody = (payload) => new URLSearchParams(payload).toString();

    const showValidationErrors = (payload) => {
        const list = Object.values(payload?.errors || {}).flat();
        if (list.length) {
            Swal.fire({
                icon: 'error',
                title: 'Validation error',
                html: `<ul style="text-align:left;padding-left:1rem;">${list.map((e) => `<li>${e}</li>`).join('')}</ul>`,
                confirmButtonColor: '#dc3545',
            });
            return;
        }
        if (payload?.message) {
            Swal.fire({ icon: 'error', title: 'Error', text: payload.message, confirmButtonColor: '#dc3545' });
        }
    };

    const table = initServerDataTable('#notificationsTable', {
        printButton: true,
        ajax: {
            url: '<?= e(route('tenant.notifications.index')) ?>',
            data: { datatable: 1 }
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 60 },
            { targets: 4, responsivePriority: 3 },
            { targets: 5, responsivePriority: 60 },
            { targets: 6, orderable: false, searchable: false, responsivePriority: 4 },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'name' },
            { data: 'unit' },
            { data: 'stock_quantity' },
            { data: 'low_stock_threshold' },
            { data: 'actions' },
        ],
    });

    const renderUnitSelect = (selected) => `
        <select class="form-select form-select-sm js-edit-unit">
            ${allowedUnits.map((u) => `<option value="${u}" ${u === selected ? 'selected' : ''}>${u}</option>`).join('')}
        </select>
    `;

    const beginEditRow = (tr, rowData) => {
        tr.classList.add('table-warning');
        tr.dataset.editing = '1';
        const cells = tr.children;
        cells[2].innerHTML = `<input class="form-control form-control-sm js-edit-name" value="${rowData.name}">`;
        cells[3].innerHTML = renderUnitSelect(rowData.unit);
        cells[4].innerHTML = `<input class="form-control form-control-sm js-edit-stock" type="number" step="any" value="${toDecInputStr(rowData.stock_quantity)}">`;
        cells[5].innerHTML = `<input class="form-control form-control-sm js-edit-threshold" type="number" step="any" value="${toDecInputStr(rowData.low_stock_threshold)}">`;
        cells[6].innerHTML = `
            <div class="d-flex gap-1 flex-wrap">
                <button type="button" class="btn btn-sm btn-success js-save" data-id="${rowData.id}" title="Save"><i class="fa fa-check"></i></button>
                <button type="button" class="btn btn-sm btn-secondary js-cancel" data-id="${rowData.id}" title="Cancel"><i class="fa fa-xmark"></i></button>
            </div>
        `;
    };

    const submitUpdate = async (tr, rowData, id) => {
        const payload = {
            _method: 'PUT',
            _source: 'notifications',
            name: tr.querySelector('.js-edit-name')?.value?.trim() || '',
            unit: tr.querySelector('.js-edit-unit')?.value || '',
            stock_quantity: tr.querySelector('.js-edit-stock')?.value || '',
            low_stock_threshold: tr.querySelector('.js-edit-threshold')?.value || '',
        };
        try {
            const res = await fetch(`<?= e(url('tenant/ingredients')) ?>/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: encodeBody(payload),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) return showValidationErrors(body);
            table.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Updated', text: body.message || 'Item updated successfully.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Update failed. Please try again.', confirmButtonColor: '#dc3545' });
        }
    };

    const $notifTable = $('#notificationsTable');
    $notifTable.on('click', '.js-edit', function (ev) {
        ev.preventDefault();
        const tr = this.closest('tr');
        if (!tr || tr.dataset.editing === '1') return;
        const rowData = table.row(tr).data();
        if (!rowData) return;
        beginEditRow(tr, rowData);
    });

    $notifTable.on('click', '.js-cancel', function (ev) {
        ev.preventDefault();
        table.ajax.reload(null, false);
    });

    $notifTable.on('click', '.js-save', function (ev) {
        ev.preventDefault();
        const tr = this.closest('tr');
        const id = this.dataset.id;
        if (!tr || !id) return;
        const rowData = table.row(tr).data();
        if (!rowData) return;
        submitUpdate(tr, rowData, id);
    });
})();
</script>
