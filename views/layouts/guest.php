<?php
/** @var string $title */
/** @var string $content */
$appName = \App\Core\App::config('name') ?? 'Kiosk System';
$brandSuffix = trim((string) (\App\Core\App::config('brand_suffix') ?? 'MPG Technology Solutions'));
$guestTitleParts = [$appName];
if ($brandSuffix !== '') {
    $guestTitleParts[] = $brandSuffix;
}
$brandLogoPath = url('images/branding/mpg-kis-logo.png');
$brandDisplayName = 'MPG KIS - Kiosk & Inventory System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(implode(' — ', $guestTitleParts)) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e($brandLogoPath) ?>">
    <link rel="shortcut icon" href="<?= e($brandLogoPath) ?>">
    <link rel="apple-touch-icon" href="<?= e($brandLogoPath) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(url('css/app-theme.css')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="app-theme bg-light d-flex flex-column min-vh-100">
<div class="flex-grow-1 d-flex align-items-center py-5">
    <div class="container w-100">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="42" height="42" class="rounded-circle border">
                            <div class="fw-semibold"><?= e($brandDisplayName) ?></div>
                        </div>
                        <h4 class="mb-1"><?= e($title ?? '') ?></h4>
                        <p class="modern-page-note mb-3">Advanced kiosk POS and inventory in one platform—scale-ready controls, clearer visibility at the register, and checkout built for busy, growing stores.</p>
                        <?php require dirname(__DIR__).'/partials/alerts.php'; ?>
                        <?= $content ?? '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$footerCreditClass = 'text-muted mt-auto pt-3 border-top';
require dirname(__DIR__).'/partials/footer_credit.php';
?>
<script>
(() => {
    if (typeof Swal !== 'undefined' && !window.__swalAutoCloseWrapped) {
        const nativeFire = Swal.fire.bind(Swal);
        Swal.fire = function (options = {}, ...rest) {
            const config = typeof options === 'object' && options !== null ? { ...options } : options;
            if (typeof config === 'object' && !config.showCancelButton && config.timer == null) {
                config.timer = 5000;
                config.timerProgressBar = true;
            }
            return nativeFire(config, ...rest);
        };
        window.__swalAutoCloseWrapped = true;
    }
})();
</script>
</body>
</html>
