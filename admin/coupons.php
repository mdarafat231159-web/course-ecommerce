<?php
// ============================================================
// admin/coupons.php  –  Coupon management
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$msg    = null;
$action = sanitize($_GET['action'] ?? 'list');

// Save coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $code  = strtoupper(sanitize($_POST['code']    ?? ''));
    $type  = sanitize($_POST['type']               ?? 'percent');
    $value = (float)($_POST['value']               ?? 0);
    $max   = !empty($_POST['max_uses']) ? (int)$_POST['max_uses'] : null;
    $exp   = !empty($_POST['expires_at']) ? sanitize($_POST['expires_at']) : null;

    if (!$code || $value <= 0) {
        $msg = ['type'=>'error','text'=>'Code and value are required.'];
    } else {
        $id = (int)($_POST['coupon_id'] ?? 0);
        if ($id) {
            db()->prepare('UPDATE coupons SET code=?,type=?,value=?,max_uses=?,expires_at=? WHERE id=?')
               ->execute([$code,$type,$value,$max,$exp,$id]);
        } else {
            db()->prepare('INSERT INTO coupons (code,type,value,max_uses,expires_at) VALUES (?,?,?,?,?)')
               ->execute([$code,$type,$value,$max,$exp]);
        }
        $msg = ['type'=>'success','text'=>'Coupon saved.'];
        $action = 'list';
    }
}

// Toggle active
if ($action === 'toggle' && isset($_GET['id'])) {
    $cid = (int)$_GET['id'];
    db()->prepare('UPDATE coupons SET is_active = NOT is_active WHERE id=?')->execute([$cid]);
    $action = 'list';
    $msg    = ['type'=>'success','text'=>'Coupon status toggled.'];
}

// Edit
$edit_coupon = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM coupons WHERE id=?');
    $stmt->execute([(int)$_GET['id']]);
    $edit_coupon = $stmt->fetch();
}

$coupons = db()->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();

$page_title = 'Coupons';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-main">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg['type']==='success'?'success':'danger' ?>"><?= e($msg['text']) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <div class="admin-topbar">
            <h1>Coupons</h1>
            <button onclick="document.getElementById('couponForm').style.display='block'" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> New Coupon</button>
        </div>

        <!-- Add/Edit Form -->
        <div id="couponForm" style="<?= ($action==='edit'||$action==='create')?'display:block':'display:none' ?>;background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;margin-bottom:1.5rem">
            <h3 style="font-size:1rem;font-weight:700;margin-bottom:1.1rem"><?= $edit_coupon ? 'Edit Coupon' : 'New Coupon' ?></h3>
            <form method="POST">
                <?= csrf_field() ?>
                <?php if ($edit_coupon): ?>
                <input type="hidden" name="coupon_id" value="<?= $edit_coupon['id'] ?>">
                <?php endif; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:1rem">
                    <div class="form-group">
                        <label>Code</label>
                        <input type="text" name="code" class="form-control" value="<?= e($edit_coupon['code']??'') ?>" placeholder="SUMMER50" required style="text-transform:uppercase">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            <option value="percent" <?= ($edit_coupon['type']??'percent')==='percent'?'selected':'' ?>>Percentage (%)</option>
                            <option value="fixed"   <?= ($edit_coupon['type']??'')==='fixed'  ?'selected':'' ?>>Fixed Amount ($)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Value</label>
                        <input type="number" name="value" class="form-control" step="0.01" min="0" value="<?= $edit_coupon['value']??'' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Max Uses (blank = unlimited)</label>
                        <input type="number" name="max_uses" class="form-control" min="1" value="<?= $edit_coupon['max_uses']??'' ?>">
                    </div>
                </div>
                <div class="form-group" style="max-width:200px">
                    <label>Expires At</label>
                    <input type="date" name="expires_at" class="form-control" value="<?= $edit_coupon['expires_at']??'' ?>">
                </div>
                <div style="display:flex;gap:.75rem">
                    <button class="btn btn--primary btn--sm"><i class="fas fa-save"></i> Save Coupon</button>
                    <button type="button" onclick="document.getElementById('couponForm').style.display='none'" class="btn btn--ghost btn--sm">Cancel</button>
                </div>
            </form>
        </div>

        <div class="admin-table-wrap">
            <table class="data-table">
                <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($coupons as $c): ?>
                <tr>
                    <td><strong><?= e($c['code']) ?></strong></td>
                    <td><?= ucfirst($c['type']) ?></td>
                    <td><?= $c['type']==='percent' ? $c['value'].'%' : money((float)$c['value']) ?></td>
                    <td><?= $c['used_count'] ?><?= $c['max_uses'] ? ' / '.$c['max_uses'] : '' ?></td>
                    <td><?= $c['expires_at'] ? date('M j, Y', strtotime($c['expires_at'])) : '—' ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--danger">Inactive</span>' ?></td>
                    <td>
                        <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i></a>
                        <a href="?action=toggle&id=<?= $c['id'] ?>" class="btn btn--ghost btn--sm"><?= $c['is_active']?'Disable':'Enable' ?></a>
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
