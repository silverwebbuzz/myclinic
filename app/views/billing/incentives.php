<div class="space-y-6">
    <h2 class="text-lg font-semibold">Doctor incentives</h2>
    <?php if (!empty($message)): ?><p class="text-sm text-emerald-600"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <form method="post" action="/billing/incentives/config" class="rounded-xl border bg-white p-4 space-y-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <h3 class="text-sm font-medium">Per-doctor config</h3>
        <?php foreach ($doctors as $i => $doc): ?>
        <input type="hidden" name="doctor_id[]" value="<?= (int) $doc['id'] ?>">
        <div class="grid gap-2 sm:grid-cols-3 text-sm items-center">
            <span class="font-medium"><?= htmlspecialchars($doc['name']) ?></span>
            <label>% <input name="incentive_percent[]" type="number" step="0.01" value="<?= htmlspecialchars((string)($doc['incentive_percent']??0)) ?>" class="w-full rounded border px-2 py-1"></label>
            <label>Flat ₹ <input name="incentive_flat_fee[]" type="number" step="0.01" value="<?= htmlspecialchars((string)($doc['incentive_flat_fee']??0)) ?>" class="w-full rounded border px-2 py-1"></label>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white">Save config</button>
    </form>

    <form method="post" action="/billing/incentives/calculate" class="flex flex-wrap gap-2 items-end">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="text-sm">Period <input name="period" type="month" value="<?= htmlspecialchars($period) ?>" class="rounded border px-2 py-1"></label>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Calculate month</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                <th class="px-4 py-3 text-left">Doctor</th><th>Revenue</th><th>Gross</th><th>Net</th><th></th>
            </tr></thead>
            <tbody class="divide-y">
                <?php if ($incentives === []): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Run calculate for <?= htmlspecialchars($period) ?></td></tr>
                <?php else: ?>
                <?php foreach ($incentives as $row): ?>
                <tr>
                    <td class="px-4 py-3"><?= htmlspecialchars($row['doctor_name'] ?? '') ?></td>
                    <td class="px-4 py-3">₹<?= number_format((float)($row['revenue_generated']??0),2) ?></td>
                    <td class="px-4 py-3">₹<?= number_format((float)($row['gross_incentive']??0),2) ?></td>
                    <td class="px-4 py-3 font-medium">₹<?= number_format((float)($row['net_payable']??0),2) ?></td>
                    <td class="px-4 py-3"><a href="/billing/incentives/<?= (int)$row['id'] ?>/payslip" class="text-emerald-600 text-xs">Payslip PDF</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
