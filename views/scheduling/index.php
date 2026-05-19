<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Advanced scheduling</h2>
        <a href="/book/<?= htmlspecialchars($clinic['slug'] ?? 'demo') ?>" target="_blank" class="rounded-lg border px-3 py-2 text-sm">Public booking →</a>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
        <?= htmlspecialchars($googleCalendar['message'] ?? '') ?>
    </div>

    <form method="post" action="/scheduling/sync-hours" class="inline">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="rounded-lg border px-3 py-2 text-sm">Sync from clinic working hours</button>
    </form>

    <form method="get" class="flex gap-2 text-sm">
        <select name="doctor_id" class="rounded-lg border px-3 py-2" onchange="this.form.submit()">
            <?php foreach ($doctors as $d): ?>
            <option value="<?= (int)$d['id'] ?>" <?= $doctorId===(int)$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <form method="post" action="/scheduling/schedules" class="rounded-xl border bg-white p-4 grid gap-3 sm:grid-cols-3 text-sm max-w-3xl">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="doctor_id" value="<?= $doctorId ?>">
        <select name="day_of_week" class="rounded border px-2 py-1">
            <?php $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; foreach ($days as $i=>$label): ?>
            <option value="<?= $i ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <input name="start_time" type="time" value="09:00" class="rounded border px-2 py-1">
        <input name="end_time" type="time" value="18:00" class="rounded border px-2 py-1">
        <input name="slot_duration" type="number" value="15" placeholder="Slot min" class="rounded border px-2 py-1">
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-white sm:col-span-3 sm:w-auto">Add schedule block</button>
    </form>

    <div class="rounded-xl border bg-white overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">Day</th><th>Start</th><th>End</th><th>Slot</th></tr></thead>
            <tbody class="divide-y">
                <?php $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; foreach ($schedules as $s): ?>
                <tr>
                    <td class="px-4 py-3"><?= $days[(int)($s['day_of_week']??0)] ?? '' ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars(substr($s['start_time']??'',0,5)) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars(substr($s['end_time']??'',0,5)) ?></td>
                    <td class="px-4 py-3"><?= (int)($s['slot_duration']??15) ?> min</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="rounded-xl border bg-white p-4">
        <h3 class="text-sm font-semibold">Waiting list</h3>
        <ul class="mt-2 divide-y text-sm">
            <?php if ($waitingList===[]): ?><li class="py-2 text-slate-500">Empty</li><?php endif; ?>
            <?php foreach ($waitingList as $w): ?>
            <li class="py-2 flex justify-between"><span><?= htmlspecialchars($w['patient_name']??'') ?> · <?= htmlspecialchars($w['doctor_name']??'') ?></span><span class="text-xs text-slate-500"><?= htmlspecialchars($w['preferred_date']??'') ?></span></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
