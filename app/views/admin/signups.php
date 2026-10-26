<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Online Signups — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-7xl p-6">
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
                   placeholder="Search by name, phone, email or city"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Search</button>
            <?php if (!empty($search)): ?>
            <a href="/admin/signups" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Clear</a>
            <?php endif; ?>
        </form>

        <?php
        // Device of the latest login: the Flutter app sends Dart's default UA.
        $deviceOf = static function (?string $ua): string {
            $ua = (string) $ua;
            if ($ua === '') return '';
            if (stripos($ua, 'dart') !== false) return 'App';
            if (preg_match('/iphone|ipad/i', $ua)) return 'iPhone web';
            if (stripos($ua, 'android') !== false) return 'Android web';
            return 'Desktop web';
        };
        $sourceLabel = ['self_signup' => 'Self signup', 'clinic_created' => 'Added by clinic', 'imported' => 'Imported'];
        ?>
        <div class="mt-4 overflow-x-auto ui-card">
            <table data-filter="Verified,Source,Device,gender:Gender,state:State,city:City" class="w-full text-left text-sm">
                <thead class="border-b bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Person</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Verified</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Clinics</th>
                        <th class="px-4 py-3">Bookings</th>
                        <th class="px-4 py-3">Logins</th>
                        <th class="px-4 py-3">Device</th>
                        <th class="px-4 py-3">Last seen</th>
                        <th class="px-4 py-3">Signed up</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($signups)): ?>
                    <tr><td colspan="12" class="px-4 py-6 text-center text-slate-400">No online signups found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($signups as $s):
                        $genderLabel = ['M' => 'Male', 'F' => 'Female', 'Other' => 'Other'][$s['gender'] ?? ''] ?? '';
                        $age = '';
                        if (!empty($s['dob']) && !str_starts_with((string) $s['dob'], '0000')) {
                            $age = (string) (new DateTime((string) $s['dob']))->diff(new DateTime('today'))->y;
                        }
                        $meta = array_filter([$genderLabel, $age !== '' ? $age . ' yrs' : '', (string) ($s['blood_group'] ?? '')]);
                        $phone = trim((string) ($s['phone'] ?? ''));
                        $email = trim((string) ($s['email'] ?? ''));
                        $clinics = array_values(array_filter(explode('||', (string) ($s['clinic_names'] ?? ''))));
                        $device = $deviceOf($s['last_user_agent'] ?? null);
                        $signed = !empty($s['created_at']) ? strtotime((string) $s['created_at']) : false;
                        $seen = !empty($s['last_seen_at']) ? strtotime((string) $s['last_seen_at']) : false;
                    ?>
                    <tr class="border-b align-top"
                        data-f-gender="<?= htmlspecialchars($genderLabel) ?>"
                        data-f-state="<?= htmlspecialchars((string) ($s['state'] ?? '')) ?>"
                        data-f-city="<?= htmlspecialchars((string) ($s['city'] ?? '')) ?>">
                        <td class="px-4 py-3">
                            <span class="font-medium"><?= htmlspecialchars($s['name'] ?? '') ?></span>
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
                            <?php if (!empty($s['city']) || !empty($s['state'])): ?>
                            <span class="block"><?= htmlspecialchars((string) ($s['city'] ?: '—')) ?></span>
                            <span class="block text-xs text-slate-500"><?= htmlspecialchars((string) ($s['state'] ?? '')) ?></span>
                            <?php else: ?>
                            <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (!empty($s['phone_verified_at'])): ?>
                            <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">Verified</span>
                            <?php else: ?>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Unverified</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap"><?= htmlspecialchars($sourceLabel[$s['source'] ?? ''] ?? ucwords(str_replace('_', ' ', (string) ($s['source'] ?? '')))) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($clinics): ?>
                            <span class="block max-w-[12rem] truncate" title="<?= htmlspecialchars(implode(', ', $clinics)) ?>"><?= htmlspecialchars($clinics[0]) ?></span>
                            <?php if (count($clinics) > 1): ?><span class="block text-xs text-slate-500">+<?= count($clinics) - 1 ?> more</span><?php endif; ?>
                            <?php else: ?>
                            <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?= (int) ($s['booking_count'] ?? 0) ?>
                            <?php if (!empty($s['doctor_name'])): ?>
                            <span class="block whitespace-nowrap text-xs text-slate-500" title="Doctor of the latest booking"><?= htmlspecialchars((string) $s['doctor_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3"><?= (int) ($s['session_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap"><?= $device !== '' ? htmlspecialchars($device) : '<span class="text-slate-400">—</span>' ?></td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                            <?php if ($seen): ?>
                            <span class="block"><?= date('d M Y', $seen) ?></span>
                            <span class="block text-xs"><?= date('h:i A', $seen) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php if ($signed): ?>
                            <span class="block"><?= date('d M Y', $signed) ?></span>
                            <span class="block text-xs text-slate-500"><?= date('h:i A', $signed) ?></span>
                            <?php else: ?><span class="text-slate-400">—</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
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
