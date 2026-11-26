<?php
/** @var array<string, mixed> $status */
$s = $status;
$freeLimit = (int) $s['free_limit'];
$freeUsed = min((int) $s['free_used'], $freeLimit);
$pct = $freeLimit > 0 ? (int) round($freeUsed / $freeLimit * 100) : 100;
$lowFree = $freeLimit > 0 && $pct >= 80 && (int) $s['paid_balance'] === 0;
$inr = static fn (float $v): string => '₹' . (floor($v) == $v ? number_format($v, 0) : number_format($v, 2));

$sendLabel = static function (array $r): array {
    $st = (string) ($r['delivery_status'] ?? '');
    $status = (string) ($r['status'] ?? '');
    if ($st === 'failed' || $status === 'failed') return ['Not delivered · not counted', 'bg-amber-50 text-amber-800'];
    if ($status === 'queued' || $status === '') return ['Sending', 'bg-slate-100 text-slate-700'];
    if ($st === 'read') return ['Read', 'bg-emerald-100 text-emerald-800'];
    if ($st === 'delivered') return ['Delivered', 'bg-emerald-100 text-emerald-800'];
    if ($status === 'sent') return ['Sent', 'bg-emerald-50 text-emerald-700'];
    return ['Sent', 'bg-emerald-50 text-emerald-700'];
};
?>
<?= ui_page_header('WhatsApp prescriptions', 'Send prescriptions to your patients\' WhatsApp. ' . $freeLimit . ' free every month.') ?>

<div class="mx-auto max-w-4xl space-y-4">

    <?php if ($bought > 0): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">✓ Payment received. <?= (int) $bought ?> messages added to your balance.</p>
    <?php elseif ($pending): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Your payment is being confirmed. The messages are added automatically within a few minutes.</p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!$s['ready']): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        WhatsApp prescription sending is being switched on by eClinicPro. You'll be able to send from the visit page soon. Your free messages are not being used meanwhile.
    </div>
    <?php elseif (!$s['enabled']): ?>
    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        WhatsApp prescriptions are turned off for your clinic. Please contact eClinicPro support.
    </div>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="ui-card ui-card-pad">
            <p class="text-xs text-slate-500">Free this month (<?= htmlspecialchars((string) $s['period_label']) ?>)</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= (int) $s['free_left'] ?> <span class="text-sm font-normal text-slate-500">of <?= $freeLimit ?> left</span></p>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-2 rounded-full <?= $pct >= 80 ? 'bg-amber-500' : 'bg-brand' ?>" style="width: <?= $pct ?>%"></div>
            </div>
            <p class="mt-1.5 text-xs text-slate-400">Resets on <?= htmlspecialchars((string) $s['resets_on']) ?></p>
        </div>
        <div class="ui-card ui-card-pad">
            <p class="text-xs text-slate-500">Purchased</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= (int) $s['paid_balance'] ?></p>
            <p class="mt-1.5 text-xs text-slate-400">Never expire. Used after the free ones.</p>
        </div>
        <div class="ui-card ui-card-pad">
            <p class="text-xs text-slate-500">Available now</p>
            <p class="mt-1 text-2xl font-semibold text-brand"><?= (int) $s['total_left'] ?></p>
            <p class="mt-1.5 text-xs text-slate-400">Prescriptions you can send</p>
        </div>
    </div>

    <?php if ($lowFree): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
        You've used <?= $freeUsed ?> of your <?= $freeLimit ?> free WhatsApp prescriptions this month. Buy a pack below so sending doesn't stop.
    </p>
    <?php endif; ?>

    <?php if ($packs !== []): ?>
    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Buy more messages</h3>
        <p class="mt-1 text-xs text-slate-500">One message = one prescription sent to one patient. Prices include 18% GST.</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <?php foreach ($packs as $i => $p): ?>
            <form method="post" action="/prescriptions/whatsapp/buy" class="rounded-lg border border-slate-200 p-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="pack" value="<?= (int) $i ?>">
                <p class="text-lg font-semibold text-slate-900"><?= number_format((int) $p['qty']) ?> messages</p>
                <p class="mt-1 text-sm text-slate-700"><?= $inr((float) $p['total']) ?> <span class="text-xs text-slate-400">(<?= $inr((float) $p['base']) ?> + GST)</span></p>
                <p class="text-xs text-slate-400"><?= $inr(round((float) $p['total'] / max(1, (int) $p['qty']), 2)) ?> per prescription</p>
                <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm mt-3 w-full">Buy</button>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Recent sends</h3>
        <?php if ($sends === []): ?>
        <p class="mt-2 text-sm text-slate-500">No prescriptions sent on WhatsApp yet.</p>
        <?php else: ?>
        <ul class="mt-2 divide-y divide-slate-100">
            <?php foreach ($sends as $r): [$label, $cls] = $sendLabel($r); ?>
            <li class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <div class="min-w-0">
                    <a href="/visits/<?= (int) $r['visit_id'] ?>" class="font-medium text-slate-800 hover:text-brand"><?= htmlspecialchars((string) ($r['patient_name'] ?? 'Patient')) ?></a>
                    <span class="ml-1 text-xs text-slate-400"><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) $r['created_at']))) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500"><?= $r['entry'] === 'paid_use' ? 'Purchased' : 'Free' ?></span>
                    <span class="rounded-full px-2 py-0.5 text-xs <?= $cls ?>"><?= $label ?></span>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <?php if ($credits !== []): ?>
    <div class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Purchases</h3>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
            <?php foreach ($credits as $c): ?>
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <span class="text-slate-700">
                    <?= $c['entry'] === 'purchase' ? 'Bought' : ($c['entry'] === 'admin_grant' ? 'Added by eClinicPro' : 'Adjusted by eClinicPro') ?>
                    <strong><?= abs((int) $c['qty']) ?></strong> messages
                    <?php if ($c['total_amount'] !== null): ?><span class="text-slate-400">· <?= $inr((float) $c['total_amount']) ?></span><?php endif; ?>
                    <?php if (!empty($c['invoice_id'])): ?>
                    · <a href="/settings/invoices/<?= (int) $c['invoice_id'] ?>/pdf" target="_blank" class="text-brand hover:underline">GST invoice <?= htmlspecialchars((string) $c['invoice_no']) ?></a>
                    <?php endif; ?>
                </span>
                <span class="text-xs text-slate-400"><?= htmlspecialchars(date('d M Y', strtotime((string) $c['created_at']))) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <p class="text-xs text-slate-400">
        The patient gets the prescription PDF in WhatsApp from the eClinicPro number, with your name and clinic. Free messages are used first, then purchased ones.
        A message that can't be delivered (for example, the number isn't on WhatsApp) is not counted.
    </p>
</div>
