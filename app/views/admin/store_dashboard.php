<?php
/**
 * /admin/store/dashboard
 *
 * @var array<string,int> $kpi
 * @var list<array{0:string,1:int,2:string}> $todo
 * @var list<array<string,mixed>> $days
 * @var list<array<string,mixed>> $topSellers
 */
use App\Services\Store\ProductService;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$r = static fn ($p): string => '₹' . ProductService::rupees((int) $p);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store dashboard — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">eClinicPro Store</h1>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ([
            ['Today', $r($kpi['today_gmv']), $kpi['today_orders'] . ' paid order(s)'],
            ['This month', $r($kpi['month_gmv']), $kpi['month_orders'] . ' paid order(s)'],
            ['Commission (month)', $r($kpi['month_commission']), 'booked on delivery'],
            ['Refunds (month)', $r($kpi['month_refunds']), 'cancellations + returns'],
        ] as [$label, $value, $sub]): ?>
            <div class="rounded-xl border bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-500"><?= $e($label) ?></div>
                <div class="mt-1 text-2xl font-semibold"><?= $e($value) ?></div><div class="text-xs text-slate-400"><?= $e($sub) ?></div></div>
        <?php endforeach; ?>
    </div>
    <p class="text-sm text-slate-500"><?= (int) $kpi['sellers'] ?> approved sellers · <?= (int) $kpi['live_products'] ?> live products · <?= (int) $kpi['customers'] ?> paying customers</p>

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="rounded-xl border bg-white p-5 shadow-sm lg:col-span-1">
            <h2 class="font-semibold">Needs your attention</h2>
            <ul class="mt-3 space-y-1.5 text-sm">
                <?php foreach ($todo as [$label, $n, $href]): ?>
                    <li><a href="<?= $e($href) ?>" class="flex justify-between rounded px-2 py-1 hover:bg-slate-50 <?= $n > 0 ? '' : 'text-slate-400' ?>">
                        <span><?= $e($label) ?></span><span class="<?= $n > 0 ? 'rounded-full bg-amber-500 px-2 text-xs font-semibold text-white' : '' ?>"><?= (int) $n ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Last 14 days</h2>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-1">Day</th><th class="text-right">Orders</th><th class="text-right">Collected</th></tr></thead>
                <tbody class="divide-y">
                <?php if (!$days): ?><tr><td colspan="3" class="py-4 text-center text-slate-400">No paid orders yet.</td></tr><?php endif; ?>
                <?php foreach ($days as $d): ?>
                    <tr><td class="py-1"><?= $e(date('D j M', (int) strtotime((string) $d['d']))) ?></td><td class="text-right"><?= (int) $d['n'] ?></td><td class="text-right"><?= $r($d['s']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="font-semibold">Top sellers this month</h2>
            <table class="mt-3 w-full text-sm">
                <tbody class="divide-y">
                <?php if (!$topSellers): ?><tr><td class="py-4 text-center text-slate-400">No sales yet.</td></tr><?php endif; ?>
                <?php foreach ($topSellers as $t): ?>
                    <tr><td class="py-1"><?= $e($t['display_name']) ?></td><td class="text-right text-slate-500"><?= (int) $t['n'] ?> pkg</td><td class="text-right"><?= $r($t['s']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
    <p class="text-xs text-slate-500">Full monthly figures: <a href="/admin/store/reports" class="text-sky-700 hover:underline">Store reports</a>.</p>
</main>
</body>
</html>
