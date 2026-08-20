<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Setup — ManageClinic') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
    <script defer src="/assets/alpine.min.js"></script>
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
        <?php $onboardingStep = (int) ($onboardingStep ?? $step ?? 1); ?>
        <div class="mx-auto max-w-4xl px-4 pb-4">
            <div class="flex items-center gap-2 text-xs">
                <?php
                $steps = [1 => 'Plan', 2 => 'Clinic', 3 => 'Specialty', 4 => 'Notify', 5 => 'Done'];
                foreach ($steps as $num => $label):
                    $active = ($step ?? 0) === $num;
                    $done = $onboardingStep > $num;
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
        <?php if (!empty($onboardingResumed)): ?>
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            Welcome back — your setup progress was saved. Continue where you left off.
        </div>
        <?php endif; ?>
        <?php if (!empty($step) && (int) ($step ?? 0) < 5): ?>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm">
            <p class="text-slate-600">
                Your progress is saved automatically. Click <strong>Continue</strong> when a step is complete.
            </p>
            <span id="onboarding-draft-status" class="text-xs text-slate-400"></span>
        </div>
        <?php endif; ?>
        <?= $content ?? '' ?>
    </main>
    <?php require dirname(__DIR__) . '/onboarding/_draft_script.php'; ?>
</body>
</html>
