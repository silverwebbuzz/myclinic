<?php
/**
 * Seller portal shell (PayGate console design: dark 244px sidebar, 56px top bar,
 * slate neutrals, emerald accent). Views set $pageTitle and capture $content, then require this.
 *
 * @var array<string,mixed> $vendor
 * @var string $csrf
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$status = (string) ($vendor['status'] ?? 'draft');
$selling = in_array($status, ['approved', 'suspended'], true);
$statusPill = [
    'draft' => ['Setup incomplete', 'bg-ntb text-nt'],
    'pending_review' => ['Under review', 'bg-wnb text-wn'],
    'approved' => ['Approved', 'bg-okb text-ok'],
    'rejected' => ['Changes needed', 'bg-erb text-er'],
    'suspended' => ['Suspended', 'bg-erb text-er'],
][$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'bg-ntb text-nt'];

$toAccept = 0;
if ($selling && !empty($vendor['id'])) {
    try {
        $st = \App\Core\Database::connection()->prepare(
            "SELECT COUNT(*) FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.vendor_id = :v AND vo.status = 'new' AND o.payment_status IN ('paid','partially_refunded')"
        );
        $st->execute(['v' => (int) $vendor['id']]);
        $toAccept = (int) $st->fetchColumn();
    } catch (\Throwable) {
    }
}
$termsPending = !empty($vendor['id']) && \App\Services\Store\StorePolicyService::needsAcceptance((int) $vendor['id']);

// [href, label, locked-until-approved, badge]
$navGroups = [
    'Overview' => [['/vendor/dashboard', 'Dashboard', false, 0]],
    'Sell' => [
        ['/vendor/products', 'Products', true, 0],
        ['/vendor/orders', 'Orders', true, $toAccept],
        ['/vendor/returns', 'Returns', true, 0],
        ['/vendor/reviews', 'Reviews', true, 0],
    ],
    'Money' => [
        ['/vendor/payouts', 'Payouts', true, 0],
        ['/vendor/gst', 'GST invoices', true, 0],
    ],
    'Account' => [
        ['/vendor/profile', 'Business profile', false, 0],
        ['/vendor/addresses', 'Addresses', false, 0],
        ['/vendor/bank', 'Bank account', false, 0],
        ['/vendor/documents', 'Documents', false, 0],
        ['/vendor/terms', 'Rules & terms', false, $termsPending ? 1 : 0],
    ],
];
// Current item: exact match, else the longest parent path (/vendor/orders/12 → Orders).
$crumbs = [];
$activeHref = null;
foreach ($navGroups as $group => $items) {
    foreach ($items as [$href, $label]) {
        if (($path === $href || str_starts_with($path, $href . '/')) && ($activeHref === null || strlen($href) > strlen($activeHref))) {
            $activeHref = $href;
            $crumbs = [$group, $label];
        }
    }
}
$userName = (string) ($vendorUser['name'] ?? $vendor['contact_name'] ?? '');
$initials = strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($userName)) ?: [], 0, 2)))) ?: 'S';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($pageTitle ?? 'Seller portal') ?> · eClinicPro Store Sellers</title>
    <?php require __DIR__ . '/_pg_head.php'; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <!-- Date boxes show 06-10-2026 on desktop; phones keep their own date wheel (file lives on the main site). -->
    <script defer src="https://eclinicpro.com/assets/js/ecp-datepicker.js?v=20261007"></script>
</head>
<body class="min-h-screen bg-bg font-sans text-tx" x-data="{ menu: false }">
<div class="flex min-h-screen">
    <!-- Sidebar: sticky on desktop, slide-in panel on phones -->
    <div class="fixed inset-0 z-30 bg-black/40 md:hidden" x-show="menu" x-cloak @click="menu = false"></div>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-[244px] flex-none -translate-x-full flex-col bg-nav text-[#C8D1DF] transition-transform duration-200 md:sticky md:top-0 md:h-screen md:translate-x-0"
           :class="menu ? '!translate-x-0' : ''">
        <div class="flex h-14 flex-none items-center gap-2.5 border-b border-white/5 px-[18px]">
            <div class="grid h-7 w-7 flex-none place-items-center rounded-lg bg-ac text-sm font-bold text-white">e</div>
            <div class="flex min-w-0 flex-col leading-tight">
                <span class="text-sm font-semibold tracking-tight text-white">eClinicPro Store</span>
                <span class="whitespace-nowrap text-[11px] text-[#8391A8]">Seller console</span>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto px-2.5 pb-4 pt-2">
            <?php foreach ($navGroups as $group => $items): ?>
                <div class="mt-3">
                    <div class="px-2.5 pb-1.5 text-[10.5px] font-semibold uppercase tracking-[.06em] text-[#6B778C]"><?= $e($group) ?></div>
                    <?php foreach ($items as [$href, $label, $locked, $badge]): ?>
                        <?php $isLocked = $locked && !$selling; $active = $href === $activeHref; ?>
                        <?php if ($isLocked): ?>
                            <span class="flex h-8 w-full cursor-default items-center gap-2.5 rounded-[7px] px-2.5 text-[13px] font-medium text-[#7B879C]" title="Opens after your account is approved">
                                <span class="h-1.5 w-1.5 flex-none rounded-[2px] bg-white/10"></span>
                                <span class="flex-1 truncate"><?= $e($label) ?></span>
                                <span class="rounded px-1.5 py-px text-[10.5px] font-medium text-[#6B778C] ring-1 ring-white/10">After approval</span>
                            </span>
                        <?php else: ?>
                            <a href="<?= $e($href) ?>" class="flex h-8 w-full items-center gap-2.5 rounded-[7px] px-2.5 text-[13px] <?= $active ? 'bg-white/[.08] font-semibold text-white' : 'font-medium text-[#C8D1DF] hover:bg-white/5' ?>">
                                <span class="h-1.5 w-1.5 flex-none rounded-[2px] <?= $active ? 'bg-ac' : 'bg-white/30' ?>"></span>
                                <span class="flex-1 truncate"><?= $e($label) ?></span>
                                <?php if ($badge > 0): ?>
                                    <span class="grid h-[18px] min-w-[18px] place-items-center rounded-full bg-ac px-1.5 text-[10.5px] font-semibold text-white"><?= $href === '/vendor/terms' ? '!' : (int) $badge ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="flex flex-none items-center gap-2.5 border-t border-white/5 px-3.5 py-3">
            <div class="grid h-[30px] w-[30px] flex-none place-items-center rounded-full bg-[#1E293B] text-[11.5px] font-semibold text-[#E2E8F0]"><?= $e($initials) ?></div>
            <div class="min-w-0 leading-tight">
                <div class="truncate text-[12.5px] font-medium text-white"><?= $e($userName) ?></div>
                <div class="truncate text-[11.5px] text-[#8391A8]"><?= $e($vendor['display_name'] ?? '') ?></div>
            </div>
            <span class="flex-1"></span>
            <form method="post" action="/vendor/logout">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <button class="h-[26px] flex-none rounded-md border border-white/10 px-2 text-[11.5px] text-[#C8D1DF] hover:bg-white/5">Sign out</button>
            </form>
        </div>
    </aside>

    <main class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-14 flex-none items-center gap-2 border-b border-ln bg-sf px-3 md:gap-3.5 md:px-5">
            <button type="button" @click="menu = !menu" title="Menu" class="grid h-8 w-8 flex-none place-items-center rounded-lg border border-ln bg-sf text-tx2 hover:bg-sf2 md:hidden">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
            <div class="flex min-w-0 items-center gap-1.5 whitespace-nowrap text-[13px] max-md:flex-1">
                <?php $all = array_merge(['Seller'], $crumbs ?: [$pageTitle ?? '']); ?>
                <?php foreach ($all as $i => $c): $last = $i === count($all) - 1; ?>
                    <span class="flex min-w-0 items-center gap-1.5 <?= $last ? 'truncate' : 'max-md:hidden' ?>">
                        <?php if ($i > 0): ?><span class="text-ln max-md:hidden">/</span><?php endif; ?>
                        <span class="<?= $last ? 'font-medium text-tx' : 'text-tx3' ?>"><?= $e($c) ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <span class="flex-1 max-md:hidden"></span>
            <?php if ($status === 'approved' && !empty($vendor['slug'])): ?>
                <a href="https://eclinicpro.com/store/seller/<?= $e(rawurlencode((string) $vendor['slug'])) ?>" target="_blank" rel="noopener"
                   class="inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-[7px] border border-ln bg-sf px-3 text-[13px] font-medium text-tx hover:bg-sf2 max-md:hidden">View my store ↗</a>
            <?php endif; ?>
            <span class="inline-flex h-[26px] items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-xs font-medium <?= $statusPill[1] ?>">
                <span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= $e($statusPill[0]) ?>
            </span>
        </header>

        <div class="flex flex-1 flex-col gap-4 p-3 md:p-5">
            <?php if ($status === 'rejected' && !empty($vendor['status_reason'])): ?>
                <div class="rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><strong>Changes needed:</strong> <?= $e($vendor['status_reason']) ?>. Update your details, then submit again from the dashboard.</div>
            <?php elseif ($status === 'suspended'): ?>
                <div class="rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><strong>Your store is suspended.</strong> <?= $e($vendor['status_reason'] ?? '') ?> Your products are hidden from customers. Contact support to resolve this.</div>
            <?php endif; ?>
            <?php if ($termsPending && $path !== '/vendor/terms'): ?>
                <div class="flex flex-wrap items-center gap-2 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-wn">
                    <strong>Please review the seller rules &amp; terms.</strong>
                    <span><?= \App\Services\Store\StorePolicyService::acceptedVersion((int) $vendor['id'], 'seller_terms') > 0 ? 'They have been updated since you last accepted them.' : 'You need to accept them to sell on eClinicPro Store.' ?></span>
                    <a href="/vendor/terms" class="font-semibold underline">Read and accept</a>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashOk)): ?>
                <div class="rounded-[10px] border border-ok/20 bg-okb px-4 py-3 text-ok"><?= $e($flashOk) ?></div>
            <?php endif; ?>
            <?php if (!empty($flashErr)): ?>
                <div class="rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-er"><?= $e($flashErr) ?></div>
            <?php endif; ?>
            <div class="min-w-0"><?= $content ?? '' ?></div>
        </div>
    </main>
</div>
</body>
</html>
