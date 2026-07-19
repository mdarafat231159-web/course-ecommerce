<?php
// ============================================================
// index.php  –  Homepage
// ============================================================
require_once __DIR__ . '/config/bootstrap.php';

$page_title = 'Learn In-Demand Skills Online';
$page_desc  = 'Browse 500+ courses in Web Development, Data Science, Design and more. Expert instructors, lifetime access.';

// Featured courses
$featured = db()->query(
    'SELECT c.*, cat.name AS category_name, u.name AS instructor_name
     FROM courses c
     JOIN categories cat ON c.category_id = cat.id
     JOIN users u ON c.instructor_id = u.id
     WHERE c.is_featured = 1 AND c.is_active = 1
     ORDER BY c.rating DESC LIMIT 8'
)->fetchAll();

// All categories with course count
$categories = db()->query(
    'SELECT cat.*, COUNT(c.id) AS course_count
     FROM categories cat
     LEFT JOIN courses c ON c.category_id = cat.id AND c.is_active = 1
     GROUP BY cat.id ORDER BY cat.name'
)->fetchAll();

// Stats
$stats = db()->query(
    'SELECT
        (SELECT COUNT(*) FROM courses  WHERE is_active=1) AS total_courses,
        (SELECT COUNT(*) FROM users    WHERE role="student") AS total_students,
        (SELECT COUNT(*) FROM categories) AS total_categories'
)->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__content">
            <h1>Learn Skills That <span>Shape Your Future</span></h1>
            <p>Join thousands of learners. Access expert-led courses in tech, design, and business — anytime, anywhere.</p>
            <div class="hero__actions">
                <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--secondary btn--lg">
                    <i class="fas fa-search"></i> Explore Courses
                </a>
                <?php if (!is_logged_in()): ?>
                <a href="<?= SITE_URL ?>/auth/register.php" class="btn btn--outline btn--lg" style="color:#fff;border-color:rgba(255,255,255,.5)">
                    <i class="fas fa-user-plus"></i> Join Free
                </a>
                <?php endif; ?>
            </div>
            <div class="hero__stats">
                <div class="hero__stat">
                    <strong><?= number_format($stats['total_courses']) ?>+</strong>
                    <span>Courses</span>
                </div>
                <div class="hero__stat">
                    <strong><?= number_format($stats['total_students'] ?: 12400) ?>+</strong>
                    <span>Students</span>
                </div>
                <div class="hero__stat">
                    <strong><?= number_format($stats['total_categories']) ?>+</strong>
                    <span>Categories</span>
                </div>
                <div class="hero__stat">
                    <strong>4.8★</strong>
                    <span>Avg Rating</span>
                </div>
            </div>
        </div>
        <div class="hero__visual">
            <div class="hero__card">
                <i class="fas fa-code"></i>
                <h3>Web Development</h3>
                <p>HTML, CSS, JS, PHP & more</p>
            </div>
            <div class="hero__card">
                <i class="fas fa-brain"></i>
                <h3>Data Science</h3>
                <p>Python, ML & AI</p>
            </div>
            <div class="hero__card">
                <i class="fas fa-mobile-alt"></i>
                <h3>Mobile Apps</h3>
                <p>iOS, Android & Flutter</p>
            </div>
            <div class="hero__card hero__card--large">
                <i class="fas fa-certificate"></i>
                <div>
                    <h3>Earn Certificates</h3>
                    <p>Shareable credentials for every completed course</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Categories ─────────────────────────────────────────── -->
<section class="section section--gray">
    <div class="container">
        <div class="section__header">
            <h2>Browse by Category</h2>
            <p>Find courses that match your interests and career goals</p>
        </div>
        <div class="category-pills">
            <a href="<?= SITE_URL ?>/pages/courses.php" class="pill <?= empty($_GET['category']) ? 'active' : '' ?>">
                <i class="fas fa-th"></i> All Courses
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= SITE_URL ?>/pages/courses.php?category=<?= e($cat['slug']) ?>" class="pill">
                <i class="<?= e($cat['icon']) ?>"></i>
                <?= e($cat['name']) ?>
                <span style="opacity:.6;font-size:.78rem">(<?= $cat['course_count'] ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Featured Courses ───────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="section__header">
            <h2>Featured Courses</h2>
            <p>Hand-picked by our team — best quality, best value</p>
        </div>
        <div class="grid grid--4">
            <?php foreach ($featured as $course):
                $price = $course['sale_price'] ?? $course['price'];
            ?>
            <article class="course-card">
                <div class="course-card__img">
                    <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($course['slug']) ?>">
                        <img src="<?= course_img($course['image']) ?>"
                             alt="<?= e($course['title']) ?>"
                             loading="lazy">
                    </a>
                    <?php if ($course['sale_price']): ?>
                    <span class="course-card__badge">
                        <?= discount_percent($course['price'], $course['sale_price']) ?>% OFF
                    </span>
                    <?php endif; ?>
                    <button class="course-card__wishlist" aria-label="Add to wishlist">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                <div class="course-card__body">
                    <span class="course-card__category"><?= e($course['category_name']) ?></span>
                    <h3 class="course-card__title">
                        <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($course['slug']) ?>">
                            <?= e($course['title']) ?>
                        </a>
                    </h3>
                    <p class="course-card__instructor">by <?= e($course['instructor_name']) ?></p>
                    <div class="course-card__meta">
                        <?= stars($course['rating']) ?>
                        <span class="rating-text"><?= number_format($course['rating'],1) ?></span>
                        <span>(<?= number_format($course['reviews_count']) ?>)</span>
                        <span><i class="fas fa-clock"></i><?= e($course['duration'] ?? 'N/A') ?></span>
                        <span><i class="fas fa-layer-group"></i><?= ucfirst($course['level']) ?></span>
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
                        <i class="fas fa-cart-plus"></i> Add
                    </button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--outline btn--lg">
                <i class="fas fa-th-large"></i> View All Courses
            </a>
        </div>
    </div>
</section>

<!-- ── Why Us ──────────────────────────────────────────────── -->
<section class="section section--gray">
    <div class="container">
        <div class="section__header">
            <h2>Why Choose CourseShop?</h2>
            <p>Everything you need to learn and grow — all in one place</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-card__icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3>Expert Instructors</h3>
                <p>Learn from industry professionals with years of real-world experience.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon"><i class="fas fa-infinity"></i></div>
                <h3>Lifetime Access</h3>
                <p>Buy once, learn forever. All future updates included at no extra cost.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon"><i class="fas fa-certificate"></i></div>
                <h3>Certificates</h3>
                <p>Earn shareable certificates upon course completion to boost your resume.</p>
            </div>
            <div class="feature-card">
                <div class="feature-card__icon"><i class="fas fa-mobile-alt"></i></div>
                <h3>Learn Anywhere</h3>
                <p>Fully responsive — learn on desktop, tablet, or mobile, whenever you want.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Testimonials ───────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="section__header">
            <h2>What Our Students Say</h2>
            <p>Join thousands of satisfied learners worldwide</p>
        </div>
        <div class="grid grid--3">
            <div class="testimonial-card">
                <p class="testimonial-card__text">"The PHP & MySQL course was incredibly detailed. I landed my first dev job within 3 months of completing it!"</p>
                <div class="testimonial-card__author">
                    <div class="testimonial-card__avatar">M</div>
                    <div>
                        <h4>Marcus L.</h4>
                        <span>Junior Web Developer</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <p class="testimonial-card__text">"Best investment I've made this year. The Python for Data Science course is hands-on and very well structured."</p>
                <div class="testimonial-card__author">
                    <div class="testimonial-card__avatar">S</div>
                    <div>
                        <h4>Sara K.</h4>
                        <span>Data Analyst</span>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <p class="testimonial-card__text">"Completed the Flutter course and shipped my app to the App Store. The instructor explains everything step by step."</p>
                <div class="testimonial-card__author">
                    <div class="testimonial-card__avatar">J</div>
                    <div>
                        <h4>James T.</h4>
                        <span>Mobile App Developer</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA Banner ─────────────────────────────────────────── -->
<section style="background:linear-gradient(135deg,var(--primary-dark),var(--primary));padding:5rem 0;color:#fff;text-align:center">
    <div class="container">
        <h2 style="font-size:2.2rem;font-weight:800;margin-bottom:1rem">Start Learning Today</h2>
        <p style="opacity:.85;font-size:1.1rem;margin-bottom:2rem">Use code <strong>WELCOME20</strong> for 20% off your first course.</p>
        <a href="<?= SITE_URL ?>/pages/courses.php" class="btn btn--secondary btn--lg">
            <i class="fas fa-rocket"></i> Get Started Now
        </a>
    </div>
</section>

<?php
// Inject JS globals
echo '<script>window.SITE_URL="' . SITE_URL . '";window.CSRF_TOKEN="' . csrf_token() . '";</script>';
require_once __DIR__ . '/includes/footer.php';
?>
