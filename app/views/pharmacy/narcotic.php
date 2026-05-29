<div class="space-y-4">
    <div class="flex flex-wrap gap-2">
        <h2 class="text-lg font-semibold flex-1">Narcotic register (Schedule H / H1)</h2>
        <a href="/pharmacy/pos" class="ui-btn ui-btn-secondary ui-btn-sm">POS</a>
    </div>
    <div class="overflow-hidden ui-card">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Drug ID</th>
                    <th class="px-4 py-3">Qty</th>
                    <th class="px-4 py-3">Balance</th>
                    <th class="px-4 py-3">Schedule</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if ($entries === []): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No narcotic sales recorded</td></tr>
                <?php else: ?>
                <?php foreach ($entries as $e): ?>
                <tr>
                    <td class="px-4 py-3"><?= htmlspecialchars($e['recorded_at'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($e['patient_name'] ?? 'Walk-in') ?></td>
                    <td class="px-4 py-3"><?= (int) ($e['drug_id'] ?? 0) ?></td>
                    <td class="px-4 py-3"><?= (int) ($e['qty'] ?? 0) ?></td>
                    <td class="px-4 py-3"><?= (int) ($e['balance_after'] ?? 0) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($e['schedule'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
