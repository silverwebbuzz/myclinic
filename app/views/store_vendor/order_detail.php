<?php
/** @var array<string,mixed> $vo */
use App\Services\Store\ProductService;

$pageTitle = 'Order ' . $vo['sub_order_no'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$ship = $vo['ship'];
$status = (string) $vo['status'];
$steps = ['new' => 'New', 'accepted' => 'Accepted', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
$stepKeys = array_keys($steps);
$cur = array_search($status, $stepKeys, true);
$cancelled = in_array($status, ['cancelled_by_customer', 'cancelled_by_vendor', 'cancelled_by_admin', 'auto_cancelled'], true);
$canCancel = in_array($status, ['new', 'accepted', 'packed'], true);
$openItems = array_filter($vo['items'], static fn ($it) => (int) $it['qty'] > (int) $it['qty_cancelled']);
ob_start();
?>
<a href="/vendor/orders" class="text-sm text-[#17774f] hover:underline">← Orders</a>
<div class="mt-2 flex flex-wrap items-center justify-between gap-3">
    <h1 class="font-mono text-2xl font-semibold"><?= $e($vo['sub_order_no']) ?></h1>
    <?php if ($cancelled): ?>
        <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">Cancelled</span>
    <?php endif; ?>
</div>
<p class="mt-1 text-sm text-slate-500">Paid <?= $e(substr((string) $vo['paid_at'], 0, 16)) ?>
    <?= $status === 'new' && $vo['accept_by'] ? ' · <strong class="text-amber-700">accept by ' . $e(date('j M, g:i a', (int) strtotime((string) $vo['accept_by']))) . '</strong>' : '' ?></p>
<?php if ($cancelled && !empty($vo['cancel_reason'])): ?><p class="mt-1 text-sm text-red-700">Reason: <?= $e($vo['cancel_reason']) ?></p><?php endif; ?>

<?php if (!$cancelled): ?>
<!-- Progress -->
<ol class="mt-5 flex flex-wrap gap-2 text-xs">
    <?php foreach ($steps as $k => $label): ?>
        <?php $i = array_search($k, $stepKeys, true); $done = $cur !== false && $i <= $cur; ?>
        <li class="rounded-full px-3 py-1 <?= $done ? 'bg-[#0e4d34] text-white' : 'bg-white text-slate-500 border border-slate-200' ?>"><?= $e($label) ?></li>
    <?php endforeach; ?>
</ol>

<!-- Next action -->
<section class="mt-4 rounded-2xl border border-[#d5e6da] bg-[#edf5ef] p-5">
    <?php if ($status === 'new'): ?>
        <p class="text-sm"><strong>Can you fulfil this order?</strong> Accept it, then pack the items below. If something is out of stock, cancel just those items below; the customer is refunded automatically.</p>
        <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/accept" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="rounded-full bg-[#0e4d34] px-6 py-2 text-sm font-medium text-white hover:bg-[#17774f]">Accept order</button>
        </form>
    <?php elseif ($status === 'accepted'): ?>
        <p class="text-sm"><strong>Pack the items below</strong> securely, then mark the package as packed. You can then print the GST invoice to put inside the box.</p>
        <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/packed" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="rounded-full bg-[#0e4d34] px-6 py-2 text-sm font-medium text-white hover:bg-[#17774f]">Mark as packed</button>
        </form>
    <?php elseif (in_array($status, ['packed', 'ready_to_ship'], true)): ?>
        <?php if (!empty($shipment['awb_code'])): ?>
            <p class="text-sm"><strong>Courier booked.</strong> <?= !empty($shipment['courier_name']) ? $e($shipment['courier_name']) . ' · ' : '' ?>AWB <span class="font-mono"><?= $e($shipment['awb_code']) ?></span>
                <?= !empty($shipment['pickup_scheduled_for']) ? ' · pickup on ' . $e(date('D j M', (int) strtotime((string) $shipment['pickup_scheduled_for']))) : '' ?></p>
            <p class="mt-1 text-sm">Print the label, stick it on the box, and hand it to the courier at pickup.</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <?php if (!empty($shipment['label_url'])): ?>
                    <a href="<?= $e($shipment['label_url']) ?>" target="_blank" rel="noopener" class="rounded-full bg-[#0e4d34] px-5 py-2 text-sm font-medium text-white hover:bg-[#17774f]">Download label</a>
                <?php endif; ?>
                <?php if (empty($shipment['label_url']) || empty($shipment['pickup_scheduled_for'])): ?>
                    <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/book"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="rounded-full border border-[#0e4d34] px-5 py-2 text-sm font-medium text-[#0e4d34] hover:bg-white">Finish booking (label / pickup)</button></form>
                <?php endif; ?>
            </div>
        <?php elseif ($shipment !== null): ?>
            <p class="text-sm"><strong>Courier booking didn't finish.</strong> <?= $e(mb_substr((string) ($shipment['last_error'] ?? ''), 0, 180)) ?></p>
            <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/book" class="mt-3"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <button class="rounded-full bg-[#0e4d34] px-5 py-2 text-sm font-medium text-white hover:bg-[#17774f]">Retry booking</button></form>
        <?php elseif (!$courierOn): ?>
            <p class="text-sm"><strong>Packed. Waiting for courier pickup.</strong> Booking the courier from this page will be switched on shortly.</p>
        <?php else: ?>
            <p class="text-sm"><strong>Book courier pickup.</strong> Weigh the packed box and measure it. Couriers charge by the larger of actual and volumetric weight.</p>
            <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/book" class="mt-3 grid gap-3 text-sm sm:grid-cols-5 sm:items-end">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <label class="block"><span class="text-slate-600">Weight (g)</span>
                    <input name="weight_g" type="number" min="10" max="50000" required value="<?= (int) $suggest['weight_g'] ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="block"><span class="text-slate-600">Length (cm)</span>
                    <input name="length_cm" type="number" step="0.5" min="1" max="300" required value="<?= $e($suggest['length_cm']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="block"><span class="text-slate-600">Breadth (cm)</span>
                    <input name="breadth_cm" type="number" step="0.5" min="1" max="300" required value="<?= $e($suggest['breadth_cm']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="block"><span class="text-slate-600">Height (cm)</span>
                    <input name="height_cm" type="number" step="0.5" min="1" max="300" required value="<?= $e($suggest['height_cm']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <button class="rounded-full bg-[#0e4d34] px-5 py-2 text-sm font-medium text-white hover:bg-[#17774f]">Book pickup</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-sm">Status: <strong><?= $e(ucfirst(str_replace('_', ' ', $status))) ?></strong></p>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php $myDocs = array_values(array_filter($taxDocs ?? [], static fn ($d) => $d['issuer'] === 'vendor')); ?>
<?php if ($myDocs || in_array($status, ['packed', 'ready_to_ship', 'shipped', 'delivered', 'completed'], true)): ?>
<section class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-[#ece8df] bg-white p-4 text-sm">
    <strong>GST documents</strong>
    <?php foreach ($myDocs as $d): ?>
        <a href="/vendor/gst/documents/<?= (int) $d['id'] ?>" target="_blank" class="rounded-full border px-3 py-1 font-mono text-xs <?= $d['doc_type'] === 'credit_note' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-[#0e4d34] text-[#0e4d34]' ?>">
            <?= $d['doc_type'] === 'credit_note' ? 'Credit note' : 'Invoice' ?> <?= $e($d['doc_no']) ?></a>
    <?php endforeach; ?>
    <?php if (!$myDocs): ?>
        <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/invoice" target="_blank">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="rounded-full bg-[#0e4d34] px-4 py-1.5 text-xs font-medium text-white hover:bg-[#17774f]">Print GST invoice</button>
        </form>
        <span class="text-xs text-slate-500">Issued in your name. It is also created automatically when the courier is booked.</span>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="mt-5 rounded-2xl border border-[#ece8df] bg-white p-6">
    <h2 class="font-semibold">Items</h2>
    <table class="mt-3 w-full text-sm">
        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">SKU</th><th>Item</th><th class="text-right">Qty to ship</th><th class="text-right">Sold at</th></tr></thead>
        <tbody class="divide-y">
        <?php foreach ($vo['items'] as $it): ?>
            <?php $left = (int) $it['qty'] - (int) $it['qty_cancelled']; ?>
            <tr class="<?= $left === 0 ? 'opacity-50' : '' ?>">
                <td class="py-2 font-mono text-xs"><?= $e($it['sku']) ?></td>
                <td><?= $e($it['name']) ?><?= $it['variant_title'] ? ' · ' . $e($it['variant_title']) : '' ?>
                    <?= (int) $it['qty_cancelled'] > 0 ? '<span class="block text-xs text-red-600">' . (int) $it['qty_cancelled'] . ' cancelled &amp; refunded</span>' : '' ?></td>
                <td class="text-right font-semibold"><?= $left ?></td>
                <td class="text-right"><?= $r((int) $it['unit_price_paise'] * $left) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($canCancel && $openItems): ?>
<details class="mt-5 rounded-2xl border border-[#ece8df] bg-white p-6">
    <summary class="cursor-pointer text-sm font-semibold text-red-700">Can't ship some items? Cancel them</summary>
    <form method="post" action="/vendor/orders/<?= (int) $vo['id'] ?>/cancel" class="mt-4 space-y-3 text-sm"
          onsubmit="return confirm('Cancel the selected quantities? The customer is refunded immediately and this cannot be undone.')">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <?php foreach ($openItems as $it): ?>
            <?php $left = (int) $it['qty'] - (int) $it['qty_cancelled']; ?>
            <label class="flex items-center justify-between gap-3">
                <span><?= $e($it['name']) ?><?= $it['variant_title'] ? ' · ' . $e($it['variant_title']) : '' ?></span>
                <select name="cancel[<?= (int) $it['id'] ?>]" class="rounded-lg border border-slate-300 px-2 py-1">
                    <?php for ($q = 0; $q <= $left; $q++): ?><option value="<?= $q ?>"><?= $q === 0 ? 'Keep all' : 'Cancel ' . $q ?></option><?php endfor; ?>
                </select>
            </label>
        <?php endforeach; ?>
        <label class="block"><span class="text-slate-600">Reason</span>
            <select name="reason" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                <option value="Out of stock">Out of stock</option>
                <option value="Damaged / expired stock">Damaged / expired stock</option>
                <option value="Cannot deliver to this pincode">Cannot deliver to this pincode</option>
                <option value="Other">Other</option>
            </select></label>
        <label class="block"><span class="text-slate-600">Note <span class="text-slate-400">(optional)</span></span>
            <input name="note" maxlength="200" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="restock" value="1"> Put these units back in my stock count (only if you actually have them)</label>
        <p class="text-xs text-slate-500">Cancellations are visible to the eClinicPro team. Keep your stock numbers up to date to avoid them.</p>
        <button class="rounded-full bg-red-600 px-5 py-2 text-sm font-medium text-white hover:bg-red-700">Cancel selected &amp; refund customer</button>
    </form>
</details>
<?php endif; ?>

<div class="mt-5 grid gap-5 md:grid-cols-2">
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6 text-sm">
        <h2 class="font-semibold">Delivering to</h2>
        <p class="mt-2"><?= $e($ship['name'] ?? $vo['contact_name']) ?><br><?= $e($ship['city'] ?? '') ?>, <?= $e($ship['state'] ?? '') ?> – <?= $e($ship['pincode'] ?? '') ?></p>
        <p class="mt-2 text-xs text-slate-500">The full address is printed on the shipping label; customers' contact details stay private.</p>
    </section>
    <section class="rounded-2xl border border-[#ece8df] bg-white p-6 text-sm">
        <h2 class="font-semibold">Your earnings on this order</h2>
        <dl class="mt-2 grid grid-cols-2 gap-y-1">
            <dt class="text-slate-500">Commission (current)</dt><dd class="text-right">−<?= $r($vo['commission_paise']) ?></dd>
            <dt class="font-semibold">You get</dt><dd class="text-right font-semibold"><?= $r(max(0, (int) $vo['vendor_payable_paise'])) ?></dd>
        </dl>
        <p class="mt-2 text-xs text-slate-500">Adjusted for cancelled items. Paid out after delivery and the return window.</p>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
