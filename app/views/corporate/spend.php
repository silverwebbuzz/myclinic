<?php
/**
 * /company/reports/spend — spend on clinic checks + statements (records only).
 *
 * @var array{months: list<string>, clinics: list<array{name:string, billed: array<string,float>, unbilled: array<string,float>}>, currency: string} $summary
 * @var list<array<string,mixed>> $statements
 * @var \App\Support\Locale\Locale $loc
 */
$pageTitle = 'Spend';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 focus:border-ac focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$cur = $summary['currency'];
$lines = [];
$totBilled = 0.0;
$totUnbilled = 0.0;
foreach (array_reverse($summary['months']) as $m) {
    foreach ($summary['clinics'] as $c) {
        $b = $c['billed'][$m] ?? 0.0;
        $u = $c['unbilled'][$m] ?? 0.0;
        if ($b > 0 || $u > 0) {
            $lines[] = [$m, $c['name'], $b, $u];
            $totBilled += $b;
            $totUnbilled += $u;
        }
    }
}
$pill = ['sent' => 'bg-inb text-in', 'paid' => 'bg-okb text-ok', 'disputed' => 'bg-erb text-er'];
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]">Spend on health checks</h1>
        <p class="mt-0.5 text-tx3">What your clinics have billed you, and what's done but not billed yet, at the prices you agreed. Records only: you pay the clinics directly.</p>
    </div>
    <a href="/company/reports" class="text-[13px] text-ac hover:underline">← Compliance reports</a>
</div>

<div class="mt-4 grid gap-3 sm:grid-cols-2">
    <div class="rounded-[10px] border border-ln bg-sf px-4 py-3"><div class="text-[12px] text-tx3">Billed, last 12 months</div><div class="mt-0.5 text-[22px] font-semibold"><?= $e($loc->money($totBilled, $cur)) ?></div></div>
    <div class="rounded-[10px] border border-ln bg-sf px-4 py-3"><div class="text-[12px] text-tx3">Done, not billed yet (estimate)</div><div class="mt-0.5 text-[22px] font-semibold text-tx2"><?= $e($loc->money($totUnbilled, $cur)) ?></div></div>
</div>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-[13px]">
            <thead class="bg-sf2 text-left text-[12px] text-tx3"><tr><th class="px-4 py-2 font-medium">Month</th><th class="px-3 py-2 font-medium">Clinic</th>
                <th class="px-3 py-2 text-right font-medium">Billed</th><th class="px-4 py-2 text-right font-medium">Not billed yet</th></tr></thead>
            <tbody class="divide-y divide-ln2">
                <?php foreach ($lines as [$m, $clinic, $b, $u]): ?>
                    <tr><td class="px-4 py-2"><?= $e(date('M Y', strtotime($m . '-01'))) ?></td><td class="px-3 py-2"><?= $e($clinic) ?></td>
                        <td class="px-3 py-2 text-right tabular-nums"><?= $b > 0 ? $e($loc->money($b, $cur)) : '<span class="text-tx3">—</span>' ?></td>
                        <td class="px-4 py-2 text-right tabular-nums text-tx2"><?= $u > 0 ? $e($loc->money($u, $cur)) : '<span class="text-tx3">—</span>' ?></td></tr>
                <?php endforeach; ?>
                <?php if ($lines === []): ?><tr><td colspan="4" class="px-4 py-8 text-center text-tx3">No completed checks at your clinics in the last 12 months.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<p class="mt-2 text-[12px] text-tx3">"Not billed yet" uses the agreed prices in Clinics; checks without an agreed price count as 0.</p>

<section class="mt-6 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <h2 class="border-b border-ln2 px-5 py-3 font-semibold">Statements from clinics</h2>
    <?php if ($statements === []): ?>
        <p class="px-5 py-8 text-center text-tx3">No statements yet.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($statements as $s): ?>
                <li class="px-5 py-3" x-data="{ d: false }">
                    <div class="grid gap-x-4 gap-y-1 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto_auto] md:items-center">
                        <div class="min-w-0"><div class="font-medium"><?= $e($s['clinic_name']) ?></div><div class="font-mono text-[12px] text-tx3"><?= $e($s['statement_no']) ?></div></div>
                        <div class="text-[13px]"><?= $e(date('M Y', strtotime((string) $s['period_from']))) ?> · <?= (int) $s['lines_count'] ?> checks</div>
                        <div class="text-[13px] font-semibold tabular-nums md:text-right"><?= $e($loc->money($s['total'], (string) $s['currency_code'])) ?></div>
                        <div class="flex flex-wrap items-center gap-2 md:justify-end">
                            <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $pill[$s['status']] ?? 'bg-ntb text-nt' ?>"><?= $e(ucfirst($s['status'])) ?></span>
                            <?php if (!empty($s['pdf_path'])): ?><a target="_blank" href="/company/reports/spend/<?= (int) $s['id'] ?>/pdf" class="text-[13px] text-ac hover:underline">PDF</a><?php endif; ?>
                            <?php if ($s['status'] !== 'disputed'): ?><button type="button" @click="d = !d" class="text-[13px] text-er hover:underline">Dispute</button><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($s['status'] === 'disputed' && !empty($s['dispute_note'])): ?><p class="mt-1 text-[12px] text-er">Your note: <?= $e($s['dispute_note']) ?></p><?php endif; ?>
                    <form x-show="d" x-cloak method="post" action="/company/reports/spend/<?= (int) $s['id'] ?>/dispute" class="mt-2 flex flex-wrap gap-2">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <input name="note" required minlength="5" maxlength="500" placeholder="What's wrong? e.g. 2 checks were cancelled" class="<?= $input ?> min-w-0 flex-1">
                        <button class="<?= $btn ?> bg-er text-white hover:opacity-90">Mark disputed</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
