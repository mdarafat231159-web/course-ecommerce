<?php
// ============================================================
// api/cart.php  –  Cart REST-style JSON API
// Handles: add | remove | coupon | count | get
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: count or full cart ───────────────────────────────────
if ($method === 'GET') {
    $action = sanitize($_GET['action'] ?? 'count');

    if ($action === 'count') {
        json_response(['count' => _cart_count()]);
    }

    if ($action === 'get') {
        json_response(['items' => _cart_items(), 'count' => _cart_count()]);
    }

    json_response(['error' => 'Unknown action.'], 400);
}

// ── POST: mutate cart ────────────────────────────────────────
if ($method === 'POST') {
    // Accept JSON body or form data
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // CSRF check
    $token = $body['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? ($_SERVER['HTTP_X_CSRF_Token'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
    }

    $action    = sanitize($body['action']    ?? '');
    $course_id = (int)($body['course_id']    ?? 0);

    switch ($action) {

        // ── Add ──────────────────────────────────────────────
        case 'add':
            if (!$course_id) json_response(['success' => false, 'message' => 'Invalid course.'], 422);

            // Verify course exists and is active
            $stmt = db()->prepare('SELECT id, title FROM courses WHERE id=? AND is_active=1');
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
            if (!$course) json_response(['success' => false, 'message' => 'Course not found.'], 404);

            if (is_logged_in()) {
                $uid = $_SESSION['user_id'];

                // Already owned?
                $own = db()->prepare('SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?');
                $own->execute([$uid, $course_id]);
                if ($own->fetchColumn()) {
                    json_response(['success' => false, 'message' => 'You already own this course.']);
                }

                // Already in cart?
                $dup = db()->prepare('SELECT 1 FROM cart WHERE user_id=? AND course_id=?');
                $dup->execute([$uid, $course_id]);
                if ($dup->fetchColumn()) {
                    json_response(['success' => true, 'message' => 'Already in cart.', 'cart_count' => _cart_count()]);
                }

                db()->prepare('INSERT INTO cart (user_id, course_id) VALUES (?,?)')
                   ->execute([$uid, $course_id]);
            } else {
                // Guest session cart
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                $_SESSION['cart'][$course_id] = true;
            }

            json_response([
                'success'    => true,
                'message'    => 'Course added to cart!',
                'cart_count' => _cart_count(),
            ]);

        // ── Remove ───────────────────────────────────────────
        case 'remove':
            if (!$course_id) json_response(['success' => false, 'message' => 'Invalid course.'], 422);

            if (is_logged_in()) {
                db()->prepare('DELETE FROM cart WHERE user_id=? AND course_id=?')
                   ->execute([$_SESSION['user_id'], $course_id]);
            } else {
                unset($_SESSION['cart'][$course_id]);
            }

            $totals = _cart_totals();
            json_response([
                'success'      => true,
                'message'      => 'Course removed.',
                'cart_count'   => _cart_count(),
                'subtotal'     => $totals['subtotal'],
                'subtotal_fmt' => money($totals['subtotal']),
                'discount'     => $totals['discount'],
                'discount_fmt' => money($totals['discount']),
                'tax'          => $totals['tax'],
                'tax_fmt'      => money($totals['tax']),
                'total'        => $totals['total'],
                'total_fmt'    => money($totals['total']),
            ]);

        // ── Coupon ───────────────────────────────────────────
        case 'coupon':
            $code = strtoupper(sanitize($body['code'] ?? ''));
            if (!$code) json_response(['success' => false, 'message' => 'Enter a coupon code.'], 422);

            $stmt = db()->prepare(
                'SELECT * FROM coupons
                 WHERE code=? AND is_active=1
                   AND (max_uses IS NULL OR used_count < max_uses)
                   AND (expires_at IS NULL OR expires_at >= CURDATE())'
            );
            $stmt->execute([$code]);
            $coupon = $stmt->fetch();

            if (!$coupon) {
                json_response(['success' => false, 'message' => 'Invalid or expired coupon code.']);
            }

            $subtotal = _cart_totals()['subtotal'];
            $discount = $coupon['type'] === 'percent'
                ? round($subtotal * ($coupon['value'] / 100), 2)
                : min((float)$coupon['value'], $subtotal);

            $_SESSION['cart_discount'] = $discount;
            $_SESSION['cart_coupon']   = $code;

            $totals = _cart_totals();
            json_response([
                'success'      => true,
                'message'      => 'Coupon "' . $code . '" applied. You saved ' . money($discount) . '!',
                'cart_count'   => _cart_count(),
                'subtotal_fmt' => money($totals['subtotal']),
                'discount_fmt' => '-' . money($discount),
                'tax_fmt'      => money($totals['tax']),
                'total_fmt'    => money($totals['total']),
            ]);

        default:
            json_response(['success' => false, 'message' => 'Unknown action.'], 400);
    }
}

json_response(['error' => 'Method not allowed.'], 405);

// ── Helpers ───────────────────────────────────────────────────

function _cart_count(): int {
    if (is_logged_in()) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM cart WHERE user_id=?');
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn();
    }
    return count($_SESSION['cart'] ?? []);
}

function _cart_items(): array {
    if (is_logged_in()) {
        $stmt = db()->prepare(
            'SELECT co.id, co.title, co.price, co.sale_price, co.image
             FROM cart c JOIN courses co ON c.course_id = co.id
             WHERE c.user_id=? AND co.is_active=1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    }
    if (empty($_SESSION['cart'])) return [];
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    return db()->query(
        "SELECT id, title, price, sale_price, image FROM courses WHERE id IN ($ids) AND is_active=1"
    )->fetchAll();
}

function _cart_totals(): array {
    $items    = _cart_items();
    $subtotal = 0.0;
    foreach ($items as $i) $subtotal += (float)($i['sale_price'] ?? $i['price']);
    $discount = (float)($_SESSION['cart_discount'] ?? 0);
    $tax      = max(0, $subtotal - $discount) * TAX_RATE;
    $total    = max(0, $subtotal - $discount + $tax);
    return compact('subtotal', 'discount', 'tax', 'total');
}
