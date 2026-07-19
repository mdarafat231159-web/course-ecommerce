<?php
// ============================================================
// api/order.php  –  Order creation + payment processing
// Called by checkout.php form POST
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_login('/auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/pages/checkout.php');
if (!csrf_verify()) {
    flash('checkout', 'Security token mismatch. Please try again.', 'error');
    redirect('/pages/checkout.php');
}

$uid = $_SESSION['user_id'];

// ── Collect & validate billing data ──────────────────────────
$billing_name    = sanitize($_POST['billing_name']    ?? '');
$billing_email   = sanitize_email($_POST['billing_email']  ?? '');
$billing_country = sanitize($_POST['billing_country'] ?? '');
$notes           = sanitize($_POST['notes']           ?? '');
$payment_method  = sanitize($_POST['payment_method']  ?? 'demo');
$buy_now_id      = (int)($_POST['buy_now_id'] ?? 0);

$errors = [];
if (strlen($billing_name) < 2)                         $errors[] = 'Full name is required.';
if (!filter_var($billing_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if (empty($billing_country))                            $errors[] = 'Country is required.';

if ($errors) {
    flash('checkout', implode(' ', $errors), 'error');
    $qs = $buy_now_id ? '?buy_now_id=' . $buy_now_id : '';
    redirect('/pages/checkout.php' . $qs);
}

// ── Gather cart items ─────────────────────────────────────────
if ($buy_now_id) {
    $stmt = db()->prepare('SELECT * FROM courses WHERE id=? AND is_active=1');
    $stmt->execute([$buy_now_id]);
    $course = $stmt->fetch();
    if (!$course) redirect('/pages/courses.php');
    $cart_courses = [$course];
} else {
    $stmt = db()->prepare(
        'SELECT co.* FROM cart c JOIN courses co ON c.course_id=co.id
         WHERE c.user_id=? AND co.is_active=1'
    );
    $stmt->execute([$uid]);
    $cart_courses = $stmt->fetchAll();
    if (empty($cart_courses)) redirect('/pages/cart.php');
}

// ── Calculate totals ──────────────────────────────────────────
$subtotal = 0.0;
foreach ($cart_courses as $c) $subtotal += (float)($c['sale_price'] ?? $c['price']);
$discount = (float)($_SESSION['cart_discount'] ?? 0);
$tax      = max(0, $subtotal - $discount) * TAX_RATE;
$total    = max(0, $subtotal - $discount + $tax);

// ── Process payment (simulated gateway) ──────────────────────
$payment_result = process_payment($payment_method, $total, [
    'card_number' => $_POST['card_number'] ?? '',
    'card_expiry' => $_POST['card_expiry'] ?? '',
    'card_cvv'    => $_POST['card_cvv']    ?? '',
    'card_name'   => $_POST['card_name']   ?? $billing_name,
    'email'       => $billing_email,
]);

if (!$payment_result['success']) {
    flash('checkout', 'Payment failed: ' . $payment_result['message'], 'error');
    redirect('/pages/checkout.php');
}

// ── Create order in DB (transaction) ─────────────────────────
$pdo = db();
$pdo->beginTransaction();

try {
    $order_number = generate_order_number();

    // Insert order
    $pdo->prepare(
        'INSERT INTO orders
         (user_id, order_number, subtotal, discount, tax, total,
          coupon_code, status, billing_name, billing_email, billing_country, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $uid, $order_number, $subtotal, $discount, $tax, $total,
        $_SESSION['cart_coupon'] ?? null, 'paid',
        $billing_name, $billing_email, $billing_country, $notes
    ]);
    $order_id = (int)$pdo->lastInsertId();

    // Insert order items + enrollments
    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, course_id, title, price) VALUES (?,?,?,?)'
    );
    $enrollStmt = $pdo->prepare(
        'INSERT IGNORE INTO enrollments (user_id, course_id, order_id) VALUES (?,?,?)'
    );
    foreach ($cart_courses as $c) {
        $p = (float)($c['sale_price'] ?? $c['price']);
        $itemStmt->execute([$order_id, $c['id'], $c['title'], $p]);
        $enrollStmt->execute([$uid, $c['id'], $order_id]);
    }

    // Insert payment record
    $pdo->prepare(
        'INSERT INTO payments (order_id, gateway, transaction_id, amount, currency, status, paid_at)
         VALUES (?,?,?,?,?,?,NOW())'
    )->execute([
        $order_id,
        $payment_method,
        $payment_result['transaction_id'],
        $total,
        CURRENCY,
        'completed',
    ]);

    // Update coupon usage
    if (!empty($_SESSION['cart_coupon'])) {
        $pdo->prepare('UPDATE coupons SET used_count=used_count+1 WHERE code=?')
           ->execute([$_SESSION['cart_coupon']]);
    }

    // Clear cart
    if ($buy_now_id) {
        $pdo->prepare('DELETE FROM cart WHERE user_id=? AND course_id=?')
           ->execute([$uid, $buy_now_id]);
    } else {
        $pdo->prepare('DELETE FROM cart WHERE user_id=?')->execute([$uid]);
    }

    $pdo->commit();

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Order creation failed: ' . $e->getMessage());
    flash('checkout', 'An error occurred while creating your order. Please try again.', 'error');
    redirect('/pages/checkout.php');
}

// ── Cleanup session ───────────────────────────────────────────
unset($_SESSION['cart_discount'], $_SESSION['cart_coupon']);

// ── Redirect to success page ──────────────────────────────────
redirect('/pages/order-success.php?order_id=' . $order_id);

// ============================================================
// Payment Gateway Simulation
// Replace with real Stripe/PayPal SDK calls in production
// ============================================================
function process_payment(string $method, float $amount, array $data): array {
    // Basic card validation for non-demo methods
    if ($method === 'card') {
        $num = preg_replace('/\s+/', '', $data['card_number'] ?? '');
        if (!preg_match('/^\d{13,19}$/', $num)) {
            return ['success' => false, 'message' => 'Invalid card number.'];
        }
        if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $data['card_expiry'] ?? '')) {
            return ['success' => false, 'message' => 'Invalid expiry date.'];
        }
        if (!preg_match('/^\d{3,4}$/', $data['card_cvv'] ?? '')) {
            return ['success' => false, 'message' => 'Invalid CVV.'];
        }
        // Simulate a declined test card
        if (str_starts_with($num, '4000000000000002')) {
            return ['success' => false, 'message' => 'Card declined. Please use a different card.'];
        }
    }

    // Simulate slight network delay
    usleep(300000); // 0.3s

    return [
        'success'        => true,
        'transaction_id' => strtoupper($method) . '_' . bin2hex(random_bytes(8)),
        'message'        => 'Payment successful.',
    ];
}
