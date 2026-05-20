<?php
$brandColor = $clinic['brand_color'] ?? '#2563eb';
$clinicName = $clinic['name'] ?? 'Clinic';
$isConfirmed = !empty($confirmation);
$bookingError = $error ?? null;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book — <?= htmlspecialchars($clinicName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>:root { --brand: <?= htmlspecialchars($brandColor) ?>; } .bg-brand{background:var(--brand);} .text-brand{color:var(--brand);} .border-brand{border-color:var(--brand);} .ring-brand:focus{--tw-ring-color:var(--brand);}</style>
</head>
<body class="min-h-screen bg-slate-50 p-4 sm:p-8">
<div class="mx-auto max-w-2xl">
    <div class="overflow-hidden rounded-2xl bg-white shadow-lg">
        <div class="bg-brand p-6 text-white">
            <h1 class="flex items-center gap-2 text-xl font-bold">📅 Book Appointment</h1>
            <p class="mt-1 text-sm opacity-90"><?= htmlspecialchars($clinicName) ?> — Online Booking</p>
        </div>

        <?php if ($isConfirmed): ?>
        <!-- STEP 3: Confirmed -->
        <div class="p-6">
            <?php require __DIR__ . '/_stepper.php'; ?>

            <div class="mt-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <?php if (!empty($confirmation['token'])): ?>
                <p class="mt-5 text-6xl font-bold text-brand"><?= (int) $confirmation['token'] ?></p>
                <p class="mt-1 text-xs uppercase tracking-wider text-slate-500">Your queue token number</p>
                <?php else: ?>
                <p class="mt-5 text-lg font-semibold text-emerald-600">Appointment confirmed</p>
                <?php endif; ?>
            </div>

            <dl class="mt-6 overflow-hidden rounded-xl border bg-slate-50">
                <div class="flex items-center justify-between border-b px-4 py-3 text-sm">
                    <dt class="text-slate-500">Patient</dt>
                    <dd class="font-semibold uppercase"><?= htmlspecialchars($confirmation['patient_name']) ?></dd>
                </div>
                <div class="flex items-center justify-between border-b px-4 py-3 text-sm">
                    <dt class="text-slate-500">Date</dt>
                    <dd class="font-semibold"><?= htmlspecialchars($confirmation['date']) ?></dd>
                </div>
                <div class="flex items-center justify-between border-b px-4 py-3 text-sm">
                    <dt class="text-slate-500">Time Slot</dt>
                    <dd class="font-semibold"><?= htmlspecialchars($confirmation['time']) ?></dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3 text-sm">
                    <dt class="text-slate-500">Appointment ID</dt>
                    <dd class="font-mono font-semibold">#<?= (int) $confirmation['appointment_id'] ?></dd>
                </div>
            </dl>

            <div class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                ⚠️ Please arrive 10 minutes before your slot and show this token number at reception.
            </div>

            <a href="/book/<?= htmlspecialchars($slug) ?>"
               class="mt-6 block w-full rounded-lg bg-brand py-3 text-center text-sm font-semibold text-white hover:opacity-90">
                Book Another Appointment
            </a>
        </div>

        <?php else: ?>
        <!-- STEPS 1 & 2: Wizard -->
        <div class="p-6" x-data="bookingWizard()" x-init="init()">
            <?php require __DIR__ . '/_stepper.php'; ?>

            <?php if ($bookingError): ?>
            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                ⚠️ <?= htmlspecialchars($bookingError) ?>
            </p>
            <?php endif; ?>

            <form method="post" action="/book/<?= htmlspecialchars($slug) ?>" @submit="submitting = true">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="doctor_id" :value="doctorId">
                <input type="hidden" name="scheduled_at" :value="selectedSlot">

                <!-- ============ STEP 1: Date & Slot ============ -->
                <div x-show="step === 1" class="mt-6 space-y-5">
                    <h2 class="text-base font-semibold">Select Date & Time Slot</h2>

                    <?php if (count($doctors) > 1): ?>
                    <label class="block text-sm">
                        <span class="text-slate-600">Doctor</span>
                        <select x-model="doctorId" @change="loadSlots()" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                            <?php foreach ($doctors as $d): ?>
                            <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php endif; ?>

                    <!-- Day strip -->
                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-7">
                        <?php foreach ($days as $d): ?>
                        <button type="button"
                                @click="selectDate('<?= htmlspecialchars($d['date']) ?>')"
                                :class="selectedDate === '<?= htmlspecialchars($d['date']) ?>' ? 'border-brand bg-brand/5 ring-2 ring-brand' : 'border-slate-200 hover:border-slate-300'"
                                <?= $d['within_window'] ? '' : 'disabled' ?>
                                class="rounded-xl border-2 p-2 text-center transition disabled:opacity-40 disabled:cursor-not-allowed">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-slate-500"><?= htmlspecialchars($d['weekday']) ?></div>
                            <div class="text-lg font-bold leading-tight <?= $d['is_today'] ? 'text-brand' : 'text-slate-800' ?>"><?= (int) $d['day'] ?></div>
                            <div class="text-[10px] text-slate-500"><?= htmlspecialchars($d['month']) ?></div>
                            <?php if (!$d['within_window']): ?>
                            <div class="mt-0.5 text-[9px] uppercase text-red-500">Closed</div>
                            <?php endif; ?>
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Slot sections -->
                    <div x-show="loadingSlots" class="rounded-lg bg-slate-50 px-3 py-4 text-center text-sm text-slate-500">Loading slots…</div>
                    <div x-show="!loadingSlots && morningSlots.length === 0 && eveningSlots.length === 0"
                         class="rounded-lg bg-amber-50 px-3 py-4 text-center text-sm text-amber-900">
                        No slots available for this day. Please pick another date.
                    </div>

                    <div x-show="morningSlots.length > 0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">☀️ Morning</p>
                        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <template x-for="s in morningSlots" :key="s.datetime">
                                <button type="button"
                                        :disabled="!s.available"
                                        @click="selectedSlot = s.datetime; selectedSlotLabel = s.label"
                                        :class="selectedSlot === s.datetime
                                            ? 'bg-brand text-white border-brand'
                                            : (s.available ? 'border-slate-200 hover:border-brand bg-white' : 'border-slate-200 bg-slate-100 text-slate-400 line-through cursor-not-allowed')"
                                        class="rounded-lg border-2 px-2 py-2 text-sm font-semibold"
                                        x-text="s.label"></button>
                            </template>
                        </div>
                    </div>

                    <div x-show="eveningSlots.length > 0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">🌙 Evening</p>
                        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <template x-for="s in eveningSlots" :key="s.datetime">
                                <button type="button"
                                        :disabled="!s.available"
                                        @click="selectedSlot = s.datetime; selectedSlotLabel = s.label"
                                        :class="selectedSlot === s.datetime
                                            ? 'bg-brand text-white border-brand'
                                            : (s.available ? 'border-slate-200 hover:border-brand bg-white' : 'border-slate-200 bg-slate-100 text-slate-400 line-through cursor-not-allowed')"
                                        class="rounded-lg border-2 px-2 py-2 text-sm font-semibold"
                                        x-text="s.label"></button>
                            </template>
                        </div>
                    </div>

                    <button type="button" @click="goNext()" :disabled="!selectedSlot"
                            class="w-full rounded-lg bg-brand py-3 text-sm font-semibold text-white hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40">
                        Next →
                    </button>
                </div>

                <!-- ============ STEP 2: Your Details ============ -->
                <div x-show="step === 2" class="mt-6 space-y-4">
                    <h2 class="text-base font-semibold">Your Details</h2>

                    <label class="block text-sm">
                        <span class="text-slate-700 font-medium">Mobile Number <span class="text-red-500">*</span></span>
                        <div class="mt-1 flex gap-2">
                            <input type="tel" name="phone" x-model="phone" required inputmode="numeric"
                                   placeholder="10-digit mobile"
                                   class="flex-1 rounded-lg border px-3 py-2 text-sm">
                            <button type="button" @click="lookupPatient()" :disabled="!phone || phone.length < 6"
                                    class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-40">
                                🔍 Find
                            </button>
                        </div>
                        <span class="mt-1 block text-xs text-slate-500">We'll check if you're already registered.</span>
                    </label>

                    <template x-if="lookupResult === 'found'">
                        <div class="rounded-lg bg-emerald-50 px-4 py-3 text-sm">
                            <p class="font-semibold text-emerald-800">👤 Found: <span x-text="foundName"></span></p>
                            <p class="text-xs text-emerald-700">Your details are pre-filled below.</p>
                        </div>
                    </template>
                    <template x-if="lookupResult === 'not_found'">
                        <div class="rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-800">
                            🆕 New patient — please enter your name below.
                        </div>
                    </template>

                    <label class="block text-sm">
                        <span class="text-slate-700 font-medium">Full Name <span class="text-red-500">*</span></span>
                        <input type="text" name="name" x-model="name" required
                               placeholder="Your full name"
                               class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                    </label>

                    <label class="block text-sm">
                        <span class="text-slate-700 font-medium">Reason for Visit <span class="text-slate-400 text-xs">(optional)</span></span>
                        <input type="text" name="chief_complaint"
                               placeholder="e.g. Fever, Back pain"
                               class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                    </label>

                    <label class="block text-sm">
                        <span class="text-slate-700 font-medium">Visit Type</span>
                        <select name="is_followup" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                            <option value="">First / Regular Visit</option>
                            <option value="1">Follow-up</option>
                        </select>
                    </label>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="step = 1"
                                class="rounded-lg border px-4 py-3 text-sm font-medium hover:bg-slate-50">
                            ← Back
                        </button>
                        <button type="submit" :disabled="submitting || !name || !phone"
                                class="flex-1 rounded-lg bg-brand py-3 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50">
                            <span x-show="!submitting">Confirm Booking →</span>
                            <span x-show="submitting">Booking…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <p class="mt-4 text-center text-xs text-slate-500">
        Need help? Call the clinic directly.
    </p>
</div>

<script>
function bookingWizard() {
    return {
        step: 1,
        doctorId: '<?= (int) ($doctorId ?? 0) ?>',
        selectedDate: '<?= htmlspecialchars($days[0]['date'] ?? date('Y-m-d')) ?>',
        selectedSlot: '',
        selectedSlotLabel: '',
        morningSlots: [],
        eveningSlots: [],
        loadingSlots: false,
        phone: '',
        name: '',
        foundName: '',
        lookupResult: null,
        submitting: false,
        init() {
            this.loadSlots();
        },
        selectDate(d) {
            this.selectedDate = d;
            this.selectedSlot = '';
            this.loadSlots();
        },
        goNext() {
            if (!this.selectedSlot) return;
            this.step = 2;
        },
        async loadSlots() {
            if (!this.doctorId || !this.selectedDate) return;
            this.loadingSlots = true;
            try {
                const r = await fetch('/book/<?= htmlspecialchars($slug) ?>/slots?doctor_id=' + this.doctorId + '&date=' + this.selectedDate);
                const data = await r.json();
                const all = (data.slots || []).map(s => ({
                    ...s,
                    label: this._formatTime(s.time),
                    hour: parseInt(s.time.split(':')[0], 10),
                }));
                this.morningSlots = all.filter(s => s.hour < 13);
                this.eveningSlots = all.filter(s => s.hour >= 13);
            } catch (e) {
                this.morningSlots = [];
                this.eveningSlots = [];
            } finally {
                this.loadingSlots = false;
            }
        },
        async lookupPatient() {
            if (!this.phone || this.phone.length < 6) return;
            try {
                const r = await fetch('/book/<?= htmlspecialchars($slug) ?>/lookup?phone=' + encodeURIComponent(this.phone));
                const data = await r.json();
                if (data.found) {
                    this.foundName = data.name;
                    this.name = data.name;
                    this.lookupResult = 'found';
                } else {
                    this.lookupResult = 'not_found';
                }
            } catch (e) {
                this.lookupResult = null;
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
</body>
</html>
