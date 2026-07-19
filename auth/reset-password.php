<?php
// ============================================================
// auth/reset-password.php  –  Set a new password via token
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

if (is_logged_in()) redirect('/index.php');

$token = sanitize($_GET['token'] ?? '');
$email = sanitize_email($_GET['email'] ?? '');
$error = '';
$valid_token = false;
$user_id     = null;

// Validate token
if ($token && $email) {
    $token_hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT pr.user_id FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
           AND u.email = ?
           AND pr.used = 0
           AND pr.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$token_hash, $email]);
    $row = $stmt->fetch();
    if ($row) {
        $valid_token = true;
        $user_id     = (int)$row['user_id'];
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $error = 'Invalid reset link. Please request a new password reset.';
}

$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!csrf_verify()) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, HASH_ALGO, ['cost' => HASH_COST]);

            // Update password
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([$hash, $user_id]);

            // Mark token as used
            db()->prepare('UPDATE password_resets SET used = 1 WHERE user_id = ?')
               ->execute([$user_id]);

            flash('global', 'Password updated successfully! Please log in with your new password.', 'success');
            redirect('/auth/login.php');
        }
    }
}

$page_title = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-card__header">
            <div class="logo"><i class="fas fa-graduation-cap"></i></div>
            <h1>Reset Password</h1>
            <p>Choose a strong new password for your account.</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= e($error) ?>
            <?php if (!$valid_token): ?>
            <br><a href="<?= SITE_URL ?>/auth/forgot-password.php" style="color:inherit;text-decoration:underline">
                Request a new reset link →
            </a>
            <?php endif; ?>
            <button class="alert-close">&times;</button>
        </div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
        <form id="resetForm" method="POST" action="" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="email" value="<?= e($email) ?>">

            <div class="form-group">
                <label for="password">New Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="At least 8 characters" required
                           autocomplete="new-password" minlength="8">
                    <button type="button" class="password-toggle__btn" aria-label="Show/hide password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm New Password <span class="required">*</span></label>
                <div class="password-toggle">
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                           placeholder="Repeat new password" required
                           autocomplete="new-password">
                    <button type="button" class="password-toggle__btn" aria-label="Show/hide confirm">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Password strength indicator -->
            <div class="password-strength" id="strengthBar" style="margin-bottom:1rem">
                <div class="strength-track">
                    <div class="strength-fill" id="strengthFill"></div>
                </div>
                <span class="strength-label" id="strengthLabel"></span>
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg">
                <i class="fas fa-lock"></i> Set New Password
            </button>
        </form>
        <?php endif; ?>

        <div class="auth-footer">
            <a href="<?= SITE_URL ?>/auth/login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</div>

<style>
.strength-track { height: 6px; background: var(--gray-300); border-radius: 3px; overflow: hidden; }
.strength-fill  { height: 100%; width: 0; transition: width .3s ease, background .3s ease; border-radius: 3px; }
.strength-label { font-size: .78rem; color: var(--gray-500); margin-top: .3rem; display: block; }
</style>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';

// Password strength meter
const pwInput    = document.getElementById('password');
const fillEl     = document.getElementById('strengthFill');
const labelEl    = document.getElementById('strengthLabel');

if (pwInput && fillEl) {
    pwInput.addEventListener('input', () => {
        const v = pwInput.value;
        let score = 0;
        if (v.length >= 8)              score++;
        if (/[A-Z]/.test(v))            score++;
        if (/[0-9]/.test(v))            score++;
        if (/[^A-Za-z0-9]/.test(v))     score++;

        const levels = [
            { pct: '0%',   color: 'transparent', label: '' },
            { pct: '25%',  color: '#ef4444',      label: 'Weak' },
            { pct: '50%',  color: '#f59e0b',      label: 'Fair' },
            { pct: '75%',  color: '#3b82f6',      label: 'Good' },
            { pct: '100%', color: '#10b981',      label: 'Strong' },
        ];
        const l = levels[score] || levels[0];
        fillEl.style.width      = l.pct;
        fillEl.style.background = l.color;
        if (labelEl) labelEl.textContent = l.label;
    });
}

// Reuse password-toggle behaviour from validation.js
document.querySelectorAll('.password-toggle__btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.password-toggle').querySelector('input');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show
            ? '<i class="fas fa-eye-slash"></i>'
            : '<i class="fas fa-eye"></i>';
    });
});

// Basic client-side confirm check
const form = document.getElementById('resetForm');
if (form) {
    form.addEventListener('submit', e => {
        const pw  = document.getElementById('password').value;
        const cfw = document.getElementById('password_confirm').value;
        if (pw.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters.');
        } else if (pw !== cfw) {
            e.preventDefault();
            alert('Passwords do not match.');
        }
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
