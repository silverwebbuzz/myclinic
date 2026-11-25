<?php
/**
 * /admin/corporate/questionnaires — every questionnaire version.
 *
 * @var list<array<string,mixed>> $rows
 * @var bool $canEdit
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pill = ['draft' => 'bg-amber-50 text-amber-800', 'published' => 'bg-emerald-50 text-emerald-800', 'retired' => 'bg-slate-100 text-slate-500'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Questionnaires · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl px-6 py-6">
        <h1 class="text-xl font-semibold">Health questionnaires</h1>
        <p class="text-sm text-slate-600">The pre-exam questionnaire employees and clinics fill before a work health check. The published version is the one in use. <?= $canEdit ? '' : 'Only super admins can change it.' ?></p>
        <?php require __DIR__ . '/_corporate_flash.php'; ?>
        <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="px-4 py-2 font-medium">Questionnaire</th><th class="px-3 py-2 font-medium">Code</th>
                    <th class="px-3 py-2 font-medium">Version</th><th class="px-3 py-2 text-right font-medium">Questions</th><th class="px-4 py-2 text-right font-medium">Answered</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="px-4 py-2"><a href="/admin/corporate/questionnaires/<?= (int) $r['id'] ?>" class="font-medium underline"><?= $h($r['name']) ?></a>
                                <?php if ((int) $r['is_sample'] === 1): ?><span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-800">Sample</span><?php endif; ?></td>
                            <td class="px-3 py-2 font-mono text-xs"><?= $h($r['code']) ?></td>
                            <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $pill[$r['status']] ?>">v<?= (int) $r['version'] ?> <?= $h($r['status']) ?></span></td>
                            <td class="px-3 py-2 text-right"><?= (int) $r['items'] ?></td>
                            <td class="px-4 py-2 text-right"><?= (int) $r['responses'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?><tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">None yet: run the Phase 2D SQL patch.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
