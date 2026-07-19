<?php
// ============================================================
// config/helpers.php  –  Utility functions used across the app
// ============================================================

require_once __DIR__ . '/config.php';

// ── Output / escaping ────────────────────────────────────────

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float $amount): string {
    return CURRENCY_SYM . number_format($amount, 2);
}

function discount_percent(float $original, float $sale): int {
    if ($original <= 0) return 0;
    return (int) round((($original - $sale) / $original) * 100);
}

// ── Slugify ───────────────────────────────────────────────────

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = strtolower(trim($text, '-'));
    return $text ?: 'n-a';
}

// ── Sanitize input ────────────────────────────────────────────

function sanitize(string $input): string {
    return trim(strip_tags($input));
}

function sanitize_email(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// ── Redirect ──────────────────────────────────────────────────

function redirect(string $path): never {
    header('Location: ' . SITE_URL . $path);
    exit;
}

// ── JSON response (for AJAX endpoints) ───────────────────────

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Pagination ────────────────────────────────────────────────

function paginate(int $total, int $per_page, int $current_page): array {
    $pages = (int) ceil($total / $per_page);
    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'last_page'    => $pages,
        'offset'       => ($current_page - 1) * $per_page,
        'has_prev'     => $current_page > 1,
        'has_next'     => $current_page < $pages,
    ];
}

// ── Order number generator ────────────────────────────────────

function generate_order_number(): string {
    return 'ORD-' . strtoupper(substr(uniqid('', true), -8)) . '-' . date('Ymd');
}

// ── Active nav link ───────────────────────────────────────────

function nav_active(string $page): string {
    $current = basename($_SERVER['PHP_SELF'], '.php');
    return $current === $page ? 'active' : '';
}

// ── Course image URL ──────────────────────────────────────────

function course_img(string $filename): string {
    $path = '/assets/images/courses/' . $filename;
    $full = ROOT_PATH . $path;
    if (!file_exists($full)) {
        return SITE_URL . '/assets/images/courses/default-course.jpg';
    }
    return SITE_URL . $path;
}

// ── Star rating HTML ──────────────────────────────────────────

function stars(float $rating): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $html .= '<i class="fas fa-star"></i>';
        } elseif ($rating >= $i - 0.5) {
            $html .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $html .= '<i class="far fa-star"></i>';
        }
    }
    return $html . '</span>';
}

// ── XSS-safe JSON for JS ─────────────────────────────────────

function js_json(mixed $data): string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}
