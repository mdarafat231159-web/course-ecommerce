<?php
// ============================================================
// pages/account.php  –  User dashboard (profile/orders/courses)
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_login('/auth/login.php');

$uid  = $_SESSION['user_id'];
$tab  = sanitize($_GET['tab'] ?? 'profile');

// Load user
$user = db()->prepare('SELECT * FROM users WHERE id = ?');
$user->execute([$uid]);
$user = $user->fetch();

// Handle profile update
$profile_msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($tab === 'profile' || isset($_POST['update_profile']))) {
    if (!csrf_verify()) {
        $profile_msg = ['type' => 'error', 'msg' => 'Invalid request.'];
    } else {
        $name  = sanitize($_POST['name']  ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $pw    = $_POST['new_password'] ?? '';
        $errors = [];
        if (strlen($name) < 2)  $errors[] = 'Name too short.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email.';
        if ($pw && strlen($pw) < 8) $errors[] = 'New password must be at least 8 characters.';

        if (empty($errors)) {
            if ($pw) {
                $hash = password_hash($pw, HASH_ALGO, ['cost' => HASH_COST]);
                db()->prepare('UPDATE users SET name=?,email=?,password_hash=? WHERE id=?')
                    ->execute([$name,$email,$hash,$uid]);
            } else {
                db()->prepare('UPDATE users SET name=?,email=? WHERE id=?')
                    ->execute([$name,$email,$uid]);
            }
            $_SESSION['user_name']  = $name;
            $_SESSION['user_email'] = $email;
            $user['name']  = $name;
            $user['email'] = $email;
            $profile_msg = ['type' => 'success', 'msg' => 'Profile updated successfully.'];
        } else {
            $profile_msg = ['type' => 'error', 'msg' => implode(' ', $errors)];
        }
    }
}

// Orders
$orders = [];
if ($tab === 'orders') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
    $stmt->execute([$uid]);
    $orders = $stmt->fetchAll();
}

// Enrolled courses
$enrolled = [];
if ($tab === 'courses') {
    $stmt = db()->prepare(
        'SELECT c.*, cat.name AS category_name, e.enrolled_at
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         JOIN categories cat ON cat.id = c.category_id
         WHERE e.user_id = ? ORDER BY e.enrolled_at DESC'
    );
    $stmt->execute([$uid]);
    $enrolled = $stmt->fetchAll();
}

$page_title = 'My Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>My Account</h1>
        <p>Manage your profile, orders, and courses</p>
    </div>
</div>

<div class="container" style="padding-bottom:4rem">
    <?= flash_html('global') ?>
    <div class="account-layout">
        <!-- Sidebar -->
        <aside class="account-sidebar">
            <div class="account-sidebar__header">
                <div class="account-sidebar__avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                <div class="account-sidebar__name"><?= e($user['name']) ?></div>
                <div class="account-sidebar__email"><?= e($user['email']) ?></div>
            </div>
            <nav class="account-nav">
                <a href="?tab=profile"  class="<?= $tab==='profile'  ? 'active' : '' ?>"><i class="fas fa-user"></i> Profile</a>
                <a href="?tab=orders"   class="<?= $tab==='orders'   ? 'active' : '' ?>"><i class="fas fa-receipt"></i> My Orders</a>
                <a href="?tab=courses"  class="<?= $tab==='courses'  ? 'active' : '' ?>"><i class="fas fa-book-open"></i> My Courses</a>
                <a href="?tab=security" class="<?= $tab==='security' ? 'active' : '' ?>"><i class="fas fa-shield-alt"></i> Security</a>
                <a href="<?= SITE_URL ?>/auth/logout.php" style="color:var(--danger)"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </aside>

        <!-- Content -->
        <div class="account-content">

            <?php if ($tab === 'profile'): ?>
            <div class="account-content__header">
                <h2>Profile Information</h2>
            </div>
            <?php if ($profile_msg): ?>
            <div class="alert alert-<?= $profile_msg['type'] === 'success' ? 'success' : 'danger' ?>">
                <?= e($profile_msg['msg']) ?><button class="alert-close">&times;</button>
            </div>
            <?php endif; ?>
            <form method="POST" action="?tab=profile">
                <?= csrf_field() ?>
                <input type="hidden" name="update_profile" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>New Password <span style="font-size:.8rem;color:var(--gray-500)">(leave blank to keep current)</span></label>
                    <div class="password-toggle">
                        <input type="password" name="new_password" class="form-control" placeholder="New password (8+ characters)" autocomplete="new-password">
                        <button type="button" class="password-toggle__btn"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Member Since</label>
                    <input type="text" class="form-control" value="<?= date('F j, Y', strtotime($user['created_at'])) ?>" disabled>
                </div>
                <button type="submit" class="btn btn--primary"><i class="fas fa-save"></i> Save Changes</button>
            </form>

            <?php elseif ($tab === 'orders'): ?>
            <div class="account-content__header">
                <h2>My Orders</h2>
                <span style="color:var(--gray-500);font-size:.9rem"><?= count($orders) ?> order<?= count($orders)!==1?'s':'' ?></span>
            </div>
            <?php if (empty($orders)): ?>
            <div style="text-align:center;padding:3rem 0">
                <i class="fas fa-receipt" style="font-size:3rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
                <h3>No orders yet</h3>
                <p style="color:var(--gray-500);margin-bottom:1.5rem">Start learning by purchasing your first course!</p>
                <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--primary">Browse Courses</a>
            </div>
            <?php else: ?>
            <div class="admin-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>Order #</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order):
                        $badge = match($order['status']) {
                            'paid'     => 'badge--success',
                            'failed'   => 'badge--danger',
                            'refunded' => 'badge--warning',
                            default    => 'badge--info',
                        };
                        $itemCount = db()->prepare('SELECT COUNT(*) FROM order_items WHERE order_id=?');
                        $itemCount->execute([$order['id']]);
                        $itemCount = $itemCount->fetchColumn();
                    ?>
                    <tr>
                        <td><strong><?= e($order['order_number']) ?></strong></td>
                        <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                        <td><?= $itemCount ?> course<?= $itemCount!==1?'s':'' ?></td>
                        <td><strong><?= money((float)$order['total']) ?></strong></td>
                        <td><span class="badge <?= $badge ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td>
                            <?php if ($order['status'] === 'paid'): ?>
                            <a href="<?= SITE_URL ?>/pages/order-success.php?order_id=<?= $order['id'] ?>" class="btn btn--ghost btn--sm">View</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php elseif ($tab === 'courses'): ?>
            <div class="account-content__header">
                <h2>My Courses</h2>
                <span style="color:var(--gray-500);font-size:.9rem"><?= count($enrolled) ?> course<?= count($enrolled)!==1?'s':'' ?></span>
            </div>
            <?php if (empty($enrolled)): ?>
            <div style="text-align:center;padding:3rem 0">
                <i class="fas fa-book-open" style="font-size:3rem;color:var(--gray-300);display:block;margin-bottom:1rem"></i>
                <h3>No courses yet</h3>
                <p style="color:var(--gray-500);margin-bottom:1.5rem">Purchase a course to start learning.</p>
                <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--primary">Browse Courses</a>
            </div>
            <?php else: ?>
            <div class="grid grid--3" style="gap:1.25rem">
                <?php foreach ($enrolled as $ec): ?>
                <div class="course-card" style="box-shadow:none;border:1px solid var(--gray-300)">
                    <div class="course-card__img">
                        <img src="<?= course_img($ec['image']) ?>" alt="<?= e($ec['title']) ?>">
                    </div>
                    <div class="course-card__body">
                        <span class="course-card__category"><?= e($ec['category_name']) ?></span>
                        <h3 class="course-card__title"><?= e($ec['title']) ?></h3>
                        <p style="font-size:.8rem;color:var(--gray-500)">Enrolled <?= date('M j, Y', strtotime($ec['enrolled_at'])) ?></p>
                    </div>
                    <div class="course-card__footer">
                        <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($ec['slug']) ?>" class="btn btn--primary btn--sm btn--block">
                            <i class="fas fa-play"></i> Continue
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($tab === 'security'): ?>
            <div class="account-content__header"><h2>Security</h2></div>
            <div style="max-width:480px">
                <p style="color:var(--gray-500);margin-bottom:1.5rem">Manage your account security settings.</p>
                <div style="padding:1.25rem;background:var(--gray-100);border-radius:var(--radius-sm);margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <strong style="font-size:.92rem">Two-Factor Authentication</strong>
                        <p style="font-size:.8rem;color:var(--gray-500);margin:0">Adds an extra layer of protection</p>
                    </div>
                    <span class="badge badge--warning">Coming Soon</span>
                </div>
                <div style="padding:1.25rem;background:var(--gray-100);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:space-between">
                    <div>
                        <strong style="font-size:.92rem">Active Sessions</strong>
                        <p style="font-size:.8rem;color:var(--gray-500);margin:0">View devices where you're logged in</p>
                    </div>
                    <span class="badge badge--info">1 session</span>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
window.SITE_URL = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<script src="<?= SITE_URL ?>/assets/js/validation.js" defer></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
