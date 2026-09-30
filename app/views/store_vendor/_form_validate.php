<?php
/**
 * Seller-portal form helper, loaded on every page via _pg_head.php.
 *  - Adds a red * to the label of every required control (kept in sync when Alpine
 *    toggles `required` / renders rows later).
 *  - Replaces the browser's bubble validation with inline messages under each field,
 *    a red outline, a count next to the submit button, and a scroll to the first problem.
 * Extra rules (mirror the server checks) via attributes on the control:
 *   data-validate="mobile|pincode|pan|gstin|ifsc|account|upi|money"
 *   data-match="other_field_name"   must equal that field in the same form
 *   data-lte="mrp"                  must be ≤ the sibling field whose name ends in [mrp]
 *   data-msg="…"                    message for a pattern mismatch
 *   data-fv-box                     on a wrapper: outline it instead of the control
 * The server still validates everything; this only saves a round trip.
 */
?>
<style>
    .fv-star { color: #dc2626; font-weight: 600; margin-left: 2px; }
    .fv-invalid { border-color: #dc2626 !important; box-shadow: 0 0 0 3px rgba(220, 38, 38, .12) !important; }
    input[type=checkbox].fv-invalid { outline: 2px solid #dc2626; outline-offset: 1px; box-shadow: none !important; }
    .fv-msg { display: flex; gap: 4px; margin-top: 4px; font-size: 12px; font-weight: 500; line-height: 1.35; color: #b91c1c; }
    .fv-msg::before { content: '!'; flex: none; display: grid; place-items: center; width: 14px; height: 14px; margin-top: 1px; border-radius: 999px; background: #dc2626; color: #fff; font-size: 10px; font-weight: 700; }
    .fv-summary { font-size: 12.5px; font-weight: 500; color: #b91c1c; }
</style>
<script>
(function () {
    const CONTROLS = 'input, select, textarea';
    const digits = (v) => v.replace(/\D/g, '');
    const RULES = {
        mobile: (v) => {
            let d = digits(v);
            if (d.length === 12 && d.startsWith('91')) d = d.slice(2);
            else if (d.length === 11 && d.startsWith('0')) d = d.slice(1);
            return /^[6-9]\d{9}$/.test(d) ? '' : 'Enter a valid 10-digit mobile number.';
        },
        pincode: (v) => /^[1-9]\d{5}$/.test(digits(v)) ? '' : 'Enter a valid 6-digit pincode.',
        pan: (v) => /^[A-Z]{5}\d{4}[A-Z]$/.test(v.replace(/\s+/g, '').toUpperCase()) ? '' : 'PAN format looks wrong (e.g. ABCDE1234F).',
        gstin: (v) => /^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/.test(v.replace(/\s+/g, '').toUpperCase()) ? '' : 'GSTIN should be 15 characters, e.g. 24ABCDE1234F1Z5.',
        ifsc: (v) => /^[A-Z]{4}0[A-Z0-9]{6}$/.test(v.trim().toUpperCase()) ? '' : 'IFSC format looks wrong (e.g. HDFC0001234).',
        account: (v) => /^\d{9,18}$/.test(v.replace(/\s+/g, '')) ? '' : 'Account number should be 9–18 digits.',
        upi: (v) => /^[\w.\-]{2,}@[a-zA-Z]{2,}$/.test(v.trim()) ? '' : 'UPI ID format looks wrong (e.g. name@bank).',
        money: (v) => parseFloat(v.replace(/,/g, '')) > 0 ? '' : 'Enter an amount greater than 0.',
    };

    const isRequired = (c) => c.required && !c.disabled;
    const visible = (c) => c.getClientRects().length > 0;
    const labelOf = (c) => c.closest('label');

    // ---- Red * on required labels ----
    function textSpan(label) {
        return label.querySelector('span.text-tx2') || label.querySelector(':scope > span');
    }
    function syncStar(label) {
        const span = textSpan(label);
        if (!span) return;
        const manual = [...span.querySelectorAll('span')].some((s) => !s.classList.contains('fv-star') && s.textContent.trim() === '*');
        const want = !manual && [...label.querySelectorAll(CONTROLS)].some(isRequired);
        let star = span.querySelector('.fv-star');
        if (want && !star) {
            star = document.createElement('span');
            star.className = 'fv-star';
            star.setAttribute('aria-hidden', 'true');
            star.textContent = '*';
            // Right after the label's own words, before any "(optional note)" span.
            const first = [...span.childNodes].find((n) => n.nodeType === 3 && n.textContent.trim() !== '');
            first ? first.after(star) : span.append(star);
        } else if (!want && star) {
            star.remove();
        }
    }
    function syncAllStars(root) {
        root.querySelectorAll('label').forEach(syncStar);
    }

    // ---- Validation ----
    function messageFor(c) {
        const v = c.type === 'checkbox' || c.type === 'file' ? '' : String(c.value || '');
        const empty = c.type === 'checkbox' ? !c.checked : c.type === 'file' ? !(c.files && c.files.length) : v.trim() === '';
        if (empty) {
            if (!isRequired(c)) return '';
            if (c.type === 'checkbox') return 'Please tick this box to continue.';
            if (c.type === 'file') return 'Please choose a file.';
            if (c.tagName === 'SELECT') return 'Please choose an option.';
            return 'This field is required.';
        }
        const rule = RULES[c.dataset.validate];
        if (rule) {
            const m = rule(v);
            if (m) return m;
        }
        if (c.dataset.match && c.form) {
            const other = c.form.elements[c.dataset.match];
            if (other && other.value !== c.value) return "Doesn't match. Please type it again.";
        }
        if (c.dataset.lte) {
            const row = c.closest('[data-fv-row]') || c.form;
            const other = row && [...row.querySelectorAll(CONTROLS)].find((x) => x.name && x.name.endsWith('[' + c.dataset.lte + ']'));
            const a = parseFloat(v.replace(/,/g, '')), b = other ? parseFloat(other.value.replace(/,/g, '')) : NaN;
            if (a > 0 && b > 0 && a > b) return "Can't be higher than the MRP.";
        }
        const s = c.validity;
        if (s.typeMismatch) return c.type === 'email' ? 'Enter a valid email address.' : 'Please check this value.';
        if (s.tooShort) return 'Must be at least ' + c.minLength + ' characters.';
        if (s.patternMismatch) return c.dataset.msg || 'Please check the format.';
        if (s.rangeUnderflow) return 'Must be at least ' + c.min + '.';
        if (s.rangeOverflow) return 'Must be ' + c.max + ' or less.';
        if (s.stepMismatch || s.badInput) return 'Please enter a valid number.';
        return '';
    }

    function slotFor(c) {
        const label = labelOf(c);
        // Checkbox rows are flex lines: put the message under the whole row.
        if (label && c.type !== 'checkbox') return { parent: label, after: null };
        return { parent: null, after: label || c.closest('[data-fv-box]') || c };
    }
    function clearError(c) {
        (c.closest('[data-fv-box]') || c).classList.remove('fv-invalid');
        c.removeAttribute('aria-invalid');
        const msg = c._fvMsg;
        if (msg) { msg.remove(); c._fvMsg = null; }
    }
    function showError(c, text) {
        (c.closest('[data-fv-box]') || c).classList.add('fv-invalid');
        c.setAttribute('aria-invalid', 'true');
        let msg = c._fvMsg;
        if (!msg) {
            msg = document.createElement('span');
            msg.className = 'fv-msg';
            msg.setAttribute('role', 'alert');
            const slot = slotFor(c);
            // One message per label (e.g. L × B × H share a label).
            const existing = slot.parent && slot.parent.querySelector(':scope > .fv-msg');
            if (existing) return;
            slot.parent ? slot.parent.append(msg) : slot.after.after(msg);
            c._fvMsg = msg;
        }
        msg.textContent = text;
    }
    function check(c) {
        clearError(c);
        if (c.disabled || c.type === 'hidden' || !visible(c)) return true;
        const m = messageFor(c);
        if (m) showError(c, m);
        return !m;
    }

    function summaryFor(form, count) {
        let s = form.querySelector('.fv-summary');
        const btn = form.querySelector('button:not([type=button]), [type=submit]');
        if (!count) { if (s) s.remove(); return; }
        if (!s) {
            s = document.createElement('p');
            s.className = 'fv-summary';
            s.setAttribute('role', 'alert');
            btn ? btn.before(s) : form.append(s);
            if (btn) s.style.marginBottom = '8px';
        }
        s.textContent = count === 1 ? 'Please fix the highlighted field above.' : 'Please fix the ' + count + ' highlighted fields above.';
    }

    document.addEventListener('submit', (ev) => {
        const form = ev.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-fv-skip')) return;
        const bad = [...form.querySelectorAll(CONTROLS)].filter((c) => !check(c));
        summaryFor(form, bad.length);
        if (bad.length) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
            bad[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            bad[0].focus({ preventScroll: true });
        }
    }, true);

    // Once a field has been flagged, re-check it live so the error clears as they fix it.
    const live = (ev) => {
        const c = ev.target;
        if (!c.matches || !c.matches(CONTROLS) || !c.form) return;
        if (c.getAttribute('aria-invalid') === 'true') check(c);
        // A confirm field depends on its partner.
        c.form.querySelectorAll('[data-match="' + c.name + '"][aria-invalid="true"]').forEach(check);
        if (!c.form.querySelector('[aria-invalid="true"]')) summaryFor(c.form, 0);
    };
    document.addEventListener('input', live);
    document.addEventListener('change', live);

    function init() {
        document.querySelectorAll('form').forEach((f) => f.setAttribute('novalidate', ''));
        syncAllStars(document);
        new MutationObserver((muts) => {
            const labels = new Set();
            for (const m of muts) {
                if (m.type === 'attributes') {
                    const l = labelOf(m.target);
                    if (l) labels.add(l);
                    continue;
                }
                m.addedNodes.forEach((n) => {
                    if (n.nodeType !== 1) return;
                    if (n.tagName === 'FORM') n.setAttribute('novalidate', '');
                    if (n.tagName === 'LABEL') labels.add(n);
                    n.querySelectorAll && n.querySelectorAll('label').forEach((l) => labels.add(l));
                    const l = n.closest && n.closest('label');
                    if (l) labels.add(l);
                });
            }
            labels.forEach(syncStar);
        }).observe(document.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['required', 'disabled'] });
    }
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
</script>
