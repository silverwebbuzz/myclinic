<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Referrals — eClinicPro Partners</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-5xl p-6">
    <h1 class="text-lg font-semibold text-slate-900">My referred clinics</h1>
    <p class="mt-1 text-sm text-slate-500">Every clinic that signed up using your code or link.</p>

    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-5">
        <?php if (empty($clinics)): ?>
            <p class="text-sm text-slate-500">No referrals yet. Share your link:
                <span class="font-mono text-emerald-600"><?= htmlspecialchars($referralUrl) ?></span>
            </p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400">
                    <tr>
                        <th class="py-2">Clinic</th><th>Plan</th><th>Subscription</th>
                        <th>Source</th><th>Status</th><th>Registered</th><th class="text-right">Earned</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($clinics as $c): ?>
                    <?php
                        $expiry = $c['plan_expires_at'] ?? null;
                        $sub = $expiry ? ('Active till ' . substr((string) $expiry, 0, 10)) : ($c['trial_ends_at'] ? 'Trial' : '—');
                    ?>
                    <tr>
                        <td class="py-2 font-medium text-slate-800"><?= htmlspecialchars($c['clinic_name']) ?></td>
                        <td class="capitalize text-slate-600"><?= htmlspecialchars($c['plan']) ?></td>
                        <td class="text-slate-600"><?= htmlspecialchars($sub) ?></td>
                        <td class="text-slate-500 uppercase text-xs"><?= htmlspecialchars($c['attributed_via']) ?></td>
                        <td>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= $c['referral_status'] === 'converted' ? 'bg-emerald-100 text-emerald-700' : ($c['referral_status'] === 'churned' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                                <?= htmlspecialchars($c['referral_status']) ?>
                            </span>
                        </td>
                        <td class="text-slate-500"><?= htmlspecialchars(substr((string) $c['registered_at'], 0, 10)) ?></td>
                        <td class="text-right font-medium text-slate-800">₹<?= number_format((float) $c['earned'], 2) ?></td>
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
