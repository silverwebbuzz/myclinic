<?php
/**
 * /admin/corporate/templates/{id} — one template version. Drafts are editable.
 *
 * @var array<string,mixed> $t  + items
 * @var list<array<string,mixed>> $versions  all versions of this code
 * @var list<array<string,mixed>> $examTypes
 * @var list<array<string,mixed>> $industries
 * @var bool $canEdit
 */
use App\Services\Corporate\ProgrammeService;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
$in = 'mt-1 block w-full rounded border border-slate-200 px-2.5 py-1.5 text-sm';
$draft = $t['status'] === 'draft';
$edit = $canEdit && $draft;
$pill = ['draft' => 'bg-amber-50 text-amber-800', 'published' => 'bg-emerald-50 text-emerald-800', 'retired' => 'bg-slate-100 text-slate-500'];
$reqPill = ['mandatory' => 'bg-rose-50 text-rose-700', 'recommended' => 'bg-slate-100 text-slate-600'];
$hasDraft = array_filter($versions, static fn ($v) => $v['status'] === 'draft') !== [];
$countryName = \App\Support\Locale\CountryRepo::country((string) $t['country_code'])['name'] ?? $t['country_code'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($t['name']) ?> v<?= (int) $t['version'] ?> · Templates · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl px-6 py-6">
        <a href="/admin/corporate/templates" class="text-sm text-slate-600 underline">← Templates</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold"><?= $h($t['name']) ?> <span class="ml-1 rounded-full px-2 py-0.5 align-middle text-xs font-medium <?= $pill[$t['status']] ?>">v<?= (int) $t['version'] ?> <?= $h($t['status']) ?></span>
                    <?php if ((int) $t['is_sample'] === 1): ?><span class="rounded bg-amber-100 px-1.5 py-0.5 align-middle text-[10px] font-semibold uppercase text-amber-800">Sample</span><?php endif; ?></h1>
                <p class="font-mono text-xs text-slate-500"><?= $h($t['code']) ?> · <?= $h($countryName) ?> · <?= $h($t['industry_name'] ?? 'Any industry') ?></p>
                <p class="mt-1 text-xs text-slate-500">Versions: <?php foreach ($versions as $v): ?><a href="/admin/corporate/templates/<?= (int) $v['id'] ?>" class="mr-1 rounded-full px-2 py-0.5 font-medium <?= $pill[$v['status']] ?> <?= (int) $v['id'] === (int) $t['id'] ? 'ring-1 ring-slate-400' : '' ?>">v<?= (int) $v['version'] ?></a><?php endforeach; ?>
                    · used by <?= (int) ($versions[0]['companies_using'] ?? 0) ?> compan<?= (int) ($versions[0]['companies_using'] ?? 0) === 1 ? 'y' : 'ies' ?></p>
            </div>
            <?php if ($canEdit): ?>
                <div class="flex flex-wrap gap-2">
                    <?php if ($draft): ?>
                        <form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>/publish" onsubmit="return confirm('Publish v<?= (int) $t['version'] ?>? Companies on older versions will be offered the update.')"><?= $csrfField ?><button class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Publish v<?= (int) $t['version'] ?></button></form>
                        <form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>/delete" onsubmit="return confirm('Delete this draft?')"><?= $csrfField ?><button class="rounded border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700">Delete draft</button></form>
                    <?php else: ?>
                        <form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>/new-version"><?= $csrfField ?><button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white"><?= $hasDraft ? 'Open the draft' : 'Make a new version' ?></button></form>
                        <?php if ($t['status'] === 'published'): ?>
                            <form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>/retire" onsubmit="return confirm('Retire? It disappears from the company library; companies keep their programmes.')"><?= $csrfField ?><button class="rounded border border-slate-300 bg-white px-3 py-2 text-xs font-semibold">Retire</button></form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>
        <?php if (!$draft): ?><p class="mt-3 rounded bg-white px-4 py-2 text-sm text-slate-600 shadow-sm">Published and retired versions can't be edited. Make a new version to change it.</p><?php endif; ?>

        <form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>" class="mt-4 grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2">
            <?= $csrfField ?>
            <fieldset <?= $edit ? '' : 'disabled' ?> class="contents">
                <label class="text-xs text-slate-600">Name<input name="name" required maxlength="150" value="<?= $h($t['name']) ?>" class="<?= $in ?>"></label>
                <label class="text-xs text-slate-600">Industry<select name="industry_id" class="<?= $in ?>"><option value="0">Any industry</option><?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>" <?= (int) $i['id'] === (int) $t['industry_id'] ? 'selected' : '' ?>><?= $h($i['name']) ?></option><?php endforeach; ?></select></label>
                <label class="text-xs text-slate-600 sm:col-span-2">For whom<input name="audience" maxlength="255" value="<?= $h($t['audience'] ?? '') ?>" class="<?= $in ?>"></label>
                <label class="text-xs text-slate-600 sm:col-span-2">Description (shown to companies)<textarea name="description" rows="2" maxlength="2000" class="<?= $in ?>"><?= $h($t['description'] ?? '') ?></textarea></label>
                <label class="text-xs text-slate-600 sm:col-span-2">Source / regulation reference<input name="source_note" maxlength="500" value="<?= $h($t['source_note'] ?? '') ?>" class="<?= $in ?>"></label>
                <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="is_sample" value="1" <?= (int) $t['is_sample'] === 1 ? 'checked' : '' ?>> Mark as SAMPLE (testing only, not reviewed)</label>
                <?php if ($edit): ?><div class="sm:col-span-2"><button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Save details</button></div><?php endif; ?>
            </fieldset>
        </form>

        <section id="items" class="mt-4 rounded-lg bg-white shadow-sm">
            <h2 class="px-4 pt-4 font-semibold">Checks</h2>
            <?php foreach (ProgrammeService::CATEGORIES as $cat => $catLabel): $rows = array_filter($t['items'], static fn ($i) => $i['category'] === $cat); if ($rows === []) { continue; } ?>
                <div class="px-4 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-500"><?= $h($catLabel) ?></div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($rows as $it): ?>
                        <li class="flex flex-wrap items-center gap-3 px-4 py-2 text-sm">
                            <span class="min-w-0 flex-1"><?= $h($it['exam_name']) ?><?= $it['frequency_months'] ? ' <span class="text-slate-500">· every ' . (int) $it['frequency_months'] . ' months</span>' : '' ?><?= !empty($it['note']) ? ' <span class="text-xs text-slate-400">— ' . $h($it['note']) . '</span>' : '' ?></span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $reqPill[$it['requirement']] ?>"><?= $h(ucfirst($it['requirement'])) ?></span>
                            <?php if ($edit): ?><form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>/items/<?= (int) $it['id'] ?>/remove"><?= $csrfField ?><button class="text-xs text-rose-700 underline">Remove</button></form><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <?php if ($t['items'] === []): ?><p class="px-4 py-4 text-sm text-slate-500">No checks yet.</p><?php endif; ?>
            <?php if ($edit): ?>
                <form method="post" action="/admin/corporate/templates/<?= (int) $t['id'] ?>/items" class="mt-2 grid gap-2 border-t border-slate-100 p-4 sm:grid-cols-6 sm:items-end" x-data="{ cat: 'periodic' }">
                    <?= $csrfField ?>
                    <label class="text-xs text-slate-600 sm:col-span-2">Check<select name="exam_type_id" required class="<?= $in ?>"><option value="">Choose…</option>
                        <?php foreach (ProgrammeService::KINDS as $kind => $kl): $list = array_filter($examTypes, static fn ($x) => $x['kind'] === $kind); if ($list === []) { continue; } ?>
                            <optgroup label="<?= $h($kl) ?>"><?php foreach ($list as $x): ?><option value="<?= (int) $x['id'] ?>"><?= $h($x['name']) ?></option><?php endforeach; ?></optgroup>
                        <?php endforeach; ?></select></label>
                    <label class="text-xs text-slate-600">When<select name="category" x-model="cat" class="<?= $in ?>"><?php foreach (ProgrammeService::CATEGORIES as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
                    <label class="text-xs text-slate-600" x-show="cat === 'periodic'">Every (months)<input type="number" name="frequency_months" min="1" max="120" value="12" class="<?= $in ?>"></label>
                    <label class="text-xs text-slate-600">Requirement<select name="requirement" class="<?= $in ?>"><option value="mandatory">Mandatory (legal)</option><option value="recommended">Recommended</option></select></label>
                    <button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Add / update</button>
                    <label class="text-xs text-slate-600 sm:col-span-6">Note <span class="text-slate-400">(optional)</span><input name="note" maxlength="255" class="<?= $in ?>"></label>
                </form>
                <p class="px-4 pb-4 text-xs text-slate-500">Adding the same check + "when" again updates it.</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
