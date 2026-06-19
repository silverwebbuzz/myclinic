<?php
$statusFilter = $statusFilter ?? 'all';
$view = $view ?? 'day';
$displayDate = date('d M Y', strtotime($date));
$isToday = $date === date('Y-m-d');
$qs = static function (array $extra) use ($date, $doctorId, $statusFilter, $view): string {
    return http_build_query(array_filter(array_merge([
        'date' => $date,
        'doctor_id' => $doctorId,
        'status' => $statusFilter,
        'view' => $view === 'day' ? null : $view,
    ], $extra), static fn ($v) => $v !== null && $v !== ''));
};
$statusBadge = static fn (string $status): string => match ($status) {
    'scheduled' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'in_progress' => 'bg-indigo-100 text-indigo-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    'no_show' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-slate-200 text-slate-600 line-through',
    default => 'bg-slate-100 text-slate-700',
};
?>
<?php
// Build a prefill URL for the single booking form (/appointments/new). Every
// "Book" trigger on this page links here so there's one booking code path.
$bookUrl = static function (?string $d = null, ?string $time = null) use ($date, $doctorId): string {
    $params = array_filter([
        'date' => $d ?? $date,
        'doctor_id' => $doctorId,
        'time' => $time,
    ], static fn ($v) => $v !== null && $v !== '');
    return '/appointments/new' . ($params ? '?' . http_build_query($params) : '');
};
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="flex items-center gap-2 ui-page-title">
                <span class="text-brand"><?= ui_icon('appointments', 20) ?></span> Appointments
            </h2>
            <p class="text-xs text-slate-500">
                <?= $view === 'week'
                    ? htmlspecialchars(date('d M', strtotime($weekStart)) . ' – ' . date('d M Y', strtotime($weekStart . ' +6 days')))
                    : htmlspecialchars($displayDate) ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Day / Week view toggle -->
            <div class="flex overflow-hidden rounded-lg border border-slate-200 text-sm font-medium">
                <a href="?<?= htmlspecialchars($qs(['view' => null])) ?>"
                   class="px-3 py-2 <?= $view === 'day' ? 'bg-brand text-white' : 'bg-white text-slate-600 hover:bg-slate-50' ?>">Day</a>
                <a href="?<?= htmlspecialchars($qs(['view' => 'week'])) ?>"
                   class="px-3 py-2 <?= $view === 'week' ? 'bg-brand text-white' : 'bg-white text-slate-600 hover:bg-slate-50' ?>">Week</a>
            </div>

            <a href="?<?= htmlspecialchars($qs(['date' => date('Y-m-d')])) ?>"
               class="rounded-lg px-3 py-2 text-sm font-medium <?= $isToday && $view === 'day' ? 'bg-emerald-600 text-white' : 'border hover:bg-slate-50' ?>">
                Today
            </a>

            <div class="flex items-center gap-1">
                <a href="?<?= htmlspecialchars($qs(['date' => $prevDate])) ?>"
                   class="ui-btn ui-btn-secondary ui-btn-sm" aria-label="Previous <?= $view ?>">‹</a>
                <form method="get" class="contents">
                    <?php foreach (['doctor_id' => $doctorId, 'status' => $statusFilter, 'view' => $view === 'day' ? null : $view] as $k => $v): ?>
                        <?php if ($v !== null && $v !== ''): ?>
                            <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars((string) $v) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"
                           onchange="this.form.submit()"
                           class="ui-input">
                </form>
                <a href="?<?= htmlspecialchars($qs(['date' => $nextDate])) ?>"
                   class="ui-btn ui-btn-secondary ui-btn-sm" aria-label="Next <?= $view ?>">›</a>
            </div>

            <a href="<?= htmlspecialchars($bookUrl()) ?>" class="ui-btn ui-btn-primary">+ Book</a>
        </div>
    </div>

    <?php if (!empty($_GET['updated'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Appointment updated.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['cancelled'])): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Appointment cancelled.</p>
    <?php endif; ?>

    <?php
    $selectedDoctorName = null;
    if ($doctorId !== null) {
        foreach ($doctors as $doc) {
            if ((int) $doc['id'] === (int) $doctorId) {
                $selectedDoctorName = (string) $doc['name'];
                break;
            }
        }
    }
    ?>
    <div class="flex flex-wrap items-center gap-3 rounded-xl border bg-white p-3">
        <form method="get" class="contents">
            <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <?php if ($view !== 'day'): ?><input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>"><?php endif; ?>
            <label class="flex items-center gap-2 text-sm">
                <span class="font-medium text-slate-600">Doctor:</span>
                <select name="doctor_id" onchange="this.form.submit()"
                        class="ui-input <?= $doctorId !== null ? 'border-emerald-400 bg-emerald-50 font-medium text-emerald-800' : '' ?>">
                    <option value="">All doctors (<?= count($doctors) ?>)</option>
                    <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>" <?= $doctorId === (int) $doc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($doc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>
        <?php if ($selectedDoctorName !== null): ?>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800">
                Filtered: <?= htmlspecialchars($selectedDoctorName) ?>
                <a href="?<?= htmlspecialchars($qs(['doctor_id' => null])) ?>"
                   class="ml-1 rounded-full hover:bg-emerald-200 px-1" title="Clear doctor filter">✕</a>
            </span>
        <?php endif; ?>
        <div class="ml-auto flex gap-2">
            <a href="/queue" class="ui-btn ui-btn-secondary ui-btn-sm">Today's queue</a>
            <a href="/queue/display?clinic=<?= urlencode($clinicSlug ?? '') ?>" target="_blank"
               class="ui-btn ui-btn-secondary ui-btn-sm">Display screen</a>
        </div>
    </div>

    <?php if ($view === 'week'): ?>
    <!-- ============ WEEK VIEW — Mon–Sun agenda columns ============ -->
    <div class="overflow-x-auto ui-card">
        <div class="grid min-w-[980px] grid-cols-7 divide-x divide-slate-100">
            <?php for ($d = 0; $d < 7; $d++):
                $colDate = date('Y-m-d', strtotime($weekStart . " +{$d} days"));
                $colIsToday = $colDate === date('Y-m-d');
                $dayAppts = $weekAppointments[$colDate] ?? [];
            ?>
            <div class="min-h-[220px]">
                <a href="?<?= htmlspecialchars($qs(['date' => $colDate, 'view' => null])) ?>"
                   class="block border-b px-2 py-2 text-center text-xs font-semibold <?= $colIsToday ? 'bg-brand-light text-brand' : 'bg-slate-50 text-slate-600 hover:bg-slate-100' ?>">
                    <?= date('D', strtotime($colDate)) ?> <span class="font-normal"><?= date('d M', strtotime($colDate)) ?></span>
                    <span class="block font-normal text-slate-400"><?= count($dayAppts) ?> appt<?= count($dayAppts) === 1 ? '' : 's' ?></span>
                </a>
                <div class="space-y-1 p-1.5">
                    <?php foreach ($dayAppts as $a): ?>
                    <a href="/appointments/<?= (int) $a['id'] ?>/edit"
                       class="block rounded px-1.5 py-1 text-xs leading-tight hover:opacity-80 <?= $statusBadge((string) ($a['status'] ?? '')) ?>"
                       title="<?= htmlspecialchars(($a['patient_name'] ?? '') . ' · ' . ($a['doctor_name'] ?? '') . ' · ' . str_replace('_', ' ', (string) ($a['status'] ?? ''))) ?>">
                        <span class="font-mono"><?= date('H:i', strtotime((string) $a['scheduled_at'])) ?></span>
                        <span class="font-medium"><?= htmlspecialchars((string) ($a['patient_name'] ?? '')) ?></span>
                        <?php if ($doctorId === null): ?>
                        <span class="block truncate text-[10px] opacity-70"><?= htmlspecialchars((string) ($a['doctor_name'] ?? '')) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <a href="<?= htmlspecialchars($bookUrl($colDate)) ?>"
                       class="block w-full rounded border border-dashed border-slate-200 px-1.5 py-1 text-center text-[11px] text-slate-400 hover:border-brand hover:text-brand">
                        + Add
                    </a>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <?php else: ?>

    <?php if ($doctorId !== null): ?>
    <!-- ============ SLOT TIMELINE — available / booked / blocked at a glance ============ -->
    <div class="ui-card p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm font-medium text-slate-700">Slots — <?= htmlspecialchars($selectedDoctorName ?? '') ?>, <?= htmlspecialchars($displayDate) ?></p>
            <div class="flex flex-wrap gap-3 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-emerald-400 bg-white"></span>Available</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-slate-300"></span>Booked</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-amber-300"></span>On leave</span>
                <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded border border-red-300 bg-red-50"></span>Extended</span>
            </div>
        </div>
        <?php if ($daySlots === []): ?>
        <p class="mt-3 text-xs text-slate-500">
            No working hours configured for this day.
            <a href="/scheduling" class="text-brand hover:underline">Set up the schedule →</a>
        </p>
        <?php else: ?>
        <div class="mt-3 flex flex-wrap gap-1.5">
            <?php foreach ($daySlots as $slot):
                $time12 = date('g:i A', strtotime((string) $slot['datetime']));
            ?>
                <?php if (!empty($slot['blocked'])): ?>
                <span class="cursor-not-allowed rounded-lg bg-amber-100 px-2 py-1.5 text-xs text-amber-800" title="Doctor on leave"><?= $time12 ?></span>
                <?php elseif (empty($slot['available'])): ?>
                <span class="cursor-not-allowed rounded-lg bg-slate-200 px-2 py-1.5 text-xs text-slate-500 line-through" title="Booked"><?= $time12 ?></span>
                <?php else: ?>
                <a href="<?= htmlspecialchars($bookUrl($date, (string) $slot['time'])) ?>"
                   class="rounded-lg border px-2 py-1.5 text-xs font-medium hover:bg-emerald-50 <?= !empty($slot['extended']) ? 'border-red-300 bg-red-50 text-red-700' : 'border-emerald-300 bg-white text-emerald-800' ?>"
                   title="<?= !empty($slot['extended']) ? 'Extended hours — book' : 'Book this slot' ?>">
                    <?= $time12 ?>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <p class="rounded-xl border border-dashed border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
        Select a doctor above to see the slot timeline (available / booked / on-leave).
    </p>
    <?php endif; ?>

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

    <div class="overflow-hidden ui-card">
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
                            <span class="rounded px-2 py-0.5 text-xs font-medium <?= $statusBadge($status) ?>">
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
            <p class="mb-3 flex justify-center text-slate-300"><?= ui_icon('appointments', 40) ?></p>
            <p class="text-sm font-medium text-slate-700">No appointments<?= $statusFilter !== 'all' ? ' in this status' : '' ?> on <?= htmlspecialchars($displayDate) ?></p>
            <p class="mt-1 text-xs text-slate-500">Try another date or status, or book a new appointment.</p>
            <a href="<?= htmlspecialchars($bookUrl()) ?>" class="mt-4 inline-block ui-btn ui-btn-primary">+ Book appointment</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
