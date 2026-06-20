<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($patient['name'] ?? 'Patient') ?> — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6">
        <a href="/admin/patients" class="text-sm text-slate-500 hover:underline">&larr; Back to patients</a>

        <!-- Profile -->
        <div class="mt-3 ui-card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-semibold"><?= htmlspecialchars($patient['name'] ?? '') ?></h1>
                    <p class="text-sm text-slate-500">
                        UHID <?= htmlspecialchars($patient['uhid'] ?? '') ?>
                        · <?= htmlspecialchars($patient['clinic_name'] ?? '—') ?>
                    </p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-medium <?= !empty($patient['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                    <?= !empty($patient['is_active']) ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-slate-400">Phone</dt><dd><?= htmlspecialchars($patient['phone'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">Email</dt><dd><?= htmlspecialchars($patient['email'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">Gender</dt><dd><?= htmlspecialchars($patient['gender'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">DOB</dt><dd><?= !empty($patient['dob']) ? htmlspecialchars((string) $patient['dob']) : '—' ?></dd></div>
                <div><dt class="text-slate-400">Blood group</dt><dd><?= htmlspecialchars($patient['blood_group'] ?? '—') ?></dd></div>
                <div><dt class="text-slate-400">Source</dt><dd><?= htmlspecialchars(ucfirst((string) ($patient['source'] ?? '—'))) ?></dd></div>
                <div><dt class="text-slate-400">On-board</dt><dd><?= !empty($patient['created_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $patient['created_at']))) : '—' ?></dd></div>
                <div><dt class="text-slate-400">Allergies</dt><dd><?= htmlspecialchars($patient['allergies'] ?? '—') ?></dd></div>
            </dl>
        </div>

        <!-- Visit history -->
        <h2 class="mt-6 text-lg font-semibold">Visit history <span class="text-sm font-normal text-slate-400">(<?= count($visits ?? []) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Chief complaint</th>
                        <th class="px-4 py-3">Diagnosis</th>
                        <th class="px-4 py-3">Follow-up</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visits)): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No visits recorded.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($visits as $v): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars(date('d M Y', strtotime((string) ($v['visited_at'] ?? 'now')))) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($v['doctor_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($v['chief_complaint'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($v['diagnosis'] ?? '—') ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= !empty($v['follow_up_date']) ? htmlspecialchars((string) $v['follow_up_date']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Appointment history -->
        <h2 class="mt-6 text-lg font-semibold">Appointments <span class="text-sm font-normal text-slate-400">(<?= count($appointments ?? []) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Scheduled</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No appointments recorded.</td></tr>
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
                        <td class="px-4 py-3"><?= htmlspecialchars($a['doctor_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars(ucfirst((string) ($a['type'] ?? '—'))) ?></td>
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

        <!-- Activity log -->
        <h2 class="mt-6 text-lg font-semibold">Activity log <span class="text-sm font-normal text-slate-400">(<?= count($activity ?? []) ?>)</span></h2>
        <div class="mt-2 overflow-x-auto ui-card">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">When</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">By</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activity)): ?>
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No activity recorded.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($activity as $act): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500"><?= htmlspecialchars(date('d M Y, H:i', strtotime((string) ($act['created_at'] ?? 'now')))) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars((string) ($act['action'] ?? '')) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($act['user_name'] ?? 'System') ?></td>
                        <td class="px-4 py-3 text-slate-400"><?= htmlspecialchars($act['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
