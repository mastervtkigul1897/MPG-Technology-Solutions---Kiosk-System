<?php
$email = strtolower(trim((string) ($email ?? '')));
?>
<div class="vstack gap-3">
    <div class="alert alert-info mb-0">
        <div class="fw-semibold mb-1">Verify your email address</div>
        <div class="small">
            We sent a verification link to <strong><?= e($email !== '' ? $email : 'your email address') ?></strong>.
            Open that link to activate your account.
        </div>
    </div>

    <form method="POST" action="<?= e(url('/email/verification-notification')) ?>" class="vstack gap-2">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Resend verification email</button>
    </form>

    <form method="POST" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-secondary w-100">Logout</button>
    </form>
</div>
