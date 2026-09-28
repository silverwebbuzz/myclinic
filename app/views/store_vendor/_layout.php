<?php
/**
 * Seller portal shell. Views set $pageTitle and capture $content, then require this.
 *
 * @var array<string,mixed> $vendor
 * @var string $csrf
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$status = (string) ($vendor['status'] ?? 'draft');
$statusLabel = [
    'draft' => ['Setup incomplete', 'bg-slate-200 text-slate-700'],
    'pending_review' => ['Under review', 'bg-amber-100 text-amber-800'],
    'approved' => ['Approved', 'bg-emerald-100 text-emerald-800'],
    'rejected' => ['Changes needed', 'bg-red-100 text-red-700'],
    'suspended' => ['Suspended', 'bg-red-100 text-red-700'],
][$status] ?? [ucfirst($status), 'bg-slate-200 text-slate-700'];
$nav = [
    ['/vendor/dashboard', 'Dashboard'],
    ['/vendor/profile', 'Business profile'],
    ['/vendor/addresses', 'Addresses'],
    ['/vendor/bank', 'Bank account'],
    ['/vendor/documents', 'Documents'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($pageTitle ?? 'Seller portal') ?> — eClinicPro Store Sellers</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-screen bg-[#faf7f1] text-[#13294b]" x-data="{ menu: false }">
<header class="bg-[#0e4d34] text-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="/vendor/dashboard" class="font-semibold tracking-tight">eClinicPro <span class="font-normal text-emerald-200">Store · Sellers</span></a>
        <div class="flex items-center gap-3 text-sm">
            <span class="hidden rounded-full px-2.5 py-0.5 text-xs font-semibold sm:inline <?= $statusLabel[1] ?>"><?= $e($statusLabel[0]) ?></span>
            <span class="hidden text-emerald-100 md:inline"><?= $e($vendor['display_name'] ?? '') ?></span>
            <form method="post" action="/vendor/logout">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <button class="rounded-lg px-2 py-1 hover:bg-white/10">Log out</button>
            </form>
            <button type="button" class="rounded-lg p-1 hover:bg-white/10 md:hidden" @click="menu = !menu" aria-label="Menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
        </div>
    </div>
</header>

<div class="mx-auto flex max-w-6xl gap-6 px-4 py-6">
    <nav class="w-52 shrink-0 space-y-1 text-sm md:block" :class="menu ? 'block fixed inset-x-4 top-14 z-20 rounded-xl bg-white p-3 shadow-lg' : 'hidden'">
        <?php foreach ($nav as [$href, $label]): ?>
            <a href="<?= $e($href) ?>" class="block rounded-lg px-3 py-2 <?= $path === $href ? 'bg-[#0e4d34] text-white' : 'hover:bg-white' ?>"><?= $e($label) ?></a>
        <?php endforeach; ?>
        <?php if (in_array($status, ['approved', 'suspended'], true)): ?>
            <a href="/vendor/products" class="block rounded-lg px-3 py-2 <?= str_starts_with($path, '/vendor/products') ? 'bg-[#0e4d34] text-white' : 'hover:bg-white' ?>">Products</a>
        <?php else: ?>
            <span class="block cursor-not-allowed rounded-lg px-3 py-2 text-slate-400" title="Opens after your account is approved">Products <span class="text-xs">(after approval)</span></span>
        <?php endif; ?>
        <?php if (in_array($status, ['approved', 'suspended'], true)): ?>
            <a href="/vendor/orders" class="block rounded-lg px-3 py-2 <?= str_starts_with($path, '/vendor/orders') ? 'bg-[#0e4d34] text-white' : 'hover:bg-white' ?>">Orders</a>
            <a href="/vendor/returns" class="block rounded-lg px-3 py-2 <?= str_starts_with($path, '/vendor/returns') ? 'bg-[#0e4d34] text-white' : 'hover:bg-white' ?>">Returns</a>
            <a href="/vendor/payouts" class="block rounded-lg px-3 py-2 <?= str_starts_with($path, '/vendor/payouts') ? 'bg-[#0e4d34] text-white' : 'hover:bg-white' ?>">Payouts</a>
        <?php endif; ?>
    </nav>

    <main class="min-w-0 flex-1">
        <?php if ($status === 'rejected' && !empty($vendor['status_reason'])): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <strong>Changes needed:</strong> <?= $e($vendor['status_reason']) ?> — update your details, then submit again from the dashboard.
            </div>
        <?php elseif ($status === 'suspended'): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <strong>Your store is suspended.</strong> <?= $e($vendor['status_reason'] ?? '') ?> Your products are hidden from customers. Contact support to resolve this.
            </div>
        <?php endif; ?>
        <?php if (!empty($flashOk)): ?>
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= $e($flashOk) ?></div>
        <?php endif; ?>
        <?php if (!empty($flashErr)): ?>
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= $e($flashErr) ?></div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
</div>
</body>
</html>
