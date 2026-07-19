<?php
// ============================================================
// pages/order-success.php  –  Order confirmation page
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_login('/auth/login.php');

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) redirect('/index.php');

// Load order (must belong to current user)
$stmt = db()->prepare(
    'SELECT o.*, p.status AS pay_status, p.transaction_id, p.gateway
     FROM orders o LEFT JOIN payments p ON p.order_id = o.id
     WHERE o.id = ? AND o.user_id = ?'
);
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) redirect('/index.php');

// Order items
$items_stmt = db()->prepare(
    'SELECT oi.*, c.slug FROM order_items oi LEFT JOIN courses c ON c.id = oi.course_id WHERE oi.order_id = ?'
);
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

$page_title = 'Order Confirmed!';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="success-page">
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1>Order Confirmed!</h1>
        <p>Thank you, <strong><?= e($_SESSION['user_name']) ?></strong>! Your payment was successful and your courses are ready.</p>

        <table class="order-details-table">
            <tr>
                <th>Order Number</th>
                <td><strong><?= e($order['order_number']) ?></strong></td>
            </tr>
            <tr>
                <th>Date</th>
                <td><?= date('F j, Y', strtotime($order['created_at'])) ?></td>
            </tr>
            <tr>
                <th>Payment</th>
                <td>
                    <span class="badge badge--success"><?= ucfirst($order['pay_status'] ?? $order['status']) ?></span>
                    <?php if ($order['transaction_id']): ?>
                    <code style="font-size:.75rem;color:var(--gray-500);margin-left:.5rem"><?= e($order['transaction_id']) ?></code>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Total Paid</th>
                <td><strong style="font-size:1.1rem"><?= money((float)$order['total']) ?></strong></td>
            </tr>
        </table>

        <!-- Purchased Courses -->
        <div style="text-align:left;margin:1.5rem 0">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Courses Purchased</h3>
            <?php foreach ($items as $item): ?>
            <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--gray-100)">
                <i class="fas fa-graduation-cap" style="color:var(--primary);font-size:1.1rem;flex-shrink:0"></i>
                <div style="flex:1;min-width:0">
                    <p style="font-size:.9rem;font-weight:600;margin:0"><?= e($item['title']) ?></p>
                </div>
                <span style="font-size:.88rem;font-weight:700;flex-shrink:0"><?= money((float)$item['price']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <p style="font-size:.85rem;color:var(--gray-500);margin-bottom:1.5rem">
            A confirmation email has been sent to <strong><?= e($order['billing_email']) ?></strong>
        </p>

        <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
            <a href="<?= SITE_URL ?>/pages/account.php?tab=courses" class="btn btn--primary btn--lg">
                <i class="fas fa-play"></i> Start Learning
            </a>
            <a href="<?= SITE_URL ?>/pages/account.php?tab=orders" class="btn btn--ghost btn--lg">
                <i class="fas fa-receipt"></i> View Orders
            </a>
        </div>
    </div>
</div>

<script>
window.SITE_URL = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
