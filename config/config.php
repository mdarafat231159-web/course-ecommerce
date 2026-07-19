<?php
// ============================================================
// config/config.php  –  Application-wide constants
// ============================================================

// ── Site ────────────────────────────────────────────────────
define('SITE_NAME',    'CourseShop');
define('SITE_URL',     'http://localhost/course-ecommerce');
define('SITE_EMAIL',   'support@courseshop.com');
define('CURRENCY',     'USD');
define('CURRENCY_SYM', '$');
define('TAX_RATE',     0.08);   // 8 %

// ── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'course_ecommerce');
define('DB_USER', 'root');
define('DB_PASS', '');          // change in production
define('DB_CHARSET', 'utf8mb4');

// ── Paths ────────────────────────────────────────────────────
define('ROOT_PATH',    dirname(__DIR__));
define('ASSETS_PATH',  ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// ── Session ──────────────────────────────────────────────────
define('SESSION_NAME',     'ce_session');
define('SESSION_LIFETIME', 7200);   // 2 hours

// ── Payment (demo keys – replace with real ones) ─────────────
define('STRIPE_PK',  'pk_test_XXXXXXXXXXXXXXXXXXXX');
define('STRIPE_SK',  'sk_test_XXXXXXXXXXXXXXXXXXXX');
define('PAYPAL_CLIENT_ID', 'demo_paypal_client_id');

// ── Security ─────────────────────────────────────────────────
define('HASH_ALGO',   PASSWORD_BCRYPT);
define('HASH_COST',   12);
define('CSRF_TOKEN_LENGTH', 32);

// ── Environment ───────────────────────────────────────────────
define('APP_ENV', 'development');   // development | production

if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
