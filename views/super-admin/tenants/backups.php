<?php
/** @var array<string,mixed> $tenant */
/** @var array<int,array<string,mixed>> $backups */
$tenantName = (string) ($tenant['name'] ?? 'Store');
$tenantSlug = (string) ($tenant['slug'] ?? '');
$tenantId = (int) ($tenant['id'] ?? 0);
$retentionDays = (int) ($retention_days ?? 30);
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div>
                <h6 class="mb-1">Store: <?= e($tenantName) ?></h6>
                <div class="small text-muted">Slug: <?= e($tenantSlug) ?> · ID: <?= (int) $tenantId ?></div>
                <div class="small text-muted">Daily backups keep the last <?= (int) $retentionDays ?> day(s).</div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(route('super-admin.tenants.index')) ?>" class="btn btn-outline-secondary btn-sm">Back to stores</a>
                <form method="POST" action="<?= e(route('super-admin.tenants.backups.store', ['id' => $tenantId])) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-floppy-disk me-1"></i>Create backup now
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4 table-responsive">
        <table class="table table-striped table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th class="d-none d-md-table-cell">Type</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="d-none d-lg-table-cell">Size</th>
                    <th class="d-none d-md-table-cell">Rows</th>
                    <th class="d-none d-lg-table-cell">Checksum</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($backups ?? []) === []): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No backups yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach (($backups ?? []) as $backup): ?>
                        <?php
                        $bid = (int) ($backup['id'] ?? 0);
                        $status = (string) ($backup['status'] ?? 'unknown');
                        $badge = match ($status) {
                            'ready' => 'text-bg-success',
                            'running' => 'text-bg-warning',
                            'failed' => 'text-bg-danger',
                            default => 'text-bg-secondary',
                        };
                        $confirmToken = 'RESTORE '.strtoupper($tenantSlug);
                        ?>
                        <tr>
                            <td><?= $bid ?></td>
                            <td class="d-none d-md-table-cell"><?= e((string) ($backup['backup_type'] ?? 'manual')) ?></td>
                            <td><span class="badge <?= e($badge) ?>"><?= e($status) ?></span></td>
                            <td><?= e((string) ($backup['created_at'] ?? '')) ?></td>
                            <td class="d-none d-lg-table-cell"><?= e(number_format(((int) ($backup['file_size'] ?? 0)) / 1024, 1)) ?> KB</td>
                            <td class="d-none d-md-table-cell"><?= e(number_format((float) ($backup['row_count'] ?? 0), 0)) ?></td>
                            <td class="small text-muted d-none d-lg-table-cell"><?= e(substr((string) ($backup['checksum_sha256'] ?? ''), 0, 16)) ?>...</td>
                            <td class="text-end">
                                <?php if ($status === 'ready'): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#restoreBackupModal"
                                        data-backup-id="<?= (int) $bid ?>"
                                        data-confirm-token="<?= e($confirmToken) ?>">
                                        Restore
                                    </button>
                                <?php else: ?>
                                    <span class="small text-muted">Not restorable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="restoreBackupModal" tabindex="-1" aria-labelledby="restoreBackupModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="restoreBackupForm" action="">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreBackupModalTitle">Restore store backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        This action will replace current store data with the selected backup. A pre-restore snapshot is created automatically first.
                    </div>
                    <label class="form-label" for="restore_confirmation">Type confirmation code to continue</label>
                    <input type="text" class="form-control" id="restore_confirmation" name="restore_confirmation" required autocomplete="off">
                    <div class="form-text">Required format: <code id="restoreConfirmHelp"></code></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Restore now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const restoreModal = document.getElementById('restoreBackupModal');
    const restoreForm = document.getElementById('restoreBackupForm');
    const helpEl = document.getElementById('restoreConfirmHelp');
    const inputEl = document.getElementById('restore_confirmation');
    const baseRestoreUrl = <?= json_embed(url('/super-admin/tenants/'.$tenantId.'/backups')) ?>;

    if (!restoreModal || !restoreForm || !helpEl || !inputEl) return;

    restoreModal.addEventListener('show.bs.modal', (ev) => {
        const btn = ev.relatedTarget;
        const backupId = btn?.getAttribute('data-backup-id') || '';
        const token = btn?.getAttribute('data-confirm-token') || '';
        restoreForm.action = `${baseRestoreUrl}/${backupId}/restore`;
        helpEl.textContent = token;
        inputEl.value = '';
        inputEl.setAttribute('placeholder', token);
    });

    restoreModal.addEventListener('hidden.bs.modal', () => {
        restoreForm.action = '';
        inputEl.value = '';
    });
})();
</script>
