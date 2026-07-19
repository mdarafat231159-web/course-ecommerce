<?php
// ============================================================
// admin/categories.php  –  Category management
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$msg = null;

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id   = (int)($_POST['cat_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');
    $icon = sanitize($_POST['icon'] ?? 'fas fa-book');
    if (!$name) {
        $msg = ['type'=>'error','text'=>'Category name is required.'];
    } else {
        $slug = slugify($name);
        if ($id) {
            db()->prepare('UPDATE categories SET name=?,slug=?,description=?,icon=? WHERE id=?')
               ->execute([$name,$slug,$desc,$icon,$id]);
        } else {
            db()->prepare('INSERT INTO categories (name,slug,description,icon) VALUES (?,?,?,?)')
               ->execute([$name,$slug,$desc,$icon]);
        }
        $msg = ['type'=>'success','text'=>'Category saved.'];
    }
}

// Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $count = db()->prepare('SELECT COUNT(*) FROM courses WHERE category_id=?');
    $count->execute([$delId]);
    if ($count->fetchColumn() > 0) {
        $msg = ['type'=>'error','text'=>'Cannot delete category that has courses.'];
    } else {
        db()->prepare('DELETE FROM categories WHERE id=?')->execute([$delId]);
        $msg = ['type'=>'success','text'=>'Category deleted.'];
    }
}

$categories = db()->query(
    'SELECT cat.*, COUNT(c.id) AS course_count
     FROM categories cat LEFT JOIN courses c ON c.category_id=cat.id AND c.is_active=1
     GROUP BY cat.id ORDER BY cat.name'
)->fetchAll();

$page_title = 'Categories';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-main">
        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg['type']==='success'?'success':'danger' ?>"><?= e($msg['text']) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>
        <div class="admin-topbar"><h1>Categories</h1></div>

        <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem">
            <div class="admin-table-wrap">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Slug</th><th>Icon</th><th>Courses</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong><?= e($cat['name']) ?></strong></td>
                        <td style="font-size:.82rem;color:var(--gray-500)"><?= e($cat['slug']) ?></td>
                        <td><i class="<?= e($cat['icon']) ?>"></i></td>
                        <td><?= $cat['course_count'] ?></td>
                        <td>
                            <button onclick="fillForm(<?= htmlspecialchars(json_encode($cat)) ?>)" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i></button>
                            <?php if ($cat['course_count'] == 0): ?>
                            <a href="?delete=<?= $cat['id'] ?>" class="btn btn--danger btn--sm" onclick="return confirm('Delete category?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:1.1rem" id="formTitle">Add Category</h3>
                <form method="POST" id="catForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cat_id" id="catId" value="0">
                    <div class="form-group">
                        <label>Name <span class="required">*</span></label>
                        <input type="text" name="name" id="catName" class="form-control" placeholder="e.g. Web Development" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="catDesc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Font Awesome Icon Class</label>
                        <input type="text" name="icon" id="catIcon" class="form-control" placeholder="fas fa-code">
                    </div>
                    <div style="display:flex;gap:.75rem">
                        <button class="btn btn--primary btn--sm"><i class="fas fa-save"></i> Save</button>
                        <button type="button" onclick="resetForm()" class="btn btn--ghost btn--sm">Clear</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<script>
window.SITE_URL='<?= SITE_URL ?>';
window.CSRF_TOKEN='<?= csrf_token() ?>';
function fillForm(cat) {
    document.getElementById('catId').value   = cat.id;
    document.getElementById('catName').value = cat.name;
    document.getElementById('catDesc').value = cat.description || '';
    document.getElementById('catIcon').value = cat.icon || 'fas fa-book';
    document.getElementById('formTitle').textContent = 'Edit Category';
}
function resetForm() {
    document.getElementById('catId').value   = '0';
    document.getElementById('catForm').reset();
    document.getElementById('formTitle').textContent = 'Add Category';
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
