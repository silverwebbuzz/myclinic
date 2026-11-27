<?php
/**
 * /creator/events — the creator's camps & events.
 *
 * @var array<string,mixed> $creator
 * @var list<array<string,mixed>> $events
 * @var list<string> $approved
 */
use App\Services\Wellness\EventService as ES;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'Camps & events';
$pill = [
    'draft' => 'bg-ntb text-nt', 'submitted' => 'bg-wnb text-wn', 'changes_requested' => 'bg-erb text-er', 'rejected' => 'bg-erb text-er',
    'published' => 'bg-okb text-ok', 'unpublished' => 'bg-ntb text-nt', 'cancelled' => 'bg-ntb text-nt',
];
$canCamp = in_array('free_camp', $approved, true);
$canEvent = in_array('event', $approved, true);
ob_start();
?>
<div class="flex flex-wrap items-center gap-2">
    <h1 class="text-[17px] font-semibold">Camps &amp; events</h1>
    <span class="flex-1"></span>
    <?php if ($canCamp): ?><a href="/creator/events/new?kind=free_camp" class="inline-flex h-8 items-center rounded-[7px] bg-ac px-3 text-[12.5px] font-semibold text-white hover:bg-act">+ Free camp</a><?php endif; ?>
    <?php if ($canEvent): ?><a href="/creator/events/new?kind=event" class="inline-flex h-8 items-center rounded-[7px] border border-ln bg-sf px-3 text-[12.5px] font-semibold hover:bg-sf2">+ Event</a><?php endif; ?>
</div>
<?php if (!$canCamp && !$canEvent): ?>
    <p class="mt-3 rounded-[10px] border border-ln bg-sf px-4 py-3 text-[13px] text-tx2">To list free health camps or events, <a href="/creator/permissions" class="font-semibold text-ac underline">apply in “What I can post”</a>. Listing free camps is always free.</p>
<?php endif; ?>

<?php if (!$events): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf p-6 text-center text-[13px] text-tx3">No camps or events yet.</div>
<?php else: ?>
    <div class="mt-4 overflow-x-auto rounded-[10px] border border-ln bg-sf">
        <table class="w-full min-w-[680px] text-[13px]">
            <thead class="bg-sf2 text-left text-[11.5px] uppercase tracking-wide text-tx3">
                <tr><th class="px-4 py-2.5">Title</th><th class="px-4 py-2.5">When</th><th class="px-4 py-2.5">Where</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Views</th></tr>
            </thead>
            <tbody class="divide-y divide-ln2">
                <?php foreach ($events as $ev): $ended = strtotime((string) $ev['end_at']) < time(); ?>
                    <tr class="hover:bg-sf2">
                        <td class="px-4 py-3">
                            <a href="/creator/events/<?= (int) $ev['id'] ?>" class="font-medium text-tx hover:underline"><?= $e($ev['title']) ?></a>
                            <div class="text-[11.5px] text-tx3"><?= $e(ES::KINDS[$ev['kind']]) ?></div>
                        </td>
                        <td class="px-4 py-3 text-tx2"><?= $e(date('d M Y', strtotime((string) $ev['start_at']))) ?><div class="text-[11.5px] text-tx3"><?= $e(date('h:i A', strtotime((string) $ev['start_at']))) ?></div></td>
                        <td class="px-4 py-3 text-tx2"><?= $e($ev['mode'] === 'online' ? 'Online' : ($ev['city_name'] ?? '—')) ?></td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11.5px] font-medium <?= $pill[$ev['status']] ?? '' ?>"><?= $e(ES::STATUS_LABELS[$ev['status']] ?? $ev['status']) ?></span>
                            <?php if ($ended && $ev['status'] === 'published'): ?><span class="ml-1 text-[11.5px] text-tx3">· ended</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-tx2"><?= (int) $ev['view_count'] ?><?php if ((int) $ev['contact_views'] > 0): ?><div class="text-[11.5px] text-tx3"><?= (int) $ev['contact_views'] ?> saw contact</div><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
