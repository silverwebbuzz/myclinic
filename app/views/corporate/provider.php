<?php
/**
 * /company/providers/{id} — one clinic in the network: branches served, agreed prices, end.
 *
 * @var array<string,mixed> $link   + clinic name/address/phone/email, branch_list
 * @var list<array<string,mixed>> $rates  + exam_name
 * @var list<array<string,mixed>> $examTypes
 * @var list<array<string,mixed>> $branches
 * @var string $currency
 * @var \App\Support\Locale\Locale $loc
 */
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\ProviderService;

$pageTitle = (string) $link['name'];
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$pill = ['active' => 'bg-okb text-ok', 'requested' => 'bg-wnb text-wn', 'declined' => 'bg-erb text-er', 'ended' => 'bg-ntb text-nt'];
$open = in_array($link['status'], ['active', 'requested'], true);
$selected = $link['branch_list'];
$csrfField = '<input type="hidden" name="_csrf" value="' . $e($csrf) . '">';
ob_start();
?>
<a href="/company/providers" class="text-[13px] text-tx2 hover:text-tx">← Clinics</a>
<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-[20px] font-semibold tracking-[-.015em]"><?= $e($link['name']) ?>
            <span class="ml-1 rounded-full px-2 py-0.5 align-middle text-[12px] font-medium <?= $pill[$link['status']] ?>"><?= $e(ProviderService::STATUSES[$link['status']]) ?></span></h1>
        <p class="mt-0.5 text-tx3"><?= $e($link['address'] ?? '') ?></p>
        <p class="text-[13px] text-tx2"><?= !empty($link['phone']) ? $e($link['phone']) : '' ?><?= !empty($link['email']) ? ' · ' . $e($link['email']) : '' ?></p>
    </div>
</div>

<?php if ($link['status'] === 'requested'): ?>
    <p class="mt-3 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-[13px] text-wn">Waiting for the clinic to accept (asked <?= $e($loc->dateTime((string) $link['updated_at'])) ?>). You can already add the agreed prices.</p>
<?php elseif ($link['status'] === 'declined'): ?>
    <p class="mt-3 rounded-[10px] border border-er/20 bg-erb px-4 py-3 text-[13px] text-er">The clinic declined. Talk to them first, then <a class="underline" href="/company/providers?q=<?= urlencode((string) $link['name']) ?>#find">ask again</a>.</p>
<?php elseif ($link['status'] === 'ended'): ?>
    <p class="mt-3 rounded-[10px] border border-ln bg-sf2 px-4 py-3 text-[13px] text-tx2">Ended by <?= $e($link['ended_by'] === 'clinic' ? 'the clinic' : ($link['ended_by'] === 'admin' ? 'eClinicPro' : 'you')) ?><?= !empty($link['ended_reason']) ? ': ' . $e($link['ended_reason']) : '' ?>.
        <a class="underline" href="/company/providers?q=<?= urlencode((string) $link['name']) ?>#find">Ask again</a></p>
<?php elseif ((int) $link['corporate_enabled'] !== 1): ?>
    <p class="mt-3 rounded-[10px] border border-wn/20 bg-wnb px-4 py-3 text-[13px] text-wn">This clinic is paused for corporate work by eClinicPro. New bookings are not possible for now.</p>
<?php endif; ?>

<div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
    <section class="rounded-[10px] border border-ln bg-sf p-5" x-data="{ scope: '<?= $selected === null ? 'all' : 'some' ?>' }">
        <h2 class="font-semibold">Branches served</h2>
        <?php if ($open): ?>
            <form method="post" action="/company/providers/<?= (int) $link['id'] ?>/branches" class="mt-2 grid gap-2 text-[13px]">
                <?= $csrfField ?>
                <label class="inline-flex items-center gap-2"><input type="radio" name="scope" value="all" x-model="scope"> All branches</label>
                <label class="inline-flex items-center gap-2"><input type="radio" name="scope" value="some" x-model="scope"> Only these:</label>
                <div x-show="scope === 'some'" class="grid gap-1 pl-6">
                    <?php foreach ($branches as $b): ?>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="branch_ids[]" value="<?= (int) $b['id'] ?>" <?= $selected !== null && in_array((int) $b['id'], $selected, true) ? 'checked' : '' ?>>
                            <?= $e($b['name']) ?> <span class="text-tx3"><?= $e($b['city'] ?? '') ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div><button class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Save branches</button></div>
            </form>
        <?php else: ?>
            <p class="mt-2 text-[13px] text-tx3">Not serving any branch.</p>
        <?php endif; ?>

        <?php if ($open): ?>
            <div class="mt-6 border-t border-ln2 pt-4" x-data="{ end: false }">
                <button type="button" @click="end = !end" class="text-[13px] text-er hover:underline"><?= $link['status'] === 'active' ? 'End this arrangement' : 'Withdraw the request' ?></button>
                <form x-show="end" x-cloak method="post" action="/company/providers/<?= (int) $link['id'] ?>/end" class="mt-2 grid gap-2">
                    <?= $csrfField ?>
                    <?php if ($link['status'] === 'active'): ?>
                        <input name="reason" required minlength="5" maxlength="500" placeholder="Why? (the clinic sees this)" class="<?= $input ?>">
                    <?php endif; ?>
                    <div><button class="<?= $btn ?> bg-er text-white hover:opacity-90"><?= $link['status'] === 'active' ? 'End' : 'Withdraw' ?></button></div>
                    <?php if ($link['status'] === 'active'): ?><p class="text-[12px] text-tx3">Booked appointments stay; you can't make new bookings here afterwards.</p><?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-[10px] border border-ln bg-sf" id="prices">
        <div class="px-5 pt-4">
            <h2 class="font-semibold">Agreed prices</h2>
            <p class="text-[13px] text-tx3">What you agreed to pay this clinic per check, in <?= $e($currency) ?>. A record for your spend reports: eClinicPro doesn't collect or pass on payments.</p>
        </div>
        <?php if ($rates === []): ?>
            <p class="px-5 py-6 text-[13px] text-tx3">No prices yet.</p>
        <?php else: ?>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-sf2 text-left text-[12px] text-tx3"><tr><th class="px-5 py-2 font-medium">Check</th><th class="px-3 py-2 text-right font-medium">Price</th><th class="px-3 py-2 font-medium">Valid</th><th class="px-5 py-2"></th></tr></thead>
                    <tbody class="divide-y divide-ln2">
                        <?php foreach ($rates as $r): ?>
                            <tr>
                                <td class="px-5 py-2"><?= $e($r['exam_name']) ?></td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= $e($loc->money($r['price'], (string) $r['currency_code'])) ?></td>
                                <td class="px-3 py-2 text-tx2"><?= $r['valid_from'] || $r['valid_to'] ? $e(($r['valid_from'] ? $loc->date((string) $r['valid_from']) : '…') . ' – ' . ($r['valid_to'] ? $loc->date((string) $r['valid_to']) : '…')) : '<span class="text-tx3">Open</span>' ?></td>
                                <td class="px-5 py-2 text-right">
                                    <?php if ($open): ?>
                                        <form method="post" action="/company/providers/<?= (int) $link['id'] ?>/rates/<?= (int) $r['id'] ?>/remove" onsubmit="return confirm('Remove this price?')"><?= $csrfField ?><button class="text-er hover:underline">Remove</button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($open): ?>
            <form method="post" action="/company/providers/<?= (int) $link['id'] ?>/rates" class="mt-2 grid gap-2 border-t border-ln2 p-5 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                <?= $csrfField ?>
                <label class="text-[12px] text-tx3">Check
                    <select name="exam_type_id" required class="<?= $input ?> mt-1 text-[13px] text-tx"><option value="">Choose…</option>
                        <?php foreach (ProgrammeService::KINDS as $kind => $kl): $list = array_filter($examTypes, static fn ($x) => $x['kind'] === $kind); if ($list === []) { continue; } ?>
                            <optgroup label="<?= $e($kl) ?>"><?php foreach ($list as $x): ?><option value="<?= (int) $x['id'] ?>"><?= $e($x['name']) ?></option><?php endforeach; ?></optgroup>
                        <?php endforeach; ?>
                    </select></label>
                <label class="text-[12px] text-tx3">Price (<?= $e($currency) ?>)<input name="price" required inputmode="decimal" placeholder="450" class="<?= $input ?> mt-1 text-[13px] text-tx"></label>
                <label class="text-[12px] text-tx3">From <span class="text-tx3">(optional)</span><input type="date" name="valid_from" class="<?= $input ?> mt-1 text-[13px] text-tx"></label>
                <label class="text-[12px] text-tx3">To <span class="text-tx3">(optional)</span><input type="date" name="valid_to" class="<?= $input ?> mt-1 text-[13px] text-tx"></label>
                <button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Save</button>
            </form>
            <p class="px-5 pb-4 text-[12px] text-tx3">Saving the same check again updates its price.</p>
        <?php endif; ?>
    </section>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
