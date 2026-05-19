<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Prescriptions</h2>
        <p class="text-sm text-slate-500"><?= (int) $total ?> total</p>
    </div>

    <form method="get" class="rounded-xl border bg-white p-4">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                   placeholder="Search patient, UHID, drug, remedy…"
                   class="min-w-[220px] flex-1 rounded-lg border px-3 py-2 text-sm">
            <select name="mode" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">All modes</option>
                <option value="allopathic" <?= $filters['mode'] === 'allopathic' ? 'selected' : '' ?>>Allopathic</option>
                <option value="homeopathic" <?= $filters['mode'] === 'homeopathic' ? 'selected' : '' ?>>Homeopathic</option>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="rounded-lg border px-3 py-2 text-sm">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="rounded-lg border px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Filter</button>
            <a href="/prescriptions" class="rounded-lg border px-4 py-2 text-sm">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Dosage</th>
                    <th class="px-4 py-3">Freq</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($rows as $row): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs text-slate-500">
                        <?= htmlspecialchars(substr((string) ($row['visited_at'] ?? ''), 0, 10)) ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium"><?= htmlspecialchars($row['patient_name'] ?? '') ?></div>
                        <div class="font-mono text-xs text-slate-500"><?= htmlspecialchars($row['uhid'] ?? '') ?></div>
                    </td>
                    <td class="px-4 py-3">
                        <?php $name = $row['drug_name'] ?? $row['remedy_name'] ?? '—'; ?>
                        <div class="font-medium"><?= htmlspecialchars((string) $name) ?></div>
                        <?php if (!empty($row['potency'])): ?>
                            <div class="text-xs text-slate-500">Potency: <?= htmlspecialchars((string) $row['potency']) ?></div>
                        <?php endif; ?>
                        <span class="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs">
                            <?= htmlspecialchars((string) ($row['mode'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs"><?= htmlspecialchars((string) ($row['dosage'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-xs"><?= htmlspecialchars((string) ($row['frequency'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-xs">
                        <?= !empty($row['duration_days']) ? ((int) $row['duration_days']) . ' days' : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="/visits/<?= (int) ($row['visit_id'] ?? 0) ?>" class="text-emerald-600 hover:underline">Visit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($rows)): ?>
        <p class="p-8 text-center text-sm text-slate-500">No prescriptions found.</p>
        <?php endif; ?>
    </div>

    <?php
    $totalPages = (int) ceil(max(1, $total) / max(1, $perPage));
    if ($totalPages > 1):
    ?>
    <div class="flex justify-center gap-2 text-sm">
        <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
        <a href="?page=<?= $p ?>&<?= http_build_query(array_filter($filters)) ?>"
           class="rounded px-2 py-1 <?= $p === $page ? 'bg-emerald-100 text-emerald-800' : 'border' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
