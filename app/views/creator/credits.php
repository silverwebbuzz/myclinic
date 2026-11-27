<?php
/**
 * /creator/credits — free posts this month, credit balance, buy packs,
 * payment history (invoices) and the credit ledger. (Wellness Hub Phase 4.)
 *
 * @var array<string,mixed> $creator
 * @var array{period:string,balance:int,types:array<string,array{limit:?int,used:int}>} $quota
 * @var list<array{qty:int,price:float,gst:float,total:float,per_post:float}> $packs
 * @var list<array<string,mixed>> $orders
 * @var list<array<string,mixed>> $ledger
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'Credits & payments';
$label = ['recipe' => 'Recipes', 'exercise' => 'Exercises', 'tip' => 'Quick tips', 'video' => 'Videos', 'event' => 'Events'];
$entryLabel = ['purchase' => 'Bought', 'reserve' => 'Used', 'release' => 'Returned', 'admin_grant' => 'Added by eClinicPro', 'admin_deduct' => 'Removed by eClinicPro'];
$month = date('F Y', strtotime($quota['period'] . '-01'));
$best = $packs ? array_reduce($packs, static fn ($c, $p) => $c === null || $p['per_post'] < $c['per_post'] ? $p : $c) : null;
$in = 'mt-1 h-9 w-full rounded-[7px] border border-ln bg-sf px-3 text-[13px] outline-none focus:border-ac';
$card = 'min-w-0 rounded-[10px] border border-ln bg-sf p-4 md:p-5';
ob_start();
?>
<h1 class="text-[17px] font-semibold">Credits &amp; payments</h1>
<p class="mt-1 max-w-3xl text-[12.5px] text-tx3">Every month you get free posts for each type you’re approved for. Free health camps are always free. After the free posts, each extra post uses 1 credit. Credits never expire. A post only uses a free slot or credit when it’s approved — if we don’t accept it, you get it back.</p>

<?php if ($bought > 0): ?>
    <p class="mt-3 rounded-[10px] border border-ok/20 bg-okb px-4 py-3 text-ok"><strong>Payment received.</strong> <?= (int) $bought ?> credit<?= $bought > 1 ? 's' : '' ?> added. Your invoice is below.</p>
<?php elseif ($pending): ?>
    <p class="mt-3 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-wn">We’re waiting for the bank to confirm your payment. Credits are added automatically within a few minutes — refresh this page.</p>
<?php endif; ?>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <section class="<?= $card ?> lg:col-span-2">
        <h2 class="text-[14px] font-semibold">Free posts — <?= $e($month) ?></h2>
        <?php if (!$quota['types']): ?>
            <p class="mt-2 text-[12.5px] text-tx3">Once you’re approved for recipes, exercises, tips, videos or events, your monthly free posts show here. <a href="/creator/permissions" class="text-ac underline">See what I can post</a>.</p>
        <?php else: ?>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <?php foreach ($quota['types'] as $t => $q): $lim = (int) $q['limit']; $left = max(0, $lim - $q['used']); ?>
                    <div class="rounded-[8px] bg-sf2 p-3">
                        <div class="flex items-center justify-between text-[13px]">
                            <span class="font-medium"><?= $e($label[$t] ?? $t) ?></span>
                            <span class="<?= $left === 0 ? 'text-wn' : 'text-tx2' ?>"><?= (int) $q['used'] ?> of <?= $lim ?> used</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ln"><div class="h-full rounded-full <?= $left === 0 ? 'bg-wn' : 'bg-ac' ?>" style="width: <?= $lim > 0 ? min(100, (int) round($q['used'] / $lim * 100)) : 100 ?>%"></div></div>
                        <div class="mt-1 text-[11.5px] text-tx3"><?= $left > 0 ? $left . ' free left' : 'Next posts use credits' ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-[11.5px] text-tx3">Free posts reset on the 1st of every month and don’t carry over.</p>
        <?php endif; ?>
    </section>

    <section class="<?= $card ?>">
        <h2 class="text-[14px] font-semibold">Credit balance</h2>
        <div class="mt-2 text-[32px] font-semibold leading-none"><?= (int) $quota['balance'] ?></div>
        <p class="mt-1 text-[12px] text-tx3">credit<?= (int) $quota['balance'] === 1 ? '' : 's' ?> · never expire</p>
    </section>
</div>

<section class="<?= $card ?> mt-4" x-data="{ pack: <?= $best ? (int) array_search($best, $packs, true) : 0 ?> }">
    <h2 class="text-[14px] font-semibold">Buy credits</h2>
    <form method="post" action="/creator/credits/buy" class="mt-3">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <div class="grid gap-2 sm:grid-cols-3">
            <?php foreach ($packs as $i => $p): ?>
                <label class="relative flex cursor-pointer flex-col rounded-[10px] border border-ln p-4 hover:bg-sf2" :class="pack === <?= $i ?> ? '!border-ac bg-acs' : ''">
                    <input type="radio" name="pack" value="<?= $i ?>" x-model.number="pack" class="sr-only">
                    <?php if ($best && $p === $best && count($packs) > 1): ?><span class="absolute right-3 top-3 rounded-full bg-ac px-2 py-0.5 text-[10.5px] font-semibold text-white">Best value</span><?php endif; ?>
                    <span class="text-[15px] font-semibold"><?= (int) $p['qty'] ?> post<?= $p['qty'] > 1 ? 's' : '' ?></span>
                    <span class="mt-1 text-[20px] font-semibold">₹<?= $e(number_format($p['price'], $p['price'] == floor($p['price']) ? 0 : 2)) ?> <span class="text-[12px] font-normal text-tx3">+ GST</span></span>
                    <span class="text-[11.5px] text-tx3">₹<?= $e(number_format($p['per_post'], 2)) ?> per post · total ₹<?= $e(number_format($p['total'], 2)) ?> incl. GST</span>
                </label>
            <?php endforeach; ?>
        </div>
        <details class="mt-3 text-[12.5px]">
            <summary class="cursor-pointer text-tx2">Invoice details (optional — for a GST invoice in your business name)</summary>
            <div class="mt-2 grid gap-3 sm:grid-cols-3">
                <label class="block font-medium text-tx2">Name on invoice
                    <input name="buyer_name" maxlength="160" value="<?= $e($creator['org_name'] ?: $creator['display_name']) ?>" class="<?= $in ?>"></label>
                <label class="block font-medium text-tx2">GSTIN (optional)
                    <input name="buyer_gstin" maxlength="15" value="<?= $e($creator['gstin'] ?? '') ?>" class="<?= $in ?> uppercase" placeholder="24ABCDE1234F1Z5"></label>
                <label class="block font-medium text-tx2">State
                    <input name="buyer_state" maxlength="80" value="<?= $e($creator['state'] ?? '') ?>" class="<?= $in ?>" placeholder="Gujarat"></label>
            </div>
        </details>
        <button class="mt-4 h-10 rounded-[8px] bg-ac px-5 text-[14px] font-semibold text-white hover:bg-act">Pay securely with Razorpay</button>
        <p class="mt-2 text-[11.5px] text-tx3">UPI, cards, net banking. Used credits are not refundable; unused credits stay in your account forever.</p>
    </form>
</section>

<div class="mt-4 grid gap-4 lg:grid-cols-2">
    <section class="<?= $card ?>">
        <h2 class="text-[14px] font-semibold">Payment history</h2>
        <?php if (!$orders): ?>
            <p class="mt-2 text-[12.5px] text-tx3">No payments yet.</p>
        <?php else: ?>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full min-w-[420px] text-[12.5px]">
                    <thead class="text-left text-[11px] uppercase tracking-wide text-tx3"><tr><th class="py-2">Date</th><th>Pack</th><th class="text-right">Total</th><th>Status</th><th></th></tr></thead>
                    <tbody class="divide-y divide-ln2">
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td class="py-2"><?= $e(date('d M Y', strtotime((string) ($o['paid_at'] ?: $o['created_at'])))) ?></td>
                                <td><?= (int) $o['pack_qty'] ?> credit<?= (int) $o['pack_qty'] > 1 ? 's' : '' ?></td>
                                <td class="text-right">₹<?= $e(number_format((float) $o['total_amount'], 2)) ?></td>
                                <td><?= $o['status'] === 'paid' ? '<span class="text-ok">Paid</span>' : ($o['status'] === 'failed' ? '<span class="text-er">Failed</span>' : '<span class="text-wn">Pending</span>') ?></td>
                                <td class="text-right"><?php if ($o['status'] === 'paid' && $o['invoice_no']): ?><a href="/creator/credits/invoice/<?= (int) $o['id'] ?>" target="_blank" rel="noopener" class="text-ac underline"><?= $e($o['invoice_no']) ?></a><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="<?= $card ?>">
        <h2 class="text-[14px] font-semibold">Credit history</h2>
        <?php if (!$ledger): ?>
            <p class="mt-2 text-[12.5px] text-tx3">Nothing yet.</p>
        <?php else: ?>
            <ul class="mt-2 divide-y divide-ln2 text-[12.5px]">
                <?php foreach ($ledger as $l): ?>
                    <li class="flex items-center justify-between gap-3 py-2">
                        <span><span class="font-medium"><?= $e($entryLabel[$l['entry']] ?? $l['entry']) ?></span>
                            <span class="text-tx3">· <?= $e(date('d M Y, h:i A', strtotime((string) $l['created_at']))) ?><?= $l['note'] ? ' · ' . $e($l['note']) : '' ?></span></span>
                        <span class="whitespace-nowrap font-medium <?= (int) $l['qty'] >= 0 ? 'text-ok' : 'text-tx2' ?>"><?= (int) $l['qty'] > 0 ? '+' : '' ?><?= (int) $l['qty'] ?> <span class="text-tx3">→ <?= (int) $l['balance_after'] ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
