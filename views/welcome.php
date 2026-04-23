<?php
$appName = \App\Core\App::config('name') ?? 'Laundry System';
$brandLogoPath = url('images/branding/mpglms-logo.png');
$brandName = (string) $appName;
$facebookUrl = 'https://www.facebook.com/mpgtechnologysolutionscom';
$demoVideoUrl = url('/demo-video');
$installAppHref = url('/install-app');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> | Laundry Shop Management</title>
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
        .hero-card { background: rgba(255,255,255,0.09); border: 1px solid rgba(255,255,255,0.18); }
        .feature-icon { width: 2.5rem; height: 2.5rem; }
        .pain-item { border-left: 4px solid #ef4444; background: #fff; }
        .solution-item { border-left: 4px solid #10b981; background: #fff; }
        .btn-install-emphasis {
            font-size: 1.125rem;
            font-weight: 700;
            padding: 0.85rem 1.75rem;
            box-shadow: 0 0.35rem 1.25rem rgba(0, 0, 0, 0.2);
        }
        .btn-install-emphasis:hover { box-shadow: 0 0.45rem 1.5rem rgba(0, 0, 0, 0.28); }
        .device-card {
            border: 1px solid rgba(37, 99, 235, 0.18);
            border-radius: 14px;
            background: #ffffff;
            padding: 1rem;
            height: 100%;
            box-shadow: 0 0.45rem 1.2rem rgba(2, 6, 23, 0.06);
        }
        .device-card .icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
            font-size: 1.4rem;
        }
        .os-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid rgba(15, 23, 42, 0.12);
            border-radius: 999px;
            padding: 0.45rem 0.7rem;
            background: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
        }
        a.landing-login-trigger[href="#"] { cursor: pointer; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(url('/')) ?>">
            <img src="<?= e($brandLogoPath) ?>" alt="<?= e($appName) ?> logo" width="34" height="34" class="rounded-circle border">
            <span class="fw-semibold"><?= e($brandName) ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landingMenu" aria-controls="landingMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="landingMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/pricing')) ?>">Pricing</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($demoVideoUrl) ?>">Demo Video</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($installAppHref) ?>">Install App</a></li>
                <li class="nav-item"><a class="nav-link landing-login-trigger" href="#" role="button" data-bs-toggle="modal" data-bs-target="#landingLoginModal">Login</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer">Contact us</a></li>
            </ul>
        </div>
    </div>
</nav>

<header id="home" class="hero-bg text-white py-5 py-lg-6">
    <div class="container py-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge text-bg-light text-dark mb-3">Built for Laundry Shop Owners</span>
                <h1 class="display-5 fw-bold mb-3">Run your laundry business faster, cleaner, and more profitably.</h1>
                <p class="lead mb-4">From walk-in orders and receipts to staff, expenses, inventory, branch operations, and reports — everything is in one modern system.</p>
                <div class="mb-3">
                    <a href="<?= e($installAppHref) ?>" class="btn btn-light btn-install-emphasis text-primary text-center"><span class="d-inline-flex flex-column align-items-center lh-sm"><span><i class="fa-solid fa-mobile-screen-button me-2"></i>Install App</span><span class="small fw-semibold mt-1 opacity-90">One Time Setup</span></span></a>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e($demoVideoUrl) ?>" class="btn btn-info fw-semibold text-white">
                        <i class="fa-brands fa-facebook me-2"></i>Watch Demo Video
                    </a>
                    <a href="<?= e(url('/register')) ?>" class="btn btn-warning fw-semibold"><i class="fa-solid fa-bolt me-1"></i>Start 7-day free trial</a>
                    <a href="<?= e(url('/pricing')) ?>" class="btn btn-outline-light">View pricing</a>
                    <a href="#" class="btn btn-light text-primary landing-login-trigger" role="button" data-bs-toggle="modal" data-bs-target="#landingLoginModal">Login</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-card rounded-4 p-4">
                    <h6 class="text-uppercase small fw-bold mb-3">What owners usually struggle with</h6>
                    <ul class="mb-0 small">
                        <li class="mb-2">Manual tracking causes missing or incorrect transactions.</li>
                        <li class="mb-2">No clear view of daily sales, expenses, and profit.</li>
                        <li class="mb-2">Difficult staff accountability and shift monitoring.</li>
                        <li class="mb-2">Inventory losses due to weak stock visibility.</li>
                        <li>Slow service and inconsistent receipts hurt customer trust.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<main>
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="h3 mb-2">Works on your everyday devices</h2>
                <p class="text-muted mb-0">Use one system across phone, tablet, and desktop for faster laundry operations anywhere.</p>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="device-card">
                        <span class="icon-wrap mb-2"><i class="fa-solid fa-mobile-screen-button"></i></span>
                        <h3 class="h6 mb-1">Phone Ready</h3>
                        <p class="small text-muted mb-0">Ideal for cashier flow and quick order updates while moving around the shop.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="device-card">
                        <span class="icon-wrap mb-2"><i class="fa-solid fa-tablet-screen-button"></i></span>
                        <h3 class="h6 mb-1">Tablet Friendly</h3>
                        <p class="small text-muted mb-0">Comfortable touch UI for front-desk encoding, summary checks, and payment capture.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="device-card">
                        <span class="icon-wrap mb-2"><i class="fa-solid fa-desktop"></i></span>
                        <h3 class="h6 mb-1">Desktop Optimized</h3>
                        <p class="small text-muted mb-0">Great for owners and admins doing reports, branch settings, and full monitoring.</p>
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-5">
                <span class="os-chip"><i class="fa-brands fa-android"></i>Android</span>
                <span class="os-chip"><i class="fa-brands fa-apple"></i>iOS</span>
                <span class="os-chip"><i class="fa-brands fa-windows"></i>Windows</span>
                <span class="os-chip"><i class="fa-brands fa-apple"></i>Mac</span>
                <span class="os-chip"><i class="fa-brands fa-linux"></i>Linux</span>
            </div>
            <div class="row g-4">
                <div class="col-lg-6">
                    <h2 class="h4 mb-3">Pain points in day-to-day operations</h2>
                    <div class="pain-item rounded-3 p-3 mb-2">Orders are handwritten or scattered, causing delays and errors.</div>
                    <div class="pain-item rounded-3 p-3 mb-2">Staff actions are hard to monitor across shifts and branches.</div>
                    <div class="pain-item rounded-3 p-3 mb-2">Expenses and damaged items are not logged consistently.</div>
                    <div class="pain-item rounded-3 p-3">Reports are delayed, so owners decide without clear data.</div>
                </div>
                <div class="col-lg-6">
                    <h2 class="h4 mb-3">How this system solves them</h2>
                    <div class="solution-item rounded-3 p-3 mb-2">Centralized POS and laundry order tracking with receipt support.</div>
                    <div class="solution-item rounded-3 p-3 mb-2">Staff, attendance, and role/module controls built in.</div>
                    <div class="solution-item rounded-3 p-3 mb-2">Expenses, damaged items, and inventory logs in one place.</div>
                    <div class="solution-item rounded-3 p-3">Real-time dashboards and reports for faster owner decisions.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white border-top border-bottom">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="h3 mb-2">Core features for laundry growth</h2>
                <p class="text-muted mb-0">Everything you need from front counter to owner reporting.</p>
            </div>
            <div class="row g-3">
                <?php
                $features = [
                    ['fa-cart-shopping', 'POS & Checkout', 'Fast order processing with cleaner transaction flow.'],
                    ['fa-receipt', 'Receipt Management', 'Printable receipts and transaction editing controls.'],
                    ['fa-users-gear', 'Staff Management', 'Role-based access for owners and staff accounts.'],
                    ['fa-boxes-stacked', 'Inventory Tracking', 'Track items, movement, and low-stock visibility.'],
                    ['fa-file-invoice-dollar', 'Expenses & Damages', 'Log operating costs and damaged items accurately.'],
                    ['fa-chart-line', 'Reports & Dashboard', 'Daily sales, trends, and profit insights at a glance.'],
                    ['fa-code-branch', 'Branch Operations', 'Manage multiple branches from one owner account.'],
                    ['fa-tags', 'Flexible Plans', 'Start free then upgrade when your shop is ready.'],
                ];
                foreach ($features as [$icon, $title, $desc]): ?>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body">
                                <div class="feature-icon rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center mb-3">
                                    <i class="fa-solid <?= e($icon) ?>"></i>
                                </div>
                                <h3 class="h6"><?= e($title) ?></h3>
                                <p class="small text-muted mb-0"><?= e($desc) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="rounded-4 p-4 p-lg-5 bg-dark text-white text-center">
                <h2 class="h3 mb-2">Ready to simplify your laundry operations?</h2>
                <p class="text-white-50 mb-4">Start with a 7-day trial, explore pricing, or sign in to your store. Staff on phones can add the app from the install guide.</p>
                <div class="mb-3 d-flex justify-content-center">
                    <a href="<?= e($installAppHref) ?>" class="btn btn-light btn-install-emphasis text-primary text-center"><span class="d-inline-flex flex-column align-items-center lh-sm"><span><i class="fa-solid fa-mobile-screen-button me-2"></i>Install App</span><span class="small fw-semibold mt-1 opacity-90">One Time Setup</span></span></a>
                </div>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="<?= e($demoVideoUrl) ?>" class="btn btn-info fw-semibold text-white">
                        <i class="fa-brands fa-facebook me-2"></i>Watch Demo Video
                    </a>
                    <a href="<?= e(url('/register')) ?>" class="btn btn-warning fw-semibold">Start free trial</a>
                    <a href="<?= e(url('/pricing')) ?>" class="btn btn-outline-light">Pricing</a>
                    <a href="#" class="btn btn-light text-primary landing-login-trigger" role="button" data-bs-toggle="modal" data-bs-target="#landingLoginModal">Login</a>
                    <a href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary"><i class="fa-brands fa-facebook me-1"></i>Contact us</a>
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="py-4 border-top bg-white">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-2 small text-muted">
        <span>&copy; <?= date('Y') ?> <?= e($brandName) ?></span>
        <span>Built for modern laundry shop operations.</span>
    </div>
</footer>

<div class="modal fade" id="landingLoginModal" tabindex="-1" aria-labelledby="landingLoginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="landingLoginModalLabel">Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="landingLoginErrors" class="alert alert-danger d-none py-2 small" role="alert"></div>
                <form id="landingLoginForm" method="POST" action="<?= e(url('/login')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="landing_login_email">Email</label>
                        <input type="email" class="form-control" id="landing_login_email" name="email" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="landing_login_password">Password</label>
                        <input type="password" class="form-control" id="landing_login_password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                    <div class="text-center small mt-2">
                        <a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(url('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script>
(function () {
    document.querySelectorAll('.landing-login-trigger[href="#"]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); });
    });
    var modalEl = document.getElementById('landingLoginModal');
    var form = document.getElementById('landingLoginForm');
    var errEl = document.getElementById('landingLoginErrors');
    if (modalEl && form) {
        modalEl.addEventListener('shown.bs.modal', function () {
            var em = document.getElementById('landing_login_email');
            if (em) { em.focus(); }
        });
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            errEl.classList.add('d-none');
            errEl.textContent = '';
            var action = form.getAttribute('action');
            try {
                var r = await fetch(action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                if (r.status === 429) {
                    errEl.textContent = 'Too many attempts. Please wait a minute and try again.';
                    errEl.classList.remove('d-none');
                    return;
                }
                var ct = (r.headers.get('Content-Type') || '');
                var data = {};
                if (ct.indexOf('application/json') !== -1) {
                    try { data = await r.json(); } catch (x) {}
                }
                if (r.status === 419) {
                    errEl.textContent = (data && data.message) ? data.message : 'Session expired. Refresh the page and try again.';
                    errEl.classList.remove('d-none');
                    return;
                }
                var errs = (data.messages && data.messages.errors) ? data.messages.errors : [];
                if (data.redirect && data.ok !== false && data.success !== false && errs.length === 0) {
                    window.location.href = data.redirect;
                    return;
                }
                if (errs.length) {
                    errEl.textContent = errs.join(' ');
                    errEl.classList.remove('d-none');
                    return;
                }
                errEl.textContent = (data && data.message) ? data.message : 'Could not sign in. Please try again.';
                errEl.classList.remove('d-none');
            } catch (x) {
                errEl.textContent = 'Network error. Please try again.';
                errEl.classList.remove('d-none');
            }
        });
    }
})();
</script>
</body>
</html>
