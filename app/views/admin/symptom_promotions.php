<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Symptom Promotions — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6 space-y-4">

        <div>
            <h1 class="text-xl font-semibold">Symptom promotions</h1>
            <p class="text-sm text-slate-500">
                Labels used by 10+ doctors with 30+ total uses. Promote to make
                them globally searchable, or ignore to suppress.
            </p>
        </div>

        <?php if ($message): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars((string) $message) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($candidates)): ?>
            <div class="rounded-xl border bg-white p-8 text-center text-sm text-slate-500">
                No promotion candidates right now. Doctors will start adding
                custom symptoms; this queue fills automatically.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Label</th>
                            <th class="px-4 py-3">Doctors</th>
                            <th class="px-4 py-3">Uses</th>
                            <th class="px-4 py-3">Last seen</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($candidates as $c): ?>
                        <tr class="border-b align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium"><?= htmlspecialchars($c['label']) ?></div>
                            </td>
                            <td class="px-4 py-3"><?= (int) $c['doctors'] ?></td>
                            <td class="px-4 py-3"><?= (int) $c['total_uses'] ?></td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                <?= htmlspecialchars(substr((string) $c['last_used'], 0, 10)) ?>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer text-xs text-emerald-700 hover:underline">Promote</summary>
                                    <form method="post" action="/admin/symptom-promotions/promote"
                                          class="mt-2 w-72 space-y-2 rounded border bg-slate-50 p-3 text-xs">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="label" value="<?= htmlspecialchars($c['label']) ?>">
                                        <label class="block">
                                            <span class="text-slate-600">Category (optional)</span>
                                            <input type="text" name="category"
                                                   placeholder="e.g. constitutional, respiratory"
                                                   class="mt-1 w-full rounded border px-2 py-1 text-xs">
                                        </label>
                                        <label class="block">
                                            <span class="text-slate-600">Specialties (comma-sep)</span>
                                            <input type="text" name="specialties"
                                                   placeholder="e.g. gp, peds"
                                                   class="mt-1 w-full rounded border px-2 py-1 text-xs">
                                        </label>
                                        <button type="submit"
                                                class="w-full rounded bg-emerald-600 px-3 py-1.5 font-semibold text-white hover:bg-emerald-700">
                                            Confirm promote
                                        </button>
                                    </form>
                                </details>
                                <form method="post" action="/admin/symptom-promotions/ignore" class="inline ml-2">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="label" value="<?= htmlspecialchars($c['label']) ?>">
                                    <button type="submit" class="text-xs text-rose-600 hover:underline"
                                            onclick="return confirm('Ignore this label permanently?')">
                                        Ignore
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>
</body>
</html>
