<form method="post" action="/settings/consent-forms" class="space-y-6 ui-card ui-card-pad">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <h2 class="ui-section-title">Consent form templates</h2>
    <p class="text-sm text-slate-500">Use merge fields: {{patient_name}}, {{uhid}}, {{clinic_name}}, {{date}}, {{procedure}}, {{doctor_name}}</p>

    <?php if (!empty($message)): ?>
    <p class="text-sm text-emerald-600">Template saved.</p>
    <?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="text-xs font-medium">Template name</label>
            <input name="name" required class="ui-input" placeholder="Procedure consent">
        </div>
        <div>
            <label class="text-xs font-medium">Form type</label>
            <select name="form_type" class="ui-input">
                <option value="procedure">Procedure</option>
                <option value="surgical">Surgical</option>
                <option value="anaesthesia">Anaesthesia</option>
                <option value="general">General</option>
            </select>
        </div>
    </div>
    <div>
        <label class="text-xs font-medium">Content</label>
        <textarea name="content" rows="10" class="ui-input font-mono" placeholder="I, {{patient_name}}, consent to..."></textarea>
    </div>
    <button type="submit" class="ui-btn ui-btn-primary">Save template</button>
</form>

<?php if (!empty($consentTemplates)): ?>
<div class="mt-8 ui-card ui-card-pad">
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
