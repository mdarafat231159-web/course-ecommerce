/* ============================================================
   cart.js  –  All cart AJAX interactions
   ============================================================ */
'use strict';

const CART_API = window.SITE_URL + '/api/cart.php';

// ── Update badge count ────────────────────────────────────────
function updateCartBadge(count) {
    const badge = document.getElementById('cartBadge');
    if (!badge) return;
    badge.textContent = count;
    badge.hidden = count === 0;
}

// ── Fetch current cart count from API ────────────────────────
async function refreshCartCount() {
    try {
        const res  = await fetch(CART_API + '?action=count');
        const data = await res.json();
        if (data.count !== undefined) updateCartBadge(data.count);
    } catch (_) { /* silently ignore */ }
}

// ── Add to cart ───────────────────────────────────────────────
async function addToCart(courseId, btn) {
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Adding…';
    }
    try {
        const res  = await fetch(CART_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body:    JSON.stringify({ action: 'add', course_id: courseId }),
        });
        const data = await res.json();
        if (data.success) {
            updateCartBadge(data.cart_count);
            showToast(data.message || 'Course added to cart!', 'success');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i> Added';
                btn.classList.add('btn--ghost');
                btn.classList.remove('btn--primary');
            }
        } else {
            showToast(data.message || 'Could not add course.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart'; }
        }
    } catch (_) {
        showToast('Network error. Please try again.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart'; }
    }
}

// ── Remove from cart ──────────────────────────────────────────
async function removeFromCart(courseId, rowEl) {
    try {
        const res  = await fetch(CART_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body:    JSON.stringify({ action: 'remove', course_id: courseId }),
        });
        const data = await res.json();
        if (data.success) {
            rowEl?.remove();
            updateCartBadge(data.cart_count);
            updateCartTotals(data);
            showToast('Course removed from cart.', 'info');
            if (data.cart_count === 0) showEmptyCart();
        } else {
            showToast(data.message || 'Could not remove course.', 'error');
        }
    } catch (_) {
        showToast('Network error. Please try again.', 'error');
    }
}

// ── Apply coupon ──────────────────────────────────────────────
async function applyCoupon(code) {
    try {
        const res  = await fetch(CART_API, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
            body:    JSON.stringify({ action: 'coupon', code }),
        });
        const data = await res.json();
        if (data.success) {
            updateCartTotals(data);
            showToast('Coupon applied! ' + data.message, 'success');
        } else {
            showToast(data.message || 'Invalid coupon code.', 'error');
        }
    } catch (_) {
        showToast('Could not apply coupon.', 'error');
    }
}

// ── Update summary totals in DOM ─────────────────────────────
function updateCartTotals(data) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    if (data.subtotal   !== undefined) set('summarySubtotal',  data.subtotal_fmt  || data.subtotal);
    if (data.discount   !== undefined) set('summaryDiscount',  data.discount_fmt  || data.discount);
    if (data.tax        !== undefined) set('summaryTax',       data.tax_fmt       || data.tax);
    if (data.total      !== undefined) set('summaryTotal',     data.total_fmt     || data.total);
}

function showEmptyCart() {
    const itemsWrap = document.getElementById('cartItemsWrap');
    if (!itemsWrap) return;
    itemsWrap.innerHTML = `
        <div class="cart-empty">
            <i class="fas fa-shopping-cart"></i>
            <h3>Your cart is empty</h3>
            <p>Looks like you haven't added any courses yet.</p>
            <a href="${window.SITE_URL}/pages/courses.php" class="btn btn--primary">
                <i class="fas fa-search"></i> Browse Courses
            </a>
        </div>`;
    const summaryWrap = document.getElementById('orderSummaryWrap');
    if (summaryWrap) summaryWrap.style.opacity = '.4';
}

// ── Event delegation for cart page ───────────────────────────
document.addEventListener('click', (e) => {
    // Add to cart buttons (course listings / detail)
    const addBtn = e.target.closest('[data-add-cart]');
    if (addBtn) {
        e.preventDefault();
        addToCart(addBtn.dataset.addCart, addBtn);
        return;
    }
    // Remove buttons on cart page
    const removeBtn = e.target.closest('[data-remove-cart]');
    if (removeBtn) {
        e.preventDefault();
        const row = removeBtn.closest('.cart-item');
        removeFromCart(removeBtn.dataset.removeCart, row);
        return;
    }
});

// ── Coupon form ───────────────────────────────────────────────
const couponForm = document.getElementById('couponForm');
if (couponForm) {
    couponForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const code = couponForm.querySelector('[name="coupon_code"]')?.value?.trim();
        if (code) applyCoupon(code);
    });
}

// ── Init ──────────────────────────────────────────────────────
refreshCartCount();
