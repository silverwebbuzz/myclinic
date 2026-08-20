<?php
/**
 * Appointment calendar — List / Day / Week / Month.
 *
 * Week & Day are a time-grid (hour rows, appointments positioned by time).
 * Month is a pill-grid (each day cell lists its appointments). List is a
 * chronological agenda for the visible month.
 *
 * Events come from GET /api/v1/appointments/calendar. Clicking an empty future
 * slot/day deep-links into /appointments/new (date/time/doctor prefilled). Past
 * slots are not clickable; AppointmentService::create is the server-side guard.
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
    'today' => $todayLocal, 'now' => $nowLocal,
    'startH' => 8, 'endH' => 20, 'stepMin' => 30, 'hourPx' => 52,
    'lockDoctorId' => $lockDoctorId, 'statusMeta' => $statusMeta,
    'doctors' => array_map(static fn ($d) => ['id' => (int) $d['id'], 'name' => (string) ($d['name'] ?? 'Doctor')], $doctors),
];
$showDoctorFilter = $lockDoctorId === null && count($doctors) > 1;
?>
<div class="ui-card overflow-hidden" x-data="clinicCalendar(<?= htmlspecialchars(json_encode($cfg), ENT_QUOTES) ?>)" x-cloak>

    <!-- Header + toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-5 py-4">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v3M16 3v3" stroke-linecap="round"/></svg>
            </span>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-slate-900">Calendar</h1>
                <p class="text-sm text-slate-500" x-text="viewSubtitle"></p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <button type="button" @click="setView('list')" :class="pill('list')" class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round"/></svg>List</button>
                <button type="button" @click="setView('day')" :class="pill('day')" class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19" stroke-linecap="round"/></svg>Day</button>
                <button type="button" @click="setView('week')" :class="pill('week')" class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v3M16 3v3" stroke-linecap="round"/></svg>Week</button>
                <button type="button" @click="setView('month')" :class="pill('month')" class="inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-medium">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M7 13h2M11 13h2M15 13h2M7 16.5h2M11 16.5h2" stroke-linecap="round"/></svg>Month</button>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" @click="shift(-1)" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600 hover:bg-slate-200">‹</button>
                <span class="min-w-[9.5rem] text-center text-lg font-semibold text-slate-900" x-text="rangeLabel"></span>
                <button type="button" @click="shift(1)" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-slate-600 hover:bg-slate-200">›</button>
                <button type="button" @click="goToday()" class="rounded-lg border border-blue-600 px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50">Today</button>
                <button type="button" @click="openBooking({})" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>Book Appointment</button>
                <span x-show="loading" class="text-xs text-slate-400">loading…</span>
            </div>
        </div>
    </div>

    <?php if ($showDoctorFilter): ?>
    <div class="flex items-center gap-2 border-b border-slate-100 px-5 py-2 text-sm">
        <span class="text-slate-500">Doctor:</span>
        <select x-model="doctorId" @change="reload()" class="ui-input !w-auto py-1 text-sm">
            <option value="">All doctors</option>
            <?php foreach ($doctors as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars((string) ($d['name'] ?? 'Doctor')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <!-- ============ MONTH ============ -->
    <div x-show="view==='month'" class="px-4 pb-3 pt-3">
        <div class="grid grid-cols-7 overflow-hidden rounded-lg border-l border-t border-slate-200 text-xs">
            <template x-for="d in weekdayNames" :key="d">
                <div class="border-b border-r border-slate-200 bg-slate-50 px-2 py-2.5 font-semibold uppercase tracking-wide text-slate-500" x-text="d"></div>
            </template>
            <template x-for="cell in monthCells" :key="cell.iso">
                <div class="min-h-[150px] border-b border-r border-slate-200 p-2 align-top"
                     :class="cell.inMonth ? 'bg-white' : 'bg-slate-50/40'">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full px-1.5 text-sm font-semibold"
                              :class="cell.isToday ? 'bg-blue-600 text-white' : (cell.inMonth ? 'text-slate-700' : 'text-slate-400')"
                              x-text="cell.dnum"></span>
                        <span x-show="cell.events.length" class="rounded-full bg-slate-100 px-1.5 text-[10px] font-medium text-slate-500" x-text="cell.events.length"></span>
                    </div>
                    <div class="mt-1.5 space-y-1">
                        <template x-for="ev in cell.events.slice(0,4)" :key="ev.id">
                            <button type="button" @click="openEvent(ev)" class="block w-full truncate rounded px-1.5 py-1 text-left text-[11px] leading-tight text-slate-700 hover:brightness-95"
                               :style="`background:${ev.color}1f;border-left:3px solid ${ev.color}`">
                                <span class="font-semibold" x-text="ev.time"></span> <span x-text="ev.patient"></span>
                            </button>
                        </template>
                        <div x-show="cell.events.length > 4" class="px-1 text-[10px] text-slate-400" x-text="`+${cell.events.length-4} more`"></div>
                        <button type="button" x-show="!cell.past && cell.events.length===0" @click="bookDay(cell.iso)"
                                class="w-full rounded px-1 py-0.5 text-left text-[10px] text-slate-300 hover:bg-blue-50 hover:text-blue-600">+ book</button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- ============ WEEK / DAY (time grid) ============ -->
    <div x-show="view==='week' || view==='day'" class="overflow-x-auto px-4 pb-3 pt-3">
        <div :class="view==='day' ? 'min-w-[360px]' : 'min-w-[760px]'">
            <div class="flex border-b border-slate-200">
                <div class="w-14 shrink-0"></div>
                <template x-for="day in gridDays" :key="day.iso">
                    <div class="flex-1 px-2 py-2 text-center">
                        <div class="text-[11px] uppercase tracking-wide text-slate-400" x-text="day.dow"></div>
                        <div class="text-sm font-semibold" :class="day.isToday ? 'text-blue-600' : 'text-slate-700'" x-text="day.dnum"></div>
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
                                        class="h-full w-full border-b border-slate-100 hover:bg-blue-50" :title="`Book ${day.dow} ${slot.label}`"></button>
                                <div x-show="slot.past" class="h-full w-full border-b border-slate-100 bg-slate-50/60"></div>
                            </div>
                        </template>
                        <template x-for="ev in day.events" :key="ev.id">
                            <button type="button" @click="openEvent(ev)" class="absolute overflow-hidden rounded-md px-1.5 py-0.5 text-left text-[11px] leading-tight text-white shadow-sm ring-1 ring-black/5 hover:brightness-95"
                               :style="`top:${ev.top}px;height:${ev.height}px;left:${ev.left}%;width:${ev.width}%;background:${ev.color}`">
                                <div class="font-semibold truncate" x-text="ev.time"></div>
                                <div class="truncate opacity-95" x-text="ev.patient"></div>
                            </button>
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
    <div x-show="view==='list'" class="px-5 pb-4 pt-2">
        <template x-if="listGroups.length === 0">
            <p class="py-10 text-center text-sm text-slate-400">No appointments this month.</p>
        </template>
        <template x-for="grp in listGroups" :key="grp.iso">
            <div class="mt-3">
                <div class="sticky top-0 bg-white py-1 text-xs font-semibold uppercase tracking-wide text-slate-400" x-text="grp.label"></div>
                <div class="divide-y divide-slate-100">
                    <template x-for="ev in grp.events" :key="ev.id">
                        <button type="button" @click="openEvent(ev)" class="flex w-full items-center gap-3 py-2.5 text-left hover:bg-slate-50">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="`background:${ev.color}`"></span>
                            <span class="w-20 shrink-0 text-sm font-medium text-slate-700" x-text="ev.time"></span>
                            <span class="flex-1 truncate text-sm text-slate-800" x-text="ev.patient"></span>
                            <span class="hidden truncate text-xs text-slate-400 sm:block" x-text="ev.doctor"></span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :style="`background:${ev.color}1f;color:${ev.color}`" x-text="ev.statusLabel"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-t border-slate-200 px-5 py-3 text-sm text-slate-600">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5">
            <?php foreach ($statusColors as [$hex, $label]): ?>
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-3 w-3 rounded-full" style="background:<?= htmlspecialchars($hex) ?>"></span>
                    <?= htmlspecialchars($label) ?>
                </span>
            <?php endforeach; ?>
        </div>
        <span class="inline-flex items-center gap-1.5 text-slate-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/></svg>
            Click any empty slot to book
        </span>
    </div>

    <!-- Appointment detail popup -->
    <div x-show="selected" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         @keydown.escape.window="closeModal()">
        <div class="absolute inset-0 bg-slate-900/40" @click="closeModal()"></div>
        <div x-show="selected" x-transition class="relative w-full max-w-md rounded-2xl bg-white shadow-xl" @click.stop>
            <template x-if="selected">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <div class="flex items-center gap-2 font-semibold text-slate-800">
                            <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0" stroke-linecap="round"/><circle cx="18" cy="14" r="3.2"/><path d="M18 12.5v1.5l1 1" stroke-linecap="round"/></svg>
                            Appointment
                        </div>
                        <button type="button" @click="closeModal()" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                        </button>
                    </div>

                    <div class="px-5 py-4">
                        <p class="text-lg font-bold text-slate-900" x-text="selected.patient"></p>
                        <p class="text-sm text-slate-500" x-text="selected.subline"></p>
                        <dl class="mt-3 space-y-1.5 text-sm text-slate-700">
                            <div><dt class="inline font-semibold">When:</dt> <span x-text="selected.dateLabel + ' · ' + selected.time"></span></div>
                            <div x-show="selected.phone"><dt class="inline font-semibold">Phone:</dt>
                                <a :href="`tel:${selected.phone}`" class="text-blue-600 hover:underline" x-text="selected.phone"></a></div>
                            <div><dt class="inline font-semibold">Status:</dt>
                                <span class="ml-1 rounded-md px-2 py-0.5 text-xs font-medium text-white" :style="`background:${selected.color}`" x-text="selected.statusLabel"></span></div>
                        </dl>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-5 py-3.5">
                        <template x-if="primaryAction">
                            <button type="button" @click="changeStatus(primaryAction.status)" :disabled="acting"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                                    :style="`background:${primaryAction.color}`">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span x-text="primaryAction.label"></span>
                            </button>
                        </template>
                        <button type="button" x-show="canCancel" @click="changeStatus('cancelled')" :disabled="acting"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200 disabled:opacity-60">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                            Cancel
                        </button>
                        <a :href="`/patients/${selected.patientId}`"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0" stroke-linecap="round"/></svg>
                            Patient
                        </a>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Booking popup -->
    <div x-show="booking.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="closeBooking()">
        <div class="absolute inset-0 bg-slate-900/40" @click="closeBooking()"></div>
        <div x-show="booking.open" x-transition class="relative w-full max-w-md rounded-2xl bg-white shadow-xl" @click.stop>
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <div class="flex items-center gap-2 font-semibold text-slate-800">
                    <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4.5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v3M16 3v3M12 12v4M10 14h4" stroke-linecap="round"/></svg>
                    Book appointment
                </div>
                <button type="button" @click="closeBooking()" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                </button>
            </div>

            <form @submit.prevent="submitBooking()" class="space-y-3 px-5 py-4">
                <!-- Patient -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-600">Patient</label>
                        <button type="button" @click="booking.newMode = !booking.newMode; clearPatient()" class="text-xs font-medium text-blue-600 hover:underline"
                                x-text="booking.newMode ? 'Pick existing' : '+ New patient'"></button>
                    </div>

                    <template x-if="!booking.newMode">
                        <div class="relative">
                            <template x-if="booking.patientId">
                                <div class="flex items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm">
                                    <span x-text="booking.patientLabel"></span>
                                    <button type="button" @click="clearPatient()" class="text-slate-400 hover:text-slate-600">✕</button>
                                </div>
                            </template>
                            <template x-if="!booking.patientId">
                                <div>
                                    <input type="text" x-model="booking.query" @input.debounce.300ms="searchPatients()" placeholder="Search name, phone, UHID…" class="ui-input">
                                    <div x-show="booking.results.length" class="mt-1 max-h-44 overflow-auto rounded-lg border border-slate-200 bg-white shadow-sm">
                                        <template x-for="p in booking.results" :key="p.id">
                                            <button type="button" @click="selectPatient(p)" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-slate-50">
                                                <span class="font-medium text-slate-800" x-text="p.name"></span>
                                                <span class="text-xs text-slate-400" x-text="p.phone"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <p x-show="booking.searching" class="mt-1 text-xs text-slate-400">searching…</p>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="booking.newMode">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" x-model="booking.newName" placeholder="Full name" class="ui-input">
                            <input type="tel" x-model="booking.newPhone" placeholder="Phone" class="ui-input">
                        </div>
                    </template>
                </div>

                <!-- Doctor -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Doctor</label>
                    <select x-model="booking.doctorId" @change="loadSlots()" class="ui-input" <?= $lockDoctorId !== null ? 'disabled' : '' ?>>
                        <option value="">Select doctor</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars((string) ($d['name'] ?? 'Doctor')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date / time / type -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Date</label>
                        <input type="date" x-model="booking.date" @change="loadSlots()" :min="cfg.today" class="ui-input">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600">Time</label>
                        <template x-if="booking.slots.length > 0">
                            <select x-model="booking.time" class="ui-input">
                                <option value="">Select time</option>
                                <template x-for="s in booking.slots" :key="s.datetime">
                                    <option :value="s.time" :disabled="!s.available" x-text="fmtSlotLabel(s.time) + (s.available ? '' : ' — booked')"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="booking.slots.length === 0">
                            <input type="time" x-model="booking.time" class="ui-input">
                        </template>
                        <p x-show="booking.loadingSlots" class="mt-1 text-xs text-slate-400">loading slots…</p>
                        <p x-show="!booking.loadingSlots && booking.noSlots" class="mt-1 text-xs text-slate-400">No preset slots — enter a time.</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Type</label>
                    <select x-model="booking.type" class="ui-input">
                        <option value="prebooked">Pre-booked</option>
                        <option value="walkin">Walk-in</option>
                        <option value="followup">Follow-up</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Reason (optional)</label>
                    <input type="text" x-model="booking.complaint" placeholder="Chief complaint" class="ui-input">
                </div>

                <p x-show="booking.error" x-text="booking.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" @click="closeBooking()" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-200">Cancel</button>
                    <button type="submit" :disabled="booking.saving" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                            x-text="booking.saving ? 'Booking…' : 'Book'"></button>
                </div>
            </form>
        </div>
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
        selected: null, acting: false,
        booking: { open: false },
        startH: cfg.startH, endH: cfg.endH, hourPx: cfg.hourPx, stepMin: cfg.stepMin,
        weekdayNames: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
        gridDays: [], monthCells: [], listGroups: [], rangeLabel: '',

        get viewSubtitle() { return this.view.charAt(0).toUpperCase() + this.view.slice(1) + ' view'; },
        pill(v) { return this.view===v ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'; },
        typeLabel(t) { return ({walkin:'Walk-in', prebooked:'Pre-booked', online:'Online', followup:'Follow-up'})[t] || (t ? t.charAt(0).toUpperCase()+t.slice(1) : 'Appointment'); },

        // --- appointment popup ---
        openEvent(ev) { this.selected = ev; },
        closeModal() { this.selected = null; },
        get primaryAction() {
            if (!this.selected) return null;
            switch (this.selected.status) {
                case 'scheduled':   return {label:'Arrived',       status:'confirmed',   color:'#22c55e'};
                case 'confirmed':   return {label:'Start consult', status:'in_progress', color:'#3b82f6'};
                case 'in_progress': return {label:'Complete',      status:'completed',   color:'#10b981'};
                default: return null;
            }
        },
        get canCancel() { return !!this.selected && !['completed','cancelled'].includes(this.selected.status); },
        async changeStatus(status) {
            if (!this.selected || this.acting) return;
            this.acting = true;
            try {
                const r = await fetch(`/api/v1/appointments/${this.selected.id}/status`, {
                    method:'POST',
                    headers:{'Content-Type':'application/x-www-form-urlencoded', Accept:'application/json'},
                    body: new URLSearchParams({status}),
                });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.ok) {
                    if (status === 'cancelled') { this.closeModal(); }
                    else {
                        const meta = this.cfg.statusMeta[j.status] || {};
                        this.selected.status = j.status;
                        this.selected.statusLabel = meta.label || j.status;
                        this.selected.color = meta.color || this.selected.color;
                    }
                    await this.reload();
                } else {
                    alert(j.error === 'forbidden' ? 'You are not allowed to change this appointment.' : 'Could not update. Please try again.');
                }
            } catch (_) { alert('Network error. Please try again.'); }
            this.acting = false;
        },
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
            else d.setMonth(d.getMonth()+dir);
            this.anchor=this.ymd(d);
            this.reload();
        },

        fetchRange() {
            const a=this.parse(this.anchor+'T00:00');
            if (this.view==='day') return [a, a];
            if (this.view==='week') { const mon=this.monday(a); const sun=new Date(mon); sun.setDate(mon.getDate()+6); return [mon, sun]; }
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

        model(e) {
            const s=this.parse(e.start);
            const meta=this.cfg.statusMeta[e.status] || {color: e.backgroundColor||'#64748b', label: e.status||''};
            const tl=this.typeLabel(e.type);
            const subline = (e.token!=null ? `Token #${e.token} · ` : '') + tl;
            return {
                id:e.id, url:e.url, start:s, iso:this.ymd(s),
                color: e.backgroundColor || meta.color,
                patient: e.patient || (e.title||'').split(' — ')[0] || 'Patient',
                patientId: e.patient_id || 0, phone: e.phone || '',
                doctor: e.doctor || '', status: e.status, statusLabel: meta.label,
                type: e.type || '', subline,
                time: this.fmtTime(s), dateLabel: s.toLocaleDateString(undefined,{weekday:'short',day:'numeric',month:'short',year:'numeric'}),
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
                cells.push({ iso, dnum:d.getDate(), inMonth:d.getMonth()===a.getMonth(),
                    isToday:iso===this.cfg.today, past:this.parse(iso+'T23:59')<this.now, events:dayEvents });
            }
            this.monthCells=cells;
        },

        buildList(models) {
            const a=this.parse(this.anchor+'T00:00');
            const inMonth=models.filter(m=>m.start.getMonth()===a.getMonth() && m.start.getFullYear()===a.getFullYear()).sort((x,y)=>x.start-y.start);
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

        book(iso, time) { this.openBooking({date:iso, time}); },
        bookDay(iso) { this.openBooking({date:iso}); },

        // --- booking popup ---
        openBooking(opts) {
            this.booking = {
                open: true, date: opts.date || this.cfg.today, time: opts.time || '',
                doctorId: this.doctorId || (this.cfg.lockDoctorId ? String(this.cfg.lockDoctorId) : (this.cfg.doctors.length===1 ? String(this.cfg.doctors[0].id) : '')),
                type: 'prebooked', complaint: '',
                newMode: false, newName: '', newPhone: '',
                patientId: '', patientLabel: '', query: '', results: [], searching: false,
                slots: [], noSlots: false, loadingSlots: false,
                saving: false, error: '',
            };
            this.loadSlots();
        },
        closeBooking() { this.booking.open = false; },
        async loadSlots() {
            const b = this.booking;
            const wanted = b.time; // time the user clicked on the week/day grid
            b.slots = []; b.noSlots = false;
            if (!b.doctorId || !b.date) return;
            b.loadingSlots = true;
            try {
                const r = await fetch(`/api/v1/slots?doctor_id=${encodeURIComponent(b.doctorId)}&date=${encodeURIComponent(b.date)}`, {headers:{Accept:'application/json'}});
                const j = r.ok ? await r.json() : {slots:[]};
                let slots = j.slots || [];
                // Honour a time the user explicitly clicked on the grid even if it
                // isn't one of the preset slots — inject it so it stays selected.
                if (wanted && !slots.some(s => s.time === wanted)) {
                    slots = [...slots, {time: wanted, datetime: b.date + ' ' + wanted + ':00', available: true}]
                        .sort((a, z) => a.time.localeCompare(z.time));
                }
                b.slots = slots;
                b.noSlots = slots.length === 0;
                // b.time is left as-is so the clicked slot stays selected.
            } catch (_) { b.slots = []; b.noSlots = true; }
            b.loadingSlots = false;
        },
        fmtSlotLabel(hhmm) { const [h,m] = (hhmm||'').split(':').map(Number); const ap = h<12?'AM':'PM'; const hh = h%12===0?12:h%12; return hh+':'+String(m).padStart(2,'0')+' '+ap; },
        selectPatient(p) {
            this.booking.patientId = String(p.id);
            this.booking.patientLabel = p.name + (p.phone ? ' · ' + p.phone : '');
            this.booking.results = []; this.booking.query = '';
        },
        clearPatient() { this.booking.patientId=''; this.booking.patientLabel=''; },
        async searchPatients() {
            const q = this.booking.query.trim();
            if (q.length < 2) { this.booking.results = []; return; }
            this.booking.searching = true;
            try {
                const r = await fetch('/api/v1/patients/search?q=' + encodeURIComponent(q), {headers:{Accept:'application/json'}});
                const j = r.ok ? await r.json() : {rows:[]};
                this.booking.results = (j.rows || []).slice(0, 6);
            } catch (_) { this.booking.results = []; }
            this.booking.searching = false;
        },
        async submitBooking() {
            const b = this.booking;
            if (b.saving) return;
            b.error = '';
            if (!b.doctorId) { b.error = 'Please select a doctor.'; return; }
            if (!b.date || !b.time) { b.error = 'Please choose a date and time.'; return; }
            if (b.newMode) { if (!b.newName.trim() || !b.newPhone.trim()) { b.error = 'Enter the new patient name and phone.'; return; } }
            else if (!b.patientId) { b.error = 'Please select a patient.'; return; }

            const body = new URLSearchParams({
                doctor_id: b.doctorId, scheduled_date: b.date, scheduled_time: b.time,
                type: b.type, chief_complaint: b.complaint,
            });
            if (b.newMode) { body.set('new_patient_name', b.newName); body.set('new_patient_phone', b.newPhone); }
            else { body.set('patient_id', b.patientId); }

            b.saving = true;
            try {
                const r = await fetch('/api/v1/appointments/book', {
                    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded', Accept:'application/json'}, body,
                });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.ok) { b.open = false; await this.reload(); }
                else { b.error = j.error || 'Could not book. Please try again.'; }
            } catch (_) { b.error = 'Network error. Please try again.'; }
            b.saving = false;
        },

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
