<?php
// ============================================================
// admin/orders.php  –  Orders management
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$order_id = (int)($_GET['id'] ?? 0);
$msg      = null;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $new_status = sanitize($_POST['status'] ?? '');
    $oid        = (int)($_POST['order_id'] ?? 0);
    $allowed    = ['pending','paid','failed','refunded'];
    if ($oid && in_array($new_status, $allowed)) {
        db()->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$new_status, $oid]);
        // Sync payment status
        db()->prepare('UPDATE payments SET status=? WHERE order_id=?')
           ->execute([$new_status === 'paid' ? 'completed' : $new_status, $oid]);
        $msg = ['type'=>'success','text'=>'Order status updated.'];
    }
}

// Detail view
if ($order_id) {
    $stmt = db()->prepare(
        'SELECT o.*, u.name AS user_name, u.email AS user_email,
                p.transaction_id, p.gateway, p.status AS pay_status
         FROM orders o
         JOIN users u ON u.id=o.user_id
         LEFT JOIN payments p ON p.order_id=o.id
         WHERE o.id=?'
    );
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    $items = db()->prepare(
        'SELECT oi.*, c.slug FROM order_items oi LEFT JOIN courses c ON c.id=oi.course_id WHERE oi.order_id=?'
    );
    $items->execute([$order_id]);
    $order_items = $items->fetchAll();
}

// List view
$search = sanitize($_GET['q']      ?? '');
$status = sanitize($_GET['status'] ?? '');
$where  = ['1=1'];
$params = [];
if ($search)  { $where[] = '(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($status)  { $where[] = 'o.status=?'; $params[] = $status; }
$whereSQL = implode(' AND ', $where);

$orders = db()->prepare(
    "SELECT o.*, u.name AS user_name
     FROM orders o JOIN users u ON u.id=o.user_id
     WHERE $whereSQL ORDER BY o.created_at DESC LIMIT 100"
);
$orders->execute($params);
$orders = $orders->fetchAll();

$page_title = 'Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-main">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg['type']==='success'?'success':'danger' ?>"><?= e($msg['text']) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <?php if ($order_id && !empty($order)): ?>
        <!-- ── Order Detail ─────────────────────────────── -->
        <div class="admin-topbar">
            <h1>Order <?= e($order['order_number']) ?></h1>
            <a href="?action=list" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back to Orders</a>
        </div>

        <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem">
            <div>
                <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;margin-bottom:1.5rem">
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Order Items</h3>
                    <table class="data-table">
                        <thead><tr><th>Course</th><th style="text-align:right">Price</th></tr></thead>
                        <tbody>
                        <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?= e($item['title']) ?></td>
                            <td style="text-align:right"><?= money((float)$item['price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td style="text-align:right;color:var(--gray-500)">Subtotal</td><td style="text-align:right"><?= money((float)$order['subtotal']) ?></td></tr>
                            <?php if ($order['discount'] > 0): ?>
                            <tr><td style="text-align:right;color:var(--success)">Discount</td><td style="text-align:right;color:var(--success)">-<?= money((float)$order['discount']) ?></td></tr>
                            <?php endif; ?>
                            <tr><td style="text-align:right;color:var(--gray-500)">Tax</td><td style="text-align:right"><?= money((float)$order['tax']) ?></td></tr>
                            <tr><td style="text-align:right;font-weight:800">Total</td><td style="text-align:right;font-weight:800"><?= money((float)$order['total']) ?></td></tr>
                        </tfoot>
                    </table>
                </div>

                <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem">
                    <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">Customer & Payment</h3>
                    <dl style="display:grid;grid-template-columns:140px 1fr;gap:.5rem .75rem;font-size:.88rem">
                        <dt style="color:var(--gray-500)">Customer</dt><dd><?= e($order['user_name']) ?></dd>
                        <dt style="color:var(--gray-500)">Email</dt><dd><?= e($order['user_email']) ?></dd>
                        <dt style="color:var(--gray-500)">Country</dt><dd><?= e($order['billing_country']) ?></dd>
                        <dt style="color:var(--gray-500)">Gateway</dt><dd><?= ucfirst($order['gateway'] ?? 'N/A') ?></dd>
                        <dt style="color:var(--gray-500)">Transaction</dt><dd style="font-family:monospace;font-size:.8rem"><?= e($order['transaction_id'] ?? 'N/A') ?></dd>
                        <dt style="color:var(--gray-500)">Date</dt><dd><?= date('F j, Y g:ia', strtotime($order['created_at'])) ?></dd>
                    </dl>
                </div>
            </div>

            <div>
                <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem">
                    <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1rem">Update Status</h3>
                    <?php
                    $badge = match($order['status']) {
                        'paid'     => 'badge--success',
                        'failed'   => 'badge--danger',
                        'refunded' => 'badge--warning',
                        default    => 'badge--info',
                    };
                    ?>
                    <div style="margin-bottom:1rem">
                        Current: <span class="badge <?= $badge ?>"><?= ucfirst($order['status']) ?></span>
                    </div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $order_id ?>">
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn--primary btn--block">Update Status</button>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ── Orders List ──────────────────────────────── -->
        <div class="admin-topbar">
            <h1>Orders</h1>
            <span style="color:var(--gray-500);font-size:.9rem"><?= count($orders) ?> orders</span>
        </div>

        <div class="admin-table-wrap">
            <div class="table-header">
                <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
                    <input type="search" name="q" class="form-control" placeholder="Search orders…" value="<?= e($search) ?>" style="max-width:200px">
                    <select name="status" class="form-control" style="max-width:140px">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--ghost btn--sm"><i class="fas fa-search"></i> Filter</button>
                </form>
            </div>
            <table class="data-table">
                <thead><tr><th>Order #</th><th>Customer</th><th>Subtotal</th><th>Total</th><th>Status</th><th>Date</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o):
                    $badge = match($o['status']) {
                        'paid'     => 'badge--success',
                        'failed'   => 'badge--danger',
                        'refunded' => 'badge--warning',
                        default    => 'badge--info',
                    };
                ?>
                <tr>
                    <td><strong><?= e($o['order_number']) ?></strong></td>
                    <td><?= e($o['user_name']) ?></td>
                    <td><?= money((float)$o['subtotal']) ?></td>
                    <td><strong><?= money((float)$o['total']) ?></strong></td>
                    <td><span class="badge <?= $badge ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td style="color:var(--gray-500);font-size:.82rem"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                    <td><a href="?id=<?= $o['id'] ?>" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i></a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--gray-500)">No orders found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>window.SITE_URL='<?= SITE_URL ?>';window.CSRF_TOKEN='<?= csrf_token() ?>';</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
