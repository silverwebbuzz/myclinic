<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Billing</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/billing/export/excel" class="rounded-lg border px-3 py-2 text-sm">Export Excel</a>
            <a href="/billing/export/tally" class="rounded-lg border px-3 py-2 text-sm">Tally XML</a>
        </div>
    </div>

    <form method="get" class="flex flex-wrap gap-2 rounded-xl border bg-white p-4">
        <input type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Search invoice, patient…" class="min-w-[200px] flex-1 rounded-lg border px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border px-3 py-2 text-sm">
            <option value="">All statuses</option>
            <?php foreach (['draft','sent','partial','paid','overdue'] as $st): ?>
            <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if ($invoices === []): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No invoices yet. Complete a visit to auto-create a draft.</td></tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($inv['invoice_number']) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($inv['patient_name'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= number_format((float) $inv['total'], 2) ?> <?= htmlspecialchars($inv['currency'] ?? '') ?></td>
                    <td class="px-4 py-3 capitalize"><?= htmlspecialchars($inv['status'] ?? '') ?></td>
                    <td class="px-4 py-3 text-xs"><?= htmlspecialchars(substr($inv['created_at'] ?? '', 0, 10)) ?></td>
                    <td class="px-4 py-3 text-right"><a href="/billing/<?= (int) $inv['id'] ?>" class="text-emerald-600 hover:underline">Open</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
