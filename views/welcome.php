<p class="text-muted">Kiosk POS and Inventory System</p>
<div class="d-flex gap-2">
    <?php if (! empty($canLogin)): ?>
        <a href="<?= e(url('/login')) ?>" class="btn btn-primary">Login</a>
    <?php endif; ?>
    <?php if (! empty($canRegister)): ?>
        <a href="<?= e(url('/register')) ?>" class="btn btn-outline-secondary">Register</a>
    <?php endif; ?>
</div>
