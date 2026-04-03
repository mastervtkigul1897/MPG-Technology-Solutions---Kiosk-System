<form method="POST" action="<?= e(url('/register')) ?>" class="vstack gap-2">
    <?= csrf_field() ?>
    <div>
        <label class="form-label" for="reg_name">Name</label>
        <input type="text" class="form-control" id="reg_name" name="name" value="<?= e(old('name')) ?>" required maxlength="255" autocomplete="name">
    </div>
    <div>
        <label class="form-label" for="reg_email">Email</label>
        <input type="email" class="form-control" id="reg_email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email">
    </div>
    <div>
        <label class="form-label" for="reg_password">Password</label>
        <input type="password" class="form-control" id="reg_password" name="password" required minlength="8" autocomplete="new-password">
    </div>
    <div>
        <label class="form-label" for="reg_password_confirmation">Confirm password</label>
        <input type="password" class="form-control" id="reg_password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary">Register</button>
</form>
