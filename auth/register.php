<?php
// ============================================================
// auth/register.php  –  User registration
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) redirect('/index.php');

$errors = [];
$vals   = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Invalid request. Please try again.'; }
    else {
        $name     = sanitize($_POST['name']     ?? '');
        $email    = sanitize_email($_POST['email']    ?? '');
        $password = $_POST['password']  ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        $vals     = compact('name', 'email');

        // Server-side validation
        if (strlen($name) < 2)          $errors['name']     = 'Name must be at least 2 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
        if (strlen($password) < 8)      $errors['password'] = 'Password must be at least 8 characters.';
        if ($password !== $confirm)     $errors['password_confirm'] = 'Passwords do not match.';

        if (empty($errors)) {
            // Check duplicate email
            $dup = db()->prepare('SELECT id FROM users WHERE email = ?');
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $errors['email'] = 'This email is already registered. <a href="login.php">Log in?</a>';
            } else {
                $hash = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);
                $ins  = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
                $ins->execute([$name, $email, $hash]);
                $new_id = db()->lastInsertId();

                // Auto-login
                $_SESSION['user_id']    = $new_id;
                $_SESSION['user_name']  = $name;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_role']  = 'student';
                session_regenerate_id(true);

                flash('global', 'Welcome to CourseShop, ' . $name . '!', 'success');
                redirect($_SESSION['redirect_after_login'] ?? '/index.php');
            }
        }
    }
}

$page_title = 'Create Your Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card__header">
            <div class="logo"><i class="fas fa-graduation-cap"></i></div>
            <h1>Create Account</h1>
            <p>Join thousands of learners on <?= SITE_NAME ?></p>
        </div>

        <?php if (!empty($errors) && isset($errors[0])): ?>
        <div class="alert alert-danger"><?= $errors[0] ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <form id="registerForm" method="POST" action="" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'error' : '' ?>"
                       value="<?= e($vals['name']) ?>" placeholder="Jane Doe" required autocomplete="name">
                <?php if (isset($errors['name'])): ?><p class="form-error"><?= $errors['name'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'error' : '' ?>"
                       value="<?= e($vals['email']) ?>" placeholder="jane@example.com" required autocomplete="email">
                <?php if (isset($errors['email'])): ?><p class="form-error"><?= $errors['email'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password"
                           class="form-control <?= isset($errors['password']) ? 'error' : '' ?>"
                           placeholder="At least 8 characters" required autocomplete="new-password">
                    <button type="button" class="password-toggle__btn" aria-label="Toggle password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <?php if (isset($errors['password'])): ?><p class="form-error"><?= $errors['password'] ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="password_confirm" name="password_confirm"
                           class="form-control <?= isset($errors['password_confirm']) ? 'error' : '' ?>"
                           placeholder="Repeat password" required autocomplete="new-password">
                    <button type="button" class="password-toggle__btn" aria-label="Toggle confirm">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <?php if (isset($errors['password_confirm'])): ?><p class="form-error"><?= $errors['password_confirm'] ?></p><?php endif; ?>
            </div>

            <div class="form-check mb-2">
                <input type="checkbox" id="agree" name="agree" required>
                <label for="agree">I agree to the <a href="#" style="color:var(--primary)">Terms of Service</a> and <a href="#" style="color:var(--primary)">Privacy Policy</a></label>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="<?= SITE_URL ?>/auth/login.php">Log in</a>
        </div>
    </div>
</div>

<script>
window.SITE_URL = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<script src="<?= SITE_URL ?>/assets/js/validation.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
