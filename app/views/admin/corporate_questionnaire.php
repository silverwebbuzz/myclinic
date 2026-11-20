<?php
/**
 * /admin/corporate/questionnaires/{id} — one version; drafts are editable.
 *
 * @var array<string,mixed> $q  + items
 * @var bool $canEdit
 */
use App\Services\OccHealth\QuestionnaireAdminService;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
$in = 'mt-1 block w-full rounded border border-slate-200 px-2.5 py-1.5 text-sm';
$draft = $q['status'] === 'draft';
$edit = $canEdit && $draft;
$pill = ['draft' => 'bg-amber-50 text-amber-800', 'published' => 'bg-emerald-50 text-emerald-800', 'retired' => 'bg-slate-100 text-slate-500'];
$typeLabel = ['yes_no' => 'Yes / No', 'choice' => 'Choice', 'text' => 'Text'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($q['name']) ?> v<?= (int) $q['version'] ?> · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl px-6 py-6">
        <a href="/admin/corporate/questionnaires" class="text-sm text-slate-600 underline">← Questionnaires</a>
        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold"><?= $h($q['name']) ?> <span class="ml-1 rounded-full px-2 py-0.5 align-middle text-xs font-medium <?= $pill[$q['status']] ?>">v<?= (int) $q['version'] ?> <?= $h($q['status']) ?></span></h1>
                <p class="font-mono text-xs text-slate-500"><?= $h($q['code']) ?> · <?= count($q['items']) ?> questions</p>
            </div>
            <?php if ($canEdit): ?>
                <div class="flex flex-wrap gap-2">
                    <?php if ($draft): ?>
                        <form method="post" action="/admin/corporate/questionnaires/<?= (int) $q['id'] ?>/publish" onsubmit="return confirm('Publish v<?= (int) $q['version'] ?>? It replaces the current version for every clinic and employee.')"><?= $csrfField ?><button class="rounded bg-emerald-700 px-3 py-2 text-xs font-semibold text-white">Publish v<?= (int) $q['version'] ?></button></form>
                        <form method="post" action="/admin/corporate/questionnaires/<?= (int) $q['id'] ?>/delete" onsubmit="return confirm('Delete this draft?')"><?= $csrfField ?><button class="rounded border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-rose-700">Delete draft</button></form>
                    <?php else: ?>
                        <form method="post" action="/admin/corporate/questionnaires/<?= (int) $q['id'] ?>/new-version"><?= $csrfField ?><button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Make a new version</button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>
        <?php if (!$draft): ?><p class="mt-3 rounded bg-white px-4 py-2 text-sm text-slate-600 shadow-sm">Published and retired versions can't be edited. Make a new version to change the questions. Past answers keep the wording they were given with.</p><?php endif; ?>

        <form method="post" action="/admin/corporate/questionnaires/<?= (int) $q['id'] ?>" class="mt-4 grid gap-3 rounded-lg bg-white p-4 shadow-sm">
            <?= $csrfField ?>
            <fieldset <?= $edit ? '' : 'disabled' ?> class="contents">
                <label class="text-xs text-slate-600">Name<input name="name" required maxlength="150" value="<?= $h($q['name']) ?>" class="<?= $in ?>"></label>
                <label class="text-xs text-slate-600">Introduction shown above the questions<input name="intro" maxlength="500" value="<?= $h($q['intro'] ?? '') ?>" class="<?= $in ?>"></label>
                <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="is_sample" value="1" <?= (int) $q['is_sample'] === 1 ? 'checked' : '' ?>> Mark as SAMPLE (not yet reviewed by an occupational physician)</label>
                <?php if ($edit): ?><div><button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Save details</button></div><?php endif; ?>
            </fieldset>
        </form>

        <section id="questions" class="mt-4 rounded-lg bg-white shadow-sm">
            <h2 class="px-4 pt-4 font-semibold">Questions</h2>
            <ul class="mt-2 divide-y divide-slate-100">
                <?php $sec = ''; foreach ($q['items'] as $it): ?>
                    <?php if ($it['section'] !== $sec): $sec = (string) $it['section']; ?><li class="bg-slate-50 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-slate-500"><?= $h($sec) ?></li><?php endif; ?>
                    <li class="flex flex-wrap items-start gap-3 px-4 py-2 text-sm">
                        <span class="w-10 shrink-0 text-xs text-slate-400"><?= (int) $it['sort_order'] ?></span>
                        <span class="min-w-0 flex-1"><?= $h($it['text']) ?>
                            <span class="block text-xs text-slate-500"><span class="font-mono"><?= $h($it['code']) ?></span> · <?= $h($typeLabel[$it['type']] ?? $it['type']) ?>
                                <?= $it['type'] === 'choice' ? ': ' . $h(implode(' / ', json_decode((string) $it['options_json'], true) ?: [])) : '' ?>
                                <?= !empty($it['detail_prompt']) ? ' · if Yes: “' . $h($it['detail_prompt']) . '”' : '' ?>
                                <?= (int) $it['required'] === 1 ? '' : ' · optional' ?></span></span>
                        <?php if ($edit): ?><form method="post" action="/admin/corporate/questionnaires/<?= (int) $q['id'] ?>/items/<?= (int) $it['id'] ?>/remove"><?= $csrfField ?><button class="text-xs text-rose-700 underline">Remove</button></form><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($q['items'] === []): ?><li class="px-4 py-4 text-sm text-slate-500">No questions yet.</li><?php endif; ?>
            </ul>
            <?php if ($edit): ?>
                <form method="post" action="/admin/corporate/questionnaires/<?= (int) $q['id'] ?>/items" class="grid gap-2 border-t border-slate-100 p-4 sm:grid-cols-6" x-data="{ t: 'yes_no' }">
                    <?= $csrfField ?>
                    <label class="text-xs text-slate-600 sm:col-span-4">Question<input name="text" required maxlength="300" class="<?= $in ?>"></label>
                    <label class="text-xs text-slate-600">Code<input name="code" required maxlength="40" placeholder="back_pain" class="<?= $in ?>"></label>
                    <label class="text-xs text-slate-600">Order<input type="number" name="sort_order" min="0" max="9999" value="<?= (int) (max(array_column($q['items'], 'sort_order') ?: [0]) + 10) ?>" class="<?= $in ?>"></label>
                    <label class="text-xs text-slate-600 sm:col-span-2">Section<input name="section" required maxlength="80" value="<?= $h($sec ?: 'Your health') ?>" class="<?= $in ?>"></label>
                    <label class="text-xs text-slate-600 sm:col-span-2">Answer type<select name="type" x-model="t" class="<?= $in ?>">
                        <?php foreach (QuestionnaireAdminService::TYPES as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
                    <label class="flex items-center gap-2 self-end pb-2 text-xs text-slate-600 sm:col-span-2"><input type="checkbox" name="required" value="1" checked> Must be answered</label>
                    <label x-show="t === 'yes_no'" class="text-xs text-slate-600 sm:col-span-6">Ask for detail when Yes <span class="text-slate-400">(optional)</span><input name="detail_prompt" maxlength="200" placeholder="e.g. Where, and since when?" class="<?= $in ?>"></label>
                    <label x-show="t === 'choice'" x-cloak class="text-xs text-slate-600 sm:col-span-6">Options, one per line<textarea name="options" rows="3" class="<?= $in ?>"></textarea></label>
                    <div class="sm:col-span-6"><button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Add / update question</button>
                        <span class="ml-2 text-xs text-slate-500">Using an existing code updates that question.</span></div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
