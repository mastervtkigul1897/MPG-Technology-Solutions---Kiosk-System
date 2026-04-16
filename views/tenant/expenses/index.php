<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="<?= e(route('tenant.expenses.store')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md-8">
                <label class="form-label mb-1" for="expense_description">Description</label>
                <input class="form-control" id="expense_description" name="description" required maxlength="500" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1" for="expense_amount">Amount</label>
                <input class="form-control" id="expense_amount" type="number" step="any" min="0" inputmode="decimal" name="amount" required>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label mb-1" for="expense_add_btn">Add</label>
                <button type="submit" id="expense_add_btn" class="btn btn-primary w-100 py-2" title="Add expense" aria-label="Add expense"><i class="fa fa-plus"></i></button>
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped w-100" id="expensesTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
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
        }
    };

    const table = initServerDataTable('#expensesTable', {
        printButton: true,
        order: [[4, 'desc']],
        ajax: {
            url: '<?= e(route('tenant.expenses.index')) ?>',
            data: { datatable: 1 }
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 3 },
            { targets: 4, responsivePriority: 50 },
            { targets: 5, orderable: false, searchable: false, responsivePriority: 4 },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'description' },
            { data: 'amount' },
            { data: 'created_at' },
            { data: 'actions' },
        ],
    });

    const deleteExpense = async (id) => {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Delete expense?',
            text: 'This action cannot be undone.',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete',
        });
        if (!confirm.isConfirmed) return;

        try {
            const res = await fetch(`<?= e(url('tenant/expenses')) ?>/${id}`, {
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
            Swal.fire({ icon: 'success', title: 'Deleted', text: body.message || 'Expense deleted successfully.', confirmButtonColor: '#198754' });
        } catch {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Delete failed. Please try again.', confirmButtonColor: '#dc3545' });
        }
    };

    $('#expensesTable').on('click', '.js-delete-expense', function (ev) {
        ev.preventDefault();
        const id = this.dataset.id;
        if (!id) return;
        deleteExpense(id);
    });
})();
</script>
