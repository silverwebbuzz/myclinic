<?php
$isEdit = $appointment !== null;
$action = $isEdit ? '/appointments/' . (int) $appointment['id'] : '/appointments/new';
$scheduledAt = $isEdit ? ($appointment['scheduled_at'] ?? '') : '';
$dateVal = $isEdit ? date('Y-m-d', strtotime($scheduledAt)) : ($prefill['date'] ?? date('Y-m-d'));
$timeVal = $isEdit ? date('H:i', strtotime($scheduledAt)) : '';
$patientId = $isEdit ? (int) $appointment['patient_id'] : (int) ($prefill['patient_id'] ?? 0);
$patientLabel = $isEdit ? ($appointment['patient_name'] . ' · ' . $appointment['uhid']) : '';
$doctorId = $isEdit ? (int) $appointment['doctor_id'] : (int) ($prefill['doctor_id'] ?? 0);
$type = $isEdit ? ($appointment['type'] ?? 'prebooked') : ($prefill['type'] ?? 'prebooked');
?>
<div class="mx-auto max-w-2xl space-y-4" x-data="bookAppointment(<?= json_encode([
    'patientId' => $patientId,
    'patientLabel' => $patientLabel,
    'doctorId' => $doctorId,
    'date' => $dateVal,
    'selectedTime' => $timeVal,
    'isEdit' => $isEdit,
]) ?>)">
    <h2 class="text-lg font-semibold"><?= $isEdit ? 'Edit appointment' : 'Book appointment' ?></h2>

    <?php if (!empty($error)): ?>
    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="space-y-4 rounded-xl border bg-white p-6">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <input type="hidden" name="patient_id" x-model="patientId">

        <label class="block text-sm">
            <span class="text-slate-600">Patient</span>
            <?php if ($isEdit): ?>
            <p class="mt-1 font-medium"><?= htmlspecialchars($patientLabel) ?></p>
            <?php else: ?>
            <input type="search" x-model="patientQuery" @input.debounce.300ms="searchPatients()"
                   placeholder="Search name, phone, UHID…"
                   class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            <ul x-show="suggestions.length" class="mt-1 max-h-40 overflow-y-auto rounded-lg border bg-white shadow">
                <template x-for="p in suggestions" :key="p.id">
                    <li>
                        <button type="button" @click="pickPatient(p)"
                                class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                                x-text="p.name + ' · ' + p.uhid + ' · ' + p.phone"></button>
                    </li>
                </template>
            </ul>
            <p x-show="patientLabel" class="mt-1 text-sm text-emerald-700" x-text="patientLabel"></p>
            <?php endif; ?>
        </label>

        <label class="block text-sm">
            <span class="text-slate-600">Doctor</span>
            <select name="doctor_id" x-model="doctorId" @change="loadSlots()" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <option value="">Select doctor</option>
                <?php foreach ($doctors as $doc): ?>
                <option value="<?= (int) $doc['id'] ?>"><?= htmlspecialchars($doc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block text-sm">
            <span class="text-slate-600">Date</span>
            <input type="date" name="scheduled_date" x-model="date" @change="loadSlots()" required
                   class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        </label>

        <div class="text-sm">
            <span class="text-slate-600">Time slot</span>
            <input type="hidden" name="scheduled_time" x-model="selectedTime">
            <p class="mt-1 text-xs text-slate-500">Refreshes every 60s</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <template x-for="slot in slots" :key="slot.datetime">
                    <button type="button"
                            :disabled="!slot.available"
                            @click="selectedTime = slot.time"
                            :class="selectedTime === slot.time ? 'bg-emerald-600 text-white' : (slot.available ? 'border hover:bg-slate-50' : 'border bg-slate-100 text-slate-400 cursor-not-allowed')"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium"
                            x-text="slot.time"></button>
                </template>
                <p x-show="slots.length === 0 && doctorId" class="text-xs text-slate-500">No slots for this day.</p>
            </div>
        </div>

        <label class="block text-sm">
            <span class="text-slate-600">Type</span>
            <select name="type" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <?php foreach (['prebooked' => 'Pre-booked', 'walkin' => 'Walk-in', 'online' => 'Online (telemedicine)', 'followup' => 'Follow-up'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $type === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($hasTelemedicine)): ?>
            <p class="mt-1 text-xs text-slate-500">Online appointments receive a Google Meet link (stub) via WhatsApp and email when booked or confirmed.</p>
            <?php endif; ?>
        </label>

        <label class="block text-sm">
            <span class="text-slate-600">Chief complaint</span>
            <textarea name="chief_complaint" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"><?= htmlspecialchars($appointment['chief_complaint'] ?? '') ?></textarea>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_followup" value="1" <?= !empty($appointment['is_followup']) || !empty($prefill['is_followup']) ? 'checked' : '' ?>>
            Follow-up visit
        </label>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                <?= $isEdit ? 'Save changes' : 'Book appointment' ?>
            </button>
            <a href="/appointments" class="rounded-lg border px-4 py-2 text-sm">Cancel</a>
        </div>

    </form>

    <?php if ($isEdit && ($appointment['status'] ?? '') !== 'cancelled'): ?>
    <form method="post" action="/appointments/<?= (int) $appointment['id'] ?>/cancel" class="mt-4 rounded-xl border border-red-100 bg-red-50 p-4"
          onsubmit="return confirm('Cancel this appointment?')">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <button type="submit" class="text-sm text-red-600 hover:underline">Cancel appointment</button>
    </form>
    <?php endif; ?>
</div>

<script>
function bookAppointment(cfg) {
    return {
        patientId: cfg.patientId || '',
        patientLabel: cfg.patientLabel || '',
        patientQuery: '',
        suggestions: [],
        doctorId: cfg.doctorId ? String(cfg.doctorId) : '',
        date: cfg.date || '',
        selectedTime: cfg.selectedTime || '',
        slots: [],
        slotTimer: null,
        init() {
            if (this.doctorId && this.date) this.loadSlots();
            this.slotTimer = setInterval(() => this.loadSlots(), 60000);
        },
        async searchPatients() {
            if (this.patientQuery.length < 2) { this.suggestions = []; return; }
            const r = await fetch('/api/v1/patients/search?q=' + encodeURIComponent(this.patientQuery));
            const data = await r.json();
            this.suggestions = data.rows || data.patients || [];
        },
        pickPatient(p) {
            this.patientId = p.id;
            this.patientLabel = p.name + ' · ' + p.uhid;
            this.suggestions = [];
            this.patientQuery = '';
        },
        async loadSlots() {
            if (!this.doctorId || !this.date) return;
            const r = await fetch('/api/v1/slots?doctor_id=' + this.doctorId + '&date=' + this.date);
            const data = await r.json();
            this.slots = data.slots || [];
        },
    };
}
</script>
