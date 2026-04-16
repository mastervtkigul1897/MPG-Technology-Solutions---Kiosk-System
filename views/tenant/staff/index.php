<?php
/** @var list<array<string,mixed>> $staff */
/** @var array<string,string> $module_labels */
/** @var list<string> $optional_module_keys */
/** @var list<string> $required_baseline_labels */
/** @var bool $module_permissions_available */
$module_permissions_available = $module_permissions_available ?? false;
$optional_module_keys = $optional_module_keys ?? [];
$required_baseline_labels = $required_baseline_labels ?? [];
?>
<?php if (! $module_permissions_available): ?>
<div class="alert alert-warning mb-3">
    <strong>Database migration pending.</strong> Per-staff module access is disabled until you add the column
    <code>module_permissions</code> to the <code>users</code> table. Run the SQL file
    <code>database/add_user_module_permissions.sql</code> on your MySQL database, then reload this page.
    Until then, cashiers use the default access: Create Transaction, Transactions, and Activity log.
</div>
<?php endif; ?>
<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <h6 class="mb-3">Add cashier</h6>
                <p class="small text-muted">Every cashier can always use Create Transaction, Transactions, and Activity log. Turn on extra areas below if this person should help with inventory, products, expenses, and more. Reports are only available to the store owner.</p>
                <form method="POST" action="<?= e(route('tenant.staff.store')) ?>" class="vstack gap-2">
                    <?= csrf_field() ?>
                    <div>
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" required maxlength="255" autocomplete="name">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" required autocomplete="email">
                    </div>
                    <div>
                        <label class="form-label">Password</label>
                        <input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="form-label">Confirm password</label>
                        <input class="form-control" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </div>
                    <?php if ($module_permissions_available): ?>
                    <div>
                        <label class="form-label d-block">Always included</label>
                        <div class="small text-muted border rounded p-2 bg-light mb-2">
                            <?= e(implode(' · ', $required_baseline_labels)) ?>
                        </div>
                        <label class="form-label d-block">Optional access <span class="text-muted fw-normal">(you decide)</span></label>
                        <div class="border rounded p-2 bg-light" style="max-height: 220px; overflow-y: auto;">
                            <?php foreach ($optional_module_keys as $key): ?>
                                <?php $label = $module_labels[$key] ?? $key; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="modules[]" value="<?= e($key) ?>" id="m-new-<?= e($key) ?>">
                                    <label class="form-check-label small" for="m-new-<?= e($key) ?>"><?= e($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary py-2">Create cashier</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <h6 class="mb-3">Store team</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Added</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($staff as $row): ?>
                            <tr>
                                <td><?= e((string) ($row['name'] ?? '')) ?></td>
                                <td><?= e((string) ($row['email'] ?? '')) ?></td>
                                <td>
                                    <?php if (($row['role'] ?? '') === 'tenant_admin'): ?>
                                        <span class="badge text-bg-primary">
                                            Store owner
                                        </span>
                                        <?php if (! empty($row['subscription_expired'])): ?>
                                            <span class="badge text-bg-danger ms-1">Subscription expired</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Cashier</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e($row['created_at'] ? date('M j, Y', strtotime((string) $row['created_at'])) : '') ?></td>
                                <td class="text-end">
                                    <?php if (($row['role'] ?? '') === 'cashier'): ?>
                                        <?php if ($module_permissions_available): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 px-2" data-bs-toggle="modal" data-bs-target="#modulesModal<?= (int) $row['id'] ?>" title="Edit module access" aria-label="Edit module access">
                                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                        </button>
                                        <?php endif; ?>
                                        <form method="POST" action="<?= e(route('tenant.staff.destroy', ['id' => (int) $row['id']])) ?>" class="d-inline" onsubmit="return confirm('Remove this cashier? They will no longer be able to log in.');">
                                            <?= csrf_field() ?>
                                            <?= method_field('DELETE') ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Remove cashier" aria-label="Remove cashier">
                                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($module_permissions_available): ?>
<?php foreach ($staff as $row): ?>
    <?php if (($row['role'] ?? '') !== 'cashier') { continue; } ?>
    <?php $sid = (int) $row['id']; ?>
    <?php $mods = $row['modules'] ?? []; ?>
    <div class="modal fade" id="modulesModal<?= $sid ?>" tabindex="-1" aria-labelledby="modulesModalLabel<?= $sid ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="<?= e(route('tenant.staff.update-modules', ['id' => $sid])) ?>">
                    <?= csrf_field() ?>
                    <?= method_field('PATCH') ?>
                    <div class="modal-header">
                        <h6 class="modal-title" id="modulesModalLabel<?= $sid ?>">Modules for <?= e((string) ($row['name'] ?? '')) ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2"><strong>Always included:</strong> <?= e(implode(' · ', $required_baseline_labels)) ?></p>
                        <label class="form-label small">Optional access</label>
                        <div class="border rounded p-2 bg-light" style="max-height: 260px; overflow-y: auto;">
                            <?php foreach ($optional_module_keys as $key): ?>
                                <?php $label = $module_labels[$key] ?? $key; ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="modules[]" value="<?= e($key) ?>" id="m-<?= $sid ?>-<?= e($key) ?>"
                                        <?= in_array($key, $mods, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="m-<?= $sid ?>-<?= e($key) ?>"><?= e($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php endif; ?>
