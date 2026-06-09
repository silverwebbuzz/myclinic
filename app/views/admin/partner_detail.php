<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Partner — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-5xl p-6">
    <?php if (!empty($message)): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <a href="/admin/partners" class="text-sm text-slate-500 hover:underline">← All partners</a>

    <div class="mt-3 flex items-start justify-between">
        <div>
            <h1 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($partner['name']) ?></h1>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($partner['email']) ?> · <?= htmlspecialchars((string) ($partner['phone'] ?? '—')) ?></p>
            <p class="text-xs text-slate-400">Code <span class="font-mono"><?= htmlspecialchars($partner['referral_code']) ?></span> · effective rate <?= number_format($effectivePercent, 2) ?>%</p>
        </div>
        <div class="flex gap-2">
            <?php if ($partner['status'] !== 'active'): ?>
            <form method="post" action="/admin/partners/<?= (int) $partner['id'] ?>/approve">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">Approve</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/admin/partners/<?= (int) $partner['id'] ?>/status">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach (['pending', 'active', 'suspended', 'rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= $partner['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Earnings -->
    <div class="mt-5 grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Lifetime</div><div class="mt-1 text-lg font-semibold">₹<?= number_format($summary['lifetime'], 2) ?></div></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Available</div><div class="mt-1 text-lg font-semibold text-emerald-600">₹<?= number_format($summary['available'], 2) ?></div></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Pending</div><div class="mt-1 text-lg font-semibold text-amber-600">₹<?= number_format($summary['pending'], 2) ?></div></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4"><div class="text-xs text-slate-500">Paid</div><div class="mt-1 text-lg font-semibold">₹<?= number_format($summary['paid'], 2) ?></div></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <!-- Commission override -->
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Commission override</h2>
            <p class="mt-1 text-xs text-slate-500">Blank = use global default (<?= number_format($defaultPercent, 2) ?>%).</p>
            <form method="post" action="/admin/partners/<?= (int) $partner['id'] ?>/override" class="mt-3 flex gap-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input name="commission_percent_override" type="number" step="0.01" placeholder="e.g. 15"
                       value="<?= $partner['commission_percent_override'] !== null ? htmlspecialchars((string) $partner['commission_percent_override']) : '' ?>"
                       class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save</button>
            </form>
        </div>

        <!-- Payout details -->
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Payout details</h2>
            <dl class="mt-2 space-y-1 text-sm text-slate-600">
                <div><dt class="inline text-slate-400">Method:</dt> <dd class="inline"><?= htmlspecialchars((string) ($partner['payout_method'] ?? '—')) ?></dd></div>
                <div><dt class="inline text-slate-400">UPI:</dt> <dd class="inline"><?= htmlspecialchars((string) ($partner['upi_id'] ?? '—')) ?></dd></div>
                <div><dt class="inline text-slate-400">Bank:</dt> <dd class="inline"><?= htmlspecialchars((string) ($partner['bank_account_name'] ?? '—')) ?> / <?= htmlspecialchars((string) ($partner['bank_account_no'] ?? '—')) ?> / <?= htmlspecialchars((string) ($partner['bank_ifsc'] ?? '—')) ?></dd></div>
                <div><dt class="inline text-slate-400">PAN:</dt> <dd class="inline"><?= htmlspecialchars((string) ($partner['pan_number'] ?? '—')) ?></dd></div>
            </dl>
        </div>
    </div>

    <!-- KYC documents -->
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">KYC documents</h2>
        <?php if (empty($documents)): ?>
            <p class="mt-2 text-sm text-slate-400">No documents uploaded.</p>
        <?php else: ?>
        <div class="mt-3 divide-y divide-slate-100 text-sm">
            <?php foreach ($documents as $d): ?>
            <div class="flex items-center justify-between py-2">
                <div>
                    <a href="<?= htmlspecialchars((string) $d['file_path']) ?>" target="_blank" class="font-medium capitalize text-emerald-600 hover:underline">
                        <?= htmlspecialchars(str_replace('_', ' ', (string) $d['doc_type'])) ?>
                    </a>
                    <span class="text-xs text-slate-400">· <?= htmlspecialchars((string) ($d['original_name'] ?? '')) ?></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= $d['status'] === 'verified' ? 'bg-emerald-100 text-emerald-700' : ($d['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                        <?= htmlspecialchars($d['status']) ?>
                    </span>
                    <form method="post" action="/admin/partners/<?= (int) $partner['id'] ?>/document" class="flex gap-1">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="doc_id" value="<?= (int) $d['id'] ?>">
                        <button name="status" value="verified" class="rounded bg-emerald-600 px-2 py-1 text-[11px] text-white">Verify</button>
                        <button name="status" value="rejected" class="rounded bg-red-600 px-2 py-1 text-[11px] text-white">Reject</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Referred clinics -->
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Referred clinics (<?= count($clinics) ?>)</h2>
        <?php if (empty($clinics)): ?>
            <p class="mt-2 text-sm text-slate-400">No referrals yet.</p>
        <?php else: ?>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-slate-400"><tr><th class="py-2">Clinic</th><th>Plan</th><th>Status</th><th>Registered</th><th class="text-right">Earned</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($clinics as $c): ?>
                    <tr>
                        <td class="py-2 font-medium text-slate-800"><?= htmlspecialchars($c['clinic_name']) ?></td>
                        <td class="capitalize text-slate-600"><?= htmlspecialchars($c['plan']) ?></td>
                        <td class="text-slate-600"><?= htmlspecialchars($c['referral_status']) ?></td>
                        <td class="text-slate-500"><?= htmlspecialchars(substr((string) $c['registered_at'], 0, 10)) ?></td>
                        <td class="text-right font-medium">₹<?= number_format((float) $c['earned'], 2) ?></td>
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
