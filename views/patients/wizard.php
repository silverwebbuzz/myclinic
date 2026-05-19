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

    <div class="mb-6 flex gap-2">
        <template x-for="n in 3" :key="n">
            <div class="h-1 flex-1 rounded" :class="step >= n ? 'bg-emerald-600' : 'bg-slate-200'"></div>
        </template>
    </div>

    <form method="post" :action="formAction" enctype="multipart/form-data" @submit="onSubmit">
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
                <input name="phone" x-model="form.phone" @blur="checkPhone()" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
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
            <div class="flex gap-2">
                <button type="button" @click="step = 1" class="flex-1 rounded-lg border py-2.5 text-sm">Back</button>
                <button type="button" @click="step = 3" class="flex-1 rounded-lg bg-emerald-600 py-2.5 text-sm text-white">Continue</button>
            </div>
        </div>

        <!-- Step 3 -->
        <div x-show="step === 3" class="space-y-4 rounded-xl border bg-white p-6">
            <h2 class="font-semibold">Specialty — <?= htmlspecialchars($specialties[$spec]['label'] ?? $spec) ?></h2>
            <?php require __DIR__ . '/_wizard_specialty.php'; ?>
            <div class="grid gap-4 sm:grid-cols-2">
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
                <button type="button" @click="step = 2" class="flex-1 rounded-lg border py-2.5 text-sm">Back</button>
                <button type="submit" class="flex-1 rounded-lg bg-emerald-600 py-2.5 text-sm font-medium text-white">
                    <?= $editId ? 'Save changes' : 'Register patient' ?>
                </button>
            </div>
        </div>
    </form>

    <div x-show="duplicateModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-xl bg-white p-6">
            <h3 class="font-semibold text-amber-800">Duplicate phone number</h3>
            <p class="mt-2 text-sm text-slate-600">A patient with this phone already exists: <strong x-text="duplicatePatient?.name"></strong> (<span x-text="duplicatePatient?.uhid"></span>)</p>
            <div class="mt-4 flex gap-2">
                <a :href="duplicatePatient ? '/patients/' + duplicatePatient.id : '#'" class="flex-1 rounded-lg border py-2 text-center text-sm">View existing</a>
                <button type="button" @click="forceDuplicate = true; duplicateModal = false; $el.closest('form').requestSubmit()" class="flex-1 rounded-lg bg-amber-600 py-2 text-sm text-white">Register anyway</button>
            </div>
            <button type="button" @click="duplicateModal = false" class="mt-2 w-full text-xs text-slate-500">Cancel</button>
        </div>
    </div>
</div>

<script>
function patientWizard(initial, editId) {
    const key = 'mc_patient_draft_' + (editId || 'new');
    const serverDup = <?= json_encode($duplicate ?? null) ?>;
    return {
        step: 1,
        form: { veg_type: 'veg', source: 'walk_in', ...initial },
        formAction: editId ? '/patients/' + editId : '/patients/new',
        editId,
        duplicateModal: !!serverDup,
        duplicatePatient: serverDup,
        forceDuplicate: false,
        startDraftTimer() {
            const saved = localStorage.getItem(key);
            if (saved && !editId) try { Object.assign(this.form, JSON.parse(saved)); } catch(e) {}
            setInterval(() => localStorage.setItem(key, JSON.stringify(this.form)), 30000);
        },
        async checkPhone() {
            if (!this.form.phone) return;
            const q = new URLSearchParams({ phone: this.form.phone });
            if (this.editId) q.set('exclude_id', this.editId);
            const r = await fetch('/api/v1/patients/check-phone?' + q, { credentials: 'same-origin' });
            const d = await r.json();
            if (d.exists) { this.duplicatePatient = d.patient; this.duplicateModal = true; }
        },
        onSubmit(e) {
            if (this.duplicateModal && !this.forceDuplicate) { e.preventDefault(); return; }
            localStorage.removeItem(key);
        }
    };
}
</script>
