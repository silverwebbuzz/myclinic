<?php
/** @var list<array<string,mixed>> $rows */
use App\Services\Store\ProductService;

$pageTitle = 'Orders';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$label = ['new' => ['New: accept & pack', 'bg-amber-100 text-amber-800'], 'accepted' => ['Accepted', 'bg-sky-100 text-sky-800'],
    'packed' => ['Packed', 'bg-sky-100 text-sky-800'], 'shipped' => ['Shipped', 'bg-emerald-100 text-emerald-800'],
    'delivered' => ['Delivered', 'bg-emerald-100 text-emerald-800'], 'completed' => ['Completed', 'bg-emerald-100 text-emerald-800']];
ob_start();
?>
<h1 class="text-2xl font-semibold">Orders</h1>
<p class="mt-1 text-sm text-slate-500">Accept every new order before its deadline. Unaccepted orders are cancelled and refunded automatically.</p>
<?php if ((int) ($counts['new_n'] ?? 0) > 0): ?>
    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900"><strong><?= (int) $counts['new_n'] ?></strong> new order(s) waiting for you to accept.</div>
<?php endif; ?>
<nav class="mt-4 flex flex-wrap gap-2 text-sm">
    <?php foreach (['todo' => 'To do (' . ((int) ($counts['new_n'] ?? 0) + (int) ($counts['wip_n'] ?? 0)) . ')', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l): ?>
        <a href="?tab=<?= $k ?>" class="rounded-full px-3 py-1 <?= $tab === $k ? 'bg-[#0e4d34] text-white' : 'bg-white hover:bg-[#edf5ef]' ?>"><?= $e($l) ?></a>
    <?php endforeach; ?>
</nav>
<div class="mt-4 overflow-hidden rounded-2xl border border-[#ece8df] bg-white">
    <?php if (!$rows): ?>
        <p class="p-10 text-center text-sm text-slate-500">Nothing here. New orders appear as soon as a customer pays.</p>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($rows as $r): ?>
                <?php [$bl, $bc] = $label[$r['status']] ?? [ucfirst(str_replace('_', ' ', (string) $r['status'])), 'bg-slate-100 text-slate-700']; ?>
                <?php $ship = json_decode((string) $r['ship_address_json'], true) ?: []; ?>
                <li>
                    <a href="/vendor/orders/<?= (int) $r['id'] ?>" class="flex flex-wrap items-center gap-4 px-4 py-3 hover:bg-[#faf7f1]">
                        <span class="min-w-0 flex-1">
                            <span class="block font-mono font-medium"><?= $e($r['sub_order_no']) ?></span>
                            <span class="block text-xs text-slate-500">Paid <?= $e(substr((string) $r['paid_at'], 0, 16)) ?> · <?= (int) $r['item_count'] ?> item(s) · to <?= $e(($ship['city'] ?? '') . ' ' . ($ship['pincode'] ?? '')) ?></span>
                            <?php if ($r['status'] === 'new' && !empty($r['accept_by'])): ?>
                                <span class="block text-xs text-amber-700">Accept by <?= $e(date('j M, g:i a', (int) strtotime((string) $r['accept_by']))) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="text-sm">You get ₹<?= $e(ProductService::rupees((int) $r['vendor_payable_paise'])) ?></span>
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
