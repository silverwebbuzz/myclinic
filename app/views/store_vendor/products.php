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
    'draft' => ['Draft', 'bg-ntb text-nt'],
    'pending_review' => ['In review', 'bg-wnb text-wn'],
    'live' => ['Live', 'bg-okb text-ok'],
    'rejected' => ['Needs changes', 'bg-erb text-er'],
    'disabled' => ['Disabled', 'bg-erb text-er'],
    'archived' => ['Archived', 'bg-slate-200 text-tx3'],
];
$canAdd = ($vendor['status'] ?? '') === 'approved';
ob_start();
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-[22px] font-semibold tracking-[-.015em]">Products</h1>
    <?php if ($canAdd): ?>
        <a href="/vendor/products/new" class="rounded-[7px] bg-ac px-4 py-1.5 text-[13px] font-medium text-white hover:opacity-90">+ Add product</a>
    <?php endif; ?>
</div>

<?php if (!empty($gstIssues)): ?>
    <div class="mt-4 rounded-[10px] border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <strong><?= count($gstIssues) ?> product(s) need their HSN / GST fixed</strong> so customer invoices are correct. Open each one, pick the HSN from the list and save:
        <ul class="mt-1 list-disc pl-5">
            <?php foreach (array_slice($gstIssues, 0, 10) as $gi): ?>
                <li><a class="underline" href="/vendor/products/<?= (int) $gi['id'] ?>"><?= $e($gi['name']) ?></a>: <?= $e($gi['problem']) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<nav class="mt-4 flex flex-wrap gap-2 text-sm">
    <?php foreach ($tabs as $key => $label): ?>
        <?php $n = $key === '' ? array_sum($counts) : ($counts[$key] ?? 0); ?>
        <a href="?status=<?= $e($key) ?>" class="rounded-md px-3 py-1 <?= $status === $key ? 'bg-acs text-act font-semibold' : 'border border-ln bg-sf text-tx2 hover:bg-sf2' ?>"><?= $e($label) ?> <span class="opacity-70">(<?= (int) $n ?>)</span></a>
    <?php endforeach; ?>
</nav>

<div class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if (!$products): ?>
        <div class="p-10 text-center text-sm text-tx3">
            No products here yet.
            <?php if ($canAdd): ?><a href="/vendor/products/new" class="text-act underline">Add your first product</a>.<?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($products as $p): ?>
                <?php [$bl, $bc] = $badge[$p['status']] ?? [$p['status'], 'bg-sf2']; ?>
                <li>
                    <a href="/vendor/products/<?= (int) $p['id'] ?>" class="flex items-center gap-4 px-4 py-3 hover:bg-sf2">
                        <span class="h-14 w-14 shrink-0 overflow-hidden rounded-[10px] bg-[#f4f0e8]">
                            <?php if (!empty($p['cover'])): ?><img src="<?= $e($p['cover']) ?>" alt="" class="h-full w-full object-cover"><?php endif; ?>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium"><?= $e($p['name']) ?></span>
                            <span class="block text-xs text-tx3"><?= $e($p['category_name'] ?? '') ?> · <?= (int) $p['variant_count'] ?> variant(s) · stock <?= (int) $p['total_stock'] ?></span>
                            <?php if (in_array($p['status'], ['rejected', 'disabled'], true) && !empty($p['review_note'])): ?>
                                <span class="block text-xs text-red-700"><?= $e($p['review_note']) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="hidden text-right text-sm sm:block">₹<?= $e(ProductService::rupees((int) $p['min_price_paise'])) ?><?= (int) $p['variant_count'] > 1 ? '+' : '' ?></span>
                        <span class="inline-flex h-[22px] items-center whitespace-nowrap rounded-md px-2 text-xs font-medium <?= $bc ?>"><?= $e($bl) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
