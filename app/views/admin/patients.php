<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patients — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Patients on-board</h1>
            <span class="rounded-full bg-slate-900 px-3 py-1 text-sm font-medium text-white">
                <?= number_format((int) ($total ?? 0)) ?> total
            </span>
        </div>

        <?php if (!empty($_GET['error'])): ?>
        <p class="mt-2 text-sm text-red-600"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif; ?>

        <form method="get" action="/admin/patients" class="mt-4 flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>"
                   placeholder="Search by name, phone, UHID or email"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Search</button>
            <?php if (!empty($search)): ?>
            <a href="/admin/patients" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="mt-4 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Clinic</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Visits</th>
                        <th class="px-4 py-3">Appts</th>
                        <th class="px-4 py-3">Last visit</th>
                        <th class="px-4 py-3">On-board</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                    <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">No patients found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($patients as $p): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3">
                            <span class="font-medium"><?= htmlspecialchars($p['name'] ?? '') ?></span>
                            <span class="block text-xs text-slate-500">
                                <?= htmlspecialchars($p['uhid'] ?? '') ?> · <?= htmlspecialchars($p['phone'] ?? '') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3"><?= htmlspecialchars($p['clinic_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars(ucfirst((string) ($p['source'] ?? ''))) ?></td>
                        <td class="px-4 py-3"><?= (int) ($p['visit_count'] ?? 0) ?></td>
                        <td class="px-4 py-3"><?= (int) ($p['appointment_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-slate-500">
                            <?= !empty($p['last_visit_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $p['last_visit_at']))) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <?= !empty($p['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $p['created_at']))) : '—' ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/admin/patients/<?= (int) ($p['id'] ?? 0) ?>" class="text-slate-700 hover:underline">View history</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-400">Showing up to 200 most-recent patients. Use search to narrow.</p>
    </main>
</body>
</html>
