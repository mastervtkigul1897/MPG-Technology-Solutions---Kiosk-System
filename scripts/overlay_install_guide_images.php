<?php

declare(strict_types=1);

/**
 * Install guide PNG helpers (public/images/install-guide/).
 *
 * Add bottom bar (URL + Chrome icon on Android):
 *   php scripts/overlay_install_guide_images.php [optional-url]
 *   php scripts/overlay_install_guide_images.php --only=android-02-chrome-address-bar.png
 *
 * Remove bottom bar (crop last N pixels — default 64, matches overlay height):
 *   php scripts/overlay_install_guide_images.php --strip
 *   php scripts/overlay_install_guide_images.php --strip --pixels=128   (e.g. if bar was applied twice)
 *   php scripts/overlay_install_guide_images.php --strip --only=ios-01-safari-open-website.png
 *
 * Run overlay once per PNG; running overlay again stacks another bar.
 */

$root = dirname(__DIR__);
$dir = $root . '/public/images/install-guide/';
$urlText = 'https://lms.mpgtechnologysolutions.com';
$only = null;
$strip = false;
$stripPixels = 64;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = array_values(array_filter(array_map('trim', explode(',', substr($arg, 7)))));
    } elseif (str_starts_with($arg, '--pixels=')) {
        $stripPixels = max(1, (int) substr($arg, 9));
    } elseif ($arg === '--strip' || $arg === '--remove-bar') {
        $strip = true;
    } elseif ($arg !== '' && ! str_starts_with($arg, '--')) {
        $urlText = $arg;
    }
}
$chromePath = $dir . 'chrome-logo-official.png';
$fontCandidates = [
    $root . '/public/vendor/fonts/manrope/manrope-600.ttf',
    $root . '/public/vendor/fonts/manrope/manrope-500.ttf',
];
$font = null;
foreach ($fontCandidates as $f) {
    if (is_readable($f)) {
        $font = $f;
        break;
    }
}

$barH = 64;
$logoSize = 40;
$pad = 16;

$allFiles = [
    'android-01-play-store-chrome.png',
    'android-01-chrome-already-on-phone.png',
    'android-02-chrome-address-bar.png',
    'android-03-chrome-menu-install.png',
    'ios-01-safari-open-website.png',
    'ios-02-safari-share-button.png',
    'ios-03-safari-add-to-home-screen.png',
];
$files = ($only !== null && $only !== []) ? $only : $allFiles;

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required.\n");
    exit(1);
}

if ($strip) {
    foreach ($files as $name) {
        $path = $dir . $name;
        if (! is_readable($path)) {
            fwrite(STDERR, "Skip (missing): {$name}\n");
            continue;
        }
        $src = @imagecreatefrompng($path);
        if ($src === false) {
            fwrite(STDERR, "Skip (not PNG?): {$name}\n");
            continue;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($h <= $stripPixels) {
            fwrite(STDERR, "Skip (too short, h={$h}): {$name}\n");
            imagedestroy($src);
            continue;
        }
        $newH = $h - $stripPixels;
        $out = imagecreatetruecolor($w, $newH);
        imagesavealpha($out, true);
        imagealphablending($out, true);
        imagecopy($out, $src, 0, 0, 0, 0, $w, $newH);
        imagedestroy($src);
        imagesavealpha($out, true);
        imagepng($out, $path, 6);
        imagedestroy($out);
        fwrite(STDOUT, "Stripped bottom {$stripPixels}px: {$name}\n");
    }
    fwrite(STDOUT, "Done.\n");
    exit(0);
}

if (! is_readable($chromePath)) {
    fwrite(STDERR, "Missing Chrome logo at: {$chromePath}\nDownload favicon-96x96.png from Google Chrome static to that path.\n");
    exit(1);
}

foreach ($files as $name) {
    $path = $dir . $name;
    if (! is_readable($path)) {
        fwrite(STDERR, "Skip (missing): {$name}\n");
        continue;
    }

    $src = @imagecreatefrompng($path);
    if ($src === false) {
        fwrite(STDERR, "Skip (not PNG?): {$name}\n");
        continue;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $isAndroid = str_starts_with($name, 'android-');

    $out = imagecreatetruecolor($w, $h + $barH);
    imagesavealpha($out, true);
    imagealphablending($out, true);
    imagecopy($out, $src, 0, 0, 0, 0, $w, $h);

    $barBg = imagecolorallocate($out, 15, 23, 42);
    imagefilledrectangle($out, 0, $h, $w - 1, $h + $barH - 1, $barBg);
    imagedestroy($src);

    $textColor = imagecolorallocate($out, 248, 250, 252);
    $textX = $pad;

    if ($isAndroid) {
        $chrome = @imagecreatefrompng($chromePath);
        if ($chrome !== false) {
            imagesavealpha($chrome, true);
            imagealphablending($chrome, true);
            $scaled = imagescale($chrome, $logoSize, $logoSize, IMG_BILINEAR_FIXED);
            imagedestroy($chrome);
            if ($scaled !== false) {
                $ly = $h + (int) (($barH - $logoSize) / 2);
                imagecopy($out, $scaled, $pad, $ly, 0, 0, $logoSize, $logoSize);
                imagedestroy($scaled);
                $textX = $pad + $logoSize + 12;
            }
        }
    }

    $fontSize = 15;
    $baselineY = $h + $barH - 20;

    if ($font !== null && function_exists('imagettftext')) {
        imagettftext($out, $fontSize, 0, $textX, $baselineY, $textColor, $font, $urlText);
    } else {
        $textY = $h + 8;
        imagestring($out, 4, $textX, $textY, $urlText, $textColor);
    }

    imagesavealpha($out, true);
    imagepng($out, $path, 6);
    imagedestroy($out);

    fwrite(STDOUT, "Updated {$name}\n");
}

fwrite(STDOUT, "Done.\n");
