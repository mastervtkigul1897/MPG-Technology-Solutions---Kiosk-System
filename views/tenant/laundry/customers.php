<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<?php if (! empty($reward_system_active)): ?>
<div class="alert alert-light border small mb-2">
    Reward load only increases from paid sales with <strong>Eligible for Reward</strong> enabled. To stop counting, turn off <strong>Activate Reward System</strong>.
</div>
<?php endif; ?>
<?php
$contactRequired = ! empty($customer_requirements['contact_required']);
$emailRequired = ! empty($customer_requirements['email_required']);
?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="POST" action="<?= e(route('tenant.customers.store')) ?>" id="customerRequirementsForm" class="d-flex flex-wrap align-items-center gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="update_customer_requirements" value="1">
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="customer_contact_required" name="customer_contact_required" value="1" <?= $contactRequired ? 'checked' : '' ?>>
                <label class="form-check-label" for="customer_contact_required">Required Contact</label>
            </div>
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" id="customer_email_required" name="customer_email_required" value="1" <?= $emailRequired ? 'checked' : '' ?>>
                <label class="form-check-label" for="customer_email_required">Required Email</label>
            </div>
        </form>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="<?= e(route('tenant.customers.store')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label mb-1">Customer name</label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Contact</label>
                <input class="form-control" name="contact" <?= $contactRequired ? 'required' : '' ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Email</label>
                <input type="email" class="form-control" name="email" <?= $emailRequired ? 'required' : '' ?>>
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="small text-muted">Tip: Click a customer row to show existing transactions for this customer.</div>
            <div class="ms-auto" style="min-width: 240px;">
                <label class="visually-hidden" for="customerListSearchInput">Search customer list</label>
                <input type="search" class="form-control form-control-sm" id="customerListSearchInput" placeholder="Search customer..." autocomplete="off">
            </div>
        </div>
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
                <tr class="js-customer-row" data-customer-id="<?= $cid ?>" data-customer-name="<?= e((string) ($customer['name'] ?? '')) ?>" role="button" tabindex="0" title="View customer transactions">
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
                                class="btn btn-sm btn-outline-primary js-edit-customer js-no-row-open"
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
                                class="btn btn-sm btn-outline-warning js-adjust-rewards js-no-row-open"
                                data-id="<?= $cid ?>"
                                data-name="<?= e((string) ($customer['name'] ?? '')) ?>"
                                data-balance="<?= e(number_format((float) ($customer['rewards_balance'] ?? 0), 2, '.', '')) ?>"
                                title="Adjust reward count (+/-)"
                            >
                                <span class="fw-bold">+/-</span>
                            </button>
                            <form method="POST" class="js-no-row-open" action="<?= e(route('tenant.customers.destroy', ['id' => $cid])) ?>" onsubmit="return confirm('Delete this customer?');">
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
<div class="modal fade" id="customerTransactionsModal" tabindex="-1" aria-labelledby="customerTransactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="customerTransactionsModalLabel">Customer transactions</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="customerTransactionsSummary">Loading...</div>
                <div id="customerTransactionsList"></div>
            </div>
        </div>
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
                        <input class="form-control" id="customer_edit_contact" name="contact" <?= $contactRequired ? 'required' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1" for="customer_edit_email">Email</label>
                        <input class="form-control" id="customer_edit_email" name="email" type="email" <?= $emailRequired ? 'required' : '' ?>>
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
    const requirementsForm = document.getElementById('customerRequirementsForm');
    const requirementsChecks = requirementsForm ? requirementsForm.querySelectorAll('input[type="checkbox"]') : [];
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
    const customerRows = document.querySelectorAll('.js-customer-row');
    const customerListSearchInput = document.getElementById('customerListSearchInput');
    const txModalEl = document.getElementById('customerTransactionsModal');
    const txModal = txModalEl ? new bootstrap.Modal(txModalEl) : null;
    const txSummary = document.getElementById('customerTransactionsSummary');
    const txList = document.getElementById('customerTransactionsList');
    const fmtMoney = (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    const formatDateTime = (raw) => {
        const d = new Date(raw);
        if (Number.isNaN(d.getTime())) return raw || '-';
        return d.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    };
    const paymentLabel = (tx) => {
        const method = String(tx.payment_method || '').trim();
        if (method === 'split_payment') {
            const onlineMethod = String(tx.split_online_method || '').trim();
            return `Split (Cash + ${onlineMethod || 'Online'})`;
        }
        return method !== '' ? method.replace(/_/g, ' ') : '-';
    };
    const renderTransactions = (customerName, transactions) => {
        if (!txSummary || !txList) return;
        txSummary.textContent = `${customerName} • ${transactions.length} transaction(s)`;
        if (!Array.isArray(transactions) || transactions.length === 0) {
            txList.innerHTML = '<div class="alert alert-light border mb-0">No transaction found for this customer.</div>';
            return;
        }
        txList.innerHTML = transactions.map((tx) => {
            const addOns = Array.isArray(tx.add_ons) ? tx.add_ons : [];
            const addOnHtml = addOns.length > 0
                ? `<ul class="mb-0">${addOns.map((a) => `<li>${esc(a.item_name || '-')}: ${esc(a.quantity || 0)} x ${esc(fmtMoney(a.unit_price || 0))} = ${esc(fmtMoney(a.total_price || 0))}</li>`).join('')}</ul>`
                : '<span class="text-muted">None</span>';
            const reference = tx.reference_code ? `#${esc(tx.reference_code)}` : `#${esc(tx.id)}`;
            return `
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div><strong>${reference}</strong> <span class="text-muted">(${esc(formatDateTime(tx.created_at || ''))})</span></div>
                        <div class="fw-semibold">${esc(fmtMoney(tx.total_amount || 0))}</div>
                    </div>
                    <div class="row g-2 mt-1 small">
                        <div class="col-md-3"><strong>Order type:</strong> ${esc(tx.order_type || '-')}</div>
                        <div class="col-md-3"><strong>Load status:</strong> ${esc(tx.status || '-')}</div>
                        <div class="col-md-3"><strong>Payment status:</strong> ${esc(tx.payment_status || '-')}</div>
                        <div class="col-md-3"><strong>Payment method:</strong> ${esc(paymentLabel(tx))}</div>
                        <div class="col-md-3"><strong>Loads:</strong> ${esc(tx.wash_qty || 0)}</div>
                        <div class="col-md-3"><strong>Dry minutes:</strong> ${esc(tx.dry_minutes || 0)}</div>
                        <div class="col-md-3"><strong>Machine type:</strong> ${esc(tx.machine_type || '-')}</div>
                        <div class="col-md-3"><strong>Fold service:</strong> ${tx.include_fold_service ? 'Yes' : 'No'}</div>
                        <div class="col-md-3"><strong>Subtotal:</strong> ${esc(fmtMoney(tx.subtotal || 0))}</div>
                        <div class="col-md-3"><strong>Add-on total:</strong> ${esc(fmtMoney(tx.add_on_total || 0))}</div>
                        <div class="col-md-3"><strong>Discount:</strong> ${esc(tx.discount_percentage || 0)}% (${esc(fmtMoney(tx.discount_amount || 0))})</div>
                        <div class="col-md-3"><strong>Excess fee:</strong> ${esc(fmtMoney(tx.excess_weight_fee_amount || 0))}</div>
                        <div class="col-md-3"><strong>Service weight:</strong> ${esc(tx.service_weight || 0)} kg</div>
                        <div class="col-md-3"><strong>Actual weight:</strong> ${esc(tx.actual_weight_kg || 0)} kg</div>
                        <div class="col-md-3"><strong>Excess weight:</strong> ${esc(tx.excess_weight_kg || 0)} kg</div>
                        <div class="col-md-3"><strong>Machine IDs:</strong> W:${esc(tx.washer_machine_id || 0)} D:${esc(tx.dryer_machine_id || 0)}</div>
                        <div class="col-md-3"><strong>Free mode:</strong> ${tx.is_free ? 'Yes' : 'No'}</div>
                        <div class="col-md-3"><strong>Reward mode:</strong> ${tx.is_reward ? 'Yes' : 'No'}</div>
                        <div class="col-md-3"><strong>Split cash:</strong> ${esc(fmtMoney(tx.split_cash_amount || 0))}</div>
                        <div class="col-md-3"><strong>Split online:</strong> ${esc(fmtMoney(tx.split_online_amount || 0))}</div>
                        <div class="col-md-12"><strong>Add-ons:</strong> ${addOnHtml}</div>
                    </div>
                </div>
            `;
        }).join('');
    };
    const fetchCustomerTransactions = async (customerId, customerName) => {
        if (!txSummary || !txList) return;
        txSummary.textContent = `${customerName} • Loading transactions...`;
        txList.innerHTML = '<div class="small text-muted">Please wait...</div>';
        txModal?.show();
        try {
            const res = await fetch(`${baseUrl}/${customerId}/transactions`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!res.ok || data.success !== true) {
                txSummary.textContent = `${customerName}`;
                txList.innerHTML = `<div class="alert alert-warning mb-0">${esc(data.message || 'Could not load transactions.')}</div>`;
                return;
            }
            renderTransactions(customerName, data.transactions || []);
        } catch (_) {
            txSummary.textContent = `${customerName}`;
            txList.innerHTML = '<div class="alert alert-warning mb-0">Could not load transactions. Please try again.</div>';
        }
    };

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
    customerRows.forEach((row) => {
        const open = () => {
            const customerId = row.getAttribute('data-customer-id');
            const customerName = row.getAttribute('data-customer-name') || 'Customer';
            if (!customerId) return;
            fetchCustomerTransactions(customerId, customerName);
        };
        row.addEventListener('click', (e) => {
            const target = e.target;
            if (target instanceof Element && target.closest('.js-no-row-open')) return;
            open();
        });
        row.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            open();
        });
    });
    if (customerListSearchInput) {
        customerListSearchInput.addEventListener('input', () => {
            const query = (customerListSearchInput.value || '').trim().toLowerCase();
            customerRows.forEach((row) => {
                const text = (row.textContent || '').toLowerCase();
                row.classList.toggle('d-none', query !== '' && !text.includes(query));
            });
        });
    }
    requirementsChecks.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            requirementsForm?.submit();
        });
    });
})();
</script>
