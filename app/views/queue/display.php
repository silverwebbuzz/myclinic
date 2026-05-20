<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($clinic['name'] ?? 'Queue') ?> — Waiting room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="10">
    <style>
        :root { --brand: <?= htmlspecialchars($clinic['brand_color'] ?? '#0F9B6E') ?>; }
        .text-brand { color: var(--brand); }
    </style>
</head>
<body class="min-h-screen bg-slate-900 text-white">
    <div class="mx-auto max-w-4xl p-8">
        <h1 class="text-center text-3xl font-bold"><?= htmlspecialchars($clinic['name'] ?? 'Clinic') ?></h1>
        <p class="mt-2 text-center text-slate-400">Now serving · <?= date('l, d M Y') ?></p>

        <div id="queue-display" class="mt-10 space-y-4">
            <?php if ($queue === []): ?>
            <p class="text-center text-xl text-slate-400">No patients in queue</p>
            <?php else: ?>
            <?php
            $serving = null;
            foreach ($queue as $row) {
                if (($row['status'] ?? '') === 'in_progress') {
                    $serving = $row;
                    break;
                }
            }
            if ($serving):
            ?>
            <div class="rounded-2xl border-2 border-emerald-400 bg-emerald-950 p-8 text-center">
                <p class="text-sm uppercase tracking-widest text-emerald-300">Now serving</p>
                <?php if (!empty($serving['token_number'])): ?>
                <p class="mt-2 text-6xl font-bold text-brand">#<?= (int) $serving['token_number'] ?></p>
                <?php endif; ?>
                <p class="mt-4 text-3xl font-semibold"><?= htmlspecialchars($serving['patient_name'] ?? '') ?></p>
                <p class="text-slate-400"><?= htmlspecialchars($serving['doctor_name'] ?? '') ?></p>
            </div>
            <?php endif; ?>

            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ($queue as $row): ?>
                <?php if (($row['status'] ?? '') === 'in_progress') continue; ?>
                <div class="rounded-xl bg-slate-800 p-4 flex items-center justify-between">
                    <div>
                        <?php if (!empty($row['token_number'])): ?>
                        <span class="text-2xl font-bold text-brand">#<?= (int) $row['token_number'] ?></span>
                        <?php endif; ?>
                        <p class="text-lg"><?= htmlspecialchars($row['patient_name'] ?? '') ?></p>
                        <p class="text-sm text-slate-400"><?= htmlspecialchars($row['doctor_name'] ?? '') ?></p>
                    </div>
                    <span class="text-xs uppercase text-slate-500"><?= htmlspecialchars(str_replace('_', ' ', $row['status'] ?? '')) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
