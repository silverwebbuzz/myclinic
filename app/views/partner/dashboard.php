<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Partner Dashboard — eClinicPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-5xl p-6">
    <?php if (!empty($message)): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <h1 class="text-lg font-semibold text-slate-900">Welcome, <?= htmlspecialchars($partner['name']) ?></h1>

    <!-- Earnings summary -->
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Lifetime earned</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">₹<?= number_format($summary['lifetime'], 2) ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Available to withdraw</div>
            <div class="mt-1 text-xl font-semibold text-emerald-600">₹<?= number_format($summary['available'], 2) ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Pending (clearing)</div>
            <div class="mt-1 text-xl font-semibold text-amber-600">₹<?= number_format($summary['pending'], 2) ?></div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs text-slate-500">Paid out</div>
            <div class="mt-1 text-xl font-semibold text-slate-700">₹<?= number_format($summary['paid'], 2) ?></div>
        </div>
    </div>

    <!-- Referral link & code -->
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Your referral link &amp; code</h2>
        <p class="mt-1 text-xs text-slate-500">You earn <strong><?= number_format($effectivePercent, 2) ?>%</strong> on every paid subscription &amp; renewal from clinics you refer.</p>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="flex-1">
                <label class="block text-xs text-slate-500">Referral link</label>
                <div class="mt-1 flex">
                    <input id="refUrl" type="text" readonly value="<?= htmlspecialchars($referralUrl) ?>"
                           class="w-full rounded-l-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm">
                    <button onclick="copyVal('refUrl', this)" type="button"
                            class="rounded-r-lg bg-slate-800 px-3 text-sm text-white hover:bg-slate-700">Copy</button>
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-500">Code</label>
                <div class="mt-1 flex">
                    <input id="refCode" type="text" readonly value="<?= htmlspecialchars($partner['referral_code']) ?>"
                           class="w-32 rounded-l-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-mono uppercase">
                    <button onclick="copyVal('refCode', this)" type="button"
                            class="rounded-r-lg bg-slate-800 px-3 text-sm text-white hover:bg-slate-700">Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Referred clinics -->
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Your referred clinics</h2>
            <a href="/partner/referrals" class="text-xs text-emerald-600 hover:underline">View all →</a>
        </div>
        <?php if (empty($clinics)): ?>
            <p class="mt-3 text-sm text-slate-500">No referrals yet. Share your link to get started.</p>
        <?php else: ?>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400">
                    <tr><th class="py-2">Clinic</th><th>Plan</th><th>Status</th><th>Registered</th><th class="text-right">Earned</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach (array_slice($clinics, 0, 8) as $c): ?>
                    <tr>
                        <td class="py-2 font-medium text-slate-800"><?= htmlspecialchars($c['clinic_name']) ?></td>
                        <td class="capitalize text-slate-600"><?= htmlspecialchars($c['plan']) ?></td>
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

    <div class="mt-6 text-center">
        <a href="/partner/earnings" class="inline-block rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Request a payout →
        </a>
        <p class="mt-2 text-xs text-slate-400">Minimum payout ₹<?= number_format($minPayout, 0) ?>. We process within 7 days.</p>
    </div>
</main>
<script>
function copyVal(id, btn) {
    const el = document.getElementById(id);
    navigator.clipboard.writeText(el.value).then(() => {
        const t = btn.textContent; btn.textContent = 'Copied'; setTimeout(() => btn.textContent = t, 1500);
    });
}
</script>
</body>
</html>
