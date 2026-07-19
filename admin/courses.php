<?php
// ============================================================
// admin/courses.php  –  Courses CRUD
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_admin();

$action    = sanitize($_GET['action'] ?? 'list');
$course_id = (int)($_GET['id'] ?? 0);
$msg       = null;

// ── DELETE ────────────────────────────────────────────────────
if ($action === 'delete' && $course_id) {
    if (!csrf_verify()) { $msg = ['type'=>'error','text'=>'Invalid token.']; }
    else {
        db()->prepare('UPDATE courses SET is_active=0 WHERE id=?')->execute([$course_id]);
        $msg = ['type'=>'success','text'=>'Course deactivated.'];
        $action = 'list';
    }
}

// ── SAVE (create / update) ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create','edit'])) {
    if (!csrf_verify()) { $msg = ['type'=>'error','text'=>'Invalid token.']; }
    else {
        $fields = [
            'category_id'   => (int)($_POST['category_id'] ?? 0),
            'instructor_id' => (int)($_POST['instructor_id'] ?? 1),
            'title'         => sanitize($_POST['title']       ?? ''),
            'slug'          => slugify($_POST['title']        ?? ''),
            'short_desc'    => sanitize($_POST['short_desc']  ?? ''),
            'description'   => $_POST['description']          ?? '',
            'price'         => (float)($_POST['price']        ?? 0),
            'sale_price'    => !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null,
            'duration'      => sanitize($_POST['duration']    ?? ''),
            'level'         => sanitize($_POST['level']       ?? 'beginner'),
            'language'      => sanitize($_POST['language']    ?? 'English'),
            'lessons_count' => (int)($_POST['lessons_count']  ?? 0),
            'is_featured'   => isset($_POST['is_featured']) ? 1 : 0,
            'is_active'     => 1,
        ];

        // Handle image upload
        $image = sanitize($_POST['current_image'] ?? 'default-course.jpg');
        if (!empty($_FILES['image']['name'])) {
            $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allowed) && $_FILES['image']['size'] < 2 * 1024 * 1024) {
                $filename = uniqid('course_') . '.' . $ext;
                $dest     = UPLOADS_PATH . '/courses/' . $filename;
                if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                    // Copy to assets/images/courses as well
                    $assetDest = ASSETS_PATH . '/images/courses/' . $filename;
                    copy($dest, $assetDest);
                    $image = $filename;
                }
            } else {
                $msg = ['type'=>'error','text'=>'Invalid image. Use JPG/PNG/WEBP under 2MB.'];
            }
        }
        $fields['image'] = $image;

        if (!$msg) {
            if ($action === 'create') {
                // Ensure unique slug
                $base = $fields['slug']; $n = 1;
                while (db()->prepare('SELECT 1 FROM courses WHERE slug=?')->execute([$fields['slug']]) &&
                       db()->prepare('SELECT 1 FROM courses WHERE slug=?')->fetchColumn()) {
                    $fields['slug'] = $base . '-' . $n++;
                }
                $cols = implode(',', array_keys($fields));
                $vals = implode(',', array_fill(0, count($fields), '?'));
                db()->prepare("INSERT INTO courses ($cols) VALUES ($vals)")
                   ->execute(array_values($fields));
                $course_id = db()->lastInsertId();
                $msg = ['type'=>'success','text'=>'Course created successfully.'];
            } else {
                $setClauses = implode(',', array_map(fn($k) => "$k=?", array_keys($fields)));
                $values     = array_values($fields);
                $values[]   = $course_id;
                db()->prepare("UPDATE courses SET $setClauses WHERE id=?")
                   ->execute($values);
                $msg = ['type'=>'success','text'=>'Course updated.'];
            }
            $action = 'edit';
        }
    }
}

// ── Load data for form ────────────────────────────────────────
$course     = null;
$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$instructors = db()->query('SELECT id,name FROM users WHERE role="admin" ORDER BY name')->fetchAll();

if (in_array($action, ['edit','view']) && $course_id) {
    $stmt = db()->prepare('SELECT * FROM courses WHERE id=?');
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) { $action = 'list'; $msg = ['type'=>'error','text'=>'Course not found.']; }
}

// ── LIST data ─────────────────────────────────────────────────
$courses_list = [];
if ($action === 'list') {
    $search = sanitize($_GET['q'] ?? '');
    $where  = $search ? 'WHERE c.title LIKE ?' : '';
    $params = $search ? ["%$search%"] : [];
    $stmt   = db()->prepare(
        "SELECT c.*, cat.name AS cat_name
         FROM courses c JOIN categories cat ON cat.id=c.category_id
         $where ORDER BY c.created_at DESC LIMIT 50"
    );
    $stmt->execute($params);
    $courses_list = $stmt->fetchAll();
}

$page_title = match($action) { 'create'=>'Add Course','edit'=>'Edit Course',default=>'Manage Courses' };
require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="admin-main">

        <?php if ($msg): ?>
        <div class="alert alert-<?= $msg['type']==='success'?'success':'danger' ?>"><?= e($msg['text']) ?><button class="alert-close">&times;</button></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
        <!-- ── LIST VIEW ───────────────────────────────── -->
        <div class="admin-topbar">
            <h1>Courses</h1>
            <a href="?action=create" class="btn btn--primary btn--sm"><i class="fas fa-plus"></i> Add Course</a>
        </div>
        <div class="admin-table-wrap">
            <div class="table-header">
                <form method="GET" style="display:flex;gap:.5rem">
                    <input type="hidden" name="action" value="list">
                    <input type="search" name="q" class="form-control" placeholder="Search courses…" value="<?= e($_GET['q']??'') ?>" style="max-width:240px">
                    <button class="btn btn--ghost btn--sm" type="submit"><i class="fas fa-search"></i></button>
                </form>
                <span style="color:var(--gray-500);font-size:.85rem"><?= count($courses_list) ?> courses</span>
            </div>
            <table class="data-table">
                <thead><tr><th>Title</th><th>Category</th><th>Price</th><th>Level</th><th>Featured</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($courses_list as $c): ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:.65rem">
                            <img src="<?= course_img($c['image']) ?>" alt="" style="width:48px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0">
                            <div>
                                <strong style="font-size:.88rem"><?= e($c['title']) ?></strong><br>
                                <span style="font-size:.75rem;color:var(--gray-500)"><?= $c['lessons_count'] ?> lessons</span>
                            </div>
                        </div>
                    </td>
                    <td><?= e($c['cat_name']) ?></td>
                    <td>
                        <?= money((float)($c['sale_price'] ?? $c['price'])) ?>
                        <?php if ($c['sale_price']): ?>
                        <span style="font-size:.75rem;color:var(--gray-500);text-decoration:line-through"><?= money((float)$c['price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= ucfirst($c['level']) ?></td>
                    <td><?= $c['is_featured'] ? '<span class="badge badge--success">Yes</span>' : '<span class="badge badge--gray">No</span>' ?></td>
                    <td><?= $c['is_active'] ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--danger">Inactive</span>' ?></td>
                    <td>
                        <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn--ghost btn--sm"><i class="fas fa-edit"></i></a>
                        <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($c['slug']) ?>" target="_blank" class="btn btn--ghost btn--sm"><i class="fas fa-eye"></i></a>
                        <form method="POST" action="?action=delete&id=<?= $c['id'] ?>" style="display:inline"
                              onsubmit="return confirm('Deactivate this course?')">
                            <?= csrf_field() ?>
                            <button class="btn btn--danger btn--sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($courses_list)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--gray-500)">No courses found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- ── CREATE / EDIT FORM ──────────────────────────── -->
        <div class="admin-topbar">
            <h1><?= $action==='create' ? 'Add New Course' : 'Edit Course' ?></h1>
            <a href="?action=list" class="btn btn--ghost btn--sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <form method="POST" enctype="multipart/form-data" action="?action=<?= $action ?><?= $course_id?"&id=$course_id":'' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="current_image" value="<?= e($course['image'] ?? 'default-course.jpg') ?>">

            <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start">
                <div>
                    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.75rem;margin-bottom:1.5rem">
                        <h3 style="font-size:1rem;font-weight:700;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-100)">Course Details</h3>
                        <div class="form-group">
                            <label>Title <span class="required">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?= e($course['title']??'') ?>" required placeholder="e.g. Complete JavaScript Guide">
                        </div>
                        <div class="form-group">
                            <label>Short Description <span class="required">*</span></label>
                            <textarea name="short_desc" class="form-control" rows="2" required><?= e($course['short_desc']??'') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Full Description (HTML allowed)</label>
                            <textarea name="description" class="form-control" rows="8"><?= e($course['description']??'') ?></textarea>
                        </div>
                    </div>

                    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.75rem">
                        <h3 style="font-size:1rem;font-weight:700;margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid var(--gray-100)">Media</h3>
                        <div class="form-group">
                            <label>Course Image <span style="font-size:.8rem;color:var(--gray-500)">(JPG/PNG/WEBP, max 2MB)</span></label>
                            <?php if (!empty($course['image']) && $course['image'] !== 'default-course.jpg'): ?>
                            <img src="<?= course_img($course['image']) ?>" alt="" style="width:160px;border-radius:6px;margin-bottom:.75rem">
                            <?php endif; ?>
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                </div>

                <div>
                    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;margin-bottom:1.5rem">
                        <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1.1rem">Publish</h3>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control" required>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($course['category_id']??0)==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Instructor</label>
                            <select name="instructor_id" class="form-control">
                                <?php foreach ($instructors as $ins): ?>
                                <option value="<?= $ins['id'] ?>" <?= ($course['instructor_id']??1)==$ins['id']?'selected':'' ?>><?= e($ins['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= !empty($course['is_featured'])?'checked':'' ?>>
                            <label for="is_featured">Featured on Homepage</label>
                        </div>
                    </div>

                    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;margin-bottom:1.5rem">
                        <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1.1rem">Pricing</h3>
                        <div class="form-group">
                            <label>Regular Price (<?= CURRENCY_SYM ?>)</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= number_format((float)($course['price']??0),2,'.','') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Sale Price (leave blank for no discount)</label>
                            <input type="number" name="sale_price" class="form-control" step="0.01" min="0" value="<?= !empty($course['sale_price']) ? number_format((float)$course['sale_price'],2,'.','') : '' ?>">
                        </div>
                    </div>

                    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem">
                        <h3 style="font-size:.95rem;font-weight:700;margin-bottom:1.1rem">Course Info</h3>
                        <div class="form-group">
                            <label>Level</label>
                            <select name="level" class="form-control">
                                <?php foreach (['beginner','intermediate','advanced'] as $lvl): ?>
                                <option value="<?= $lvl ?>" <?= ($course['level']??'beginner')===$lvl?'selected':'' ?>><?= ucfirst($lvl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Duration (e.g. "12 hours")</label>
                            <input type="text" name="duration" class="form-control" value="<?= e($course['duration']??'') ?>" placeholder="12 hours">
                        </div>
                        <div class="form-group">
                            <label>Number of Lessons</label>
                            <input type="number" name="lessons_count" class="form-control" min="0" value="<?= (int)($course['lessons_count']??0) ?>">
                        </div>
                        <div class="form-group">
                            <label>Language</label>
                            <input type="text" name="language" class="form-control" value="<?= e($course['language']??'English') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:1.5rem;display:flex;gap:1rem">
                <button type="submit" class="btn btn--primary btn--lg"><i class="fas fa-save"></i> <?= $action==='create'?'Create Course':'Save Changes' ?></button>
                <a href="?action=list" class="btn btn--ghost btn--lg">Cancel</a>
            </div>
        </form>
        <?php endif; ?>

    </main>
</div>

<script>window.SITE_URL='<?= SITE_URL ?>';window.CSRF_TOKEN='<?= csrf_token() ?>';</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
