<?php
// Annual "Standard" plan model. Old enum values kept as a defensive fallback.
$planKey = $clinic['plan'] ?? 'standard';
$plan = $plans[$planKey] ?? ($plans['standard'] ?? reset($plans));
$seatLimit = (int) ($clinic['seat_limit'] ?? 2) + (int) ($clinic['extra_seats_purchased'] ?? 0);

// Live price breakdown for the Standard annual plan (base + 18% GST).
$base = (float) ($plans['standard']['yearly_usd'] ?? 16000);
$taxPct = 18.0;
$tax = round($base * $taxPct / 100, 2);
$gross = round($base + $tax, 2);

// "Paid" = there's a paid subscription invoice on file (vs. trial / free).
$isPaid = !empty(array_filter($invoices, static fn ($i) => ($i['status'] ?? '') === 'paid'));
$onTrial = !empty($clinic['trial_ends_at']) && strtotime((string) $clinic['trial_ends_at']) >= time();
?>
<div class="space-y-4">
    <section class="ui-card ui-card-pad">
        <h2 class="ui-section-title">Subscription</h2>
        <p class="mt-2 text-sm text-slate-600">
            Plan: <strong><?= htmlspecialchars($plan['name'] ?? 'Free') ?></strong>
            · Seats: <?= (int) ($staffCount ?? 1) ?> / <?= $seatLimit ?>
            <?php if ($isPaid): ?>
            · <span class="font-medium text-emerald-700">Active</span>
            <?php endif; ?>
        </p>
        <?php if ($onTrial && !$isPaid): ?>
        <p class="text-xs text-amber-700">Trial ends <?= htmlspecialchars(date('d M Y', strtotime((string) $clinic['trial_ends_at']))) ?> — subscribe to keep full access.</p>
        <?php endif; ?>

        <?php if (!$isPaid): ?>
        <!-- Subscribe / upgrade — triggers real Cashfree checkout -->
        <div class="mt-4 rounded-xl border border-slate-200 p-4" style="max-width:380px;">
            <p class="text-sm font-semibold text-slate-800">eClinicPro Standard — Annual</p>
            <p class="text-xs text-slate-500">Everything to run your clinic · unlimited patients &amp; users</p>
            <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Plan price</dt><dd class="text-slate-800">₹<?= number_format($base, 2) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">GST (18%)</dt><dd class="text-slate-800">₹<?= number_format($tax, 2) ?></dd></div>
                <div class="mt-1 flex justify-between border-t border-slate-100 pt-1.5"><dt class="font-semibold text-slate-900">Total / year</dt><dd class="font-bold text-slate-900">₹<?= number_format($gross, 2) ?></dd></div>
            </dl>
            <form method="post" action="/subscription/checkout" class="mt-3"
                  @submit="$el.querySelector('button').disabled = true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="plan" value="standard">
                <input type="hidden" name="billing_cycle" value="yearly">
                <button type="submit" class="ui-btn ui-btn-primary w-full">Subscribe now · ₹<?= number_format($gross, 0) ?></button>
            </form>
            <p class="mt-2 text-center text-[11px] text-slate-400">Secure payment via Cashfree</p>
        </div>
        <?php else: ?>
        <p class="mt-3 text-sm text-emerald-700">✓ Your subscription is active. Invoices are below.</p>
        <?php endif; ?>
    </section>

    <?php
    // A payment that's still confirming (Cashfree webhook lands within a few
    // mins). Show the in-progress timeline so the doctor isn't left guessing.
    $pending = array_values(array_filter($invoices, static fn ($i) => ($i['status'] ?? '') === 'pending'));
    $statusTone = static fn (string $s): string => match ($s) {
        'paid' => 'bg-emerald-50 text-emerald-700',
        'pending' => 'bg-amber-50 text-amber-700',
        'failed' => 'bg-rose-50 text-rose-700',
        default => 'bg-slate-100 text-slate-600',
    };
    ?>

    <?php if ($pending !== []): ?>
    <section class="ui-card ui-card-pad border-amber-200" style="border-color:#fcd34d;">
        <h3 class="ui-section-title text-amber-800">Payment in progress</h3>
        <?php foreach ($pending as $p): ?>
        <div class="mt-3 rounded-lg bg-amber-50/60 p-3">
            <p class="text-sm font-medium text-slate-800">
                <?= htmlspecialchars(ucfirst((string) ($p['plan_id'] ?? 'Plan'))) ?> · ₹<?= number_format((float) ($p['amount'] ?? 0), 2) ?>
            </p>
            <!-- Pending → Paid timeline -->
            <ol class="mt-3 space-y-3">
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-[11px] text-white">✓</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800">Order created</p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars(date('d M Y, h:i A', strtotime((string) ($p['created_at'] ?? 'now')))) ?></p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-amber-400 text-[11px] text-white">●</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800">Awaiting payment confirmation</p>
                        <p class="text-xs text-slate-500">This updates automatically within a few minutes of payment. You can refresh this page.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3 opacity-50">
                    <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-slate-300 text-[11px] text-white">○</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800">Invoice issued &amp; emailed</p>
                        <p class="text-xs text-slate-500">Sent to your clinic email once payment is confirmed.</p>
                    </div>
                </li>
            </ol>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Billing history</h3>
        <?php if (!empty($_GET['error'])): ?>
        <p class="mt-2 rounded bg-rose-50 px-3 py-2 text-sm text-rose-700"><?= htmlspecialchars((string) $_GET['error']) ?></p>
        <?php endif; ?>
        <?php if ($invoices === []): ?>
        <p class="mt-2 text-sm text-slate-500">No invoices yet.</p>
        <?php else: ?>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left ui-group-label">
                <th class="pb-2">Invoice</th><th class="pb-2">Plan</th><th class="pb-2">Amount</th><th class="pb-2">Status</th><th class="pb-2"></th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($invoices as $inv):
                $st = (string) ($inv['status'] ?? '');
                // Amount: new INR column when present, else legacy total_usd.
                $amt = isset($inv['amount']) && (float) $inv['amount'] > 0 ? (float) $inv['amount'] : (float) ($inv['total_usd'] ?? 0);
                $cur = $inv['currency'] ?? 'INR';
                $sym = $cur === 'INR' ? '₹' : ($cur . ' ');
            ?>
            <tr>
                <td class="py-2 font-mono text-xs text-slate-700"><?= htmlspecialchars($inv['invoice_no'] ?? ('#' . (int) $inv['id'])) ?>
                    <div class="text-[11px] text-slate-400"><?= htmlspecialchars(substr((string) ($inv['created_at'] ?? ''), 0, 10)) ?></div>
                </td>
                <td class="text-slate-700"><?= htmlspecialchars(ucfirst((string) ($inv['plan_id'] ?? '—'))) ?></td>
                <td class="text-slate-700"><?= $sym ?><?= number_format($amt, 2) ?></td>
                <td><span class="rounded-full px-2 py-0.5 text-xs capitalize <?= $statusTone($st) ?>"><?= htmlspecialchars($st) ?></span></td>
                <td class="text-right">
                    <?php if ($st === 'paid'): ?>
                    <a href="/settings/invoices/<?= (int) $inv['id'] ?>/pdf" target="_blank" class="text-xs font-medium text-brand hover:underline">Download</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-500">Invoices are issued by SILVER WEBBUZZ PRIVATE LIMITED (GSTIN 24ABHCS6317H1ZB). Cancel subscription: contact support.</p>
    </section>
</div>
