/* ============================================================
   validation.js  –  Client-side form validation helpers
   ============================================================ */
'use strict';

// ── Field rules ───────────────────────────────────────────────
const rules = {
    required: (v) => v.trim() !== ''          || 'This field is required.',
    email:    (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) || 'Enter a valid email address.',
    minLen:   (n) => (v) => v.length >= n     || `Must be at least ${n} characters.`,
    maxLen:   (n) => (v) => v.length <= n     || `Must be no more than ${n} characters.`,
    match:    (id) => (v) => v === document.getElementById(id)?.value || 'Passwords do not match.',
    phone:    (v) => /^\+?[\d\s\-()]{7,20}$/.test(v) || 'Enter a valid phone number.',
    card:     (v) => /^\d{13,19}$/.test(v.replace(/\s/g,'')) || 'Enter a valid card number.',
    cvv:      (v) => /^\d{3,4}$/.test(v)     || 'Enter a valid CVV.',
    expiry:   (v) => /^(0[1-9]|1[0-2])\/\d{2}$/.test(v) || 'Use MM/YY format.',
};

// ── Validate a single field ───────────────────────────────────
function validateField(input, fieldRules) {
    const value = input.value;
    for (const rule of fieldRules) {
        const result = rule(value);
        if (result !== true) {
            setError(input, result);
            return false;
        }
    }
    clearError(input);
    return true;
}

function setError(input, message) {
    input.classList.add('error');
    input.classList.remove('success');
    let errEl = input.parentElement.querySelector('.form-error');
    if (!errEl) {
        errEl = document.createElement('p');
        errEl.className = 'form-error';
        input.after(errEl);
    }
    errEl.textContent = message;
}

function clearError(input) {
    input.classList.remove('error');
    input.classList.add('success');
    input.parentElement.querySelector('.form-error')?.remove();
}

// ── Validate entire form ──────────────────────────────────────
function validateForm(form, schema) {
    let valid = true;
    for (const [name, fieldRules] of Object.entries(schema)) {
        const input = form.querySelector(`[name="${name}"]`);
        if (!input) continue;
        if (!validateField(input, fieldRules)) valid = false;
    }
    return valid;
}

// ── Live validation on blur ───────────────────────────────────
function enableLiveValidation(form, schema) {
    for (const [name, fieldRules] of Object.entries(schema)) {
        const input = form.querySelector(`[name="${name}"]`);
        if (!input) continue;
        input.addEventListener('blur', () => validateField(input, fieldRules));
        input.addEventListener('input', () => {
            if (input.classList.contains('error')) validateField(input, fieldRules);
        });
    }
}

// ── Password visibility toggle ────────────────────────────────
document.querySelectorAll('.password-toggle__btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.password-toggle').querySelector('input');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });
});

// ── Credit card number formatting ────────────────────────────
const cardInput = document.getElementById('cardNumber');
if (cardInput) {
    cardInput.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, '').substring(0, 16);
        e.target.value = val.replace(/(.{4})/g, '$1 ').trim();
    });
}

// ── Expiry date formatting ────────────────────────────────────
const expiryInput = document.getElementById('cardExpiry');
if (expiryInput) {
    expiryInput.addEventListener('input', (e) => {
        let val = e.target.value.replace(/\D/g, '').substring(0, 4);
        if (val.length > 2) val = val.substring(0,2) + '/' + val.substring(2);
        e.target.value = val;
    });
}

// ── Register form validation ──────────────────────────────────
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    const schema = {
        name:             [rules.required, rules.minLen(2), rules.maxLen(120)],
        email:            [rules.required, rules.email],
        password:         [rules.required, rules.minLen(8)],
        password_confirm: [rules.required, rules.match('password')],
    };
    enableLiveValidation(registerForm, schema);
    registerForm.addEventListener('submit', (e) => {
        if (!validateForm(registerForm, schema)) e.preventDefault();
    });
}

// ── Login form validation ─────────────────────────────────────
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    const schema = {
        email:    [rules.required, rules.email],
        password: [rules.required],
    };
    enableLiveValidation(loginForm, schema);
    loginForm.addEventListener('submit', (e) => {
        if (!validateForm(loginForm, schema)) e.preventDefault();
    });
}

// ── Checkout form validation ──────────────────────────────────
const checkoutForm = document.getElementById('checkoutForm');
if (checkoutForm) {
    const schema = {
        billing_name:    [rules.required, rules.minLen(2)],
        billing_email:   [rules.required, rules.email],
        billing_country: [rules.required],
    };
    enableLiveValidation(checkoutForm, schema);
    checkoutForm.addEventListener('submit', (e) => {
        const method = document.getElementById('paymentMethodInput')?.value;
        if (method === 'card') {
            Object.assign(schema, {
                card_number: [rules.required, rules.card],
                card_expiry: [rules.required, rules.expiry],
                card_cvv:    [rules.required, rules.cvv],
            });
        }
        if (!validateForm(checkoutForm, schema)) e.preventDefault();
    });
}

// Export for module environments (optional)
if (typeof module !== 'undefined') module.exports = { validateForm, validateField, rules };
