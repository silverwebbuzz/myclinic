<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Earnings &amp; Payouts — eClinicPro Partners</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-5xl p-6">
    <?php if (!empty($message)): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <h1 class="text-lg font-semibold text-slate-900">Earnings &amp; payouts</h1>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
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

    <!-- Payout details + request -->
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Payout details</h2>
            <form method="post" action="/partner/payout-details" class="mt-3 space-y-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div>
                    <label class="block text-xs text-slate-500">Method</label>
                    <select name="payout_method" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="upi"  <?= ($partner['payout_method'] ?? '') === 'upi' ? 'selected' : '' ?>>UPI</option>
                        <option value="bank" <?= ($partner['payout_method'] ?? '') === 'bank' ? 'selected' : '' ?>>Bank transfer</option>
                    </select>
                </div>
                <input name="upi_id" placeholder="UPI ID" value="<?= htmlspecialchars($partner['upi_id'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input name="bank_account_name" placeholder="Account holder name" value="<?= htmlspecialchars($partner['bank_account_name'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <input name="bank_account_no" placeholder="Account number" value="<?= htmlspecialchars($partner['bank_account_no'] ?? '') ?>"
                           class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="bank_ifsc" placeholder="IFSC" value="<?= htmlspecialchars($partner['bank_ifsc'] ?? '') ?>"
                           class="rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                </div>
                <input name="pan_number" placeholder="PAN (for TDS)" value="<?= htmlspecialchars($partner['pan_number'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save details</button>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Request a payout</h2>
            <p class="mt-1 text-xs text-slate-500">Minimum ₹<?= number_format($minPayout, 0) ?>. Processed within 7 days. Withdraws your full available balance.</p>
            <form method="post" action="/partner/payout-request" class="mt-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                        <?= $summary['available'] < $minPayout ? 'disabled' : '' ?>>
                    Request ₹<?= number_format($summary['available'], 2) ?>
                </button>
            </form>

            <h3 class="mt-5 text-xs font-semibold uppercase text-slate-400">Payout history</h3>
            <div class="mt-2 divide-y divide-slate-100 text-sm">
                <?php if (empty($payouts)): ?>
                    <p class="text-slate-400">No payout requests yet.</p>
                <?php else: foreach ($payouts as $p): ?>
                    <div class="flex items-center justify-between py-2">
                        <div>
                            <div class="font-medium text-slate-800">₹<?= number_format((float) $p['amount'], 2) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars(substr((string) $p['requested_at'], 0, 16)) ?></div>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= $p['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($p['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                            <?= htmlspecialchars($p['status']) ?>
                        </span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Commission ledger -->
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Commission ledger</h2>
        <?php if (empty($ledger)): ?>
            <p class="mt-2 text-sm text-slate-400">No commissions yet.</p>
        <?php else: ?>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400">
                    <tr><th class="py-2">Date</th><th>Clinic</th><th>Type</th><th>Base</th><th>Rate</th><th>Commission</th><th>Status</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($ledger as $c): ?>
                    <tr>
                        <td class="py-2 text-slate-500"><?= htmlspecialchars(substr((string) $c['earned_at'], 0, 10)) ?></td>
                        <td class="font-medium text-slate-800"><?= htmlspecialchars($c['clinic_name']) ?></td>
                        <td class="capitalize text-slate-600"><?= htmlspecialchars($c['type']) ?></td>
                        <td class="text-slate-600">₹<?= number_format((float) $c['base_amount'], 2) ?></td>
                        <td class="text-slate-600"><?= number_format((float) $c['commission_percent'], 2) ?>%</td>
                        <td class="font-medium text-slate-800">₹<?= number_format((float) $c['commission_amount'], 2) ?></td>
                        <td>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= in_array($c['status'], ['approved','paid'], true) ? 'bg-emerald-100 text-emerald-700' : ($c['status'] === 'reversed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                                <?= htmlspecialchars($c['status']) ?>
                            </span>
                        </td>
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
