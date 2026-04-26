<?php
/** @var array<string, string> $settings */
/** @var bool $can_run_storage_migrations */
$s = $settings ?? [];
$appNameVal = (string) ($s['app_name'] ?? '');
$maintOn = ($s['maintenance_mode'] ?? '0') === '1';
$msg = (string) ($s['maintenance_message'] ?? '');
$warnDays = (int) ($s['subscription_warning_days'] ?? 7);
$canRunStorageMigrations = ! empty($can_run_storage_migrations);
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-3 p-md-4">
        <p class="small text-muted mb-4">These values apply to the whole application. If <strong>Application name</strong> is empty, the name from your environment file is used.</p>
        <form method="POST" action="<?= e(url('/super-admin/settings')) ?>" class="vstack gap-4">
            <?= csrf_field() ?>
            <div>
                <label class="form-label" for="app_name">Application name</label>
                <input type="text" class="form-control" id="app_name" name="app_name" value="<?= e($appNameVal) ?>" maxlength="255" placeholder="Shown in the browser title and sidebar" autocomplete="organization">
                <div class="form-text">Website / product name visible to all users.</div>
            </div>
            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?= $maintOn ? 'checked' : '' ?>>
                    <label class="form-check-label" for="maintenance_mode">Maintenance mode</label>
                </div>
                <div class="form-text">When enabled, store owners and cashiers see your maintenance message on the dashboard (they can still use the app).</div>
            </div>
            <div>
                <label class="form-label" for="maintenance_message">Maintenance message</label>
                <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3" maxlength="2000" placeholder="e.g. Scheduled maintenance tonight 10pm–12am."><?= e($msg) ?></textarea>
            </div>
            <div>
                <label class="form-label" for="subscription_warning_days">Subscription ending reminder (days)</label>
                <input type="number" class="form-control" style="max-width:8rem" id="subscription_warning_days" name="subscription_warning_days" value="<?= (int) $warnDays ?>" min="1" max="90" required>
                <div class="form-text">Store dashboard shows a notice when the subscription end date falls within this many days (including the last day).</div>
            </div>
            <div>
                <button type="submit" class="btn btn-primary px-4 py-2">Save settings</button>
            </div>
        </form>
        <?php if ($canRunStorageMigrations): ?>
            <hr class="my-4">
            <div class="rounded-3 border p-3 p-md-4 bg-body-tertiary bg-opacity-25">
                <h6 class="mb-2">Database migrations</h6>
                <p class="small text-muted mb-3">Run SQL files from <code>storage/migrations</code> directly from this page. Use this after pulling new updates that include migration scripts.</p>
                <form method="POST" action="<?= e(route('super-admin.settings.run-storage-migrations')) ?>" onsubmit="return confirm('Run storage migrations now? This can modify database structure and data.');">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="migrationConfirmationInput">Type <strong>RUN MIGRATIONS</strong> to confirm</label>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            id="migrationConfirmationInput"
                            name="migration_confirmation"
                            placeholder="RUN MIGRATIONS"
                            required
                        >
                    </div>
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fa-solid fa-database me-1" aria-hidden="true"></i>Run storage migrations
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
