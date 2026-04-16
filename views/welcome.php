<p class="text-muted mb-2">Kiosk POS and Inventory System</p>
<p class="small text-muted mb-3">Start your own account with a free 7-day trial.</p>
<div class="d-flex gap-2">
    <?php if (! empty($canLogin)): ?>
        <a href="<?= e(url('/login')) ?>" class="btn btn-primary">Login</a>
    <?php endif; ?>
    <?php if (! empty($canRegister)): ?>
        <a href="<?= e(url('/register')) ?>" class="btn btn-outline-secondary">Start 7-day trial</a>
    <?php endif; ?>
    <a href="<?= e(url('/pricing')) ?>" class="btn btn-outline-primary">Pricing</a>
</div>
