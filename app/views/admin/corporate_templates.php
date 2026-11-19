<?php
/**
 * /admin/corporate/templates — screening templates (all versions).
 *
 * @var list<array<string,mixed>> $templates  newest version first per code
 * @var list<array<string,mixed>> $countries  the admin's countries
 * @var list<array<string,mixed>> $industries
 * @var bool $canEdit
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
$in = 'mt-1 block w-full rounded border border-slate-200 px-2.5 py-1.5 text-sm';
$pill = ['draft' => 'bg-amber-50 text-amber-800', 'published' => 'bg-emerald-50 text-emerald-800', 'retired' => 'bg-slate-100 text-slate-500'];
$countryNames = array_column(\App\Support\Locale\CountryRepo::allCountries(), 'name', 'code');
// One row per code: the newest version, plus the other versions listed under it.
$byCode = [];
foreach ($templates as $t) {
    $byCode[$t['code']][] = $t;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Screening templates · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl px-6 py-6" x-data="{ add: false }">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Workforce Health · Screening templates</h1>
                <p class="text-sm text-slate-500">Programme templates companies adopt, per country × industry. Edit a draft, then publish. Publishing a new version never changes a company's programme: they see "Update available" and review it. <a href="/admin/corporate/exam-types" class="font-semibold text-slate-700 underline">Exam catalogue</a></p>
            </div>
            <?php if ($canEdit): ?><button type="button" @click="add = !add" class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">New template</button><?php endif; ?>
        </div>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <?php if ($canEdit): ?>
            <form x-show="add" x-cloak method="post" action="/admin/corporate/templates" class="mt-4 grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-4">
                <?= $csrfField ?>
                <label class="text-xs text-slate-600">Country<select name="country_code" required class="<?= $in ?>"><?php foreach ($countries as $c): ?><option value="<?= $h($c['code']) ?>"><?= $h($c['name']) ?></option><?php endforeach; ?></select></label>
                <label class="text-xs text-slate-600">Code<input name="code" required maxlength="40" placeholder="IN-CON-WELD" class="<?= $in ?> font-mono uppercase"></label>
                <label class="text-xs text-slate-600">Industry<select name="industry_id" class="<?= $in ?>"><option value="0">Any industry</option><?php foreach ($industries as $i): ?><option value="<?= (int) $i['id'] ?>"><?= $h($i['name']) ?></option><?php endforeach; ?></select></label>
                <label class="text-xs text-slate-600">Name<input name="name" required maxlength="150" placeholder="Welding & hot work" class="<?= $in ?>"></label>
                <label class="text-xs text-slate-600 sm:col-span-2">For whom<input name="audience" maxlength="255" placeholder="Welders, gas cutters, fabricators" class="<?= $in ?>"></label>
                <label class="text-xs text-slate-600 sm:col-span-2">Source / regulation<input name="source_note" maxlength="500" class="<?= $in ?>"></label>
                <div class="sm:col-span-4"><button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Create draft</button></div>
            </form>
        <?php endif; ?>

        <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2.5">Template</th><th class="px-4 py-2.5">Country · industry</th><th class="px-4 py-2.5">Versions</th><th class="px-4 py-2.5">Companies</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($byCode === []): ?><tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No templates yet.</td></tr><?php endif; ?>
                    <?php foreach ($byCode as $code => $versions): $t = $versions[0]; ?>
                        <tr>
                            <td class="px-4 py-2.5"><a href="/admin/corporate/templates/<?= (int) $t['id'] ?>" class="font-medium underline"><?= $h($t['name']) ?></a>
                                <?php if ((int) $t['is_sample'] === 1): ?><span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Sample</span><?php endif; ?>
                                <div class="font-mono text-xs text-slate-500"><?= $h($code) ?> · <?= (int) $t['item_count'] ?> checks</div></td>
                            <td class="px-4 py-2.5"><?= $h($countryNames[$t['country_code']] ?? $t['country_code']) ?> · <?= $h($t['industry_name'] ?? 'Any industry') ?></td>
                            <td class="px-4 py-2.5"><?php foreach ($versions as $v): ?><a href="/admin/corporate/templates/<?= (int) $v['id'] ?>" class="mr-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium <?= $pill[$v['status']] ?>">v<?= (int) $v['version'] ?> <?= $h($v['status']) ?></a><?php endforeach; ?></td>
                            <td class="px-4 py-2.5"><?= (int) $t['companies_using'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
