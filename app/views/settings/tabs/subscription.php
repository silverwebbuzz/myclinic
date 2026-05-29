<?php
// After Phase 1 there is only one plan ('standard'). Old enum values
// kept as a defensive fallback so this view never crashes mid-migration.
$planKey = $clinic['plan'] ?? 'standard';
$plan = $plans[$planKey] ?? ($plans['standard'] ?? reset($plans));
$seatLimit = (int) ($clinic['seat_limit'] ?? 2) + (int) ($clinic['extra_seats_purchased'] ?? 0);
?>
<div class="space-y-4">
    <section class="ui-card ui-card-pad">
        <h2 class="ui-section-title">Subscription</h2>
        <p class="mt-2 text-sm text-slate-600">
            Plan: <strong><?= htmlspecialchars($plan['name'] ?? 'Free') ?></strong>
            · Seats: <?= (int) ($staffCount ?? 1) ?> / <?= $seatLimit ?>
        </p>
        <?php if (!empty($clinic['trial_ends_at'])): ?>
        <p class="text-xs text-slate-500">Trial ends <?= htmlspecialchars($clinic['trial_ends_at']) ?></p>
        <?php endif; ?>
        <a href="/onboarding/plan-selection" class="mt-4 inline-block ui-btn ui-btn-primary">Upgrade plan</a>
    </section>

    <section class="ui-card ui-card-pad">
        <h3 class="ui-section-title">Billing history</h3>
        <?php if ($invoices === []): ?>
        <p class="mt-2 text-sm text-slate-500">No invoices yet.</p>
        <?php else: ?>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left ui-group-label"><th class="pb-2">Period</th><th class="pb-2">Total</th><th class="pb-2">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            <?php foreach ($invoices as $inv): ?>
            <tr>
                <td class="py-2 text-slate-700"><?= htmlspecialchars($inv['period_start'] ?? '') ?> – <?= htmlspecialchars($inv['period_end'] ?? '') ?></td>
                <td class="text-slate-700">$<?= number_format((float)($inv['total_usd'] ?? 0), 2) ?></td>
                <td class="capitalize text-slate-600"><?= htmlspecialchars($inv['status'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-500">Cancel subscription: contact support (self-serve cancel in a future sprint).</p>
    </section>
</div>
