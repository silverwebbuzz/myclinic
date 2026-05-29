<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="ui-section-title">Lab orders</h2>
        <a href="/lab/catalog" class="ui-btn ui-btn-secondary ui-btn-sm">Catalog</a>
    </div>
    <div class="overflow-hidden ui-card">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr><th class="px-4 py-3">Patient</th><th class="px-4 py-3">Test</th><th class="px-4 py-3">Barcode</th><th class="px-4 py-3">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y">
                <?php if ($orders === []): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No pending orders</td></tr>
                <?php else: ?>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td class="px-4 py-3"><?= htmlspecialchars($o['patient_name'] ?? '') ?> <span class="text-xs text-slate-400"><?= htmlspecialchars($o['uhid'] ?? '') ?></span></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($o['test_name'] ?? '') ?></td>
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($o['barcode'] ?? '') ?></td>
                    <td class="px-4 py-3 capitalize"><?= htmlspecialchars($o['status'] ?? '') ?></td>
                    <td class="px-4 py-3"><a href="/lab/orders/<?= (int) $o['id'] ?>" class="text-emerald-600 hover:underline">Work →</a></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
