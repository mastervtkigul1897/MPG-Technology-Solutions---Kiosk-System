<?php
/** @var string $appOwnerEmail */
/** @var string $reason */
$email = trim((string) ($appOwnerEmail ?? ''));
?>
<div class="subscription-card p-4 p-md-5 text-center">
    <div class="d-inline-flex subscription-icon-wrap align-items-center justify-content-center mb-4">
        <i class="fa-solid fa-circle-pause fa-2x text-warning"></i>
    </div>
    <h1 class="h3 fw-semibold mb-2">This store is inactive</h1>
    <p class="text-white-50 mb-4 lh-lg">
        Access to this application for your store is paused because the store has been deactivated.
        To restore access, please contact the application owner.
    </p>
    <?php if ($email !== '') : ?>
        <p class="small text-white-50 mb-2">You can reach them at:</p>
        <a class="btn btn-outline-light btn-sm mb-4" href="mailto:<?= e($email) ?>">
            <i class="fa-regular fa-envelope me-2"></i><?= e($email) ?>
        </a>
    <?php else : ?>
        <p class="small text-white-50 mb-4">Your administrator will provide contact details for account support.</p>
    <?php endif; ?>
    <p class="small text-white-50 mb-2">For account support, contact us on Facebook:</p>
    <a class="btn btn-outline-light btn-sm mb-4" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/mpgtechnologysolutionscom/">
        <i class="fa-brands fa-facebook me-2"></i>MPG Technology Solutions
    </a>
    <form method="post" action="<?= e(route('logout')) ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-light px-4">
            <i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Log out
        </button>
    </form>
</div>
