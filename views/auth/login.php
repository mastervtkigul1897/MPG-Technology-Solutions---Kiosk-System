<form method="POST" action="<?= e(url('/login')) ?>" class="vstack gap-2">
    <?= csrf_field() ?>
    <div>
        <label class="form-label" for="login_email">Email</label>
        <input type="email" class="form-control" id="login_email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
    </div>
    <div>
        <label class="form-label" for="login_password">Password</label>
        <input type="password" class="form-control" id="login_password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary">Login</button>
    <div class="text-center small">
        <a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
    </div>
</form>
