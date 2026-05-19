<?php
$sp = $sp ?? [];
if ($spec === 'homeopathy'): ?>
<div>
    <label class="text-xs font-medium">Constitution / temperament</label>
    <input name="sp_constitution" value="<?= htmlspecialchars($sp['constitution'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" placeholder="e.g. Sulphur, Pulsatilla">
</div>
<div>
    <label class="text-xs font-medium">Thermal preference</label>
    <select name="sp_thermal" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        <option value="hot" <?= ($sp['thermal'] ?? '') === 'hot' ? 'selected' : '' ?>>Prefers cold</option>
        <option value="cold" <?= ($sp['thermal'] ?? '') === 'cold' ? 'selected' : '' ?>>Prefers heat</option>
    </select>
</div>
<?php elseif ($spec === 'derma'): ?>
<div>
    <label class="text-xs font-medium">Skin type</label>
    <select name="sp_skin_type" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        <option value="oily">Oily</option>
        <option value="dry">Dry</option>
        <option value="combination">Combination</option>
        <option value="sensitive">Sensitive</option>
    </select>
</div>
<?php elseif ($spec === 'peds'): ?>
<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="text-xs font-medium">Birth weight (kg)</label>
        <input name="sp_birth_weight" type="number" step="0.01" value="<?= htmlspecialchars($sp['birth_weight'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
    </div>
    <div>
        <label class="text-xs font-medium">Gestational age (weeks)</label>
        <input name="sp_gestational_age" type="number" value="<?= htmlspecialchars($sp['gestational_age'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
    </div>
</div>
<?php elseif ($spec === 'dental'): ?>
<div>
    <label class="text-xs font-medium">Dental notes</label>
    <input name="sp_dental_notes" value="<?= htmlspecialchars($sp['dental_notes'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
</div>
<?php else: ?>
<p class="text-sm text-slate-500">No extra specialty fields for this clinic type.</p>
<?php endif; ?>
