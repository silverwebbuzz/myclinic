<?php
/**
 * /admin/store/payouts/{id}
 *
 * @var array<string,mixed> $payout
 * @var array<string,mixed>|null $vendor
 * @var array<string,mixed>|null $bank
 * @var list<array<string,mixed>> $entries
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$st = (string) $payout['status'];
$pid = (int) $payout['id'];
$bankChanged = $bank !== null && ($bank['account_last4'] !== $payout['bank_last4'] || $bank['ifsc'] !== $payout['bank_ifsc']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($payout['payout_no']) ?> — Payout</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-4xl space-y-5 p-6">
    <a href="/admin/store/payouts" class="text-sm text-sky-700 hover:underline">← Payouts</a>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-mono text-xl font-semibold"><?= $e($payout['payout_no']) ?></h1>
            <p class="text-sm text-slate-500"><?= $e($vendor['display_name'] ?? '') ?> · <?= $e($st) ?> · <?= $e($payout['period_from']) ?> → <?= $e($payout['period_to']) ?></p>
        </div>
        <a href="/admin/store/payouts/<?= $pid ?>/statement" class="rounded border bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Download statement (CSV)</a>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <?php if (!empty($request)): ?>
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            <strong>Requested by the seller</strong> on <?= $e(substr((string) $request['requested_at'], 0, 16)) ?>.
            <?php if (!empty($request['note'])): ?><br>Seller's note: “<?= $e($request['note']) ?>”<?php endif; ?>
            <?php if (!empty($request['decline_reason'])): ?><br>Declined: <?= $e($request['decline_reason']) ?><?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="grid gap-5 rounded-xl border bg-white p-5 shadow-sm sm:grid-cols-2">
        <dl class="grid grid-cols-2 gap-y-1 text-sm">
            <dt class="text-slate-400">Earnings</dt><dd class="text-right"><?= $r($payout['gross_paise']) ?></dd>
            <dt class="text-slate-400">Deductions</dt><dd class="text-right">−<?= $r($payout['deductions_paise']) ?></dd>
            <dt class="font-semibold">Transfer</dt><dd class="text-right text-lg font-semibold"><?= $r($payout['net_paise']) ?></dd>
        </dl>
        <div class="text-sm">
            <div class="text-slate-400">Pay to</div>
            <div class="font-medium"><?= $e($bank['holder_name'] ?? '—') ?></div>
            <div class="font-mono">A/c ••••<?= $e($payout['bank_last4'] ?? '') ?> · <?= $e($payout['bank_ifsc'] ?? '') ?></div>
            <?php if ($bankChanged): ?><div class="mt-1 text-xs text-red-600">The seller changed bank details after this payout was created. Verify before paying.</div><?php endif; ?>
            <?php if ($bank !== null && $bank['status'] !== 'verified'): ?><div class="mt-1 text-xs text-red-600">Bank account is <?= $e($bank['status']) ?>. Don't pay until verified.</div><?php endif; ?>
            <a href="/admin/store/vendors/<?= (int) $payout['vendor_id'] ?>" class="mt-1 inline-block text-xs text-sky-700 hover:underline">Full account number on the seller page (Reveal)</a>
        </div>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Next step</h2>
        <?php if ($st === 'draft'): ?>
            <p class="mt-1 text-sm text-slate-600">Check the entries below, then approve.</p>
            <div class="mt-3 flex flex-wrap items-start gap-2">
                <form method="post" action="/admin/store/payouts/<?= $pid ?>/approve"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="rounded bg-emerald-600 px-4 py-2 text-sm text-white">Approve</button></form>
                <?php if (!empty($request)): ?>
                    <form method="post" action="/admin/store/payouts/<?= $pid ?>/cancel" class="flex flex-wrap gap-2" onsubmit="return confirm('Decline this request? The amount goes back to the seller\'s available balance and they are emailed the reason.')">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <input name="note" required placeholder="Reason (the seller sees this)" class="w-72 rounded border px-3 py-1.5 text-sm">
                        <button class="rounded border border-red-300 px-4 py-2 text-sm text-red-700">Decline request</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="/admin/store/payouts/<?= $pid ?>/cancel" onsubmit="return confirm('Cancel this payout? Entries go back to available.')"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><button class="rounded border px-4 py-2 text-sm">Cancel</button></form>
                <?php endif; ?>
            </div>
        <?php elseif ($st === 'approved' || $st === 'processing'): ?>
            <p class="mt-1 text-sm text-slate-600">Transfer <strong><?= $r($payout['net_paise']) ?></strong> from your bank (NEFT/IMPS/UPI), then record the UTR.</p>
            <form method="post" action="/admin/store/payouts/<?= $pid ?>/paid" class="mt-3 flex flex-wrap gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="reference" required placeholder="UTR / transfer reference" class="rounded border px-3 py-1.5 text-sm">
                <button class="rounded bg-emerald-600 px-4 py-2 text-sm text-white">Mark paid</button>
            </form>
            <form method="post" action="/admin/store/payouts/<?= $pid ?>/failed" class="mt-2 flex flex-wrap gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="note" placeholder="Why it failed (the seller sees this)" class="w-72 rounded border px-3 py-1.5 text-sm">
                <button class="rounded border border-red-300 px-3 py-1.5 text-sm text-red-700">Mark failed</button>
            </form>
        <?php elseif ($st === 'paid'): ?>
            <p class="mt-1 text-sm text-emerald-700">Paid <?= $e(substr((string) $payout['paid_at'], 0, 16)) ?> · UTR <span class="font-mono"><?= $e($payout['reference']) ?></span>. The seller has been emailed.</p>
        <?php else: ?>
            <p class="mt-1 text-sm text-slate-600"><?= $e(ucfirst($st)) ?><?= !empty($payout['failure_reason']) ? ': ' . $e($payout['failure_reason']) : '' ?>. Entries are back in the seller's available balance.</p>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Entries (<?= count($entries) ?>)</h2>
        <table class="mt-3 w-full text-sm">
            <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">Date</th><th>Package</th><th>Type</th><th>Note</th><th class="text-right">Amount</th></tr></thead>
            <tbody class="divide-y">
            <?php foreach ($entries as $en): ?>
                <tr><td class="py-1.5 text-slate-500"><?= $e(substr((string) $en['created_at'], 0, 10)) ?></td>
                    <td class="font-mono text-xs"><?= $e($en['sub_order_no'] ?? '') ?></td>
                    <td><?= $e(str_replace('_', ' ', (string) $en['entry_type'])) ?></td>
                    <td class="text-slate-500"><?= $e($en['memo'] ?? '') ?></td>
                    <td class="text-right <?= (int) $en['amount_paise'] < 0 ? 'text-red-600' : '' ?>"><?= $r($en['amount_paise']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
