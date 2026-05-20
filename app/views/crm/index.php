<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">CRM &amp; Leads</h2>
        <a href="/crm/new" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Add lead</a>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="/crm" class="rounded-full px-3 py-1 text-xs <?= ($status ?? '') === '' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100' ?>">All (<?= array_sum($counts) ?>)</a>
        <?php foreach (\App\Services\CrmLeadService::STATUSES as $st): ?>
        <a href="/crm?status=<?= urlencode($st) ?>"
           class="rounded-full px-3 py-1 text-xs <?= ($status ?? '') === $st ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
            <?= ucfirst($st) ?> (<?= (int) ($counts[$st] ?? 0) ?>)
        </a>
        <?php endforeach; ?>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-hidden rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                    <th class="px-4 py-3 text-left">Name</th><th>Phone</th><th>Source</th><th>Status</th><th></th>
                </tr></thead>
                <tbody class="divide-y">
                    <?php if ($leads === []): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No leads</td></tr>
                    <?php else: ?>
                    <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($lead['name']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($lead['phone'] ?? '') ?></td>
                        <td class="px-4 py-3 text-xs"><?= htmlspecialchars($lead['source'] ?? '') ?></td>
                        <td class="px-4 py-3 capitalize text-xs"><?= htmlspecialchars($lead['status'] ?? '') ?></td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="/crm/<?= (int) $lead['id'] ?>/edit" class="text-emerald-600 text-xs">Edit</a>
                            <?php if (($lead['status'] ?? '') !== 'converted'): ?>
                            <form method="post" action="/crm/<?= (int) $lead['id'] ?>/convert" class="inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <button type="submit" class="text-xs text-blue-600">Convert</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="rounded-xl border bg-white p-4">
            <h3 class="text-sm font-medium">Leads by source</h3>
            <canvas id="source-chart" height="180"></canvas>
        </div>
    </div>
</div>
<script>
const sc = <?= json_encode($sourceChart) ?>;
if (sc.labels.length) {
    new Chart(document.getElementById('source-chart'), {
        type: 'doughnut',
        data: { labels: sc.labels, datasets: [{ data: sc.values, backgroundColor: ['#0F9B6E','#3b82f6','#f59e0b','#ef4444','#8b5cf6','#64748b'] }] },
    });
}
</script>
