<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<?php
$cfg = (array) ($notification_sms ?? []);
$template = (string) ($cfg['template'] ?? 'Hi {customer_name}, your laundry order {reference_code} is done and ready for pick up.');
$shopEmail = (string) ($cfg['shop_email'] ?? '');
$shopPhone = (string) ($cfg['shop_phone'] ?? '');
$dailyCredits = (int) ($cfg['daily_credits'] ?? 0);
$extraCredits = (int) ($cfg['extra_credits'] ?? 0);
?>
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">SMS Notifications</h5>
        <p class="small text-muted mb-0">Configure automatic SMS alerts when a laundry job is completed and ready for pick up.</p>
    </div>
</div>
<div class="alert alert-info border-0 shadow-sm">
    <strong>Soon:</strong> this will be implemented on <strong>May 1, 2026</strong>.
</div>
<div class="card">
    <div class="card-body">
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <div class="alert alert-info mb-0 py-2">Daily credits: <strong><?= $dailyCredits ?></strong></div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-secondary mb-0 py-2">Extra credits: <strong><?= $extraCredits ?></strong></div>
            </div>
        </div>
        <form method="POST" action="<?= e(route('tenant.notifications.sms.update')) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12">
                <label class="form-label" for="pickupSmsTemplate">SMS template</label>
                <textarea class="form-control" id="pickupSmsTemplate" name="pickup_sms_template" rows="3" maxlength="500"><?= e($template) ?></textarea>
                <div class="form-text">Allowed placeholders: <code>{customer_name}</code>, <code>{reference_code}</code>, <code>{store_name}</code>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pickupContactShopEmailSms">Shop email</label>
                <input type="email" class="form-control" id="pickupContactShopEmailSms" name="pickup_contact_shop_email" value="<?= e($shopEmail) ?>" maxlength="180" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pickupContactShopPhoneSms">Shop phone number</label>
                <input type="text" class="form-control" id="pickupContactShopPhoneSms" name="pickup_contact_shop_phone" value="<?= e($shopPhone) ?>" maxlength="30" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save SMS settings</button>
            </div>
        </form>
    </div>
</div>
