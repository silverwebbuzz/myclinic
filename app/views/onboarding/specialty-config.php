<?php
$title = 'Specialty settings — ManageClinic';
$options = $options ?? [];
$specialty = $specialty ?? 'gp';
$specialties = \App\Support\SpecialtyCatalog::all(true);
ob_start();
?>
<h1 class="text-2xl font-semibold text-slate-900">Specialty configuration</h1>
<p class="mt-1 text-sm text-slate-500">Tailor ManageClinic for <?= htmlspecialchars($specialties[$specialty]['label'] ?? $specialty) ?></p>

<form method="post" action="/onboarding/specialty-config"
      data-onboarding-draft="/onboarding/specialty-config/draft"
      class="mt-8 space-y-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

    <section class="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <div>
            <label class="text-xs font-medium text-slate-600">Appointment slot duration (minutes)</label>
            <select name="slot_duration" class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach (\App\Services\DoctorScheduleService::ALLOWED_SLOT_DURATIONS as $m): ?>
                <option value="<?= $m ?>" <?= (int)($options['slot_duration'] ?? 15) === $m ? 'selected' : '' ?>><?= $m ?> min</option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Slot duration is now the only specialty-config option. -->
    </section>

    <div class="flex justify-between">
        <a href="/onboarding/clinic-setup" class="text-sm text-slate-500 hover:underline">← Back</a>
        <button type="submit" class="rounded-lg bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">Continue →</button>
    </div>
</form>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/_layout.php';
