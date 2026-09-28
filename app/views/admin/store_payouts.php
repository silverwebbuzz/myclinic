<?php
/**
 * /admin/store/payouts
 *
 * @var list<array<string,mixed>> $balances
 * @var list<array<string,mixed>> $payouts
 * @var string $status
 * @var int $minPayout
 * @var list<string> $batchSkipped
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$badge = ['draft' => 'bg-slate-100 text-slate-700', 'approved' => 'bg-sky-100 text-sky-800', 'processing' => 'bg-sky-100 text-sky-800',
    'paid' => 'bg-emerald-100 text-emerald-800', 'failed' => 'bg-red-100 text-red-700', 'cancelled' => 'bg-slate-200 text-slate-500'];
$totalAvailable = array_sum(array_map(static fn ($b) => max(0, (int) $b['available']), $balances));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller payouts — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Seller payouts</h1>
            <p class="text-sm text-slate-500">Sellers earn only on <strong>delivered</strong> packages, and only after the return window. Payouts are bank transfers you make, then record here with the UTR.</p>
        </div>
        <form method="post" action="/admin/store/payouts/batch" onsubmit="return confirm('Create draft payouts for every seller with an available balance?')">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Create payout batch (<?= $r($totalAvailable) ?> available)</button>
        </form>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if ($batchSkipped): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900"><strong>Not included:</strong>
            <ul class="list-disc pl-5"><?php foreach ($batchSkipped as $s): ?><li><?= $e($s) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <?php if (!empty($tableMissing)): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Ledger tables not found. Import <code>2026_09_30_store_fulfilment.sql</code>.</div>
    <?php endif; ?>

    <section class="overflow-x-auto rounded-xl border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-2">Seller</th><th class="px-4 py-2 text-right">Pending (return window)</th><th class="px-4 py-2 text-right">Available</th><th class="px-4 py-2 text-right">In payout</th><th class="px-4 py-2 text-right">Paid (lifetime)</th><th class="px-4 py-2">Bank</th></tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!$balances): ?><tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No seller earnings yet. They appear once packages are delivered.</td></tr><?php endif; ?>
            <?php foreach ($balances as $b): ?>
                <tr>
                    <td class="px-4 py-2"><a href="/admin/store/vendors/<?= (int) $b['id'] ?>" class="text-sky-700 hover:underline"><?= $e($b['display_name']) ?></a>
                        <?= $b['vendor_status'] !== 'approved' ? '<span class="ml-1 text-xs text-red-600">(' . $e($b['vendor_status']) . ')</span>' : '' ?></td>
                    <td class="px-4 py-2 text-right text-slate-500"><?= $r($b['pending']) ?></td>
                    <td class="px-4 py-2 text-right font-semibold <?= (int) $b['available'] < 0 ? 'text-red-600' : '' ?>"><?= $r($b['available']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $r($b['in_payout']) ?></td>
                    <td class="px-4 py-2 text-right text-slate-500"><?= $r($b['paid']) ?></td>
                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs <?= $b['bank_status'] === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= $e($b['bank_status'] ?? 'none') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <p class="text-xs text-slate-500">Minimum payout ₹<?= $e(ProductService::rupees($minPayout)) ?>. A negative balance (refund after a payout) is netted from the next payout automatically.</p>

    <details class="rounded-xl border bg-white p-4 text-sm shadow-sm">
        <summary class="cursor-pointer font-medium">Add a manual adjustment (bonus, penalty, correction)</summary>
        <form method="post" action="/admin/store/payouts/adjust" class="mt-3 flex flex-wrap items-end gap-2">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label>Seller<select name="vendor_id" class="ml-1 rounded border px-2 py-1">
                <?php foreach ($balances as $b): ?><option value="<?= (int) $b['id'] ?>"><?= $e($b['display_name']) ?></option><?php endforeach; ?>
            </select></label>
            <label>Amount ₹<input name="amount" placeholder="250 or -250" class="ml-1 w-28 rounded border px-2 py-1"></label>
            <input name="memo" required placeholder="Reason (seller sees this)" class="flex-1 rounded border px-2 py-1">
            <button class="rounded bg-slate-800 px-3 py-1.5 text-white">Add</button>
        </form>
    </details>

    <section class="rounded-xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
            <h2 class="font-semibold">Payouts</h2>
            <nav class="flex flex-wrap gap-1 text-xs">
                <?php foreach (['' => 'All', 'draft' => 'Draft', 'approved' => 'Approved', 'paid' => 'Paid', 'failed' => 'Failed'] as $k => $l): ?>
                    <a href="?status=<?= $k ?>" class="rounded-full px-2.5 py-1 <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-slate-100' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y">
            <?php if (!$payouts): ?><tr><td class="px-4 py-6 text-center text-slate-400">No payouts yet.</td></tr><?php endif; ?>
            <?php foreach ($payouts as $p): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2"><a href="/admin/store/payouts/<?= (int) $p['id'] ?>" class="font-mono text-sky-700 hover:underline"><?= $e($p['payout_no']) ?></a></td>
                    <td class="px-4 py-2"><?= $e($p['display_name']) ?></td>
                    <td class="px-4 py-2 text-slate-500"><?= $e($p['period_from']) ?> → <?= $e($p['period_to']) ?></td>
                    <td class="px-4 py-2 text-right font-semibold"><?= $r($p['net_paise']) ?></td>
                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs <?= $badge[$p['status']] ?? '' ?>"><?= $e($p['status']) ?></span></td>
                    <td class="px-4 py-2 font-mono text-xs text-slate-500"><?= $e($p['reference'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
