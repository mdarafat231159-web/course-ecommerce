<?php
// ============================================================
// auth/forgot-password.php  –  Request a password reset link
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) redirect('/index.php');

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize_email($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Look up user
            $stmt = db()->prepare('SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Expire any existing tokens for this user
                db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')
                   ->execute([$user['id']]);

                // Generate secure token
                $token     = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                db()->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
                )->execute([$user['id'], $token_hash, $expires_at]);

                // In production, send email here. For demo we show the link.
                $reset_link = SITE_URL . '/auth/reset-password.php?token=' . $token . '&email=' . urlencode($email);

                // Log the reset link so devs can test it (remove in production)
                error_log("Password reset link for {$email}: {$reset_link}");

                // Store link in session for demo display only — remove in production
                $_SESSION['_demo_reset_link'] = $reset_link;
            }

            // Always show success to prevent email enumeration
            $success = true;
        }
    }
}

$page_title = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card__header">
            <div class="logo"><i class="fas fa-graduation-cap"></i></div>
            <h1>Forgot Password?</h1>
            <p>Enter your email and we'll send you a reset link.</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Check your email!</strong><br>
                If an account exists for that address, a password reset link has been sent.
                The link expires in 1 hour.
            </div>
        </div>

        <?php if (!empty($_SESSION['_demo_reset_link'])): ?>
        <!-- DEMO ONLY – remove this block in production -->
        <div class="alert alert-warning" style="font-size:.82rem;word-break:break-all">
            <i class="fas fa-flask"></i>
            <div>
                <strong>Demo Mode – Reset Link:</strong><br>
                <a href="<?= e($_SESSION['_demo_reset_link']) ?>" style="color:var(--primary)">
                    <?= e($_SESSION['_demo_reset_link']) ?>
                </a>
            </div>
        </div>
        <?php unset($_SESSION['_demo_reset_link']); ?>
        <?php endif; ?>

        <a href="<?= SITE_URL ?>/auth/login.php" class="btn btn--primary btn--block">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>

        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="you@example.com" required autocomplete="email">
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div class="auth-footer">
            Remember your password? <a href="<?= SITE_URL ?>/auth/login.php">Log in</a>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
