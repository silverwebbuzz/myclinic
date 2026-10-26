<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patients — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-7xl p-6">
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
                   placeholder="Search by name, phone, UHID, email, clinic or city"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Search</button>
            <?php if (!empty($search)): ?>
            <a href="/admin/patients" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Clear</a>
            <?php endif; ?>
        </form>

        <div class="mt-4 overflow-x-auto ui-card">
            <table data-filter="Clinic,Doctor,Source,gender:Gender,state:State,city:City" class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Patient</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Clinic</th>
                        <th class="px-4 py-3">Doctor</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Visits</th>
                        <th class="px-4 py-3">Appts</th>
                        <th class="px-4 py-3">Last visit</th>
                        <th class="px-4 py-3">Registered</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($patients)): ?>
                    <tr><td colspan="11" class="px-4 py-6 text-center text-slate-400">No patients found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($patients as $p):
                        $genderLabel = ['M' => 'Male', 'F' => 'Female', 'Other' => 'Other'][$p['gender'] ?? ''] ?? '';
                        $age = '';
                        if (!empty($p['dob']) && !str_starts_with((string) $p['dob'], '0000')) {
                            $age = (string) (new DateTime((string) $p['dob']))->diff(new DateTime('today'))->y;
                        }
                        $meta = array_filter([$genderLabel, $age !== '' ? $age . ' yrs' : '', (string) ($p['blood_group'] ?? '')]);
                        $email = trim((string) ($p['email'] ?? '')) ?: trim((string) ($p['account_email'] ?? ''));
                        $phone = trim((string) ($p['phone'] ?? ''));
                        $address = trim((string) ($p['address'] ?? ''));
                        $registered = !empty($p['created_at']) ? strtotime((string) $p['created_at']) : false;
                    ?>
                    <tr class="border-b align-top"
                        data-f-gender="<?= htmlspecialchars($genderLabel) ?>"
                        data-f-state="<?= htmlspecialchars((string) ($p['state'] ?? '')) ?>"
                        data-f-city="<?= htmlspecialchars((string) ($p['city'] ?? '')) ?>">
                        <td class="px-4 py-3">
                            <span class="font-medium"><?= htmlspecialchars($p['name'] ?? '') ?></span>
                            <span class="block text-xs text-slate-500"><?= htmlspecialchars($p['uhid'] ?? '') ?></span>
                            <?php if ($meta): ?>
                            <span class="block text-xs text-slate-500"><?= htmlspecialchars(implode(' · ', $meta)) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($phone !== ''): ?>
                            <a href="tel:<?= htmlspecialchars($phone) ?>" class="block whitespace-nowrap text-emerald-700 hover:underline"><?= htmlspecialchars($phone) ?></a>
                            <?php endif; ?>
                            <?php if ($email !== ''): ?>
                            <a href="mailto:<?= htmlspecialchars($email) ?>" class="block max-w-[14rem] truncate text-xs text-slate-600 hover:underline" title="<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
                            <?php endif; ?>
                            <?php if ($phone === '' && $email === ''): ?><span class="text-slate-400">—</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!empty($p['city']) || !empty($p['state'])): ?>
                            <span class="block"><?= htmlspecialchars((string) ($p['city'] ?: '—')) ?></span>
                            <span class="block text-xs text-slate-500"><?= htmlspecialchars((string) ($p['state'] ?? '')) ?></span>
                            <?php elseif ($address !== ''): ?>
                            <span class="block max-w-[12rem] truncate text-xs text-slate-600" title="<?= htmlspecialchars($address) ?>"><?= htmlspecialchars($address) ?></span>
                            <?php else: ?>
                            <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?= htmlspecialchars($p['clinic_name'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= !empty($p['doctor_name']) ? htmlspecialchars((string) $p['doctor_name']) : '<span class="text-slate-400">—</span>' ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($p['source'] ?? '')))) ?></td>
                        <td class="px-4 py-3"><?= (int) ($p['visit_count'] ?? 0) ?></td>
                        <td class="px-4 py-3"><?= (int) ($p['appointment_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                            <?= !empty($p['last_visit_at']) ? htmlspecialchars(date('d M Y', strtotime((string) $p['last_visit_at']))) : '—' ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php if ($registered): ?>
                            <span class="block"><?= date('d M Y', $registered) ?></span>
                            <span class="block text-xs text-slate-500"><?= date('h:i A', $registered) ?></span>
                            <?php else: ?>
                            <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="/admin/patients/<?= (int) ($p['id'] ?? 0) ?>" class="text-slate-700 hover:underline">View history</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $page = (int) ($page ?? 1);
        $pages = (int) ($pages ?? 1);
        $qs = static fn (int $p): string => '/admin/patients?q=' . urlencode((string) ($search ?? '')) . '&page=' . $p;
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
