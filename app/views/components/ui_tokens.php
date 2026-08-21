<?php
/**
 * Shared design-token stylesheet (the .ui-* classes). Emitted once per
 * request via a guard so it can be safely included from any layout —
 * the clinic app shell (layouts/base.php) and the standalone admin pages
 * (admin/_nav.php) alike.
 *
 * Design system: "Apple + Healthcare + Modern SaaS".
 *   Primary   Teal 600 #0F766E (hover #115E59, light #CCFBF1, soft #F0FDFA)
 *   Semantic  success #16A34A · warning #D97706 · danger #DC2626 · info #0284C7
 *   Neutrals  bg #F8FAFC · card #FFFFFF · border #E2E8F0
 *             text #0F172A / #475569 / #64748B
 *   Type      Inter — body 14/400, small 12, section 18/600, buttons 14/600
 *   Radius    cards 12px · buttons+inputs 10px · modals 16px
 *   Motion    150ms, subtle; honors prefers-reduced-motion
 *
 * --brand here is only a FALLBACK. layouts/base.php sets the real
 * per-clinic --brand earlier in :root, and the cascade keeps that value.
 */
if (!defined('UI_TOKENS_EMITTED')) {
    define('UI_TOKENS_EMITTED', true);
?>
<style>
    :root {
        --ui-radius-card: 0.75rem;     /* 12px */
        --ui-radius-control: 0.625rem; /* 10px */
        --ui-radius-modal: 1rem;       /* 16px */
        --ui-border: #E2E8F0;
        --ui-text: #0F172A;
        --ui-text-2: #475569;
        --ui-text-3: #64748B;
        --ui-shadow-card: 0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.05);
        --ui-shadow-pop: 0 4px 6px -1px rgb(15 23 42 / 0.07), 0 10px 15px -3px rgb(15 23 42 / 0.08);
    }

    /* ============================================================
       TYPE SCALE — body 14px / small 12px (Tailwind defaults for
       text-sm / text-xs already match; no remapping needed).
       ============================================================ */
    body { font-size: 14px; line-height: 1.5; color: var(--ui-text); }

    .ui-page-header-title { font-size: 1.5rem;   line-height: 2rem;     font-weight: 700; letter-spacing: -0.02em; color: var(--ui-text); } /* 24/700 */
    .ui-page-title        { font-size: 1.25rem;  line-height: 1.75rem;  font-weight: 600; letter-spacing: -0.01em; color: var(--ui-text); } /* 20/600 */
    .ui-section-title     { font-size: 1.125rem; line-height: 1.5rem;   font-weight: 600; color: var(--ui-text); }                          /* 18/600 */
    .ui-section-sub       { font-size: 0.8125rem; line-height: 1.25rem; color: var(--ui-text-3); }
    .ui-label             { font-size: 0.8125rem; line-height: 1.25rem; font-weight: 500; color: #334155; }
    .ui-group-label       { font-size: 0.6875rem; line-height: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #334155; }
    .ui-help              { font-size: 0.75rem;   line-height: 1.125rem; color: #94A3B8; }

    /* ============================================================
       CARDS — white, subtle border, soft shadow, 12px radius,
       generous padding.
       ============================================================ */
    .ui-card {
        background: #fff; border: 1px solid var(--ui-border);
        border-radius: var(--ui-radius-card);
        box-shadow: var(--ui-shadow-card);
    }
    .ui-card-pad { padding: 1.25rem; }
    .ui-card-header {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        padding: 0.875rem 1.25rem; border-bottom: 1px solid #F1F5F9;
    }
    .ui-row {
        display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
        padding: 0.75rem 0.875rem;
    }

    /* ============================================================
       BUTTONS — 14/600, 10px radius, 150ms, visible focus ring.
       ============================================================ */
    .ui-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.4375rem;
        border-radius: var(--ui-radius-control);
        font-size: 0.875rem; font-weight: 600; line-height: 1.25rem;
        padding: 0.5rem 1rem; transition: all .15s ease;
        cursor: pointer; white-space: nowrap; border: 1px solid transparent;
    }
    .ui-btn-sm { padding: 0.3125rem 0.75rem; font-size: 0.8125rem; }
    .ui-btn-primary { background: var(--brand); color: #fff; }
    .ui-btn-primary:hover { background: var(--brand-dark); }
    .ui-btn-secondary { background: #fff; color: #334155; border-color: #CBD5E1; }
    .ui-btn-secondary:hover { background: #F8FAFC; border-color: #94A3B8; }
    .ui-btn-ghost { background: transparent; color: var(--ui-text-2); }
    .ui-btn-ghost:hover { background: #F1F5F9; }
    .ui-btn-danger { background: #DC2626; color: #fff; }
    .ui-btn-danger:hover { background: #B91C1C; }
    .ui-btn-success { background: #16A34A; color: #fff; }
    .ui-btn-success:hover { background: #15803D; }
    .ui-btn:disabled { opacity: .5; cursor: not-allowed; }
    .ui-btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

    /* ============================================================
       FORMS — labels above fields, 14px controls, 10px radius,
       brand focus ring.
       ============================================================ */
    .ui-input {
        width: 100%; border: 1px solid #CBD5E1; border-radius: var(--ui-radius-control);
        background: #fff; padding: 0.5rem 0.75rem;
        font-size: 0.875rem; line-height: 1.25rem; color: var(--ui-text);
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .ui-input::placeholder { color: #94A3B8; }
    .ui-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-light); }
    input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]),
    select, textarea {
        font-size: 0.875rem;
    }

    /* ============================================================
       BADGES — semantic palette with status dot.
       ============================================================ */
    .ui-badge {
        display: inline-flex; align-items: center; gap: 0.375rem;
        border-radius: 9999px; padding: 0.125rem 0.625rem;
        font-size: 0.75rem; font-weight: 600; line-height: 1.25rem;
    }
    .ui-badge::before { content: ''; width: 0.375rem; height: 0.375rem; border-radius: 9999px; background: currentColor; }
    .ui-badge-success { background: #F0FDF4; color: #16A34A; }
    .ui-badge-danger  { background: #FEF2F2; color: #DC2626; }
    .ui-badge-warning { background: #FFFBEB; color: #D97706; }
    .ui-badge-info    { background: #F0F9FF; color: #0284C7; }
    .ui-badge-neutral { background: #F1F5F9; color: var(--ui-text-2); }

    /* ============================================================
       TABLES — high-density professional tables inside any ui-card.
       Complements (doesn't fight) existing utility classes.
       ============================================================ */
    .ui-card thead th {
        font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--ui-text-3); background: #F8FAFC;
    }
    /* Cell content reads at body size (14px) even where legacy markup says
       text-xs — (0,1,1) specificity beats the utility. Inner spans/divs that
       declare their own size (secondary lines, badges) keep it. */
    .ui-card td { font-size: 0.875rem; }
    .ui-card tbody tr { transition: background .15s ease; }
    .ui-card tbody tr:hover { background: var(--brand-soft, #F0FDFA); }

    /* ============================================================
       TOGGLES / CHECKBOXES / RADIOS
       ============================================================ */
    .ui-toggle { position: relative; display: inline-flex; height: 1.375rem; width: 2.5rem; flex-shrink: 0; cursor: pointer; align-items: center; }
    .ui-toggle input { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; z-index: 2; }
    .ui-toggle-track { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 9999px; background: #D1D9E2; transition: background .15s ease; }
    .ui-toggle-thumb { position: absolute; left: 0.1875rem; top: 50%; transform: translateY(-50%); height: 1rem; width: 1rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 2px rgb(0 0 0 / 0.25); transition: left .15s ease; }
    .ui-toggle input:checked ~ .ui-toggle-track { background: var(--brand); }
    .ui-toggle input:checked ~ .ui-toggle-thumb { left: 1.3125rem; }

    .ui-checkbox, .ui-radio {
        appearance: none; -webkit-appearance: none;
        width: 1rem; height: 1rem; flex-shrink: 0;
        border: 1.5px solid #CBD5E1; background: #fff; cursor: pointer;
        transition: border-color .15s, background .15s;
        display: inline-grid; place-content: center;
    }
    .ui-checkbox { border-radius: 0.25rem; }
    .ui-radio { border-radius: 9999px; }
    .ui-checkbox:checked, .ui-radio:checked { background: var(--brand); border-color: var(--brand); }
    .ui-checkbox:checked::before {
        content: ''; width: 0.5625rem; height: 0.5625rem;
        background: #fff; clip-path: polygon(13% 50%, 34% 71%, 87% 18%, 96% 27%, 34% 89%, 4% 59%);
    }
    .ui-radio:checked::before { content: ''; width: 0.4375rem; height: 0.4375rem; border-radius: 9999px; background: #fff; }
    .ui-checkbox:focus-visible, .ui-radio:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

    /* Section block inside a settings/list page */
    .ui-section { padding: 1rem 0; border-top: 1px solid #F1F5F9; }
    .ui-section:first-child { border-top: 0; padding-top: 0; }

    /* ============================================================
       LOADING SKELETONS — shimmer placeholder for async content.
       Usage: <div class="ui-skeleton h-4 w-32"></div>
       ============================================================ */
    .ui-skeleton {
        background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 37%, #F1F5F9 63%);
        background-size: 400% 100%;
        animation: ui-shimmer 1.4s ease infinite;
        border-radius: 0.5rem;
    }
    @keyframes ui-shimmer {
        0% { background-position: 100% 0; }
        100% { background-position: 0 0; }
    }

    /* Accessibility: respect users who opt out of motion. */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* ============================================================
       BRAND-COLOR BRIDGE — legacy markup uses hardcoded `emerald-*`
       Tailwind classes (429 across the app). Remap them to the clinic
       --brand variable so changing brand color in Settings reflects
       site-wide without editing every file. Strong shades -> --brand,
       light tints -> --brand-light, dark hovers -> --brand-dark.
       ============================================================ */
    .bg-emerald-600, .bg-emerald-700, .bg-emerald-500 { background-color: var(--brand) !important; }
    .hover\:bg-emerald-700:hover, .hover\:bg-emerald-600:hover { background-color: var(--brand-dark) !important; }
    .bg-emerald-50, .bg-emerald-100 { background-color: var(--brand-light) !important; }
    .hover\:bg-emerald-50:hover, .hover\:bg-emerald-100:hover { background-color: var(--brand-light) !important; }
    .text-emerald-600, .text-emerald-700, .text-emerald-800, .text-emerald-900 { color: var(--brand) !important; }
    .hover\:text-emerald-700:hover, .hover\:text-emerald-800:hover { color: var(--brand-dark) !important; }
    .border-emerald-200, .border-emerald-300, .border-emerald-400, .border-emerald-500 { border-color: var(--brand) !important; }
    .ring-emerald-500, .ring-emerald-400 { --tw-ring-color: var(--brand) !important; }
    .focus\:ring-emerald-500:focus, .focus\:border-emerald-500:focus { --tw-ring-color: var(--brand) !important; border-color: var(--brand) !important; }
    .focus-within\:ring-emerald-500:focus-within { --tw-ring-color: var(--brand) !important; }
    .focus-within\:border-emerald-500:focus-within { border-color: var(--brand) !important; }
</style>
<?php } ?>
