<?php
/**
 * Shared design-token stylesheet (the .ui-* classes). Emitted once per
 * request via a guard so it can be safely included from any layout —
 * the clinic app shell (layouts/base.php) and the standalone admin pages
 * (admin/_nav.php) alike.
 *
 * --brand here is only a FALLBACK. layouts/base.php sets the real
 * per-clinic --brand earlier in :root, and the cascade keeps that value.
 * Admin pages have no clinic brand, so they fall back to slate.
 */
if (!defined('UI_TOKENS_EMITTED')) {
    define('UI_TOKENS_EMITTED', true);
?>
<style>
    :root {
        --ui-radius-card: 0.75rem;
        --ui-radius-control: 0.5rem;
    }
    .ui-page-title   { font-size: 1.125rem; line-height: 1.5rem; font-weight: 600; color: #0f172a; letter-spacing: -0.01em; }
    .ui-section-title{ font-size: 1rem;     line-height: 1.5rem; font-weight: 600; color: #0f172a; }
    .ui-section-sub  { font-size: 0.8125rem;line-height: 1.25rem; color: #64748b; }
    .ui-label        { font-size: 0.8125rem;line-height: 1.25rem; font-weight: 500; color: #334155; }
    .ui-group-label  { font-size: 0.625rem; line-height: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
    .ui-help         { font-size: 0.75rem;  line-height: 1rem; color: #94a3b8; }
    .ui-card {
        background: #fff; border: 1px solid #e9eef4;
        border-radius: var(--ui-radius-card);
        box-shadow: 0 1px 2px 0 rgb(16 24 40 / 0.04), 0 1px 3px 0 rgb(16 24 40 / 0.06);
    }
    .ui-card-pad { padding: 1.5rem 1.75rem; }
    .ui-card-header {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 1.25rem 1.75rem; border-bottom: 1px solid #f1f5f9;
    }
    /* A subtle row used for settings/list items */
    .ui-row {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: 0.875rem 1rem;
    }
    .ui-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        border-radius: var(--ui-radius-control);
        font-size: 0.875rem; font-weight: 600; line-height: 1.25rem;
        padding: 0.5rem 1rem; transition: all .15s ease;
        cursor: pointer; white-space: nowrap; border: 1px solid transparent;
    }
    .ui-btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8125rem; }
    .ui-btn-primary { background: var(--brand); color: #fff; }
    .ui-btn-primary:hover { background: var(--brand-dark); }
    .ui-btn-secondary { background: #fff; color: #334155; border-color: #cbd5e1; }
    .ui-btn-secondary:hover { background: #f8fafc; }
    .ui-btn-ghost { background: transparent; color: #475569; }
    .ui-btn-ghost:hover { background: #f1f5f9; }
    .ui-btn-danger { background: #dc2626; color: #fff; }
    .ui-btn-danger:hover { background: #b91c1c; }
    .ui-btn:disabled { opacity: .5; cursor: not-allowed; }
    .ui-input {
        width: 100%; border: 1px solid #cbd5e1; border-radius: var(--ui-radius-control);
        background: #fff; padding: 0.5rem 0.75rem;
        font-size: 0.875rem; line-height: 1.25rem; color: #0f172a;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    .ui-input::placeholder { color: #94a3b8; }
    .ui-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-light); }
    .ui-badge {
        display: inline-flex; align-items: center; gap: 0.375rem;
        border-radius: 9999px; padding: 0.125rem 0.625rem;
        font-size: 0.75rem; font-weight: 600; line-height: 1.25rem;
    }
    .ui-badge::before { content: ''; width: 0.375rem; height: 0.375rem; border-radius: 9999px; background: currentColor; }
    .ui-badge-success { background: #ecfdf5; color: #047857; }
    .ui-badge-danger  { background: #fef2f2; color: #b91c1c; }
    .ui-badge-warning { background: #fffbeb; color: #b45309; }
    .ui-badge-neutral { background: #f1f5f9; color: #475569; }
    .ui-toggle { position: relative; display: inline-flex; height: 1.625rem; width: 3rem; flex-shrink: 0; cursor: pointer; align-items: center; }
    .ui-toggle input { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; z-index: 2; }
    .ui-toggle-track { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 9999px; background: #d1d9e2; transition: background .2s ease; }
    .ui-toggle-thumb { position: absolute; left: 0.1875rem; top: 50%; transform: translateY(-50%); height: 1.25rem; width: 1.25rem; border-radius: 9999px; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / 0.25); transition: left .2s ease; }
    .ui-toggle input:checked ~ .ui-toggle-track { background: var(--brand); }
    .ui-toggle input:checked ~ .ui-toggle-thumb { left: 1.5625rem; }
</style>
<?php } ?>
