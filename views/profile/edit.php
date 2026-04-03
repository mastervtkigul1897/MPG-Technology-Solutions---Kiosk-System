<?php /** @var array $user */ ?>
<?php $role = (string) ($user['role'] ?? ''); ?>
<div class="row g-3">
    <div class="col-12">
        <?php if ($role === 'cashier'): ?>
            <div class="alert alert-info mb-0">
                As a <strong>cashier</strong>, you can only <strong>change your password</strong> here. Name and login email are set by your store owner. Account deletion and self-registration are not available.
            </div>
        <?php elseif ($role === 'tenant_admin'): ?>
            <div class="alert alert-info mb-0">
                Login email and account deletion cannot be changed here. Use <strong>Receipt Data</strong> in the menu for transaction receipt details. You can change your password in the section below.
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">
                Email and account deletion are disabled. You can only change your password below; other profile fields are read-only.
            </div>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-2">User information (read only)</h6>
                <div class="mb-2"><strong>Name:</strong> <?= e($user['name'] ?? '') ?></div>
                <div class="mb-0"><strong>Email:</strong> <?= e($user['email'] ?? '') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6>Change Password</h6>
                <form method="POST" action="<?= e(url('/password')) ?>" class="vstack gap-2">
                    <?= csrf_field() ?>
                    <?= method_field('PUT') ?>
                    <div>
                        <label class="form-label" for="current_password">Current password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div>
                        <label class="form-label" for="new_password">New password</label>
                        <input type="password" class="form-control" id="new_password" name="password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="form-label" for="password_confirmation">Confirm new password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-warning">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
