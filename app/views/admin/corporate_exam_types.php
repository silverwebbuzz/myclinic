<?php
/**
 * /admin/corporate/exam-types — the shared exam / test catalogue.
 *
 * @var list<array<string,mixed>> $examTypes  + used
 * @var bool $canEdit  super admins only
 */
use App\Services\Corporate\ProgrammeService;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
$in = 'mt-1 block w-full rounded border border-slate-200 px-2.5 py-1.5 text-sm';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exam catalogue · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Workforce Health · Exam catalogue</h1>
        <p class="text-sm text-slate-500">Every examination, test, scan, questionnaire and vaccination that screening templates and company programmes can ask for. Clinics and labs will fulfil these. Codes never change once created; switch an item off instead of deleting it.</p>
        <p class="mt-1 text-sm"><a href="/admin/corporate/templates" class="font-semibold text-slate-700 underline">Screening templates →</a></p>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <?php if ($canEdit): ?>
            <form method="post" action="/admin/corporate/exam-types" class="mt-4 grid gap-2 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-6 sm:items-end">
                <?= $csrfField ?><input type="hidden" name="id" value="0">
                <label class="text-xs text-slate-600">Code<input name="code" required maxlength="40" placeholder="blood_lead" class="<?= $in ?> font-mono"></label>
                <label class="text-xs text-slate-600 sm:col-span-2">Name<input name="name" required maxlength="120" class="<?= $in ?>"></label>
                <label class="text-xs text-slate-600">Kind<select name="kind" class="<?= $in ?>"><?php foreach (ProgrammeService::KINDS as $k => $l): ?><option value="<?= $h($k) ?>"><?= $h($l) ?></option><?php endforeach; ?></select></label>
                <label class="text-xs text-slate-600">Order<input type="number" name="sort_order" value="300" class="<?= $in ?>"></label>
                <button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Add to catalogue</button>
                <label class="text-xs text-slate-600 sm:col-span-6">Description <span class="text-slate-400">(optional)</span><input name="description" maxlength="255" class="<?= $in ?>"></label>
            </form>
        <?php endif; ?>

        <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2.5">Name</th><th class="px-4 py-2.5">Code</th><th class="px-4 py-2.5">Kind</th><th class="px-4 py-2.5">In templates</th><th class="px-4 py-2.5">Status</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($examTypes as $x): ?>
                        <tr class="<?= (int) $x['is_active'] === 1 ? '' : 'text-slate-400' ?>" x-data="{ edit: false }">
                            <td class="px-4 py-2.5"><div class="font-medium"><?= $h($x['name']) ?></div><div class="text-xs text-slate-500"><?= $h($x['description'] ?? '') ?></div>
                                <?php if ($canEdit): ?>
                                    <form x-show="edit" x-cloak method="post" action="/admin/corporate/exam-types" class="mt-2 grid gap-2 sm:grid-cols-4">
                                        <?= $csrfField ?><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                                        <input name="name" required maxlength="120" value="<?= $h($x['name']) ?>" class="<?= $in ?> sm:col-span-2">
                                        <select name="kind" class="<?= $in ?>"><?php foreach (ProgrammeService::KINDS as $k => $l): ?><option value="<?= $h($k) ?>" <?= $k === $x['kind'] ? 'selected' : '' ?>><?= $h($l) ?></option><?php endforeach; ?></select>
                                        <input type="number" name="sort_order" value="<?= (int) $x['sort_order'] ?>" class="<?= $in ?>">
                                        <input name="description" maxlength="255" value="<?= $h($x['description'] ?? '') ?>" placeholder="Description" class="<?= $in ?> sm:col-span-3">
                                        <button class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                                    </form>
                                <?php endif; ?></td>
                            <td class="px-4 py-2.5 font-mono text-xs"><?= $h($x['code']) ?></td>
                            <td class="px-4 py-2.5"><?= $h(ProgrammeService::KINDS[$x['kind']] ?? $x['kind']) ?></td>
                            <td class="px-4 py-2.5"><?= (int) $x['used'] ?></td>
                            <td class="px-4 py-2.5"><?= (int) $x['is_active'] === 1 ? '<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">Active</span>' : '<span class="text-xs">Off</span>' ?></td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <?php if ($canEdit): ?>
                                    <button type="button" @click="edit = !edit" class="text-xs font-semibold text-slate-700 underline">Edit</button>
                                    <form method="post" action="/admin/corporate/exam-types/<?= (int) $x['id'] ?>/toggle" class="ml-2 inline"><?= $csrfField ?><button class="text-xs text-slate-500 underline"><?= (int) $x['is_active'] === 1 ? 'Switch off' : 'Switch on' ?></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
