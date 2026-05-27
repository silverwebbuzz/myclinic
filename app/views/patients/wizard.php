<?php
$p = $payload ?? [];
$editId = $editId ?? null;
$action = $editId ? '/patients/' . $editId : '/patients/new';
$spec = $specialty ?? 'gp';
$sp = is_array($p['specialty_data'] ?? null) ? $p['specialty_data'] : [];
?>
<div x-data="patientWizard(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>, <?= (int) ($editId ?? 0) ?>)" x-init="startDraftTimer()" class="mx-auto max-w-2xl">
    <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ============ STEP 0: Phone lookup (NEW patients only) ============ -->
    <div x-show="step === 0" x-cloak class="mx-auto max-w-lg">
        <div class="rounded-2xl border bg-white p-8 shadow-sm">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <h2 class="text-lg font-semibold text-slate-900">Add new patient</h2>
                <p class="mt-1 text-sm text-slate-500">Enter the patient's mobile number first — we'll check if they're already in our system.</p>
            </div>

            <form @submit.prevent="doPhoneLookup()" class="space-y-4">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Mobile number</label>
                    <div class="mt-1.5 flex overflow-hidden rounded-xl border-2 border-slate-200 focus-within:border-emerald-500">
                        <span class="border-r-2 border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">+91</span>
                        <input type="tel" inputmode="numeric" maxlength="14" required autofocus
                               x-model="lookupPhone"
                               @input="lookupPhone = lookupPhone.replace(/\D/g, '').slice(0, 12)"
                               placeholder="98XXXXXXXX"
                               class="w-full px-4 py-3 text-base focus:outline-none">
                    </div>
                </div>

                <p x-show="lookupError" x-cloak class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800" x-text="lookupError"></p>

                <button type="submit" class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="lookupBusy || lookupPhone.length < 10">
                    <span x-show="!lookupBusy">Continue</span>
                    <span x-show="lookupBusy" x-cloak>Checking…</span>
                </button>
            </form>

            <div class="mt-5 rounded-xl bg-slate-50 p-3 text-xs text-slate-600">
                💡 <strong>Why we ask first:</strong> If this patient already has a profile on eClinicPro
                (either with you or another clinic), we'll auto-fill the basics so you don't retype.
            </div>
        </div>
    </div>

    <!-- ============ STEP 0.5: Existing chart found (at THIS clinic) ============ -->
    <div x-show="step === 0 && existingChart" x-cloak class="mx-auto mt-4 max-w-lg">
        <div class="rounded-2xl border-2 border-amber-300 bg-amber-50 p-6 shadow-sm">
            <h3 class="flex items-center gap-2 text-base font-semibold text-amber-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Patient already exists in your records
            </h3>
            <p class="mt-2 text-sm text-amber-800">
                <strong x-text="existingChart.name"></strong>
                · UHID <span x-text="existingChart.uhid"></span>
                already has a chart here.
            </p>
            <div class="mt-4 flex gap-2">
                <a :href="'/patients/' + existingChart.id"
                   class="flex-1 rounded-lg bg-amber-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-700">
                    Open existing chart
                </a>
                <button type="button" @click="proceedAnyway()"
                        class="rounded-lg border border-amber-400 bg-white px-4 py-2.5 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    Register anyway
                </button>
            </div>
            <button type="button" @click="resetLookup()"
                    class="mt-3 w-full text-center text-xs text-amber-700 hover:underline">
                ← Search a different number
            </button>
        </div>
    </div>

    <div class="mb-6 flex gap-2" x-show="step >= 1">
        <template x-for="n in 2" :key="n">
            <div class="h-1 flex-1 rounded" :class="step >= n ? 'bg-emerald-600' : 'bg-slate-200'"></div>
        </template>
    </div>

    <form method="post" :action="formAction" enctype="multipart/form-data" @submit="onSubmit" x-show="step >= 1">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="force_duplicate" :value="forceDuplicate ? '1' : ''">

        <!-- Step 1 -->
        <div x-show="step === 1" class="space-y-4 rounded-xl border bg-white p-6">
            <h2 class="font-semibold">Personal details</h2>
            <div>
                <label class="text-xs font-medium">Full name *</label>
                <input name="name" x-model="form.name" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium">Phone *</label>
                <!-- Phone is locked once Step 0 lookup is done. Receptionist can still
                     edit it if they made a typo — that re-triggers checkPhone. -->
                <input name="phone" x-model="form.phone" @blur="checkPhone()" required
                       class="mt-1 w-full rounded-lg border bg-slate-50 px-3 py-2 text-sm"
                       :readonly="step >= 1 && lookupPhone === form.phone">
                <p class="mt-1 text-[11px] text-slate-500" x-show="prefillBanner" x-cloak>
                    ✓ Verified · already in eClinicPro
                </p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-medium">Date of birth</label>
                    <input name="dob" type="date" x-model="form.dob" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium">Gender</label>
                    <select name="gender" x-model="form.gender" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-medium">Email</label>
                <input name="email" type="email" x-model="form.email" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium">Address</label>
                <textarea name="address" x-model="form.address" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-medium">Blood group</label>
                    <select name="blood_group" x-model="form.blood_group" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                        <option value="">—</option>
                        <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                        <option value="<?= $bg ?>"><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium">Diet</label>
                    <select name="veg_type" x-model="form.veg_type" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                        <option value="veg">Vegetarian</option>
                        <option value="nonveg">Non-vegetarian</option>
                        <option value="vegan">Vegan</option>
                        <option value="eggetarian">Eggetarian</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-medium">Photo</label>
                <input name="photo" type="file" accept="image/*" class="mt-1 w-full text-sm">
            </div>
            <button type="button" @click="step = 2" class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm text-white">Continue</button>
        </div>

        <!-- Step 2 -->
        <div x-show="step === 2" class="space-y-4 rounded-xl border bg-white p-6">
            <h2 class="font-semibold">Medical history</h2>
            <div>
                <label class="text-xs font-medium">Allergies (comma-separated)</label>
                <input name="allergies" x-model="form.allergies" placeholder="Penicillin, Peanuts" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium">Chronic conditions</label>
                <input name="chronic_conditions" x-model="form.chronic_conditions" placeholder="Diabetes, Hypertension" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium">Past surgeries</label>
                <textarea name="surgeries" x-model="form.surgeries" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </div>
            <div>
                <label class="text-xs font-medium">Family history</label>
                <textarea name="family_history" x-model="form.family_history" rows="2" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-medium">Insurance provider</label>
                    <input name="insurance_provider" x-model="form.insurance_provider" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium">Policy ID</label>
                    <input name="insurance_id" x-model="form.insurance_id" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                </div>
            </div>
            <!-- Phase 2: referral / source moved here. Step 3 (specialty fields)
                 is removed — specialty data is now captured inline at visit
                 time, not at patient registration. -->
            <div class="grid gap-4 sm:grid-cols-2 border-t pt-4">
                <div>
                    <label class="text-xs font-medium">Referred by</label>
                    <input name="referred_by" x-model="form.referred_by" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium">Source</label>
                    <select name="source" x-model="form.source" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                        <option value="walk_in">Walk-in</option>
                        <option value="referral">Referral</option>
                        <option value="online">Online</option>
                        <option value="camp">Camp</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" @click="step = 1" class="flex-1 rounded-lg border py-2.5 text-sm">Back</button>
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white">
                    <?= $editId ? 'Save changes' : 'Register patient' ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Existing chart at THIS clinic — show "view existing" warning -->
    <div x-show="duplicateModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-6">
            <h3 class="font-semibold text-amber-800">Patient already exists here</h3>
            <p class="mt-2 text-sm text-slate-600">A patient with this phone is already in your records: <strong x-text="duplicatePatient?.name"></strong> (<span x-text="duplicatePatient?.uhid"></span>)</p>
            <div class="mt-4 flex gap-2">
                <a :href="duplicatePatient ? '/patients/' + duplicatePatient.id : '#'" class="flex-1 rounded-lg border py-2 text-center text-sm">Open chart</a>
                <button type="button" @click="forceDuplicate = true; duplicateModal = false; $el.closest('form').requestSubmit()" class="flex-1 rounded-lg bg-amber-600 py-2 text-sm text-white">Register anyway</button>
            </div>
            <button type="button" @click="duplicateModal = false" class="mt-2 w-full text-xs text-slate-500">Cancel</button>
        </div>
    </div>

    <!-- Identity-only: patient known on eClinicPro but new to THIS clinic.
         Auto-fills form fields + shows a green banner so the receptionist
         doesn't manually retype the basics. -->
    <div x-show="prefillBanner" x-transition x-cloak
         class="fixed inset-x-4 bottom-4 z-40 mx-auto max-w-md rounded-xl border border-emerald-300 bg-emerald-50 p-4 shadow-lg sm:inset-x-auto sm:left-4">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-emerald-900">
                    <span x-show="prefillSource === 'identity'">Patient is registered on eClinicPro</span>
                    <span x-show="prefillSource !== 'identity'">Patient is already in our system</span>
                </div>
                <p class="mt-0.5 text-xs text-emerald-800">
                    We've pre-filled their basic info — please review and add any clinic-specific details.
                </p>
            </div>
            <button type="button" @click="prefillBanner = false" class="text-emerald-700 hover:text-emerald-900" aria-label="Dismiss">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
    </div>
</div>

<script>
function patientWizard(initial, editId) {
    const key = 'mc_patient_draft_' + (editId || 'new');
    const serverDup = <?= json_encode($duplicate ?? null) ?>;
    return {
        // For NEW patients we start at step 0 (phone lookup).
        // For edits we skip straight to step 1 (no lookup needed).
        step: editId ? 1 : 0,
        form: { veg_type: 'veg', source: 'walk_in', ...initial },
        formAction: editId ? '/patients/' + editId : '/patients/new',
        prefillBanner: false,
        prefillSource: 'identity',
        editId,
        duplicateModal: !!serverDup,
        duplicatePatient: serverDup,
        forceDuplicate: false,

        // Step 0 (phone lookup) state
        lookupPhone: '',
        lookupBusy:  false,
        lookupError: '',
        existingChart: null,

        startDraftTimer() {
            const saved = localStorage.getItem(key);
            if (saved && !editId) try { Object.assign(this.form, JSON.parse(saved)); } catch(e) {}
            setInterval(() => localStorage.setItem(key, JSON.stringify(this.form)), 30000);
        },

        // ----- Phone lookup at the start of new-patient flow -----
        async doPhoneLookup() {
            if (this.lookupPhone.length < 10) {
                this.lookupError = 'Please enter a valid 10-digit number.';
                return;
            }
            this.lookupBusy = true;
            this.lookupError = '';
            this.existingChart = null;

            try {
                const q = new URLSearchParams({ phone: this.lookupPhone });
                const r = await fetch('/api/v1/patients/check-phone?' + q, { credentials: 'same-origin' });
                const d = await r.json();

                if (d.status === 'existing_chart') {
                    // Show "open existing chart" panel — receptionist decides.
                    this.existingChart = d.patient;
                    return;
                }

                if (d.status === 'identity_only' && d.prefill) {
                    // Auto-fill form fields and show green banner.
                    const carry = ['name','email','dob','gender','address',
                                   'blood_group','veg_type','allergies',
                                   'chronic_conditions'];
                    for (const f of carry) {
                        if (d.prefill[f]) {
                            let v = d.prefill[f];
                            if (Array.isArray(v)) v = v.join(', ');
                            if (typeof v === 'string' && v.startsWith('[')) {
                                try { const arr = JSON.parse(v); if (Array.isArray(arr)) v = arr.join(', '); }
                                catch (e) {}
                            }
                            this.form[f] = v;
                        }
                    }
                    this.prefillSource = d.source || 'identity';
                    this.prefillBanner = true;
                }

                // Either way (identity_only or unknown), advance to the form
                // with the phone already filled.
                this.form.phone = this.lookupPhone;
                this.step = 1;
            } catch (e) {
                this.lookupError = "Couldn't reach the server. Try again.";
            } finally {
                this.lookupBusy = false;
            }
        },

        // User clicked "Register anyway" on the existing-chart screen.
        proceedAnyway() {
            this.form.phone = this.lookupPhone;
            this.forceDuplicate = true;
            this.existingChart = null;
            this.step = 1;
        },

        // User clicked "Search a different number".
        resetLookup() {
            this.lookupPhone = '';
            this.lookupError = '';
            this.existingChart = null;
            this.prefillBanner = false;
        },
        async checkPhone() {
            if (!this.form.phone) return;
            const q = new URLSearchParams({ phone: this.form.phone });
            if (this.editId) q.set('exclude_id', this.editId);
            const r = await fetch('/api/v1/patients/check-phone?' + q, { credentials: 'same-origin' });
            const d = await r.json();

            // Existing chart at THIS clinic → duplicate warning
            if (d.status === 'existing_chart') {
                this.duplicatePatient = d.patient;
                this.duplicateModal   = true;
                this.prefillBanner    = false;
                return;
            }

            // Globally known patient (signed up on /patient OR exists at
            // another clinic) → pre-fill the form, show green banner.
            if (d.status === 'identity_only' && d.prefill) {
                // Only overwrite fields the user hasn't typed yet, so we
                // don't clobber what reception already entered.
                const carryFields = ['name','email','dob','gender','address',
                                     'blood_group','veg_type','allergies',
                                     'chronic_conditions'];
                for (const f of carryFields) {
                    if (d.prefill[f] && !this.form[f]) {
                        // allergies / chronic may be JSON arrays — flatten.
                        let v = d.prefill[f];
                        if (Array.isArray(v)) v = v.join(', ');
                        if (typeof v === 'string' && v.startsWith('[')) {
                            try { const arr = JSON.parse(v); if (Array.isArray(arr)) v = arr.join(', '); }
                            catch (e) {}
                        }
                        this.form[f] = v;
                    }
                }
                this.prefillSource = d.source || 'identity';
                this.prefillBanner = true;
                this.duplicateModal = false;
                return;
            }

            // Truly unknown — clear any leftover banner state.
            this.prefillBanner = false;
            this.duplicateModal = false;
        },
        onSubmit(e) {
            if (this.duplicateModal && !this.forceDuplicate) { e.preventDefault(); return; }
            localStorage.removeItem(key);
        }
    };
}
</script>
