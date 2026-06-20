<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($identity['name'] ?? 'Signup') ?> — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6">
        <a href="/admin/signups" class="text-sm text-slate-500 hover:underline">&larr; Back to signups</a>

        <!-- Identity -->
        <div class="mt-3 ui-card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-semibold"><?= htmlspecialchars($identity['name'] ?? '') ?></h1>
                    <p class="text-sm text-slate-500">
                        Online signup · ID <?= (int) ($identity['id'] ?? 0) ?>
                        · <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($identity['source'] ?? '')))) ?>
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-medium <?= !empty($identity['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                    <?= !empty($identity['is_active']) ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-slate-400">Phone</dt><dd><?= htmlspecialchars($identity['phone'] ?? '—') ?> <?= !empty($identity['phone_verified_at']) ? '<span class="text-emerald-600">✓</span>' : '' ?></dd></div>
                <div><dt class="text-slate-400">Email</dt><dd><?= htmlspecialchars($identity['email'] ?? '—') ?> <?= !empty($identity['email_verified_at']) ? '<span class="text-emerald-600">✓</span>' : '' ?></dd></div>
                <div><dt class="text-slate-400">Gender</dt><dd><?= htmlspecialchars($identity['gender'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">DOB</dt><dd><?= !empty($identity['dob']) ? htmlspecialchars((string) $identity['dob']) : '—' ?></dd></div>
                <div><dt class="text-slate-400">Blood group</dt><dd><?= htmlspecialchars($identity['blood_group'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">Allergies</dt><dd><?= htmlspecialchars($identity['allergies'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">Signed up</dt><dd><?= !empty($identity['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $identity['created_at']))) : '—' ?></dd></div>
            </dl>
        </div>

        <!-- Linked clinic charts -->
        <h2 class="mt-6 text-lg font-semibold">Clinics joined <span class="text-sm font-normal text-slate-400">(<?= count($charts ?? []) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Clinic</th>
                        <th class="px-4 py-3">UHID</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Joined</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($charts)): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Not yet linked to any clinic.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($charts as $c): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3"><?= htmlspecialchars($c['clinic_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($c['uhid'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($c['source'] ?? '')))) ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= !empty($c['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $c['created_at']))) : '—' ?></td>
                        <td class="px-4 py-3"><a href="/admin/patients/<?= (int) ($c['id'] ?? 0) ?>" class="text-slate-700 hover:underline">Chart</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Bookings -->
        <h2 class="mt-6 text-lg font-semibold">Bookings <span class="text-sm font-normal text-slate-400">(<?= count($appointments ?? []) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Scheduled</th>
                        <th class="px-4 py-3">Clinic</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No bookings yet.</td></tr>
                    <?php endif; ?>
                    <?php
                    $statusColors = [
                        'completed' => 'bg-emerald-100 text-emerald-800',
                        'cancelled' => 'bg-red-100 text-red-700',
                        'no_show' => 'bg-amber-100 text-amber-800',
                    ];
                    ?>
                    <?php foreach ($appointments as $a): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) ($a['scheduled_at'] ?? 'now')))) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($a['clinic_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($a['doctor_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars(ucfirst((string) ($a['source'] ?? '—'))) ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded px-2 py-0.5 text-xs <?= $statusColors[$a['status'] ?? ''] ?? 'bg-slate-100 text-slate-600' ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', (string) ($a['status'] ?? ''))) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Login sessions -->
        <h2 class="mt-6 text-lg font-semibold">Login activity <span class="text-sm font-normal text-slate-400">(<?= count($sessions ?? []) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Last seen</th>
                        <th class="px-4 py-3">Started</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Device</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No logins recorded.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $s): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500"><?= !empty($s['last_seen_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime((string) $s['last_seen_at']))) : '—' ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500"><?= !empty($s['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime((string) $s['created_at']))) : '—' ?></td>
                        <td class="px-4 py-3 text-slate-400"><?= htmlspecialchars($s['ip'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-400 max-w-xs truncate" title="<?= htmlspecialchars($s['user_agent'] ?? '') ?>"><?= htmlspecialchars($s['user_agent'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Wishlist -->
        <?php if (!empty($wishlist)): ?>
        <h2 class="mt-6 text-lg font-semibold">Saved doctors <span class="text-sm font-normal text-slate-400">(<?= count($wishlist) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Saved</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($wishlist as $w): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3"><?= htmlspecialchars($w['doctor_name'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= !empty($w['added_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $w['added_at']))) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
