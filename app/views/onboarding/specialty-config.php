<?php
$title = 'Specialty settings — ManageClinic';
$options = $options ?? [];
$specialty = $specialty ?? 'gp';
$specialties = \App\Support\SpecialtyCatalog::all(true);
ob_start();
?>
<h1 class="text-2xl font-semibold text-slate-900">Specialty configuration</h1>
<p class="mt-1 text-sm text-slate-500">Tailor ManageClinic for <?= htmlspecialchars($specialties[$specialty]['label'] ?? $specialty) ?></p>

<form method="post" action="/onboarding/specialty-config" class="mt-8 space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <div>
            <label class="text-xs font-medium text-slate-600">Appointment slot duration (minutes)</label>
            <select name="slot_duration" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?>
                <option value="<?= $m ?>" <?= (int)($options['slot_duration'] ?? 15) === $m ? 'selected' : '' ?>><?= $m ?> min</option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($specialty === 'gp'): ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="icd10_enabled" value="1" <?= !empty($options['icd10_enabled']) ? 'checked' : '' ?>> Enable ICD-10 diagnosis codes</label>
        <div>
            <label class="text-xs font-medium text-slate-600">Drug database</label>
            <select name="drug_db" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="global" <?= ($options['drug_db'] ?? 'global') === 'global' ? 'selected' : '' ?>>Global platform drug DB</option>
                <option value="custom">Upload custom (later)</option>
            </select>
        </div>
        <?php elseif ($specialty === 'homeopathy'): ?>
        <p class="text-sm font-medium text-slate-700">Case-taking fields</p>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <?php foreach (['mental_generals'=>'Mental generals','physical_generals'=>'Physical generals','peculiar_symptoms'=>'Peculiar symptoms','modalities'=>'Modalities','miasmatic_analysis'=>'Miasmatic analysis'] as $k => $label): ?>
            <label class="flex gap-2"><input type="checkbox" name="<?= $k ?>" value="1" <?= !empty($options['case_fields'][$k] ?? true) ? 'checked' : '' ?>> <?= $label ?></label>
            <?php endforeach; ?>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Potency system</label>
            <select name="potency_system" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="centesimal" <?= ($options['potency_system'] ?? '') === 'centesimal' ? 'selected' : '' ?>>Centesimal (C)</option>
                <option value="lm">LM</option>
                <option value="both">Both</option>
            </select>
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="dietary_antidote_warnings" value="1" checked> Show dietary antidote warnings on prescriptions</label>
        <?php elseif ($specialty === 'dental'): ?>
        <div>
            <label class="text-xs font-medium text-slate-600">Tooth numbering</label>
            <select name="tooth_numbering" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="FDI">FDI (01–48)</option>
                <option value="Universal">Universal (1–32)</option>
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Default procedures (comma-separated)</label>
            <input name="procedures" value="scaling, RCT, extraction, crown, filling, implant" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <?php elseif ($specialty === 'derma'): ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="skin_score_enabled" value="1" checked> Skin condition score (1–10)</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="photo_tracking" value="1" checked> Before/after photo tracking</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="body_map" value="1" checked> Body map for condition marking</label>
        <?php elseif ($specialty === 'peds'): ?>
        <div>
            <label class="text-xs font-medium text-slate-600">Growth chart region</label>
            <select name="growth_chart_region" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="global">WHO Global</option>
                <option value="south_asia">South Asia</option>
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Vaccination schedule</label>
            <select name="vaccine_schedule" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="iap">India IAP</option>
                <option value="nhs">UK NHS</option>
                <option value="cdc">US CDC</option>
            </select>
        </div>
        <?php elseif ($specialty === 'physio'): ?>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="rom_joints" value="1" checked> Track ROM per joint</label>
        <div>
            <label class="text-xs font-medium text-slate-600">Pain scale</label>
            <select name="pain_scale" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="nrs">NRS 0–10</option>
                <option value="vas">VAS</option>
            </select>
        </div>
        <div>
            <label class="text-xs font-medium text-slate-600">Default session duration</label>
            <select name="default_session_duration" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="30">30 min</option>
                <option value="45" selected>45 min</option>
                <option value="60">60 min</option>
            </select>
        </div>
        <?php endif; ?>
    </section>

    <div class="flex justify-between">
        <a href="/onboarding/clinic-setup" class="text-sm text-slate-500 hover:underline">← Back</a>
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">Continue →</button>
    </div>
</form>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/_layout.php';
