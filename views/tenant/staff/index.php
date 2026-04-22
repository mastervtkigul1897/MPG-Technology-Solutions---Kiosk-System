<?php
/** @var list<array<string,mixed>> $staff */
/** @var array<string,string> $module_labels */
/** @var list<string> $optional_module_keys */
/** @var list<string> $required_baseline_labels */
/** @var bool $module_permissions_available */
/** @var int|null $free_staff_limit */
$module_permissions_available = $module_permissions_available ?? false;
$optional_module_keys = $optional_module_keys ?? [];
$required_baseline_labels = $required_baseline_labels ?? [];
$freeStaffLimit = isset($free_staff_limit) ? (int) $free_staff_limit : 0;
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
<?php if ($freeStaffLimit > 0): ?>
<div class="alert alert-info mb-3 border-0 shadow-sm">
    <strong>Free Mode:</strong> only the store owner and <?= $freeStaffLimit ?> staff account can log in. Rows marked
    <span class="badge text-bg-warning text-dark ms-1">Free-limited</span> are restricted until upgrade.
</div>
<?php endif; ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Store team / Shop team</h6>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTeamModal">
                        Add team member
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Compensation</th>
                                <th>Added</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($staff as $row): ?>
                            <?php $stype = strtolower((string) ($row['staff_type'] ?? 'full_time')); ?>
                            <?php $isFreeRestrictedRow = ! empty($row['free_limited_restricted']); ?>
                            <tr class="<?= $isFreeRestrictedRow ? 'table-warning' : '' ?>">
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
                                        <?php
                                        $roleLabel = match ($stype) {
                                            'utility' => 'Utility',
                                            'driver' => 'Driver',
                                            'part_timer' => 'Part Time',
                                            default => 'Full Time',
                                        };
                                        ?>
                                        <span class="badge text-bg-secondary"><?= e($roleLabel) ?></span>
                                        <?php if ($isFreeRestrictedRow): ?>
                                            <span class="badge text-bg-warning text-dark ms-1">Free-limited</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small">
                                    <?php if (($row['role'] ?? '') === 'cashier'): ?>
                                    <form method="POST" action="<?= e(route('tenant.staff.update-day-rate', ['id' => (int) $row['id']])) ?>" class="d-flex gap-1 align-items-center">
                                        <?= csrf_field() ?>
                                        <?= method_field('PATCH') ?>
                                        <input class="form-control form-control-sm" style="max-width:100px;" type="number" min="0" step="0.01" name="day_rate" value="<?= e((string) number_format((float) ($row['day_rate'] ?? 350), 2, '.', '')) ?>">
                                        <input class="form-control form-control-sm" style="max-width:120px;" type="number" min="0" step="0.01" name="overtime_rate_per_hour" value="<?= e((string) number_format((float) ($row['overtime_rate_per_hour'] ?? 0), 2, '.', '')) ?>">
                                        <input class="form-control form-control-sm" style="max-width:100px;" type="number" min="1" max="24" step="0.5" name="working_hours_per_day" value="<?= e((string) number_format((float) ($row['working_hours_per_day'] ?? 8), 2, '.', '')) ?>">
                                            <select class="form-select form-select-sm" name="staff_type" style="max-width:150px;">
                                                <option value="utility" <?= $stype === 'utility' ? 'selected' : '' ?>>Utility</option>
                                                <option value="driver" <?= $stype === 'driver' ? 'selected' : '' ?>>Driver</option>
                                                <option value="full_time" <?= ($stype === 'full_time' || $stype === '') ? 'selected' : '' ?>>Full Time</option>
                                                <option value="part_timer" <?= $stype === 'part_timer' ? 'selected' : '' ?>>Part Timer</option>
                                            </select>
                                            <label class="form-check form-check-inline m-0 border rounded px-2 py-1">
                                                <input class="form-check-input" type="checkbox" name="commission_eligible" value="1" <?= ! empty($row['commission_eligible']) ? 'checked' : '' ?>>
                                                <span class="form-check-label small">Commission eligible</span>
                                            </label>
                                            <div class="d-flex flex-wrap gap-2 border rounded px-2 py-1" style="max-width:280px;">
                                                <?php $wcsv = (string) ($row['work_days_csv'] ?? '1,2,3,4,5,6,7'); $wset = array_flip(array_map('intval', array_filter(explode(',', $wcsv)))); ?>
                                                <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $dayNo => $dayLbl): ?>
                                                    <label class="form-check form-check-inline m-0">
                                                        <input class="form-check-input" type="checkbox" name="work_days[]" value="<?= $dayNo ?>" <?= isset($wset[$dayNo]) ? 'checked' : '' ?>>
                                                        <span class="form-check-label small"><?= e($dayLbl) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                    <div class="small text-muted mt-1">Salary / OT rate / Hours / Commission / Type / Working days</div>
                                    <?php else: ?>
                                        <span class="small text-muted">Store owner settings are not editable here.</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= e($row['created_at'] ? date('M j, Y', strtotime((string) $row['created_at'])) : '') ?></td>
                                <td class="text-end">
                                    <?php if (($row['role'] ?? '') === 'cashier'): ?>
                                        <?php $isTimeOnly = in_array($stype, ['utility', 'driver'], true); ?>
                                        <?php if ($module_permissions_available && ! $isTimeOnly): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1 px-2" data-bs-toggle="modal" data-bs-target="#modulesModal<?= (int) $row['id'] ?>" title="Edit module access" aria-label="Edit module access">
                                            <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                                        </button>
                                        <?php elseif ($isTimeOnly): ?>
                                            <span class="badge text-bg-light text-dark border me-1">Time in/out only</span>
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

<div class="modal fade" id="addTeamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Add team member</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Every cashier can always use Daily Sales Monitoring, Customer Profile, and Activity log. Turn on extra areas below if this person should help with inventory, expenses, and more. Reports are only available to the store owner.</p>
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
                    <div>
                        <label class="form-label">Day rate</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="day_rate" value="350" required>
                    </div>
                    <div>
                        <label class="form-label">Hourly overtime rate</label>
                        <input class="form-control" type="number" min="0" step="0.01" name="overtime_rate_per_hour" value="0" required>
                    </div>
                    <div>
                        <label class="form-label">Working hours per day</label>
                        <input class="form-control" type="number" min="1" max="24" step="0.5" name="working_hours_per_day" value="8" required>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="commission_eligible" value="1" id="newCommissionEligible">
                        <label class="form-check-label" for="newCommissionEligible">Commission Eligible</label>
                    </div>
                    <div>
                        <label class="form-label">Working days (Mon-Sun)</label>
                        <div class="d-flex flex-wrap gap-3 border rounded px-3 py-2">
                            <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $dayNo => $dayLbl): ?>
                                <label class="form-check form-check-inline m-0">
                                    <input class="form-check-input" type="checkbox" name="work_days[]" value="<?= $dayNo ?>" checked>
                                    <span class="form-check-label"><?= e($dayLbl) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Staff type</label>
                        <select class="form-select" name="staff_type" required>
                            <option value="utility">Utility (time in/out only)</option>
                            <option value="driver">Driver (time in/out only)</option>
                            <option value="full_time" selected>Full Time (cashier access)</option>
                            <option value="part_timer">Part Timer (cashier access)</option>
                        </select>
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
</div>

<?php if ($module_permissions_available): ?>
<?php foreach ($staff as $row): ?>
    <?php if (($row['role'] ?? '') !== 'cashier') { continue; } ?>
    <?php $stype = strtolower((string) ($row['staff_type'] ?? 'full_time')); ?>
    <?php if (in_array($stype, ['utility', 'driver'], true)) { continue; } ?>
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
