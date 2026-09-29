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
                        <td class="py-1.5"><?= $e($it['name']) ?><?= !empty($it['variant_title']) ? ' · ' . $e($it['variant_title']) : '' ?> <span class="font-mono text-xs text-slate-400"><?= $e($it['sku']) ?></span>
                            <?= (int) $it['qty_cancelled'] > 0 ? '<span class="block text-xs text-red-600">' . (int) $it['qty_cancelled'] . ' cancelled &amp; refunded</span>' : '' ?></td>
                        <td class="text-right"><?= (int) $it['qty'] ?></td>
                        <td class="text-right"><?= $r($it['line_total_paise']) ?></td>
                        <td class="text-right text-slate-500"><?= $r($it['tax_included_paise']) ?> (<?= (int) $it['gst_bp'] / 100 ?>%)</td>
                        <td class="text-right text-slate-500"><?= $r($it['commission_paise']) ?> <span class="text-xs">(<?= $it['commission_type'] === 'percent' ? ((int) $it['commission_rate_bp'] / 100) . '%' : 'fixed' ?>)</span></td>
                        <td class="text-right"><?= $r($it['vendor_payable_paise']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $voShip = $shipments[(int) $vo['id']] ?? []; ?>
            <div class="mt-3 rounded border bg-slate-50 p-3 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong>Shipping</strong>
                    <?php if (!$courierOn): ?><span class="text-xs text-amber-700">Shiprocket not connected (Store settings)</span><?php endif; ?>
                </div>
                <?php foreach ($voShip as $s): ?>
                    <div class="mt-2 rounded border bg-white p-2 <?= $s['status'] === 'cancelled' ? 'opacity-50' : '' ?>">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span><span class="font-mono"><?= $e($s['shipment_no']) ?></span> · <strong><?= $e(str_replace('_', ' ', (string) $s['status'])) ?></strong>
                                <?= !empty($s['courier_name']) ? ' · ' . $e($s['courier_name']) : '' ?>
                                <?= !empty($s['awb_code']) ? ' · AWB <span class="font-mono">' . $e($s['awb_code']) . '</span>' : '' ?>
                                <?= !empty($s['pickup_scheduled_for']) ? ' · pickup ' . $e($s['pickup_scheduled_for']) : '' ?>
                                · <?= (int) $s['weight_g'] ?> g, <?= (int) $s['length_mm'] / 10 ?>×<?= (int) $s['breadth_mm'] / 10 ?>×<?= (int) $s['height_mm'] / 10 ?> cm</span>
                            <span class="flex gap-2">
                                <?php if (!empty($s['label_url'])): ?><a href="<?= $e($s['label_url']) ?>" target="_blank" rel="noopener" class="text-sky-700 hover:underline">Label</a><?php endif; ?>
                                <?php if (!empty($s['awb_code'])): ?>
                                    <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/shipments/<?= (int) $s['id'] ?>/refresh"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-sky-700 hover:underline">Refresh tracking</button></form>
                                <?php endif; ?>
                                <?php if ($s['status'] !== 'cancelled' && (int) $s['status_rank'] < 40): ?>
                                    <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/shipments/<?= (int) $s['id'] ?>/cancel" onsubmit="return confirm('Cancel this courier booking?')"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-red-600 hover:underline">Cancel shipment</button></form>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($s['last_error']) && empty($s['label_url'])): ?>
                            <p class="mt-1 text-xs text-red-700">Last error: <?= $e(mb_substr((string) $s['last_error'], 0, 300)) ?></p>
                            <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/ship" class="mt-1"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="rounded bg-slate-800 px-2 py-1 text-xs text-white">Retry booking</button></form>
                        <?php endif; ?>
                        <?php if ($s['events']): ?>
                            <ul class="mt-2 space-y-0.5 text-xs text-slate-600">
                                <?php foreach ($s['events'] as $ev): ?>
                                    <li><span class="text-slate-400"><?= $e(substr((string) $ev['event_at'], 0, 16)) ?></span> · <?= $e($ev['raw_status']) ?><?= $ev['internal_status'] === null ? ' <span class="text-amber-600">(unmapped)</span>' : '' ?><?= !empty($ev['location']) ? ' · ' . $e($ev['location']) : '' ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php $hasActive = (bool) array_filter($voShip, static fn ($s) => $s['status'] !== 'cancelled'); ?>
                <?php if ($courierOn && !$hasActive && $vo['status'] === 'packed'): ?>
                    <?php $sg = \App\Services\Store\ShippingService::suggestPackage((int) $vo['id']); ?>
                    <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/ship" class="mt-2 flex flex-wrap items-end gap-2 text-xs">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <label>Weight g<input name="weight_g" type="number" value="<?= (int) $sg['weight_g'] ?>" class="ml-1 w-20 rounded border px-1 py-0.5"></label>
                        <label>L<input name="length_cm" type="number" step="0.5" value="<?= $e($sg['length_cm']) ?>" class="ml-1 w-14 rounded border px-1 py-0.5"></label>
                        <label>B<input name="breadth_cm" type="number" step="0.5" value="<?= $e($sg['breadth_cm']) ?>" class="ml-1 w-14 rounded border px-1 py-0.5"></label>
                        <label>H<input name="height_cm" type="number" step="0.5" value="<?= $e($sg['height_cm']) ?>" class="ml-1 w-14 rounded border px-1 py-0.5"></label>
                        <button class="rounded bg-slate-800 px-2 py-1 text-white">Book pickup</button>
                    </form>
                <?php elseif (!$voShip): ?>
                    <p class="mt-1 text-xs text-slate-500">No shipment yet<?= $vo['status'] === 'packed' ? '' : ' (package must be packed first)' ?>.</p>
                <?php endif; ?>
                <?php if (in_array($vo['status'], ['accepted', 'packed', 'ready_to_ship'], true)): ?>
                    <details class="mt-2 text-xs">
                        <summary class="cursor-pointer text-slate-600">Shipped outside Shiprocket? Mark shipped manually</summary>
                        <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/mark-shipped" class="mt-1 flex flex-wrap gap-2">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <input name="courier" placeholder="Courier (e.g. Delhivery)" class="rounded border px-2 py-1">
                            <input name="awb" placeholder="Tracking no. (optional)" class="rounded border px-2 py-1">
                            <button class="rounded bg-slate-800 px-2 py-1 text-white">Mark shipped</button>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if (in_array($vo['status'], ['shipped', 'ready_to_ship'], true)): ?>
                    <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/mark-delivered" class="mt-2"
                          onsubmit="return confirm('Mark this package delivered? The return window starts now and the seller\'s earnings become payable after it.')">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <button class="rounded border border-emerald-600 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Mark delivered manually</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php
            $open = array_filter($vo['items'], static fn ($it) => (int) $it['qty'] > (int) $it['qty_cancelled']);
            $adminCan = $open && in_array($vo['status'], \App\Services\Store\StoreRefundService::CANCELLABLE['admin'], true)
                && in_array($order['payment_status'], ['paid', 'partially_refunded'], true);
            ?>
            <?php if ($adminCan): ?>
                <details class="mt-3 rounded border border-red-200 bg-red-50/40 p-3 text-sm">
                    <summary class="cursor-pointer font-medium text-red-700">Cancel items &amp; refund…</summary>
                    <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/cancel-items" class="mt-2 space-y-2"
                          onsubmit="return confirm('Refund the selected items via Razorpay now?')">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <?php foreach ($open as $it): ?>
                            <?php $left = (int) $it['qty'] - (int) $it['qty_cancelled']; ?>
                            <label class="flex items-center justify-between gap-3"><span><?= $e($it['name']) ?> <span class="font-mono text-xs text-slate-400"><?= $e($it['sku']) ?></span></span>
                                <select name="cancel[<?= (int) $it['id'] ?>]" class="rounded border px-2 py-1">
                                    <?php for ($q = 0; $q <= $left; $q++): ?><option value="<?= $q ?>" <?= $q === $left ? '' : '' ?>><?= $q === 0 ? 'Keep' : 'Cancel ' . $q ?></option><?php endfor; ?>
                                </select></label>
                        <?php endforeach; ?>
                        <input name="reason" required placeholder="Reason (customer and seller see this)" class="w-full rounded border px-2 py-1">
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="restock" value="1" checked> Return units to the seller's stock</label>
                        <button class="rounded bg-red-600 px-3 py-1.5 text-white hover:bg-red-700">Cancel &amp; refund</button>
                    </form>
                </details>
            <?php endif; ?>
            <?php $voDocs = array_filter($taxDocs ?? [], static fn ($d) => (int) $d['vendor_order_id'] === (int) $vo['id']); ?>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                <strong class="text-slate-600">GST documents:</strong>
                <?php foreach ($voDocs as $d): ?>
                    <a href="/admin/store/gst/documents/<?= (int) $d['id'] ?>" target="_blank" class="rounded border px-2 py-0.5 font-mono <?= $d['doc_type'] === 'credit_note' ? 'border-amber-300 bg-amber-50 text-amber-800' : 'text-sky-700' ?>"
                       title="<?= $d['issuer'] === 'platform' ? 'eClinicPro delivery charge' : 'Seller' ?> <?= $d['doc_type'] === 'credit_note' ? 'credit note' : 'invoice' ?>"><?= $e($d['doc_no']) ?><?= $d['issuer'] === 'platform' ? ' (delivery)' : '' ?></a>
                <?php endforeach; ?>
                <?php if (!$voDocs): ?>
                    <span class="text-slate-400">none yet (issued automatically at dispatch)</span>
                    <?php if (in_array($vo['status'], ['packed', 'ready_to_ship', 'shipped', 'delivered', 'completed'], true)): ?>
                        <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/invoice" class="inline">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-sky-700 hover:underline">Issue now</button></form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php
            $undeliveredOpen = array_filter($vo['items'], static fn ($it) => (int) $it['qty'] > (int) $it['qty_cancelled'] + (int) $it['qty_returned']);
            $canUndelivered = $undeliveredOpen && in_array($vo['status'], \App\Services\Store\StoreRefundService::UNDELIVERED_FROM, true)
                && in_array($order['payment_status'], ['paid', 'partially_refunded'], true);
            ?>
            <?php if ($canUndelivered): ?>
                <details class="mt-3 rounded border border-amber-200 bg-amber-50/40 p-3 text-sm" <?= $vo['status'] === 'rto' ? 'open' : '' ?> x-data="{ cause: '<?= $vo['status'] === 'rto' ? 'rto' : '' ?>' }">
                    <summary class="cursor-pointer font-medium text-amber-800">Package not delivered? Refund the customer…</summary>
                    <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/undelivered" class="mt-2 space-y-2"
                          onsubmit="return confirm('Refund every remaining item in this package via Razorpay now? This cannot be undone.')">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <p class="text-xs text-slate-600">Refunds all remaining items (<?= array_sum(array_map(static fn ($it) => (int) $it['qty'] - (int) $it['qty_cancelled'] - (int) $it['qty_returned'], $undeliveredOpen)) ?> unit(s)) and issues a credit note against the seller's invoice. Cancel an active courier booking first if the package hasn't left.</p>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-1"><input type="radio" name="cause" value="rto" x-model="cause" required> Returned to seller (refused / unreachable)</label>
                            <label class="flex items-center gap-1"><input type="radio" name="cause" value="lost" x-model="cause"> Lost by courier</label>
                            <label class="flex items-center gap-1"><input type="radio" name="cause" value="damaged" x-model="cause"> Damaged in transit</label>
                        </div>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="refund_shipping" value="1" :checked="cause !== 'rto'"> Refund the delivery charge (<?= $r($vo['shipping_paise']) ?>). Recommended for lost/damaged, not for refused deliveries.</label>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="restock" value="1" :checked="cause === 'rto'"> Return units to the seller's stock (only if the goods came back)</label>
                        <label class="flex items-center gap-2 text-xs" x-show="cause !== 'rto'"><input type="checkbox" name="compensate" value="1" checked> Pay the seller what they would have earned (claim it from the courier)</label>
                        <input name="note" placeholder="Note (e.g. courier claim number)" class="w-full rounded border px-2 py-1">
                        <button class="rounded bg-amber-600 px-3 py-1.5 text-white hover:bg-amber-700">Refund package</button>
                    </form>
                </details>
            <?php endif; ?>
            <p class="mt-3 text-xs text-slate-500">
                Sub-total <?= $r($vo['items_subtotal_paise']) ?> · customer delivery fee share <?= $r($vo['shipping_paise']) ?> ·
                commission <?= $r($vo['commission_paise']) ?> + GST on commission <?= $r($vo['commission_gst_paise']) ?> ·
                <strong>seller payable <?= $r($vo['vendor_payable_paise']) ?></strong> before courier charges (released only after delivery + return window)
            </p>
            <?php
            $activeShip = null;
            foreach ($voShip as $s) {
                if ($s['direction'] === 'forward' && $s['status'] !== 'cancelled') {
                    $activeShip = $s;
                }
            }
            $fee = \App\Services\Store\SellerFeeService::packageCharge($vo, $activeShip);
            $charges = \App\Services\Store\SellerFeeService::chargesFor((int) $vo['id']);
            $charged = isset($vo['seller_shipping_paise']) && $vo['seller_shipping_paise'] !== null;   // booked at delivery
            ?>
            <details class="mt-3 rounded border bg-slate-50 p-3 text-sm">
                <summary class="cursor-pointer font-medium text-slate-700">
                    Courier &amp; seller charges · seller <?= $charged ? 'paid' : 'pays' ?> <?= $r($charged ? $vo['seller_shipping_paise'] : $fee['charge']) ?> courier<?= !$charged && $fee['estimated'] ? ' (estimate)' : '' ?>
                    <?= $charges ? ' · ' . count($charges) . ' ledger entr' . (count($charges) === 1 ? 'y' : 'ies') : '' ?>
                </summary>
                <p class="mt-2 text-xs text-slate-600">
                    Courier <?= $r($fee['courier']) ?><?= $fee['estimated'] ? ' (rate-card estimate)' : '' ?> − customer fee share <?= $r($fee['credit']) ?> = <strong><?= $r($fee['charge']) ?></strong> charged to the seller at delivery.
                    <?php if (!empty($vo['parcel_photo_path'])): ?>
                        · <a href="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/parcel-photo" target="_blank" class="text-sky-700 hover:underline">Parcel photo (on scale)</a>
                    <?php else: ?>
                        · <span class="text-amber-700">no parcel photo</span>
                    <?php endif; ?>
                </p>
                <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/courier-charge" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <label>Actual courier charge ₹ <input name="courier" inputmode="decimal" value="<?= $e(isset($vo['courier_charge_paise']) && $vo['courier_charge_paise'] !== null ? \App\Services\Store\ProductService::rupees((int) $vo['courier_charge_paise']) : '') ?>" placeholder="from Shiprocket invoice" class="ml-1 w-32 rounded border px-2 py-1"></label>
                    <button class="rounded border px-2 py-1 hover:bg-white">Save</button>
                    <span class="text-slate-400">If already delivered, the difference is booked automatically.</span>
                </form>
                <?php if ($charges): ?>
                    <table class="mt-3 w-full text-xs">
                        <?php foreach ($charges as $c): ?>
                            <tr class="border-t">
                                <td class="py-1 text-slate-400"><?= $e(substr((string) $c['created_at'], 0, 10)) ?></td>
                                <td class="py-1"><?= $e($c['memo']) ?></td>
                                <td class="py-1 text-right <?= (int) $c['amount_paise'] < 0 ? 'text-red-700' : 'text-emerald-700' ?>"><?= (int) $c['amount_paise'] < 0 ? '−' : '+' ?><?= $r(abs((int) $c['amount_paise'])) ?></td>
                                <td class="py-1 text-right text-slate-400"><?= $e($c['status']) ?></td>
                                <td class="py-1 text-right">
                                    <?php if ((int) $c['amount_paise'] < 0 && $c['created_by_type'] === 'admin'): ?>
                                        <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/charges/<?= (int) $c['id'] ?>/reverse" onsubmit="return confirm('Reverse this charge? The seller is credited the same amount.')">
                                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="text-sky-700 hover:underline">Reverse</button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
                <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/packages/<?= (int) $vo['id'] ?>/charge" class="mt-3 flex flex-wrap items-end gap-2 text-xs"
                      onsubmit="return confirm('Charge the seller this amount? It is deducted from their next payout and shown in their statement.')">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <label class="block">Charge the seller for
                        <select name="kind" required class="mt-1 block rounded border px-2 py-1">
                            <?php foreach (\App\Services\Store\SettlementService::CHARGES as $k => [, $label]): ?>
                                <option value="<?= $e($k) ?>"><?= $e($label) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                    <label class="block">Amount ₹<input name="amount" required inputmode="decimal" class="mt-1 block w-24 rounded border px-2 py-1"></label>
                    <label class="block grow">Note (seller sees it)<input name="note" maxlength="150" placeholder="e.g. Delhivery billed 1.5 kg, entered 0.5 kg" class="mt-1 block w-full rounded border px-2 py-1"></label>
                    <button class="rounded bg-slate-800 px-3 py-1.5 text-white hover:bg-slate-700">Add charge</button>
                </form>
            </details>
        </section>
    <?php endforeach; ?>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Payments (Razorpay<?= \App\Services\Store\StorePaymentService::mode() === 'production' ? '' : ', TEST mode' ?>)</h2>
            <?php if ($order['payments']): ?>
                <form method="post" action="/admin/store/orders/<?= (int) $order['id'] ?>/recheck">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="rounded border px-3 py-1 text-sm hover:bg-slate-50">Re-check with Razorpay</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$order['payments']): ?>
            <p class="mt-2 text-sm text-slate-400">No payment attempt yet.</p>
        <?php else: ?>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">Razorpay order</th><th>Payment</th><th>Method</th><th class="text-right">Amount</th><th>Status</th><th>Captured</th></tr></thead>
                <tbody class="divide-y">
                <?php foreach ($order['payments'] as $p): ?>
                    <tr>
                        <td class="py-1.5 font-mono text-xs"><?= $e($p['rzp_order_id']) ?></td>
                        <td class="font-mono text-xs"><?= $e($p['rzp_payment_id'] ?? '—') ?></td>
                        <td><?= $e($p['method'] ?? '—') ?></td>
                        <td class="text-right"><?= $r($p['amount_paise']) ?></td>
                        <td><?= $e($p['status']) ?><?= !empty($p['failure_reason']) ? ' <span class="text-xs text-red-600">(' . $e($p['failure_reason']) . ')</span>' : '' ?></td>
                        <td class="text-slate-500"><?= $e(substr((string) ($p['captured_at'] ?? ''), 0, 16) ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php foreach ($order['refunds'] as $rf): ?>
            <p class="mt-2 text-sm <?= $rf['status'] === 'failed' ? 'text-red-700' : 'text-slate-600' ?>">
                Refund <?= $e($rf['refund_no']) ?>: <?= $r($rf['amount_paise']) ?> · <?= $e($rf['reason']) ?> · <strong><?= $e($rf['status']) ?></strong>
                <?= $rf['status'] === 'failed' ? ' (refund manually in the Razorpay dashboard)' : '' ?>
            </p>
        <?php endforeach; ?>
    </section>

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
