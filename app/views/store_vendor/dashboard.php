<?php
/**
 * Seller dashboard (PayGate console arrangement: title row, KPI tiles, sales chart +
 * outcome donut, needs-attention queue + recent orders). Setup checklist before approval.
 *
 * @var array<string,mixed> $vendor
 * @var array{items: list<array{key:string,label:string,done:bool,href:string}>, complete: bool} $checklist
 * @var array<string,mixed>|null $stats
 */
$pageTitle = 'Dashboard';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = (string) $vendor['status'];
$doneCount = count(array_filter($checklist['items'], static fn ($i) => $i['done']));
$total = count($checklist['items']);
$firstName = explode(' ', (string) ($vendorUser['name'] ?? ''))[0] ?: 'there';

/** ₹4.82 Cr / ₹38.42 L / ₹9,400 */
$money = static function (int $paise): string {
    $r = abs($paise) / 100;
    $sign = $paise < 0 ? '−' : '';
    if ($r >= 1e7) {
        return $sign . '₹' . number_format($r / 1e7, 2) . ' Cr';
    }
    if ($r >= 1e5) {
        return $sign . '₹' . number_format($r / 1e5, 2) . ' L';
    }

    return $sign . '₹' . number_format($r, $r >= 1000 ? 0 : 2);
};
$tone = ['ok' => 'bg-okb text-ok', 'wn' => 'bg-wnb text-wn', 'er' => 'bg-erb text-er', 'in' => 'bg-inb text-in', 'rv' => 'bg-rvb text-rv', 'nt' => 'bg-ntb text-nt'];
$subTone = ['up' => 'text-ok', 'down' => 'text-er', 'warn' => 'text-wn', 'neutral' => 'text-tx3'];
$pkgStatus = [
    'new' => ['New', 'wn', '!'], 'accepted' => ['Accepted', 'in', '◷'], 'packed' => ['Packed', 'in', '▣'],
    'ready_to_ship' => ['Courier booked', 'in', '→'], 'shipped' => ['Shipped', 'rv', '→'], 'delivered' => ['Delivered', 'ok', '✓'],
    'completed' => ['Completed', 'ok', '✓'], 'cancelled_by_customer' => ['Cancelled', 'nt', '×'], 'cancelled_by_vendor' => ['Cancelled', 'nt', '×'],
    'cancelled_by_admin' => ['Cancelled', 'nt', '×'], 'auto_cancelled' => ['Auto-cancelled', 'er', '×'],
    'rto' => ['Returned to you', 'er', '↩'], 'lost_in_transit' => ['Lost in transit', 'er', '×'],
];
$panel = 'rounded-[10px] border border-ln bg-sf';
$btn = 'inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-[7px] border px-3 text-[13px] font-medium';
ob_start();
?>
<div class="flex flex-col gap-4">
    <!-- Title row -->
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <div class="flex items-center gap-1.5 text-xs font-medium text-ok">
                <span class="h-1.5 w-1.5 rounded-full bg-ok"></span><?= $e(date('l, j M Y')) ?>
            </div>
            <h1 class="mt-1 text-[22px] font-semibold tracking-[-.015em]">
                <?= $status === 'approved' ? $e($vendor['display_name']) . ' · Seller overview' : 'Hello, ' . $e($firstName) ?>
            </h1>
            <p class="mt-1 text-[13px] text-tx2">
                <?= $status === 'approved' ? 'Your orders, sales and payouts at a glance.' : 'Set up your seller account to start listing products on eClinicPro Store.' ?>
            </p>
        </div>
        <?php if ($status === 'approved'): ?>
            <div class="flex flex-wrap gap-2">
                <a href="/vendor/orders" class="<?= $btn ?> border-ln bg-sf text-tx hover:bg-sf2">Orders</a>
                <a href="/vendor/products/new" class="<?= $btn ?> border-transparent bg-ac text-white hover:opacity-90">+ Add product</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($welcome)): ?>
        <div class="rounded-[10px] border border-ok/20 bg-okb px-4 py-3 text-ok">Welcome aboard! Complete the steps below and submit your account for review. Once approved you can start listing products.</div>
    <?php endif; ?>

<?php if ($status === 'approved' && !empty($stats)): ?>
    <?php
    $kpis = [
        ['Orders to accept', number_format((int) $stats['to_accept']), $stats['to_accept'] > 0 ? ['Accept before the deadline', 'warn'] : ['All caught up', 'up'], '/vendor/orders'],
        ['In progress', number_format((int) $stats['in_progress']), $stats['packed_unbooked'] > 0 ? [$stats['packed_unbooked'] . ' packed, courier not booked', 'warn'] : ['Being packed or shipped', 'neutral'], '/vendor/orders'],
        ['Sales this month', $money((int) $stats['month_sales']), [number_format((int) $stats['month_orders']) . ' order' . ((int) $stats['month_orders'] === 1 ? '' : 's'), 'neutral'], '/vendor/orders?tab=all'],
        ['Ready for payout', $money((int) $stats['available']), [$money((int) $stats['pending']) . ' in return window', 'neutral'], '/vendor/payouts'],
        ['Open returns', number_format((int) $stats['open_returns']), $stats['open_returns'] > 0 ? ['Review within 2 days', 'warn'] : ['None open', 'up'], '/vendor/returns'],
        ['Rating', $stats['rating_count'] > 0 ? '★ ' . number_format((float) $stats['rating'], 1) : '—', [$stats['rating_count'] > 0 ? $stats['rating_count'] . ' review' . ($stats['rating_count'] === 1 ? '' : 's') : 'No reviews yet', 'neutral'], '/vendor/reviews'],
    ];
    ?>
    <!-- KPI tiles -->
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-6">
        <?php foreach ($kpis as [$label, $value, $sub, $href]): ?>
            <a href="<?= $e($href) ?>" class="<?= $panel ?> block px-3.5 py-3 hover:border-ac/40">
                <div class="text-xs text-tx2"><?= $e($label) ?></div>
                <div class="mt-1 text-[19px] font-semibold tracking-[-.02em]"><?= $e($value) ?></div>
                <div class="mt-0.5 truncate text-xs <?= $subTone[$sub[1]] ?>"><?= $e($sub[0]) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php
    // 14-day series (fill empty days).
    $series = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $series[$d] = $stats['daily'][$d] ?? ['n' => 0, 's' => 0];
    }
    $max = max(1, max(array_column($series, 's')));   // (no ...spread: $series has date-string keys)
    $sum14 = array_sum(array_column($series, 's'));
    $cnt14 = array_sum(array_column($series, 'n'));
    $mix = $stats['mix'];
    $mixTotal = array_sum($mix);
    $pct = static fn (int $n) => $mixTotal > 0 ? $n / $mixTotal * 100 : 0;
    $a = $pct($mix['delivered']);
    $b = $a + $pct($mix['in_progress']);
    ?>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_340px]">
        <!-- Sales chart -->
        <section class="<?= $panel ?> flex flex-col gap-3 px-[18px] py-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="text-sm font-semibold">Sales · last 14 days</div>
                    <div class="text-xs text-tx3">Your selling price, paid orders only</div>
                </div>
                <div class="text-right">
                    <div class="text-[19px] font-semibold tracking-[-.02em]"><?= $e($money($sum14)) ?></div>
                    <div class="text-xs text-tx3"><?= number_format($cnt14) ?> order<?= $cnt14 === 1 ? '' : 's' ?></div>
                </div>
            </div>
            <div class="flex h-40 items-end gap-1 border-b border-ln2 pt-2" role="img" aria-label="Daily sales for the last 14 days">
                <?php foreach ($series as $d => $p): $h = $p['s'] > 0 ? max(4, (int) round($p['s'] / $max * 100)) : 0; ?>
                    <div class="group relative flex h-full flex-1 items-end" title="<?= $e(date('j M', strtotime($d)) . ': ' . $money($p['s']) . ' · ' . $p['n'] . ' order(s)') ?>">
                        <div class="w-full rounded-t-[3px] <?= $p['s'] > 0 ? 'bg-ac/80 group-hover:bg-ac' : 'bg-ln2' ?>" style="height: <?= $p['s'] > 0 ? $h : 2 ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-1 text-[10.5px] text-tx3">
                <?php $k = 0; foreach ($series as $d => $p): ?>
                    <div class="flex-1 text-center"><?= $k % 2 === 0 ? $e(date('j', strtotime($d))) : '' ?></div>
                <?php $k++; endforeach; ?>
            </div>
        </section>

        <!-- Outcome donut -->
        <section class="<?= $panel ?> flex flex-col gap-3.5 px-[18px] py-4">
            <div>
                <div class="text-sm font-semibold">Packages · last 30 days</div>
                <div class="text-xs text-tx3"><?= number_format($mixTotal) ?> paid package<?= $mixTotal === 1 ? '' : 's' ?></div>
            </div>
            <div class="flex items-center gap-[18px]">
                <div class="grid h-32 w-32 flex-none place-items-center rounded-full" role="img"
                     aria-label="<?= (int) $mix['delivered'] ?> delivered, <?= (int) $mix['in_progress'] ?> in progress, <?= (int) $mix['not_delivered'] ?> not delivered"
                     style="background: <?= $mixTotal === 0 ? '#f8fafc' : "conic-gradient(#047857 0 {$a}%, #1d4ed8 {$a}% {$b}%, #b91c1c {$b}% 100%)" ?>">
                    <div class="grid h-[94px] w-[94px] place-items-center rounded-full bg-sf text-center">
                        <div>
                            <div class="text-[21px] font-semibold tracking-[-.02em]"><?= $mixTotal > 0 ? (int) round($pct($mix['delivered'])) . '%' : '—' ?></div>
                            <div class="text-[11px] text-tx3">delivered</div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-1 flex-col gap-2 text-[13px]">
                    <?php foreach ([['Delivered', $mix['delivered'], 'ok', '✓'], ['In progress', $mix['in_progress'], 'in', '◷'], ['Not delivered', $mix['not_delivered'], 'er', '×']] as [$l, $n, $t, $ic]): ?>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5"><span class="grid h-[18px] w-[18px] place-items-center rounded text-[10px] <?= $tone[$t] ?>"><?= $ic ?></span><?= $e($l) ?></span>
                            <b><?= number_format((int) $n) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 border-t border-ln2 pt-3 text-xs text-tx3">
                <div>Live products<div class="text-[15px] font-semibold text-tx"><?= number_format((int) $stats['live_products']) ?></div></div>
                <div>Low on stock<div class="text-[15px] font-semibold <?= $stats['low_stock'] ? 'text-wn' : 'text-tx' ?>"><?= count($stats['low_stock']) ?></div></div>
            </div>
        </section>
    </div>

    <?php
    $attention = array_values(array_filter([
        $stats['to_accept'] > 0 ? ['Orders waiting for you', 'Accept or cancel before the deadline', (string) $stats['to_accept'], 'wn', '!', '/vendor/orders'] : null,
        $stats['packed_unbooked'] > 0 ? ['Packed, courier not booked', 'Book pickup from the order page', (string) $stats['packed_unbooked'], 'in', '→', '/vendor/orders'] : null,
        $stats['open_returns'] > 0 ? ['Open returns', 'Approve or reject within 2 days', (string) $stats['open_returns'], 'wn', '↩', '/vendor/returns'] : null,
        $stats['low_stock'] ? ['Low stock', 'Restock so orders are not cancelled', (string) count($stats['low_stock']), 'wn', '▾', '/vendor/products'] : null,
        $stats['gst_issues'] > 0 ? ['HSN / GST needs fixing', 'Invoices depend on it', (string) $stats['gst_issues'], 'er', '%', '/vendor/products'] : null,
        \App\Services\Store\StorePolicyService::needsAcceptance((int) $vendor['id']) ? ['Accept the seller terms', 'A new version is waiting', '1', 'wn', '§', '/vendor/terms'] : null,
    ]));
    ?>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[380px_minmax(0,1fr)]">
        <!-- Needs attention -->
        <section class="<?= $panel ?> flex flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-ln2 px-4 py-3">
                <span class="text-sm font-semibold">Needs attention</span>
                <span class="text-xs text-tx3">Your to-do queue</span>
            </div>
            <?php if (!$attention): ?>
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="grid h-7 w-7 flex-none place-items-center rounded-md text-xs <?= $tone['ok'] ?>">✓</span>
                    <span class="text-[13px] font-medium">Nothing needs your attention</span>
                </div>
            <?php endif; ?>
            <?php foreach ($attention as [$label, $sub, $value, $t, $icon, $href]): ?>
                <a href="<?= $e($href) ?>" class="flex items-center gap-3 border-b border-ln2 px-4 py-2.5 last:border-b-0 hover:bg-sf2">
                    <span class="grid h-7 w-7 flex-none place-items-center rounded-md text-xs <?= $tone[$t] ?>"><?= $e($icon) ?></span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="text-[13px] font-medium"><?= $e($label) ?></span>
                        <span class="truncate text-xs text-tx3"><?= $e($sub) ?></span>
                    </span>
                    <span class="text-[15px] font-semibold"><?= $e($value) ?></span>
                </a>
            <?php endforeach; ?>
        </section>

        <!-- Recent orders -->
        <section class="<?= $panel ?> flex min-w-0 flex-col overflow-hidden">
            <div class="flex items-center justify-between border-b border-ln2 px-4 py-3">
                <span class="text-sm font-semibold">Recent orders</span>
                <a href="/vendor/orders?tab=all" class="text-xs font-medium text-act hover:underline">View all →</a>
            </div>
            <?php if (!$stats['recent']): ?>
                <p class="px-4 py-8 text-center text-tx3">No orders yet. They appear here as soon as a customer pays.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead><tr class="bg-sf2 text-left text-xs text-tx3">
                            <th class="px-4 py-2 font-medium">Package</th><th class="px-3 py-2 font-medium">Paid</th><th class="px-3 py-2 font-medium">Ship to</th>
                            <th class="px-3 py-2 text-right font-medium">Units</th><th class="px-3 py-2 text-right font-medium">Value</th><th class="px-4 py-2 font-medium">Status</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($stats['recent'] as $o): ?>
                            <?php $ship = json_decode((string) $o['ship_address_json'], true) ?: []; $ps = $pkgStatus[$o['status']] ?? [ucfirst(str_replace('_', ' ', (string) $o['status'])), 'nt', '·']; ?>
                            <tr class="border-t border-ln2 hover:bg-sf2">
                                <td class="px-4 py-2"><a href="/vendor/orders/<?= (int) $o['id'] ?>" class="font-mono text-[12.5px] font-medium text-tx hover:text-act"><?= $e($o['sub_order_no']) ?></a></td>
                                <td class="whitespace-nowrap px-3 py-2 text-tx2"><?= $e(date('d M, h:i A', (int) strtotime((string) $o['paid_at']))) ?></td>
                                <td class="px-3 py-2 text-tx2"><?= $e(trim(($ship['city'] ?? '') . ' ' . ($ship['pincode'] ?? ''))) ?></td>
                                <td class="px-3 py-2 text-right"><?= (int) $o['units'] ?></td>
                                <td class="px-3 py-2 text-right font-medium"><?= $e($money((int) $o['value_paise'])) ?></td>
                                <td class="px-4 py-2"><span class="inline-flex h-[22px] items-center gap-1.5 whitespace-nowrap rounded-md px-2 text-xs font-medium <?= $tone[$ps[1]] ?>"><span class="text-[11px]" aria-hidden="true"><?= $e($ps[2]) ?></span><?= $e($ps[0]) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <?php if ($stats['low_stock']): ?>
        <section class="<?= $panel ?> overflow-hidden">
            <div class="flex items-center justify-between border-b border-ln2 px-4 py-3">
                <span class="text-sm font-semibold">Low stock</span>
                <span class="text-xs text-tx3">Stock left after open orders</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead><tr class="bg-sf2 text-left text-xs text-tx3"><th class="px-4 py-2 font-medium">Product</th><th class="px-3 py-2 font-medium">SKU</th><th class="px-4 py-2 text-right font-medium">Left</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['low_stock'] as $ls): $left = max(0, (int) $ls['stock_qty'] - (int) $ls['reserved_qty']); ?>
                        <tr class="border-t border-ln2 hover:bg-sf2">
                            <td class="px-4 py-2"><a href="/vendor/products/<?= (int) $ls['id'] ?>" class="hover:text-act"><?= $e($ls['name']) ?><?= $ls['title'] ? ' · ' . $e($ls['title']) : '' ?></a></td>
                            <td class="px-3 py-2 font-mono text-xs text-tx3"><?= $e($ls['sku']) ?></td>
                            <td class="px-4 py-2 text-right"><span class="inline-flex h-[22px] items-center rounded-md px-2 text-xs font-medium <?= $left === 0 ? $tone['er'] : $tone['wn'] ?>"><?= $left === 0 ? 'Out of stock' : $left . ' left' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

<?php else: ?>
    <?php if ($status === 'pending_review'): ?>
        <div class="flex items-start gap-3 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-wn">
            <span class="grid h-7 w-7 flex-none place-items-center rounded-md bg-sf text-xs">◷</span>
            <div><div class="font-semibold">Your account is under review</div>
                <div class="text-[13px]">Submitted <?= $e(substr((string) ($vendor['submitted_at'] ?? ''), 0, 10)) ?>. We usually respond within 2 working days. Business and tax details are locked during review; addresses, bank and documents can still be updated.</div></div>
        </div>
    <?php endif; ?>

    <?php if (in_array($status, ['draft', 'rejected', 'pending_review'], true)): ?>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_340px]">
        <section class="<?= $panel ?> overflow-hidden">
            <div class="flex items-center justify-between border-b border-ln2 px-4 py-3">
                <span class="text-sm font-semibold">Account setup</span>
                <span class="text-xs text-tx3"><?= $doneCount ?> of <?= $total ?> done</span>
            </div>
            <div class="px-4 pt-3">
                <div class="h-1.5 overflow-hidden rounded-full bg-ln2"><div class="h-full rounded-full bg-ac" style="width: <?= $total ? (int) round($doneCount / $total * 100) : 0 ?>%"></div></div>
            </div>
            <ul class="mt-2">
                <?php foreach ($checklist['items'] as $item): ?>
                    <li class="flex items-center gap-3 border-t border-ln2 px-4 py-2.5 first:border-t-0">
                        <span class="grid h-7 w-7 flex-none place-items-center rounded-md text-xs <?= $item['done'] ? $tone['ok'] : $tone['nt'] ?>"><?= $item['done'] ? '✓' : '○' ?></span>
                        <span class="flex-1 text-[13px] <?= $item['done'] ? 'text-tx3' : 'font-medium' ?>"><?= $e($item['label']) ?></span>
                        <a href="<?= $e($item['href']) ?>" class="text-xs font-medium text-act hover:underline"><?= $item['done'] ? 'Edit' : 'Start' ?> →</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (in_array($status, ['draft', 'rejected'], true)): ?>
                <form method="post" action="/vendor/submit" class="flex flex-wrap items-center gap-3 border-t border-ln2 px-4 py-3">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button <?= $checklist['complete'] ? '' : 'disabled' ?> class="<?= $btn ?> border-transparent bg-ac text-white hover:opacity-90 disabled:pointer-events-none disabled:opacity-50">
                        <?= $status === 'rejected' ? 'Resubmit for review' : 'Submit for review' ?>
                    </button>
                    <?php if (!$checklist['complete']): ?><span class="text-xs text-tx3">Complete all steps to submit.</span><?php endif; ?>
                </form>
            <?php endif; ?>
        </section>

        <section class="<?= $panel ?> flex flex-col gap-2 px-[18px] py-4 text-[13px] text-tx2">
            <div class="text-sm font-semibold text-tx">What you can sell</div>
            <p>Health, wellness and personal-care products across 21 departments.</p>
            <p>Some categories need a licence on file first: <strong class="text-tx">FSSAI</strong> for supplements and foods, <strong class="text-tx">AYUSH</strong> for Ayurvedic products, and medical-device registration for devices such as BP monitors. Upload them under <a href="/vendor/documents" class="font-medium text-act underline">Documents</a>.</p>
            <p>Prescription and other drug-licensed products are not sold on the store.</p>
            <?php if (!empty($vendor['slug'])): ?>
                <p class="mt-1 border-t border-ln2 pt-2 text-xs text-tx3">Your store page will be <span class="font-mono text-tx2">eclinicpro.com/store/seller/<?= $e($vendor['slug']) ?></span></p>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
