<?php
/**
 * Completed visit: send the prescription to the patient's WhatsApp.
 * Expects $rxWa (RxWhatsAppService::status), $rxWaLast, $rxWaFlash, $visitId, $csrf.
 */
$rxReady = !empty($rxWa['ready']) && !empty($rxWa['enabled']);
$rxLeft = (int) ($rxWa['total_left'] ?? 0);
$rxFreeLeft = (int) ($rxWa['free_left'] ?? 0);
$rxFreeLimit = (int) ($rxWa['free_limit'] ?? 0);
$rxLow = $rxReady && $rxFreeLimit > 0 && $rxFreeLeft <= (int) floor($rxFreeLimit * 0.2) && (int) ($rxWa['paid_balance'] ?? 0) === 0;

// What happened to the latest send (auto on completion, or a manual send).
$rxLastLine = null;
$rxLastOk = true;
if (is_array($rxWaFlash) && empty($rxWaFlash['ok'])) {
    // Auto-send on completion didn't go ahead — say why, briefly.
    $rxLastOk = false;
    $rxLastLine = match ((string) ($rxWaFlash['code'] ?? '')) {
        'no_allowance' => 'Not sent automatically: no WhatsApp prescriptions left this month.',
        'opted_out' => 'Not sent: this patient replied STOP to WhatsApp messages.',
        'not_ready', 'disabled' => null, // explained below
        default => 'Not sent automatically: ' . (string) ($rxWaFlash['message'] ?? ''),
    };
} elseif (is_array($rxWaLast)) {
    $st = (string) ($rxWaLast['delivery_status'] ?? '');
    $status = (string) ($rxWaLast['status'] ?? '');
    $when = date('d M Y, h:i A', strtotime((string) $rxWaLast['created_at']));
    if ($st === 'failed' || $status === 'failed') {
        $rxLastOk = false;
        $rxLastLine = 'Last send could not be delivered (' . $when . '). It was not counted.';
    } elseif ($status === 'queued') {
        $rxLastLine = 'Prescription is being sent to the patient\'s WhatsApp (' . $when . ').';
    } else {
        $label = $st === 'read' ? 'read by the patient' : ($st === 'delivered' ? 'delivered' : 'sent');
        $rxLastLine = 'Prescription ' . $label . ' on WhatsApp (' . $when . ').';
    }
}
?>
<div class="ui-card ui-card-pad">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="ui-section-title">Prescription on WhatsApp</h3>
            <?php if ($rxLastLine !== null): ?>
            <p class="mt-1 text-sm <?= $rxLastOk ? 'text-emerald-700' : 'text-amber-800' ?>"><?= htmlspecialchars($rxLastLine) ?></p>
            <?php endif; ?>
            <?php if ($rxReady): ?>
            <p class="mt-1 text-xs text-slate-500">
                <?= $rxLeft ?> left: <?= $rxFreeLeft ?> free this month<?= (int) ($rxWa['paid_balance'] ?? 0) > 0 ? ' + ' . (int) $rxWa['paid_balance'] . ' purchased' : '' ?>.
                <a href="/prescriptions/whatsapp" class="font-medium text-brand hover:underline">Usage &amp; packs</a>
            </p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($rxReady && $rxLeft > 0): ?>
            <form method="post" action="/visits/<?= (int) $visitId ?>/send-rx" class="inline"
                  <?php if (is_array($rxWaLast) && $rxLastOk): ?>onsubmit="return confirm('Send the prescription again? This uses 1 more message.');"<?php endif; ?>>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">
                    <?= is_array($rxWaLast) && $rxLastOk ? 'Send again' : 'Send on WhatsApp' ?>
                </button>
            </form>
            <?php elseif ($rxReady): ?>
            <a href="/prescriptions/whatsapp" class="ui-btn ui-btn-primary ui-btn-sm">Buy more messages</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($rxReady && $rxLeft > 0): ?>
    <p class="mt-2 text-xs text-slate-400">Only send if the patient agreed to get their prescription on WhatsApp.</p>
    <?php elseif ($rxReady): ?>
    <p class="mt-2 text-xs text-amber-800">Free WhatsApp prescriptions are used up for this month (resets <?= htmlspecialchars((string) ($rxWa['resets_on'] ?? '')) ?>). Buy a pack to keep sending.</p>
    <?php endif; ?>
    <?php if ($rxLow && $rxLeft > 0): ?>
    <p class="mt-1 text-xs text-amber-800">Only <?= $rxFreeLeft ?> free left this month. <a href="/prescriptions/whatsapp" class="underline">Buy a pack</a> so sending doesn't stop.</p>
    <?php endif; ?>
</div>
