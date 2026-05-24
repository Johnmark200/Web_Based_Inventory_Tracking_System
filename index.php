<?php
include 'db.php';

$currentUser = currentUser($conn);
$summary = $conn->query(
    'SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_units
     FROM products'
)->fetch_assoc();
$categorySummary = $conn->query('SELECT COUNT(*) AS total_categories FROM categories')->fetch_assoc();
$recentProducts = $conn->query(
    'SELECT p.name, p.stock_quantity, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     ORDER BY p.id DESC
     LIMIT 6'
);

$dashboardLink = $currentUser ? 'dashboard.php' : 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InventoryFlow | Smart Inventory Tracking</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="landing-page" data-theme="light">
    <nav class="navbar navbar-expand-lg landing-nav sticky-top">
        <div class="container py-2">
            <a class="navbar-brand landing-brand" href="index.php">
                <span class="brand-orb"></span>
                InventoryFlow
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="landingNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#preview">Preview</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item">
                        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch theme" aria-pressed="false" title="Switch theme">
                            <span class="icon-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M20 14.69A8 8 0 0 1 9.31 4a8 8 0 1 0 10.69 10.69Z"/>
                                </svg>
                            </span>
                        </button>
                    </li>
                    <?php if ($currentUser): ?>
                        <li class="nav-item"><a class="btn btn-outline-light rounded-pill px-4" href="dashboard.php">Dashboard</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-warning rounded-pill px-4 is-disabled-link" href="register.php" aria-disabled="true" tabindex="-1" onclick="return false;">Start Free</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <header class="landing-hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-7 hero-copy-column">
                    <span class="promo-chip reveal-on-scroll">Bootstrap-powered inventory experience</span>
                    <h1 class="hero-title display-3 fw-bold mt-4 reveal-on-scroll">Track stock, create categories instantly, and keep your team ahead of shortages.</h1>
                    <p class="hero-summary lead mt-4 reveal-on-scroll">InventoryFlow gives your business a cleaner way to manage products, monitor low-stock items, and keep records organized without slowing down daily operations.</p>
                    <div class="hero-cta-row d-flex flex-wrap gap-3 mt-4 reveal-on-scroll">
                        <a href="<?php echo h($dashboardLink); ?>" class="btn btn-warning btn-lg rounded-pill px-4">Open Inventory</a>
                        <a href="#preview" class="btn hero-secondary-btn btn-lg rounded-pill px-4">See Live Highlights</a>
                    </div>
                    <div class="hero-metrics row g-3 mt-4">
                        <div class="col-sm-4 reveal-on-scroll">
                            <div class="metric-tile">
                                <strong data-counter="<?php echo (int) $summary['total_products']; ?>">0</strong>
                                <span>Products</span>
                            </div>
                        </div>
                        <div class="col-sm-4 reveal-on-scroll">
                            <div class="metric-tile">
                                <strong data-counter="<?php echo (int) $categorySummary['total_categories']; ?>">0</strong>
                                <span>Categories</span>
                            </div>
                        </div>
                        <div class="col-sm-4 reveal-on-scroll">
                            <div class="metric-tile">
                                <strong data-counter="<?php echo (int) $summary['total_units']; ?>">0</strong>
                                <span>Units tracked</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 hero-showcase-column">
                    <div class="hero-showcase reveal-on-scroll">
                        <div id="inventoryCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-indicators">
                                <button type="button" data-bs-target="#inventoryCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                <button type="button" data-bs-target="#inventoryCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                <button type="button" data-bs-target="#inventoryCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                            </div>
                            <div class="carousel-inner">
                                <div class="carousel-item active">
                                    <div class="showcase-card">
                                        <span class="showcase-kicker">Low stock alerts</span>
                                        <h2>Spot restocking issues before they become missed sales.</h2>
                                        <p>The dashboard highlights low-stock products and surfaces quick stock notifications for fast action.</p>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="showcase-card">
                                        <span class="showcase-kicker">Flexible categories</span>
                                        <h2>Type a new category when adding products and the system creates it automatically.</h2>
                                        <p>No separate category management page is required for day-to-day encoding.</p>
                                    </div>
                                </div>
                                <div class="carousel-item">
                                    <div class="showcase-card">
                                        <span class="showcase-kicker">Audit-ready history</span>
                                        <h2>Review stock changes and quantity movement in one place.</h2>
                                        <p>Track creates, edits, and deletions with a simple activity report built into the workflow.</p>
                                    </div>
                                </div>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#inventoryCarousel" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#inventoryCarousel" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                        <div class="floating-badge floating-badge-top">
                            <span class="icon-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M4 19h16v2H2V3h2v16Zm3-2H5v-6h2v6Zm6 0h-2V7h2v10Zm6 0h-2v-8h2v8Z"/>
                                </svg>
                            </span>
                            Real-time visibility
                        </div>
                        <div class="floating-badge floating-badge-bottom">
                            <span class="icon-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M11 3H4a1 1 0 0 0-1 1v7l9.5 9.5a1 1 0 0 0 1.4 0l6.6-6.6a1 1 0 0 0 0-1.4L11 3Zm-4.5 5A1.5 1.5 0 1 1 8 6.5 1.5 1.5 0 0 1 6.5 8Z"/>
                                </svg>
                            </span>
                            Smart category creation
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section id="features" class="py-5">
            <div class="container py-lg-5">
                <div class="section-heading text-center reveal-on-scroll">
                    <span class="section-kicker">Why teams use it</span>
                    <h2>Built for practical inventory work, not just reports.</h2>
                </div>
                <div class="row g-4 mt-2">
                    <div class="col-md-4 reveal-on-scroll">
                        <div class="feature-card h-100">
                            <div class="feature-icon">
                                <span class="icon-mark icon-mark-light" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M12 2 3 6.5v11L12 22l9-4.5v-11L12 2Zm0 2.2 6.4 3.2L12 10.6 5.6 7.4 12 4.2Zm-7 4.8 6 3v7.4l-6-3V9Zm8 10.4V12l6-3v7.4l-6 3Z"/>
                                    </svg>
                                </span>
                            </div>
                            <h3>Fast product encoding</h3>
                            <p>Add stock, descriptions, and categories from one form with less manual setup.</p>
                        </div>
                    </div>
                    <div class="col-md-4 reveal-on-scroll">
                        <div class="feature-card h-100">
                            <div class="feature-icon">
                                <span class="icon-mark icon-mark-light" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M12 22a2.25 2.25 0 0 0 2.24-2H9.76A2.25 2.25 0 0 0 12 22Zm7-4v-1l-2-2.75V11a5 5 0 1 0-10 0v3.25L5 17v1h14Z"/>
                                    </svg>
                                </span>
                            </div>
                            <h3>Low-stock awareness</h3>
                            <p>See which products need attention through dashboard signals and warning indicators.</p>
                        </div>
                    </div>
                    <div class="col-md-4 reveal-on-scroll">
                        <div class="feature-card h-100">
                            <div class="feature-icon">
                                <span class="icon-mark icon-mark-light" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M13 7h-2v6l5 3 1-1.73-4-2.37V7Zm-1-5a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8Z"/>
                                    </svg>
                                </span>
                            </div>
                            <h3>Clear stock history</h3>
                            <p>Review change logs for accountability and quicker inventory audits.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="preview" class="py-5">
            <div class="container py-lg-5">
                <div class="row align-items-center g-4">
                    <div class="col-lg-5 reveal-on-scroll">
                        <span class="section-kicker">Inventory preview</span>
                        <h2>Show the business value before users even log in.</h2>
                        <p class="text-secondary">The landing page gives a simple sales pitch for the platform while the dashboard remains focused on operations.</p>
                        <div class="accordion mt-4 landing-accordion" id="promoAccordion">
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#promoOne" aria-expanded="true" aria-controls="promoOne">
                                        Faster onboarding for staff
                                    </button>
                                </h3>
                                <div id="promoOne" class="accordion-collapse collapse show" data-bs-parent="#promoAccordion">
                                    <div class="accordion-body">New users can understand the system before signing in, which reduces friction during demos and training.</div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#promoTwo" aria-expanded="false" aria-controls="promoTwo">
                                        Flexible category handling
                                    </button>
                                </h3>
                                <div id="promoTwo" class="accordion-collapse collapse" data-bs-parent="#promoAccordion">
                                    <div class="accordion-body">When a typed category does not exist yet, the product workflow now creates it automatically instead of blocking the save.</div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h3 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#promoThree" aria-expanded="false" aria-controls="promoThree">
                                        Better stock visibility
                                    </button>
                                </h3>
                                <div id="promoThree" class="accordion-collapse collapse" data-bs-parent="#promoAccordion">
                                    <div class="accordion-body">Summary cards and recent history help staff make quick decisions without digging through separate pages.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 reveal-on-scroll">
                        <div class="preview-panel">
                            <div class="preview-header">
                                <span>Latest products</span>
                                <a href="<?php echo h($dashboardLink); ?>">Open full inventory</a>
                            </div>
                            <div class="preview-list">
                                <?php if ($recentProducts->num_rows === 0): ?>
                                    <div class="preview-item">
                                        <div>
                                            <strong>No products yet</strong>
                                            <p>Add your first item to start tracking stock.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php while ($recentProduct = $recentProducts->fetch_assoc()): ?>
                                    <div class="preview-item">
                                        <div>
                                            <strong><?php echo h($recentProduct['name']); ?></strong>
                                            <p><?php echo h($recentProduct['category_name']); ?></p>
                                        </div>
                                        <span class="preview-stock"><?php echo (int) $recentProduct['stock_quantity']; ?> units</span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq" class="py-5">
            <div class="container py-lg-5">
                <div class="cta-panel reveal-on-scroll">
                    <div>
                        <span class="section-kicker">Ready to use it?</span>
                        <h2>Promote the system publicly, then route your team into the working dashboard.</h2>
                        <p class="mb-0 cta-copy">Use the landing page for presentations and let the dashboard handle the actual inventory operations.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo h($dashboardLink); ?>" class="btn btn-warning btn-lg rounded-pill px-4">Launch Inventory</a>
                        <?php if (!$currentUser): ?>
                            <a href="register.php" class="btn btn-outline-light btn-lg rounded-pill px-4 is-disabled-link" aria-disabled="true" tabindex="-1" onclick="return false;">Create Account</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer py-4">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-3">
            <span>InventoryFlow</span>
            <span>Smart inventory tracking for products, categories, and stock movement.</span>
        </div>
    </footer>

    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="scripts.js"></script>
</body>
</html>
