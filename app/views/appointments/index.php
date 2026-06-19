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
<div class="space-y-4" x-data="quickAdd(<?= htmlspecialchars(json_encode([
    'doctorId' => $doctorId ?? 0,
    'date' => $date,
], JSON_THROW_ON_ERROR), ENT_QUOTES) ?>)">
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

            <button type="button" @click="open()" class="ui-btn ui-btn-primary">+ Book</button>
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
                    <button type="button" @click="open('<?= $colDate ?>')"
                            class="block w-full rounded border border-dashed border-slate-200 px-1.5 py-1 text-center text-[11px] text-slate-400 hover:border-brand hover:text-brand">
                        + Add
                    </button>
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
                <button type="button" @click="open('<?= htmlspecialchars($date) ?>', '<?= htmlspecialchars((string) $slot['time']) ?>')"
                        class="rounded-lg border px-2 py-1.5 text-xs font-medium hover:bg-emerald-50 <?= !empty($slot['extended']) ? 'border-red-300 bg-red-50 text-red-700' : 'border-emerald-300 bg-white text-emerald-800' ?>"
                        title="<?= !empty($slot['extended']) ? 'Extended hours — book' : 'Book this slot' ?>">
                    <?= $time12 ?>
                </button>
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
            <button type="button" @click="open()" class="mt-4 inline-block ui-btn ui-btn-primary">+ Book appointment</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ============ QUICK-ADD MODAL — book without leaving the calendar ============ -->
    <div x-show="isOpen" x-cloak
         @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:items-center">
        <div @click.outside="close()" class="my-4 w-full max-w-lg rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <h3 class="ui-section-title">Book appointment</h3>
                <a :href="'/appointments/new?date=' + form.date + (form.doctorId ? '&doctor_id=' + form.doctorId : '')"
                   class="text-xs text-slate-500 hover:text-brand hover:underline">Open full form →</a>
            </div>

            <form method="post" action="/appointments/new" class="space-y-3 px-4 py-4"
                  @submit="$event.target.querySelector('button[type=submit]').disabled = true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <input type="hidden" name="patient_id" :value="patientId">
                <input type="hidden" name="type" value="prebooked">

                <!-- Patient -->
                <div>
                    <label class="ui-label mb-1 block">Patient</label>
                    <div class="relative" x-show="patientId === 0">
                        <input type="search" x-model="patientQuery" @input.debounce.300ms="searchPatients()"
                               placeholder="Search name, phone, UHID…" class="ui-input">
                        <ul x-show="suggestions.length" x-cloak
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border bg-white shadow-lg">
                            <template x-for="p in suggestions" :key="p.id">
                                <li>
                                    <button type="button" @click="pickPatient(p)"
                                            class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-emerald-50">
                                        <span><span class="font-medium" x-text="p.name"></span> <span class="text-slate-500" x-text="p.phone"></span></span>
                                        <span class="font-mono text-xs text-slate-400" x-text="p.uhid"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <input type="text" name="new_patient_name" x-model="newName" placeholder="…or new patient name" class="ui-input">
                            <input type="tel" name="new_patient_phone" x-model="newPhone" placeholder="Phone" class="ui-input">
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">Pick an existing patient, or fill name + phone to register a new one.</p>
                    </div>
                    <div x-show="patientId > 0" x-cloak class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2 text-sm">
                        <span class="font-medium text-emerald-900" x-text="patientLabel"></span>
                        <button type="button" @click="clearPatient()" class="text-xs text-emerald-700 underline">Change</button>
                    </div>
                </div>

                <!-- Doctor + date -->
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="ui-label mb-1 block">Doctor</span>
                        <select name="doctor_id" x-model="form.doctorId" @change="loadSlots()" required class="ui-input">
                            <option value="">Select doctor</option>
                            <?php foreach ($doctors as $doc): ?>
                            <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="ui-label mb-1 block">Date</span>
                        <input type="date" name="scheduled_date" x-model="form.date" @change="loadSlots()" required
                               min="<?= htmlspecialchars((new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d')) ?>"
                               class="ui-input">
                    </label>
                </div>

                <!-- Slots -->
                <div>
                    <div class="flex items-center justify-between">
                        <span class="ui-label">Time slot</span>
                        <span x-show="slotsLoading" class="text-xs text-slate-400">Loading…</span>
                    </div>
                    <input type="hidden" name="scheduled_time" :value="form.time">
                    <p x-show="!form.doctorId" class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Select a doctor to load slots.</p>
                    <p x-show="form.doctorId && !slotsLoading && slots.length === 0" x-cloak
                       class="mt-1 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        No working hours for this day — enter a time manually:
                        <input type="time" x-model="form.time" class="ui-input mt-1 w-32">
                    </p>
                    <div x-show="slots.length" x-cloak class="mt-2 grid max-h-40 grid-cols-4 gap-1.5 overflow-y-auto sm:grid-cols-5">
                        <template x-for="s in slots" :key="s.datetime">
                            <button type="button" :disabled="!s.available" @click="form.time = s.time"
                                    :title="s.blocked ? 'Doctor on leave' : (s.extended ? 'Extended hours' : '')"
                                    :class="form.time === s.time ? 'bg-emerald-600 text-white'
                                        : (s.blocked ? 'bg-amber-100 text-amber-800 cursor-not-allowed'
                                            : (!s.available ? 'bg-slate-100 text-slate-400 line-through cursor-not-allowed'
                                                : (s.extended ? 'border border-red-300 bg-red-50 text-red-700' : 'border bg-white hover:bg-emerald-50')))"
                                    class="rounded px-1.5 py-1.5 text-xs font-medium"
                                    x-text="s.label"></button>
                        </template>
                    </div>
                </div>

                <label class="block text-sm">
                    <span class="ui-label mb-1 block">Chief complaint <span class="font-normal text-slate-400">(optional)</span></span>
                    <input type="text" name="chief_complaint" placeholder="Reason for visit" class="ui-input">
                </label>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
                    <button type="button" @click="close()" class="ui-btn ui-btn-secondary">Cancel</button>
                    <button type="submit" class="ui-btn ui-btn-primary" :disabled="!form.time || (!patientId && (!newName || !newPhone))">
                        Book appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function quickAdd(cfg) {
    return {
        isOpen: false,
        patientId: 0,
        patientLabel: '',
        patientQuery: '',
        suggestions: [],
        newName: '',
        newPhone: '',
        slots: [],
        slotsLoading: false,
        form: {
            doctorId: cfg.doctorId ? String(cfg.doctorId) : '',
            date: cfg.date || '',
            time: '',
        },
        open(date, time) {
            if (date) this.form.date = date;
            if (time) this.form.time = time;
            this.isOpen = true;
            if (this.form.doctorId) this.loadSlots();
        },
        close() { this.isOpen = false; },
        async searchPatients() {
            if (this.patientQuery.length < 2) { this.suggestions = []; return; }
            try {
                const r = await fetch('/api/v1/patients/search?q=' + encodeURIComponent(this.patientQuery), { credentials: 'same-origin' });
                if (!r.ok) { this.suggestions = []; return; }
                const data = await r.json();
                this.suggestions = (data.rows || []).slice(0, 8);
            } catch (e) { this.suggestions = []; }
        },
        pickPatient(p) {
            this.patientId = p.id;
            this.patientLabel = p.name + ' · ' + (p.uhid || '');
            this.patientQuery = '';
            this.suggestions = [];
            this.newName = '';
            this.newPhone = '';
        },
        clearPatient() {
            this.patientId = 0;
            this.patientLabel = '';
        },
        async loadSlots() {
            if (!this.form.doctorId || !this.form.date) { this.slots = []; return; }
            this.slotsLoading = true;
            try {
                const r = await fetch('/api/v1/slots?doctor_id=' + this.form.doctorId + '&date=' + this.form.date, { credentials: 'same-origin' });
                const data = r.ok ? await r.json() : { slots: [] };
                this.slots = (data.slots || []).map(s => ({ ...s, label: this._fmt(s.time) }));
                // Keep a preselected slot only if it's still bookable.
                if (this.form.time && !this.slots.some(s => s.time === this.form.time && s.available) && this.slots.length) {
                    this.form.time = '';
                }
            } catch (e) {
                this.slots = [];
            } finally {
                this.slotsLoading = false;
            }
        },
        _fmt(hhmm) {
            const [h, m] = hhmm.split(':').map(n => parseInt(n, 10));
            const period = h >= 12 ? 'PM' : 'AM';
            const h12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
            return h12 + ':' + String(m).padStart(2, '0') + ' ' + period;
        },
    };
}
</script>
