<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Radiology orders</h2>
        <p class="text-sm text-slate-500"><?= (int) $total ?> total</p>
    </div>

    <form method="get" class="rounded-xl border bg-white p-4">
        <div class="flex flex-wrap gap-3">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                   placeholder="Search patient, UHID, body part…"
                   class="min-w-[220px] flex-1 rounded-lg border px-3 py-2 text-sm">
            <select name="modality" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">All modalities</option>
                <?php foreach (['xray','ct','mri','ultrasound','mammography','dexa','pet','other'] as $m): ?>
                <option value="<?= $m ?>" <?= $filters['modality'] === $m ? 'selected' : '' ?>><?= strtoupper($m) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="rounded-lg border px-2 py-2 text-sm">
                <option value="">All statuses</option>
                <?php foreach (['ordered','in_progress','reported','verified','shared'] as $s): ?>
                <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="rounded-lg border px-3 py-2 text-sm">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="rounded-lg border px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Filter</button>
            <a href="/radiology" class="rounded-lg border px-4 py-2 text-sm">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3">Ordered</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Modality</th>
                    <th class="px-4 py-3">Body part</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($rows as $row): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-xs text-slate-500"><?= htmlspecialchars(substr((string) ($row['ordered_at'] ?? ''), 0, 16)) ?></td>
                    <td class="px-4 py-3">
                        <div class="font-medium"><?= htmlspecialchars($row['patient_name'] ?? '') ?></div>
                        <div class="font-mono text-xs text-slate-500"><?= htmlspecialchars($row['uhid'] ?? '') ?></div>
                    </td>
                    <td class="px-4 py-3"><span class="rounded bg-slate-100 px-2 py-0.5 text-xs uppercase"><?= htmlspecialchars((string) ($row['modality'] ?? '')) ?></span></td>
                    <td class="px-4 py-3"><?= htmlspecialchars((string) ($row['body_part'] ?? '—')) ?></td>
                    <td class="px-4 py-3">
                        <?php
                            $status = (string) ($row['status'] ?? 'ordered');
                            $color = match ($status) {
                                'ordered' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'reported', 'verified', 'shared' => 'bg-emerald-100 text-emerald-800',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        ?>
                        <span class="rounded px-2 py-0.5 text-xs <?= $color ?>"><?= htmlspecialchars($status) ?></span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="/radiology/<?= (int) $row['id'] ?>" class="text-emerald-600 hover:underline">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($rows)): ?>
        <p class="p-8 text-center text-sm text-slate-500">No radiology orders yet.</p>
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
