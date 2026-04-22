<?php
$email = strtolower(trim((string) ($email ?? '')));
$token = trim((string) ($token ?? ''));
?>
<form method="POST" action="<?= e(url('/reset-password')) ?>" class="vstack gap-3">
    <?= csrf_field() ?>
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div>
        <label class="form-label" for="reset_email">Email</label>
        <input type="email" class="form-control" id="reset_email" value="<?= e($email) ?>" disabled>
    </div>
    <div>
        <label class="form-label" for="reset_password">New password</label>
        <input type="password" class="form-control" id="reset_password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div>
        <label class="form-label" for="reset_password_confirmation">Confirm new password</label>
        <input type="password" class="form-control" id="reset_password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary">Reset password</button>
</form>
