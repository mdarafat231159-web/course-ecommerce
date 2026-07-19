<?php
// ============================================================
// admin/includes/sidebar.php  –  Admin navigation sidebar
// ============================================================
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="admin-sidebar">
    <div style="padding:.5rem .5rem 1rem;border-bottom:1px solid rgba(255,255,255,.1);margin-bottom:.5rem">
        <a href="<?= SITE_URL ?>/admin/index.php"
           style="display:flex;align-items:center;gap:.6rem;color:#fff;font-size:1.1rem;font-weight:800;text-decoration:none">
            <i class="fas fa-graduation-cap" style="color:var(--secondary)"></i> <?= SITE_NAME ?>
        </a>
        <span style="font-size:.72rem;opacity:.45;margin-top:.2rem;display:block">Admin Panel</span>
    </div>

    <h3>Main</h3>
    <a href="<?= SITE_URL ?>/admin/index.php"   class="<?= $current==='index'   ?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>

    <h3>Catalogue</h3>
    <a href="<?= SITE_URL ?>/admin/courses.php" class="<?= $current==='courses' ?'active':'' ?>"><i class="fas fa-book"></i> Courses</a>
    <a href="<?= SITE_URL ?>/admin/categories.php" class="<?= $current==='categories' ?'active':'' ?>"><i class="fas fa-tags"></i> Categories</a>

    <h3>Commerce</h3>
    <a href="<?= SITE_URL ?>/admin/orders.php"  class="<?= $current==='orders'  ?'active':'' ?>"><i class="fas fa-receipt"></i> Orders</a>
    <a href="<?= SITE_URL ?>/admin/coupons.php" class="<?= $current==='coupons' ?'active':'' ?>"><i class="fas fa-ticket-alt"></i> Coupons</a>

    <h3>Users</h3>
    <a href="<?= SITE_URL ?>/admin/users.php"   class="<?= $current==='users'   ?'active':'' ?>"><i class="fas fa-users"></i> All Users</a>

    <h3>System</h3>
    <a href="<?= SITE_URL ?>/index.php" target="_blank"><i class="fas fa-external-link-alt"></i> View Site</a>
    <a href="<?= SITE_URL ?>/auth/logout.php" style="color:#f87171;margin-top:auto"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>
