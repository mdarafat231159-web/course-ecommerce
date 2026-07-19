                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <?php
// ============================================================
// auth/login.php  –  User login
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) redirect('/index.php');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request token. Please try again.';
    } else {
        $email    = sanitize_email($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = 'Please enter your email and password.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Merge guest cart into user cart
                if (!empty($_SESSION['cart'])) {
                    $merge = db()->prepare(
                        'INSERT IGNORE INTO cart (user_id, course_id) VALUES (?, ?)'
                    );
                    foreach (array_keys($_SESSION['cart']) as $cid) {
                        $merge->execute([$user['id'], $cid]);
                    }
                    unset($_SESSION['cart']);
                }

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                session_regenerate_id(true);

                $redirect = $_SESSION['redirect_after_login'] ?? ($user['role'] === 'admin' ? '/admin/index.php' : '/index.php');
                unset($_SESSION['redirect_after_login']);
                redirect($redirect);
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}

$page_title = 'Log In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card__header">
            <div class="logo"><i class="fas fa-graduation-cap"></i></div>
            <h1>Welcome Back</h1>
            <p>Log in to continue learning</p>
        </div>

        <?= flash_html('global') ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <!-- Demo credentials hint -->
        <div class="alert alert-info" style="font-size:.82rem">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Demo accounts:</strong><br>
                Admin: admin@courseshop.com / Admin@1234<br>
                Student: jane@example.com / Student@1234
            </div>
        </div>

        <form id="loginForm" method="POST" action="" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($email) ?>" placeholder="you@example.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Your password" required autocomplete="current-password">
                    <button type="button" class="password-toggle__btn" aria-label="Toggle password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
                <label class="form-check">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="#" style="font-size:.85rem;color:var(--primary)">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg">
                <i class="fas fa-sign-in-alt"></i> Log In
            </button>
        </form>

        <div class="auth-footer">
            Don't have an account? <a href="<?= SITE_URL ?>/auth/register.php">Sign up free</a>
        </div>
    </div>
</div>

<script>
window.SITE_URL = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<script src="<?= SITE_URL ?>/assets/js/validation.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
