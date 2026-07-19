<?php
// ============================================================
// includes/footer.php  –  Site footer + JS includes
// ============================================================
?>
</main><!-- /#mainContent -->

<!-- ── Footer ───────────────────────────────────────────── -->
<footer class="footer">
    <div class="container footer__grid">

        <div class="footer__brand">
            <a class="footer__logo" href="<?= SITE_URL ?>/index.php">
                <i class="fas fa-graduation-cap"></i> <?= SITE_NAME ?>
            </a>
            <p>Learn in-demand skills from expert instructors. Over 500 courses across tech, design, and business.</p>
            <div class="footer__social">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>

        <div class="footer__col">
            <h4>Explore</h4>
            <ul>
                <li><a href="<?= SITE_URL ?>/pages/courses.php">All Courses</a></li>
                <li><a href="<?= SITE_URL ?>/pages/courses.php?category=web-development">Web Development</a></li>
                <li><a href="<?= SITE_URL ?>/pages/courses.php?category=data-science">Data Science</a></li>
                <li><a href="<?= SITE_URL ?>/pages/courses.php?category=mobile-development">Mobile Dev</a></li>
                <li><a href="<?= SITE_URL ?>/pages/courses.php?category=design-ux">Design & UX</a></li>
            </ul>
        </div>

        <div class="footer__col">
            <h4>Company</h4>
            <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Become an Instructor</a></li>
                <li><a href="#">Affiliate Program</a></li>
            </ul>
        </div>

        <div class="footer__col">
            <h4>Support</h4>
            <ul>
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Contact Us</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
                <li><a href="#">Refund Policy</a></li>
            </ul>
        </div>

        <div class="footer__col footer__newsletter">
            <h4>Stay Updated</h4>
            <p>Get the latest courses and offers straight to your inbox.</p>
            <form class="newsletter-form" id="newsletterForm">
                <input type="email" placeholder="Your email address" required>
                <button type="submit" class="btn btn--primary">Subscribe</button>
            </form>
        </div>

    </div>

    <div class="footer__bottom">
        <div class="container footer__bottom-inner">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
            <div class="payment-badges">
                <i class="fab fa-cc-visa"       title="Visa"></i>
                <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                <i class="fab fa-cc-paypal"     title="PayPal"></i>
                <i class="fab fa-cc-stripe"     title="Stripe"></i>
                <i class="fab fa-cc-amex"       title="Amex"></i>
            </div>
        </div>
    </div>
</footer>
<!-- ── End Footer ───────────────────────────────────────── -->

<!-- Scripts -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script src="<?= SITE_URL ?>/assets/js/cart.js"></script>
</body>
</html>
