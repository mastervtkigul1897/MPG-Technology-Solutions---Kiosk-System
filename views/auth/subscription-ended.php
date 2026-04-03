<?php
/** @var string $appOwnerEmail */
/** @var string $reason */
$email = trim((string) ($appOwnerEmail ?? ''));
$reason = (string) ($reason ?? 'expired');
$isInactive = $reason === 'inactive';
?>
<div class="subscription-card p-4 p-md-5 text-center">
    <div class="d-inline-flex subscription-icon-wrap align-items-center justify-content-center mb-4">
        <?php if ($isInactive) : ?>
            <i class="fa-solid fa-circle-pause fa-2x text-warning"></i>
        <?php else : ?>
            <i class="fa-solid fa-store-slash fa-2x text-warning"></i>
        <?php endif; ?>
    </div>
    <?php if ($isInactive) : ?>
        <h1 class="h3 fw-semibold mb-2">This store is inactive</h1>
        <p class="text-white-50 mb-4 lh-lg">
            Access to this application for your store is paused because the store has been deactivated.
            To restore access, please contact the application owner.
        </p>
    <?php else : ?>
        <h1 class="h3 fw-semibold mb-2">Store subscription has ended</h1>
        <p class="text-white-50 mb-4 lh-lg">
            Access to this application for your store is paused because the subscription period is over.
            To restore access, please contact the application owner.
        </p>
    <?php endif; ?>
    <?php if ($email !== '') : ?>
        <p class="small text-white-50 mb-2">You can reach them at:</p>
        <a class="btn btn-outline-light btn-sm mb-4" href="mailto:<?= e($email) ?>">
            <i class="fa-regular fa-envelope me-2"></i><?= e($email) ?>
        </a>
    <?php else : ?>
        <p class="small text-white-50 mb-4">Your administrator will provide contact details for renewal.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(route('logout')) ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-light px-4">
            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Log out
        </button>
    </form>
</div>
