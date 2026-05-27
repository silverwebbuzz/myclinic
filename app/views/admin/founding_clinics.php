<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Founding Clinics — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6 space-y-6">

        <div>
            <h1 class="text-xl font-semibold">Founding clinics</h1>
            <p class="text-sm text-slate-500">
                First <?= (int) $state['cap'] ?> clinics get ₹999/month locked for 24 months,
                then auto-convert to ₹1,499.
            </p>
        </div>

        <?php if ($message): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars((string) $message) ?>
        </div>
        <?php endif; ?>

        <!-- Counter + cap controls -->
        <section class="rounded-xl border bg-white p-5 flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-500">Spots claimed</div>
                <div class="text-2xl font-semibold">
                    <?= (int) $state['claimed'] ?> / <?= (int) $state['cap'] ?>
                </div>
                <?php if (!empty($state['closed_at'])): ?>
                    <p class="text-xs text-rose-600 mt-1">
                        Closed at <?= htmlspecialchars((string) $state['closed_at']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <form method="post" action="/admin/founding-clinics" class="flex items-end gap-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="text-xs">
                    Cap
                    <input type="number" name="cap" value="<?= (int) $state['cap'] ?>" min="0"
                           class="ml-1 rounded border px-2 py-1 text-sm w-24">
                </label>
                <label class="text-xs inline-flex items-center">
                    <input type="checkbox" name="close" value="1" <?= !empty($state['closed_at']) ? 'checked' : '' ?>>
                    <span class="ml-1">Close program</span>
                </label>
                <button type="submit" class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">
                    Save
                </button>
            </form>
        </section>

        <!-- Roster -->
        <section class="rounded-xl border bg-white p-5">
            <h2 class="text-sm font-semibold">Roster</h2>
            <?php if (empty($clinics)): ?>
                <p class="mt-3 text-sm text-slate-500">No founding clinics yet.</p>
            <?php else: ?>
            <table class="mt-3 w-full text-left text-sm">
                <thead class="text-xs text-slate-500">
                    <tr>
                        <th class="py-2">Clinic</th>
                        <th>Granted</th>
                        <th>Locked until</th>
                        <th>Days left</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($clinics as $c):
                    $days = (int) ($c['days_left'] ?? 0);
                    $expiringSoon = $days >= 0 && $days <= 30;
                ?>
                    <tr class="border-t <?= $expiringSoon ? 'bg-amber-50' : '' ?>">
                        <td class="py-2">
                            <a href="/admin/clinics/<?= (int) $c['id'] ?>" class="font-medium hover:underline">
                                <?= htmlspecialchars($c['name']) ?>
                            </a>
                            <span class="block text-xs text-slate-500"><?= htmlspecialchars($c['slug']) ?></span>
                        </td>
                        <td class="text-xs text-slate-600"><?= htmlspecialchars(substr((string) $c['founding_clinic_locked_at'], 0, 10)) ?></td>
                        <td class="text-xs"><?= htmlspecialchars((string) ($c['founding_clinic_locked_until'] ?? '—')) ?></td>
                        <td class="text-xs">
                            <?php if ($days < 0): ?>
                                <span class="text-rose-600 font-semibold">Expired</span>
                            <?php elseif ($expiringSoon): ?>
                                <span class="text-amber-700 font-semibold"><?= $days ?> days</span>
                            <?php else: ?>
                                <span><?= $days ?> days</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/admin/clinics/<?= (int) $c['id'] ?>" class="text-xs text-emerald-700 hover:underline">Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
