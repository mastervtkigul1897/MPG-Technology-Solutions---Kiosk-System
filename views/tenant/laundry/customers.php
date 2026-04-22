<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="<?= e(route('tenant.customers.store')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label mb-1">Customer name</label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Contact (optional)</label>
                <input class="form-control" name="contact">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Email (optional)</label>
                <input type="email" class="form-control" name="email">
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1">Birthday</label>
                <input type="date" class="form-control" name="birthday">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Save customer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Birthday</th>
                <th class="text-end">Rewards count</th>
                <th class="text-end">Visit frequency</th>
                <th class="text-end">Total spending</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (($customers ?? []) as $customer): ?>
                <?php $cid = (int) ($customer['id'] ?? 0); ?>
                <tr>
                    <td><?= e((string) ($customer['name'] ?? '')) ?></td>
                    <td><?= e((string) ($customer['contact'] ?? '-')) ?></td>
                    <td><?= e((string) ($customer['email'] ?? '-')) ?></td>
                    <td><?= e((string) ($customer['birthday'] ?? '-')) ?></td>
                    <td class="text-end"><?= e(number_format((float) ($customer['rewards_balance'] ?? 0), 2)) ?></td>
                    <td class="text-end"><?= (int) ($customer['visit_count'] ?? 0) ?></td>
                    <td class="text-end"><?= e(format_money((float) ($customer['total_spent'] ?? 0))) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary js-edit-customer"
                                data-id="<?= $cid ?>"
                                data-name="<?= e((string) ($customer['name'] ?? '')) ?>"
                                data-contact="<?= e((string) ($customer['contact'] ?? '')) ?>"
                                data-email="<?= e((string) ($customer['email'] ?? '')) ?>"
                                data-birthday="<?= e((string) ($customer['birthday'] ?? '')) ?>"
                                title="Edit customer"
                            >
                                <i class="fa fa-pen"></i>
                            </button>
                            <form method="POST" action="<?= e(route('tenant.customers.destroy', ['id' => $cid])) ?>" onsubmit="return confirm('Delete this customer?');">
                                <?= csrf_field() ?>
                                <?= method_field('DELETE') ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete customer">
                                <i class="fa fa-trash"></i>
                            </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="customerEditModal" tabindex="-1" aria-labelledby="customerEditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="customerEditForm" action="">
                <?= csrf_field() ?>
                <?= method_field('PUT') ?>
                <div class="modal-header">
                    <h6 class="modal-title" id="customerEditModalLabel">Edit customer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label mb-1" for="customer_edit_name">Customer name</label>
                        <input class="form-control" id="customer_edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1" for="customer_edit_contact">Contact</label>
                        <input class="form-control" id="customer_edit_contact" name="contact">
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1" for="customer_edit_email">Email</label>
                        <input class="form-control" id="customer_edit_email" name="email" type="email">
                    </div>
                    <div>
                        <label class="form-label mb-1" for="customer_edit_birthday">Birthday</label>
                        <input class="form-control" id="customer_edit_birthday" name="birthday" type="date">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(() => {
    const editModalEl = document.getElementById('customerEditModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
    const editForm = document.getElementById('customerEditForm');
    const baseUrl = '<?= e(url('/tenant/customers')) ?>';
    const editName = document.getElementById('customer_edit_name');
    const editContact = document.getElementById('customer_edit_contact');
    const editEmail = document.getElementById('customer_edit_email');
    const editBirthday = document.getElementById('customer_edit_birthday');

    document.querySelectorAll('.js-edit-customer').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            if (!id || !editForm) return;
            editForm.action = `${baseUrl}/${id}`;
            if (editName) editName.value = btn.getAttribute('data-name') || '';
            if (editContact) editContact.value = btn.getAttribute('data-contact') || '';
            if (editEmail) editEmail.value = btn.getAttribute('data-email') || '';
            if (editBirthday) editBirthday.value = btn.getAttribute('data-birthday') || '';
            editModal?.show();
        });
    });
})();
</script>
