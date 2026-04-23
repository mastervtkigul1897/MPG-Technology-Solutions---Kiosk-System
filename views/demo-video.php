<?php
$appName = \App\Core\App::config('name') ?? 'Laundry System';
$brandLogoPath = url('images/branding/mpglms-logo.png');
$homeUrl = url('/');
$facebookReelUrl = 'https://www.facebook.com/reel/1692474531743789';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> | Demo Video</title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e($brandLogoPath) ?>">
    <link href="<?= e(url('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('vendor/fonts/manrope/manrope.css')) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('vendor/fontawesome/css/all.min.css')) ?>">
    <style>
        body { font-family: Manrope, Arial, sans-serif; background: #f8fafc; color: #1f2937; }
        .demo-wrap { max-width: 980px; margin: 0 auto; }
        .demo-frame-wrap {
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 0.6rem 1.6rem rgba(2, 6, 23, 0.08);
            padding: 0.75rem;
        }
        .demo-frame {
            width: 100%;
            min-height: 540px;
            border: 0;
            border-radius: 12px;
            overflow: hidden;
        }
        @media (max-width: 768px) {
            .demo-frame { min-height: 480px; }
        }
    </style>
</head>
<body>
<main class="container py-4 py-md-5">
    <div class="demo-wrap">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h1 class="h3 mb-1">Laundry System Demo Video</h1>
                <p class="text-muted mb-0">Watch how the system works in real shop flow.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e($facebookReelUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                    <i class="fa-brands fa-facebook me-1"></i>Open on Facebook
                </a>
                <a href="<?= e($homeUrl) ?>" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i>Back to Home
                </a>
            </div>
        </div>
        <div class="demo-frame-wrap">
            <iframe
                class="demo-frame"
                src="https://www.facebook.com/plugins/video.php?height=314&href=https%3A%2F%2Fwww.facebook.com%2Freel%2F1692474531743789%2F&show_text=true&width=560&t=0"
                scrolling="no"
                frameborder="0"
                allowfullscreen="true"
                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">
            </iframe>
        </div>
    </div>
</main>
</body>
</html>
