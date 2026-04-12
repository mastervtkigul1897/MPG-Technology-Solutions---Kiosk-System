<div class="card border-0 shadow-sm mb-3">
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
                    <label class="form-label" for="license_expires_at">Subscription ends</label>
                    <input type="date" class="form-control" id="license_expires_at" name="license_expires_at" autocomplete="off">
                    <div class="form-text">When this store’s access should end (optional).</div>
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
<div class="card border-0 shadow-sm">
    <div class="card-body table-responsive p-3 p-md-4">
        <table class="table table-striped w-100" id="tenantsTable">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Store name</th>
                    <th>Slug</th>
                    <th>Branch details</th>
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

<script>
(() => {
    const tenantsBase = <?= json_encode(url('/super-admin/tenants')) ?>;
    const isMobile = window.matchMedia('(max-width: 767.98px)').matches;
    const isTablet = window.matchMedia('(max-width: 991.98px)').matches;
    const hiddenByViewport = isMobile
        ? [1, 3, 4, 5, 6, 8]
        : (isTablet ? [3, 4, 5, 6] : []);

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
            { targets: 7, responsivePriority: 4 },
            { targets: 8, responsivePriority: 99 },
            { targets: 9, responsivePriority: 3 },
            { targets: 10, orderable: false, searchable: false, responsivePriority: 5 },
            { targets: hiddenByViewport, visible: false },
        ],
        columns: [
            { data: null },
            { data: 'id' },
            { data: 'name' },
            { data: 'slug' },
            { data: 'branch_details' },
            { data: 'paid_amount' },
            { data: 'starts' },
            { data: 'expires' },
            { data: 'owner_email' },
            { data: 'status' },
            { data: 'actions' },
        ],
    });

    document.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit-sub-exp');
        if (editBtn) {
            const wrap = editBtn.closest('.tenant-sub-exp-wrap');
            if (!wrap) return;
            wrap.querySelector('.tenant-sub-exp-view')?.classList.add('d-none');
            wrap.querySelector('.tenant-sub-exp-edit')?.classList.remove('d-none');
            return;
        }
        const cancelBtn = e.target.closest('.btn-cancel-sub-exp');
        if (cancelBtn) {
            const wrap = cancelBtn.closest('.tenant-sub-exp-wrap');
            if (!wrap) return;
            const form = wrap.querySelector('.tenant-sub-exp-edit');
            if (form && form.reset) form.reset();
            wrap.querySelector('.tenant-sub-exp-edit')?.classList.add('d-none');
            wrap.querySelector('.tenant-sub-exp-view')?.classList.remove('d-none');
        }
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
})();
</script>
