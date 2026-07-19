<?php
// ============================================================
// pages/cart.php  –  Shopping cart page
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

// Fetch cart items – from DB (logged-in) or session (guest)
$cart_items = [];
$subtotal   = 0.0;
$discount   = (float)($_SESSION['cart_discount'] ?? 0);
$coupon     = $_SESSION['cart_coupon'] ?? '';

if (is_logged_in()) {
    $stmt = db()->prepare(
        'SELECT c.id AS cart_id, co.id, co.title, co.slug, co.image,
                co.price, co.sale_price, co.level, co.duration,
                u.name AS instructor_name
         FROM cart c
         JOIN courses co ON c.course_id = co.id
         JOIN users u    ON co.instructor_id = u.id
         WHERE c.user_id = ? AND co.is_active = 1'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
} elseif (!empty($_SESSION['cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['cart']));
    if ($ids) {
        $in  = implode(',', $ids);
        $cart_items = db()->query(
            "SELECT co.*, u.name AS instructor_name
             FROM courses co JOIN users u ON co.instructor_id = u.id
             WHERE co.id IN ($in) AND co.is_active=1"
        )->fetchAll();
    }
}

foreach ($cart_items as $item) {
    $subtotal += (float)($item['sale_price'] ?? $item['price']);
}
$tax   = $subtotal * TAX_RATE;
$total = max(0, $subtotal - $discount + $tax);

$page_title = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <nav class="breadcrumb"><a href="<?= SITE_URL ?>">Home</a> / <span>Cart</span></nav>
        <h1>Shopping Cart</h1>
        <p><?= count($cart_items) ?> course<?= count($cart_items) !== 1 ? 's' : '' ?> in your cart</p>
    </div>
</div>

<div class="container" style="padding-bottom:4rem">
    <?= flash_html('cart') ?>

    <?php if (empty($cart_items)): ?>
    <div class="cart-empty" style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow)">
        <i class="fas fa-shopping-cart"></i>
        <h3>Your cart is empty</h3>
        <p>Looks like you haven't added any courses yet.</p>
        <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--primary btn--lg">
            <i class="fas fa-search"></i> Browse Courses
        </a>
    </div>
    <?php else: ?>

    <div class="cart-layout">
        <!-- Cart Items -->
        <div class="cart-items" id="cartItemsWrap">
            <div class="cart-items__header">
                <h2>Course<?= count($cart_items) !== 1 ? 's' : '' ?></h2>
                <span style="color:var(--gray-500);font-size:.88rem"><?= count($cart_items) ?> item<?= count($cart_items) !== 1 ? 's' : '' ?></span>
            </div>

            <?php foreach ($cart_items as $item):
                $price = (float)($item['sale_price'] ?? $item['price']);
            ?>
            <div class="cart-item" id="cartItem-<?= $item['id'] ?>">
                <div class="cart-item__img">
                    <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($item['slug']) ?>">
                        <img src="<?= course_img($item['image']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="cart-item__info">
                    <h3>
                        <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($item['slug']) ?>"><?= e($item['title']) ?></a>
                    </h3>
                    <div class="instructor">by <?= e($item['instructor_name']) ?></div>
                    <span class="level"><?= ucfirst($item['level']) ?></span>
                    <?php if ($item['duration']): ?>
                    <span style="font-size:.8rem;color:var(--gray-500);margin-left:.5rem"><i class="fas fa-clock"></i> <?= e($item['duration']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="cart-item__price">
                    <span class="price"><?= money($price) ?></span>
                    <?php if ($item['sale_price']): ?>
                    <span style="font-size:.82rem;color:var(--gray-500);text-decoration:line-through"><?= money((float)$item['price']) ?></span>
                    <?php endif; ?>
                    <button class="cart-item__remove" data-remove-cart="<?= $item['id'] ?>">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Order Summary -->
        <div id="orderSummaryWrap">
            <div class="order-summary">
                <h3>Order Summary</h3>
                <div class="summary-row">
                    <span>Subtotal (<?= count($cart_items) ?> items)</span>
                    <span id="summarySubtotal"><?= money($subtotal) ?></span>
                </div>
                <?php if ($discount > 0): ?>
                <div class="summary-row" style="color:var(--success)">
                    <span>Discount <?= $coupon ? "($coupon)" : '' ?></span>
                    <span id="summaryDiscount">-<?= money($discount) ?></span>
                </div>
                <?php else: ?>
                <div class="summary-row" style="color:var(--success);display:none" id="discountRow">
                    <span>Discount</span>
                    <span id="summaryDiscount">-<?= money($discount) ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row">
                    <span>Tax (<?= TAX_RATE * 100 ?>%)</span>
                    <span id="summaryTax"><?= money($tax) ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span id="summaryTotal"><?= money($total) ?></span>
                </div>

                <!-- Coupon -->
                <div class="coupon-box">
                    <form id="couponForm">
                        <div class="input-group">
                            <input type="text" name="coupon_code" class="form-control"
                                   placeholder="Coupon code" value="<?= e($coupon) ?>">
                            <button type="submit" class="btn btn--secondary">Apply</button>
                        </div>
                    </form>
                </div>

                <a href="<?= SITE_URL ?>/pages/checkout.php" class="btn btn--primary btn--block btn--lg mt-2">
                    <i class="fas fa-lock"></i> Proceed to Checkout
                </a>
                <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--ghost btn--block mt-1">
                    <i class="fas fa-arrow-left"></i> Continue Shopping
                </a>

                <div class="secure-badge mt-2">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure checkout powered by Stripe &amp; PayPal</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<script src="<?= SITE_URL ?>/assets/js/validation.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
