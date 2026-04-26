<div class="card modern-section border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-end mb-3">
            <a class="btn btn-outline-primary btn-sm" href="<?= e(route('super-admin.backups.runner')) ?>">
                <i class="fa-solid fa-database me-1"></i>Run daily backup for all stores
            </a>
        </div>
        <p class="small text-muted mb-3">Each store needs a <strong>store owner</strong> (<code>tenant_admin</code>). Stores are not deleted from the system—use <strong>active/inactive</strong> to control access. <strong>Subscription starts</strong> is set when the store is created (not editable here). <strong>Subscription ends</strong> is shown as read-only in its column; use <strong>Edit</strong> in <strong>Actions</strong> to change the end date, then <strong>Save</strong>. <strong>Slug</strong> is fixed after creation. <strong>Reset password</strong> sets a new password for the store owner email shown in the table.</p>
        <form method="POST" action="<?= e(route('super-admin.tenants.store')) ?>" class="vstack gap-3">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="tenant_name">Store name</label>
                    <input class="form-control" id="tenant_name" name="name" required maxlength="255" autocomplete="organization">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="tenant_slug">URL slug</label>
                    <input class="form-control" id="tenant_slug" name="slug" required maxlength="100" autocomplete="off" placeholder="e.g. my-cafe">
                    <div class="form-text">Unique identifier in URLs. Lowercase, numbers, hyphens.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="subscription_plan">Subscription plan</label>
                    <select class="form-select" id="subscription_plan" name="subscription_plan" required>
                        <option value="">Select plan</option>
                        <option value="free_access">Free 7-day trial</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                <div class="col-md-6" id="subscription_months_wrap">
                    <label class="form-label" for="subscription_months">Paid plan duration</label>
                    <select class="form-select" id="subscription_months" name="subscription_months">
                        <option value="">Select duration</option>
                        <option value="1">1 month</option>
                        <option value="3">3 months</option>
                        <option value="6">6 months</option>
                        <option value="12">12 months</option>
                    </select>
                    <div class="form-text">Expiration is auto-calculated from today.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="subscription_expires_preview">Auto subscription end date</label>
                    <input type="text" class="form-control" id="subscription_expires_preview" value="Select a duration first" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="paid_amount">Paid amount</label>
                    <input type="number" class="form-control" id="paid_amount" name="paid_amount" min="0" step="any" placeholder="0" autocomplete="off">
                    <div class="form-text">Amount received for this store (optional, for your records).</div>
                </div>
            </div>
            <hr class="my-0 opacity-25">
            <p class="small fw-semibold mb-0">Store owner account</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="owner_name">Owner full name</label>
                    <input class="form-control" id="owner_name" name="owner_name" required maxlength="255" autocomplete="name">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="owner_email">Owner email (login)</label>
                    <input class="form-control" id="owner_email" type="email" name="owner_email" required autocomplete="email">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="owner_password">Owner password</label>
                    <input class="form-control" id="owner_password" type="password" name="owner_password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">At least 8 characters.</div>
                </div>
            </div>
            <div>
                <button type="submit" class="btn btn-primary px-3 py-2" title="Create tenant and store owner" aria-label="Create tenant and store owner">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </div>
        </form>
    </div>
</div>
<div class="card modern-section border-0 shadow-sm">
    <div class="card-body table-responsive p-3 p-md-4">
        <table class="table table-striped w-100" id="tenantsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Store name</th>
                    <th>Slug</th>
                    <th>Branch details</th>
                    <th>Plan</th>
                    <th>Paid amount</th>
                    <th>Subscription starts</th>
                    <th>Subscription ends</th>
                    <th>Store owner email</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="resetOwnerPasswordModal" tabindex="-1" aria-labelledby="resetOwnerPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetOwnerPasswordModalLabel">Reset store owner password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="resetOwnerPasswordForm" autocomplete="off">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <p class="small text-muted">Set a temporary password for the <strong>tenant_admin</strong> account. The owner should sign in with this password and change it under Profile.</p>
                    <div class="mb-3">
                        <label class="form-label" for="reset_owner_password">New password</label>
                        <input type="password" class="form-control" id="reset_owner_password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="reset_owner_password_confirmation">Confirm password</label>
                        <input type="password" class="form-control" id="reset_owner_password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editTenantModal" tabindex="-1" aria-labelledby="editTenantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTenantModalLabel">Edit store</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="editTenantForm" autocomplete="off">
                <?= csrf_field() ?>
                <?= method_field('PATCH') ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_tenant_name">Store name</label>
                        <input type="text" class="form-control" id="edit_tenant_name" name="name" maxlength="255" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="edit_subscription_plan">Subscription plan</label>
                        <select class="form-select" id="edit_subscription_plan" name="subscription_plan" required>
                            <option value="free_access">Free 7-day trial</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="mt-3" id="edit_subscription_months_wrap">
                        <label class="form-label" for="edit_subscription_months">Paid plan duration</label>
                        <select class="form-select" id="edit_subscription_months" name="subscription_months">
                            <option value="1">1 month</option>
                            <option value="3">3 months</option>
                            <option value="6">6 months</option>
                            <option value="12">12 months</option>
                        </select>
                        <div class="form-text">Selecting a duration recalculates subscription end date from today. Subscription start date remains unchanged.</div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" for="edit_license_expires_at">Subscription end date</label>
                        <input type="date" class="form-control" id="edit_license_expires_at" name="license_expires_at">
                        <input type="hidden" id="edit_original_license_expires_at" name="original_license_expires_at" value="">
                        <div class="form-text">You can manually override the end date for both Free 7-day trial and Paid plans.</div>
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
    const tenantsBase = <?= json_encode(url('/super-admin/tenants')) ?>;
    const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    const isTablet = window.matchMedia('(max-width: 991.98px)').matches;
    const hiddenByViewport = isMobile
        ? [1, 3, 4, 5, 6, 7, 9]
        : (isTablet ? [3, 4, 6, 7] : []);

    initServerDataTable('#tenantsTable', {
        printButton: true,
        ajax: {
            url: '<?= e(route('super-admin.tenants.index')) ?>',
            data: { datatable: 1 }
        },
        columnDefs: [
            { targets: 0, className: 'dtr-control', orderable: false, searchable: false, defaultContent: '', responsivePriority: 1 },
            { targets: 1, responsivePriority: 100 },
            { targets: 2, responsivePriority: 2 },
            { targets: 3, responsivePriority: 95 },
            { targets: 4, responsivePriority: 96 },
            { targets: 5, responsivePriority: 97 },
            { targets: 6, responsivePriority: 98 },
            { targets: 7, responsivePriority: 99 },
            { targets: 8, responsivePriority: 4 },
            { targets: 9, responsivePriority: 100 },
            { targets: 10, responsivePriority: 3 },
            { targets: 11, orderable: false, searchable: false, responsivePriority: 5 },
            { targets: hiddenByViewport, visible: false },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'name' },
            { data: 'slug' },
            { data: 'branch_details' },
            { data: 'plan' },
            { data: 'paid_amount' },
            { data: 'starts' },
            { data: 'expires' },
            { data: 'owner_email' },
            { data: 'status' },
            { data: 'actions' },
        ],
    });

    const resetModal = document.getElementById('resetOwnerPasswordModal');
    const resetForm = document.getElementById('resetOwnerPasswordForm');
    if (resetModal && resetForm) {
        resetModal.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget;
            const tid = btn && btn.getAttribute ? btn.getAttribute('data-tenant-id') : null;
            if (tid) {
                resetForm.action = tenantsBase + '/' + tid + '/reset-owner-password';
            }
        });
        resetModal.addEventListener('hidden.bs.modal', () => {
            resetForm.action = '';
            resetForm.reset();
        });
    }

    const editModal = document.getElementById('editTenantModal');
    const editForm = document.getElementById('editTenantForm');
    const editNameInput = document.getElementById('edit_tenant_name');
    const editPlanInput = document.getElementById('edit_subscription_plan');
    const editMonthsInput = document.getElementById('edit_subscription_months');
    const editExpiresInput = document.getElementById('edit_license_expires_at');
    const editOriginalExpiresInput = document.getElementById('edit_original_license_expires_at');
    const editMonthsWrap = document.getElementById('edit_subscription_months_wrap');
    if (editModal && editForm && editNameInput && editPlanInput && editMonthsInput && editExpiresInput && editOriginalExpiresInput && editMonthsWrap) {
        const syncEditPlanUi = () => {
            const isFree = editPlanInput.value === 'free_access';
            editMonthsWrap.classList.toggle('d-none', isFree);
            editMonthsInput.required = !isFree;
        };
        editModal.addEventListener('show.bs.modal', (ev) => {
            const btn = ev.relatedTarget;
            const tid = btn && btn.getAttribute ? btn.getAttribute('data-tenant-id') : null;
            const tname = btn && btn.getAttribute ? btn.getAttribute('data-tenant-name') : '';
            const tmonths = btn && btn.getAttribute ? btn.getAttribute('data-tenant-plan-months') : '';
            const tplan = btn && btn.getAttribute ? btn.getAttribute('data-tenant-plan-code') : '';
            const texpires = btn && btn.getAttribute ? btn.getAttribute('data-tenant-expires') : '';
            if (!tid) return;
            editForm.action = tenantsBase + '/' + tid;
            editNameInput.value = tname || '';
            editPlanInput.value = String(tplan || '').toLowerCase() === 'free_access' ? 'free_access' : 'paid';
            editMonthsInput.value = ['1', '3', '6', '12'].includes(String(tmonths || '')) ? String(tmonths) : '1';
            editExpiresInput.value = texpires || '';
            editOriginalExpiresInput.value = texpires || '';
            syncEditPlanUi();
        });
        editModal.addEventListener('hidden.bs.modal', () => {
            editForm.action = '';
            editForm.reset();
        });
        editPlanInput.addEventListener('change', syncEditPlanUi);
    }

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-edit-tenant');
        if (!btn || !editModal) return;
        const modal = bootstrap.Modal.getOrCreateInstance(editModal);
        btn.setAttribute('data-bs-toggle', 'modal');
        btn.setAttribute('data-bs-target', '#editTenantModal');
        modal.show(btn);
    });

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('.js-delete-tenant-form');
        if (!form) return;
        if (form.dataset.mpgConfirmBypass === '1') {
            form.dataset.mpgConfirmBypass = '0';
            return;
        }
        e.preventDefault();
        const tenantName = form.getAttribute('data-tenant-name') || 'this store';
        const requiredPhrase = `DELETE ${String(tenantName).trim().toUpperCase()}`;
        const confirmationInput = form.querySelector('input[name="delete_confirmation"]');
        if (typeof Swal === 'undefined') {
            const typed = window.prompt(`Type ${requiredPhrase} to permanently delete ${tenantName} and all associated data (including users).`);
            if (String(typed || '').trim().toUpperCase() === requiredPhrase) {
                if (confirmationInput) confirmationInput.value = requiredPhrase;
                form.dataset.mpgConfirmBypass = '1';
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            }
            return;
        }
        const res = await Swal.fire({
            icon: 'warning',
            title: 'Delete store?',
            html: `You are about to permanently delete <strong>${tenantName}</strong>.<br>This removes all associated data, including users.`,
            input: 'text',
            inputLabel: `Type ${requiredPhrase} to confirm`,
            inputPlaceholder: requiredPhrase,
            showCancelButton: true,
            confirmButtonText: 'Delete permanently',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            preConfirm: (value) => {
                if (String(value || '').trim().toUpperCase() !== requiredPhrase) {
                    Swal.showValidationMessage(`Type ${requiredPhrase} exactly to continue.`);
                    return false;
                }
                return requiredPhrase;
            },
        });
        if (res.isConfirmed) {
            if (confirmationInput) confirmationInput.value = requiredPhrase;
            form.dataset.mpgConfirmBypass = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        }
    });

    const planSelect = document.getElementById('subscription_plan');
    const monthsSelect = document.getElementById('subscription_months');
    const monthsWrap = document.getElementById('subscription_months_wrap');
    const expiresPreview = document.getElementById('subscription_expires_preview');
    if (planSelect && monthsSelect && monthsWrap && expiresPreview) {
        const formatDate = (d) => {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };
        const syncCreatePlanUi = () => {
            const isFree = planSelect.value === 'free_access';
            monthsWrap.classList.toggle('d-none', isFree);
            monthsSelect.required = !isFree;
            if (isFree) {
                const exp = new Date();
                exp.setDate(exp.getDate() + 7);
                expiresPreview.value = formatDate(exp);
                return;
            }
            refreshExpiryPreview();
        };
        const refreshExpiryPreview = () => {
            const months = parseInt(monthsSelect.value || '0', 10);
            if (![1, 3, 6, 12].includes(months)) {
                expiresPreview.value = 'Select a duration first';
                return;
            }
            const start = new Date();
            const exp = new Date(start);
            exp.setMonth(exp.getMonth() + months);
            expiresPreview.value = formatDate(exp);
        };
        monthsSelect.addEventListener('change', refreshExpiryPreview);
        planSelect.addEventListener('change', syncCreatePlanUi);
        syncCreatePlanUi();
    }
})();
</script>
