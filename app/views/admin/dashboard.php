<?php
/**
 * Super admin dashboard (same console arrangement as the seller dashboard:
 * title row, KPI tiles, MRR bars + plan donut, needs-attention queue).
 *
 * @var array{mrr: float, arr: float, clinics: int, at_risk: int, by_plan: array<string,int>, mrr_trend: list<array{month:string,mrr:float}>} $metrics
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
/** ₹4.82 Cr / ₹38.42 L / ₹9,400 */
$money = static function (float $r): string {
    if ($r >= 1e7) {
        return '₹' . number_format($r / 1e7, 2) . ' Cr';
    }
    if ($r >= 1e5) {
        return '₹' . number_format($r / 1e5, 2) . ' L';
    }

    return '₹' . number_format($r, 0);
};
$panel = 'rounded-[10px] border border-[#e4e7ec] bg-white';
$tone = ['ok' => 'bg-[#ecfdf5] text-[#047857]', 'wn' => 'bg-[#fffbeb] text-[#b45309]', 'er' => 'bg-[#fef2f2] text-[#b91c1c]', 'in' => 'bg-[#eff6ff] text-[#1d4ed8]', 'nt' => 'bg-[#f1f5f9] text-[#475569]'];
$trend = $metrics['mrr_trend'] ?? [];
$byPlan = $metrics['by_plan'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <?php
    // Pending counts come from _nav.php (it computes them for the sidebar badges).
    $attention = array_values(array_filter([
        ($pendingClaimCount ?? 0) > 0 ? ['Doctor profile claims', 'Verify and approve or reject', (int) $pendingClaimCount, 'wn', '!', '/admin/claims'] : null,
        ($vendorsPending ?? 0) > 0 ? ['Store sellers to review', 'New seller applications', (int) $vendorsPending, 'wn', '▣', '/admin/store/vendors?status=pending_review'] : null,
        ($productsPending ?? 0) > 0 ? ['Store products to review', 'Listings waiting for approval', (int) $productsPending, 'in', '◷', '/admin/store/products'] : null,
        (int) ($metrics['at_risk'] ?? 0) > 0 ? ['Clinics at churn risk', 'Run the churn scan to send outreach', (int) $metrics['at_risk'], 'er', '↘', '/admin/clinics'] : null,
    ]));
    ?>
    <main class="mx-auto max-w-6xl p-6">
    <div class="flex flex-col gap-4">
        <!-- Title row -->
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="flex items-center gap-1.5 text-xs font-medium text-[#047857]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#047857]"></span><?= $e(date('l, j M Y')) ?>
                </div>
                <h1 class="mt-1">Platform overview</h1>
                <p class="mt-1 text-[13px] text-[#475569]">Revenue, clinics and churn at a glance.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="/admin/clinics" class="inline-flex h-8 items-center whitespace-nowrap rounded-[7px] border border-[#e4e7ec] bg-white px-3 text-[13px] font-medium text-[#0f172a] hover:bg-[#f8fafc]">Clinics</a>
                <form method="post" action="/admin/churn/run">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf ?? '') ?>">
                    <button type="submit" class="inline-flex h-8 items-center whitespace-nowrap rounded-[7px] bg-[#059669] px-3 text-[13px] font-medium text-white hover:opacity-90">Run churn scan + outreach</button>
                </form>
            </div>
        </div>

        <?php if (!empty($_GET['message'])): ?>
            <div class="rounded-[10px] border border-[#047857]/20 bg-[#ecfdf5] px-4 py-3 text-[#047857]"><?= $e($_GET['message']) ?></div>
        <?php endif; ?>

        <!-- KPI tiles -->
        <?php
        $kpis = [
            ['MRR (paid only)', $money((float) ($metrics['mrr'] ?? 0)), ['Monthly recurring revenue', 'text-[#64748b]'], '/admin/payments'],
            ['ARR (paid only)', $money((float) ($metrics['arr'] ?? 0)), ['MRR × 12', 'text-[#64748b]'], '/admin/payments'],
            ['Clinics', number_format((int) ($metrics['clinics'] ?? 0)), [count($byPlan) . ' plan' . (count($byPlan) === 1 ? '' : 's') . ' in use', 'text-[#64748b]'], '/admin/clinics'],
            ['At churn risk', number_format((int) ($metrics['at_risk'] ?? 0)), (int) ($metrics['at_risk'] ?? 0) > 0 ? ['Needs outreach', 'text-[#b45309]'] : ['All healthy', 'text-[#047857]'], '/admin/clinics'],
        ];
        ?>
        <div class="grid grid-cols-2 gap-2.5 xl:grid-cols-4">
            <?php foreach ($kpis as [$label, $value, $sub, $href]): ?>
                <a href="<?= $e($href) ?>" class="<?= $panel ?> block px-3.5 py-3 hover:border-[#059669]/40">
                    <div class="text-xs text-[#475569]"><?= $e($label) ?></div>
                    <div class="mt-1 text-[19px] font-semibold tracking-[-.02em] <?= $label === 'At churn risk' && (int) ($metrics['at_risk'] ?? 0) > 0 ? 'text-[#b45309]' : '' ?>"><?= $e($value) ?></div>
                    <div class="mt-0.5 truncate text-xs <?= $sub[1] ?>"><?= $e($sub[0]) ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php
        $max = max(1, ...array_map(static fn ($r) => (float) $r['mrr'], $trend ?: [['mrr' => 0]]));
        $planTotal = array_sum($byPlan);
        $palette = ['#047857', '#1d4ed8', '#6d28d9', '#b45309', '#94a3b8', '#0e7490'];
        $stops = [];
        $acc = 0.0;
        $i = 0;
        foreach ($byPlan as $n) {
            $from = $acc;
            $acc += $planTotal > 0 ? $n / $planTotal * 100 : 0;
            $stops[] = $palette[$i++ % count($palette)] . " {$from}% {$acc}%";
        }
        ?>
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(0,1fr)_340px]">
            <!-- MRR trend -->
            <section class="<?= $panel ?> flex flex-col gap-3 px-[18px] py-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold">MRR trend</div>
                        <div class="text-xs text-[#64748b]">Paid subscriptions, by month</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[19px] font-semibold tracking-[-.02em]"><?= $e($money((float) ($metrics['mrr'] ?? 0))) ?></div>
                        <div class="text-xs text-[#64748b]">this month</div>
                    </div>
                </div>
                <?php if (!$trend): ?>
                    <p class="py-10 text-center text-[#64748b]">No paid revenue yet.</p>
                <?php else: ?>
                    <div class="flex h-40 items-end gap-1.5 border-b border-[#eef0f3] pt-2" role="img" aria-label="MRR by month">
                        <?php foreach ($trend as $p): $v = (float) $p['mrr']; $h = $v > 0 ? max(4, (int) round($v / $max * 100)) : 0; ?>
                            <div class="group relative flex h-full flex-1 items-end" title="<?= $e($p['month'] . ': ' . $money($v)) ?>">
                                <div class="w-full rounded-t-[3px] <?= $v > 0 ? 'bg-[#059669]/80 group-hover:bg-[#059669]' : 'bg-[#eef0f3]' ?>" style="height: <?= $v > 0 ? $h : 2 ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex gap-1.5 text-[10.5px] text-[#64748b]">
                        <?php foreach ($trend as $p): ?>
                            <?php $ts = strtotime($p['month'] . (strlen((string) $p['month']) === 7 ? '-01' : '')); ?>
                            <div class="flex-1 truncate text-center"><?= $e($ts ? date('M y', $ts) : $p['month']) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Clinics by plan -->
            <section class="<?= $panel ?> flex flex-col gap-3.5 px-[18px] py-4">
                <div>
                    <div class="text-sm font-semibold">Clinics by plan</div>
                    <div class="text-xs text-[#64748b]"><?= number_format($planTotal) ?> clinic<?= $planTotal === 1 ? '' : 's' ?></div>
                </div>
                <div class="flex items-center gap-[18px]">
                    <div class="grid h-32 w-32 flex-none place-items-center rounded-full" role="img" aria-label="Clinics by plan"
                         style="background: <?= $planTotal === 0 ? '#f8fafc' : 'conic-gradient(' . implode(', ', $stops) . ')' ?>">
                        <div class="grid h-[94px] w-[94px] place-items-center rounded-full bg-white text-center">
                            <div>
                                <div class="text-[21px] font-semibold tracking-[-.02em]"><?= number_format((int) ($metrics['clinics'] ?? 0)) ?></div>
                                <div class="text-[11px] text-[#64748b]">clinics</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2 text-[13px]">
                        <?php $i = 0; foreach ($byPlan as $plan => $n): ?>
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-1.5"><span class="h-2.5 w-2.5 flex-none rounded-[3px]" style="background: <?= $palette[$i++ % count($palette)] ?>"></span><span class="truncate"><?= $e($plan) ?></span></span>
                                <b><?= number_format((int) $n) ?></b>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$byPlan): ?><span class="text-[#64748b]">No clinics yet.</span><?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <!-- Needs attention -->
        <section class="<?= $panel ?> flex flex-col overflow-hidden lg:max-w-[480px]">
            <div class="flex items-center justify-between border-b border-[#eef0f3] px-4 py-3">
                <span class="text-sm font-semibold">Needs attention</span>
                <span class="text-xs text-[#64748b]">Your to-do queue</span>
            </div>
            <?php if (!$attention): ?>
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="grid h-7 w-7 flex-none place-items-center rounded-md text-xs <?= $tone['ok'] ?>">✓</span>
                    <span class="text-[13px] font-medium">Nothing needs your attention</span>
                </div>
            <?php endif; ?>
            <?php foreach ($attention as [$label, $sub, $value, $t, $icon, $href]): ?>
                <a href="<?= $e($href) ?>" class="flex items-center gap-3 border-b border-[#eef0f3] px-4 py-2.5 last:border-b-0 hover:bg-[#f8fafc]">
                    <span class="grid h-7 w-7 flex-none place-items-center rounded-md text-xs <?= $tone[$t] ?>"><?= $e($icon) ?></span>
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="text-[13px] font-medium"><?= $e($label) ?></span>
                        <span class="truncate text-xs text-[#64748b]"><?= $e($sub) ?></span>
                    </span>
                    <span class="text-[15px] font-semibold"><?= (int) $value ?></span>
                </a>
            <?php endforeach; ?>
        </section>
    </div>
    </main>
</body>
</html>
