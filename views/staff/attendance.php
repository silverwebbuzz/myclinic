<div class="space-y-4">
    <div class="flex flex-wrap gap-2">
        <h2 class="text-lg font-semibold flex-1">Staff attendance</h2>
        <a href="/staff/leaves" class="rounded-lg border px-3 py-2 text-sm">Leave requests</a>
    </div>
    <?php if (!empty($_GET['clocked_in'])): ?><p class="text-sm text-emerald-600">Clocked in.</p><?php endif; ?>
    <?php if (!empty($_GET['clocked_out'])): ?><p class="text-sm text-emerald-600">Clocked out.</p><?php endif; ?>

    <div class="rounded-xl border bg-white p-6 flex flex-wrap gap-4 items-center">
        <div class="text-sm">
            <p class="font-medium">Today</p>
            <?php if ($today): ?>
            <p class="text-slate-500">In: <?= htmlspecialchars($today['clock_in'] ?? '—') ?> · Out: <?= htmlspecialchars($today['clock_out'] ?? '—') ?></p>
            <?php else: ?>
            <p class="text-slate-500">Not clocked in</p>
            <?php endif; ?>
        </div>
        <form method="post" action="/staff/attendance/clock-in">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white" <?= !empty($today['clock_in']) ? 'disabled' : '' ?>>Clock in</button>
        </form>
        <form method="post" action="/staff/attendance/clock-out">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="rounded-lg border px-4 py-2 text-sm" <?= empty($today['clock_in']) || !empty($today['clock_out']) ? 'disabled' : '' ?>>Clock out</button>
        </form>
    </div>

    <form method="get" class="flex gap-2 text-sm">
        <select name="month" class="rounded border px-2 py-1">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
            <?php endfor; ?>
        </select>
        <input type="number" name="year" value="<?= $year ?>" class="w-20 rounded border px-2 py-1">
        <button type="submit" class="rounded bg-slate-800 px-3 py-1 text-white">View</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3 text-left">Staff</th><th>Date</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
            <tbody class="divide-y">
                <?php foreach ($report as $row): ?>
                <tr>
                    <td class="px-4 py-3"><?= htmlspecialchars($row['staff_name'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($row['date'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($row['clock_in'] ?? '—') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($row['clock_out'] ?? '—') ?></td>
                    <td class="px-4 py-3 capitalize"><?= htmlspecialchars($row['status'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
