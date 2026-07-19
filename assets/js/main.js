/* ============================================================
   main.js  –  Global UI behaviour
   ============================================================ */
'use strict';

// ── Navbar scroll shadow ─────────────────────────────────────
const nav = document.getElementById('mainNav');
if (nav) {
    window.addEventListener('scroll', () => {
        nav.classList.toggle('scrolled', window.scrollY > 20);
    }, { passive: true });
}

// ── Mobile hamburger ─────────────────────────────────────────
const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('navLinks');
if (hamburger && navLinks) {
    hamburger.addEventListener('click', () => {
        const open = navLinks.classList.toggle('open');
        hamburger.setAttribute('aria-expanded', open);
        hamburger.classList.toggle('open', open);
    });
    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
            navLinks.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            hamburger.classList.remove('open');
        }
    });
}

// ── User dropdown ────────────────────────────────────────────
const dropdownToggle = document.querySelector('.user-dropdown__toggle');
const dropdownMenu   = document.querySelector('.user-dropdown__menu');
if (dropdownToggle && dropdownMenu) {
    dropdownToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = dropdownMenu.classList.toggle('open');
        dropdownToggle.setAttribute('aria-expanded', open);
    });
    document.addEventListener('click', () => {
        dropdownMenu.classList.remove('open');
        dropdownToggle?.setAttribute('aria-expanded', 'false');
    });
}

// ── Alert dismissal ──────────────────────────────────────────
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('alert-close')) {
        e.target.closest('.alert')?.remove();
    }
});

// ── Toast notification system ────────────────────────────────
const toastContainer = (() => {
    let el = document.querySelector('.toast-container');
    if (!el) {
        el = document.createElement('div');
        el.className = 'toast-container';
        document.body.appendChild(el);
    }
    return el;
})();

window.showToast = function(message, type = 'info', duration = 4000) {
    const icons = { success: 'fas fa-check-circle', error: 'fas fa-times-circle', info: 'fas fa-info-circle', warning: 'fas fa-exclamation-triangle' };
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `<i class="${icons[type] || icons.info}"></i><span>${message}</span>`;
    toastContainer.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOutRight .3s ease forwards';
        toast.addEventListener('animationend', () => toast.remove());
    }, duration);
};

// ── Curriculum accordion ─────────────────────────────────────
document.querySelectorAll('.curriculum-section__header').forEach(header => {
    header.addEventListener('click', () => {
        const section  = header.closest('.curriculum-section');
        const lessons  = section.querySelector('.curriculum-section__lessons');
        const chevron  = header.querySelector('.fa-chevron-down, .fa-chevron-up');
        const isOpen   = lessons.style.display === 'block';
        lessons.style.display = isOpen ? 'none' : 'block';
        if (chevron) chevron.classList.toggle('fa-chevron-down', isOpen);
        if (chevron) chevron.classList.toggle('fa-chevron-up',   !isOpen);
    });
});

// ── Course detail tabs ────────────────────────────────────────
document.querySelectorAll('.course-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        document.querySelectorAll('.course-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + target)?.classList.add('active');
    });
});

// ── Payment method selector ───────────────────────────────────
document.querySelectorAll('.payment-method').forEach(method => {
    method.addEventListener('click', () => {
        document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
        method.classList.add('active');
        const selected = method.dataset.method;
        document.querySelectorAll('.payment-panel').forEach(p => p.style.display = 'none');
        const panel = document.getElementById('payment-' + selected);
        if (panel) panel.style.display = 'block';
        const hidden = document.getElementById('paymentMethodInput');
        if (hidden) hidden.value = selected;
    });
});

// ── Newsletter form ───────────────────────────────────────────
const newsletterForm = document.getElementById('newsletterForm');
if (newsletterForm) {
    newsletterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const input = newsletterForm.querySelector('input[type="email"]');
        if (input && input.value) {
            showToast('Thanks for subscribing! Check your inbox soon.', 'success');
            input.value = '';
        }
    });
}

// ── Smooth anchor scrolling ───────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
        const target = document.querySelector(anchor.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ── Loading overlay helpers ───────────────────────────────────
window.showLoader = function() {
    let el = document.querySelector('.loading-overlay');
    if (!el) {
        el = document.createElement('div');
        el.className = 'loading-overlay';
        el.innerHTML = '<div class="spinner"></div>';
        document.body.appendChild(el);
    }
    el.style.display = 'flex';
};
window.hideLoader = function() {
    document.querySelector('.loading-overlay')?.remove();
};
