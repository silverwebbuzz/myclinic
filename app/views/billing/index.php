<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Billing</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/billing/export/excel" class="ui-btn ui-btn-secondary ui-btn-sm">Export Excel</a>
            <a href="/billing/export/tally" class="ui-btn ui-btn-secondary ui-btn-sm">Tally XML</a>
        </div>
    </div>

    <form method="get" class="flex flex-wrap gap-2 ui-card p-4">
        <input type="search" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Search invoice, patient…" class="min-w-[200px] flex-1 ui-input">
        <select name="status" class="ui-input">
            <option value="">All statuses</option>
            <?php foreach (['draft','sent','partial','paid','overdue'] as $st): ?>
            <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="ui-btn ui-btn-primary">Filter</button>
    </form>

    <div class="overflow-hidden ui-card">
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
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <a href="/billing/<?= (int) $inv['id'] ?>" class="font-medium text-brand hover:underline">Open</a>
                            <a href="/billing/<?= (int) $inv['id'] ?>/pdf" class="text-slate-400 hover:text-slate-700" title="Download PDF"><?= ui_icon('emr', 16) ?></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
