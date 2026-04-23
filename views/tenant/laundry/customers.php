<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<?php if (! empty($reward_system_active)): ?>
<div class="alert alert-light border small mb-3 mb-md-0">
    Reward load on each customer increases only from <strong>paid</strong> sales whose order type has <strong>Include to Reward System</strong> turned on (see <a href="<?= e(route('tenant.laundry-order-pricing.index')) ?>">Order Type Pricing</a>). Turn off <strong>Activate Reward System</strong> on the Rewards page to stop counting entirely.
</div>
<?php endif; ?>
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
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-warning js-adjust-rewards"
                                data-id="<?= $cid ?>"
                                data-name="<?= e((string) ($customer['name'] ?? '')) ?>"
                                data-balance="<?= e(number_format((float) ($customer['rewards_balance'] ?? 0), 2, '.', '')) ?>"
                                title="Adjust reward count (+/-)"
                            >
                                <span class="fw-bold">+/-</span>
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
<div class="modal fade" id="customerRewardsAdjustModal" tabindex="-1" aria-labelledby="customerRewardsAdjustModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="customerRewardsAdjustForm" action="">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h6 class="modal-title" id="customerRewardsAdjustModalLabel">Adjust customer reward count</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Customer: <strong id="customer_rewards_name">-</strong><br>
                        Current count: <strong id="customer_rewards_balance">0.00</strong>
                    </p>
                    <div class="mb-2">
                        <label class="form-label mb-1 d-block">Adjustment type</label>
                        <div class="btn-group" role="group" aria-label="Adjustment type">
                            <input type="radio" class="btn-check" name="adjust_type" id="customer_rewards_add" value="add" autocomplete="off" checked>
                            <label class="btn btn-outline-success" for="customer_rewards_add">Add</label>
                            <input type="radio" class="btn-check" name="adjust_type" id="customer_rewards_deduct" value="deduct" autocomplete="off">
                            <label class="btn btn-outline-danger" for="customer_rewards_deduct">Deduct</label>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1" for="customer_rewards_count">Count</label>
                        <input class="form-control" id="customer_rewards_count" name="points_count" type="number" min="0.01" step="0.01" required placeholder="e.g. 10">
                    </div>
                    <p class="small text-muted mb-0">Use this for manual migration or correction of old loyalty records.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Apply adjustment</button>
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
    const adjustModalEl = document.getElementById('customerRewardsAdjustModal');
    const adjustModal = adjustModalEl ? new bootstrap.Modal(adjustModalEl) : null;
    const adjustForm = document.getElementById('customerRewardsAdjustForm');
    const adjustName = document.getElementById('customer_rewards_name');
    const adjustBalance = document.getElementById('customer_rewards_balance');
    const adjustCount = document.getElementById('customer_rewards_count');
    const adjustTypeAdd = document.getElementById('customer_rewards_add');
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
    document.querySelectorAll('.js-adjust-rewards').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-id');
            if (!id || !adjustForm) return;
            adjustForm.action = `${baseUrl}/${id}/rewards-adjust`;
            if (adjustName) adjustName.textContent = btn.getAttribute('data-name') || '-';
            if (adjustBalance) adjustBalance.textContent = btn.getAttribute('data-balance') || '0.00';
            if (adjustCount) adjustCount.value = '';
            if (adjustTypeAdd) adjustTypeAdd.checked = true;
            adjustModal?.show();
        });
    });
})();
</script>
