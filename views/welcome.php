<?php
$appName = \App\Core\App::config('name') ?? 'Laundry System';
$brandLogoPath = url('images/branding/mpglms-logo.png');
$brandName = (string) $appName;
$facebookUrl = 'https://www.facebook.com/mpgtechnologysolutionscom';
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
                <li class="nav-item"><a class="nav-link" href="<?= e(url('/login')) ?>">Login</a></li>
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
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('/register')) ?>" class="btn btn-warning fw-semibold"><i class="fa-solid fa-bolt me-1"></i>Start 7-day free trial</a>
                    <a href="<?= e(url('/pricing')) ?>" class="btn btn-outline-light">View pricing</a>
                    <a href="<?= e(url('/login')) ?>" class="btn btn-light text-primary">Login</a>
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
                <p class="text-white-50 mb-4">Start with a 7-day trial, explore pricing, or sign in to your store.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="<?= e(url('/register')) ?>" class="btn btn-warning fw-semibold">Start free trial</a>
                    <a href="<?= e(url('/pricing')) ?>" class="btn btn-outline-light">Pricing</a>
                    <a href="<?= e(url('/login')) ?>" class="btn btn-light text-primary">Login</a>
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

<script src="<?= e(url('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
