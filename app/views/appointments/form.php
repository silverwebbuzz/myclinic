<?php
$isEdit = $appointment !== null;
$action = $isEdit ? '/appointments/' . (int) $appointment['id'] : '/appointments/new';
$scheduledAt = $isEdit ? ($appointment['scheduled_at'] ?? '') : '';
$scheduledTs = $scheduledAt !== '' ? strtotime($scheduledAt) : false;
$dateVal = ($isEdit && $scheduledTs) ? date('Y-m-d', $scheduledTs) : ($prefill['date'] ?? date('Y-m-d'));
$timeVal = ($isEdit && $scheduledTs) ? date('H:i', $scheduledTs) : '';

$patientId = 0;
$patientLabel = '';
$patientPhone = '';
if ($isEdit) {
    $patientId = (int) $appointment['patient_id'];
    $patientLabel = ($appointment['patient_name'] ?? '') . ' · ' . ($appointment['uhid'] ?? '');
    $patientPhone = (string) ($appointment['patient_phone'] ?? '');
} elseif (!empty($patientHint)) {
    $patientId = (int) $patientHint['id'];
    $patientLabel = ($patientHint['name'] ?? '') . ' · ' . ($patientHint['uhid'] ?? '');
    $patientPhone = (string) ($patientHint['phone'] ?? '');
} elseif (!empty($prefill['patient_id'])) {
    $patientId = (int) $prefill['patient_id'];
}

$doctorId = $isEdit ? (int) $appointment['doctor_id'] : (int) ($prefill['doctor_id'] ?? 0);
$type = $isEdit ? ($appointment['type'] ?? 'prebooked') : ($prefill['type'] ?? 'prebooked');
$complaint = $isEdit ? ($appointment['chief_complaint'] ?? '') : ($prefill['chief_complaint'] ?? '');
$isFollowup = !empty($appointment['is_followup']) || !empty($prefill['is_followup']);
?>
<div class="mx-auto max-w-3xl space-y-4" x-data="bookAppointment(<?= htmlspecialchars(json_encode([
    'patientId' => $patientId,
    'patientLabel' => $patientLabel,
    'patientPhone' => $patientPhone,
    'doctorId' => $doctorId,
    'date' => $dateVal,
    'selectedTime' => $timeVal,
    'apptType' => $type,
    'isEdit' => $isEdit,
]), ENT_QUOTES) ?>)">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="flex items-center gap-2 ui-section-title"><span class="text-brand"><?= ui_icon('appointments', 18) ?></span><?= $isEdit ? 'Edit appointment' : 'Book appointment' ?></h2>
        <a href="/appointments" class="text-sm text-slate-500 hover:underline">← Back</a>
    </div>

    <?php if (!empty($error)): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="space-y-5 ui-card ui-card-pad">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="patient_id" x-model="patientId">

        <!-- Patient picker (only when creating; edit shows static label) -->
        <?php if ($isEdit): ?>
        <div>
            <p class="text-sm font-medium text-slate-700">Patient</p>
            <p class="mt-1 text-slate-900"><?= htmlspecialchars($patientLabel) ?></p>
        </div>
        <?php else: ?>
        <div>
            <label class="block text-sm font-medium text-slate-700">Patient search</label>
            <div class="relative mt-1">
                <input type="search" x-model="patientQuery" @input.debounce.300ms="searchPatients()" @focus="searchPatients()"
                       :placeholder="patientId ? '' : 'Search name, phone, UHID…'"
                       :disabled="patientId > 0"
                       class="ui-input disabled:bg-slate-100">
                <button type="button" x-show="patientId > 0" @click="clearPatient()"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded border px-2 py-0.5 text-xs hover:bg-slate-50">
                    Clear
                </button>
                <ul x-show="suggestions.length > 0 && patientId === 0" x-transition.opacity
                    class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border bg-white shadow-lg">
                    <template x-for="p in suggestions" :key="p.id">
                        <li>
                            <button type="button" @click="pickPatient(p)"
                                    class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-emerald-50">
                                <span>
                                    <span class="font-medium" x-text="p.name"></span>
                                    <span class="text-slate-500" x-text="p.phone"></span>
                                </span>
                                <span class="font-mono text-xs text-slate-400" x-text="p.uhid"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
            <div x-show="patientId > 0" x-transition.opacity
                 class="mt-2 flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm">
                <span class="text-emerald-700"><?= ui_icon('patients', 15) ?></span>
                <span class="font-medium text-emerald-900" x-text="patientLabel"></span>
                <span class="text-emerald-700" x-text="patientPhone"></span>
            </div>
            <p x-show="patientId === 0" class="mt-1 text-xs text-slate-500">Leave empty to register a new walk-in patient below.</p>
        </div>

        <!-- Inline new-patient fields (visible when no patient picked) -->
        <div x-show="patientId === 0" x-transition.opacity class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">New patient name</span>
                <input type="text" name="new_patient_name" x-model="newName"
                       class="ui-input" placeholder="Full name">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Phone</span>
                <input type="tel" name="new_patient_phone" x-model="newPhone"
                       class="ui-input" placeholder="Contact number">
            </label>
        </div>
        <?php endif; ?>

        <!-- Doctor + Date row -->
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Doctor</span>
                <select name="doctor_id" x-model="doctorId" @change="loadSlots()" required
                        class="ui-input">
                    <option value="">Select doctor</option>
                    <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Date</span>
                <input type="date" name="scheduled_date" x-model="date" @change="loadSlots()" required
                       class="ui-input">
            </label>
        </div>

        <!-- Type -->
        <label class="block text-sm">
            <span class="text-slate-600">Type</span>
            <select name="type" x-model="apptType" class="ui-input">
                <?php foreach (['prebooked' => 'Pre-booked', 'walkin' => 'Walk-in', 'online' => 'Online (telemedicine)', 'followup' => 'Follow-up'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $type === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($hasTelemedicine)): ?>
            <p class="mt-1 text-xs text-slate-500">Online appointments receive a Google Meet link (stub) via WhatsApp and email when booked or confirmed.</p>
            <?php endif; ?>
        </label>

        <!-- Time slot grid -->
        <div>
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-700">
                    Time slot
                    <span class="ml-1 text-xs font-normal text-slate-500">
                        (<span x-show="apptType === 'walkin'">optional — assign to avoid overlap</span><span x-show="apptType !== 'walkin'">required</span>)
                    </span>
                </span>
                <span x-show="slotsLoading" class="text-xs text-slate-500">Loading…</span>
            </div>
            <input type="hidden" name="scheduled_time" :value="selectedTime">

            <div x-show="!doctorId" class="mt-2 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                Select a doctor and date to see available slots.
            </div>

            <div x-show="doctorId && morningSlots.length === 0 && eveningSlots.length === 0 && !slotsLoading"
                 class="mt-2 rounded-lg bg-amber-50 p-3">
                <p class="text-xs text-amber-900">
                    No working hours configured for this doctor on the selected day.
                    Set hours in <a href="/scheduling" class="underline">Scheduling</a> first, or enter a manual time below.
                </p>
                <input type="time" x-model="selectedTime"
                       class="mt-2 w-full max-w-[200px] ui-input">
            </div>

            <div x-show="morningSlots.length > 0" class="mt-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">☀️ Morning</p>
                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                    <template x-for="slot in morningSlots" :key="slot.datetime">
                        <button type="button"
                                :disabled="!slot.available"
                                @click="selectedTime = slot.time"
                                :title="slot.extended ? 'Extended hours (walk-in only)' : ''"
                                :class="selectedTime === slot.time
                                    ? 'bg-emerald-600 text-white shadow'
                                    : (!slot.available ? 'border bg-slate-100 text-slate-400 cursor-not-allowed line-through'
                                        : (slot.extended ? 'border-2 border-red-300 bg-red-50 text-red-700 hover:bg-red-100' : 'border bg-white hover:bg-emerald-50'))"
                                class="rounded-lg px-2 py-2 text-xs font-medium"
                                x-text="slot.extended ? '🚨 ' + slot.label : slot.label"></button>
                    </template>
                </div>
            </div>

            <div x-show="eveningSlots.length > 0" class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">🌙 Evening</p>
                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6">
                    <template x-for="slot in eveningSlots" :key="slot.datetime">
                        <button type="button"
                                :disabled="!slot.available"
                                @click="selectedTime = slot.time"
                                :title="slot.extended ? 'Extended hours (walk-in only)' : ''"
                                :class="selectedTime === slot.time
                                    ? 'bg-emerald-600 text-white shadow'
                                    : (!slot.available ? 'border bg-slate-100 text-slate-400 cursor-not-allowed line-through'
                                        : (slot.extended ? 'border-2 border-red-300 bg-red-50 text-red-700 hover:bg-red-100' : 'border bg-white hover:bg-emerald-50'))"
                                class="rounded-lg px-2 py-2 text-xs font-medium"
                                x-text="slot.extended ? '🚨 ' + slot.label : slot.label"></button>
                    </template>
                </div>
            </div>

            <p x-show="apptType === 'walkin' && (morningSlots.length || eveningSlots.length)" class="mt-3 text-xs text-slate-500">
                Walk-ins without a slot join the queue after pre-booked patients for that time.
            </p>
        </div>

        <!-- Complaint + follow-up -->
        <label class="block text-sm">
            <span class="text-slate-600">Chief complaint</span>
            <textarea name="chief_complaint" rows="2" placeholder="Reason for visit"
                      class="ui-input"><?= htmlspecialchars($complaint) ?></textarea>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_followup" value="1" <?= $isFollowup ? 'checked' : '' ?>>
            Follow-up visit
        </label>

        <!-- Submit -->
        <div class="flex flex-wrap gap-3 border-t pt-4">
            <button type="submit" class="ui-btn ui-btn-primary">
                <?= $isEdit ? 'Save changes' : (($type === 'walkin') ? 'Generate token' : 'Book appointment') ?>
            </button>
            <a href="/appointments" class="ui-btn ui-btn-secondary">Cancel</a>
        </div>
    </form>

    <?php if ($isEdit && ($appointment['status'] ?? '') !== 'cancelled'): ?>
    <form method="post" action="/appointments/<?= (int) $appointment['id'] ?>/cancel"
          class="rounded-xl border border-red-100 bg-red-50 p-4"
          onsubmit="return confirm('Cancel this appointment?')">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Cancel appointment</button>
    </form>
    <?php endif; ?>
</div>

<script>
function bookAppointment(cfg) {
    return {
        patientId: cfg.patientId || 0,
        patientLabel: cfg.patientLabel || '',
        patientPhone: cfg.patientPhone || '',
        patientQuery: '',
        suggestions: [],
        newName: '',
        newPhone: '',
        doctorId: cfg.doctorId ? String(cfg.doctorId) : '',
        date: cfg.date || '',
        selectedTime: cfg.selectedTime || '',
        apptType: cfg.apptType || 'prebooked',
        slots: [],
        morningSlots: [],
        eveningSlots: [],
        slotsLoading: false,
        slotTimer: null,
        init() {
            if (this.apptType === 'walkin' && !this.selectedTime) {
                this.selectedTime = this._nowTime();
            }
            this.$watch('apptType', (val) => {
                if (val === 'walkin' && !this.selectedTime) {
                    this.selectedTime = this._nowTime();
                }
            });
            if (this.doctorId && this.date) this.loadSlots();
            this.slotTimer = setInterval(() => this.loadSlots(), 60000);
        },
        _nowTime() {
            const n = new Date();
            return String(n.getHours()).padStart(2, '0') + ':' + String(n.getMinutes()).padStart(2, '0');
        },
        async searchPatients() {
            if (this.patientQuery.length < 2) { this.suggestions = []; return; }
            try {
                const r = await fetch('/api/v1/patients/search?q=' + encodeURIComponent(this.patientQuery), { credentials: 'same-origin' });
                if (!r.ok) { this.suggestions = []; return; }
                const data = await r.json();
                this.suggestions = (data.rows || []).slice(0, 8);
            } catch (e) {
                this.suggestions = [];
            }
        },
        pickPatient(p) {
            this.patientId = p.id;
            this.patientLabel = p.name + ' · ' + (p.uhid || '');
            this.patientPhone = p.phone || '';
            this.patientQuery = '';
            this.suggestions = [];
            this.newName = '';
            this.newPhone = '';
        },
        clearPatient() {
            this.patientId = 0;
            this.patientLabel = '';
            this.patientPhone = '';
            this.patientQuery = '';
            this.suggestions = [];
        },
        async loadSlots() {
            if (!this.doctorId || !this.date) {
                this.morningSlots = []; this.eveningSlots = [];
                return;
            }
            this.slotsLoading = true;
            try {
                const r = await fetch('/api/v1/slots?doctor_id=' + this.doctorId + '&date=' + this.date, { credentials: 'same-origin' });
                const data = await r.json();
                const all = (data.slots || []).map(s => ({
                    ...s,
                    label: this._formatTime(s.time),
                    hour: parseInt(s.time.split(':')[0], 10),
                }));
                this.morningSlots = all.filter(s => s.hour < 13);
                this.eveningSlots = all.filter(s => s.hour >= 13);
            } catch (e) {
                this.morningSlots = []; this.eveningSlots = [];
            } finally {
                this.slotsLoading = false;
            }
        },
        _formatTime(hhmm) {
            const [h, m] = hhmm.split(':').map(n => parseInt(n, 10));
            const period = h >= 12 ? 'PM' : 'AM';
            const h12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
            return h12 + ':' + String(m).padStart(2, '0') + ' ' + period;
        },
    };
}
</script>
