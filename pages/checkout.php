<?php
// ============================================================
// pages/checkout.php  –  Checkout & payment page
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_login('/auth/login.php');

$uid = $_SESSION['user_id'];

// Support "Buy Now" single-course fast checkout
$buy_now_id = (int)($_GET['buy_now'] ?? 0);

if ($buy_now_id) {
    // Temporarily push just this course into session cart
    $stmt = db()->prepare('SELECT * FROM courses WHERE id=? AND is_active=1');
    $stmt->execute([$buy_now_id]);
    $bnCourse = $stmt->fetch();
    if (!$bnCourse) redirect('/pages/courses.php');
    $cart_items = [$bnCourse + ['instructor_name' => '']];
} else {
    $stmt = db()->prepare(
        'SELECT co.*, u.name AS instructor_name
         FROM cart c JOIN courses co ON c.course_id=co.id JOIN users u ON co.instructor_id=u.id
         WHERE c.user_id=? AND co.is_active=1'
    );
    $stmt->execute([$uid]);
    $cart_items = $stmt->fetchAll();
    if (empty($cart_items)) redirect('/pages/cart.php');
}

// Totals
$subtotal = 0.0;
foreach ($cart_items as $item) $subtotal += (float)($item['sale_price'] ?? $item['price']);
$discount = (float)($_SESSION['cart_discount'] ?? 0);
$tax      = ($subtotal - $discount) * TAX_RATE;
$total    = max(0, $subtotal - $discount + $tax);

// Current user
$user = db()->prepare('SELECT * FROM users WHERE id=?');
$user->execute([$uid]);
$user = $user->fetch();

$page_title = 'Checkout';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <nav class="breadcrumb"><a href="<?= SITE_URL ?>">Home</a> / <a href="<?= SITE_URL ?>/pages/cart.php">Cart</a> / <span>Checkout</span></nav>
        <h1>Checkout</h1>
    </div>
</div>

<div class="container" style="padding-bottom:4rem">
    <?= flash_html('checkout') ?>

    <div class="checkout-layout">
        <!-- Left: Form -->
        <form id="checkoutForm" action="<?= SITE_URL ?>/api/order.php" method="POST">
            <?= csrf_field() ?>
            <?php if ($buy_now_id): ?>
            <input type="hidden" name="buy_now_id" value="<?= $buy_now_id ?>">
            <?php endif; ?>
            <input type="hidden" name="payment_method" id="paymentMethodInput" value="card">

            <!-- Billing Info -->
            <div class="checkout-form">
                <h2><i class="fas fa-user"></i> Billing Information</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="billing_name" class="form-control"
                               value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="billing_email" class="form-control"
                               value="<?= e($user['email']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Country <span class="required">*</span></label>
                        <select name="billing_country" class="form-control" required>
                            <option value="">— Select Country —</option>
                            <?php foreach (['United States','United Kingdom','Canada','Australia','Germany','France','India','Pakistan','Bangladesh','Other'] as $c): ?>
                            <option value="<?= $c ?>"><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Phone (optional)</label>
                        <input type="tel" name="billing_phone" class="form-control" placeholder="+1 555 000 0000">
                    </div>
                </div>
                <div class="form-group">
                    <label>Order Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Any special requests…"></textarea>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="checkout-form mt-3">
                <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
                <div class="payment-methods">
                    <div class="payment-method active" data-method="card">
                        <i class="fas fa-credit-card"></i> Credit / Debit Card
                    </div>
                    <div class="payment-method" data-method="paypal">
                        <i class="fab fa-paypal"></i> PayPal
                    </div>
                    <div class="payment-method" data-method="demo">
                        <i class="fas fa-flask"></i> Demo Pay
                    </div>
                </div>

                <!-- Card Panel -->
                <div id="payment-card">
                    <div class="card-icons">
                        <i class="fab fa-cc-visa"></i>
                        <i class="fab fa-cc-mastercard"></i>
                        <i class="fab fa-cc-amex"></i>
                    </div>
                    <div class="form-group mt-2">
                        <label>Card Number <span class="required">*</span></label>
                        <input type="text" id="cardNumber" name="card_number" class="form-control"
                               placeholder="1234 5678 9012 3456" maxlength="19" autocomplete="cc-number">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Expiry Date <span class="required">*</span></label>
                            <input type="text" id="cardExpiry" name="card_expiry" class="form-control"
                                   placeholder="MM/YY" maxlength="5" autocomplete="cc-exp">
                        </div>
                        <div class="form-group">
                            <label>CVV <span class="required">*</span></label>
                            <input type="text" name="card_cvv" class="form-control"
                                   placeholder="123" maxlength="4" autocomplete="cc-csc">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Name on Card <span class="required">*</span></label>
                        <input type="text" name="card_name" class="form-control"
                               placeholder="John Doe" value="<?= e($user['name']) ?>" autocomplete="cc-name">
                    </div>
                </div>

                <!-- PayPal Panel -->
                <div id="payment-paypal" style="display:none">
                    <div class="alert alert-info">
                        <i class="fab fa-paypal" style="font-size:1.4rem"></i>
                        <div>You'll be redirected to PayPal to complete your payment securely.</div>
                    </div>
                </div>

                <!-- Demo Panel -->
                <div id="payment-demo" style="display:none">
                    <div class="alert alert-warning">
                        <i class="fas fa-flask" style="font-size:1.4rem"></i>
                        <div>Demo mode – no real payment is processed. Use this for testing.</div>
                    </div>
                </div>

                <div class="secure-badge">
                    <i class="fas fa-lock"></i>
                    <span>Your payment is secured with 256-bit SSL encryption. We never store full card details.</span>
                </div>

                <button type="submit" class="btn btn--primary btn--block btn--lg mt-3" id="placeOrderBtn">
                    <i class="fas fa-lock"></i> Place Order — <?= money($total) ?>
                </button>
            </div>
        </form>

        <!-- Right: Order Summary -->
        <div>
            <div class="order-summary">
                <h3>Order Summary</h3>
                <?php foreach ($cart_items as $item):
                    $ip = (float)($item['sale_price'] ?? $item['price']);
                ?>
                <div style="display:flex;gap:.75rem;margin-bottom:.85rem;padding-bottom:.85rem;border-bottom:1px solid var(--gray-100)">
                    <img src="<?= course_img($item['image']) ?>" alt="" style="width:60px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0">
                    <div style="flex:1;min-width:0">
                        <p style="font-size:.85rem;font-weight:600;line-height:1.3;margin-bottom:.2rem"><?= e($item['title']) ?></p>
                        <p style="font-size:.8rem;color:var(--gray-500)"><?= ucfirst($item['level']) ?></p>
                    </div>
                    <span style="font-size:.88rem;font-weight:700;flex-shrink:0"><?= money($ip) ?></span>
                </div>
                <?php endforeach; ?>

                <div class="summary-row"><span>Subtotal</span><span id="summarySubtotal"><?= money($subtotal) ?></span></div>
                <?php if ($discount > 0): ?>
                <div class="summary-row" style="color:var(--success)"><span>Discount</span><span>-<?= money($discount) ?></span></div>
                <?php endif; ?>
                <div class="summary-row"><span>Tax (<?= TAX_RATE * 100 ?>%)</span><span><?= money($tax) ?></span></div>
                <div class="summary-row total"><span>Total</span><span><?= money($total) ?></span></div>
            </div>

            <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;margin-top:1rem">
                <h4 style="font-size:.9rem;font-weight:700;margin-bottom:.75rem">You're getting:</h4>
                <ul style="list-style:none;display:flex;flex-direction:column;gap:.4rem">
                    <li style="font-size:.85rem;display:flex;align-items:center;gap:.5rem;color:var(--gray-700)"><i class="fas fa-check" style="color:var(--success)"></i> Lifetime access</li>
                    <li style="font-size:.85rem;display:flex;align-items:center;gap:.5rem;color:var(--gray-700)"><i class="fas fa-check" style="color:var(--success)"></i> Certificate of completion</li>
                    <li style="font-size:.85rem;display:flex;align-items:center;gap:.5rem;color:var(--gray-700)"><i class="fas fa-check" style="color:var(--success)"></i> 30-day money-back guarantee</li>
                    <li style="font-size:.85rem;display:flex;align-items:center;gap:.5rem;color:var(--gray-700)"><i class="fas fa-check" style="color:var(--success)"></i> Access on all devices</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
// Show loader on submit
document.getElementById('checkoutForm')?.addEventListener('submit', () => showLoader());
</script>
<script src="<?= SITE_URL ?>/assets/js/validation.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
