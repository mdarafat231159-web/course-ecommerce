<?php
// ============================================================
// pages/course.php  –  Course detail / purchase page
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';

$slug = sanitize($_GET['slug'] ?? '');
if (!$slug) redirect('/pages/courses.php');

$stmt = db()->prepare(
    'SELECT c.*, cat.name AS category_name, cat.slug AS category_slug,
            u.name AS instructor_name
     FROM courses c
     JOIN categories cat ON c.category_id = cat.id
     JOIN users u ON c.instructor_id = u.id
     WHERE c.slug = ? AND c.is_active = 1'
);
$stmt->execute([$slug]);
$course = $stmt->fetch();
if (!$course) redirect('/pages/courses.php');

// Curriculum
$curriculum_raw = db()->prepare(
    'SELECT * FROM course_curriculum WHERE course_id = ? ORDER BY sort_order'
);
$curriculum_raw->execute([$course['id']]);
$lessons = $curriculum_raw->fetchAll();
$sections = [];
foreach ($lessons as $l) {
    $sections[$l['section']][] = $l;
}

// Related courses
$related = db()->prepare(
    'SELECT c.*, cat.name AS category_name
     FROM courses c JOIN categories cat ON c.category_id=cat.id
     WHERE c.category_id=? AND c.id!=? AND c.is_active=1
     ORDER BY c.rating DESC LIMIT 4'
);
$related->execute([$course['category_id'], $course['id']]);
$related = $related->fetchAll();

// Is course already in cart / owned?
$in_cart = false;
$owned   = false;
if (is_logged_in()) {
    $uid = $_SESSION['user_id'];
    $inCartStmt = db()->prepare('SELECT 1 FROM cart WHERE user_id=? AND course_id=?');
    $inCartStmt->execute([$uid, $course['id']]);
    $in_cart = (bool)$inCartStmt->fetchColumn();

    $ownedStmt = db()->prepare('SELECT 1 FROM enrollments WHERE user_id=? AND course_id=?');
    $ownedStmt->execute([$uid, $course['id']]);
    $owned = (bool)$ownedStmt->fetchColumn();
}

$price = (float)($course['sale_price'] ?? $course['price']);
$page_title = $course['title'];
$page_desc  = strip_tags($course['short_desc']);
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Course Hero -->
<div class="course-hero">
    <div class="container course-hero__inner">
        <div>
            <nav class="course-hero__breadcrumb" aria-label="breadcrumb">
                <a href="<?= SITE_URL ?>">Home</a> /
                <a href="<?= SITE_URL ?>/pages/courses.php">Courses</a> /
                <a href="<?= SITE_URL ?>/pages/courses.php?category=<?= e($course['category_slug']) ?>"><?= e($course['category_name']) ?></a>
            </nav>
            <h1><?= e($course['title']) ?></h1>
            <p style="opacity:.85;font-size:1.05rem;margin:.75rem 0 0"><?= e($course['short_desc']) ?></p>
            <div class="course-hero__meta">
                <span><?= stars($course['rating']) ?> <strong><?= number_format($course['rating'],1) ?></strong> (<?= number_format($course['reviews_count']) ?> reviews)</span>
                <span><i class="fas fa-users"></i> <?= number_format($course['reviews_count'] * 3) ?>+ students</span>
                <span><i class="fas fa-clock"></i> <?= e($course['duration'] ?? 'N/A') ?></span>
                <span><i class="fas fa-layer-group"></i> <?= ucfirst($course['level']) ?></span>
                <span><i class="fas fa-globe"></i> <?= e($course['language']) ?></span>
                <span><i class="fas fa-book-open"></i> <?= $course['lessons_count'] ?> lessons</span>
            </div>
            <div class="course-hero__instructor">
                <div class="avatar" style="width:40px;height:40px;font-size:1rem"><?= strtoupper(substr($course['instructor_name'],0,1)) ?></div>
                <div>Created by <strong><?= e($course['instructor_name']) ?></strong></div>
            </div>
        </div>

        <!-- Purchase Card -->
        <div class="course-purchase-card">
            <div class="course-purchase-card__preview">
                <img src="<?= course_img($course['image']) ?>" alt="<?= e($course['title']) ?>">
                <div class="play-btn"><i class="fas fa-play-circle"></i></div>
            </div>
            <div class="course-purchase-card__body">
                <div class="course-purchase-card__price">
                    <span class="current"><?= money($price) ?></span>
                    <?php if ($course['sale_price']): ?>
                    <span class="original"><?= money((float)$course['price']) ?></span>
                    <span class="discount"><?= discount_percent($course['price'],$course['sale_price']) ?>% off</span>
                    <?php endif; ?>
                </div>

                <?php if ($owned): ?>
                <a href="<?= SITE_URL ?>/pages/account.php?tab=courses" class="btn btn--primary btn--block btn--lg">
                    <i class="fas fa-play"></i> Go to Course
                </a>
                <?php elseif ($in_cart): ?>
                <a href="<?= SITE_URL ?>/pages/cart.php" class="btn btn--secondary btn--block btn--lg">
                    <i class="fas fa-shopping-cart"></i> View in Cart
                </a>
                <?php else: ?>
                <button class="btn btn--primary btn--block btn--lg" data-add-cart="<?= $course['id'] ?>" id="addToCartBtn">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <a href="<?= SITE_URL ?>/pages/checkout.php?buy_now=<?= $course['id'] ?>" class="btn btn--outline btn--block mt-1">
                    Buy Now
                </a>
                <?php endif; ?>

                <ul class="course-includes">
                    <li><i class="fas fa-infinity"></i> Lifetime access</li>
                    <li><i class="fas fa-mobile-alt"></i> Access on mobile &amp; desktop</li>
                    <li><i class="fas fa-certificate"></i> Certificate of completion</li>
                    <li><i class="fas fa-undo"></i> 30-day money-back guarantee</li>
                    <li><i class="fas fa-video"></i> <?= $course['lessons_count'] ?> lessons &amp; <?= e($course['duration'] ?? '') ?> content</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Course Content Tabs -->
<div class="container" style="padding:2rem 0 4rem">
    <div class="course-tabs" role="tablist">
        <button class="course-tab active" data-tab="overview"  role="tab">Overview</button>
        <button class="course-tab"        data-tab="curriculum" role="tab">Curriculum</button>
        <button class="course-tab"        data-tab="instructor" role="tab">Instructor</button>
    </div>

    <!-- Overview -->
    <div class="tab-panel active" id="tab-overview">
        <div style="max-width:820px">
            <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:1rem">About This Course</h2>
            <div style="color:var(--gray-700);line-height:1.8"><?= $course['description'] ?></div>
        </div>
    </div>

    <!-- Curriculum -->
    <div class="tab-panel" id="tab-curriculum">
        <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:1.25rem">
            Course Content
            <span style="font-size:.9rem;font-weight:400;color:var(--gray-500);margin-left:.75rem">
                <?= count($lessons) ?> lessons &bull; <?= e($course['duration'] ?? '') ?>
            </span>
        </h2>
        <?php if (empty($sections)): ?>
        <p style="color:var(--gray-500)">Curriculum details coming soon.</p>
        <?php else: ?>
        <?php foreach ($sections as $sectionName => $sectionLessons): ?>
        <div class="curriculum-section">
            <div class="curriculum-section__header">
                <span><i class="fas fa-folder" style="color:var(--primary);margin-right:.5rem"></i><?= e($sectionName) ?></span>
                <span style="display:flex;align-items:center;gap:.5rem;color:var(--gray-500);font-size:.82rem">
                    <?= count($sectionLessons) ?> lessons
                    <i class="fas fa-chevron-down"></i>
                </span>
            </div>
            <div class="curriculum-section__lessons" style="display:block">
                <?php foreach ($sectionLessons as $lesson): ?>
                <div class="curriculum-lesson">
                    <i class="fas fa-play-circle"></i>
                    <span><?= e($lesson['lesson']) ?></span>
                    <?php if ($lesson['duration']): ?>
                    <span class="duration"><?= e($lesson['duration']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Instructor -->
    <div class="tab-panel" id="tab-instructor">
        <div style="display:flex;align-items:flex-start;gap:1.5rem;max-width:700px">
            <div class="avatar" style="width:72px;height:72px;font-size:1.8rem;font-weight:800;flex-shrink:0">
                <?= strtoupper(substr($course['instructor_name'],0,1)) ?>
            </div>
            <div>
                <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:.25rem"><?= e($course['instructor_name']) ?></h2>
                <p style="color:var(--primary);font-weight:600;margin-bottom:.75rem">Senior Instructor &amp; Industry Expert</p>
                <p style="color:var(--gray-700);line-height:1.8">Passionate educator with 10+ years of industry experience. Has helped over 50,000 students across multiple platforms master in-demand tech skills through clear, practical, and project-based teaching.</p>
            </div>
        </div>
    </div>

    <!-- Related Courses -->
    <?php if ($related): ?>
    <div style="margin-top:4rem">
        <h2 style="font-size:1.4rem;font-weight:700;margin-bottom:1.5rem">More in <?= e($course['category_name']) ?></h2>
        <div class="grid grid--4">
            <?php foreach ($related as $r):
                $rp = $r['sale_price'] ?? $r['price'];
            ?>
            <article class="course-card">
                <div class="course-card__img">
                    <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($r['slug']) ?>">
                        <img src="<?= course_img($r['image']) ?>" alt="<?= e($r['title']) ?>" loading="lazy">
                    </a>
                </div>
                <div class="course-card__body">
                    <span class="course-card__category"><?= e($r['category_name']) ?></span>
                    <h3 class="course-card__title">
                        <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($r['slug']) ?>"><?= e($r['title']) ?></a>
                    </h3>
                    <div class="course-card__meta">
                        <?= stars($r['rating']) ?>
                        <span class="rating-text"><?= number_format($r['rating'],1) ?></span>
                    </div>
                </div>
                <div class="course-card__footer">
                    <span class="price-current"><?= money((float)$rp) ?></span>
                    <button class="btn btn--primary btn--sm" data-add-cart="<?= $r['id'] ?>">
                        <i class="fas fa-cart-plus"></i>
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
