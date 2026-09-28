<?php
/**
 * /admin/store/orders/{id} — parent order, per-seller sub-orders, money split, history.
 *
 * @var array<string,mixed> $order OrderService::load()
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$a = $order['ship_address'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($order['order_no']) ?> — Store order</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <a href="/admin/store/orders" class="text-sm text-sky-700 hover:underline">← Orders</a>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-mono text-xl font-semibold"><?= $e($order['order_no']) ?></h1>
            <p class="text-sm text-slate-500"><?= $e(str_replace('_', ' ', (string) $order['status'])) ?> · payment <?= $e($order['payment_status']) ?> · placed <?= $e(substr((string) $order['placed_at'], 0, 16)) ?>
                <?= $order['status'] === 'pending_payment' && !empty($order['expires_at']) ? ' · expires ' . $e(substr((string) $order['expires_at'], 11, 5)) : '' ?></p>
        </div>
        <?php if ($order['status'] === 'pending_payment'): ?>
            <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/cancel" class="flex gap-2" onsubmit="return confirm('Cancel this unpaid order and release its stock?')">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="note" placeholder="Reason" class="rounded border px-2 py-1.5 text-sm">
                <button class="rounded bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">Cancel unpaid order</button>
            </form>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border bg-white p-5 text-sm shadow-sm">
            <h2 class="font-semibold">Customer &amp; delivery</h2>
            <p class="mt-2"><?= $e($order['contact_name']) ?> · <?= $e($order['contact_phone']) ?><?= !empty($order['contact_email']) ? ' · ' . $e($order['contact_email']) : '' ?></p>
            <p class="mt-2 text-slate-600"><?= $e($a['name'] ?? '') ?>, <?= $e($a['phone'] ?? '') ?><br>
                <?= $e($a['line1'] ?? '') ?><?= !empty($a['line2']) ? ', ' . $e($a['line2']) : '' ?><?= !empty($a['landmark']) ? ' (near ' . $e($a['landmark']) . ')' : '' ?><br>
                <?= $e($a['city'] ?? '') ?>, <?= $e($a['state'] ?? '') ?> – <?= $e($a['pincode'] ?? '') ?></p>
            <p class="mt-2 text-xs text-slate-400">Patient identity #<?= (int) $order['identity_id'] ?></p>
        </section>
        <section class="rounded-xl border bg-white p-5 text-sm shadow-sm lg:col-span-2">
            <h2 class="font-semibold">Money</h2>
            <dl class="mt-2 grid grid-cols-2 gap-y-1 sm:grid-cols-4">
                <dt class="text-slate-400">Items</dt><dd><?= $r($order['items_subtotal_paise']) ?></dd>
                <dt class="text-slate-400">Shipping</dt><dd><?= $r($order['shipping_paise']) ?></dd>
                <dt class="text-slate-400">GST included</dt><dd><?= $r($order['tax_included_paise']) ?></dd>
                <dt class="text-slate-400 font-semibold">Grand total</dt><dd class="font-semibold"><?= $r($order['grand_total_paise']) ?></dd>
            </dl>
            <?php if ($order['client_total_paise'] !== null && (int) $order['client_total_paise'] !== (int) $order['grand_total_paise']): ?>
                <p class="mt-2 text-xs text-amber-700">Browser showed <?= $r($order['client_total_paise']) ?> (diagnostic only; the server total above is what's charged).</p>
            <?php endif; ?>
        </section>
    </div>

    <?php foreach ($order['vendor_orders'] as $vo): ?>
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold"><span class="font-mono"><?= $e($vo['sub_order_no']) ?></span> · <a href="/admin/store/vendors/<?= (int) $vo['vendor_id'] ?>" class="text-sky-700 hover:underline"><?= $e($vo['vendor_name']) ?></a></h2>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs"><?= $e(str_replace('_', ' ', (string) $vo['status'])) ?></span>
            </div>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">Item</th><th class="text-right">Qty</th><th class="text-right">Line total</th><th class="text-right">GST incl.</th><th class="text-right">Commission</th><th class="text-right">Seller gets</th></tr></thead>
                <tbody class="divide-y">
                <?php foreach ($vo['items'] as $it): ?>
                    <tr>
                        <td class="py-1.5"><?= $e($it['name']) ?><?= !empty($it['variant_title']) ? ' · ' . $e($it['variant_title']) : '' ?> <span class="font-mono text-xs text-slate-400"><?= $e($it['sku']) ?></span></td>
                        <td class="text-right"><?= (int) $it['qty'] ?></td>
                        <td class="text-right"><?= $r($it['line_total_paise']) ?></td>
                        <td class="text-right text-slate-500"><?= $r($it['tax_included_paise']) ?> (<?= (int) $it['gst_bp'] / 100 ?>%)</td>
                        <td class="text-right text-slate-500"><?= $r($it['commission_paise']) ?> <span class="text-xs">(<?= $it['commission_type'] === 'percent' ? ((int) $it['commission_rate_bp'] / 100) . '%' : 'fixed' ?>)</span></td>
                        <td class="text-right"><?= $r($it['vendor_payable_paise']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="mt-3 text-xs text-slate-500">
                Sub-total <?= $r($vo['items_subtotal_paise']) ?> · shipping charged <?= $r($vo['shipping_paise']) ?> ·
                commission <?= $r($vo['commission_paise']) ?> + GST on commission <?= $r($vo['commission_gst_paise']) ?> ·
                <strong>seller payable <?= $r($vo['vendor_payable_paise']) ?></strong> (released only after delivery + return window)
            </p>
        </section>
    <?php endforeach; ?>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">History</h2>
        <ul class="mt-2 space-y-1 text-xs text-slate-600">
            <?php foreach ($order['history'] as $h): ?>
                <li><span class="text-slate-400"><?= $e(substr((string) $h['created_at'], 0, 16)) ?></span> · <?= $e($h['actor_type']) ?> · <?= $e($h['from_status'] ?? '—') ?> → <strong><?= $e($h['to_status']) ?></strong><?= !empty($h['note']) ? ' · ' . $e($h['note']) : '' ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
</main>
</body>
</html>
