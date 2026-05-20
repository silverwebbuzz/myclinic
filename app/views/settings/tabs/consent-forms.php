<form method="post" action="/settings/consent-forms" class="space-y-6 rounded-xl border bg-white p-6">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <h2 class="text-lg font-semibold">Consent form templates</h2>
    <p class="text-sm text-slate-500">Use merge fields: {{patient_name}}, {{uhid}}, {{clinic_name}}, {{date}}, {{procedure}}, {{doctor_name}}</p>

    <?php if (!empty($message)): ?>
    <p class="text-sm text-emerald-600">Template saved.</p>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="text-xs font-medium">Template name</label>
            <input name="name" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" placeholder="Procedure consent">
        </div>
        <div>
            <label class="text-xs font-medium">Form type</label>
            <select name="form_type" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <option value="procedure">Procedure</option>
                <option value="surgical">Surgical</option>
                <option value="anaesthesia">Anaesthesia</option>
                <option value="general">General</option>
            </select>
        </div>
    </div>
    <div>
        <label class="text-xs font-medium">Content</label>
        <textarea name="content" rows="10" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm font-mono" placeholder="I, {{patient_name}}, consent to..."></textarea>
    </div>
    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Save template</button>
</form>

<?php if (!empty($consentTemplates)): ?>
<div class="mt-8 rounded-xl border bg-white p-6">
    <h3 class="font-medium text-sm">Active templates</h3>
    <ul class="mt-3 divide-y text-sm">
        <?php foreach ($consentTemplates as $tpl): ?>
        <li class="py-3">
            <p class="font-medium"><?= htmlspecialchars($tpl['name']) ?> <span class="text-xs text-slate-400">(<?= htmlspecialchars($tpl['form_type'] ?? '') ?>)</span></p>
            <p class="mt-1 text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars(mb_substr($tpl['content'] ?? '', 0, 120)) ?>…</p>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
