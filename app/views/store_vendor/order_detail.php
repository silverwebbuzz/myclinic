<?php
/** @var array<string,mixed> $vo */
use App\Services\Store\ProductService;

$pageTitle = 'Order ' . $vo['sub_order_no'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$ship = $vo['ship'];
ob_start();
?>
<a href="/vendor/orders" class="text-sm text-[#17774f] hover:underline">← Orders</a>
<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-mono text-2xl font-semibold"><?= $e($vo['sub_order_no']) ?></h1>
    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold"><?= $e(ucfirst(str_replace('_', ' ', (string) $vo['status']))) ?></span>
</div>
<p class="mt-1 text-sm text-slate-500">Paid <?= $e(substr((string) $vo['paid_at'], 0, 16)) ?><?= $vo['status'] === 'new' && $vo['accept_by'] ? ' · accept by ' . $e(date('j M, g:i a', (int) strtotime((string) $vo['accept_by']))) : '' ?></p>

<section class="mt-5 rounded-2xl border border-[#ece8df] bg-white p-6">
    <h2 class="font-semibold">Items to pack</h2>
    <table class="mt-3 w-full text-sm">
        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">SKU</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Sold at</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($vo['items'] as $it): ?>
            <tr>
                <td class="py-2 font-mono text-xs"><?= $e($it['sku']) ?></td>
                <td><?= $e($it['name']) ?><?= $it['variant_title'] ? ' · ' . $e($it['variant_title']) : '' ?></td>
                <td class="text-right font-semibold"><?= (int) $it['qty'] ?></td>
                <td class="text-right"><?= $r($it['line_total_paise']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<div class="mt-5 grid gap-5 md:grid-cols-2">
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6 text-sm">
        <h2 class="font-semibold">Delivering to</h2>
        <p class="mt-2"><?= $e($ship['name'] ?? $vo['contact_name']) ?><br><?= $e($ship['city'] ?? '') ?>, <?= $e($ship['state'] ?? '') ?> – <?= $e($ship['pincode'] ?? '') ?></p>
        <p class="mt-2 text-xs text-slate-500">The full address is printed on the shipping label; customers' contact details stay private.</p>
    </section>
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6 text-sm">
        <h2 class="font-semibold">Your earnings on this order</h2>
        <dl class="mt-2 grid grid-cols-2 gap-y-1">
            <dt class="text-slate-500">Items sold</dt><dd class="text-right"><?= $r($vo['items_subtotal_paise']) ?></dd>
            <dt class="text-slate-500">Commission</dt><dd class="text-right">−<?= $r($vo['commission_paise']) ?></dd>
            <dt class="text-slate-500">GST on commission</dt><dd class="text-right">−<?= $r($vo['commission_gst_paise']) ?></dd>
            <dt class="font-semibold">You get</dt><dd class="text-right font-semibold"><?= $r($vo['vendor_payable_paise']) ?></dd>
        </dl>
        <p class="mt-2 text-xs text-slate-500">Paid out after the package is delivered and the return window closes.</p>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
