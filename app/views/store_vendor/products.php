<?php
/**
 * @var list<array<string,mixed>> $products
 * @var array<string,int> $counts
 * @var string $status
 */
use App\Services\Store\ProductService;

$pageTitle = 'Products';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tabs = ['' => 'All', 'live' => 'Live', 'pending_review' => 'In review', 'draft' => 'Drafts',
    'rejected' => 'Needs changes', 'disabled' => 'Disabled', 'archived' => 'Archived'];
$badge = [
    'draft' => ['Draft', 'bg-slate-100 text-slate-700'],
    'pending_review' => ['In review', 'bg-amber-100 text-amber-800'],
    'live' => ['Live', 'bg-emerald-100 text-emerald-800'],
    'rejected' => ['Needs changes', 'bg-red-100 text-red-700'],
    'disabled' => ['Disabled', 'bg-red-100 text-red-700'],
    'archived' => ['Archived', 'bg-slate-200 text-slate-500'],
];
$canAdd = ($vendor['status'] ?? '') === 'approved';
ob_start();
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Products</h1>
    <?php if ($canAdd): ?>
        <a href="/vendor/products/new" class="rounded-full bg-[#0e4d34] px-5 py-2 text-sm font-medium text-white hover:bg-[#17774f]">+ Add product</a>
    <?php endif; ?>
</div>

<nav class="mt-4 flex flex-wrap gap-2 text-sm">
    <?php foreach ($tabs as $key => $label): ?>
        <?php $n = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
        <a href="?status=<?= $e($key) ?>" class="rounded-full px-3 py-1 <?= $status === $key ? 'bg-[#0e4d34] text-white' : 'bg-white hover:bg-[#edf5ef]' ?>"><?= $e($label) ?> <span class="opacity-70">(<?= (int) $n ?>)</span></a>
    <?php endforeach; ?>
</nav>

<div class="mt-4 overflow-hidden rounded-2xl border border-[#ece8df] bg-white">
    <?php if (!$products): ?>
        <div class="p-10 text-center text-sm text-slate-500">
            No products here yet.
            <?php if ($canAdd): ?><a href="/vendor/products/new" class="text-[#17774f] underline">Add your first product</a>.<?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($products as $p): ?>
                <?php [$bl, $bc] = $badge[$p['status']] ?? [$p['status'], 'bg-slate-100']; ?>
                <li>
                    <a href="/vendor/products/<?= (int) $p['id'] ?>" class="flex items-center gap-4 px-4 py-3 hover:bg-[#faf7f1]">
                        <span class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-[#f4f0e8]">
                            <?php if (!empty($p['cover'])): ?><img src="<?= $e($p['cover']) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium"><?= $e($p['name']) ?></span>
                            <span class="block text-xs text-slate-500"><?= $e($p['category_name'] ?? '') ?> · <?= (int) $p['variant_count'] ?> variant(s) · stock <?= (int) $p['total_stock'] ?></span>
                            <?php if (in_array($p['status'], ['rejected', 'disabled'], true) && !empty($p['review_note'])): ?>
                                <span class="block text-xs text-red-700"><?= $e($p['review_note']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="hidden text-right text-sm sm:block">₹<?= $e(ProductService::rupees((int) $p['min_price_paise'])) ?><?= (int) $p['variant_count'] > 1 ? '+' : '' ?></span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold <?= $bc ?>"><?= $e($bl) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
