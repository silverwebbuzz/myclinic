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
// Walk-in: same booking form, opened in walk-in mode (no slot — queue token).
$walkinUrl = static function () use ($date, $doctorId): string {
    return '/appointments/new?' . http_build_query(array_filter([
        'type' => 'walkin',
        'date' => $date,
        'doctor_id' => $doctorId,
    ], static fn ($v) => $v !== null && $v !== ''));
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

            <?php if (!empty($canBookAppointments)): ?>
            <a href="<?= htmlspecialchars($walkinUrl()) ?>" class="ui-btn ui-btn-secondary">Walk-in</a>
            <a href="<?= htmlspecialchars($bookUrl()) ?>" class="ui-btn ui-btn-primary">+ Book</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars((string) $_GET['error']) ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['updated'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Appointment updated.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['cancelled'])): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Appointment cancelled.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['booked'])): ?>
    <p class="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
        <span>✓ Booking added.</span>
        <?php if (!empty($_GET['new_id'])): ?>
        <a href="/appointments/<?= (int) $_GET['new_id'] ?>/slip"
           class="font-medium text-emerald-700 underline hover:text-emerald-900">Download slip</a>
        <?php endif; ?>
    </p>
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
        <?php if (!empty($isDoctorScoped) && $selectedDoctorName): ?>
        <p class="text-sm text-slate-600">
            <span class="font-medium text-slate-800">Doctor:</span> <?= htmlspecialchars($selectedDoctorName) ?>
        </p>
        <?php else: ?>
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
        <?php endif; ?>
        <?php if ($selectedDoctorName !== null && empty($isDoctorScoped)): ?>
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
                    <?php foreach ($dayAppts as $a):
                        $rowUser = \App\Core\RequestContext::user() ?? [];
                        $canManageRow = \App\Services\RoleAccessService::canManageAppointment($rowUser, $a);
                        $rowTitle = htmlspecialchars(($a['patient_name'] ?? '') . ' · ' . ($a['doctor_name'] ?? '') . ' · ' . str_replace('_', ' ', (string) ($a['status'] ?? '')));
                        $rowClass = 'block rounded px-1.5 py-1 text-xs leading-tight ' . $statusBadge((string) ($a['status'] ?? ''));
                    ?>
                    <?php if ($canManageRow): ?>
                    <a href="/appointments/<?= (int) $a['id'] ?>/edit"
                       class="<?= $rowClass ?> hover:opacity-80"
                       title="<?= $rowTitle ?>">
                    <?php else: ?>
                    <div class="<?= $rowClass ?> opacity-90" title="<?= $rowTitle ?>">
                    <?php endif; ?>
                        <span class="font-mono"><?= date('H:i', strtotime((string) $a['scheduled_at'])) ?></span>
                        <span class="font-medium"><?= htmlspecialchars((string) ($a['patient_name'] ?? '')) ?></span>
                        <?php if ($doctorId === null): ?>
                        <span class="block truncate text-[10px] opacity-70"><?= htmlspecialchars((string) ($a['doctor_name'] ?? '')) ?></span>
                        <?php endif; ?>
                    <?php if ($canManageRow): ?></a><?php else: ?></div><?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!empty($canBookAppointments)): ?>
                    <a href="<?= htmlspecialchars($bookUrl($colDate)) ?>"
                       class="block w-full rounded border border-dashed border-slate-200 px-1.5 py-1 text-center text-[11px] text-slate-400 hover:border-brand hover:text-brand">
                        + Add
                    </a>
                    <?php endif; ?>
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
            <?php if (!empty($canBookForAll)): ?>
            <a href="/settings?tab=hours" class="text-brand hover:underline">Set working hours in Settings →</a>
            <?php else: ?>
            <a href="/doctor/schedule" class="text-brand hover:underline">Set your hours in My schedule →</a>
            <?php endif; ?>
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
                <?php elseif (!empty($canBookAppointments)): ?>
                <a href="<?= htmlspecialchars($bookUrl($date, (string) $slot['time'])) ?>"
                   class="rounded-lg border px-2 py-1.5 text-xs font-medium hover:bg-emerald-50 <?= !empty($slot['extended']) ? 'border-red-300 bg-red-50 text-red-700' : 'border-emerald-300 bg-white text-emerald-800' ?>"
                   title="<?= !empty($slot['extended']) ? 'Extended hours — book' : 'Book this slot' ?>">
                    <?= $time12 ?>
                </a>
                <?php else: ?>
                <span class="rounded-lg border border-emerald-300 bg-white px-2 py-1.5 text-xs text-emerald-800"><?= $time12 ?></span>
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

    <!-- Live-refreshing results region (count cards + tabs + table). Polled
         every 15s so a booking added by the receptionist on another PC shows
         up here without a manual reload. See the script at the bottom. -->
    <div id="appts-list-body"
         data-appts-poll
         data-date="<?= htmlspecialchars($date) ?>"
         data-doctor-id="<?= htmlspecialchars((string) ($doctorId ?? '')) ?>"
         data-status="<?= htmlspecialchars($statusFilter) ?>">
        <?php require __DIR__ . '/_list_body.php'; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Toast for "new booking" notice (doctor-friendly nudge) -->
<div id="appts-toast"
     class="pointer-events-none fixed bottom-5 left-1/2 z-50 -translate-x-1/2 translate-y-4 rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white opacity-0 shadow-lg transition-all duration-300"
     role="status" aria-live="polite">🔔 New booking added</div>

<script>
(function () {
    var box = document.getElementById('appts-list-body');
    if (!box || !box.hasAttribute('data-appts-poll')) return; // week view: no poll

    var toast = document.getElementById('appts-toast');
    var POLL_MS = 15000;
    function currentTotal() {
        var el = box.querySelector('[data-appt-total]');
        return el ? (parseInt(el.getAttribute('data-appt-total'), 10) || 0) : 0;
    }
    var lastTotal = currentTotal();

    function showToast(msg) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.remove('opacity-0', 'translate-y-4');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () {
            toast.classList.add('opacity-0', 'translate-y-4');
        }, 3500);
    }

    function buildUrl() {
        var p = new URLSearchParams();
        p.set('date', box.getAttribute('data-date') || '');
        if (box.getAttribute('data-doctor-id')) p.set('doctor_id', box.getAttribute('data-doctor-id'));
        if (box.getAttribute('data-status')) p.set('status', box.getAttribute('data-status'));
        return '/api/v1/appointments/list?' + p.toString();
    }

    async function refresh() {
        // Don't yank the list while the user is interacting with something
        // inside it (e.g. mid-click on a cancel-confirm).
        if (box.contains(document.activeElement)) return;
        try {
            var r = await fetch(buildUrl(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!r.ok) return;
            var data = await r.json();
            if (!data.html) return;
            box.innerHTML = data.html;

            var newTotal = parseInt(data.total != null ? data.total : '0', 10) || 0;
            if (newTotal > lastTotal) {
                var added = newTotal - lastTotal;
                showToast('🔔 ' + added + ' new booking' + (added === 1 ? '' : 's') + ' added');
                document.title = '(' + newTotal + ') Appointments';
            }
            lastTotal = newTotal;
        } catch (e) { /* offline / transient — try again next tick */ }
    }

    setInterval(refresh, POLL_MS);
})();
</script>
