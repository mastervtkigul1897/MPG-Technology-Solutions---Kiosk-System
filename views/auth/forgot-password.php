<form method="POST" action="<?= e(url('/forgot-password')) ?>" class="vstack gap-3">
    <?= csrf_field() ?>
    <div>
        <label class="form-label" for="forgot_email">Verified email</label>
        <input type="email" class="form-control" id="forgot_email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
        <div class="form-text">Password reset links are sent only to verified accounts.</div>
    </div>
    <button type="submit" class="btn btn-primary">Send password reset link</button>
    <a href="<?= e(url('/login')) ?>" class="btn btn-outline-secondary">Back to login</a>
</form>
