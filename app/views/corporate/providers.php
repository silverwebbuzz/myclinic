<?php
/**
 * /company/providers — the company's clinic network (design §4, C1–C3).
 *
 * @var list<array<string,mixed>> $links    + name, address, rate_count, branch_list
 * @var list<array<string,mixed>> $results  enabled clinics (+ link_status)
 * @var list<array<string,mixed>> $branches
 * @var string $q
 */
use App\Services\Corporate\ProviderService;

$pageTitle = 'Clinics';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'w-full rounded-[7px] border border-ln bg-sf px-3 py-1.5 focus:border-ac focus:ring-2 focus:ring-ac/15 focus:outline-none';
$btn = 'inline-flex h-8 items-center justify-center whitespace-nowrap rounded-[7px] px-3 text-[13px] font-medium';
$pill = ['active' => 'bg-okb text-ok', 'requested' => 'bg-wnb text-wn', 'declined' => 'bg-erb text-er', 'ended' => 'bg-ntb text-nt'];
$branchNames = array_column($branches, 'name', 'id');
$branchText = static function (?array $ids) use ($branchNames): string {
    if ($ids === null) {
        return 'All branches';
    }
    $names = array_values(array_filter(array_map(static fn ($id) => $branchNames[$id] ?? null, $ids)));

    return $names === [] ? 'No active branches' : implode(', ', $names);
};
$current = array_values(array_filter($links, static fn ($l) => in_array($l['status'], ['active', 'requested'], true)));
$past = array_values(array_filter($links, static fn ($l) => !in_array($l['status'], ['active', 'requested'], true)));
ob_start();
?>
<div>
    <h1 class="text-[20px] font-semibold tracking-[-.015em]">Clinics</h1>
    <p class="mt-0.5 text-tx3">The clinics that do your employees' health checks. Add a clinic, tell it which branches it serves, and record the prices you agreed. Payment stays between you and the clinic.</p>
</div>

<section class="mt-4 overflow-hidden rounded-[10px] border border-ln bg-sf">
    <h2 class="border-b border-ln2 px-5 py-3 font-semibold">Your network</h2>
    <?php if ($current === []): ?>
        <p class="px-5 py-8 text-center text-tx3">No clinics yet. Find one below.</p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($current as $l): ?>
                <li>
                    <a href="/company/providers/<?= (int) $l['id'] ?>" class="grid gap-x-4 gap-y-1 px-5 py-3 hover:bg-sf2 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_auto] md:items-center">
                        <div class="min-w-0">
                            <div class="font-medium"><?= $e($l['name']) ?></div>
                            <div class="truncate text-[13px] text-tx3"><?= $e($l['address'] ?? '') ?></div>
                        </div>
                        <div class="min-w-0 text-[13px] text-tx2">
                            <?= $e($branchText($l['branch_list'])) ?>
                            <span class="text-tx3"> · <?= (int) $l['rate_count'] ?> price<?= (int) $l['rate_count'] === 1 ? '' : 's' ?></span>
                        </div>
                        <div><span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $pill[$l['status']] ?>"><?= $e(ProviderService::STATUSES[$l['status']]) ?></span></div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="mt-4 rounded-[10px] border border-ln bg-sf" id="find">
    <div class="border-b border-ln2 px-5 py-3">
        <h2 class="font-semibold">Find a clinic</h2>
        <p class="text-[13px] text-tx3">Clinics on eClinicPro that take corporate health checks, in the countries where you have branches.</p>
        <form method="get" action="/company/providers#find" class="mt-2 flex gap-2">
            <input name="q" value="<?= $e($q) ?>" placeholder="Clinic name or area" class="<?= $input ?> max-w-sm">
            <button class="<?= $btn ?> border border-ln bg-sf hover:bg-sf2">Search</button>
        </form>
    </div>
    <?php if ($results === []): ?>
        <p class="px-5 py-8 text-center text-tx3"><?= $q !== '' ? 'No clinic matches "' . $e($q) . '".' : 'No clinics are available in your countries yet.' ?>
            <br><span class="text-[13px]">Want a clinic you already use? Ask them to contact eClinicPro, or write to us and we'll bring them on.</span></p>
    <?php else: ?>
        <ul class="divide-y divide-ln2">
            <?php foreach ($results as $c): ?>
                <li class="px-5 py-3" x-data="{ open: false, scope: 'all' }">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-medium"><?= $e($c['name']) ?> <span class="text-[12px] font-normal text-tx3"><?= $e($c['country_code']) ?></span></div>
                            <div class="truncate text-[13px] text-tx3"><?= $e($c['address'] ?? '') ?><?= !empty($c['phone']) ? ' · ' . $e($c['phone']) : '' ?></div>
                        </div>
                        <?php if (in_array($c['link_status'], ['active', 'requested'], true)): ?>
                            <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $pill[$c['link_status']] ?>"><?= $e(ProviderService::STATUSES[$c['link_status']]) ?></span>
                        <?php else: ?>
                            <button type="button" @click="open = !open" class="<?= $btn ?> bg-ac text-white hover:opacity-90"><?= $c['link_status'] ? 'Ask again' : 'Add to network' ?></button>
                        <?php endif; ?>
                    </div>
                    <form x-show="open" x-cloak method="post" action="/company/providers/request" class="mt-3 grid gap-3 rounded-[9px] bg-sf2 p-3 ring-1 ring-ln">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="clinic_id" value="<?= (int) $c['id'] ?>">
                        <input type="hidden" name="q" value="<?= $e($q) ?>">
                        <div class="text-[13px]">
                            <div class="font-medium">Which branches will this clinic serve?</div>
                            <label class="mt-1 mr-4 inline-flex items-center gap-2"><input type="radio" name="scope" value="all" x-model="scope"> All branches</label>
                            <label class="mt-1 inline-flex items-center gap-2"><input type="radio" name="scope" value="some" x-model="scope"> Only some</label>
                            <div x-show="scope === 'some'" class="mt-2 grid gap-1 sm:grid-cols-2">
                                <?php foreach ($branches as $b): ?>
                                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="branch_ids[]" value="<?= (int) $b['id'] ?>"> <?= $e($b['name']) ?> <span class="text-tx3"><?= $e($b['city'] ?? '') ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <label class="text-[13px]">Message to the clinic <span class="text-tx3">(optional)</span>
                            <input name="note" maxlength="500" placeholder="e.g. As discussed with Dr. Rao on the phone" class="<?= $input ?> mt-1"></label>
                        <div><button class="<?= $btn ?> bg-ac text-white hover:opacity-90">Send request</button>
                            <span class="ml-2 text-[13px] text-tx3">The clinic accepts it in their eClinicPro app.</span></div>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($past !== []): ?>
    <details class="mt-4 rounded-[10px] border border-ln bg-sf">
        <summary class="cursor-pointer px-5 py-3 font-medium">Declined and ended (<?= count($past) ?>)</summary>
        <ul class="divide-y divide-ln2 border-t border-ln2">
            <?php foreach ($past as $l): ?>
                <li class="flex flex-wrap items-center gap-3 px-5 py-2.5 text-[13px]">
                    <a href="/company/providers/<?= (int) $l['id'] ?>" class="min-w-0 flex-1 font-medium hover:underline"><?= $e($l['name']) ?></a>
                    <span class="text-tx3"><?= $e($l['ended_reason'] ?? '') ?></span>
                    <span class="rounded-full px-2 py-0.5 text-[12px] font-medium <?= $pill[$l['status']] ?>"><?= $e(ProviderService::STATUSES[$l['status']]) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </details>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
