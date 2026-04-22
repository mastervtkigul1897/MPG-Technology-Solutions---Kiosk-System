<?php
/** @var string $title */
/** @var string $content */
$appName = \App\Core\App::config('name') ?? 'Laundry System';
$brandSuffix = trim((string) (\App\Core\App::config('brand_suffix') ?? 'MPG Technology Solutions'));
$subTitleParts = [];
if (isset($title) && (string) $title !== '') {
    $subTitleParts[] = (string) $title;
}
$subTitleParts[] = $appName;
if ($brandSuffix !== '') {
    $subTitleParts[] = $brandSuffix;
}
$brandLogoPath = url('images/branding/mpglms-logo.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(implode(' — ', $subTitleParts)) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e($brandLogoPath) ?>">
    <link rel="shortcut icon" href="<?= e($brandLogoPath) ?>">
    <link rel="apple-touch-icon" href="<?= e($brandLogoPath) ?>">
    <link href="<?= e(url('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('vendor/fontawesome/css/all.min.css')) ?>">
    <style>
        .subscription-shell {
            min-height: 100vh;
            background: linear-gradient(155deg, #0c1222 0%, #1a2744 42%, #243b55 100%);
        }
        .subscription-glow {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 179, 237, 0.25), transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 100%, rgba(167, 139, 250, 0.12), transparent 50%);
        }
        .subscription-card {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(14px);
            border-radius: 1.25rem;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
        }
        .subscription-icon-wrap {
            width: 4.5rem;
            height: 4.5rem;
            border-radius: 1rem;
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.25), rgba(249, 115, 22, 0.2));
            border: 1px solid rgba(251, 191, 36, 0.35);
        }
        .subscription-shell .btn {
            cursor: pointer;
            touch-action: manipulation;
            -webkit-tap-highlight-color: rgba(251, 191, 36, 0.25);
        }
        @media (max-width: 767.98px) {
            .subscription-shell .btn { min-height: 2.75rem; }
        }
    </style>
</head>
<body class="subscription-shell text-white d-flex flex-column min-vh-100">
<div class="subscription-glow"></div>
<main class="position-relative flex-grow-1 d-flex align-items-center justify-content-center px-3 py-5">
    <div class="w-100" style="max-width: 32rem;">
        <div class="d-flex justify-content-center mb-3">
            <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="64" height="64" class="rounded-circle border border-light border-opacity-25">
        </div>
        <?= $content ?? '' ?>
    </div>
</main>
<?php
$footerCreditClass = 'text-white-50 border-secondary border-opacity-25 mt-auto pt-3 border-top';
require dirname(__DIR__).'/partials/footer_credit.php';
?>
<script src="<?= e(url('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
