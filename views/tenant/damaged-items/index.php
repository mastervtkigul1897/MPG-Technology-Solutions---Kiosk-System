<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="card mb-3">
    <div class="card-body">
        <form id="damagedCreateForm" method="POST" action="<?= e(route('tenant.damaged-items.store')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md-6">
                <label class="form-label mb-1" for="damaged_ingredient_id">Inventory item</label>
                <select class="form-select" id="damaged_ingredient_id" name="ingredient_id" required>
                    <option value="">Select item</option>
                    <?php foreach (($ingredients ?? []) as $ing): ?>
                        <option value="<?= (int) $ing['id'] ?>"><?= e((string) ($ing['name'] ?? '')) ?> (<?= e((string) ($ing['unit'] ?? '')) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1" for="damaged_quantity">Quantity</label>
                <input class="form-control" id="damaged_quantity" type="number" step="any" min="0" inputmode="decimal" name="quantity" required>
            </div>
            <div class="col-12 col-md-9">
                <label class="form-label mb-1" for="damaged_note">Notes (optional)</label>
                <input class="form-control" id="damaged_note" type="text" name="note" maxlength="255" placeholder="e.g. expired, spilled, broken seal">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1" for="damaged_add_btn">Add</label>
                <button type="submit" id="damaged_add_btn" class="btn btn-primary w-100 py-2">
                    <i class="fa fa-plus me-1"></i> Log damage
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped w-100" id="damagedItemsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Notes</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="editDamagedModal" tabindex="-1" aria-labelledby="editDamagedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="editDamagedModalLabel">Edit damage entry</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="damagedEditForm" method="POST" action="">
                <?= csrf_field() ?>
                <?= method_field('PUT') ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label mb-1" for="edit_damaged_ingredient_id">Inventory item</label>
                        <select class="form-select" id="edit_damaged_ingredient_id" name="ingredient_id" required>
                            <option value="">Select item</option>
                            <?php foreach (($ingredients ?? []) as $ing): ?>
                                <option value="<?= (int) $ing['id'] ?>"><?= e((string) ($ing['name'] ?? '')) ?> (<?= e((string) ($ing['unit'] ?? '')) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label mb-1" for="edit_damaged_quantity">Quantity</label>
                        <input class="form-control" id="edit_damaged_quantity" type="number" step="any" min="0" inputmode="decimal" name="quantity" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label mb-1" for="edit_damaged_note">Notes (optional)</label>
                        <input class="form-control" id="edit_damaged_note" type="text" name="note" maxlength="255" placeholder="e.g. expired, spilled, broken seal">
                    </div>
                    <div class="form-text">When you save, stock will be adjusted automatically based on the difference.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to process request.', confirmButtonColor: '#dc3545' });
        }
    };

    const table = initServerDataTable('#damagedItemsTable', {
        printButton: true,
        ajax: {
            url: '<?= e(route('tenant.damaged-items.index')) ?>',
            data: { datatable: 1 },
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 3 },
            { targets: 4, responsivePriority: 4 },
            { targets: 5, responsivePriority: 50 },
            { targets: 6, orderable: false, searchable: false, responsivePriority: 5 },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'ingredient_name' },
            {
                data: 'quantity',
                render: function (data, type, row) {
                    // quantity already formatted to 2 decimals; unit is a separate field.
                    return `${data} ${row.unit || ''}`.trim();
                }
            },
            {
                data: 'note',
                render: function (data) {
                    return data || '';
                }
            },
            { data: 'created_at' },
            { data: 'actions' },
        ],
    });

    const createForm = document.getElementById('damagedCreateForm');
    let createSubmitting = false;
    const submitCreate = async (e) => {
        e.preventDefault();
        if (!createForm || createSubmitting) return;
        if (!createForm.checkValidity()) {
            createForm.reportValidity();
            return;
        }
        createSubmitting = true;
        const btn = document.getElementById('damaged_add_btn');
        if (btn) btn.disabled = true;

        try {
            const formData = new FormData(createForm);
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
            Swal.fire({ icon: 'success', title: 'Saved', text: payload.message || 'Damage logged successfully.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Unable to submit. Please try again.', confirmButtonColor: '#dc3545' });
        } finally {
            createSubmitting = false;
            if (btn) btn.disabled = false;
        }
    };
    createForm?.addEventListener('submit', submitCreate);

    // ----- Edit modal -----
    const editModalEl = document.getElementById('editDamagedModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('damagedEditForm');
    const editIngredientSel = document.getElementById('edit_damaged_ingredient_id');
    const editQtyInput = document.getElementById('edit_damaged_quantity');
    const editNoteInput = document.getElementById('edit_damaged_note');

    const baseUrl = '<?= e(url('/tenant/damaged-items')) ?>';

    let editSubmitting = false;
    editForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (editSubmitting) return;
        if (!editForm) return;

        if (!editForm.checkValidity()) {
            editForm.reportValidity();
            return;
        }

        editSubmitting = true;
        try {
            const formData = new FormData(editForm);
            const res = await fetch(editForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: formData,
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) return showValidationErrors(payload);
            editModal?.hide();
            table.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Updated', text: payload.message || 'Damage updated successfully.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Update failed. Please try again.', confirmButtonColor: '#dc3545' });
        } finally {
            editSubmitting = false;
        }
    });

    $('#damagedItemsTable tbody').on('click', '.js-edit-damaged', function () {
        const tr = this.closest('tr');
        if (!tr || !table) return;
        const rowData = table.row(tr).data();
        if (!rowData) return;

        if (editForm) {
            editForm.action = `${baseUrl}/${rowData.id}`;
        }
        if (editIngredientSel) editIngredientSel.value = rowData.ingredient_id;
        if (editQtyInput) editQtyInput.value = rowData.quantity_value;
        if (editNoteInput) editNoteInput.value = rowData.note || '';

        editModal?.show();
    });

    // ----- Delete -----
    const deleteDamage = async (id) => {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Delete damage entry?',
            text: 'Stock will be restored. This cannot be undone.',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
        });
        if (!confirm.isConfirmed) return;

        try {
            const res = await fetch(`${baseUrl}/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: encodeBody({ _method: 'DELETE', _token: csrf }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) return showValidationErrors(body);
            table.ajax.reload(null, false);
            Swal.fire({ icon: 'success', title: 'Deleted', text: body.message || 'Damage entry deleted.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Delete failed. Please try again.', confirmButtonColor: '#dc3545' });
        }
    };

    $('#damagedItemsTable tbody').on('click', '.js-delete-damaged', function () {
        const id = this.dataset.id;
        if (!id) return;
        deleteDamage(id);
    });
})();
</script>

