<?php
/**
 * Appointment calendar — List / Day / Week / Month.
 *
 * Week & Day are a time-grid (hour rows, appointments positioned by time).
 * Month is a pill-grid (each day cell lists its appointments). List is a
 * chronological agenda for the visible month.
 *
 * Events come from GET /api/v1/appointments/calendar (colour + status per
 * appointment). Clicking an empty future slot/day deep-links into the existing
 * /appointments/new booking form (date/time/doctor prefilled). Past slots are
 * not clickable; AppointmentService::create is the authoritative server guard.
 *
 * @var array      $doctors
 * @var string     $clinicTimezone
 * @var string     $todayLocal   Y-m-d (clinic tz)
 * @var string     $nowLocal     Y-m-d\TH:i (clinic tz)
 * @var array      $statusColors status => [hex, label]
 * @var int|null   $lockDoctorId
 */
$statusMeta = [];
foreach ($statusColors as $st => [$hex, $label]) {
    $statusMeta[$st] = ['color' => $hex, 'label' => $label];
}
$cfg = [
    'today'   => $todayLocal,
    'now'     => $nowLocal,
    'startH'  => 8,
    'endH'    => 20,
    'stepMin' => 30,
    'hourPx'  => 52,
    'lockDoctorId' => $lockDoctorId,
    'statusMeta' => $statusMeta,
    'doctors' => array_map(static fn ($d) => [
        'id' => (int) $d['id'], 'name' => (string) ($d['name'] ?? 'Doctor'),
    ], $doctors),
];
?>
<div class="ui-card" x-data="clinicCalendar(<?= htmlspecialchars(json_encode($cfg), ENT_QUOTES) ?>)" x-cloak>

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <div class="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-sm">
            <template x-for="v in ['list','day','week','month']" :key="v">
                <button type="button" @click="setView(v)"
                        :class="view===v ? 'bg-brand text-white shadow-sm' : 'text-slate-600 hover:bg-white'"
                        class="rounded-md px-3 py-1.5 capitalize" x-text="v"></button>
            </template>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" @click="shift(-1)" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50">‹</button>
            <h2 class="min-w-[9rem] text-center text-base font-semibold text-slate-900" x-text="rangeLabel"></h2>
            <button type="button" @click="shift(1)" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50">›</button>
            <button type="button" @click="goToday()" class="rounded-md border border-brand px-3 py-1.5 text-sm font-medium text-brand hover:bg-brand-soft">Today</button>
            <a :href="bookUrl()" class="ui-btn ui-btn-primary ui-btn-sm">+ Book Appointment</a>
            <span x-show="loading" class="text-xs text-slate-400">loading…</span>
        </div>
    </div>

    <!-- Doctor filter -->
    <div class="flex items-center gap-2 px-4 py-2 text-sm">
        <span class="text-slate-500">Doctor:</span>
        <select x-model="doctorId" @change="reload()" class="ui-input !w-auto py-1 text-sm" <?= $lockDoctorId !== null ? 'disabled' : '' ?>>
            <option value="">All doctors</option>
            <?php foreach ($doctors as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= $lockDoctorId === (int) $d['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($d['name'] ?? 'Doctor')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- ============ MONTH ============ -->
    <div x-show="view==='month'" class="px-3 pb-3">
        <div class="grid grid-cols-7 border-l border-t border-slate-200 text-xs">
            <template x-for="d in weekdayNames" :key="d">
                <div class="border-b border-r border-slate-200 bg-slate-50 px-2 py-2 font-semibold uppercase tracking-wide text-slate-500" x-text="d"></div>
            </template>
            <template x-for="cell in monthCells" :key="cell.iso">
                <div class="min-h-[104px] border-b border-r border-slate-200 p-1.5 align-top"
                     :class="cell.inMonth ? 'bg-white' : 'bg-slate-50/50'">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs font-semibold"
                              :class="cell.isToday ? 'bg-brand text-white' : (cell.inMonth ? 'text-slate-700' : 'text-slate-400')"
                              x-text="cell.dnum"></span>
                        <span x-show="cell.events.length" class="rounded-full bg-slate-100 px-1.5 text-[10px] text-slate-500" x-text="cell.events.length"></span>
                    </div>
                    <div class="mt-1 space-y-1">
                        <template x-for="ev in cell.events.slice(0,3)" :key="ev.id">
                            <a :href="ev.url" class="block truncate rounded px-1.5 py-0.5 text-[11px] leading-tight text-slate-700 hover:brightness-95"
                               :style="`background:${ev.color}1f;border-left:3px solid ${ev.color}`">
                                <span class="font-medium" x-text="ev.time"></span> <span x-text="ev.patient"></span>
                            </a>
                        </template>
                        <div x-show="cell.events.length > 3" class="px-1 text-[10px] text-slate-400" x-text="`+${cell.events.length-3} more`"></div>
                        <button type="button" x-show="!cell.past && cell.events.length===0" @click="bookDay(cell.iso)"
                                class="w-full rounded px-1 py-0.5 text-left text-[10px] text-slate-300 hover:bg-brand-soft hover:text-brand">+ book</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- ============ WEEK / DAY (time grid) ============ -->
    <div x-show="view==='week' || view==='day'" class="overflow-x-auto px-3 pb-3">
        <div :class="view==='day' ? 'min-w-[360px]' : 'min-w-[760px]'">
            <div class="flex border-b border-slate-200">
                <div class="w-14 shrink-0"></div>
                <template x-for="day in gridDays" :key="day.iso">
                    <div class="flex-1 px-2 py-2 text-center">
                        <div class="text-[11px] uppercase tracking-wide text-slate-400" x-text="day.dow"></div>
                        <div class="text-sm font-semibold" :class="day.isToday ? 'text-brand' : 'text-slate-700'" x-text="day.dnum"></div>
                    </div>
                </template>
            </div>
            <div class="flex">
                <div class="w-14 shrink-0">
                    <template x-for="h in hours" :key="h">
                        <div class="relative" :style="`height:${hourPx}px`">
                            <span class="absolute -top-2 right-2 text-[11px] text-slate-400" x-text="fmtHour(h)"></span>
                        </div>
                    </template>
                </div>
                <template x-for="day in gridDays" :key="day.iso">
                    <div class="relative flex-1 border-l border-slate-100" :style="`height:${bodyHeight}px`">
                        <template x-for="slot in day.slots" :key="slot.iso">
                            <div class="absolute inset-x-0" :style="`top:${slot.top}px;height:${slotPx}px`">
                                <button type="button" x-show="!slot.past" @click="book(day.iso, slot.time)"
                                        class="h-full w-full border-b border-slate-100 hover:bg-brand-soft" :title="`Book ${day.dow} ${slot.label}`"></button>
                                <div x-show="slot.past" class="h-full w-full border-b border-slate-100 bg-slate-50/60"></div>
                            </div>
                        </template>
                        <template x-for="ev in day.events" :key="ev.id">
                            <a :href="ev.url" class="absolute overflow-hidden rounded-md px-1.5 py-0.5 text-[11px] leading-tight text-white shadow-sm ring-1 ring-black/5 hover:brightness-95"
                               :style="`top:${ev.top}px;height:${ev.height}px;left:${ev.left}%;width:${ev.width}%;background:${ev.color}`">
                                <div class="font-semibold truncate" x-text="ev.time"></div>
                                <div class="truncate opacity-95" x-text="ev.patient"></div>
                            </a>
                        </template>
                        <div x-show="day.isToday && nowTop !== null" class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-500" :style="`top:${nowTop}px`">
                            <span class="absolute -left-1 -top-1 h-2 w-2 rounded-full bg-red-500"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ============ LIST ============ -->
    <div x-show="view==='list'" class="px-4 pb-4">
        <template x-if="listGroups.length === 0">
            <p class="py-8 text-center text-sm text-slate-400">No appointments this month.</p>
        </template>
        <template x-for="grp in listGroups" :key="grp.iso">
            <div class="mt-3">
                <div class="sticky top-0 bg-white py-1 text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="grp.label"></div>
                <div class="divide-y divide-slate-100">
                    <template x-for="ev in grp.events" :key="ev.id">
                        <a :href="ev.url" class="flex items-center gap-3 py-2 hover:bg-slate-50">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${ev.color}`"></span>
                            <span class="w-16 shrink-0 text-sm font-medium text-slate-700" x-text="ev.time"></span>
                            <span class="flex-1 truncate text-sm text-slate-800" x-text="ev.patient"></span>
                            <span class="hidden truncate text-xs text-slate-400 sm:block" x-text="ev.doctor"></span>
                            <span class="rounded-full px-2 py-0.5 text-[11px]" :style="`background:${ev.color}1f;color:${ev.color}`" x-text="ev.statusLabel"></span>
                        </a>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <?php foreach ($statusColors as [$hex, $label]): ?>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:<?= htmlspecialchars($hex) ?>"></span>
                    <?= htmlspecialchars($label) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <span class="text-slate-400">Click any empty slot to book · past slots are locked</span>
    </div>
</div>

<script>
function clinicCalendar(cfg) {
    return {
        cfg,
        view: 'month',
        anchor: cfg.today,
        doctorId: cfg.lockDoctorId ? String(cfg.lockDoctorId) : '',
        events: [],
        loading: false,
        startH: cfg.startH, endH: cfg.endH, hourPx: cfg.hourPx, stepMin: cfg.stepMin,
        weekdayNames: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        // built per reload:
        gridDays: [], monthCells: [], listGroups: [], rangeLabel: '',

        get hours() { const o=[]; for (let h=this.startH; h<this.endH; h++) o.push(h); return o; },
        get bodyHeight() { return (this.endH - this.startH) * this.hourPx; },
        get slotPx() { return this.hourPx * this.stepMin / 60; },
        get now() { return this.parse(this.cfg.now); },
        get nowTop() {
            const n=this.now, mins=n.getHours()*60+n.getMinutes()-this.startH*60;
            if (mins<0 || mins>(this.endH-this.startH)*60) return null;
            return mins/60*this.hourPx;
        },

        init() { this.reload(); },
        setView(v) { this.view=v; this.reload(); },
        goToday() { this.anchor=this.cfg.today; this.reload(); },
        shift(dir) {
            const d=this.parse(this.anchor+'T00:00');
            if (this.view==='day') d.setDate(d.getDate()+dir);
            else if (this.view==='week') d.setDate(d.getDate()+dir*7);
            else d.setMonth(d.getMonth()+dir); // month + list
            this.anchor=this.ymd(d);
            this.reload();
        },

        // Fetch range for the active view
        fetchRange() {
            const a=this.parse(this.anchor+'T00:00');
            if (this.view==='day') return [a, a];
            if (this.view==='week') { const mon=this.monday(a); const sun=new Date(mon); sun.setDate(mon.getDate()+6); return [mon, sun]; }
            // month / list → full month-grid range
            const first=new Date(a.getFullYear(), a.getMonth(), 1);
            const last=new Date(a.getFullYear(), a.getMonth()+1, 0);
            return [this.monday(first), this.sunday(last)];
        },

        async reload() {
            const [s,e]=this.fetchRange();
            const end=new Date(e); end.setDate(end.getDate()+1);
            const qs=new URLSearchParams({ start: this.ymd(s)+' 00:00:00', end: this.ymd(end)+' 00:00:00' });
            if (this.doctorId) qs.set('doctor_id', this.doctorId);
            this.loading=true;
            try { const r=await fetch('/api/v1/appointments/calendar?'+qs, {headers:{Accept:'application/json'}}); this.events=r.ok?await r.json():[]; }
            catch(_) { this.events=[]; }
            this.loading=false;
            this.rebuild();
        },

        // normalise one API event into a render model
        model(e) {
            const s=this.parse(e.start);
            const meta=this.cfg.statusMeta[e.status] || {color: e.backgroundColor||'#64748b', label: e.status||''};
            return {
                id:e.id, url:e.url, start:s, iso:this.ymd(s),
                color: e.backgroundColor || meta.color,
                patient: e.patient || (e.title||'').split(' — ')[0] || 'Patient',
                doctor: e.doctor || '',
                status: e.status, statusLabel: meta.label,
                time: this.fmtTime(s),
                _end: this.parse(e.end||e.start),
            };
        },

        rebuild() {
            const models=this.events.map(e=>this.model(e));
            if (this.view==='month') this.buildMonth(models);
            else if (this.view==='list') this.buildList(models);
            else this.buildGrid(models);
            this.rangeLabel=this.buildLabel();
        },

        buildMonth(models) {
            const a=this.parse(this.anchor+'T00:00');
            const start=this.monday(new Date(a.getFullYear(), a.getMonth(), 1));
            const cells=[];
            for (let i=0;i<42;i++) {
                const d=new Date(start); d.setDate(start.getDate()+i);
                const iso=this.ymd(d);
                const dayEvents=models.filter(m=>m.iso===iso).sort((x,y)=>x.start-y.start);
                const isPast=this.parse(iso+'T23:59') < this.now;
                cells.push({
                    iso, dnum:d.getDate(), inMonth:d.getMonth()===a.getMonth(),
                    isToday:iso===this.cfg.today, past:isPast, events:dayEvents,
                });
            }
            this.monthCells=cells;
        },

        buildList(models) {
            const a=this.parse(this.anchor+'T00:00');
            const inMonth=models.filter(m=>m.start.getMonth()===a.getMonth() && m.start.getFullYear()===a.getFullYear())
                                .sort((x,y)=>x.start-y.start);
            const groups={};
            for (const m of inMonth) { (groups[m.iso] ||= []).push(m); }
            this.listGroups=Object.keys(groups).sort().map(iso=>({
                iso, label: this.parse(iso+'T00:00').toLocaleDateString(undefined,{weekday:'long', day:'numeric', month:'long'}),
                events: groups[iso],
            }));
        },

        buildGrid(models) {
            const [s,e]=this.fetchRange();
            const days=[]; const cur=new Date(s);
            while (cur<=e) { days.push(new Date(cur)); cur.setDate(cur.getDate()+1); }
            const now=this.now;
            this.gridDays=days.map(d=>{
                const iso=this.ymd(d);
                const slots=[];
                for (let m=this.startH*60; m<this.endH*60; m+=this.stepMin) {
                    const hh=String(Math.floor(m/60)).padStart(2,'0'), mm=String(m%60).padStart(2,'0');
                    slots.push({ iso:iso+'T'+hh+':'+mm, time:hh+':'+mm, label:this.fmtTime(this.parse(iso+'T'+hh+':'+mm)),
                        top:(m-this.startH*60)/60*this.hourPx, past:this.parse(iso+'T'+hh+':'+mm)<=now });
                }
                const evs=models.filter(m=>m.iso===iso).map(m=>{
                    const sMin=m.start.getHours()*60+m.start.getMinutes()-this.startH*60;
                    const dur=Math.max(15,(m._end-m.start)/60000);
                    return {...m, top:Math.max(0,sMin/60*this.hourPx), height:Math.max(this.slotPx-2,dur/60*this.hourPx-2), _s:sMin, _e:sMin+dur};
                }).sort((a,b)=>a._s-b._s);
                this.lanes(evs);
                return { iso, dow:d.toLocaleDateString(undefined,{weekday:'short'}),
                    dnum:d.toLocaleDateString(undefined,{day:'numeric',month:'short'}),
                    isToday:iso===this.cfg.today, slots, events:evs };
            });
        },

        lanes(evs) {
            for (const ev of evs) {
                const overlap=evs.filter(o=>o!==ev && o._s<ev._e && o._e>ev._s);
                let lane=0; const used=new Set();
                for (const o of overlap) if (o._lane!==undefined && o._s<=ev._s) used.add(o._lane);
                while (used.has(lane)) lane++;
                ev._lane=lane;
            }
            for (const ev of evs) {
                const overlap=evs.filter(o=>o._s<ev._e && o._e>ev._s);
                const lanes=Math.max(...overlap.map(o=>o._lane))+1;
                ev.left=ev._lane*100/lanes; ev.width=100/lanes-1;
            }
        },

        book(iso, time) { const q=new URLSearchParams({date:iso, time, type:'prebooked'}); if (this.doctorId) q.set('doctor_id',this.doctorId); window.location='/appointments/new?'+q; },
        bookDay(iso) { const q=new URLSearchParams({date:iso}); if (this.doctorId) q.set('doctor_id',this.doctorId); window.location='/appointments/new?'+q; },
        bookUrl() { const q=new URLSearchParams(); if (this.doctorId) q.set('doctor_id',this.doctorId); const s=q.toString(); return '/appointments/new'+(s?'?'+s:''); },

        // date helpers (all LOCAL wall-clock)
        parse(s){ const [d,t]=s.split('T'); const [y,mo,da]=d.split('-').map(Number); const [h,mi]=(t||'00:00').split(':').map(Number); return new Date(y,mo-1,da,h,mi,0,0); },
        ymd(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); },
        monday(d){ const x=new Date(d); const dow=(x.getDay()+6)%7; x.setDate(x.getDate()-dow); return x; },
        sunday(d){ const m=this.monday(d); const x=new Date(m); x.setDate(m.getDate()+6); return x; },
        fmtHour(h){ const ap=h<12?'am':'pm'; const hh=h%12===0?12:h%12; return hh+' '+ap; },
        fmtTime(d){ let h=d.getHours(); const mm=String(d.getMinutes()).padStart(2,'0'); const ap=h<12?'AM':'PM'; h=h%12===0?12:h%12; return h+':'+mm+' '+ap; },
        buildLabel(){
            const a=this.parse(this.anchor+'T00:00');
            if (this.view==='day') return a.toLocaleDateString(undefined,{weekday:'short',day:'numeric',month:'short',year:'numeric'});
            if (this.view==='week') { const m=this.monday(a), s=this.sunday(a); return m.toLocaleDateString(undefined,{day:'numeric',month:'short'})+' – '+s.toLocaleDateString(undefined,{day:'numeric',month:'short',year:'numeric'}); }
            return a.toLocaleDateString(undefined,{month:'long',year:'numeric'});
        },
    };
}
</script>
