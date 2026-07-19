<?php
// ============================================================
// includes/header.php  –  HTML <head> + top navbar
// Variables expected from the calling page:
//   $page_title  (string)
//   $page_desc   (string, optional)
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

$page_title = $page_title ?? SITE_NAME;
$page_desc  = $page_desc  ?? 'Learn in-demand skills from expert instructors. Browse our library of online courses.';

// Cart count (session-based for guests, DB for logged-in)
$cart_count = 0;
if (is_logged_in()) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM cart WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $cart_count = (int)$stmt->fetchColumn();
} elseif (!empty($_SESSION['cart'])) {
    $cart_count = count($_SESSION['cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($page_desc) ?>">
    <title><?= e($page_title) ?> | <?= SITE_NAME ?></title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- App Stylesheet -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ── Top Bar ──────────────────────────────────────────── -->
<div class="topbar">
    <div class="container topbar__inner">
        <span><i class="fas fa-envelope"></i> <?= SITE_EMAIL ?></span>
        <span><i class="fas fa-tag"></i> Use code <strong>WELCOME20</strong> for 20% off your first course!</span>
    </div>
</div>

<!-- ── Main Navbar ──────────────────────────────────────── -->
<nav class="navbar" id="mainNav">
    <div class="container navbar__inner">

        <!-- Logo -->
        <a class="navbar__logo" href="<?= SITE_URL ?>/index.php">
            <i class="fas fa-graduation-cap"></i>
            <span><?= SITE_NAME ?></span>
        </a>

        <!-- Search -->
        <form class="navbar__search" action="<?= SITE_URL ?>/pages/courses.php" method="GET" role="search">
            <input type="search" name="q" placeholder="Search for courses…"
                   value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search courses">
            <button type="submit" aria-label="Submit search"><i class="fas fa-search"></i></button>
        </form>

        <!-- Nav Links -->
        <ul class="navbar__links" id="navLinks">
            <li><a href="<?= SITE_URL ?>/index.php" class="<?= nav_active('index') ?>">Home</a></li>
            <li><a href="<?= SITE_URL ?>/pages/courses.php" class="<?= nav_active('courses') ?>">Courses</a></li>
            <?php if (is_admin()): ?>
            <li><a href="<?= SITE_URL ?>/admin/index.php" class="<?= nav_active('admin') ?>"><i class="fas fa-cog"></i> Admin</a></li>
            <?php endif; ?>
        </ul>

        <!-- Right actions -->
        <div class="navbar__actions">
            <!-- Cart -->
            <a href="<?= SITE_URL ?>/pages/cart.php" class="btn-icon cart-btn" aria-label="Shopping cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" id="cartBadge" <?= $cart_count === 0 ? 'hidden' : '' ?>><?= $cart_count ?></span>
            </a>

            <?php if (is_logged_in()): ?>
            <!-- User dropdown -->
            <div class="user-dropdown">
                <button class="user-dropdown__toggle" aria-haspopup="true" aria-expanded="false">
                    <span class="avatar"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></span>
                    <span class="user-name"><?= e(explode(' ', $_SESSION['user_name'])[0]) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <ul class="user-dropdown__menu" role="menu">
                    <li><a href="<?= SITE_URL ?>/pages/account.php"><i class="fas fa-user"></i> My Account</a></li>
                    <li><a href="<?= SITE_URL ?>/pages/account.php?tab=orders"><i class="fas fa-receipt"></i> My Orders</a></li>
                    <li><a href="<?= SITE_URL ?>/pages/account.php?tab=courses"><i class="fas fa-book-open"></i> My Courses</a></li>
                    <li class="divider"></li>
                    <li><a href="<?= SITE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            <?php else: ?>
            <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn--outline btn--sm">Log In</a>
            <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn--primary btn--sm">Sign Up</a>
            <?php endif; ?>
        </div>

        <!-- Mobile hamburger -->
        <button class="navbar__hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>
<!-- ── End Navbar ────────────────────────────────────────── -->

<main id="mainContent">
