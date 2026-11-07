/* Vacation rental site-wide form input validators.
 *
 * Loaded once from the master layout. Auto-attaches to inputs across the site
 * so we don't have to wire validation per-form.
 *
 * Phone (input[type=tel], name*=phone, name*=mobile):
 *   - Strips letters and disallowed characters as the user types.
 *   - Allows: digits, leading "+", spaces, parentheses, dashes.
 *   - Auto-formats US-style numbers as (XXX) XXX-XXXX.
 *   - Treats anything starting with "+" as international and leaves the spacing
 *     to the user (formatting differs per country).
 *   - On blur, marks the field invalid if the digit count is < 7.
 *
 * Email (input[type=email]):
 *   - Lowercases and trims on blur.
 *   - Validates against a pragmatic regex; sets aria-invalid + appends a
 *     `.field-error` hint under the input.
 *
 * Both validators clear their inline error the moment the user starts editing
 * again, and they coexist with any per-form custom JS (the contact-form AJAX
 * handler clears its own field errors via the same `.has-error` class).
 */
(function () {
    'use strict';

    var EMAIL_RE = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

    function isPhoneInput(el) {
        if (!el || el.tagName !== 'INPUT') return false;
        if (el.type === 'tel') return true;
        var name = (el.getAttribute('name') || '').toLowerCase();
        var id   = (el.id || '').toLowerCase();
        return /(^|[_-])(phone|mobile|whatsapp|tel)([_-]|$)/.test(name)
            || /(^|[_-])(phone|mobile|whatsapp|tel)([_-]|$)/.test(id);
    }
    function isEmailInput(el) {
        return el && el.tagName === 'INPUT' && el.type === 'email';
    }

    function setError(el, msg) {
        if (!el) return;
        el.setAttribute('aria-invalid', 'true');
        var group = el.closest('.form-group') || el.parentElement;
        if (!group) return;
        group.classList.add('has-error');
        // Remove any prior message; re-attach with the new text.
        var prior = group.querySelector('.field-error[data-validator]');
        if (prior) prior.remove();
        var hint = document.createElement('div');
        hint.className = 'field-error';
        hint.setAttribute('data-validator', '1');
        hint.textContent = msg;
        group.appendChild(hint);
    }
    function clearError(el) {
        if (!el) return;
        el.removeAttribute('aria-invalid');
        var group = el.closest('.form-group') || el.parentElement;
        if (!group) return;
        group.classList.remove('has-error');
        var prior = group.querySelector('.field-error[data-validator]');
        if (prior) prior.remove();
    }

    // ───────── Phone ─────────

    function sanitizePhone(value) {
        // Allow only digits, +, space, hyphen, parens. Keep order; strip everything else.
        // "+" only valid as the very first character.
        if (!value) return '';
        var first = value.charAt(0);
        var rest = value.slice(1);
        var head = first === '+' ? '+' : first.replace(/[^0-9() \-]/g, '');
        var tail = rest.replace(/[^0-9() \-]/g, '');
        return head + tail;
    }

    function formatUSPhone(digits) {
        // Standard 10-digit US format: (XXX) XXX-XXXX. Partial input formats
        // sensibly while typing: "5", "(55", "(555) 1", etc.
        digits = digits.slice(0, 10);
        if (digits.length === 0) return '';
        if (digits.length < 4) return '(' + digits;
        if (digits.length < 7) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
        return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
    }

    function reformatPhone(value) {
        var sanitized = sanitizePhone(value);
        // International (starts with +) — preserve user spacing/grouping.
        if (sanitized.startsWith('+')) {
            // Allow + then digits/space/-/() up to 20 chars.
            return sanitized.slice(0, 20);
        }
        var digitsOnly = sanitized.replace(/\D/g, '');
        return formatUSPhone(digitsOnly);
    }

    function attachPhone(el) {
        if (!isPhoneInput(el) || el.dataset.caPhoneBound) return;
        el.dataset.caPhoneBound = '1';
        el.setAttribute('inputmode', 'tel');
        el.setAttribute('autocomplete', el.getAttribute('autocomplete') || 'tel');

        el.addEventListener('input', function () {
            var caretEnd = el.selectionEnd;
            var prevLen  = el.value.length;
            var formatted = reformatPhone(el.value);
            if (formatted !== el.value) {
                el.value = formatted;
                // Try to keep the caret near the end; if user is editing in the
                // middle, snap to end (acceptable for phone fields).
                try {
                    var diff = formatted.length - prevLen;
                    var newPos = Math.max(0, Math.min((caretEnd || 0) + diff, formatted.length));
                    el.setSelectionRange(newPos, newPos);
                } catch (_) {}
            }
            clearError(el);
        });

        el.addEventListener('blur', function () {
            var v = el.value.trim();
            if (!v) { clearError(el); return; }
            var digits = v.replace(/\D/g, '');
            // 7-digit minimum (covers local-only numbers); phone fields that are
            // marked `required` can have stricter checks — they'll fail server-side
            // validation too. We only flag clearly broken values here.
            if (digits.length < 7) {
                setError(el, 'Please enter a valid phone number.');
            } else {
                clearError(el);
            }
        });
    }

    // ───────── Email ─────────

    function attachEmail(el) {
        if (!isEmailInput(el) || el.dataset.caEmailBound) return;
        el.dataset.caEmailBound = '1';
        el.setAttribute('autocomplete', el.getAttribute('autocomplete') || 'email');

        el.addEventListener('input', function () { clearError(el); });

        el.addEventListener('blur', function () {
            var v = (el.value || '').trim();
            if (v && v !== el.value) el.value = v.toLowerCase();
            else if (v) el.value = v.toLowerCase();
            v = el.value;
            if (!v) { clearError(el); return; }
            if (!EMAIL_RE.test(v)) {
                setError(el, 'Please enter a valid email address.');
            } else {
                clearError(el);
            }
        });
    }

    function scan(root) {
        root = root || document;
        root.querySelectorAll('input').forEach(function (el) {
            if (isPhoneInput(el)) attachPhone(el);
            else if (isEmailInput(el)) attachEmail(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(); });
    } else {
        scan();
    }
    // Watch for forms injected later (modals, AJAX-loaded sections, etc.).
    if (window.MutationObserver) {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes && m.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (node.tagName === 'INPUT') {
                        if (isPhoneInput(node)) attachPhone(node);
                        else if (isEmailInput(node)) attachEmail(node);
                    } else if (node.querySelectorAll) {
                        scan(node);
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }
})();
