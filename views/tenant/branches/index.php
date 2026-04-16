<?php
/** @var array<string,mixed> $root_tenant */
/** @var int $current_tenant_id */
/** @var array<int,array<string,mixed>> $branches */
/** @var int $branch_limit */
/** @var array<int,string> $clone_defaults */
$rootTenant = $root_tenant ?? [];
$rows = $branches ?? [];
$currentTenantId = (int) ($current_tenant_id ?? 0);
$limit = (int) ($branch_limit ?? 1);
$defaults = $clone_defaults ?? ['categories', 'ingredients', 'products', 'requirements'];
?>

<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <h6 class="mb-1">Your branch account</h6>
        <div class="small text-muted">Main account: <?= e((string) ($rootTenant['name'] ?? '')) ?> · Allowed branches: <?= $limit ?></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <h6 class="mb-3">Create new branch</h6>
        <form method="POST" action="<?= e(route('tenant.branches.store')) ?>" class="vstack gap-3">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Branch name</label>
                    <input class="form-control" name="name" maxlength="255" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Branch slug</label>
                    <input class="form-control" name="slug" maxlength="120" placeholder="e.g. my-store-branch-2" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Copy data from source branch</label>
                    <select class="form-select" name="source_tenant_id">
                        <option value="" selected>Fresh New Branch (no data copy)</option>
                        <?php foreach ($rows as $row): ?>
                            <option value="<?= (int) ($row['id'] ?? 0) ?>">
                                <?= e((string) ($row['name'] ?? '')) ?> (<?= e((string) ($row['slug'] ?? '')) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label d-block mb-1">Copy options</label>
                <div class="small text-muted mb-2">Optional. Leave all unchecked for a clean new branch. Excluded by default: staff/accounts, profile/contact/location, transactions, expenses, logs.</div>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach (['categories' => 'Categories', 'ingredients' => 'Inventory items', 'products' => 'Products', 'requirements' => 'Requirements'] as $k => $label): ?>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="clone[]" value="<?= e($k) ?>" <?= in_array($k, $defaults, true) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="small text-muted mb-2">Owner login is shared for all branches in your account. Staff accounts remain per-branch.</div>
                <button class="btn btn-success">Create branch</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4 table-responsive">
        <h6 class="mb-3">Branches</h6>
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Main</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php $id = (int) ($row['id'] ?? 0); ?>
                    <?php $active = (bool) ($row['is_active'] ?? false); ?>
                    <?php $main = (bool) ($row['is_main_branch'] ?? false); ?>
                    <tr>
                        <td><?= $id ?></td>
                        <td><?= e((string) ($row['name'] ?? '')) ?></td>
                        <td><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><?= $main ? '<span class="badge text-bg-primary">Main</span>' : '<span class="badge text-bg-light text-dark border">Branch</span>' ?></td>
                        <td><?= $active ? '<span class="badge text-bg-success">Open</span>' : '<span class="badge text-bg-secondary">Closed</span>' ?></td>
                        <td class="text-end">
                            <?php if (! $main && $active): ?>
                                <form method="POST" action="<?= e(route('tenant.branches.set-main', ['id' => $id])) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-primary">Set main</button>
                                </form>
                            <?php endif; ?>
                            <?php if (! ($main && $active)): ?>
                                <form method="POST"
                                      action="<?= e(route('tenant.branches.toggle-active', ['id' => $id])) ?>"
                                      class="d-inline js-branch-toggle-form"
                                      data-branch-name="<?= e((string) ($row['name'] ?? 'Branch')) ?>"
                                      data-close-confirm="<?= $active ? '1' : '0' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="active" value="<?= $active ? '0' : '1' ?>">
                                    <button class="btn btn-sm <?= $active ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                        <?= $active ? 'Close branch' : 'Open branch' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge text-bg-light text-dark border">Main branch stays open</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(() => {
    document.querySelectorAll('.js-branch-toggle-form[data-close-confirm="1"]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const branchName = form.getAttribute('data-branch-name') || 'this branch';
            if (typeof Swal === 'undefined') {
                if (window.confirm(`Close ${branchName}?`)) form.submit();
                return;
            }
            const res = await Swal.fire({
                icon: 'warning',
                title: 'Close Branch?',
                text: `You are about to close ${branchName}. This branch will not be usable until reopened.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, close branch',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc3545',
            });
            if (res.isConfirmed) {
                form.submit();
            }
        });
    });
})();
</script>
