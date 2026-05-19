<div class="space-y-4">
    <div class="flex flex-wrap gap-2">
        <h2 class="text-lg font-semibold flex-1">Staff leave requests</h2>
        <a href="/staff/attendance" class="rounded-lg border px-3 py-2 text-sm">Attendance</a>
    </div>

    <form method="post" action="/staff/leaves" class="rounded-xl border bg-white p-4 grid gap-3 sm:grid-cols-2 text-sm max-w-2xl">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <h3 class="sm:col-span-2 font-medium">Request leave</h3>
        <select name="leave_type" class="rounded-lg border px-3 py-2">
            <?php foreach (['CL','SL','EL','LWP','other'] as $t): ?>
            <option value="<?= $t ?>"><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <input name="from_date" type="date" required class="rounded-lg border px-3 py-2">
        <input name="to_date" type="date" required class="rounded-lg border px-3 py-2">
        <textarea name="reason" rows="2" placeholder="Reason" class="sm:col-span-2 rounded-lg border px-3 py-2"></textarea>
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-white sm:col-span-2 sm:w-auto">Submit</button>
    </form>

    <div class="overflow-hidden rounded-xl border bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3 text-left">Staff</th><th>Type</th><th>Dates</th><th>Status</th><th></th></tr></thead>
            <tbody class="divide-y">
                <?php foreach ($leaves as $lv): ?>
                <tr>
                    <td class="px-4 py-3"><?= htmlspecialchars($lv['staff_name'] ?? '') ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($lv['leave_type'] ?? '') ?></td>
                    <td class="px-4 py-3 text-xs"><?= htmlspecialchars($lv['from_date'] ?? '') ?> → <?= htmlspecialchars($lv['to_date'] ?? '') ?></td>
                    <td class="px-4 py-3 capitalize"><?= htmlspecialchars($lv['status'] ?? '') ?></td>
                    <td class="px-4 py-3 text-right">
                        <?php if (($lv['status'] ?? '') === 'pending'): ?>
                        <form method="post" action="/staff/leaves/<?= (int)$lv['id'] ?>/approve" class="inline"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><button class="text-xs text-emerald-600">Approve</button></form>
                        <form method="post" action="/staff/leaves/<?= (int)$lv['id'] ?>/reject" class="inline ml-2"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><button class="text-xs text-red-600">Reject</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
