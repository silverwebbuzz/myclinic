<?php
/**
 * /admin/store/products — review queue + catalog.
 *
 * @var list<array<string,mixed>> $rows
 * @var array<string,int> $counts
 * @var string $status
 * @var string $q
 * @var int $vendorId
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tabs = ['pending_review' => 'Awaiting review', 'live' => 'Live', 'rejected' => 'Sent back', 'disabled' => 'Disabled',
    'draft' => 'Drafts', 'archived' => 'Archived', '' => 'All'];
$badge = ['draft' => 'bg-slate-100 text-slate-700', 'pending_review' => 'bg-amber-100 text-amber-800',
    'live' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-red-100 text-red-700',
    'disabled' => 'bg-red-100 text-red-700', 'archived' => 'bg-slate-200 text-slate-500'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store products — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Store products</h1>
        <form method="get" class="flex gap-2">
            <input type="hidden" name="status" value="<?= $e($status) ?>">
            <?php if ($vendorId > 0): ?><input type="hidden" name="vendor" value="<?= $vendorId ?>"><?php endif; ?>
            <input name="q" value="<?= $e($q) ?>" placeholder="Name, slug or SKU" class="rounded border px-3 py-1.5 text-sm">
            <button class="rounded bg-slate-800 px-3 py-1.5 text-sm text-white">Search</button>
        </form>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if (!empty($tableMissing)): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Catalog tables not found. Import <code>2026_09_28_store_catalog.sql</code>.</div>
    <?php endif; ?>
    <?php if ($vendorId > 0): ?>
        <p class="text-sm text-slate-600">Filtered to one seller. <a href="?status=<?= $e($status) ?>" class="text-sky-700 hover:underline">Clear</a></p>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 text-sm">
        <?php foreach ($tabs as $key => $label): ?>
            <?php $n = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
            <a href="?status=<?= $e($key) ?><?= $vendorId > 0 ? '&vendor=' . $vendorId : '' ?>" class="rounded-full px-3 py-1 <?= $status === $key ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' ?>">
                <?= $e($label) ?> <span class="opacity-70">(<?= (int) $n ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-2">Product</th><th class="px-4 py-2">Seller</th><th class="px-4 py-2">Category</th><th class="px-4 py-2 text-right">From</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Updated</th></tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!$rows): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nothing here.</td></tr><?php endif; ?>
            <?php foreach ($rows as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2">
                        <a href="/admin/store/products/<?= (int) $p['id'] ?>" class="flex items-center gap-3">
                            <span class="h-10 w-10 shrink-0 overflow-hidden rounded bg-slate-100"><?php if (!empty($p['cover'])): ?><img src="<?= $e($p['cover']) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?></span>
                            <span class="font-medium text-sky-700 hover:underline"><?= $e($p['name']) ?><?= !empty($p['is_featured']) ? ' <span class="text-amber-500">★</span>' : '' ?></span>
                        </a>
                    </td>
                    <td class="px-4 py-2"><a href="?status=<?= $e($status) ?>&vendor=<?= (int) $p['vendor_id'] ?>" class="text-slate-600 hover:underline"><?= $e($p['vendor_name']) ?></a></td>
                    <td class="px-4 py-2 text-slate-600"><?= $e($p['category_name'] ?? '') ?><?= ($p['listing_mode'] ?? '') === 'review' ? ' <span class="rounded bg-amber-100 px-1 text-[10px] text-amber-800">extra review</span>' : '' ?></td>
                    <td class="px-4 py-2 text-right">₹<?= $e(ProductService::rupees((int) $p['min_price_paise'])) ?></td>
                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[$p['status']] ?? '' ?>"><?= $e(str_replace('_', ' ', (string) $p['status'])) ?></span></td>
                    <td class="px-4 py-2 text-slate-500"><?= $e(substr((string) $p['updated_at'], 0, 10)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
