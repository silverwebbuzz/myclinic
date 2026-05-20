<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Visits</h2>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Doctor</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <?php if ($visits === []): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No visits yet.</td></tr>
                <?php else: ?>
                <?php foreach ($visits as $v): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><?= htmlspecialchars(date('d M Y H:i', strtotime($v['visited_at']))) ?></td>
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($v['patient_name'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($v['doctor_name'] ?? '') ?></td>
                    <td class="px-4 py-3 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $v['status'] ?? 'in_progress')) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="/visits/<?= (int) $v['id'] ?>" class="text-emerald-600 hover:underline">Open</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
