<?php
/** @var list<array<string,mixed>> $rows */
use App\Services\Store\ProductService;

$pageTitle = 'Orders';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$label = ['new' => ['New: accept & pack', 'bg-wnb text-wn'], 'accepted' => ['Accepted', 'bg-inb text-in'],
    'packed' => ['Packed', 'bg-inb text-in'], 'shipped' => ['Shipped', 'bg-okb text-ok'],
    'delivered' => ['Delivered', 'bg-okb text-ok'], 'completed' => ['Completed', 'bg-okb text-ok']];
ob_start();
?>
<h1 class="text-[22px] font-semibold tracking-[-.015em]">Orders</h1>
<p class="mt-1 text-sm text-tx3">Accept every new order before its deadline. Unaccepted orders are cancelled and refunded automatically.</p>
<?php if ((int) ($counts['new_n'] ?? 0) > 0): ?>
    <div class="mt-3 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900"><strong><?= (int) $counts['new_n'] ?></strong> new order(s) waiting for you to accept.</div>
<?php endif; ?>
<nav class="mt-4 flex flex-wrap gap-2 text-sm">
    <?php foreach (['todo' => 'To do (' . ((int) ($counts['new_n'] ?? 0) + (int) ($counts['wip_n'] ?? 0)) . ')', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $l): ?>
        <a href="?tab=<?= $k ?>" class="rounded-md px-3 py-1 <?= $tab === $k ? 'bg-acs text-act font-semibold' : 'border border-ln bg-sf text-tx2 hover:bg-sf2' ?>"><?= $e($l) ?></a>
    <?php endforeach; ?>
</nav>
<div class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <?php if (!$rows): ?>
        <p class="p-10 text-center text-sm text-tx3">Nothing here. New orders appear as soon as a customer pays.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($rows as $r): ?>
                <?php [$bl, $bc] = $label[$r['status']] ?? [ucfirst(str_replace('_', ' ', (string) $r['status'])), 'bg-ntb text-nt']; ?>
                <?php $ship = json_decode((string) $r['ship_address_json'], true) ?: []; ?>
                <li>
                    <a href="/vendor/orders/<?= (int) $r['id'] ?>" class="flex flex-wrap items-center gap-4 px-4 py-3 hover:bg-sf2">
                        <span class="min-w-0 flex-1">
                            <span class="block font-mono font-medium"><?= $e($r['sub_order_no']) ?></span>
                            <span class="block text-xs text-tx3">Paid <?= $e(\App\Support\IndianDate::dateTime($r['paid_at'])) ?> · <?= (int) $r['item_count'] ?> item(s) · to <?= $e(($ship['city'] ?? '') . ' ' . ($ship['pincode'] ?? '')) ?></span>
                            <?php if ($r['status'] === 'new' && !empty($r['accept_by'])): ?>
                                <span class="block text-xs text-amber-700">Accept by <?= $e(date('d M, h:i A', (int) strtotime((string) $r['accept_by']))) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="text-sm">You get ₹<?= $e(ProductService::rupees((int) $r['vendor_payable_paise'])) ?></span>
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
