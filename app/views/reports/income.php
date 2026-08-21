<?php
/**
 * Income report — billed vs collected for a date range (today by default),
 * with the GST split when the clinic charges tax.
 */
$currency = '₹';
$sum = $summary ?? [];
$money = static fn ($v): string => '₹' . number_format((float) $v, 2);
$qs = static fn (array $extra): string => '?' . http_build_query(array_filter(array_merge([
    'from' => $from, 'to' => $to,
], $extra), static fn ($v) => $v !== null && $v !== ''));

$modeLabels = ['cash' => 'Cash', 'online' => 'Online', 'upi' => 'UPI', 'card' => 'Card',
    'insurance' => 'Insurance', 'bank_transfer' => 'Bank transfer'];
$rangeLabel = $isToday
    ? 'Today · ' . date('d M Y', strtotime($from))
    : date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 ui-page-title">
                <span class="text-brand"><?= ui_icon('analytics', 20) ?></span> Income report
            </h2>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($rangeLabel) ?></p>
        </div>
        <a href="/reports/income/export<?= htmlspecialchars($qs([])) ?>" class="ui-btn ui-btn-secondary ui-btn-sm">Download CSV</a>
    </div>

    <!-- Range picker: quick presets + custom dates. Today is the default. -->
    <form method="get" class="flex flex-wrap items-end gap-2 ui-card p-4">
        <div class="flex overflow-hidden rounded-lg border border-slate-200 text-sm font-medium">
            <?php
            $today = date('Y-m-d');
            $presets = [
                ['Today', ['from' => $today, 'to' => $today], $from === $today && $to === $today],
                ['This week', ['range' => 'week'], ($range ?? '') === 'week'],
                ['This month', ['range' => 'month'], ($range ?? '') === 'month'],
                ['This year', ['range' => 'year'], ($range ?? '') === 'year'],
            ];
            foreach ($presets as [$label, $params, $active]): ?>
            <a href="?<?= htmlspecialchars(http_build_query($params)) ?>"
               class="px-3 py-2 <?= $active ? 'bg-brand text-white' : 'bg-white text-slate-600 hover:bg-slate-50' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <label class="text-sm">
            <span class="ui-label">From</span>
            <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="ui-input">
        </label>
        <label class="text-sm">
            <span class="ui-label">To</span>
            <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="ui-input">
        </label>
        <button type="submit" class="ui-btn ui-btn-primary">Apply</button>
    </form>

    <!-- Headline numbers -->
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $tiles = [
            ['Collected', $sum['collected'] ?? 0, 'border-emerald-400', 'text-emerald-700', 'received in this period'],
            ['Billed', $sum['billed'] ?? 0, 'border-slate-300', 'text-slate-800', ((int) ($sum['invoices'] ?? 0)) . ' invoice' . (((int) ($sum['invoices'] ?? 0)) === 1 ? '' : 's')],
            [($sum['tax_label'] ?? 'GST'), $sum['tax'] ?? 0, 'border-blue-400', 'text-blue-700', 'tax charged'],
            ['Outstanding', $sum['due'] ?? 0, 'border-amber-400', 'text-amber-700', 'still due on these bills'],
        ];
        foreach ($tiles as [$label, $value, $border, $text, $hint]): ?>
        <div class="rounded-xl border-2 <?= $border ?> bg-white p-4">
            <p class="text-2xl font-bold <?= $text ?>"><?= $money($value) ?></p>
            <p class="ui-group-label mt-1"><?= htmlspecialchars($label) ?></p>
            <p class="text-xs text-slate-400"><?= htmlspecialchars($hint) ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <!-- Billing breakdown -->
        <section class="ui-card p-4">
            <h3 class="ui-section-title text-base">Billing breakdown</h3>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Gross charges</dt><dd><?= $money($sum['gross'] ?? 0) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="text-rose-600">− <?= $money($sum['discount'] ?? 0) ?></dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-1.5"><dt class="text-slate-500">Taxable value</dt><dd><?= $money($sum['taxable'] ?? 0) ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500"><?= htmlspecialchars($sum['tax_label'] ?? 'GST') ?></dt><dd><?= $money($sum['tax'] ?? 0) ?></dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-1.5 font-semibold"><dt>Total billed</dt><dd><?= $money($sum['billed'] ?? 0) ?></dd></div>
            </dl>
        </section>

        <!-- How the money came in -->
        <section class="ui-card p-4">
            <h3 class="ui-section-title text-base">Collected by mode</h3>
            <?php if (empty($sum['modes'])): ?>
            <p class="mt-2 text-sm text-slate-400">No payments recorded in this period.</p>
            <?php else: ?>
            <dl class="mt-3 space-y-1.5 text-sm">
                <?php foreach ($sum['modes'] as $mode => $amount): ?>
                <div class="flex justify-between">
                    <dt class="text-slate-500"><?= htmlspecialchars($modeLabels[$mode] ?? ucfirst((string) $mode)) ?></dt>
                    <dd><?= $money($amount) ?></dd>
                </div>
                <?php endforeach; ?>
                <div class="flex justify-between border-t border-slate-100 pt-1.5 font-semibold">
                    <dt>Total collected</dt><dd><?= $money($sum['collected'] ?? 0) ?></dd>
                </div>
            </dl>
            <?php endif; ?>
        </section>

        <!-- GST report — only when tax was actually charged -->
        <section class="ui-card p-4">
            <h3 class="ui-section-title text-base"><?= htmlspecialchars($sum['tax_label'] ?? 'GST') ?> report</h3>
            <?php if (empty($gst)): ?>
            <p class="mt-2 text-sm text-slate-400">No tax charged in this period.</p>
            <?php else: ?>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left ui-group-label">
                    <tr><th class="pb-1">Rate</th><th class="pb-1 text-right">Taxable</th><th class="pb-1 text-right">Tax</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($gst as $g): ?>
                    <tr>
                        <td class="py-1.5"><?= rtrim(rtrim(number_format($g['percent'], 2), '0'), '.') ?>%
                            <span class="text-xs text-slate-400">· <?= (int) $g['invoices'] ?></span>
                        </td>
                        <td class="py-1.5 text-right"><?= $money($g['taxable']) ?></td>
                        <td class="py-1.5 text-right font-medium"><?= $money($g['tax']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>

    <!-- Day-by-day (hidden for a single-day range — the tiles already say it) -->
    <?php if (!$isToday && count($daily ?? []) > 1): ?>
    <section class="overflow-hidden ui-card">
        <h3 class="ui-section-title border-b border-slate-100 px-4 py-2.5 text-base">Day by day</h3>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left ui-group-label">
                <tr><th class="px-4 py-2">Date</th><th class="px-4 py-2">Invoices</th>
                    <th class="px-4 py-2 text-right"><?= htmlspecialchars($sum['tax_label'] ?? 'GST') ?></th>
                    <th class="px-4 py-2 text-right">Billed</th></tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($daily as $d): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2"><?= htmlspecialchars(date('d M Y', strtotime((string) $d['d']))) ?></td>
                    <td class="px-4 py-2"><?= (int) $d['invoices'] ?></td>
                    <td class="px-4 py-2 text-right text-slate-600"><?= $money($d['tax']) ?></td>
                    <td class="px-4 py-2 text-right font-medium"><?= $money($d['billed']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- Invoice detail -->
    <section class="overflow-hidden ui-card">
        <h3 class="ui-section-title border-b border-slate-100 px-4 py-2.5 text-base">
            Invoices <span class="text-sm font-normal text-slate-500">(<?= count($invoices ?? []) ?>)</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left ui-group-label">
                    <tr>
                        <th class="px-4 py-2">Invoice</th>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Patient</th>
                        <th class="px-4 py-2">Doctor</th>
                        <th class="px-4 py-2 text-right">Taxable</th>
                        <th class="px-4 py-2 text-right"><?= htmlspecialchars($sum['tax_label'] ?? 'GST') ?></th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-right">Due</th>
                        <th class="px-4 py-2">Mode</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">No invoices in this period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($invoices as $inv):
                        $paid = (float) ($inv['amount_paid'] ?? 0) + (float) ($inv['advance_paid'] ?? 0);
                        $due = max(0, round((float) $inv['total'] - $paid, 2));
                        $taxable = round((float) ($inv['subtotal'] ?? 0) - (float) ($inv['discount_amount'] ?? 0), 2);
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs">
                            <a href="/billing/<?= (int) $inv['id'] ?>" class="text-brand hover:underline"><?= htmlspecialchars((string) $inv['invoice_number']) ?></a>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-500"><?= htmlspecialchars(date('d M Y', strtotime((string) $inv['created_at']))) ?></td>
                        <td class="px-4 py-2">
                            <?= htmlspecialchars((string) ($inv['patient_name'] ?? '')) ?>
                            <span class="block font-mono text-[11px] text-slate-400"><?= htmlspecialchars((string) ($inv['uhid'] ?? '')) ?></span>
                        </td>
                        <td class="px-4 py-2 text-xs text-slate-600"><?= htmlspecialchars((string) ($inv['doctor_name'] ?? '—')) ?></td>
                        <td class="px-4 py-2 text-right"><?= $money($taxable) ?></td>
                        <td class="px-4 py-2 text-right text-slate-600"><?= $money($inv['tax_amount'] ?? 0) ?></td>
                        <td class="px-4 py-2 text-right font-medium"><?= $money($inv['total']) ?></td>
                        <td class="px-4 py-2 text-right <?= $due > 0 ? 'font-semibold text-amber-700' : 'text-slate-300' ?>">
                            <?= $due > 0 ? $money($due) : '—' ?>
                        </td>
                        <td class="px-4 py-2 text-xs uppercase text-slate-500"><?= htmlspecialchars((string) ($inv['payment_mode'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
