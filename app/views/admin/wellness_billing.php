<?php
/** /admin/wellness/billing — free posts, packs, GST, orders, revenue (Wellness Hub Phase 4). */
use App\Services\Wellness\BillingAdminService as BA;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rs = static fn ($v) => '₹' . number_format((float) $v, 2);
$rows = array_pad($settings['packs'], 4, ['qty' => '', 'price' => '']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing &amp; credits · Wellness Hub · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <h1 class="text-xl font-semibold">Wellness Hub — billing &amp; credits</h1>
        <p class="text-sm text-slate-500">Free health camps are always free. Other types: free posts each month, then 1 credit per post. A post uses its slot / credit only when you approve it — rejecting gives it back.</p>
        <?php if (!$enabled): ?>
            <div class="mt-3 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">Run <code>2026_11_30_wellness_hub_phase4.sql</code> first — until then nothing is charged.</div>
        <?php endif; ?>
        <?php if ($message === 'saved'): ?><div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Prices saved. They apply to new purchases and posts from now on.</div><?php endif; ?>
        <?php if ($error): ?><div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($error) ?></div><?php endif; ?>

        <div class="mt-4 grid gap-4 sm:grid-cols-4">
            <div class="ui-card p-4"><div class="text-xs text-slate-500">This month (incl. GST)</div><div class="mt-1 text-xl font-semibold"><?= $h($rs($revenue['month_total'])) ?></div><div class="text-xs text-slate-500"><?= (int) $revenue['month_n'] ?> payments</div></div>
            <div class="ui-card p-4"><div class="text-xs text-slate-500">This month — taxable / GST</div><div class="mt-1 text-sm font-semibold"><?= $h($rs($revenue['month_base'])) ?> / <?= $h($rs($revenue['month_gst'])) ?></div></div>
            <div class="ui-card p-4"><div class="text-xs text-slate-500">All time (incl. GST)</div><div class="mt-1 text-xl font-semibold"><?= $h($rs($revenue['all_total'])) ?></div></div>
            <div class="ui-card p-4"><div class="text-xs text-slate-500">Unused credits held by creators</div><div class="mt-1 text-xl font-semibold"><?= (int) $revenue['credits_out'] ?></div></div>
        </div>

        <form method="post" action="/admin/wellness/billing/settings" class="mt-4 grid gap-4 lg:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <section class="ui-card p-5">
                <h2 class="font-semibold">Free posts per month (each type)</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <?php foreach (BA::TYPES as $t => $l): ?>
                        <label class="block text-xs text-slate-600"><?= $h($l) ?>
                            <input type="number" name="free[<?= $h($t) ?>]" min="0" max="1000" value="<?= (int) ($settings['free'][$t] ?? 5) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm"></label>
                    <?php endforeach; ?>
                    <div class="text-xs text-slate-500">Free camps<div class="mt-2 text-sm font-semibold text-slate-700">Unlimited</div></div>
                </div>
                <label class="mt-4 block text-xs text-slate-600">GST %
                    <input type="number" name="gst_pct" min="0" max="28" step="0.01" value="<?= $h($settings['gst_pct']) ?>" class="mt-1 w-28 rounded border px-2 py-1.5 text-sm"></label>
            </section>
            <section class="ui-card p-5">
                <h2 class="font-semibold">Credit packs (price before GST)</h2>
                <div class="mt-3 space-y-2">
                    <?php foreach ($rows as $p): ?>
                        <div class="flex items-center gap-2 text-sm">
                            <input type="number" name="pack_qty[]" min="1" max="1000" value="<?= $h($p['qty']) ?>" placeholder="posts" class="w-24 rounded border px-2 py-1.5">
                            <span class="text-slate-500">posts for ₹</span>
                            <input type="number" name="pack_price[]" min="1" step="0.01" value="<?= $h($p['price']) ?>" placeholder="price" class="w-28 rounded border px-2 py-1.5">
                            <?php if ($p['qty'] !== ''): ?><span class="text-xs text-slate-500">₹<?= $h(number_format((float) $p['price'] * (1 + (float) $settings['gst_pct'] / 100), 2)) ?> incl. GST · ₹<?= $h(number_format((float) $p['price'] / max(1, (int) $p['qty']), 2)) ?>/post</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2 text-xs text-slate-500">Leave a row empty to remove it. Up to 4 packs.</p>
                <button class="mt-4 rounded bg-slate-800 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-900">Save prices</button>
            </section>
        </form>

        <section class="mt-4 ui-card p-5">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-semibold">Payments</h2>
                <span class="flex-1"></span>
                <?php foreach (['paid' => 'Paid', 'pending' => 'Pending', 'failed' => 'Failed', 'all' => 'All'] as $k => $l): ?>
                    <a href="/admin/wellness/billing?status=<?= $k ?>" class="rounded-full px-3 py-1 text-xs font-semibold <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-white ring-1 ring-slate-200' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="px-3 py-2">Date</th><th class="px-3 py-2">Creator</th><th class="px-3 py-2">Pack</th><th class="px-3 py-2 text-right">Taxable</th><th class="px-3 py-2 text-right">GST</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Invoice</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php if (!$orders): ?><tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">Nothing here.</td></tr><?php endif; ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="px-3 py-2 text-xs"><?= $h(date('d M Y, h:i A', strtotime((string) ($o['paid_at'] ?: $o['created_at'])))) ?></td>
                                <td class="px-3 py-2"><a class="text-sky-700 hover:underline" href="/admin/wellness/creators/<?= (int) $o['creator_id'] ?>"><?= $h($o['creator_name'] ?? '#' . $o['creator_id']) ?></a>
                                    <div class="text-xs text-slate-500"><?= $h($o['buyer_name'] ?? '') ?><?= $o['buyer_gstin'] ? ' · ' . $h($o['buyer_gstin']) : '' ?></div></td>
                                <td class="px-3 py-2"><?= (int) $o['pack_qty'] ?></td>
                                <td class="px-3 py-2 text-right"><?= $h($rs($o['base_amount'])) ?></td>
                                <td class="px-3 py-2 text-right"><?= $h($rs($o['gst_amount'])) ?></td>
                                <td class="px-3 py-2 text-right font-medium"><?= $h($rs($o['total_amount'])) ?></td>
                                <td class="px-3 py-2 text-xs"><?= $h(ucfirst((string) $o['status'])) ?></td>
                                <td class="px-3 py-2 text-xs"><?php if ($o['invoice_no']): ?><a class="text-sky-700 underline" target="_blank" rel="noopener" href="/admin/wellness/orders/<?= (int) $o['id'] ?>/invoice"><?= $h($o['invoice_no']) ?></a><?php else: ?>—<?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
