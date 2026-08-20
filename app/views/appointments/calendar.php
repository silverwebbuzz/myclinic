<?php
/**
 * Appointment calendar (week / day grid).
 *
 * Events come from GET /api/v1/appointments/calendar (AppointmentService::
 * calendarEvents) which already colours by status. Clicking an empty future
 * slot deep-links into the existing booking form (/appointments/new) with the
 * date/time/doctor prefilled; clicking an event opens it. Past slots are not
 * clickable here, and AppointmentService::create is the authoritative
 * server-side guard against booking in the past.
 *
 * @var array $doctors
 * @var string $clinicTimezone
 * @var string $todayLocal   Y-m-d in clinic tz
 * @var string $nowLocal     Y-m-d\TH:i in clinic tz (reference "now")
 * @var array  $statusColors status => [hex, label]
 * @var int|null $lockDoctorId  when the user may only see one doctor
 */
$cfg = [
    'today'   => $todayLocal,
    'now'     => $nowLocal,
    'startH'  => 8,
    'endH'    => 20,
    'stepMin' => 30,
    'hourPx'  => 56,
    'lockDoctorId' => $lockDoctorId,
    'doctors' => array_map(static fn ($d) => [
        'id' => (int) $d['id'],
        'name' => (string) ($d['name'] ?? 'Doctor'),
    ], $doctors),
];
?>
<div class="ui-card ui-card-pad" x-data="clinicCalendar(<?= htmlspecialchars(json_encode($cfg), ENT_QUOTES) ?>)" x-cloak>

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <div class="flex overflow-hidden rounded-lg border border-slate-300">
                <button type="button" @click="shift(-1)" class="px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">←</button>
                <button type="button" @click="goToday()" class="border-x border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Today</button>
                <button type="button" @click="shift(1)" class="px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">→</button>
            </div>
            <h2 class="text-base font-semibold text-slate-900" x-text="rangeLabel"></h2>
            <span x-show="loading" class="text-xs text-slate-400">loading…</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <select x-model="doctorId" @change="reload()" class="ui-input !w-auto py-1.5 text-sm"
                    <?= $lockDoctorId !== null ? 'disabled' : '' ?>>
                <option value="">All doctors</option>
                <?php foreach ($doctors as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= $lockDoctorId === (int) $d['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($d['name'] ?? 'Doctor')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="flex overflow-hidden rounded-lg border border-slate-300 text-sm">
                <button type="button" @click="setView('day')"  :class="view==='day'  ? 'bg-brand text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5">Day</button>
                <button type="button" @click="setView('week')" :class="view==='week' ? 'bg-brand text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="border-l border-slate-300 px-3 py-1.5">Week</button>
            </div>
            <a href="/appointments/new" class="ui-btn ui-btn-primary ui-btn-sm">+ Book</a>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
        <?php foreach ($statusColors as $st => [$hex, $label]): ?>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:<?= htmlspecialchars($hex) ?>"></span>
                <?= htmlspecialchars($label) ?>
            </span>
        <?php endforeach; ?>
        <span class="text-slate-400">· Click an empty slot to book · past slots are locked</span>
    </div>

    <!-- Grid -->
    <div class="mt-4 overflow-x-auto">
        <div class="min-w-[720px]">
            <!-- Day headers -->
            <div class="flex border-b border-slate-200">
                <div class="w-14 shrink-0"></div>
                <template x-for="day in days" :key="day.iso">
                    <div class="flex-1 px-2 py-2 text-center">
                        <div class="text-[11px] uppercase tracking-wide text-slate-400" x-text="day.dow"></div>
                        <div class="text-sm font-semibold"
                             :class="day.isToday ? 'text-brand' : 'text-slate-700'"
                             x-text="day.dnum"></div>
                    </div>
                </template>
            </div>

            <!-- Time rows + day columns -->
            <div class="flex">
                <!-- time gutter -->
                <div class="w-14 shrink-0">
                    <template x-for="h in hours" :key="h">
                        <div class="relative text-right pr-2" :style="`height:${hourPx}px`">
                            <span class="absolute -top-2 right-2 text-[11px] text-slate-400" x-text="fmtHour(h)"></span>
                        </div>
                    </template>
                </div>

                <!-- day columns -->
                <template x-for="day in days" :key="day.iso">
                    <div class="relative flex-1 border-l border-slate-100" :style="`height:${bodyHeight}px`">
                        <!-- clickable slot cells -->
                        <template x-for="slot in day.slots" :key="slot.iso">
                            <div class="absolute inset-x-0"
                                 :style="`top:${slot.top}px;height:${slotPx}px`">
                                <button type="button"
                                        x-show="!slot.past"
                                        @click="book(day, slot)"
                                        class="group h-full w-full border-b border-slate-100 hover:bg-brand-soft"
                                        :title="`Book ${day.dow} ${slot.label}`"></button>
                                <div x-show="slot.past"
                                     class="h-full w-full border-b border-slate-100 bg-slate-50/60"></div>
                            </div>
                        </template>

                        <!-- events -->
                        <template x-for="ev in day.events" :key="ev.id">
                            <a :href="ev.url"
                               class="absolute overflow-hidden rounded-md px-1.5 py-0.5 text-[11px] leading-tight text-white shadow-sm ring-1 ring-black/5 hover:brightness-95"
                               :style="`top:${ev.top}px;height:${ev.height}px;left:${ev.left}%;width:${ev.width}%;background:${ev.color}`">
                                <div class="font-semibold truncate" x-text="ev.time"></div>
                                <div class="truncate opacity-95" x-text="ev.title"></div>
                            </a>
                        </template>

                        <!-- now indicator -->
                        <div x-show="day.isToday && nowTop !== null"
                             class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-500"
                             :style="`top:${nowTop}px`">
                            <span class="absolute -left-1 -top-1 h-2 w-2 rounded-full bg-red-500"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function clinicCalendar(cfg) {
    return {
        cfg,
        view: 'week',
        anchor: cfg.today,            // 'YYYY-MM-DD'
        doctorId: cfg.lockDoctorId ? String(cfg.lockDoctorId) : '',
        events: [],
        loading: false,
        // layout constants
        startH: cfg.startH, endH: cfg.endH, hourPx: cfg.hourPx, stepMin: cfg.stepMin,
        days: [],
        rangeLabel: '',

        get hours() {
            const out = [];
            for (let h = this.startH; h < this.endH; h++) out.push(h);
            return out;
        },
        get bodyHeight() { return (this.endH - this.startH) * this.hourPx; },
        get slotPx() { return this.hourPx * this.stepMin / 60; },
        get now() { return this.parse(this.cfg.now); },
        get nowTop() {
            const n = this.now;
            const mins = n.getHours() * 60 + n.getMinutes() - this.startH * 60;
            if (mins < 0 || mins > (this.endH - this.startH) * 60) return null;
            return mins / 60 * this.hourPx;
        },

        init() { this.reload(); },

        setView(v) { this.view = v; this.reload(); },
        goToday() { this.anchor = this.cfg.today; this.reload(); },
        shift(dir) {
            const d = this.parse(this.anchor + 'T00:00');
            d.setDate(d.getDate() + dir * (this.view === 'week' ? 7 : 1));
            this.anchor = this.ymd(d);
            this.reload();
        },

        // --- data ---
        rangeDates() {
            const a = this.parse(this.anchor + 'T00:00');
            if (this.view === 'day') return [a];
            // Monday-start week
            const dow = (a.getDay() + 6) % 7;
            const mon = new Date(a); mon.setDate(a.getDate() - dow);
            return Array.from({length: 7}, (_, i) => { const d = new Date(mon); d.setDate(mon.getDate() + i); return d; });
        },

        async reload() {
            const dates = this.rangeDates();
            const start = this.ymd(dates[0]) + ' 00:00:00';
            const endD = new Date(dates[dates.length - 1]); endD.setDate(endD.getDate() + 1);
            const end = this.ymd(endD) + ' 00:00:00';
            const qs = new URLSearchParams({ start, end });
            if (this.doctorId) qs.set('doctor_id', this.doctorId);

            this.loading = true;
            try {
                const r = await fetch('/api/v1/appointments/calendar?' + qs.toString(), { headers: { 'Accept': 'application/json' } });
                this.events = r.ok ? await r.json() : [];
            } catch (e) { this.events = []; }
            this.loading = false;
            this.rebuild(dates);
        },

        rebuild(dates) {
            const now = this.now;
            this.days = dates.map(d => {
                const iso = this.ymd(d);
                // slots
                const slots = [];
                for (let m = this.startH * 60; m < this.endH * 60; m += this.stepMin) {
                    const hh = String(Math.floor(m / 60)).padStart(2, '0');
                    const mm = String(m % 60).padStart(2, '0');
                    const slotDate = this.parse(iso + 'T' + hh + ':' + mm);
                    slots.push({
                        iso: iso + 'T' + hh + ':' + mm,
                        time: hh + ':' + mm,
                        label: this.fmtTime(hh, mm),
                        top: (m - this.startH * 60) / 60 * this.hourPx,
                        past: slotDate <= now,
                    });
                }
                // events for this day, positioned with overlap lanes
                const evs = this.events
                    .filter(e => (e.start || '').slice(0, 10) === iso)
                    .map(e => {
                        const s = this.parse(e.start), en = this.parse(e.end || e.start);
                        const sMin = s.getHours() * 60 + s.getMinutes() - this.startH * 60;
                        const dur = Math.max(15, (en - s) / 60000);
                        return {
                            id: e.id, url: e.url, color: e.backgroundColor || '#64748b',
                            title: e.title || '', time: this.fmtTime(String(s.getHours()).padStart(2,'0'), String(s.getMinutes()).padStart(2,'0')),
                            top: Math.max(0, sMin / 60 * this.hourPx),
                            height: Math.max(this.slotPx - 2, dur / 60 * this.hourPx - 2),
                            _s: sMin, _e: sMin + dur,
                        };
                    })
                    .sort((a, b) => a._s - b._s);
                this.assignLanes(evs);
                return {
                    iso,
                    dow: d.toLocaleDateString(undefined, { weekday: 'short' }),
                    dnum: d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }),
                    isToday: iso === this.cfg.today,
                    slots, events: evs,
                };
            });
            this.rangeLabel = this.buildRangeLabel(dates);
        },

        // simple lane packing for overlapping events
        assignLanes(evs) {
            const active = [];
            for (const ev of evs) {
                for (let i = active.length - 1; i >= 0; i--) if (active[i]._e <= ev._s) active.splice(i, 1);
                let lane = 0;
                const used = new Set(active.map(a => a._lane));
                while (used.has(lane)) lane++;
                ev._lane = lane;
                active.push(ev);
                ev._lanes = Math.max(...active.map(a => a._lane)) + 1;
            }
            // second pass: final lane count per cluster (use max seen)
            for (const ev of evs) {
                const overlap = evs.filter(o => o._s < ev._e && o._e > ev._s);
                const lanes = Math.max(...overlap.map(o => o._lane)) + 1;
                ev.left = (ev._lane * 100 / lanes);
                ev.width = (100 / lanes) - 1;
            }
        },

        book(day, slot) {
            const qs = new URLSearchParams({ date: day.iso, time: slot.time, type: 'prebooked' });
            if (this.doctorId) qs.set('doctor_id', this.doctorId);
            window.location = '/appointments/new?' + qs.toString();
        },

        // --- helpers ---
        parse(s) { // 'YYYY-MM-DDTHH:MM' as LOCAL time
            const [d, t] = s.split('T');
            const [y, mo, da] = d.split('-').map(Number);
            const [h, mi] = (t || '00:00').split(':').map(Number);
            return new Date(y, mo - 1, da, h, mi, 0, 0);
        },
        ymd(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); },
        fmtHour(h) { const ap = h < 12 ? 'am' : 'pm'; const hh = h % 12 === 0 ? 12 : h % 12; return hh + ' ' + ap; },
        fmtTime(hh, mm) { let h = parseInt(hh,10); const ap = h < 12 ? 'am' : 'pm'; h = h % 12 === 0 ? 12 : h % 12; return h + ':' + mm + ap; },
        buildRangeLabel(dates) {
            const opts = { day: 'numeric', month: 'short' };
            if (this.view === 'day') return dates[0].toLocaleDateString(undefined, { weekday: 'long', ...opts, year: 'numeric' });
            const a = dates[0], b = dates[dates.length - 1];
            return a.toLocaleDateString(undefined, opts) + ' – ' + b.toLocaleDateString(undefined, { ...opts, year: 'numeric' });
        },
    };
}
</script>
