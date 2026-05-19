<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Lab catalog</h2>
        <a href="/lab/orders" class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">Pending orders →</a>
    </div>
    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Test</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Parameters</th></tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($tests as $t): ?>
                <tr>
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars($t['test_code'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($t['test_name']) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($t['category'] ?? '') ?></td>
                    <td class="px-4 py-3 text-xs text-slate-500"><?= count($t['parameters'] ?? []) ?> params</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
