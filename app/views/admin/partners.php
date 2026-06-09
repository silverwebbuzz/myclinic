<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Partners — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl p-6">
    <?php if (!empty($message)): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold text-slate-900">Partners</h1>
        <div class="flex gap-2 text-sm">
            <?php foreach (['' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Rejected'] as $val => $label): ?>
                <a href="/admin/partners<?= $val ? '?status=' . $val : '' ?>"
                   class="rounded-lg px-3 py-1 <?= ($filterStatus ?? '') === $val ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Global program settings -->
    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Program settings</h2>
        <form method="post" action="/admin/partner-settings" class="mt-3 grid gap-3 sm:grid-cols-5 sm:items-end">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <div>
                <label class="block text-xs text-slate-500">Default commission %</label>
                <input name="default_commission_percent" type="number" step="0.01" value="<?= htmlspecialchars((string) $settings['default_commission_percent']) ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">Clearance days</label>
                <input name="clearance_days" type="number" value="<?= htmlspecialchars((string) $settings['clearance_days']) ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">Min payout ₹</label>
                <input name="min_payout_amount" type="number" step="0.01" value="<?= htmlspecialchars((string) $settings['min_payout_amount']) ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-slate-500">Cookie window (days)</label>
                <input name="cookie_window_days" type="number" value="<?= htmlspecialchars((string) $settings['cookie_window_days']) ?>"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <label class="flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" name="commission_on_renewals" <?= (int) $settings['commission_on_renewals'] ? 'checked' : '' ?>>
                    Pay on renewals
                </label>
            </div>
            <div class="sm:col-span-5">
                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save settings</button>
            </div>
        </form>
    </div>

    <!-- Partner list -->
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <?php if (empty($partners)): ?>
            <p class="text-sm text-slate-400">No partners found.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400">
                    <tr><th class="py-2">Name</th><th>Email</th><th>Location</th><th>Code</th><th>Override</th><th>Status</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($partners as $p): ?>
                    <tr>
                        <td class="py-2 font-medium text-slate-800"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="text-slate-600"><?= htmlspecialchars($p['email']) ?></td>
                        <td class="text-slate-500"><?= htmlspecialchars(trim(($p['city'] ?? '') . ', ' . ($p['country_code'] ?? ''), ', ')) ?></td>
                        <td class="font-mono text-xs text-slate-600"><?= htmlspecialchars($p['referral_code']) ?></td>
                        <td class="text-slate-600"><?= $p['commission_percent_override'] !== null ? number_format((float) $p['commission_percent_override'], 2) . '%' : '—' ?></td>
                        <td>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= $p['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : ($p['status'] === 'rejected' ? 'bg-red-100 text-red-700' : ($p['status'] === 'suspended' ? 'bg-slate-200 text-slate-600' : 'bg-amber-100 text-amber-700')) ?>">
                                <?= htmlspecialchars($p['status']) ?>
                            </span>
                        </td>
                        <td class="text-right"><a href="/admin/partners/<?= (int) $p['id'] ?>" class="text-emerald-600 hover:underline">Manage →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
