<?php
/**
 * Patient header partial — reusable across patient detail + visit screen.
 *
 * Required vars in scope:
 *   $patient   — patient row (id, name, uhid, age, gender, phone, photo_path,
 *                allergies, blood_group, etc.)
 *
 * Optional:
 *   $visitCount  — int, displayed beside ID if set
 *   $allergies   — list<string> decoded already (saves a call)
 *   $chronic     — list<string> chronic conditions, decoded already
 *   $compact     — bool, renders the slim sticky variant
 */
$photoUrl = !empty($patient['photo_path']) ? '/' . ltrim((string) $patient['photo_path'], '/') : null;
$allergies = $allergies ?? [];
$chronic = $chronic ?? [];
$compact = !empty($compact);
$initials = strtoupper(substr(trim((string) ($patient['name'] ?? '')), 0, 1)) ?: '?';

$age = $patient['age'] ?? null;
$gender = match (strtoupper((string) ($patient['gender'] ?? ''))) {
    'M' => 'Male',
    'F' => 'Female',
    default => '',
};
$phone = (string) ($patient['phone'] ?? '');
$visitCount = $visitCount ?? null;
// Inline "Edit patient" panel (full variant only): posts a partial payload to
// /patients/{id} and comes back to this same screen via return_to, so the
// doctor never loses the visit they are in the middle of.
$returnTo = $_SERVER['REQUEST_URI'] ?? ('/patients/' . (int) ($patient['id'] ?? 0));
$editGender = strtoupper((string) ($patient['gender'] ?? '')) === 'OTHER'
    ? 'Other' : strtoupper((string) ($patient['gender'] ?? ''));
?>
<?php if (!$compact): ?>
<div x-data="{ editPatient: false }" class="space-y-3">
<?php endif; ?>
<div class="<?= $compact ? 'pt-2 pb-3' : 'p-5' ?> flex flex-wrap items-center gap-4 ui-card">
    <?php if ($photoUrl): ?>
        <img src="<?= htmlspecialchars($photoUrl) ?>" alt=""
             class="<?= $compact ? 'h-10 w-10' : 'h-14 w-14' ?> rounded-full object-cover">
    <?php else: ?>
        <span class="flex <?= $compact ? 'h-10 w-10 text-base' : 'h-14 w-14 text-xl' ?>
                     items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-700">
            <?= htmlspecialchars($initials) ?>
        </span>
    <?php endif; ?>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-2">
            <h2 class="<?= $compact ? 'text-base' : 'text-lg' ?> font-semibold text-slate-900 truncate">
                <?= htmlspecialchars((string) ($patient['name'] ?? 'Patient')) ?>
            </h2>
            <?php if ($age !== null && $age !== ''): ?>
                <span class="text-sm text-slate-500"><?= (int) $age ?> yrs</span>
            <?php endif; ?>
            <?php if ($gender): ?>
                <span class="text-sm text-slate-500"><?= htmlspecialchars($gender) ?></span>
            <?php endif; ?>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">
            <span>ID <?= htmlspecialchars((string) ($patient['uhid'] ?? $patient['id'] ?? '')) ?></span>
            <?php if ($phone): ?>
                <span class="mx-1.5">·</span>
                <a href="tel:<?= htmlspecialchars($phone) ?>" class="text-emerald-700 hover:underline"><?= htmlspecialchars($phone) ?></a>
            <?php endif; ?>
            <?php if ($visitCount !== null): ?>
                <span class="mx-1.5">·</span>
                <span><?= (int) $visitCount ?> visit<?= (int) $visitCount === 1 ? '' : 's' ?></span>
            <?php endif; ?>
            <?php if (!empty($patient['blood_group'])): ?>
                <span class="mx-1.5">·</span>
                <span class="text-rose-700"><?= htmlspecialchars((string) $patient['blood_group']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (!empty($allergies)): ?>
            <p class="mt-1 text-xs text-rose-700">
                ⚠ Allergies: <?= htmlspecialchars(implode(', ', $allergies)) ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($chronic)): ?>
            <p class="mt-0.5 text-xs text-amber-700">
                Chronic: <?= htmlspecialchars(implode(', ', $chronic)) ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!$compact): ?>
        <div class="ml-auto flex items-center gap-2">
            <button type="button" @click="editPatient = !editPatient"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    x-text="editPatient ? 'Close editor' : 'Edit patient'">Edit patient</button>
            <a href="/patients/<?= (int) $patient['id'] ?>"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Patient profile
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!$compact): ?>
    <?php if (!empty($_GET['patient_updated'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">✓ Patient details updated.</p>
    <?php endif; ?>
    <?php if (!empty($_GET['patient_error'])): ?>
    <p class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800"><?= htmlspecialchars((string) $_GET['patient_error']) ?></p>
    <?php endif; ?>

    <div x-show="editPatient" x-cloak x-collapse class="ui-card p-4">
        <form method="post" action="/patients/<?= (int) $patient['id'] ?>" enctype="multipart/form-data" class="space-y-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block">
                    <span class="ui-label">Full name *</span>
                    <input name="name" required value="<?= htmlspecialchars((string) ($patient['name'] ?? '')) ?>" class="ui-input">
                </label>
                <label class="block">
                    <span class="ui-label">Phone</span>
                    <input name="phone" type="tel" inputmode="numeric" value="<?= htmlspecialchars($phone) ?>" class="ui-input">
                </label>
                <label class="block">
                    <span class="ui-label">Email</span>
                    <input name="email" type="email" value="<?= htmlspecialchars((string) ($patient['email'] ?? '')) ?>" class="ui-input">
                </label>
                <label class="block">
                    <span class="ui-label">Date of birth</span>
                    <input name="dob" type="date" value="<?= htmlspecialchars((string) ($patient['dob'] ?? '')) ?>" class="ui-input">
                </label>
                <label class="block">
                    <span class="ui-label">Gender</span>
                    <select name="gender" class="ui-input">
                        <option value="">—</option>
                        <?php foreach (['M' => 'Male', 'F' => 'Female', 'Other' => 'Other'] as $gv => $gl): ?>
                        <option value="<?= $gv ?>" <?= $editGender === strtoupper($gv) ? 'selected' : '' ?>><?= $gl ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="ui-label">Blood group</span>
                    <select name="blood_group" class="ui-input">
                        <option value="">—</option>
                        <?php foreach (['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $bg): ?>
                        <option value="<?= $bg ?>" <?= ($patient['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="ui-label">Diet</span>
                    <select name="veg_type" class="ui-input">
                        <?php foreach (['veg' => 'Vegetarian', 'nonveg' => 'Non-vegetarian', 'vegan' => 'Vegan', 'eggetarian' => 'Eggetarian'] as $vv => $vl): ?>
                        <option value="<?= $vv ?>" <?= ($patient['veg_type'] ?? 'veg') === $vv ? 'selected' : '' ?>><?= $vl ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="block">
                    <span class="ui-label">Referred by</span>
                    <input name="referred_by" value="<?= htmlspecialchars((string) ($patient['referred_by'] ?? '')) ?>" class="ui-input">
                </label>
                <label class="block">
                    <span class="ui-label">Photo</span>
                    <input name="photo" type="file" accept="image/*" class="mt-1 w-full text-sm">
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="ui-label">Allergies <span class="font-normal text-slate-400">(comma separated)</span></span>
                    <input name="allergies" value="<?= htmlspecialchars(implode(', ', $allergies)) ?>" class="ui-input">
                </label>
                <label class="block">
                    <span class="ui-label">Chronic conditions <span class="font-normal text-slate-400">(comma separated)</span></span>
                    <input name="chronic_conditions" value="<?= htmlspecialchars(implode(', ', $chronic)) ?>" class="ui-input">
                </label>
            </div>

            <label class="block">
                <span class="ui-label">Address</span>
                <textarea name="address" rows="2" class="ui-input"><?= htmlspecialchars((string) ($patient['address'] ?? '')) ?></textarea>
            </label>

            <div class="flex items-center gap-2">
                <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">Save patient</button>
                <button type="button" @click="editPatient = false" class="ui-btn ui-btn-secondary ui-btn-sm">Cancel</button>
                <a href="/patients/<?= (int) $patient['id'] ?>/edit" class="text-xs text-slate-500 hover:underline">Open full editor</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
