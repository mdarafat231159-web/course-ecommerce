<?php
// ============================================================
// pages/lesson.php  –  Course lesson viewer (enrolled only)
// ============================================================
require_once __DIR__ . '/../config/bootstrap.php';
require_login('/auth/login.php');

$uid       = $_SESSION['user_id'];
$course_id = (int)($_GET['course_id'] ?? 0);
$lesson_id = (int)($_GET['lesson_id'] ?? 0);

if (!$course_id) redirect('/pages/courses.php');

// ── Verify enrollment ─────────────────────────────────────
$enroll = db()->prepare(
    'SELECT e.id FROM enrollments e WHERE e.user_id = ? AND e.course_id = ?'
);
$enroll->execute([$uid, $course_id]);
if (!$enroll->fetchColumn()) {
    flash('global', 'You must purchase this course before viewing its lessons.', 'error');
    redirect('/pages/course.php?slug=' . urlencode(
        db()->query("SELECT slug FROM courses WHERE id = $course_id")->fetchColumn() ?? ''
    ));
}

// ── Load course ───────────────────────────────────────────
$stmt = db()->prepare(
    'SELECT c.*, cat.name AS category_name, u.name AS instructor_name
     FROM courses c
     JOIN categories cat ON cat.id = c.category_id
     JOIN users u ON u.id = c.instructor_id
     WHERE c.id = ? AND c.is_active = 1'
);
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) redirect('/pages/account.php?tab=courses');

// ── Load full curriculum ──────────────────────────────────
$curriculum_rows = db()->prepare(
    'SELECT * FROM course_curriculum WHERE course_id = ? ORDER BY sort_order, id'
);
$curriculum_rows->execute([$course_id]);
$all_lessons = $curriculum_rows->fetchAll();

// Group into sections
$sections = [];
foreach ($all_lessons as $l) {
    $sections[$l['section']][] = $l;
}

// ── Resolve current lesson ────────────────────────────────
$current_lesson = null;
if ($lesson_id) {
    foreach ($all_lessons as $l) {
        if ((int)$l['id'] === $lesson_id) { $current_lesson = $l; break; }
    }
}
// Default to first lesson
if (!$current_lesson && !empty($all_lessons)) {
    $current_lesson = $all_lessons[0];
    $lesson_id      = (int)$current_lesson['id'];
}

// ── Determine prev / next ─────────────────────────────────
$prev_lesson = null;
$next_lesson = null;
foreach ($all_lessons as $idx => $l) {
    if ((int)$l['id'] === $lesson_id) {
        $prev_lesson = $all_lessons[$idx - 1] ?? null;
        $next_lesson = $all_lessons[$idx + 1] ?? null;
        break;
    }
}

$total_lessons    = count($all_lessons);
$current_position = 0;
foreach ($all_lessons as $i => $l) {
    if ((int)$l['id'] === $lesson_id) { $current_position = $i + 1; break; }
}
$progress_pct = $total_lessons > 0 ? round(($current_position / $total_lessons) * 100) : 0;

$page_title = ($current_lesson['lesson'] ?? 'Lesson') . ' – ' . $course['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Lesson Layout ──────────────────────────────────────── -->
<div class="lesson-layout">

    <!-- ── Sidebar: Curriculum ──────────────────────────── -->
    <aside class="lesson-sidebar" id="lessonSidebar">
        <div class="lesson-sidebar__header">
            <a href="<?= SITE_URL ?>/pages/course.php?slug=<?= e($course['slug']) ?>"
               class="lesson-sidebar__back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="lesson-sidebar__course">
                <h2><?= e($course['title']) ?></h2>
                <div class="lesson-sidebar__progress">
                    <div class="progress-bar">
                        <div class="progress-bar__fill" style="width:<?= $progress_pct ?>%"></div>
                    </div>
                    <span><?= $current_position ?>/<?= $total_lessons ?> completed</span>
                </div>
            </div>
        </div>

        <nav class="lesson-nav" aria-label="Course curriculum">
            <?php foreach ($sections as $section_name => $section_lessons): ?>
            <div class="lesson-nav__section">
                <button class="lesson-nav__section-title" aria-expanded="true">
                    <i class="fas fa-folder"></i>
                    <span><?= e($section_name) ?></span>
                    <i class="fas fa-chevron-down lesson-nav__chevron"></i>
                </button>
                <ul class="lesson-nav__items">
                    <?php foreach ($section_lessons as $l):
                        $is_active = (int)$l['id'] === $lesson_id;
                    ?>
                    <li>
                        <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $l['id'] ?>"
                           class="lesson-nav__item <?= $is_active ? 'active' : '' ?>"
                           <?= $is_active ? 'aria-current="page"' : '' ?>>
                            <span class="lesson-nav__icon">
                                <i class="fas <?= $is_active ? 'fa-play-circle' : 'fa-circle' ?>"></i>
                            </span>
                            <span class="lesson-nav__title"><?= e($l['lesson']) ?></span>
                            <?php if ($l['duration']): ?>
                            <span class="lesson-nav__dur"><?= e($l['duration']) ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>

            <?php if (empty($sections)): ?>
            <p style="padding:1.25rem;color:var(--gray-500);font-size:.88rem">
                No curriculum available yet.
            </p>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- ── Main Content ─────────────────────────────────── -->
    <div class="lesson-main">

        <!-- Mobile sidebar toggle -->
        <button class="lesson-sidebar-toggle" id="sidebarToggle" aria-label="Toggle curriculum">
            <i class="fas fa-bars"></i> Curriculum
        </button>

        <?php if ($current_lesson): ?>

        <!-- Video / Content Area -->
        <div class="lesson-video-wrap">
            <!-- Simulated video player – replace src with real video URLs -->
            <div class="lesson-video-placeholder" role="img" aria-label="Lesson video for <?= e($current_lesson['lesson']) ?>">
                <div class="lesson-video-placeholder__inner">
                    <div class="lesson-play-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <p class="lesson-video-placeholder__label">
                        <?= e($current_lesson['lesson']) ?>
                    </p>
                    <p style="font-size:.83rem;opacity:.7;margin-top:.35rem">
                        <i class="fas fa-clock"></i>
                        <?= $current_lesson['duration'] ? e($current_lesson['duration']) : 'Duration N/A' ?>
                    </p>
                    <p style="font-size:.78rem;opacity:.5;margin-top:.75rem">
                        Video hosting integration required – add your video URL to the lesson record.
                    </p>
                </div>
            </div>
        </div>

        <!-- Lesson info bar -->
        <div class="lesson-info-bar">
            <div class="lesson-info-bar__left">
                <h1 class="lesson-info-bar__title"><?= e($current_lesson['lesson']) ?></h1>
                <div class="lesson-info-bar__meta">
                    <span><i class="fas fa-folder-open"></i> <?= e($current_lesson['section']) ?></span>
                    <?php if ($current_lesson['duration']): ?>
                    <span><i class="fas fa-clock"></i> <?= e($current_lesson['duration']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-list-ol"></i> Lesson <?= $current_position ?> of <?= $total_lessons ?></span>
                </div>
            </div>
            <div class="lesson-info-bar__actions">
                <?php if ($prev_lesson): ?>
                <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $prev_lesson['id'] ?>"
                   class="btn btn--ghost btn--sm">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                <?php if ($next_lesson): ?>
                <a href="?course_id=<?= $course_id ?>&lesson_id=<?= $next_lesson['id'] ?>"
                   class="btn btn--primary btn--sm">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php else: ?>
                <a href="<?= SITE_URL ?>/pages/account.php?tab=courses"
                   class="btn btn--primary btn--sm">
                    <i class="fas fa-check-circle"></i> Finish Course
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabs: Notes / Resources / About -->
        <div class="lesson-tabs-wrap">
            <div class="course-tabs" role="tablist" style="border-top:1px solid var(--gray-100)">
                <button class="course-tab active" data-tab="notes"     role="tab">Notes</button>
                <button class="course-tab"        data-tab="resources" role="tab">Resources</button>
                <button class="course-tab"        data-tab="about"     role="tab">About Course</button>
            </div>

            <!-- Notes Panel -->
            <div class="tab-panel active" id="tab-notes" style="padding:1.5rem 2rem">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:.75rem">
                    <i class="fas fa-sticky-note" style="color:var(--primary)"></i> My Notes
                </h3>
                <textarea class="form-control" id="lessonNotes" rows="6"
                          placeholder="Take notes while you watch…"
                          style="resize:vertical"><?= e($_COOKIE['lesson_notes_' . $lesson_id] ?? '') ?></textarea>
                <button class="btn btn--primary btn--sm" style="margin-top:.75rem" id="saveNotes">
                    <i class="fas fa-save"></i> Save Notes
                </button>
            </div>

            <!-- Resources Panel -->
            <div class="tab-panel" id="tab-resources" style="padding:1.5rem 2rem">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:1rem">
                    <i class="fas fa-paperclip" style="color:var(--primary)"></i> Resources
                </h3>
                <p style="color:var(--gray-500);font-size:.9rem">No downloadable resources for this lesson yet.</p>
            </div>

            <!-- About Panel -->
            <div class="tab-panel" id="tab-about" style="padding:1.5rem 2rem">
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:.75rem">
                    <i class="fas fa-info-circle" style="color:var(--primary)"></i> About This Course
                </h3>
                <div style="display:flex;gap:1rem;align-items:flex-start;margin-bottom:1rem">
                    <img src="<?= course_img($course['image']) ?>" alt=""
                         style="width:120px;border-radius:var(--radius-sm);flex-shrink:0">
                    <div>
                        <h4 style="font-size:.95rem;font-weight:700;margin-bottom:.25rem">
                            <?= e($course['title']) ?>
                        </h4>
                        <p style="font-size:.85rem;color:var(--gray-500)">
                            by <?= e($course['instructor_name']) ?>
                        </p>
                        <p style="font-size:.85rem;color:var(--gray-700);margin-top:.5rem">
                            <?= e($course['short_desc']) ?>
                        </p>
                        <div style="display:flex;gap:1rem;margin-top:.75rem;font-size:.82rem;color:var(--gray-500)">
                            <span><i class="fas fa-book-open"></i> <?= $total_lessons ?> lessons</span>
                            <span><i class="fas fa-clock"></i> <?= e($course['duration'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-layer-group"></i> <?= ucfirst($course['level']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- No lessons available -->
        <div style="padding:4rem 2rem;text-align:center">
            <i class="fas fa-film" style="font-size:4rem;color:var(--gray-300);margin-bottom:1rem;display:block"></i>
            <h2>No Lessons Available Yet</h2>
            <p style="color:var(--gray-500);margin:.75rem 0 1.5rem">
                Curriculum content for this course hasn't been published yet.
                Check back soon!
            </p>
            <a href="<?= SITE_URL ?>/pages/account.php?tab=courses" class="btn btn--primary">
                <i class="fas fa-arrow-left"></i> Back to My Courses
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /.lesson-main -->
</div><!-- /.lesson-layout -->

<style>
/* ── Lesson-specific layout overrides ─────────────────── */
#mainContent { display: flex; flex-direction: column; }

.lesson-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    min-height: calc(100vh - var(--nav-h) - 48px); /* subtract topbar */
    align-items: start;
}

/* Sidebar */
.lesson-sidebar {
    position: sticky;
    top: var(--nav-h);
    height: calc(100vh - var(--nav-h));
    overflow-y: auto;
    background: var(--dark);
    color: #e2e8f0;
    display: flex;
    flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: #334155 transparent;
}
.lesson-sidebar__header {
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    position: sticky;
    top: 0;
    background: var(--dark);
    z-index: 2;
}
.lesson-sidebar__back {
    color: #94a3b8;
    font-size: 1rem;
    flex-shrink: 0;
    padding: .25rem;
    margin-top: .15rem;
    transition: color .2s;
}
.lesson-sidebar__back:hover { color: #fff; }
.lesson-sidebar__course h2 { font-size: .88rem; font-weight: 700; line-height: 1.4; }
.lesson-sidebar__progress {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-top: .35rem;
    font-size: .72rem;
    color: #94a3b8;
}
.progress-bar { flex: 1; height: 4px; background: rgba(255,255,255,.12); border-radius: 2px; overflow: hidden; }
.progress-bar__fill { height: 100%; background: var(--primary); border-radius: 2px; transition: width .4s ease; }

/* Nav sections */
.lesson-nav { padding: .5rem 0; }
.lesson-nav__section { border-bottom: 1px solid rgba(255,255,255,.06); }
.lesson-nav__section-title {
    width: 100%;
    padding: .8rem 1.25rem;
    background: none;
    border: none;
    color: #94a3b8;
    font-size: .78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: .5rem;
    text-align: left;
    transition: color .2s;
}
.lesson-nav__section-title:hover { color: #fff; }
.lesson-nav__section-title .lesson-nav__chevron { margin-left: auto; transition: transform .25s; }
.lesson-nav__section-title[aria-expanded="false"] .lesson-nav__chevron { transform: rotate(-90deg); }
.lesson-nav__items { list-style: none; }
.lesson-nav__item {
    display: flex;
    align-items: center;
    gap: .65rem;
    padding: .65rem 1.25rem .65rem 2rem;
    font-size: .83rem;
    color: #94a3b8;
    transition: background .2s, color .2s;
    text-decoration: none;
}
.lesson-nav__item:hover { background: rgba(255,255,255,.05); color: #fff; }
.lesson-nav__item.active { background: rgba(99,102,241,.18); color: #a5b4fc; }
.lesson-nav__icon { flex-shrink: 0; width: 16px; font-size: .75rem; }
.lesson-nav__title { flex: 1; line-height: 1.4; }
.lesson-nav__dur { font-size: .72rem; color: #64748b; flex-shrink: 0; }

/* Main */
.lesson-main { background: var(--white); min-height: 100%; display: flex; flex-direction: column; }
.lesson-video-wrap { background: #000; position: relative; }
.lesson-video-placeholder {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    aspect-ratio: 16/9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    text-align: center;
}
.lesson-video-placeholder__inner { max-width: 420px; padding: 2rem; }
.lesson-play-icon { font-size: 4.5rem; color: var(--primary); margin-bottom: 1rem; opacity: .85; }
.lesson-video-placeholder__label { font-size: 1.15rem; font-weight: 700; line-height: 1.4; }

/* Info bar */
.lesson-info-bar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 2rem;
    border-bottom: 1px solid var(--gray-100);
    flex-wrap: wrap;
}
.lesson-info-bar__title { font-size: 1.2rem; font-weight: 800; margin-bottom: .25rem; }
.lesson-info-bar__meta {
    display: flex;
    gap: 1rem;
    font-size: .82rem;
    color: var(--gray-500);
    flex-wrap: wrap;
}
.lesson-info-bar__meta i { margin-right: .3rem; }
.lesson-info-bar__actions { display: flex; gap: .65rem; flex-shrink: 0; }

/* Mobile sidebar toggle */
.lesson-sidebar-toggle {
    display: none;
    align-items: center;
    gap: .5rem;
    padding: .65rem 1.25rem;
    background: var(--dark);
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: .88rem;
    font-weight: 600;
    width: 100%;
    text-align: left;
}

/* Responsive */
@media (max-width: 900px) {
    .lesson-layout { grid-template-columns: 1fr; }
    .lesson-sidebar {
        position: fixed;
        top: var(--nav-h);
        left: 0;
        width: 300px;
        height: calc(100vh - var(--nav-h));
        z-index: 500;
        transform: translateX(-100%);
        transition: transform .3s ease;
    }
    .lesson-sidebar.open { transform: translateX(0); }
    .lesson-sidebar-toggle { display: flex; }
    .lesson-info-bar { padding: 1rem; }
    .lesson-info-bar__title { font-size: 1rem; }
    .lesson-tabs-wrap .tab-panel { padding: 1rem !important; }
}
</style>

<script>
window.SITE_URL   = '<?= SITE_URL ?>';
window.CSRF_TOKEN = '<?= csrf_token() ?>';

// ── Mobile sidebar toggle ─────────────────────────────────
const sidebar      = document.getElementById('lessonSidebar');
const toggleBtn    = document.getElementById('sidebarToggle');
if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('open');
        }
    });
}

// ── Curriculum section accordion ─────────────────────────
document.querySelectorAll('.lesson-nav__section-title').forEach(btn => {
    btn.addEventListener('click', () => {
        const items   = btn.nextElementSibling;
        const isOpen  = btn.getAttribute('aria-expanded') !== 'false';
        btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        items.style.display = isOpen ? 'none' : '';
    });
});

// ── Tab switching (reuses global main.js handler) ─────────

// ── Save notes to cookie ──────────────────────────────────
const saveBtn    = document.getElementById('saveNotes');
const notesArea  = document.getElementById('lessonNotes');
const lessonId   = <?= (int)$lesson_id ?>;

if (saveBtn && notesArea) {
    saveBtn.addEventListener('click', () => {
        const val = notesArea.value;
        const exp = new Date();
        exp.setFullYear(exp.getFullYear() + 1);
        document.cookie = `lesson_notes_${lessonId}=${encodeURIComponent(val)};expires=${exp.toUTCString()};path=/`;
        if (typeof showToast !== 'undefined') showToast('Notes saved!', 'success');
    });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
