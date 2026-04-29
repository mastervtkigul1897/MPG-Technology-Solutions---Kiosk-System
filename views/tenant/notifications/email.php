<?php require dirname(__DIR__, 2).'/partials/premium_trial_page_banner.php'; ?>
<?php
$cfg = (array) ($notification_email ?? []);
$subject = (string) ($cfg['subject'] ?? 'Laundry ready for pick up');
$template = (string) ($cfg['template'] ?? 'Hello {customer_name}, your laundry order {reference_code} is done and ready for pick up.');
$shopEmail = (string) ($cfg['shop_email'] ?? '');
$shopPhone = (string) ($cfg['shop_phone'] ?? '');
?>
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">Email Notifications</h5>
        <p class="small text-muted mb-0">Configure automatic email alerts when a laundry job is completed and ready for pick up.</p>
    </div>
</div>
<div class="alert alert-info border-0 shadow-sm">
    <strong>Soon:</strong> this will be implemented on <strong>May 1, 2026</strong>.
</div>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= e(route('tenant.notifications.email.update')) ?>" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-8">
                <label class="form-label" for="pickupEmailSubject">Email subject</label>
                <input class="form-control" id="pickupEmailSubject" name="pickup_email_subject" value="<?= e($subject) ?>" maxlength="180">
            </div>
            <div class="col-12">
                <label class="form-label" for="pickupEmailTemplate">Email message</label>
                <textarea class="form-control" id="pickupEmailTemplate" name="pickup_email_template" rows="5" maxlength="2000"><?= e($template) ?></textarea>
                <div class="form-text">Allowed placeholders: <code>{customer_name}</code>, <code>{reference_code}</code>, <code>{store_name}</code>.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pickupContactShopEmailEmail">Shop email</label>
                <input type="email" class="form-control" id="pickupContactShopEmailEmail" name="pickup_contact_shop_email" value="<?= e($shopEmail) ?>" maxlength="180" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pickupContactShopPhoneEmail">Shop phone number</label>
                <input type="text" class="form-control" id="pickupContactShopPhoneEmail" name="pickup_contact_shop_phone" value="<?= e($shopPhone) ?>" maxlength="30" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save Email settings</button>
            </div>
        </form>
    </div>
</div>
