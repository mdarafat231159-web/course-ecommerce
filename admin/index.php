<?php
// ============================================================
// admin/index.php  –  Admin dashboard overview
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

// Stats
$stats = db()->query('
    SELECT
        (SELECT COUNT(*) FROM courses  WHERE is_active=1)          AS courses,
        (SELECT COUNT(*) FROM users    WHERE role="student")        AS students,
        (SELECT COUNT(*) FROM orders   WHERE status="paid")         AS orders,
        (SELECT IFNULL(SUM(total),0) FROM orders WHERE status="paid") AS revenue
')->fetch();

// Recent orders
$recent_orders = db()->query(
    'SELECT o.*, u.name AS user_name
     FROM orders o JOIN users u ON u.id=o.user_id
     ORDER BY o.created_at DESC LIMIT 8'
)->fetchAll();

// Top courses by enrollment
$top_courses = db()->query(
    'SELECT c.title, COUNT(e.id) AS enrolled
     FROM enrollments e JOIN courses c ON c.id=e.course_id
     GROUP BY c.id ORDER BY enrolled DESC LIMIT 5'
)->fetchAll();

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-topbar">
            <h1><i class="fas fa-tachometer-alt" style="color:var(--primary)"></i> Dashboard</h1>
            <div style="display:flex;gap:.75rem">
                <a href="<?= SITE_URL ?>/admin/courses.php?action=create" class="btn btn--primary btn--sm">
                    <i class="fas fa-plus"></i> Add Course
                </a>
                <a href="<?= SITE_URL ?>/index.php" class="btn btn--ghost btn--sm" target="_blank">
                    <i class="fas fa-external-link-alt"></i> View Site
                </a>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card__icon purple"><i class="fas fa-book"></i></div>
                <div>
                    <div class="stat-card__value"><?= number_format($stats['courses']) ?></div>
                    <div class="stat-card__label">Active Courses</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon blue"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-card__value"><?= number_format($stats['students']) ?></div>
                    <div class="stat-card__label">Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon green"><i class="fas fa-shopping-bag"></i></div>
                <div>
                    <div class="stat-card__value"><?= number_format($stats['orders']) ?></div>
                    <div class="stat-card__label">Paid Orders</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__icon yellow"><i class="fas fa-dollar-sign"></i></div>
                <div>
                    <div class="stat-card__value"><?= money((float)$stats['revenue']) ?></div>
                    <div class="stat-card__label">Total Revenue</div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">
            <!-- Recent Orders -->
            <div class="admin-table-wrap">
                <div class="table-header">
                    <h2>Recent Orders</h2>
                    <a href="<?= SITE_URL ?>/admin/orders.php" class="btn btn--ghost btn--sm">View All</a>
                </div>
                <table class="data-table">
                    <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_orders as $o):
                        $badge = match($o['status']) {
                            'paid'     => 'badge--success',
                            'failed'   => 'badge--danger',
                            'refunded' => 'badge--warning',
                            default    => 'badge--info',
                        };
                    ?>
                    <tr>
                        <td><a href="<?= SITE_URL ?>/admin/orders.php?id=<?= $o['id'] ?>" style="color:var(--primary);font-weight:600"><?= e($o['order_number']) ?></a></td>
                        <td><?= e($o['user_name']) ?></td>
                        <td><strong><?= money((float)$o['total']) ?></strong></td>
                        <td><span class="badge <?= $badge ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td style="color:var(--gray-500);font-size:.82rem"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--gray-500);padding:2rem">No orders yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top Courses -->
            <div class="admin-table-wrap">
                <div class="table-header"><h2>Top Courses</h2></div>
                <?php if (empty($top_courses)): ?>
                <p style="padding:1.5rem;color:var(--gray-500);font-size:.88rem">No enrollment data yet.</p>
                <?php else: ?>
                <ul style="list-style:none;padding:1rem">
                    <?php foreach ($top_courses as $i => $tc): ?>
                    <li style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--gray-100)">
                        <span style="width:24px;height:24px;border-radius:50%;background:var(--primary-light);color:var(--primary);display:grid;place-items:center;font-size:.75rem;font-weight:700;flex-shrink:0"><?= $i+1 ?></span>
                        <span style="flex:1;font-size:.85rem;font-weight:600"><?= e($tc['title']) ?></span>
                        <span style="font-size:.8rem;color:var(--gray-500)"><?= $tc['enrolled'] ?> students</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>window.SITE_URL='<?= SITE_URL ?>';window.CSRF_TOKEN='<?= csrf_token() ?>';</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
