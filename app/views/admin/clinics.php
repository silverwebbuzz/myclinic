<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinics — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6">
        <h1 class="text-xl font-semibold">All clinics</h1>
        <?php if (!empty($_GET['error'])): ?>
        <p class="mt-2 text-sm text-red-600"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif; ?>
        <div class="mt-4 overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Clinic</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">MRR</th>
                        <th class="px-4 py-3">Churn</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clinics as $c): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3">
                            <span class="font-medium"><?= htmlspecialchars($c['name'] ?? '') ?></span>
                            <span class="block text-xs text-slate-500"><?= htmlspecialchars($c['slug'] ?? '') ?></span>
                        </td>
                        <td class="px-4 py-3"><?= htmlspecialchars($c['plan_label'] ?? '') ?></td>
                        <td class="px-4 py-3">$<?= number_format((float) ($c['mrr_usd'] ?? 0), 0) ?></td>
                        <td class="px-4 py-3">
                            <?php if (!empty($c['churn_flag'])): ?>
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800" title="<?= htmlspecialchars($c['churn_risk_reason'] ?? '') ?>">
                                <?= htmlspecialchars($c['churn_risk_level'] ?? 'risk') ?>
                            </span>
                            <?php else: ?>
                            <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 space-x-3">
                            <a href="/admin/clinics/<?= (int) ($c['id'] ?? 0) ?>" class="text-slate-700 hover:underline">Manage</a>
                            <form method="post" action="/admin/impersonate" class="inline">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                                <input type="hidden" name="clinic_id" value="<?= (int) ($c['id'] ?? 0) ?>">
                                <button type="submit" class="text-emerald-700 hover:underline">Impersonate</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
