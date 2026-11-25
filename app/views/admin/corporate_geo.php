<?php
/**
 * /admin/corporate/geo — countries (switch on per market) + industries.
 *
 * @var list<array<string,mixed>> $countries
 * @var list<array<string,mixed>> $industries  (super only)
 * @var bool $isSuper
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$market = ['india' => 'India', 'middle_east' => 'Middle East', 'africa' => 'Africa', 'europe' => 'Europe', 'other' => 'Other'];
$csrfField = '<input type="hidden" name="_csrf" value="' . $h($csrf) . '">';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Countries &amp; industries · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Workforce Health · Countries &amp; industries</h1>
        <p class="text-sm text-slate-500">Country facts (currency, phone, time zone, form labels) come from here, not from code. Only <strong>active</strong> countries are offered at company sign-up.</p>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <div class="mt-4 overflow-x-auto ui-card">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2.5">Country</th><th class="px-4 py-2.5">Market</th><th class="px-4 py-2.5">Phone</th><th class="px-4 py-2.5">Currency</th>
                        <th class="px-4 py-2.5">Time zone</th><th class="px-4 py-2.5">Labels</th><th class="px-4 py-2.5">Regions</th><th class="px-4 py-2.5">Sign-up</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($countries as $c): ?>
                        <tr class="<?= (int) $c['is_active'] === 1 ? '' : 'text-slate-500' ?>">
                            <td class="px-4 py-2.5 font-medium"><?= $h($c['name']) ?> <span class="text-xs text-slate-400"><?= $h($c['code']) ?></span></td>
                            <td class="px-4 py-2.5"><?= $h($market[$c['market']] ?? $c['market']) ?></td>
                            <td class="px-4 py-2.5">+<?= $h($c['phone_code']) ?></td>
                            <td class="px-4 py-2.5"><?= $h($c['currency_code']) ?></td>
                            <td class="px-4 py-2.5 text-xs"><?= $h($c['default_timezone']) ?></td>
                            <td class="px-4 py-2.5 text-xs"><?= $h($c['region_label']) ?> · <?= $h($c['tax_id_label'] ?? 'no tax ID') ?> · <?= $h($c['company_id_label'] ?? '—') ?></td>
                            <td class="px-4 py-2.5"><?= (int) $c['region_count'] ?></td>
                            <td class="px-4 py-2.5"><?= (int) $c['is_active'] === 1 ? '<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800">On</span>' : '<span class="text-xs">Off</span>' ?></td>
                            <td class="px-4 py-2.5 text-right"><a href="/admin/corporate/geo/countries/<?= $h($c['code']) ?>" class="text-xs font-semibold text-slate-700 underline">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($isSuper): ?>
            <h2 class="mt-8 text-lg font-semibold">Industries</h2>
            <p class="text-sm text-slate-500">Companies pick one at sign-up. Screening rule templates will be organised by country × industry.</p>
            <form method="post" action="/admin/corporate/geo/industries" class="mt-3 flex flex-wrap items-end gap-2">
                <?= $csrfField ?>
                <label class="text-xs text-slate-600">Name<input name="name" required maxlength="100" class="mt-1 block w-64 rounded border border-slate-200 px-2.5 py-1.5 text-sm"></label>
                <label class="text-xs text-slate-600">ISIC code <span class="text-slate-400">(optional)</span><input name="isic_code" maxlength="10" class="mt-1 block w-28 rounded border border-slate-200 px-2.5 py-1.5 text-sm"></label>
                <button class="rounded bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Add industry</button>
            </form>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($industries as $i): ?>
                    <div class="flex items-center justify-between gap-2 rounded bg-white px-3 py-2 text-sm ring-1 ring-slate-200 <?= (int) $i['is_active'] === 1 ? '' : 'opacity-60' ?>">
                        <div><?= $h($i['name']) ?> <span class="text-xs text-slate-400"><?= $h($i['isic_code'] ?? '') ?> · <?= (int) $i['company_count'] ?> co.</span></div>
                        <form method="post" action="/admin/corporate/geo/industries/<?= (int) $i['id'] ?>/toggle">
                            <?= $csrfField ?><button class="text-xs font-semibold <?= (int) $i['is_active'] === 1 ? 'text-slate-500' : 'text-emerald-700' ?> underline"><?= (int) $i['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
