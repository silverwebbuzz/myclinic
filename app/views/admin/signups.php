<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Signups — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Online signups</h1>
                <p class="text-sm text-slate-500">People who created an account or booked from the public site.</p>
            </div>
            <span class="rounded-full bg-slate-900 px-3 py-1 text-sm font-medium text-white">
                <?= number_format((int) ($total ?? 0)) ?> total
            </span>
        </div>

        <?php if (!empty($_GET['error'])): ?>
        <p class="mt-2 text-sm text-red-600"><?= htmlspecialchars($_GET['error']) ?></p>
        <?php endif; ?>

        <form method="get" action="/admin/signups" class="mt-4 flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>"
                   placeholder="Search by name, phone or email"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Search</button>
            <?php if (!empty($search)): ?>
            <a href="/admin/signups" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="mt-4 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Person</th>
                        <th class="px-4 py-3">Verified</th>
                        <th class="px-4 py-3">Clinics</th>
                        <th class="px-4 py-3">Logins</th>
                        <th class="px-4 py-3">Last seen</th>
                        <th class="px-4 py-3">Signed up</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($signups)): ?>
                    <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No online signups found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($signups as $s): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3">
                            <span class="font-medium"><?= htmlspecialchars($s['name'] ?? '') ?></span>
                            <span class="block text-xs text-slate-500">
                                <?= htmlspecialchars($s['phone'] ?? '—') ?><?= !empty($s['email']) ? ' · ' . htmlspecialchars($s['email']) : '' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!empty($s['phone_verified_at'])): ?>
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Verified</span>
                            <?php else: ?>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?= (int) ($s['clinic_count'] ?? 0) ?></td>
                        <td class="px-4 py-3"><?= (int) ($s['session_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-slate-500">
                            <?= !empty($s['last_seen_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $s['last_seen_at']))) : '—' ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <?= !empty($s['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $s['created_at']))) : '—' ?>
                        </td>
                        <td class="px-4 py-3">
                            <a href="/admin/signups/<?= (int) ($s['id'] ?? 0) ?>" class="text-slate-700 hover:underline">View activity</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $page = (int) ($page ?? 1);
        $pages = (int) ($pages ?? 1);
        $qs = static fn (int $p): string => '/admin/signups?q=' . urlencode((string) ($search ?? '')) . '&page=' . $p;
        ?>
        <div class="mt-3 flex items-center justify-between text-sm text-slate-500">
            <span>Page <?= $page ?> of <?= max(1, $pages) ?> · <?= number_format((int) ($total ?? 0)) ?> match<?= ((int) ($total ?? 0)) === 1 ? '' : 'es' ?></span>
            <div class="space-x-2">
                <?php if ($page > 1): ?>
                <a href="<?= htmlspecialchars($qs($page - 1)) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Previous</a>
                <?php else: ?>
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-300">Previous</span>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                <a href="<?= htmlspecialchars($qs($page + 1)) ?>" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 hover:bg-slate-50">Next</a>
                <?php else: ?>
                <span class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-300">Next</span>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
