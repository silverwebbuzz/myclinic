<?php
$statusFilter = $statusFilter ?? 'all';
$displayDate = date('d M Y', strtotime($date));
$isToday = $date === date('Y-m-d');
$qs = static function (array $extra) use ($date, $doctorId, $statusFilter): string {
    return http_build_query(array_filter(array_merge([
        'date' => $date,
        'doctor_id' => $doctorId,
        'status' => $statusFilter,
    ], $extra), static fn ($v) => $v !== null && $v !== ''));
};
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 text-lg font-semibold">
                <span>📅</span> Appointments
            </h2>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($displayDate) ?></p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="?<?= htmlspecialchars($qs(['date' => date('Y-m-d')])) ?>"
               class="rounded-lg px-3 py-2 text-sm font-medium <?= $isToday ? 'bg-emerald-600 text-white' : 'border hover:bg-slate-50' ?>">
                Today
            </a>
            <a href="?<?= htmlspecialchars($qs(['date' => date('Y-m-d', strtotime('monday this week'))])) ?>"
               class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">This week</a>
            <a href="?<?= htmlspecialchars($qs(['date' => date('Y-m-01')])) ?>"
               class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">This month</a>

            <div class="flex items-center gap-1">
                <a href="?<?= htmlspecialchars($qs(['date' => $prevDate])) ?>"
                   class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50" aria-label="Previous day">‹</a>
                <form method="get" class="contents">
                    <?php foreach (['doctor_id' => $doctorId, 'status' => $statusFilter] as $k => $v): ?>
                        <?php if ($v !== null && $v !== ''): ?>
                            <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) $v) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"
                           onchange="this.form.submit()"
                           class="rounded-lg border px-3 py-2 text-sm">
                </form>
                <a href="?<?= htmlspecialchars($qs(['date' => $nextDate])) ?>"
                   class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50" aria-label="Next day">›</a>
            </div>

            <a href="/appointments/new?date=<?= urlencode($date) ?>"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">+ Book</a>
        </div>
    </div>

    <?php if (!empty($_GET['updated'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Appointment updated.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['cancelled'])): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Appointment cancelled.</p>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3 rounded-xl border bg-white p-3">
        <form method="get" class="contents">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <select name="doctor_id" onchange="this.form.submit()" class="rounded-lg border px-3 py-2 text-sm">
                <option value="">All doctors</option>
                <?php foreach ($doctors as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>" <?= $doctorId === (int) $doc['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($doc['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="/queue" class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">Today's queue</a>
        <a href="/queue/display?clinic=<?= urlencode($clinicSlug ?? '') ?>" target="_blank"
           class="rounded-lg border px-3 py-2 text-sm hover:bg-slate-50">Display screen</a>
    </div>

    <?php
    $cards = [
        ['key' => 'all', 'label' => 'Total', 'color' => 'border-slate-300', 'text' => 'text-slate-800'],
        ['key' => 'scheduled', 'label' => 'Waiting', 'color' => 'border-amber-400', 'text' => 'text-amber-600'],
        ['key' => 'confirmed', 'label' => 'Confirmed', 'color' => 'border-blue-400', 'text' => 'text-blue-600'],
        ['key' => 'in_progress', 'label' => 'In Consult', 'color' => 'border-indigo-400', 'text' => 'text-indigo-600'],
        ['key' => 'completed', 'label' => 'Completed', 'color' => 'border-emerald-400', 'text' => 'text-emerald-600'],
    ];
    ?>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <?php foreach ($cards as $card): ?>
        <a href="?<?= htmlspecialchars($qs(['status' => $card['key']])) ?>"
           class="rounded-xl border-2 bg-white p-4 transition hover:shadow-sm <?= $card['color'] ?> <?= $statusFilter === $card['key'] ? 'ring-2 ring-offset-1 ring-emerald-500' : '' ?>">
            <p class="text-2xl font-bold <?= $card['text'] ?>"><?= (int) ($counts[$card['key']] ?? 0) ?></p>
            <p class="text-xs uppercase tracking-wide text-slate-500"><?= htmlspecialchars($card['label']) ?></p>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white">
        <div class="flex flex-wrap gap-1 border-b px-2 py-2 text-sm">
            <?php
            $tabs = [
                'all' => 'All',
                'scheduled' => 'Waiting',
                'confirmed' => 'Confirmed',
                'in_progress' => 'In Consult',
                'completed' => 'Completed',
                'no_show' => 'Not Arrived',
                'cancelled' => 'Cancelled',
            ];
            foreach ($tabs as $key => $label):
                $active = $statusFilter === $key;
            ?>
            <a href="?<?= htmlspecialchars($qs(['status' => $key])) ?>"
               class="rounded-lg px-3 py-1.5 <?= $active ? 'bg-emerald-50 font-medium text-emerald-700' : 'text-slate-600 hover:bg-slate-50' ?>">
                <?= htmlspecialchars($label) ?>
                <span class="ml-1 text-xs text-slate-400">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Phone</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Slot</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Complaint</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($appointments as $i => $a): ?>
                    <?php
                        $status = (string) ($a['status'] ?? 'scheduled');
                        $badge = match ($status) {
                            'scheduled' => 'bg-amber-100 text-amber-800',
                            'confirmed' => 'bg-blue-100 text-blue-800',
                            'in_progress' => 'bg-indigo-100 text-indigo-800',
                            'completed' => 'bg-emerald-100 text-emerald-800',
                            'no_show' => 'bg-red-100 text-red-800',
                            'cancelled' => 'bg-slate-200 text-slate-600',
                            default => 'bg-slate-100 text-slate-700',
                        };
                        $type = (string) ($a['type'] ?? 'prebooked');
                        $typeBadge = match ($type) {
                            'walkin' => 'bg-slate-100 text-slate-700',
                            'online' => 'bg-cyan-100 text-cyan-800',
                            'followup' => 'bg-purple-100 text-purple-800',
                            default => 'bg-indigo-50 text-indigo-700',
                        };
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">
                                <?= $i + 1 ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/patients/<?= (int) $a['patient_id'] ?>" class="font-medium text-emerald-700 hover:underline">
                                <?= htmlspecialchars((string) ($a['patient_name'] ?? '')) ?>
                            </a>
                            <div class="font-mono text-xs text-slate-500"><?= htmlspecialchars((string) ($a['uhid'] ?? '')) ?></div>
                        </td>
                        <td class="px-4 py-3 text-xs"><?= htmlspecialchars((string) ($a['patient_phone'] ?? '—')) ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs <?= $typeBadge ?>">
                                <?= $type === 'walkin' ? 'Walk-in' : ($type === 'online' ? 'Online' : ($type === 'followup' ? 'Follow-up' : 'Booked')) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium">
                            <?= htmlspecialchars(date('h:i A', strtotime((string) $a['scheduled_at']))) ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600"><?= htmlspecialchars((string) ($a['doctor_name'] ?? '')) ?></td>
                        <td class="px-4 py-3 text-xs text-slate-600 max-w-[200px] truncate" title="<?= htmlspecialchars((string) ($a['chief_complaint'] ?? '')) ?>">
                            <?= htmlspecialchars((string) ($a['chief_complaint'] ?? '—')) ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs font-medium <?= $badge ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $status)) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <a href="/appointments/<?= (int) $a['id'] ?>/edit"
                                   class="rounded border px-2 py-1 text-xs hover:bg-slate-50">Edit</a>
                                <?php if ($status !== 'cancelled' && $status !== 'completed'): ?>
                                <form method="post" action="/appointments/<?= (int) $a['id'] ?>/cancel" class="inline"
                                      onsubmit="return confirm('Cancel this appointment?')">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                    <button type="submit" class="rounded border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">✕</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($appointments)): ?>
        <div class="p-12 text-center">
            <p class="text-4xl mb-2">📭</p>
            <p class="text-sm font-medium text-slate-700">No appointments<?= $statusFilter !== 'all' ? ' in this status' : '' ?> on <?= htmlspecialchars($displayDate) ?></p>
            <p class="mt-1 text-xs text-slate-500">Try another date or status, or book a new appointment.</p>
            <a href="/appointments/new?date=<?= urlencode($date) ?>"
               class="mt-4 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">+ Book appointment</a>
        </div>
        <?php endif; ?>
    </div>
</div>
