<?php
/**
 * /creator/content — the creator's posts. Phase 1: Health Tips posts (written in
 * the existing editor on eclinicpro.com/my-health-tips). Camps, events and videos
 * join this list in later phases.
 *
 * @var array<string,mixed> $creator
 * @var list<array<string,mixed>> $tips
 * @var list<string> $approved
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'My content';
$typeLabel = ['recipe' => 'Recipe', 'exercise' => 'Exercise', 'tip' => 'Quick tip', 'video' => 'Video'];
$status = [
    'draft' => ['Draft', 'bg-ntb text-nt'],
    'submitted' => ['In review', 'bg-wnb text-wn'],
    'changes_requested' => ['Changes needed', 'bg-erb text-er'],
    'rejected' => ['Not accepted', 'bg-erb text-er'],
    'published' => ['Live', 'bg-okb text-ok'],
    'unpublished' => ['Unpublished', 'bg-ntb text-nt'],
];
$tipReady = array_values(array_intersect(CS::TIP_TYPES, $approved));
$fmt = static fn (?string $d): string => $d ? date('d M Y', strtotime($d)) : '—';
ob_start();
?>
<div class="flex flex-wrap items-center gap-2">
    <h1 class="text-[17px] font-semibold">My content</h1>
    <span class="flex-1"></span>
    <?php foreach ($tipReady as $t): ?>
        <a href="<?= $e(CS::siteUrl('/my-health-tips?new=' . $t)) ?>" class="inline-flex h-8 items-center rounded-[7px] bg-ac px-3 text-[12.5px] font-semibold text-white hover:bg-act">+ <?= $e($typeLabel[$t]) ?></a>
    <?php endforeach; ?>
</div>

<?php if (!$tipReady && !$tips): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf p-6 text-center text-[13px] text-tx3">
        Nothing here yet. Once you’re approved for recipes, exercises or quick tips, you can write them from here.
        <div class="mt-3"><a href="/creator/permissions" class="font-semibold text-ac underline">See what I can post</a></div>
    </div>
<?php elseif (!$tips): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf p-6 text-center text-[13px] text-tx3">No posts yet. Start with your favourite one!</div>
<?php else: ?>
    <div class="mt-4 overflow-x-auto rounded-[10px] border border-ln bg-sf">
        <table class="w-full min-w-[640px] text-[13px]">
            <thead class="bg-sf2 text-left text-[11.5px] uppercase tracking-wide text-tx3">
                <tr><th class="px-4 py-2.5">Title</th><th class="px-4 py-2.5">Type</th><th class="px-4 py-2.5">Status</th><th class="px-4 py-2.5">Views</th><th class="px-4 py-2.5">Updated</th><th class="px-4 py-2.5"></th></tr>
            </thead>
            <tbody class="divide-y divide-ln2">
                <?php foreach ($tips as $t): [$sl, $sc] = $status[$t['status']] ?? [$t['status'], 'bg-ntb text-nt']; ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= $e($t['title']) ?></div>
                            <?php if ($t['status'] === 'changes_requested' && $t['review_note']): ?>
                                <div class="mt-0.5 text-[12px] text-er">Our note: <?= $e($t['review_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-tx2"><?= $e($typeLabel[$t['type']] ?? $t['type']) ?></td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11.5px] font-medium <?= $sc ?>"><?= $e($sl) ?></span></td>
                        <td class="px-4 py-3 text-tx2"><?= (int) $t['view_count'] ?></td>
                        <td class="px-4 py-3 text-tx2"><?= $e($fmt($t['updated_at'])) ?></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <?php if ($t['status'] === 'published'): ?>
                                <a href="<?= $e(CS::siteUrl('/health-tips/' . $t['slug'])) ?>" target="_blank" rel="noopener" class="text-[12.5px] text-ac underline">View ↗</a>
                            <?php endif; ?>
                            <a href="<?= $e(CS::siteUrl('/my-health-tips?edit=' . (int) $t['id'])) ?>" class="ml-3 text-[12.5px] text-tx2 underline">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<p class="mt-3 text-[12px] text-tx3">Camps, events and videos will appear here when they open.</p>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
