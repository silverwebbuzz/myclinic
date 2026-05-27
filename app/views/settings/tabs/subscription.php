<?php
// After Phase 1 there is only one plan ('standard'). Old enum values
// kept as a defensive fallback so this view never crashes mid-migration.
$planKey = $clinic['plan'] ?? 'standard';
$plan = $plans[$planKey] ?? ($plans['standard'] ?? reset($plans));
$seatLimit = (int) ($clinic['seat_limit'] ?? 2) + (int) ($clinic['extra_seats_purchased'] ?? 0);
?>
<div class="space-y-6">
    <section class="rounded-xl border bg-white p-6">
        <h2 class="text-lg font-semibold">Subscription</h2>
        <p class="mt-2 text-sm text-slate-600">
            Plan: <strong><?= htmlspecialchars($plan['name'] ?? 'Free') ?></strong>
            · Seats: <?= (int) ($staffCount ?? 1) ?> / <?= $seatLimit ?>
        </p>
        <?php if (!empty($clinic['trial_ends_at'])): ?>
        <p class="text-xs text-slate-500">Trial ends <?= htmlspecialchars($clinic['trial_ends_at']) ?></p>
        <?php endif; ?>
        <a href="/onboarding/plan-selection" class="mt-4 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Upgrade plan</a>
    </section>

    <section class="rounded-xl border bg-white p-6">
        <h3 class="text-sm font-semibold">Active modules</h3>
        <ul class="mt-3 divide-y text-sm">
            <?php foreach ($modules as $mod): ?>
            <li class="flex justify-between py-2">
                <span><?= htmlspecialchars($mod['name'] ?? $mod['module_id']) ?></span>
                <span class="text-slate-400">$<?= number_format((float)($mod['price_monthly_usd'] ?? 0), 2) ?>/mo</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="rounded-xl border bg-white p-6">
        <h3 class="text-sm font-semibold">Billing history</h3>
        <?php if ($invoices === []): ?>
        <p class="mt-2 text-sm text-slate-500">No invoices yet.</p>
        <?php else: ?>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-xs text-slate-500"><th>Period</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
            <tr class="border-t">
                <td class="py-2"><?= htmlspecialchars($inv['period_start'] ?? '') ?> – <?= htmlspecialchars($inv['period_end'] ?? '') ?></td>
                <td>$<?= number_format((float)($inv['total_usd'] ?? 0), 2) ?></td>
                <td class="capitalize"><?= htmlspecialchars($inv['status'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="mt-4 text-xs text-slate-500">Cancel subscription: contact support (self-serve cancel in a future sprint).</p>
    </section>
</div>
