<form method="POST" action="<?= e(url('/register')) ?>" class="vstack gap-2">
    <?= csrf_field() ?>
    <?php if (!empty($register_upgrade_blocked)): ?>
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Device already linked to a Free plan</div>
            <div class="small mb-2">
                <?php if (!empty($register_existing_store)): ?>
                    Existing store: <strong><?= e((string) $register_existing_store) ?></strong>.
                <?php endif; ?>
                This device cannot create another Free plan account.
            </div>
            <a href="<?= e(url('/pricing')) ?>" class="btn btn-success btn-sm fw-semibold">
                <i class="fa-solid fa-circle-up me-1"></i>Upgrade now
            </a>
        </div>
    <?php endif; ?>
    <input type="hidden" name="device_token" id="reg_device_token" value="">
    <input type="hidden" name="device_name" id="reg_device_name" value="">
    <input type="hidden" name="device_platform" id="reg_device_platform" value="">
    <input type="hidden" name="device_user_agent" id="reg_device_user_agent" value="">
    <div>
        <label class="form-label" for="reg_store_name">Store name</label>
        <input type="text" class="form-control" id="reg_store_name" name="store_name" value="<?= e(old('store_name')) ?>" required maxlength="255" autocomplete="organization">
        <div class="form-text">This will be used for your trial store account.</div>
    </div>
    <div>
        <label class="form-label" for="reg_name">Name</label>
        <input type="text" class="form-control" id="reg_name" name="name" value="<?= e(old('name')) ?>" required maxlength="255" autocomplete="name">
    </div>
    <div>
        <label class="form-label" for="reg_email">Email</label>
        <input type="email" class="form-control" id="reg_email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
        <div class="form-text">Use a real email address. You can access the system right away, but email verification will be required after 5 days.</div>
    </div>
    <div>
        <label class="form-label" for="reg_password">Password</label>
        <input type="password" class="form-control" id="reg_password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div>
        <label class="form-label" for="reg_password_confirmation">Confirm password</label>
        <input type="password" class="form-control" id="reg_password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <div class="small text-muted">Your trial is valid for 7 days from account creation.</div>
    <button type="submit" class="btn btn-primary">Create trial account</button>
</form>
<script>
(() => {
    const tokenEl = document.getElementById('reg_device_token');
    const nameEl = document.getElementById('reg_device_name');
    const platformEl = document.getElementById('reg_device_platform');
    const uaEl = document.getElementById('reg_device_user_agent');
    if (!tokenEl || !nameEl || !platformEl || !uaEl) return;
    const safe = (v, max = 180) => String(v || '').slice(0, max);
    let token = '';
    try {
        token = safe(localStorage.getItem('mpg_device_token'));
        if (!token) {
            const rand = (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 48);
            token = safe('mpg_' + rand, 64);
            localStorage.setItem('mpg_device_token', token);
        }
    } catch (e) {
        token = '';
    }
    const platform = safe((navigator.platform || '') + ' | ' + (navigator.userAgentData && navigator.userAgentData.platform ? navigator.userAgentData.platform : ''), 180);
    const ua = safe(navigator.userAgent || '', 500);
    const deviceName = safe((navigator.vendor || 'Browser') + ' on ' + (navigator.platform || 'Unknown OS'), 180);
    tokenEl.value = token;
    nameEl.value = deviceName;
    platformEl.value = platform;
    uaEl.value = ua;
})();
</script>
