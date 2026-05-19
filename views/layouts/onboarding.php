<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Setup — ManageClinic') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4">
            <div class="flex items-center gap-2">
                <span class="text-lg font-semibold text-emerald-600">ManageClinic</span>
                <span class="text-xs text-slate-400">Setup</span>
            </div>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                <button type="submit" class="text-sm text-slate-500 hover:text-slate-700">Log out</button>
            </form>
        </div>
        <?php if (!empty($step)): ?>
        <div class="mx-auto max-w-4xl px-4 pb-4">
            <div class="flex items-center gap-2 text-xs">
                <?php
                $steps = [1 => 'Plan', 2 => 'Clinic', 3 => 'Specialty', 4 => 'Notify', 5 => 'Done'];
                foreach ($steps as $num => $label):
                    $active = ($step ?? 0) === $num;
                    $done = ($step ?? 0) > $num;
                ?>
                <div class="flex items-center gap-2 <?= $num < 5 ? 'flex-1' : '' ?>">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-medium
                        <?= $done ? 'bg-emerald-600 text-white' : ($active ? 'bg-emerald-100 text-emerald-700 ring-2 ring-emerald-500' : 'bg-slate-200 text-slate-500') ?>">
                        <?= $done ? '✓' : $num ?>
                    </span>
                    <span class="<?= $active ? 'font-medium text-slate-900' : 'text-slate-400' ?> hidden sm:inline"><?= $label ?></span>
                    <?php if ($num < 5): ?><div class="h-px flex-1 bg-slate-200"></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </header>
    <main class="mx-auto max-w-4xl px-4 py-8">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
