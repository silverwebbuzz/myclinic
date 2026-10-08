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
        ['/admin/payments', 'Payments', 'M1 4h22v16H1zM1 10h22M5 15h4'],
        ['/admin/claims', 'Claims', 'M9 12l2 2 4-4M7 3h10l2 4v13H5V7z', (int) ($pendingClaimCount ?? 0)],
        ['/admin/leads', 'Leads', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 100 8 4 4 0 000-8z'],
        ['/admin/outreach', 'Outreach', 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
        ['/admin/wordpress-doctors', 'Blog access', 'M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z'],
    ],
    'Catalog' => [
        ['/admin/plans', 'Plans', 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2'],
        ['/admin/discounts', 'Plan Discounts', 'M9 14l6-6M9.5 8.5h.01M14.5 13.5h.01M5 3h14a2 2 0 012 2v14l-3-2-3 2-3-2-3 2-3-2-3 2V5a2 2 0 012-2z'],
        ['/admin/specialties', 'Specialties', 'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.29 1.51 4.04 3 5.5l7 7 7-7z'],
        ['/admin/locations', 'States & Cities', 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1112 6.5a2.5 2.5 0 010 5z'],
        ['/admin/rx-templates', 'Rx Templates', 'M9 2h6v4H9zM4 6h16v16H4zM8 12h8M8 16h5'],
    ],
    'Lab Tests' => [
        ['/admin/lab/orders', 'Bookings', 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11'],
        ['/admin/lab/products', 'Tests & Packages', 'M9 3h6v2l-1 1v4.586l4.707 4.707A2 2 0 0117.293 19H6.707a2 2 0 01-1.414-3.707L10 10.586V6L9 5V3z'],
        ['/admin/lab/categories', 'Categories', 'M4 6h16M4 10h16M4 14h10M4 18h10'],
        ['/admin/lab/coupons', 'Discount Coupons', 'M9 5H7a2 2 0 00-2 2v3a2 2 0 010 4v3a2 2 0 002 2h2M9 5h8a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H9M9 5v14'],
    ],
    'Growth' => [
        ['/admin/partners', 'Partners', 'M17 21v-2a4 4 0 00-3-3.87M9 21v-2a4 4 0 013-3.87M12 7a4 4 0 100 8 4 4 0 000-8z'],
        ['/admin/partner-payouts', 'Payouts', 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
        ['/admin/reviews', 'Reviews', 'M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z'],
        ['/admin/symptom-promotions', 'Symptoms', 'M22 12h-4l-3 9L9 3l-3 9H2'],
    ],
    'System' => [
        ['/admin/feature-flags', 'Feature Flags', 'M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7'],
        ['/admin/misc', 'Misc', 'M4 7h16M4 12h16M4 17h10'],
        ['/admin/app-versions', 'App Versions', 'M7 2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4a2 2 0 012-2zM11 18h2'],
        ['/admin/recaptcha', 'reCAPTCHA', 'M12 2a10 10 0 100 20 10 10 0 000-20zm1 14h-2v-2h2v2zm2.07-7.75l-.9.92A3.49 3.49 0 0013 12h-2v-.5c0-.83.34-1.58.88-2.12l1.24-1.26a1.99 1.99 0 10-3.4-1.41H7.72a4 4 0 117.35 2.54z'],
        ['/admin/messaging', 'Messaging', 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z'],
        ['/admin/email', 'Email', 'M4 4h16v16H4zM4 8l8 5 8-5'],
        ['/admin/email-templates', 'Email Templates', 'M4 4h16v16H4zM8 8h8M8 12h8M8 16h4'],
        ['/admin/payment-gateway', 'Payment Gateway', 'M1 4h22v16H1zM1 10h22'],
        ['/admin/wordpress-settings', 'WordPress', 'M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4L16.5 3.5z'],
    ],
];

// The Store (marketplace) has its own menu: main admin shows ONE "Store" link;
// inside /admin/store/* the sidebar switches to the store's menu.
$vendorsPending = $productsPending = 0;
try {
    $vendorsPending = \App\Services\Store\VendorService::pendingReviewCount();
    $productsPending = \App\Services\Store\ProductService::pendingReviewCount();
} catch (\Throwable) {
}
$storePending = $vendorsPending + $productsPending;
$inStore = $adminPath === '/admin/store' || str_starts_with($adminPath, '/admin/store/');
if ($inStore) {
    $navGroups = [
        'Store' => [
            ['/admin/store/dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10h14V10'],
            ['/admin/store/reports', 'Reports', 'M3 3v18h18M7 14l4-4 4 4 5-6'],
        ],
        'Orders & money' => [
            ['/admin/store/orders', 'Orders', 'M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11'],
            ['/admin/store/returns', 'Returns', 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5'],
            ['/admin/store/payouts', 'Seller payouts', 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
            ['/admin/store/disputes', 'Charge disputes', 'M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z'],
            ['/admin/store/gst', 'GST register', 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M8 13h8M8 17h8'],
            ['/admin/store/accounts', 'Accounts (for CA)', 'M4 4h16v16H4zM4 9h16M9 9v11'],
        ],
        'Sellers & catalog' => [
            ['/admin/store/vendors', 'Sellers', 'M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6', $vendorsPending],
            ['/admin/store/products', 'Products', 'M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16zM3.3 7L12 12l8.7-5M12 22V12', $productsPending],
            ['/admin/store/categories', 'Categories', 'M4 6h16M4 10h16M4 14h10M4 18h10'],
            ['/admin/store/hsn', 'HSN codes & GST', 'M4 7h16M4 12h16M4 17h10M17 17l2 2 3-4'],
            ['/admin/store/brands', 'Brands', 'M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82zM7 7h.01'],
        ],
        'Marketing' => [
            ['/admin/store/coupons', 'Coupons', 'M9 14l6-6M9.5 8.5h.01M14.5 13.5h.01M5 3h14a2 2 0 012 2v14l-3-2-3 2-3-2-3 2-3-2-3 2V5a2 2 0 012-2z'],
            ['/admin/store/rewards', 'Points & referrals', 'M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7zM12 8v5'],
            ['/admin/store/banners', 'Banners', 'M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6'],
            ['/admin/store/reviews', 'Reviews', 'M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z'],
        ],
        'Setup' => [
            ['/admin/store/policies/seller_terms', 'Seller terms', 'M9 12l2 2 4-4M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7z'],
            ['/admin/store/email', 'Store email', 'M4 4h16v16H4zM4 6l8 7 8-7'],
            ['/admin/store/email-templates', 'Email templates', 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h5'],
            ['/admin/store/settings', 'Store settings', 'M12 15a3 3 0 100-6 3 3 0 000 6zM4 12h2M18 12h2M12 4v2M12 18v2'],
        ],
    ];
} else {
    $navGroups['Overview'][] = ['/admin/store/dashboard', 'Store (marketplace)', 'M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18M9 20v-6h6v6', $storePending];
}

// Current item: exact match, else the longest parent path (/admin/store/orders/12 → Orders).
$activeHref = null;
$crumbs = [];
foreach ($navGroups as $group => $items) {
    foreach ($items as $item) {
        $href = $item[0];
        if (($adminPath === $href || str_starts_with($adminPath, $href . '/')) && ($activeHref === null || strlen($href) > strlen($activeHref))) {
            $activeHref = $href;
            $crumbs = [$group, $item[1]];
        }
    }
}
$crumbs = array_merge([$inStore ? 'Store admin' : 'Admin'], $crumbs);
$sa = \App\Core\RequestContext::superAdmin() ?? [];
$adminName = trim((string) ($sa['name'] ?? '')) ?: 'Super admin';
$adminEmail = (string) ($sa['email'] ?? '');
$adminInitials = strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', $adminName) ?: [], 0, 2)))) ?: 'A';
?>
<?php require dirname(__DIR__) . '/components/ui_tokens.php'; ?>
<?php
// The sidebar needs Alpine, and _nav.php is the single global loader for every
// admin page (per-page Alpine tags were removed). PHP constant guards against
// double-injection if _nav is ever included twice in one render.
if (!defined('ECP_ADMIN_ALPINE_LOADED')) {
    define('ECP_ADMIN_ALPINE_LOADED', true);
    echo '<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>';
    // Date boxes show 06-10-2026 on desktop; phones keep their own date wheel (file lives on the main site).
    echo '<script defer src="https://eclinicpro.com/assets/js/ecp-datepicker.js?v=20261007"></script>';
}
?>
<script>
    // Sidebar open state (phones only) in a global Alpine store so the top-bar
    // toggle can flip it without relying on Alpine internals.
    document.addEventListener('alpine:init', () => {
        Alpine.store('adminNav', { open: false });
    });
</script>
<?php if (!defined('ECP_ADMIN_SKIN_EMITTED')): define('ECP_ADMIN_SKIN_EMITTED', true); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
    /* ============================================================
       Admin skin — same console look as the seller portal
       (store_vendor/_layout.php): Inter 13px, slate neutrals, 10px
       cards, emerald accent, dark 244px sidebar + 56px top bar.
       Pages keep their own markup; this only re-skins it.
       ============================================================ */
    :root {
        --brand: #059669; --brand-dark: #047857; --brand-light: #ecfdf5; --brand-soft: #f8fafc;
        --ui-border: #e4e7ec; --ui-shadow-card: none;
    }
    body {
        background: #f5f6f8 !important; color: #0f172a;
        font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 13px;
        font-feature-settings: 'tnum' 1, 'cv11' 1; -webkit-font-smoothing: antialiased;
        /* Reserve the fixed sidebar's width on <body>, not <main>: pages render
           <main class="mx-auto max-w-6xl">, and Tailwind's .mx-auto would beat a
           bare `body > main` margin rule. */
        padding-left: 244px;
    }
    @media (max-width: 767px) { body { padding-left: 0; } }
    [x-cloak] { display: none !important; }
    .font-mono, code { font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, monospace; }

    /* Page title */
    body > main h1 { font-size: 22px; line-height: 1.3; font-weight: 600; letter-spacing: -.015em; }

    /* Cards / panels: flat, hairline border, 10px radius */
    .ui-card { border-radius: 10px; border-color: #e4e7ec; box-shadow: none; }
    body > main .rounded-xl, body > main .rounded-2xl { border-radius: 10px; }
    body > main .shadow-sm, body > main .shadow { box-shadow: none; }
    body > main .border-slate-200, body > main .border-slate-100, body > main .border-gray-200 { border-color: #e4e7ec; }
    body > main .border:not([class*="border-"]) { border-color: #e4e7ec; }
    body > main .divide-y > :not([hidden]) ~ :not([hidden]) { border-color: #eef0f3; }

    /* Tables */
    thead { background: #f8fafc; }
    thead th { color: #64748b; font-weight: 600; }
    tbody tr { transition: background .15s ease; }
    tbody tr:hover { background: #f8fafc; }

    /* Form controls: 7px radius, hairline border, emerald focus ring */
    input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]):not([type="file"]):not([type="hidden"]):not([type="submit"]):not([type="button"]),
    select, textarea {
        border-color: #e4e7ec; border-radius: 7px; background-color: #fff;
    }
    input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"]):not([type="file"]):focus,
    select:focus, textarea:focus {
        outline: none; border-color: #059669; box-shadow: 0 0 0 3px rgb(5 150 105 / .15);
    }
    input[type="checkbox"], input[type="radio"] { accent-color: #059669; }

    /* Primary actions: the old dark-slate buttons become the emerald accent */
    .bg-slate-700, .bg-slate-800, .bg-slate-900 { background-color: #059669; }
    .hover\:bg-slate-700:hover, .hover\:bg-slate-800:hover, .hover\:bg-slate-900:hover { background-color: #047857; }
    .bg-slate-700, .bg-slate-800, .bg-slate-900, .ui-btn { border-radius: 7px; }
    /* …except selected filter pills/tabs, which use the soft accent like the seller portal */
    a.rounded-full.bg-slate-800, a.rounded-full.bg-slate-900, button.rounded-full.bg-slate-800, button.rounded-full.bg-slate-900,
    a.rounded-lg.bg-slate-800, a.rounded-lg.bg-slate-900 {
        background-color: #ecfdf5; color: #065f46; font-weight: 600; border-radius: 6px;
    }
    a.rounded-full:not(.bg-slate-800):not(.bg-slate-900).bg-white { border: 1px solid #e4e7ec; border-radius: 6px; color: #475569; }
    a.rounded-full:not(.bg-slate-800):not(.bg-slate-900).bg-white:hover { background: #f8fafc; }
</style>
<?php endif; ?>

<aside class="fixed inset-y-0 left-0 z-40 flex w-[244px] -translate-x-full flex-col bg-[#0b1220] text-[#C8D1DF] transition-transform duration-200 md:translate-x-0"
       x-data :class="$store.adminNav && $store.adminNav.open ? '!translate-x-0' : ''">
    <div class="flex h-14 flex-none items-center gap-2.5 border-b border-white/5 px-[18px]">
        <a href="<?= $inStore ? '/admin/store/dashboard' : '/admin/dashboard' ?>" class="grid h-7 w-7 flex-none place-items-center rounded-lg bg-[#059669] text-sm font-bold text-white">e</a>
        <div class="flex min-w-0 flex-col leading-tight">
            <span class="text-sm font-semibold tracking-tight text-white"><?= $inStore ? 'eClinicPro Store' : 'eClinicPro' ?></span>
            <span class="whitespace-nowrap text-[11px] text-[#8391A8]"><?= $inStore ? 'Marketplace admin' : 'Super admin console' ?></span>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-2.5 pb-4 pt-2">
        <?php if ($inStore): ?>
            <a href="/admin/dashboard" class="mt-2 flex h-8 items-center gap-2 rounded-[7px] px-2.5 text-[12.5px] font-medium text-[#8391A8] hover:bg-white/5 hover:text-white">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                Back to main admin
            </a>
        <?php endif; ?>
        <?php foreach ($navGroups as $group => $items): ?>
            <div class="mt-3">
                <div class="px-2.5 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.06em] text-[#6B778C]"><?= htmlspecialchars($group) ?></div>
                <?php foreach ($items as $item): ?>
                    <?php [$href, $label] = $item; $badge = (int) ($item[3] ?? 0); $active = $href === $activeHref; ?>
                    <a href="<?= htmlspecialchars($href) ?>" class="flex h-8 w-full items-center gap-2.5 rounded-[7px] px-2.5 text-[13px] <?= $active ? 'bg-white/[.08] font-semibold text-white' : 'font-medium text-[#C8D1DF] hover:bg-white/5' ?>">
                        <span class="h-1.5 w-1.5 flex-none rounded-[2px] <?= $active ? 'bg-[#059669]' : 'bg-white/30' ?>"></span>
                        <span class="flex-1 truncate"><?= htmlspecialchars($label) ?></span>
                        <?php if ($badge > 0): ?>
                            <span class="grid h-[18px] min-w-[18px] place-items-center rounded-full bg-[#059669] px-1.5 text-[10.5px] font-semibold text-white"><?= $badge ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="flex flex-none items-center gap-2.5 border-t border-white/5 px-3.5 py-3">
        <div class="grid h-[30px] w-[30px] flex-none place-items-center rounded-full bg-[#1E293B] text-[11.5px] font-semibold text-[#E2E8F0]"><?= htmlspecialchars($adminInitials) ?></div>
        <div class="min-w-0 leading-tight">
            <div class="truncate text-[12.5px] font-medium text-white"><?= htmlspecialchars($adminName) ?></div>
            <div class="truncate text-[11.5px] text-[#8391A8]"><?= htmlspecialchars($adminEmail ?: 'Platform admin') ?></div>
        </div>
        <span class="flex-1"></span>
        <form method="post" action="/admin/logout">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? \App\Services\CsrfService::token()) ?>">
            <button type="submit" class="h-[26px] flex-none whitespace-nowrap rounded-md border border-white/10 px-2 text-[11.5px] text-[#C8D1DF] hover:bg-white/5">Sign out</button>
        </form>
    </div>
</aside>

<!-- Phones: dim + tap-to-close backdrop behind the open sidebar -->
<div class="fixed inset-0 z-30 bg-black/40 md:hidden" x-data x-cloak
     x-show="$store.adminNav.open" @click="$store.adminNav.open = false" aria-hidden="true"></div>

<header class="sticky top-0 z-20 flex h-14 items-center gap-2 border-b border-[#e4e7ec] bg-white px-3 md:gap-3.5 md:px-5" x-data>
    <button type="button" @click="$store.adminNav.open = !$store.adminNav.open" title="Menu" aria-label="Toggle menu"
            class="grid h-8 w-8 flex-none place-items-center rounded-lg border border-[#e4e7ec] bg-white text-[#475569] hover:bg-[#f8fafc] md:hidden">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
    <div class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[13px] max-md:flex-1">
        <?php foreach ($crumbs as $i => $c): $last = $i === count($crumbs) - 1; ?>
            <span class="flex min-w-0 items-center gap-1.5 <?= $last ? 'truncate' : 'max-md:hidden' ?>">
                <?php if ($i > 0): ?><span class="text-[#e4e7ec] max-md:hidden">/</span><?php endif; ?>
                <span class="<?= $last ? 'font-medium text-[#0f172a]' : 'text-[#64748b]' ?>"><?= htmlspecialchars($c) ?></span>
            </span>
        <?php endforeach; ?>
    </div>
    <span class="flex-1 max-md:hidden"></span>
    <a href="<?= $inStore ? 'https://eclinicpro.com/store' : 'https://eclinicpro.com/' ?>" target="_blank" rel="noopener"
       class="inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-[7px] border border-[#e4e7ec] bg-white px-3 text-[13px] font-medium text-[#0f172a] hover:bg-[#f8fafc] max-md:hidden"><?= $inStore ? 'View store ↗' : 'View site ↗' ?></a>
    <span class="inline-flex h-[26px] items-center gap-1.5 whitespace-nowrap rounded-full bg-[#f1f5f9] max-md:hidden px-2.5 text-xs font-medium text-[#475569]">
        <span class="h-1.5 w-1.5 rounded-full bg-[#059669]"></span>Super admin
    </span>
</header>
