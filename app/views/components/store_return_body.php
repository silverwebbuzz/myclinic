<?php
/**
 * Shared return detail + actions for the seller portal and admin.
 *
 * @var array<string,mixed> $r ReturnService::find()
 * @var string $base  '/vendor/returns' or '/admin/store/returns'
 * @var bool $isAdmin
 * @var string $csrf
 */
use App\Services\Store\ProductService;
use App\Services\Store\ReturnService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rid = (int) $r['id'];
$st = (string) $r['status'];
$amount = array_sum(array_map(static fn ($i) => (int) $i['unit_price_paise'] * (int) $i['qty'], $r['items']));
$label = ['requested' => 'Awaiting decision', 'approved' => 'Approved: arrange pickup', 'pickup_scheduled' => 'Pickup scheduled',
    'picked_up' => 'On its way back', 'received' => 'Received: check the item', 'qc_passed' => 'Check passed', 'qc_failed' => 'Check failed: admin to decide',
    'refunded' => 'Refunded', 'rejected' => 'Rejected', 'closed' => 'Closed'][$st] ?? $st;
$btn = 'rounded-full px-4 py-2 text-sm font-medium';
?>
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="font-mono text-2xl font-semibold"><?= $e($r['return_no']) ?></h1>
        <p class="text-sm text-slate-500">Order <?= $e($r['order_no']) ?> · package <?= $e($r['sub_order_no']) ?><?= $isAdmin ? ' · ' . $e($r['vendor_name']) : '' ?> · requested <?= $e(\App\Support\IndianDate::dateTime($r['created_at'])) ?></p>
    </div>
    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold"><?= $e($label) ?></span>
</div>

<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 text-sm">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <div class="text-xs uppercase text-slate-400">Reason</div>
            <div class="font-medium"><?= $e(ReturnService::REASONS[$r['reason_code']] ?? $r['reason_code']) ?></div>
            <?php if (!empty($r['customer_note'])): ?><p class="mt-1 whitespace-pre-line text-slate-600">“<?= $e($r['customer_note']) ?>”</p><?php endif; ?>
            <?php if (!empty($r['vendor_note'])): ?><p class="mt-2 text-slate-600"><span class="text-xs uppercase text-slate-400">Seller/admin note:</span> <?= $e($r['vendor_note']) ?></p><?php endif; ?>
        </div>
        <div>
            <div class="text-xs uppercase text-slate-400">Items (refund value <?= '₹' . $e(ProductService::rupees($amount)) ?>)</div>
            <ul class="mt-1 space-y-1">
                <?php foreach ($r['items'] as $it): ?>
                    <li><span class="font-mono text-xs"><?= $e($it['sku']) ?></span> <?= $e($it['name']) ?><?= $it['variant_title'] ? ' · ' . $e($it['variant_title']) : '' ?> × <?= (int) $it['qty'] ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php if ($r['photos']): ?>
        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach ($r['photos'] as $i => $_): ?>
                <a href="<?= $e($base . '/' . $rid . '/photo/' . $i) ?>" target="_blank" rel="noopener"><img src="<?= $e($base . '/' . $rid . '/photo/' . $i) ?>" alt="Customer photo <?= $i + 1 ?>" class="h-28 w-28 rounded-lg border object-cover"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 text-sm">
    <h2 class="font-semibold">Next step</h2>
    <?php if ($st === 'requested'): ?>
        <form method="post" class="mt-3 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input name="note" maxlength="500" placeholder="Note (required to reject; the customer sees it)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <div class="flex flex-wrap gap-2">
                <button formaction="<?= $e($base . '/' . $rid . '/approve') ?>" class="<?= $btn ?> bg-emerald-600 text-white hover:bg-emerald-700">Approve: collect the item</button>
                <button formaction="<?= $e($base . '/' . $rid . '/approve-no-pickup') ?>" class="<?= $btn ?> border border-emerald-600 text-emerald-700 hover:bg-emerald-50"
                        onclick="return confirm('Refund the customer now without collecting the item?')">Refund without pickup</button>
                <button formaction="<?= $e($base . '/' . $rid . '/reject') ?>" class="<?= $btn ?> border border-red-300 text-red-700 hover:bg-red-50">Reject</button>
            </div>
            <p class="text-xs text-slate-500">"Refund without pickup" suits low-value or unsafe-to-return items (e.g. opened food): the customer is refunded immediately.</p>
        </form>
    <?php elseif (in_array($st, ['approved', 'pickup_scheduled', 'picked_up', 'received'], true)): ?>
        <p class="mt-1 text-slate-600">
            <?= $st === 'approved' ? 'Reverse pickup isn\'t booked yet' . ($isAdmin ? ': arrange it manually or retry booking below.' : '. The eClinicPro team will arrange it.') : '' ?>
            <?= $st === 'pickup_scheduled' ? 'A courier will collect it from the customer.' : '' ?>
            <?= $st === 'picked_up' ? 'On its way back to you.' : '' ?>
            When the item reaches you, check it and record the result.
        </p>
        <form method="post" class="mt-3 space-y-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input name="note" maxlength="500" placeholder="Condition notes (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <label class="flex items-center gap-2"><input type="checkbox" name="restock" value="1"> Item is resaleable: add it back to my stock</label>
            <div class="flex flex-wrap gap-2">
                <button formaction="<?= $e($base . '/' . $rid . '/receive-ok') ?>" class="<?= $btn ?> bg-emerald-600 text-white hover:bg-emerald-700"
                        onclick="return confirm('Received and OK? The customer will be refunded now.')">Received: OK, refund customer</button>
                <button formaction="<?= $e($base . '/' . $rid . '/receive-fail') ?>" class="<?= $btn ?> border border-red-300 text-red-700 hover:bg-red-50">Received: problem (used / missing / different item)</button>
                <?php if ($isAdmin && $st === 'approved'): ?>
                    <button formaction="<?= $e($base . '/' . $rid . '/rebook') ?>" class="<?= $btn ?> border border-slate-300 hover:bg-slate-50">Book reverse pickup (Shiprocket)</button>
                <?php endif; ?>
            </div>
        </form>
    <?php elseif ($st === 'qc_failed'): ?>
        <p class="mt-1 text-amber-700">The seller reported a problem with the returned item. <?= $isAdmin ? 'Decide:' : 'The eClinicPro team will review and decide.' ?></p>
        <?php if ($isAdmin): ?>
            <form method="post" class="mt-3 flex flex-wrap gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="note" maxlength="500" placeholder="Note to customer (for reject)" class="flex-1 rounded-lg border border-slate-300 px-3 py-2">
                <button formaction="<?= $e($base . '/' . $rid . '/refund') ?>" class="<?= $btn ?> bg-emerald-600 text-white">Refund anyway</button>
                <button formaction="<?= $e($base . '/' . $rid . '/reject') ?>" class="<?= $btn ?> border border-red-300 text-red-700">Reject return</button>
            </form>
        <?php endif; ?>
    <?php elseif ($st === 'refunded'): ?>
        <p class="mt-1 text-emerald-700">Refunded ₹<?= $e(ProductService::rupees((int) $r['refund_amount_paise'])) ?>. The seller's earnings were adjusted.</p>
    <?php else: ?>
        <p class="mt-1 text-slate-600"><?= $e($label) ?>.</p>
    <?php endif; ?>
</section>
