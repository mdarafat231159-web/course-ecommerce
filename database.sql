-- ============================================================
-- Course E-Commerce Platform - Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

CREATE DATABASE IF NOT EXISTS course_ecommerce
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE course_ecommerce;

-- ------------------------------------------------------------
-- Table: categories
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(110) NOT NULL UNIQUE,
    description TEXT,
    icon        VARCHAR(50)  DEFAULT 'fas fa-book',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(180) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar        VARCHAR(255) DEFAULT NULL,
    role          ENUM('student','admin') DEFAULT 'student',
    is_active     TINYINT(1)   DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: courses
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   INT UNSIGNED NOT NULL,
    instructor_id INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    slug          VARCHAR(220) NOT NULL UNIQUE,
    short_desc    VARCHAR(350) NOT NULL,
    description   LONGTEXT     NOT NULL,
    image         VARCHAR(255) DEFAULT 'default-course.jpg',
    price         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    sale_price    DECIMAL(10,2) DEFAULT NULL,
    duration      VARCHAR(50)  DEFAULT NULL,   -- e.g. "12 hours"
    level         ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    language      VARCHAR(60)  DEFAULT 'English',
    lessons_count INT UNSIGNED DEFAULT 0,
    rating        DECIMAL(3,2) DEFAULT 0.00,
    reviews_count INT UNSIGNED DEFAULT 0,
    is_featured   TINYINT(1)   DEFAULT 0,
    is_active     TINYINT(1)   DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id)   REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (instructor_id) REFERENCES users(id)      ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: course_curriculum (lesson sections)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS course_curriculum (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id  INT UNSIGNED NOT NULL,
    section    VARCHAR(200) NOT NULL,
    lesson     VARCHAR(200) NOT NULL,
    duration   VARCHAR(20)  DEFAULT NULL,
    sort_order INT UNSIGNED DEFAULT 0,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: cart
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED DEFAULT NULL,   -- NULL for guest (session-based)
    session_id VARCHAR(128) DEFAULT NULL,
    course_id  INT UNSIGNED NOT NULL,
    added_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_course    (user_id, course_id),
    UNIQUE KEY uq_session_course (session_id, course_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: orders
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    order_number    VARCHAR(30)  NOT NULL UNIQUE,
    subtotal        DECIMAL(10,2) NOT NULL,
    discount        DECIMAL(10,2) DEFAULT 0.00,
    tax             DECIMAL(10,2) DEFAULT 0.00,
    total           DECIMAL(10,2) NOT NULL,
    coupon_code     VARCHAR(50)  DEFAULT NULL,
    status          ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    billing_name    VARCHAR(120) NOT NULL,
    billing_email   VARCHAR(180) NOT NULL,
    billing_country VARCHAR(80)  DEFAULT NULL,
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: order_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id   INT UNSIGNED NOT NULL,
    course_id  INT UNSIGNED NOT NULL,
    title      VARCHAR(200) NOT NULL,  -- snapshot at purchase time
    price      DECIMAL(10,2) NOT NULL, -- snapshot at purchase time
    FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: payments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id         INT UNSIGNED NOT NULL UNIQUE,
    gateway          VARCHAR(50)  NOT NULL DEFAULT 'stripe', -- stripe | paypal | demo
    transaction_id   VARCHAR(255) DEFAULT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    currency         VARCHAR(10)  DEFAULT 'USD',
    status           ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    payload          JSON         DEFAULT NULL, -- raw gateway response
    paid_at          TIMESTAMP    DEFAULT NULL,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: enrollments  (tracks which courses a user owns)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS enrollments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    course_id  INT UNSIGNED NOT NULL,
    order_id   INT UNSIGNED NOT NULL,
    enrolled_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment (user_id, course_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id)  REFERENCES orders(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: coupons
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(50)  NOT NULL UNIQUE,
    type         ENUM('percent','fixed') DEFAULT 'percent',
    value        DECIMAL(10,2) NOT NULL,
    max_uses     INT UNSIGNED DEFAULT NULL,
    used_count   INT UNSIGNED DEFAULT 0,
    expires_at   DATE         DEFAULT NULL,
    is_active    TINYINT(1)   DEFAULT 1,
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Seed Data
-- ============================================================

-- Categories
INSERT INTO categories (name, slug, description, icon) VALUES
('Web Development',    'web-development',    'HTML, CSS, JavaScript, PHP and more',        'fas fa-code'),
('Data Science',       'data-science',       'Python, ML, AI and data analysis',           'fas fa-chart-bar'),
('Mobile Development', 'mobile-development', 'iOS, Android, Flutter and React Native',     'fas fa-mobile-alt'),
('Design & UX',        'design-ux',          'UI/UX design, Figma, Adobe XD',              'fas fa-paint-brush'),
('Cybersecurity',      'cybersecurity',      'Ethical hacking, network security',          'fas fa-shield-alt'),
('Business',           'business',           'Marketing, finance, entrepreneurship',       'fas fa-briefcase');

-- Admin user  (password: Admin@1234)
INSERT INTO users (name, email, password_hash, role) VALUES
('Admin User', 'admin@courseshop.com',
 '$2y$12$9n6fLhEKAhbqPy3I2gMzLOqNb8wK7lR1NTjnz5.5oHUhgb4bWyYkW', 'admin');

-- Student demo user  (password: Student@1234)
INSERT INTO users (name, email, password_hash, role) VALUES
('Jane Doe', 'jane@example.com',
 '$2y$12$9n6fLhEKAhbqPy3I2gMzLOqNb8wK7lR1NTjnz5.5oHUhgb4bWyYkW', 'student');

-- Courses (instructor_id = 1 = Admin)
INSERT INTO courses
  (category_id, instructor_id, title, slug, short_desc, description,
   image, price, sale_price, duration, level, lessons_count, rating, reviews_count, is_featured)
VALUES
(1, 1,
 'Complete HTML & CSS Bootcamp',
 'complete-html-css-bootcamp',
 'Master HTML5 and CSS3 from scratch to build stunning, responsive websites.',
 '<p>This comprehensive course covers everything you need to know to build modern websites using HTML5 and CSS3. You will learn semantic markup, Flexbox, CSS Grid, animations, and responsive design.</p><ul><li>HTML5 semantic elements</li><li>CSS3 advanced selectors</li><li>Flexbox &amp; Grid layouts</li><li>Responsive media queries</li><li>CSS animations &amp; transitions</li></ul>',
 'html-css.jpg', 49.99, 29.99, '18 hours', 'beginner', 42, 4.80, 1240, 1),

(1, 1,
 'JavaScript: The Complete Guide',
 'javascript-complete-guide',
 'Go from beginner to advanced JavaScript with hands-on projects.',
 '<p>Deep-dive into modern JavaScript (ES6+). Topics include closures, promises, async/await, DOM manipulation, OOP, and more.</p>',
 'javascript.jpg', 69.99, 39.99, '28 hours', 'intermediate', 68, 4.90, 3210, 1),

(1, 1,
 'PHP & MySQL Web Development',
 'php-mysql-web-development',
 'Build dynamic, database-driven websites with PHP 8 and MySQL.',
 '<p>Learn PHP 8 fundamentals, OOP, PDO, MVC patterns, and integrate MySQL to create real-world web applications including an e-commerce shop.</p>',
 'php-mysql.jpg', 79.99, 49.99, '32 hours', 'intermediate', 74, 4.75, 890, 1),

(2, 1,
 'Python for Data Science & ML',
 'python-data-science-ml',
 'Master Python, Pandas, NumPy, Matplotlib, and Scikit-learn.',
 '<p>This course takes you from Python basics to building real machine-learning models. Includes 10 end-to-end projects.</p>',
 'python-ds.jpg', 89.99, 59.99, '36 hours', 'intermediate', 88, 4.85, 4100, 1),

(3, 1,
 'Flutter & Dart – Build iOS & Android Apps',
 'flutter-dart-mobile-apps',
 'Create beautiful cross-platform mobile apps with Flutter.',
 '<p>Learn Dart and Flutter from the ground up. Build 5 real apps including a full e-commerce mobile app.</p>',
 'flutter.jpg', 74.99, 44.99, '24 hours', 'beginner', 56, 4.70, 760, 0),

(4, 1,
 'UI/UX Design Masterclass',
 'ui-ux-design-masterclass',
 'Design user-centered interfaces with Figma and Adobe XD.',
 '<p>Learn design thinking, wireframing, prototyping, and user testing. Build a professional portfolio by the end of the course.</p>',
 'uiux.jpg', 59.99, 34.99, '20 hours', 'beginner', 48, 4.65, 620, 0),

(5, 1,
 'Ethical Hacking & Cybersecurity',
 'ethical-hacking-cybersecurity',
 'Learn penetration testing, network security, and ethical hacking.',
 '<p>This course covers Kali Linux, network scanning, web app attacks, and defensive strategies. Prepares you for CEH certification.</p>',
 'cybersec.jpg', 94.99, 64.99, '40 hours', 'advanced', 92, 4.88, 1580, 1),

(6, 1,
 'Digital Marketing Fundamentals',
 'digital-marketing-fundamentals',
 'Master SEO, social media, email marketing, and paid ads.',
 '<p>A practical guide to growing any business online. Covers Google Ads, Facebook Ads, SEO, email funnels, and analytics.</p>',
 'marketing.jpg', 44.99, 24.99, '16 hours', 'beginner', 38, 4.55, 510, 0);

-- Curriculum sample for course 1
INSERT INTO course_curriculum (course_id, section, lesson, duration, sort_order) VALUES
(1, 'Getting Started',      'Welcome & Course Overview',          '3:20',  1),
(1, 'Getting Started',      'Setting up VS Code',                 '5:10',  2),
(1, 'HTML Fundamentals',    'HTML Document Structure',            '8:45',  3),
(1, 'HTML Fundamentals',    'Headings, Paragraphs & Links',       '10:20', 4),
(1, 'HTML Fundamentals',    'Images, Lists & Tables',             '12:05', 5),
(1, 'CSS Basics',           'Introduction to CSS',                '9:30',  6),
(1, 'CSS Basics',           'Selectors & Specificity',            '11:15', 7),
(1, 'CSS Basics',           'Box Model Explained',                '8:50',  8),
(1, 'Layouts',              'Flexbox Deep Dive',                  '20:00', 9),
(1, 'Layouts',              'CSS Grid System',                    '22:30', 10),
(1, 'Responsive Design',    'Media Queries',                      '14:00', 11),
(1, 'Responsive Design',    'Mobile-First Approach',              '10:45', 12);

-- Coupon
INSERT INTO coupons (code, type, value, max_uses, expires_at) VALUES
('WELCOME20', 'percent', 20.00, 500, '2027-12-31'),
('FLAT10',    'fixed',   10.00, 200, '2027-06-30');
