<?php
/**
 * /admin/corporate/companies — Workforce Health company review queue + list.
 *
 * @var list<array<string,mixed>> $companies
 * @var array<string,int> $counts
 * @var string $status
 * @var string $q
 * @var array<string,string> $countryNames
 */
$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pill = [
    'pending' => 'bg-amber-50 text-amber-800 ring-amber-200', 'active' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    'suspended' => 'bg-rose-50 text-rose-800 ring-rose-200', 'rejected' => 'bg-slate-100 text-slate-600 ring-slate-200', 'closed' => 'bg-slate-100 text-slate-600 ring-slate-200',
];
$statusLabel = ['' => 'All', 'pending' => 'Waiting for review', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Rejected', 'closed' => 'Closed'];
$total = array_sum($counts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Companies · Workforce Health · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Workforce Health · Companies</h1>
        <p class="text-sm text-slate-500">Employers who registered at app.eclinicpro.com/company. Review new companies, then manage their trial and plan.</p>

        <?php require __DIR__ . '/_corporate_flash.php'; ?>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <?php foreach ($statusLabel as $key => $label): ?>
                <?php $n = $key === '' ? $total : (int) ($counts[$key] ?? 0); $on = $status === $key; ?>
                <a href="/admin/corporate/companies<?= $key !== '' ? '?status=' . $h($key) : '' ?>"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 <?= $on ? 'bg-slate-800 text-white ring-slate-800' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50' ?>">
                    <?= $h($label) ?> <span class="<?= $on ? 'text-slate-300' : 'text-slate-400' ?>"><?= $n ?></span>
                </a>
            <?php endforeach; ?>
            <form method="get" action="/admin/corporate/companies" class="ml-auto flex gap-2">
                <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= $h($status) ?>"><?php endif; ?>
                <input name="q" value="<?= $h($q) ?>" placeholder="Name, ECW-ref or email" class="w-56 rounded border border-slate-200 px-2.5 py-1.5 text-sm">
                <button class="rounded bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Search</button>
            </form>
        </div>

        <div class="mt-4 overflow-x-auto ui-card">
            <?php if ($companies === []): ?>
                <p class="p-8 text-center text-sm text-slate-500">No companies<?= $status !== '' ? ' with this status' : ' yet' ?>.</p>
            <?php else: ?>
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr><th class="px-4 py-2.5">Company</th><th class="px-4 py-2.5">Country</th><th class="px-4 py-2.5">Industry · size</th>
                        <th class="px-4 py-2.5">Docs</th><th class="px-4 py-2.5">Users</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Registered</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($companies as $c): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2.5">
                                <a href="/admin/corporate/companies/<?= (int) $c['id'] ?>" class="font-medium text-slate-900 hover:underline"><?= $h($c['legal_name']) ?></a>
                                <div class="text-xs text-slate-500"><?= $h($c['public_ref']) ?> · <?= $h($c['contact_email'] ?? '') ?><?= (int) $c['is_demo'] === 1 ? ' · <span class="text-violet-600">demo</span>' : '' ?></div>
                            </td>
                            <td class="px-4 py-2.5"><?= $h($countryNames[$c['country_code']] ?? $c['country_code']) ?><div class="text-xs text-slate-500"><?= $h($c['city'] ?? '') ?></div></td>
                            <td class="px-4 py-2.5"><?= $h($c['industry_name'] ?? '—') ?><div class="text-xs text-slate-500"><?= $h($c['employee_size_band'] ?? '') ?> employees</div></td>
                            <td class="px-4 py-2.5"><?= (int) $c['doc_count'] ?><?= (int) $c['docs_pending'] > 0 ? ' <span class="text-xs text-amber-700">(' . (int) $c['docs_pending'] . ' to review)</span>' : '' ?></td>
                            <td class="px-4 py-2.5"><?= (int) $c['user_count'] ?></td>
                            <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 <?= $pill[$c['status']] ?? '' ?>"><?= $h($statusLabel[$c['status']] ?? $c['status']) ?></span>
                                <?php if ($c['status'] === 'active' && !empty($c['trial_ends_at']) && empty($c['plan_expires_at'])): ?>
                                    <div class="mt-0.5 text-xs text-slate-500">trial to <?= $h(\App\Support\Locale\LocalDate::date((string) $c['trial_ends_at'])) ?></div>
                                <?php elseif (!empty($c['plan_expires_at'])): ?>
                                    <div class="mt-0.5 text-xs text-slate-500">paid to <?= $h(\App\Support\Locale\LocalDate::date((string) $c['plan_expires_at'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-slate-500"><?= $h(\App\Support\Locale\LocalDate::dateTime((string) $c['created_at'], 'Asia/Kolkata')) ?> IST</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
