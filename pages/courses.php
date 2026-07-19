<?php
// ============================================================
// pages/courses.php  –  Course catalogue with filter/search
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

// ── Filters from GET ─────────────────────────────────────────
$search   = sanitize($_GET['q']        ?? '');
$cat_slug = sanitize($_GET['category'] ?? '');
$level    = sanitize($_GET['level']    ?? '');
$sort     = sanitize($_GET['sort']     ?? 'featured');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9;

// Resolve category id from slug
$cat_id = null;
$cat_name = 'All Courses';
if ($cat_slug) {
    $catRow = db()->prepare('SELECT id, name FROM categories WHERE slug = ?');
    $catRow->execute([$cat_slug]);
    $catRow = $catRow->fetch();
    if ($catRow) { $cat_id = $catRow['id']; $cat_name = $catRow['name']; }
}

// ── Build WHERE + bindings ────────────────────────────────────
$where  = ['c.is_active = 1'];
$params = [];
if ($search)  { $where[] = '(c.title LIKE ? OR c.short_desc LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($cat_id)  { $where[] = 'c.category_id = ?';  $params[] = $cat_id; }
if ($level)   { $where[] = 'c.level = ?';         $params[] = $level; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Sort
$orderSQL = match($sort) {
    'newest'    => 'ORDER BY c.created_at DESC',
    'price_asc' => 'ORDER BY COALESCE(c.sale_price,c.price) ASC',
    'price_desc'=> 'ORDER BY COALESCE(c.sale_price,c.price) DESC',
    'rating'    => 'ORDER BY c.rating DESC',
    default     => 'ORDER BY c.is_featured DESC, c.rating DESC',
};

// Count
$countStmt = db()->prepare("SELECT COUNT(*) FROM courses c $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $per_page, $page);

// Fetch courses
$sql = "SELECT c.*, cat.name AS category_name, u.name AS instructor_name
        FROM courses c
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.instructor_id = u.id
        $whereSQL $orderSQL
        LIMIT $per_page OFFSET {$pag['offset']}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// All categories for sidebar
$allCats = db()->query(
    'SELECT cat.*, COUNT(c.id) AS cnt FROM categories cat
     LEFT JOIN courses c ON c.category_id = cat.id AND c.is_active=1
     GROUP BY cat.id ORDER BY cat.name'
)->fetchAll();

$page_title = $cat_name . ' Courses';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="<?= SITE_URL ?>">Home</a> <i class="fas fa-chevron-right" style="font-size:.7rem"></i>
            <span>Courses</span>
        </nav>
        <h1><?= e($cat_name) ?></h1>
        <p><?= $total ?> course<?= $total !== 1 ? 's' : '' ?> found
            <?= $search ? ' for "<em>' . e($search) . '</em>"' : '' ?>
        </p>
    </div>
</div>

<div class="container" style="padding-bottom:4rem">
    <div style="display:grid;grid-template-columns:240px 1fr;gap:2rem;align-items:start">

        <!-- ── Sidebar Filters ────────────────────────────── -->
        <aside>
            <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;position:sticky;top:calc(var(--nav-h)+1rem)">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:1.25rem">Filters</h3>

                <!-- Search -->
                <form method="GET" action="">
                    <?php if ($cat_slug): ?><input type="hidden" name="category" value="<?= e($cat_slug) ?>"><?php endif; ?>
                    <div class="form-group">
                        <label>Search</label>
                        <div class="input-group">
                            <input type="search" name="q" class="form-control" placeholder="Keyword…" value="<?= e($search) ?>">
                            <button class="btn btn--primary" type="submit"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </form>

                <!-- Categories -->
                <div class="form-group">
                    <label>Category</label>
                    <ul style="list-style:none;display:flex;flex-direction:column;gap:.35rem">
                        <li><a href="<?= SITE_URL ?>/pages/courses.php<?= $search ? '?q='.urlencode($search) : '' ?>"
                               class="pill <?= !$cat_slug ? 'active' : '' ?>" style="justify-content:space-between">
                            All <span style="font-size:.78rem;opacity:.6">(<?= $total ?>)</span></a></li>
                        <?php foreach ($allCats as $cat): ?>
                        <li><a href="<?= SITE_URL ?>/pages/courses.php?category=<?= e($cat['slug']) ?><?= $search ? '&q='.urlencode($search) : '' ?>"
                               class="pill <?= $cat_slug === $cat['slug'] ? 'active' : '' ?>"
                               style="justify-content:space-between">
                            <?= e($cat['name']) ?> <span style="font-size:.78rem;opacity:.6">(<?= $cat['cnt'] ?>)</span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Level -->
                <div class="form-group">
                    <label>Level</label>
                    <?php foreach (['beginner','intermediate','advanced'] as $lvl): ?>
                    <div class="form-check" style="margin-bottom:.4rem">
                        <input type="checkbox" id="lvl-<?= $lvl ?>" onchange="applyFilter('level','<?= $lvl ?>')"
                               <?= $level === $lvl ? 'checked' : '' ?>>
                        <label for="lvl-<?= $lvl ?>"><?= ucfirst($lvl) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sort -->
                <div class="form-group">
                    <label>Sort By</label>
                    <select class="form-control" onchange="applyFilter('sort',this.value)">
                        <?php foreach (['featured'=>'Featured','newest'=>'Newest','rating'=>'Highest Rated','price_asc'=>'Price: Low to High','price_desc'=>'Price: High to Low'] as $val=>$label): ?>
                        <option value="<?= $val ?>" <?= $sort===$val?'selected':'' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </aside>

        <!-- ── Course Grid ────────────────────────────────── -->
        <div>
            <?php if (empty($courses)): ?>
            <div class="cart-empty" style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow)">
                <i class="fas fa-search"></i>
                <h3>No courses found</h3>
                <p>Try a different search term or category.</p>
                <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--primary">Clear Filters</a>
            </div>
            <?php else: ?>
            <div class="grid grid--3">
                <?php foreach ($courses as $course):
                    $price = $course['sale_price'] ?? $course['price'];
                ?>
                <article class="course-card">
                    <div class="course-card__img">
                        <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($course['slug']) ?>">
                            <img src="<?= course_img($course['image']) ?>" alt="<?= e($course['title']) ?>" loading="lazy">
                        </a>
                        <?php if ($course['sale_price']): ?>
                        <span class="course-card__badge"><?= discount_percent($course['price'],$course['sale_price']) ?>% OFF</span>
                        <?php endif; ?>
                    </div>
                    <div class="course-card__body">
                        <span class="course-card__category"><?= e($course['category_name']) ?></span>
                        <h3 class="course-card__title">
                            <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($course['slug']) ?>"><?= e($course['title']) ?></a>
                        </h3>
                        <p class="course-card__instructor">by <?= e($course['instructor_name']) ?></p>
                        <div class="course-card__meta">
                            <?= stars($course['rating']) ?>
                            <span class="rating-text"><?= number_format($course['rating'],1) ?></span>
                            <span>(<?= number_format($course['reviews_count']) ?>)</span>
                            <span><i class="fas fa-clock"></i><?= e($course['duration'] ?? '') ?></span>
                        </div>
                    </div>
                    <div class="course-card__footer">
                        <div class="course-card__price">
                            <span class="price-current"><?= money((float)$price) ?></span>
                            <?php if ($course['sale_price']): ?>
                            <span class="price-original"><?= money((float)$course['price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="btn btn--primary btn--sm" data-add-cart="<?= $course['id'] ?>">
                            <i class="fas fa-cart-plus"></i>
                        </button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pag['last_page'] > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php
                $qs = http_build_query(array_filter(['q'=>$search,'category'=>$cat_slug,'level'=>$level,'sort'=>$sort]));
                if ($pag['has_prev']): ?>
                <a href="?<?= $qs ?>&page=<?= $page-1 ?>" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
                <?php endif;
                for ($i = max(1,$page-2); $i <= min($pag['last_page'],$page+2); $i++): ?>
                <?php if ($i === $page): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endif; endfor;
                if ($pag['has_next']): ?>
                <a href="?<?= $qs ?>&page=<?= $page+1 ?>" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
function applyFilter(key, val) {
    const url = new URL(window.location.href);
    url.searchParams.set(key, val);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
