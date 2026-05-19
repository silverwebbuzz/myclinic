<?php
$plan = $dietPlan ?? null;
$week = is_array($plan['plan_json'] ?? null) ? $plan['plan_json'] : ($defaultDietWeek ?? []);
?>
<section x-show="activeTab === 'diet'" class="rounded-xl border bg-white p-6 space-y-4">
    <h3 class="font-semibold">Diet plan</h3>
    <?php if (!empty($_GET['diet_saved'])): ?><p class="text-sm text-emerald-600">Draft saved.</p><?php endif; ?>
    <?php if (!empty($_GET['diet_shared'])): ?><p class="text-sm text-emerald-600">Plan shared via WhatsApp.</p><?php endif; ?>

    <form method="post" action="/visits/<?= (int) $visit['id'] ?>/diet" class="space-y-3 text-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block">Condition<input name="condition" value="<?= htmlspecialchars($plan['condition'] ?? '') ?>" class="mt-1 w-full rounded border px-2 py-1"></label>
            <label class="block">Diet type
                <select name="veg_type" class="mt-1 w-full rounded border px-2 py-1">
                    <?php foreach (['veg','nonveg','vegan','eggetarian'] as $v): ?>
                    <option value="<?= $v ?>" <?= ($plan['veg_type']??'veg')===$v?'selected':'' ?>><?= ucfirst($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="flex gap-2 text-xs"><input type="checkbox" name="include_homeo_warnings" value="1" checked> Include homeopathic dietary warnings from Rx</label>
        <textarea name="antidotes_shown" rows="2" placeholder="Additional restrictions" class="w-full rounded border px-2 py-1 text-xs"><?= htmlspecialchars($plan['antidotes_shown'] ?? '') ?></textarea>

        <div class="overflow-x-auto">
            <table class="w-full text-xs border">
                <thead class="bg-slate-50"><tr><th class="p-2 text-left">Day</th><th>Breakfast</th><th>Lunch</th><th>Dinner</th></tr></thead>
                <tbody>
                <?php foreach ($week as $day => $meals): ?>
                <tr class="border-t">
                    <td class="p-2 font-medium"><?= htmlspecialchars($day) ?></td>
                    <td class="p-1"><input name="meals[<?= htmlspecialchars($day) ?>][breakfast]" value="<?= htmlspecialchars(is_array($meals)?($meals['breakfast']??''):'') ?>" class="w-full border rounded px-1"></td>
                    <td class="p-1"><input name="meals[<?= htmlspecialchars($day) ?>][lunch]" value="<?= htmlspecialchars(is_array($meals)?($meals['lunch']??''):'') ?>" class="w-full border rounded px-1"></td>
                    <td class="p-1"><input name="meals[<?= htmlspecialchars($day) ?>][dinner]" value="<?= htmlspecialchars(is_array($meals)?($meals['dinner']??''):'') ?>" class="w-full border rounded px-1"></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-white text-sm">Save draft</button>
    </form>

    <?php if ($plan !== null): ?>
    <form method="post" action="/visits/<?= (int) $visit['id'] ?>/diet/share">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm">Share PDF + WhatsApp</button>
    </form>
    <?php if (!empty($plan['pdf_path'])): ?>
    <a href="<?= htmlspecialchars($plan['pdf_path']) ?>" target="_blank" class="text-sm text-emerald-600">View diet PDF</a>
    <?php endif; ?>
    <?php endif; ?>
</section>
