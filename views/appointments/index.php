<div class="space-y-4" x-data="appointmentCalendar(<?= (int) ($doctorId ?? 0) ?>)">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">Appointments</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/queue" class="rounded-lg border px-4 py-2 text-sm hover:bg-slate-50">Today's queue</a>
            <a href="/queue/display?clinic=<?= urlencode($clinicSlug ?? 'demo') ?>" target="_blank" class="rounded-lg border px-4 py-2 text-sm hover:bg-slate-50">Display screen</a>
            <a href="/appointments/new" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">+ Book</a>
        </div>
    </div>

    <?php if (!empty($_GET['updated'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Appointment updated.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['cancelled'])): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Appointment cancelled.</p>
    <?php endif; ?>

    <div class="flex flex-wrap gap-3 rounded-xl border bg-white p-4">
        <select x-model="doctorId" @change="calendar.refetchEvents()" class="rounded-lg border px-3 py-2 text-sm">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $doc): ?>
            <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="calendar" class="rounded-xl border bg-white p-4"></div>
</div>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
function appointmentCalendar(initialDoctor) {
    return {
        doctorId: initialDoctor ? String(initialDoctor) : '',
        calendar: null,
        init() {
            const el = document.getElementById('calendar');
            this.calendar = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                events: (info, success, failure) => {
                    const params = new URLSearchParams({
                        start: info.startStr,
                        end: info.endStr,
                    });
                    if (this.doctorId) params.set('doctor_id', this.doctorId);
                    fetch('/api/v1/appointments/calendar?' + params)
                        .then(r => r.json())
                        .then(success)
                        .catch(failure);
                },
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    if (info.event.url) window.location = info.event.url;
                },
            });
            this.calendar.render();
        },
    };
}
</script>
