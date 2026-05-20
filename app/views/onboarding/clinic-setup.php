<?php
$title = 'Clinic setup — ManageClinic';
$config = $config ?? [];
$workingHours = $workingHours ?? [];
ob_start();
$uhid = htmlspecialchars($config['uhid_prefix'] ?? 'MC');
?>
<h1 class="text-2xl font-semibold text-slate-900">Set up your clinic</h1>
<p class="mt-1 text-sm text-slate-500">Basic details patients and staff will see</p>

<form method="post" action="/onboarding/clinic-setup" enctype="multipart/form-data" class="mt-8 space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-sm font-medium text-slate-700">Clinic details</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-slate-600">Clinic name</label>
                <input name="clinic_name" required value="<?= htmlspecialchars($clinic['name'] ?? '') ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-slate-600">Address</label>
                <textarea name="address" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars($clinic['address'] ?? '') ?></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Phone</label>
                <input name="phone" value="<?= htmlspecialchars($clinic['phone'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Email</label>
                <input name="email" type="email" value="<?= htmlspecialchars($clinic['email'] ?? '') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-slate-600">Logo (PNG/JPG, max 2MB)</label>
                <input name="logo" type="file" accept="image/png,image/jpeg" class="mt-1 w-full text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-medium text-slate-700 mb-3">Specialty</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <?php foreach ($specialties as $key => $spec): ?>
            <label class="cursor-pointer rounded-lg border p-3 text-center has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="specialty" value="<?= htmlspecialchars($key) ?>" class="sr-only"
                    <?= ($clinic['specialty'] ?? 'gp') === $key ? 'checked' : '' ?>>
                <div class="text-2xl"><?= $spec['icon'] ?></div>
                <div class="mt-1 text-xs font-medium"><?= htmlspecialchars($spec['label']) ?></div>
            </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <h2 class="text-sm font-medium text-slate-700">Billing & IDs</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-600">Patient ID prefix</label>
                <input name="uhid_prefix" maxlength="6" value="<?= $uhid ?>" id="uhid_prefix"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                <p class="mt-1 text-xs text-slate-400">Preview: <span id="uhid_preview"><?= $uhid ?>-00001</span></p>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Country</label>
                <select name="country_code" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($countries as $code => $name): ?>
                    <option value="<?= $code ?>" <?= ($clinic['country_code'] ?? 'IN') === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Currency</label>
                <select name="currency" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach (['INR','USD','GBP','AED','SGD','MYR'] as $c): ?>
                    <option value="<?= $c ?>" <?= ($clinic['currency'] ?? 'INR') === $c ? 'selected' : '' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Tax label</label>
                <input name="invoice_tax_label" value="<?= htmlspecialchars($config['invoice_tax_label'] ?? 'GST') ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Tax %</label>
                <input name="invoice_tax_percent" type="number" step="0.01" min="0" max="30" value="<?= htmlspecialchars((string) ($config['invoice_tax_percent'] ?? '0')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">Default consultation fee</label>
                <input name="consultation_fee" type="number" step="0.01" min="0" value="<?= htmlspecialchars((string) ($config['consultation_fee'] ?? '0')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-medium text-slate-700 mb-3">Working hours</h2>
        <div class="space-y-3 text-sm">
            <?php
            $dayLabels = ['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'];
            foreach ($dayLabels as $day => $label):
                $d = $workingHours[$day] ?? ['enabled' => false, 'sessions' => []];
                $sess = $d['sessions'] ?? [];
                $mStart = $sess[0]['start'] ?? '09:00';
                $mEnd = $sess[0]['end'] ?? '13:00';
                $eStart = $sess[1]['start'] ?? '16:00';
                $eEnd = $sess[1]['end'] ?? '20:00';
            ?>
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 pb-3">
                <label class="flex w-20 items-center gap-2">
                    <input type="checkbox" name="<?= $day ?>_enabled" value="1" <?= !empty($d['enabled']) ? 'checked' : '' ?>>
                    <?= $label ?>
                </label>
                <input type="time" name="<?= $day ?>_morning_start" value="<?= $mStart ?>" class="rounded border px-2 py-1 text-xs">
                <span class="text-slate-400">–</span>
                <input type="time" name="<?= $day ?>_morning_end" value="<?= $mEnd ?>" class="rounded border px-2 py-1 text-xs">
                <input type="time" name="<?= $day ?>_evening_start" value="<?= $eStart ?>" class="rounded border px-2 py-1 text-xs">
                <span class="text-slate-400">–</span>
                <input type="time" name="<?= $day ?>_evening_end" value="<?= $eEnd ?>" class="rounded border px-2 py-1 text-xs">
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="flex justify-end">
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Continue →
        </button>
    </div>
</form>
<script>
document.getElementById('uhid_prefix')?.addEventListener('input', function() {
    const v = this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,6) || 'MC';
    document.getElementById('uhid_preview').textContent = v + '-00001';
});
</script>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/_layout.php';
