<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Partner Payouts — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl p-6">
    <?php if (!empty($message)): ?>
        <p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold text-slate-900">Partner payout requests</h1>
        <div class="flex gap-2 text-sm">
            <?php foreach (['' => 'All', 'requested' => 'Requested', 'processing' => 'Processing', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $val => $label): ?>
                <a href="/admin/partner-payouts<?= $val ? '?status=' . $val : '' ?>"
                   class="rounded-lg px-3 py-1 <?= ($filterStatus ?? '') === $val ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 border border-slate-200' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-4 space-y-3">
        <?php if (empty($requests)): ?>
            <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-400">No payout requests.</div>
        <?php else: foreach ($requests as $r): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="font-semibold text-slate-900">₹<?= number_format((float) $r['amount'], 2) ?>
                        <span class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase <?= $r['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : ($r['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>"><?= htmlspecialchars($r['status']) ?></span>
                    </div>
                    <div class="mt-1 text-sm text-slate-600"><?= htmlspecialchars($r['partner_name']) ?> · <?= htmlspecialchars($r['partner_email']) ?></div>
                    <div class="mt-1 text-xs text-slate-400">
                        Requested <?= htmlspecialchars(substr((string) $r['requested_at'], 0, 16)) ?> ·
                        Method: <?= htmlspecialchars((string) ($r['payout_method'] ?? '—')) ?> ·
                        UPI: <?= htmlspecialchars((string) ($r['upi_id'] ?? '—')) ?> ·
                        Bank: <?= htmlspecialchars((string) ($r['bank_account_no'] ?? '—')) ?> / <?= htmlspecialchars((string) ($r['bank_ifsc'] ?? '—')) ?>
                    </div>
                    <?php if (!empty($r['payment_reference'])): ?>
                        <div class="mt-1 text-xs text-slate-500">Ref: <?= htmlspecialchars((string) $r['payment_reference']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if (in_array($r['status'], ['requested', 'processing'], true)): ?>
                <form method="post" action="/admin/partner-payouts/<?= (int) $r['id'] ?>/process" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input name="payment_reference" placeholder="Payment ref / UTR" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input name="admin_note" placeholder="Note (optional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button name="status" value="processing" class="rounded-lg bg-slate-700 px-3 py-2 text-sm text-white">Processing</button>
                    <button name="status" value="paid" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white">Mark paid</button>
                    <button name="status" value="rejected" class="rounded-lg bg-red-600 px-3 py-2 text-sm text-white">Reject</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</main>
</body>
</html>
