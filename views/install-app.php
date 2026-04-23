<?php
$appName = \App\Core\App::config('name') ?? 'Laundry System';
$brandLogoPath = url('images/branding/mpglms-logo.png');
$brandName = (string) $appName;
$facebookUrl = 'https://www.facebook.com/mpgtechnologysolutionscom';
/** Live web app URL shown in this guide (typing on phone / bookmarks). Not tied to APP_URL. */
$publicAppUrl = 'https://lms.mpgtechnologysolutions.com';
$publicLoginUrl = $publicAppUrl . '/login';

$rawLang = strtolower((string) ($_GET['lang'] ?? ''));
$tutorialLang = $rawLang === 'en' ? 'en' : 'fil';
$htmlLang = $tutorialLang === 'en' ? 'en' : 'fil';
$installAppPath = url('/install-app');
$tutorialUrlFil = $installAppPath;
$tutorialUrlEn = $installAppPath . (str_contains($installAppPath, '?') ? '&' : '?') . 'lang=en';
$tutorialSelfUrl = $tutorialLang === 'en' ? $tutorialUrlEn : $tutorialUrlFil;

$I = [
    'fil' => [
        'page_title' => 'I-install ang App',
        'nav_aria' => 'Buksan ang menu',
        'nav_home' => 'Home',
        'nav_pricing' => 'Presyo',
        'nav_install' => 'I-install ang App',
        'nav_login' => 'Login',
        'nav_contact' => 'Makipag-ugnayan',
        'lang_label' => 'Wika ng gabay',
        'lang_option_fil' => 'Filipino',
        'lang_option_en' => 'English',
        'badge_staff' => 'Pagsasaayos ng staff',
        'hero_title' => 'I-install ang App — Chrome (Android) at Safari (iOS)',
        'hero_lead' => 'Sa Android, minsan mo lang kailanganin ang Chrome para unang mabuksan ang shop, tapos idagdag sa Home screen (Add to Home screen) para diretso na mula sa icon. Minsan lumalabas din ang Install app sa ibang site — dito sa LMS karaniwang Add to Home screen lang. Sa iOS, Safari at Add to Home Screen.',
        'android_alert_title' => 'Android — shortcut sa Home screen (hindi araw-araw na Chrome + URL)',
        'android_alert_p1' => 'Layunin: magdagdag ng shortcut sa Home screen (sa Chrome: Add to Home screen) para mabuksan ang shop nang diretso. Karamihan ng phone ay may Chrome; gamitin pansamantala para unang makapunta sa site.',
        'ios_alert_title' => 'iOS — Safari sa pag-setup',
        'ios_alert_p1' => 'Sa iOS (iPhone o iPad), gamitin ang Safari sa tutorial sa ibaba (mas maaasahan ang Add to Home Screen sa Safari).',
        'ios_alert_p2' => 'Bluetooth / thermal receipt printing sa iOS: Madalas hindi gumagana sa Safari. Kung kailangan mong mag-print sa Bluetooth thermal printer mula sa iOS device, i-install ang Google Chrome mula sa App Store, buksan ang parehong shop link sa Chrome, mag-sign in, at doon gawin ang print. Kung hindi pa rin gumana, gumamit ng Android device o humingi ng ibang opsyon sa may-ari.',
        'android_card_title' => 'Android — Chrome (unang bukas) → Add to Home screen',
        'd1_title' => 'Hanapin at buksan ang Google Chrome',
        'd1_body' => 'Kung wala pa ang Chrome sa phone, i-install mula sa Play Store (larawan sa kaliwa). Kung naka-install na, hanapin ang Chrome icon sa app drawer o sa listahan ng apps at buksan (larawan sa kanan).',
        'd1_or_label' => 'OR',
        'd2_title' => 'Unang buksan ang shop sa Chrome (minsan isang beses lang)',
        'd2_body_pre' => 'Buksan ang Chrome. I-tap ang address bar, ilagay ang ',
        'd2_body_post' => ' o ang link na ipinadala ng may-ari, tapos Go. Hintayin mag-load ang shop (mag-sign in kung humihingi ang page). Ang hakbang na ito ay para lang makita ang site sa Chrome bago mo i-install sa Home screen.',
        'd3_title' => 'Add to Home screen mula sa menu ng Chrome',
        'd3_body' => 'Habang bukas ang shop sa Chrome, i-tap ang ⋮ (tatlong tuldok) sa kanang itaas. Sa menu, i-tap ang Add to Home screen / Idagdag sa Home screen (makikita sa halos lahat ng bersyon). Ang Install app ay hiwalay na opsyon na minsan wala sa menu — lumalabas lang iyon kung sinusuportahan ng site at ng Chrome bilang PWA; kung wala, normal iyon at gamitin ang Add to Home screen. Lalabas ang icon sa Home screen: doon mo na bubuksan ang shop araw-araw.',
        'd4_title' => 'Buksan mula sa icon sa Home screen',
        'd4_body_pre' => 'I-tap ang bagong icon ng shop. Kung kailangan ng login, gamitin ang ',
        'd4_body_link' => 'Login',
        'd4_body_post' => ' at ang username/password mula sa may-ari. Gagamitin mo na ang shortcut; hindi mo na kailangan buksan ang Chrome at mag-type ng address araw-araw.',
        'ios_card_title' => 'iOS — Safari',
        'ios_intro' => 'Gamitin ang Safari (karaniwang browser sa iOS — asul na compass icon). Karaniwang paraan ito para idagdag ang shop sa Home Screen sa iPhone o iPad.',
        'i1_title' => 'Buksan ang Safari at pumunta sa shop',
        'i1_body_pre' => 'Buksan ang Safari. I-tap ang address bar, ilagay ang ',
        'i1_body_post' => ' (o i-paste ang link mula sa may-ari), tapos Go. Hintayin mag-load ang login o shop page.',
        'i2_title' => 'I-tap ang Share',
        'i2_body' => 'I-tap ang Share icon (parisukat na may palabas na arrow), karaniwan sa gitna-ibaba o taas ng screen sa iOS (maaaring magkaiba ang posisyon sa iPhone at iPad).',
        'i3_title' => 'Add to Home Screen',
        'i3_body' => 'I-scroll ang share sheet at i-tap ang Add to Home Screen. Maaari mong baguhin ang pangalan, tapos Add. Lalabas ang shortcut sa Home Screen.',
        'i4_title' => 'Buksan ang shortcut at mag-sign in',
        'i4_body_pre' => 'Mula sa Home Screen, i-tap ang bagong icon para buksan ang shop sa Safari. Pumunta sa ',
        'i4_body_link' => 'Login',
        'i4_body_post' => ' at ilagay ang credentials mula sa may-ari.',
        'ios_ble_note' => 'Paalala (thermal / Bluetooth print sa iOS): Kung kailangan mo ng ganitong printing, maaaring hindi suportado ng Safari. I-install ang Google Chrome, buksan ang parehong website sa Chrome, mag-sign in, at subukan doon ang print. Tatanungin sa may-ari kung aling device ang suportado.',
        'ph_title' => 'Lugar para sa screenshot',
        'ph_add' => 'Magdagdag ng larawan:',
        'shot_a01_play' => 'Play Store — i-install ang Google Chrome (kung wala pa)',
        'shot_a01_drawer' => 'App drawer — Chrome ay naka-install na (hanapin at buksan)',
        'shot_a02' => 'Chrome — https://lms.mpgtechnologysolutions.com sa address bar; pahina: Laundry Management System',
        'shot_a03' => 'Chrome menu — Add to Home screen (Install app ay maaaring wala)',
        'shot_i01' => 'Safari — https://lms.mpgtechnologysolutions.com sa address bar; pahina: Laundry Management System',
        'shot_i02' => 'Safari — Share button (Laundry Management System page)',
        'shot_i03' => 'Share sheet — Add to Home Screen',
    ],
    'en' => [
        'page_title' => 'Install App',
        'nav_aria' => 'Toggle navigation',
        'nav_home' => 'Home',
        'nav_pricing' => 'Pricing',
        'nav_install' => 'Install App',
        'nav_login' => 'Login',
        'nav_contact' => 'Contact us',
        'lang_label' => 'Guide language',
        'lang_option_fil' => 'Filipino',
        'lang_option_en' => 'English',
        'badge_staff' => 'Staff setup',
        'hero_title' => 'Install App — Chrome (Android) & Safari (iOS)',
        'hero_lead' => 'On Android, open Chrome only the first time to reach the shop, then use Add to Home screen so you launch from an icon. Install app may appear on some sites but often not on this LMS — Add to Home screen is enough. On iOS, use Safari and Add to Home Screen.',
        'android_alert_title' => 'Android — Home screen shortcut (not Chrome + typing the URL every day)',
        'android_alert_p1' => 'Goal: add a shortcut to your Home screen (in Chrome: Add to Home screen) so you open the shop directly. Most phones already have Chrome; use it temporarily for the first visit.',
        'ios_alert_title' => 'iOS — Safari for setup',
        'ios_alert_p1' => 'On iOS (iPhone or iPad), use Safari for the tutorial below (Add to Home Screen works reliably in Safari).',
        'ios_alert_p2' => 'Bluetooth / thermal receipt printing on iOS: It often does not work in Safari. If you must print to a Bluetooth thermal printer from an iOS device, install Google Chrome from the App Store, open the same shop link in Chrome, sign in, and run the print action from there. If it still fails, use an Android device or ask your owner for another print option.',
        'android_card_title' => 'Android — Chrome (first open) → Add to Home screen',
        'd1_title' => 'Find and open Google Chrome',
        'd1_body' => 'If Chrome is not on the phone yet, install it from the Play Store (left image). If it is already installed, find the Chrome icon in your app drawer or app list and open it (right image).',
        'd1_or_label' => 'OR',
        'd2_title' => 'Open the shop in Chrome once (first time)',
        'd2_body_pre' => 'Open Chrome. Tap the address bar, enter ',
        'd2_body_post' => ' or the link your owner sent, then tap Go. Wait for the shop to load (sign in if the page asks). This step is only to view the site in Chrome before you install the shortcut to your Home screen.',
        'd3_title' => 'Add to Home screen from the Chrome menu',
        'd3_body' => 'While the shop is open in Chrome, tap ⋮ (three dots) on the top right. In the menu, tap Add to Home screen (shown on most versions). Install app is a separate option that is often missing — it only appears when the site and Chrome support an installable PWA; if you do not see it, that is normal — use Add to Home screen. You will get a Home screen icon and open the shop from there day to day.',
        'd4_title' => 'Open from the Home screen icon',
        'd4_body_pre' => 'Tap your new shop icon. If you need to sign in, use ',
        'd4_body_link' => 'Login',
        'd4_body_post' => ' and the username and password from your owner. From then on, use the shortcut — you do not need to open Chrome and type the URL every day.',
        'ios_card_title' => 'iOS — Safari',
        'ios_intro' => 'Use Safari (the default browser on iOS — blue compass icon). This is the standard way to add the shop to your Home Screen on iPhone or iPad.',
        'i1_title' => 'Open Safari and go to your shop',
        'i1_body_pre' => 'Open Safari. Tap the address bar, enter ',
        'i1_body_post' => ' (or paste the link from your owner), then tap Go. Wait until the login or shop page loads.',
        'i2_title' => 'Tap the Share button',
        'i2_body' => 'Tap the Share icon (square with an arrow pointing up), usually at the bottom center or top of the screen on iOS (position may differ on iPhone vs iPad).',
        'i3_title' => 'Add to Home Screen',
        'i3_body' => 'Scroll the share sheet and tap Add to Home Screen. You can edit the name if you want, then tap Add. A shortcut icon will appear on your Home Screen.',
        'i4_title' => 'Open the shortcut and sign in',
        'i4_body_pre' => 'From your Home Screen, tap the new icon to open the shop in Safari. Go to ',
        'i4_body_link' => 'Login',
        'i4_body_post' => ' and enter the credentials your owner gave you.',
        'ios_ble_note' => 'Reminder (thermal / Bluetooth print on iOS): If you need this kind of printing, Safari may not support it. Install Google Chrome, open this same website in Chrome, sign in, and try printing from there. Your owner can confirm which devices are supported.',
        'ph_title' => 'Screenshot placeholder',
        'ph_add' => 'Add image:',
        'shot_a01_play' => 'Play Store — install Google Chrome (if missing)',
        'shot_a01_drawer' => 'App drawer — Chrome already installed (find and open)',
        'shot_a02' => 'Chrome — https://lms.mpgtechnologysolutions.com in address bar; page: Laundry Management System',
        'shot_a03' => 'Chrome menu — Add to Home screen (Install app may be absent)',
        'shot_i01' => 'Safari — https://lms.mpgtechnologysolutions.com in address bar; page: Laundry Management System',
        'shot_i02' => 'Safari — Share button (Laundry Management System page)',
        'shot_i03' => 'Share sheet — Add to Home Screen',
    ],
];

$L = $I[$tutorialLang];

$guideShot = static function (string $filename, string $altFil, string $altEn) use ($tutorialLang): array {
    $alt = $tutorialLang === 'en' ? $altEn : $altFil;
    $full = BASE_PATH . '/public/images/install-guide/' . $filename;
    $u = is_file($full) ? url('images/install-guide/' . $filename) : null;

    return ['src' => $u, 'file' => $filename, 'alt' => $alt];
};

$renderShot = static function (array $shot) use ($L): string {
    if ($shot['src']) {
        return '<figure class="mb-0 text-center">'
            . '<img src="' . e($shot['src']) . '" class="img-fluid rounded border shadow-sm install-guide-shot" alt="' . e($shot['alt']) . '">'
            . '<figcaption class="small text-muted mt-2">' . e($shot['alt']) . '</figcaption>'
            . '</figure>';
    }

    return '<div class="install-shot-placeholder rounded-3 border border-2 border-dashed bg-body-secondary p-4 text-center text-muted">'
        . '<div class="fw-semibold mb-1"><i class="fa-regular fa-image me-1"></i>' . e($L['ph_title']) . '</div>'
        . '<div class="small">' . e($L['ph_add']) . ' <code class="user-select-all">' . e($shot['file']) . '</code></div>'
        . '<div class="small mt-1">' . e($shot['alt']) . '</div>'
        . '</div>';
};
?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($L['page_title']) ?> | <?= e($appName) ?></title>
    <link rel="icon" type="image/png" sizes="512x512" href="<?= e($brandLogoPath) ?>">
    <link rel="shortcut icon" href="<?= e($brandLogoPath) ?>">
    <link rel="apple-touch-icon" href="<?= e($brandLogoPath) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.json')) ?>">
    <meta name="theme-color" content="#2563eb">
    <link href="<?= e(url('vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('vendor/fonts/manrope/manrope.css')) ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('vendor/fontawesome/css/all.min.css')) ?>">
    <style>
        body { font-family: Manrope, Arial, sans-serif; background: #f7fafc; color: #1f2937; }
        .hero-bg { background: linear-gradient(150deg, #0f172a 0%, #1e3a8a 55%, #2563eb 100%); }
        .step-num { width: 2.25rem; height: 2.25rem; border-radius: 50%; background: #2563eb; color: #fff; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .install-shot-placeholder { min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .install-guide-shot {
            display: block;
            margin-inline: auto;
            width: auto;
            max-width: 100%;
            max-height: min(78vh, 680px);
            height: auto;
            object-fit: contain;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('/')) ?>">
            <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="34" height="34" class="rounded-circle border">
            <span class="fw-semibold"><?= e($brandName) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingMenu" aria-controls="landingMenu" aria-expanded="false" aria-label="<?= e($L['nav_aria']) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="landingMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/')) ?>"><?= e($L['nav_home']) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/pricing')) ?>"><?= e($L['nav_pricing']) ?></a></li>
                <li class="nav-item"><a class="nav-link active" href="<?= e($tutorialSelfUrl) ?>" aria-current="page"><?= e($L['nav_install']) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/login')) ?>"><?= e($L['nav_login']) ?></a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($L['nav_contact']) ?></a></li>
                <li class="nav-item ps-lg-2">
                    <label class="visually-hidden" for="installLangSelect"><?= e($L['lang_label']) ?></label>
                    <select id="installLangSelect" class="form-select form-select-sm" style="max-width: 11rem;" title="<?= e($L['lang_label']) ?>">
                        <option value="fil" <?= $tutorialLang === 'fil' ? ' selected' : '' ?>><?= e($L['lang_option_fil']) ?></option>
                        <option value="en" <?= $tutorialLang === 'en' ? ' selected' : '' ?>><?= e($L['lang_option_en']) ?></option>
                    </select>
                </li>
            </ul>
        </div>
    </div>
</nav>

<header class="hero-bg text-white py-5">
    <div class="container py-2">
        <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-md-between gap-3 mb-2">
            <span class="badge text-bg-light text-dark"><?= e($L['badge_staff']) ?></span>
            <div class="d-md-none">
                <label class="form-label small text-white-50 mb-1" for="installLangSelectHero"><?= e($L['lang_label']) ?></label>
                <select id="installLangSelectHero" class="form-select form-select-sm" style="max-width: 12rem;">
                    <option value="fil" <?= $tutorialLang === 'fil' ? ' selected' : '' ?>><?= e($L['lang_option_fil']) ?></option>
                    <option value="en" <?= $tutorialLang === 'en' ? ' selected' : '' ?>><?= e($L['lang_option_en']) ?></option>
                </select>
            </div>
        </div>
        <h1 class="display-6 fw-bold mb-2"><?= e($L['hero_title']) ?></h1>
        <p class="lead mb-0 opacity-90"><?= e($L['hero_lead']) ?></p>
    </div>
</header>

<main class="py-5">
    <div class="container col-lg-10 col-xl-9">

        <div class="alert alert-info border-0 shadow-sm mb-3">
            <div class="fw-semibold mb-1"><i class="fa-brands fa-chrome me-1"></i><?= e($L['android_alert_title']) ?></div>
            <p class="small mb-0"><?= e($L['android_alert_p1']) ?></p>
        </div>

        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <div class="fw-semibold mb-1"><i class="fa-brands fa-apple me-1"></i><?= e($L['ios_alert_title']) ?></div>
            <p class="small mb-2"><?= e($L['ios_alert_p1']) ?></p>
            <p class="small mb-0"><?= e($L['ios_alert_p2']) ?></p>
        </div>

        <div class="card border-0 shadow-sm mb-5">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0"><i class="fa-brands fa-android text-success me-2"></i><?= e($L['android_card_title']) ?></h2>
            </div>
            <div class="card-body">
                <ol class="list-unstyled mb-0">
                    <li class="d-flex gap-3 mb-4 pb-4 border-bottom">
                        <span class="step-num">1</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['d1_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['d1_body']) ?></p>
                            <div class="row g-2 g-md-3 align-items-center justify-content-center">
                                <div class="col-12 col-md-5 text-center">
                                    <?= $renderShot($guideShot('android-01-play-store-chrome.png', $I['fil']['shot_a01_play'], $I['en']['shot_a01_play'])) ?>
                                </div>
                                <div class="col-12 col-md-auto align-self-center py-2 py-md-0">
                                    <span class="d-inline-block rounded-pill bg-body-secondary text-secondary fw-bold px-3 py-2 small text-uppercase"><?= e($L['d1_or_label']) ?></span>
                                </div>
                                <div class="col-12 col-md-5 text-center">
                                    <?= $renderShot($guideShot('android-01-chrome-already-on-phone.png', $I['fil']['shot_a01_drawer'], $I['en']['shot_a01_drawer'])) ?>
                                </div>
                            </div>
                        </div>
                    </li>
                    <li class="d-flex gap-3 mb-4 pb-4 border-bottom">
                        <span class="step-num">2</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['d2_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['d2_body_pre']) ?><code class="user-select-all"><?= e($publicAppUrl) ?></code><?= e($L['d2_body_post']) ?></p>
                            <?= $renderShot($guideShot('android-02-chrome-address-bar.png', $I['fil']['shot_a02'], $I['en']['shot_a02'])) ?>
                        </div>
                    </li>
                    <li class="d-flex gap-3 mb-4 pb-4 border-bottom">
                        <span class="step-num">3</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['d3_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['d3_body']) ?></p>
                            <?= $renderShot($guideShot('android-03-chrome-menu-install.png', $I['fil']['shot_a03'], $I['en']['shot_a03'])) ?>
                        </div>
                    </li>
                    <li class="d-flex gap-3">
                        <span class="step-num">4</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['d4_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['d4_body_pre']) ?><a href="<?= e($publicLoginUrl) ?>"><?= e($L['d4_body_link']) ?></a><?= e($L['d4_body_post']) ?></p>
                        </div>
                    </li>
                </ol>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-5">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0"><i class="fa-brands fa-apple text-secondary me-2"></i><?= e($L['ios_card_title']) ?></h2>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-4"><?= e($L['ios_intro']) ?></p>
                <ol class="list-unstyled mb-0">
                    <li class="d-flex gap-3 mb-4 pb-4 border-bottom">
                        <span class="step-num">1</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['i1_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['i1_body_pre']) ?><code class="user-select-all"><?= e($publicAppUrl) ?></code><?= e($L['i1_body_post']) ?></p>
                            <?= $renderShot($guideShot('ios-01-safari-open-website.png', $I['fil']['shot_i01'], $I['en']['shot_i01'])) ?>
                        </div>
                    </li>
                    <li class="d-flex gap-3 mb-4 pb-4 border-bottom">
                        <span class="step-num">2</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['i2_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['i2_body']) ?></p>
                            <?= $renderShot($guideShot('ios-02-safari-share-button.png', $I['fil']['shot_i02'], $I['en']['shot_i02'])) ?>
                        </div>
                    </li>
                    <li class="d-flex gap-3 mb-4 pb-4 border-bottom">
                        <span class="step-num">3</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['i3_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['i3_body']) ?></p>
                            <?= $renderShot($guideShot('ios-03-safari-add-to-home-screen.png', $I['fil']['shot_i03'], $I['en']['shot_i03'])) ?>
                        </div>
                    </li>
                    <li class="d-flex gap-3">
                        <span class="step-num">4</span>
                        <div class="flex-grow-1">
                            <h3 class="h6"><?= e($L['i4_title']) ?></h3>
                            <p class="small text-muted mb-2"><?= e($L['i4_body_pre']) ?><a href="<?= e($publicLoginUrl) ?>"><?= e($L['i4_body_link']) ?></a><?= e($L['i4_body_post']) ?></p>
                        </div>
                    </li>
                </ol>
                <div class="alert alert-light border small mb-0 mt-4">
                    <?= e($L['ios_ble_note']) ?>
                </div>
            </div>
        </div>
    </div>
</main>

<footer class="py-4 border-top bg-white mt-5">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small text-muted">
        <span>&copy; <?= date('Y') ?> <?= e($brandName) ?></span>
        <span><a href="<?= e(url('/')) ?>" class="text-muted"><?= e($L['nav_home']) ?></a> · <a href="<?= e($tutorialSelfUrl) ?>" class="text-muted"><?= e($L['nav_install']) ?></a></span>
    </div>
</footer>

<script src="<?= e(url('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script>
(function () {
    function go(lang) {
        if (lang === 'en') {
            window.location.href = <?= json_encode($tutorialUrlEn, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        } else {
            window.location.href = <?= json_encode($tutorialUrlFil, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        }
    }
    var navSel = document.getElementById('installLangSelect');
    var heroSel = document.getElementById('installLangSelectHero');
    if (navSel) {
        navSel.addEventListener('change', function () { go(this.value); });
    }
    if (heroSel) {
        heroSel.addEventListener('change', function () { go(this.value); });
    }
})();
</script>
</body>
</html>
