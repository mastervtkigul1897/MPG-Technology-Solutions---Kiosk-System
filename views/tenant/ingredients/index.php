<div class="card mb-3">
    <div class="card-body">
        <form id="ingredientCreateForm" method="POST" action="<?= e(route('tenant.ingredients.store')) ?>" class="row g-2 g-md-3 align-items-end" novalidate>
            <?= csrf_field() ?>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Item Name</label>
                <input class="form-control" name="name" placeholder="Item Name" required>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label mb-1">Unit</label>
                <select class="form-select" name="unit" required>
                    <?php foreach ($allowed_units as $unit): ?><option value="<?= e($unit ?? '') ?>"><?= e($unit ?? '') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label mb-1">Current Stock</label>
                <input class="form-control" type="number" step="any" inputmode="decimal" name="stock_quantity" placeholder="Stock" required>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <label class="form-label mb-1">Low Stock Threshold</label>
                <input class="form-control" type="number" step="any" inputmode="decimal" name="low_stock_threshold" placeholder="Low Threshold" required>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <label class="form-label mb-1" for="ingredientCreateBtn">Add item</label>
                <button type="submit" id="ingredientCreateBtn" class="btn btn-primary w-100 py-2"><i class="fa fa-plus me-1"></i> Add Item</button>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body table-responsive px-2 px-md-3">
        <table class="table table-striped align-middle w-100" id="ingredientsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Unit</th>
                    <th>Stock</th>
                    <th>Low Threshold</th>
                    <th>Actions</th>
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
    const createForm = document.getElementById('ingredientCreateForm');

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

    const encodeBody = (payload) => new URLSearchParams(payload).toString();

    const table = initServerDataTable('#ingredientsTable', {
        printButton: true,
        ajax: {
            url: '<?= e(route('tenant.ingredients.index')) ?>',
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

    let createSubmitting = false;
    const submitCreateIngredient = async (e) => {
        e.preventDefault();
        if (!createForm || createSubmitting) return;
        if (!createForm.checkValidity()) {
            createForm.reportValidity();
            return;
        }
        createSubmitting = true;
        const btn = document.getElementById('ingredientCreateBtn');
        if (btn) btn.disabled = true;
        const formData = new FormData(createForm);
        try {
            const res = await fetch(createForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: formData,
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) return showValidationErrors(payload);
            createForm.reset();
            table.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Added', text: payload.message || 'Item added successfully.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to submit form. Please try again.', confirmButtonColor: '#dc3545' });
        } finally {
            createSubmitting = false;
            if (btn) btn.disabled = false;
        }
    };
    createForm?.addEventListener('submit', submitCreateIngredient);

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

    const submitUpdate = async (tr, id) => {
        const payload = {
            _method: 'PUT',
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

    const deleteRow = async (id) => {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Delete item?',
            text: 'This action cannot be undone.',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
        });
        if (!confirm.isConfirmed) return;

        try {
            const res = await fetch(`<?= e(url('tenant/ingredients')) ?>/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: encodeBody({ _method: 'DELETE' }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) return showValidationErrors(body);
            table.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Deleted', text: body.message || 'Item deleted successfully.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Delete failed. Please try again.', confirmButtonColor: '#dc3545' });
        }
    };

    const $ingTable = $('#ingredientsTable');
    $ingTable.on('click', '.js-edit', function (ev) {
        ev.preventDefault();
        const tr = this.closest('tr');
        if (!tr) return;
        if (tr.dataset.editing === '1') return;
        const rowData = table.row(tr).data();
        if (!rowData) return;
        beginEditRow(tr, rowData);
    });

    $ingTable.on('click', '.js-cancel', function (ev) {
        ev.preventDefault();
        table.ajax.reload(null, false);
    });

    $ingTable.on('click', '.js-save', function (ev) {
        ev.preventDefault();
        const tr = this.closest('tr');
        const id = this.dataset.id;
        if (!tr || !id) return;
        submitUpdate(tr, id);
    });

    $ingTable.on('click', '.js-delete', function (ev) {
        ev.preventDefault();
        const id = this.dataset.id;
        if (!id) return;
        deleteRow(id);
    });
})();
</script>
