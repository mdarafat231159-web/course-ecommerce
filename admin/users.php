<?php
// ============================================================
// admin/users.php  –  User management
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$msg    = null;
$search = sanitize($_GET['q'] ?? '');
$role   = sanitize($_GET['role'] ?? '');

// Toggle active status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $toggle = (int)($_POST['toggle_active'] ?? 0);
    if ($uid && $uid !== $_SESSION['user_id']) {
        db()->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$toggle, $uid]);
        $msg = ['type'=>'success','text'=>'User status updated.'];
    }
}

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($role)   { $where[] = 'role=?'; $params[] = $role; }
$whereSQL = implode(' AND ', $where);

$users = db()->prepare(
    "SELECT u.*,
       (SELECT COUNT(*) FROM enrollments WHERE user_id=u.id) AS courses_owned,
       (SELECT COUNT(*) FROM orders WHERE user_id=u.id AND status='paid') AS total_orders
     FROM users u WHERE $whereSQL ORDER BY u.created_at DESC LIMIT 100"
);
$users->execute($params);
$users = $users->fetchAll();

$page_title = 'Users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-main">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg['type']==='success'?'success':'danger' ?>"><?= e($msg['text']) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <div class="admin-topbar">
            <h1>Users</h1>
            <span style="color:var(--gray-500);font-size:.9rem"><?= count($users) ?> users</span>
        </div>

        <div class="admin-table-wrap">
            <div class="table-header">
                <form method="GET" style="display:flex;gap:.5rem">
                    <input type="search" name="q" class="form-control" placeholder="Search users…" value="<?= e($search) ?>" style="max-width:200px">
                    <select name="role" class="form-control" style="max-width:130px">
                        <option value="">All Roles</option>
                        <option value="student" <?= $role==='student'?'selected':'' ?>>Student</option>
                        <option value="admin"   <?= $role==='admin'  ?'selected':'' ?>>Admin</option>
                    </select>
                    <button class="btn btn--ghost btn--sm"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <table class="data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Courses</th><th>Orders</th><th>Joined</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <div class="avatar" style="width:32px;height:32px;font-size:.8rem"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                            <strong><?= e($u['name']) ?></strong>
                        </div>
                    </td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge <?= $u['role']==='admin'?'badge--info':'badge--gray' ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td><?= $u['courses_owned'] ?></td>
                    <td><?= $u['total_orders'] ?></td>
                    <td style="color:var(--gray-500);font-size:.82rem"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td><?= $u['is_active'] ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--danger">Suspended</span>' ?></td>
                    <td>
                        <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="toggle_active" value="<?= $u['is_active'] ? 0 : 1 ?>">
                            <button class="btn btn--<?= $u['is_active']?'danger':'primary' ?> btn--sm"><?= $u['is_active']?'Suspend':'Activate' ?></button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:.78rem;color:var(--gray-500)">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<script>window.SITE_URL='<?= SITE_URL ?>';window.CSRF_TOKEN='<?= csrf_token() ?>';</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
