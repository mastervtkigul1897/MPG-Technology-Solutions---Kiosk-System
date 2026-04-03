<?php
/** @var array<string, string> $settings */
$s = $settings ?? [];
$appNameVal = (string) ($s['app_name'] ?? '');
$maintOn = ($s['maintenance_mode'] ?? '0') === '1';
$msg = (string) ($s['maintenance_message'] ?? '');
$warnDays = (int) ($s['subscription_warning_days'] ?? 7);
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
    </div>
</div>
