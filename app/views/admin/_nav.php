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

    /* Page area: left-aligned, 20px gutter like the seller console. List pages
       (5xl and wider) use the full width; form pages keep their reading width. */
    body > main { margin-left: 0 !important; margin-right: 0 !important; padding: 20px !important; }
    body > main.max-w-5xl, body > main.max-w-6xl, body > main.max-w-7xl { max-width: none !important; }
    @media (max-width: 767px) { body > main { padding: 12px !important; } }

    /* Page title + section titles */
    body > main h1 { font-size: 22px; line-height: 1.3; font-weight: 600; letter-spacing: -.015em; }
    body > main h1 ~ p:is(.text-slate-500, .text-slate-600, .text-gray-500) { font-size: 13px; color: #64748b; }
    body > main h2 { font-size: 14px; line-height: 1.4; font-weight: 600; letter-spacing: 0; }
    body > main h3 { font-size: 13px; font-weight: 600; }
    body > main .text-2xl, body > main .text-3xl { font-size: 19px; line-height: 1.35; font-weight: 600; letter-spacing: -.02em; }
    body > main .ui-card > p.text-xs:first-child { color: #475569; }

    /* Cards / panels: flat, hairline border, 10px radius */
    .ui-card { border-radius: 10px; border-color: #e4e7ec; box-shadow: none; }
    body > main .rounded-xl, body > main .rounded-2xl { border-radius: 10px; }
    body > main .shadow-sm, body > main .shadow { box-shadow: none; }
    body > main .border-slate-200, body > main .border-slate-100, body > main .border-gray-200 { border-color: #e4e7ec; }
    body > main .border:not([class*="border-"]) { border-color: #e4e7ec; }
    body > main .divide-y > :not([hidden]) ~ :not([hidden]) { border-color: #eef0f3; }

    /* White panels get the hairline border even when the page only gave them a shadow */
    body > main :is(div, section, form).bg-white[class*="rounded"]:not(.rounded-full) {
        border: 1px solid #e4e7ec; border-radius: 10px;
    }

    /* Tables: 13px rows, quiet sentence-case header strip, hairline row rules */
    body > main table { font-size: 13px; }
    body > main thead, body > main thead tr, body > main thead th { background: #f8fafc !important; border-color: #eef0f3; }
    body > main thead th {
        color: #64748b !important; font-size: 12px !important; font-weight: 500 !important; text-transform: none !important; letter-spacing: 0 !important;
        padding-top: 8px !important; padding-bottom: 8px !important; white-space: nowrap;
    }
    body > main .ui-card td, body > main td { font-size: 13px; }
    body > main td { padding-top: 10px; padding-bottom: 10px; }
    body > main td.py-1, body > main td.py-1\.5, body > main td.py-2 { padding-top: 8px; padding-bottom: 8px; }
    body > main tbody tr { border-color: #eef0f3; transition: background .15s ease; }
    body > main tbody tr:hover { background: #f8fafc; }

    /* Status badges (small tinted spans): 22px, 6px radius, seller-console tones */
    body > main span.text-xs[class*="bg-"][class*="px-"], body > main span.text-\[11px\][class*="bg-"][class*="px-"] {
        display: inline-flex; align-items: center; min-height: 22px; padding-top: 0; padding-bottom: 0;
        border-radius: 6px; font-weight: 500; white-space: nowrap;
    }
    body > main span.bg-emerald-100, body > main span.bg-emerald-50, body > main span.bg-green-100, body > main span.bg-green-50, body > main span.bg-teal-100 { background: #ecfdf5 !important; color: #047857 !important; }
    body > main span.bg-amber-100, body > main span.bg-amber-50, body > main span.bg-yellow-100, body > main span.bg-orange-100, body > main span.bg-orange-50 { background: #fffbeb !important; color: #b45309 !important; }
    body > main span.bg-red-100, body > main span.bg-red-50, body > main span.bg-rose-100, body > main span.bg-rose-50 { background: #fef2f2 !important; color: #b91c1c !important; }
    body > main span.bg-sky-100, body > main span.bg-sky-50, body > main span.bg-blue-100, body > main span.bg-blue-50, body > main span.bg-indigo-100, body > main span.bg-cyan-100 { background: #eff6ff !important; color: #1d4ed8 !important; }
    body > main span.bg-violet-100, body > main span.bg-purple-100, body > main span.bg-fuchsia-100 { background: #f5f3ff !important; color: #6d28d9 !important; }
    body > main span.bg-slate-100, body > main span.bg-slate-200, body > main span.bg-gray-100, body > main span.bg-gray-200 { background: #f1f5f9 !important; color: #475569 !important; }

    /* Row actions (text links/buttons in a table's last column) become compact outline buttons */
    body > main td:last-child:not(:first-child) a:not([class*="bg-"]),
    body > main td:last-child:not(:first-child) button:not([class*="bg-"]) {
        display: inline-flex; align-items: center; height: 28px; padding: 0 10px; margin: 2px 0;
        border: 1px solid #e4e7ec; border-radius: 7px; background: #fff;
        font-size: 12.5px; font-weight: 500; text-decoration: none !important; white-space: nowrap;
    }
    body > main td:last-child:not(:first-child) a:not([class*="bg-"]):not([class*="text-"]),
    body > main td:last-child:not(:first-child) a.text-slate-700, body > main td:last-child:not(:first-child) a.text-slate-600 { color: #0f172a; }
    body > main td:last-child:not(:first-child) a:not([class*="bg-"]):hover,
    body > main td:last-child:not(:first-child) button:not([class*="bg-"]):hover { background: #f8fafc; }
    body > main td:last-child.space-x-3 > * + *, body > main td:last-child.space-x-2 > * + * { margin-left: 6px; }

    /* Notices / flash boxes: tinted with a matching hairline border, 10px radius */
    body > main :is(p, div)[class*="px-"].bg-emerald-50 { border: 1px solid rgb(4 120 87 / .2); color: #047857; border-radius: 10px; background: #ecfdf5 !important; }
    body > main :is(p, div)[class*="px-"].bg-amber-50 { border: 1px solid rgb(180 83 9 / .2); color: #b45309; border-radius: 10px; }
    body > main :is(p, div)[class*="px-"]:is(.bg-red-50, .bg-rose-50) { border: 1px solid rgb(185 28 28 / .2); color: #b91c1c; border-radius: 10px; }
    body > main :is(p, div)[class*="px-"]:is(.bg-sky-50, .bg-blue-50) { border: 1px solid rgb(29 78 216 / .2); color: #1d4ed8; border-radius: 10px; }

    /* Generic text links in page content pick up the accent */
    body > main a.underline, body > main a.hover\:underline:not([class*="text-"]) { color: #065f46; }

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

    /* List filter bar (see the script below) */
    .adm-fbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 12px 0; }
    .adm-fbar.in-card { margin: 0; padding: 10px 16px; border-bottom: 1px solid #eef0f3; }
    .adm-fbar .adm-fsearch { position: relative; flex: 1 1 220px; max-width: 320px; }
    .adm-fbar .adm-fsearch svg { position: absolute; left: 9px; top: 50%; width: 14px; height: 14px; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
    .adm-fbar input.adm-fq { width: 100%; height: 32px; padding: 0 10px 0 30px !important; font-size: 13px !important; border: 1px solid #e4e7ec; border-radius: 7px; background: #fff; }
    .adm-fbar select { height: 32px; padding: 0 28px 0 10px; font-size: 13px !important; border: 1px solid #e4e7ec; border-radius: 7px; background-color: #fff; color: #0f172a; max-width: 200px; }
    .adm-fbar select.on { border-color: #059669; background-color: #ecfdf5; color: #065f46; font-weight: 500; }
    .adm-fbar .adm-fclear { height: 32px; padding: 0 10px; font-size: 12.5px; font-weight: 500; color: #475569; border-radius: 7px; }
    .adm-fbar .adm-fclear:hover { background: #f1f5f9; }
    .adm-fbar .adm-fcount { margin-left: auto; font-size: 12px; color: #64748b; white-space: nowrap; }
    .adm-fnone td, li.adm-fnone, div.adm-fnone { padding: 28px 16px !important; text-align: center; color: #64748b; }
    .adm-f-hide { display: none !important; }
</style>
<script>
/*
 * Admin list filter bar — search + dropdown filters for any listing.
 *   <table data-filter="Status,Plan">          dropdown per named column (matched on header text)
 *   <div data-filter="status:Status">          card list: rows are [data-filter-row] (or direct children),
 *     <div data-filter-row data-f-status="Active">  each facet value read from data-f-<key>
 *   data-filter=""                             search only
 * A cell can override its filter value with data-f="…" (default: first line of its text).
 * Filters only what is already on the page — for paginated lists use the server search.
 */
(function () {
    const norm = (s) => (s || '').replace(/\s+/g, ' ').trim();
    const firstLine = (el) => norm(((el.dataset && el.dataset.f) ?? (el.innerText || el.textContent) ?? '').split('\n').map(norm).find(Boolean) || '');
    // Row text for search: visible text plus what editable rows hold in their fields.
    const textOf = (r) => norm((r.innerText || r.textContent) + ' ' + [...r.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea, select')]
        .map((f) => (f.tagName === 'SELECT' ? (f.selectedOptions[0] || {}).text || '' : f.value)).join(' ')).toLowerCase();
    const ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>';

    function rowsOf(box) {
        if (box.tagName === 'TABLE') {
            return [...box.tBodies].flatMap((tb) => [...tb.rows]);
        }
        const marked = box.querySelectorAll('[data-filter-row]');
        return marked.length ? [...marked] : [...box.children].filter((c) => c.tagName !== 'TEMPLATE' && !c.classList.contains('adm-fnone'));
    }
    // A single colspan cell row (edit form, details, empty state) belongs to the row above it.
    const isAttached = (r) => r.tagName === 'TR' && r.cells.length === 1 && r.cells[0].colSpan > 1;

    function init(box, idx) {
        if (box.dataset.filterReady) {
            return;
        }
        const isTable = box.tagName === 'TABLE';
        const main = () => rowsOf(box).filter((r) => !isAttached(r));
        if (main().length < 2) {
            return; // nothing worth filtering (yet)
        }
        box.dataset.filterReady = '1';

        const heads = isTable && box.tHead ? [...box.tHead.rows[box.tHead.rows.length - 1].cells].map((th) => norm(th.textContent).toLowerCase()) : [];
        const facets = (box.dataset.filter || '').split(',').map(norm).filter(Boolean).map((spec) => {
            const [a, b] = spec.includes(':') ? spec.split(':') : [null, spec];
            const label = norm(b);
            const col = a ? -1 : heads.indexOf(label.toLowerCase());
            const val = a
                ? (r) => norm(r.getAttribute('data-f-' + norm(a)))
                : (r) => (col >= 0 && r.cells[col] ? firstLine(r.cells[col]) : '');
            return { label, val, ok: !!a || col >= 0 };
        }).filter((f) => f.ok);

        const bar = document.createElement('div');
        bar.className = 'adm-fbar';
        bar.innerHTML = '<label class="adm-fsearch">' + ICON + '<input type="search" class="adm-fq" placeholder="Search this list…" aria-label="Search this list"></label>';
        const q = bar.querySelector('input');
        const selects = facets.map((f) => {
            const values = [...new Set(main().map(f.val).filter(Boolean))].sort((x, y) => x.localeCompare(y, 'en', { numeric: true }));
            const s = document.createElement('select');
            s.setAttribute('aria-label', 'Filter by ' + f.label);
            s.innerHTML = '<option value="">All · ' + f.label + '</option>' + values.map((v) => '<option></option>').join('');
            values.forEach((v, i) => { s.options[i + 1].value = v; s.options[i + 1].textContent = v; });
            bar.appendChild(s);
            return s;
        });
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'adm-fclear';
        clear.textContent = 'Clear';
        clear.hidden = true;
        bar.appendChild(clear);
        const count = document.createElement('span');
        count.className = 'adm-fcount';
        bar.appendChild(count);

        // Place the bar above the table's own scroll wrapper; inside a card it becomes the card's toolbar strip.
        let anchor = box;
        if (isTable && box.parentElement && /overflow-x-auto|overflow-auto/.test(box.parentElement.className) && box.parentElement.children.length === 1) {
            anchor = box.parentElement;
        }
        const card = anchor.parentElement && anchor.parentElement.closest('.ui-card, .bg-white');
        const anchorIsCard = anchor.matches('.ui-card, .bg-white');
        if (card && !anchorIsCard && card.closest('main')) {
            bar.classList.add('in-card');
        }
        anchor.parentElement.insertBefore(bar, anchor);

        // "No matches" row/item
        let none;
        if (isTable) {
            none = document.createElement('tr');
            none.className = 'adm-fnone adm-f-hide';
            none.innerHTML = '<td colspan="99">No rows match these filters.</td>';
            (box.tBodies[box.tBodies.length - 1] || box).appendChild(none);
        } else {
            none = document.createElement('div');
            none.className = 'adm-fnone adm-f-hide';
            none.textContent = 'Nothing matches these filters.';
            box.appendChild(none);
        }

        const key = 'admf:' + location.pathname + ':' + idx;
        function apply(save) {
            const term = norm(q.value).toLowerCase();
            const picks = selects.map((s) => s.value);
            let shown = 0, total = 0, lastVisible = true;
            rowsOf(box).forEach((r) => {
                if (r === none) {
                    return;
                }
                if (isAttached(r)) {
                    r.classList.toggle('adm-f-hide', !lastVisible);
                    return;
                }
                total++;
                const ok = (!term || textOf(r).includes(term))
                    && facets.every((f, i) => !picks[i] || f.val(r) === picks[i]);
                r.classList.toggle('adm-f-hide', !ok);
                lastVisible = ok;
                if (ok) {
                    shown++;
                }
            });
            none.classList.toggle('adm-f-hide', shown > 0);
            const active = !!term || picks.some(Boolean);
            selects.forEach((s) => s.classList.toggle('on', !!s.value));
            clear.hidden = !active;
            count.textContent = active ? shown + ' of ' + total : total + ' total';
            if (save) {
                try { sessionStorage.setItem(key, JSON.stringify({ q: q.value, p: picks })); } catch (e) {}
            }
        }
        q.addEventListener('input', () => apply(true));
        selects.forEach((s) => s.addEventListener('change', () => apply(true)));
        clear.addEventListener('click', () => { q.value = ''; selects.forEach((s) => { s.value = ''; }); apply(true); q.focus(); });

        // Keep the filters when coming back from a detail page (same tab only).
        try {
            const saved = JSON.parse(sessionStorage.getItem(key) || 'null');
            if (saved) {
                q.value = saved.q || '';
                selects.forEach((s, i) => { if ([...s.options].some((o) => o.value === (saved.p || [])[i])) { s.value = saved.p[i]; } });
            }
        } catch (e) {}
        apply(false);
    }

    function boot() {
        document.querySelectorAll('main [data-filter]').forEach(init);
    }
    document.addEventListener('DOMContentLoaded', boot);
    // Lists rendered by Alpine (x-for) only exist after it starts.
    document.addEventListener('alpine:initialized', () => setTimeout(boot, 0));
})();
</script>
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
