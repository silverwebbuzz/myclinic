<?php
/**
 * /admin/store/disputes — sellers' disputes of deductions (courier, weight, returns, failed delivery).
 *
 * @var list<array<string,mixed>> $rows
 * @var string $status
 * @var bool $ready
 */
use App\Services\Store\ProductService;
use App\Support\IndianDate;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
$badge = ['open' => 'bg-amber-100 text-amber-800', 'accepted' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-slate-200 text-slate-600'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Charge disputes — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div>
        <h1 class="text-xl font-semibold">Charge disputes</h1>
        <p class="text-sm text-slate-500">Sellers can dispute a deduction within <?= (int) \App\Services\Store\ChargeDisputeService::windowDays() ?> days. While a dispute is open, that charge is left out of payouts.
            <strong>Accept</strong> reverses the charge (credited in the next payout); <strong>Reject</strong> keeps it. Your reply is emailed to the seller, so explain it with the evidence.</p>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>
    <?php if (!$ready): ?>
        <div class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Import <code>app/database/patches/2026_10_08_store_seller_trust.sql</code> to enable disputes.</div>
    <?php endif; ?>
    <nav class="flex flex-wrap gap-2 text-sm">
        <?php foreach (['open' => 'Open', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label): ?>
            <a href="/admin/store/disputes?status=<?= $e($k) ?>" class="rounded-full px-3 py-1 <?= $status === $k ? 'bg-slate-800 text-white' : 'bg-white text-slate-700 hover:bg-slate-50' ?>"><?= $e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$rows): ?>
        <p class="rounded-xl border bg-white p-6 text-sm text-slate-500">No disputes here.</p>
    <?php endif; ?>
    <?php foreach ($rows as $d): ?>
        <?php $late = $d['status'] === 'open' && strtotime((string) $d['respond_by']) < time(); ?>
        <section class="rounded-xl border bg-white p-5 text-sm shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="font-semibold"><?= $e($d['display_name']) ?> · <a class="text-sky-700 hover:underline" href="/admin/store/orders/<?= (int) $d['order_id'] ?>"><?= $e($d['sub_order_no']) ?></a></div>
                    <div class="text-slate-600"><?= $e($d['memo']) ?> · <strong><?= $r($d['amount_paise']) ?></strong></div>
                </div>
                <div class="text-right">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[$d['status']] ?? '' ?>"><?= $e(ucfirst((string) $d['status'])) ?></span>
                    <div class="mt-1 text-xs <?= $late ? 'font-semibold text-red-700' : 'text-slate-400' ?>">
                        Raised <?= $e(IndianDate::date($d['created_at'])) ?> · reply by <?= $e(IndianDate::date($d['respond_by'])) ?><?= $late ? ' (overdue)' : '' ?></div>
                </div>
            </div>
            <p class="mt-2 rounded bg-slate-50 px-3 py-2 text-slate-700"><span class="text-xs text-slate-400">Seller says:</span><br><?= nl2br($e($d['reason'])) ?></p>
            <?php if ($d['status'] === 'open'): ?>
                <form method="post" class="mt-3 space-y-2">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <textarea name="note" required maxlength="1000" rows="2" placeholder="Reply to the seller (emailed). e.g. Shiprocket weight report shows 1.5 kg; your parcel photo shows 1.4 kg." class="w-full rounded border px-2 py-1.5"></textarea>
                    <div class="flex flex-wrap gap-2">
                        <button formaction="/admin/store/disputes/<?= (int) $d['id'] ?>/accept" onclick="return confirm('Accept and reverse this charge?')" class="rounded bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700">Accept: reverse the charge</button>
                        <button formaction="/admin/store/disputes/<?= (int) $d['id'] ?>/reject" onclick="return confirm('Reject this dispute? The charge stays.')" class="rounded border px-3 py-1.5 hover:bg-slate-50">Reject: keep the charge</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="mt-2 text-slate-600"><span class="text-xs text-slate-400">Our reply (<?= $e(IndianDate::date($d['resolved_at'])) ?>):</span><br><?= nl2br($e($d['resolution_note'])) ?></p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</main>
</body>
</html>
