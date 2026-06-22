<?php
// Admin left sidebar — grouped, collapsible, icon nav. Self-contained: each
// admin page does `require _nav.php` right after <body>, then renders its own
// <main>. We render a FIXED sidebar and shift the page's <main> over via CSS
// so no per-page markup changes are needed.

// Pending claims badge (compute if the parent view didn't pass it).
if (!isset($pendingClaimCount)) {
    try {
        $pendingClaimCount = \App\Services\DoctorClaimService::pendingCount();
    } catch (\Throwable $e) {
        $pendingClaimCount = 0;
    }
}

// Admin pages are standalone (own <head>, no clinic shell): pull in shared UI
// helpers + token stylesheet. Neutral slate brand for the .ui-* colors.
require_once dirname(__DIR__) . '/components/ui.php';

// Active-link detection from the current path.
$adminPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';

/**
 * Nav groups. Each item: [href, label, svg-icon-path-d, optional badge].
 * `match` = path prefixes that mark the item active.
 */
$navGroups = [
    'Overview' => [
        ['/admin/dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
    ],
    'Clinics & Users' => [
        ['/admin/clinics', 'Clinics', 'M3 21V8l9-5 9 5v13M9 21v-6h6v6'],
        ['/admin/patients', 'Patients', 'M17 21v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M12 3a4 4 0 100 8 4 4 0 000-8z'],
        ['/admin/signups', 'Online Signups', 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 3a4 4 0 100 8 4 4 0 000-8zM19 8v6M22 11h-6'],
        ['/admin/claims', 'Claims', 'M9 12l2 2 4-4M7 3h10l2 4v13H5V7z', (int) ($pendingClaimCount ?? 0)],
        ['/admin/leads', 'Leads', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8z'],
    ],
    'Catalog' => [
        ['/admin/plans', 'Plans', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2'],
        ['/admin/specialties', 'Specialties', 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.29 1.51 4.04 3 5.5l7 7 7-7z'],
        ['/admin/rx-templates', 'Rx Templates', 'M9 2h6v4H9zM4 6h16v16H4zM8 12h8M8 16h5'],
    ],
    'Growth' => [
        ['/admin/partners', 'Partners', 'M17 21v-2a4 4 0 00-3-3.87M9 21v-2a4 4 0 013-3.87M12 7a4 4 0 100 8 4 4 0 000-8z'],
        ['/admin/partner-payouts', 'Payouts', 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
        ['/admin/reviews', 'Reviews', 'M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z'],
        ['/admin/symptom-promotions', 'Symptoms', 'M22 12h-4l-3 9L9 3l-3 9H2'],
    ],
    'System' => [
        ['/admin/feature-flags', 'Feature Flags', 'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7'],
        ['/admin/messaging', 'Messaging', 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z'],
        ['/admin/email', 'Email', 'M4 4h16v16H4zM4 8l8 5 8-5'],
        ['/admin/email-templates', 'Email Templates', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h4'],
        ['/admin/payment-gateway', 'Payment Gateway', 'M1 4h22v16H1zM1 10h22'],
    ],
];

$isActive = static fn (string $href): bool => $adminPath === $href || str_starts_with($adminPath, $href . '/');
?>
<?php require dirname(__DIR__) . '/components/ui_tokens.php'; ?>
<?php
// The sidebar needs Alpine, and _nav.php is the single global loader for every
// admin page (per-page Alpine tags were removed). PHP constant guards against
// double-injection if _nav is ever included twice in one render.
if (!defined('ECP_ADMIN_ALPINE_LOADED')) {
    define('ECP_ADMIN_ALPINE_LOADED', true);
    echo '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>';
}
?>
<script>
    // Sidebar open state in a global Alpine store so the mobile toggle button
    // (outside the sidebar) can flip it without relying on Alpine internals.
    document.addEventListener('alpine:init', () => {
        Alpine.store('adminNav', { open: window.matchMedia('(min-width:768px)').matches });
    });
</script>
<style>
    :root { --brand: #334155; --brand-light: rgba(51,65,85,0.1); --brand-dark: #1e293b; }
    /* Shift each admin page's <main> right to clear the fixed sidebar.
       Pages render <main> as a direct child of <body> after this nav. */
    body > main { margin-left: 16rem; max-width: none; }
    @media (max-width: 768px) { body > main { margin-left: 0; } }
    .admin-side { width: 16rem; }
    .admin-link.active { background: rgba(255,255,255,0.12); color: #fff; }
    .admin-link.active .admin-ico { color: #fff; }
</style>

<aside class="admin-side fixed inset-y-0 left-0 z-30 flex flex-col bg-slate-900 text-slate-300"
       :class="$store.adminNav.open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
       style="transition: transform .2s">
    <div class="flex items-center gap-2 px-5 py-4 border-b border-white/10">
        <a href="/admin/dashboard" class="font-semibold text-white">eClinicPro Admin</a>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5 text-sm" x-data="{ collapsed: {} }">
        <?php foreach ($navGroups as $group => $items): ?>
        <div>
            <button type="button"
                    @click="collapsed['<?= $group ?>'] = !collapsed['<?= $group ?>']"
                    class="flex w-full items-center justify-between px-2 mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500 hover:text-slate-300">
                <span><?= htmlspecialchars($group) ?></span>
                <svg class="h-3 w-3 transition-transform" :class="collapsed['<?= $group ?>'] ? '-rotate-90' : ''"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div x-show="!collapsed['<?= $group ?>']" class="space-y-0.5">
                <?php foreach ($items as $item): ?>
                <?php [$href, $label, $iconD] = $item; $badge = $item[3] ?? 0; ?>
                <a href="<?= htmlspecialchars($href) ?>"
                   class="admin-link flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5 hover:text-white <?= $isActive($href) ? 'active' : '' ?>">
                    <svg class="admin-ico h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $iconD ?>"/></svg>
                    <span class="flex-1"><?= htmlspecialchars($label) ?></span>
                    <?php if ($badge > 0): ?>
                    <span class="inline-flex items-center rounded-full bg-amber-500 px-2 text-[10px] font-semibold text-white"><?= (int) $badge ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="/admin/logout" class="border-t border-white/10 px-3 py-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? \App\Services\CsrfService::token()) ?>">
        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-white/5 hover:text-white">
            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            Log out
        </button>
    </form>
</aside>

<!-- Mobile: toggle button to reveal the sidebar -->
<button type="button" class="fixed left-3 top-3 z-40 rounded-lg bg-slate-900 p-2 text-white md:hidden"
        x-data @click="$store.adminNav.open = !$store.adminNav.open"
        aria-label="Toggle menu">
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
</button>
