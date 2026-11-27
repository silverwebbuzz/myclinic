<?php
/**
 * /creator/questions — questions & comments on the creator's posts (Phase 5).
 *
 * @var array<string,mixed> $creator
 * @var list<array<string,mixed>> $rows
 * @var string $filter unanswered|all
 * @var int $unanswered
 * @var bool $enabled
 */
use App\Services\Wellness\CreatorService as CS;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pageTitle = 'Questions & comments';
ob_start();
?>
<div class="flex flex-wrap items-center gap-2">
    <h1 class="text-[17px] font-semibold">Questions &amp; comments</h1>
    <span class="flex-1"></span>
    <a href="/creator/questions" class="rounded-full px-3 py-1 text-[12.5px] font-medium <?= $filter === 'unanswered' ? 'bg-tx text-white' : 'bg-sf ring-1 ring-ln' ?>">Unanswered questions<?= $unanswered ? ' · ' . (int) $unanswered : '' ?></a>
    <a href="/creator/questions?filter=all" class="rounded-full px-3 py-1 text-[12.5px] font-medium <?= $filter === 'all' ? 'bg-tx text-white' : 'bg-sf ring-1 ring-ln' ?>">Everything</a>
</div>
<p class="mt-1 max-w-3xl text-[12.5px] text-tx3">Your replies show as <strong>“Creator’s answer”</strong> on the page, and the person who asked gets an email. Keep answers general — for personal symptoms, suggest they see a doctor. Replies with links or phone numbers are checked by our team before they show.</p>

<?php if (!$enabled): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf p-6 text-center text-[13px] text-tx3">Questions open soon.</div>
<?php elseif (!$rows): ?>
    <div class="mt-4 rounded-[10px] border border-ln bg-sf p-6 text-center text-[13px] text-tx3"><?= $filter === 'unanswered' ? 'All caught up — no questions waiting.' : 'No comments on your posts yet.' ?></div>
<?php else: ?>
    <div class="mt-4 space-y-3">
        <?php foreach ($rows as $r): $answered = (bool) array_filter($r['replies'], static fn ($x) => (int) $x['is_creator_answer'] === 1); ?>
            <section class="rounded-[10px] border border-ln bg-sf p-4" x-data="{ open: <?= $filter === 'unanswered' ? 'true' : 'false' ?> }">
                <div class="flex flex-wrap items-center gap-2 text-[12px] text-tx3">
                    <span class="rounded-full px-2 py-0.5 font-medium <?= $r['kind'] === 'question' ? 'bg-inb text-in' : 'bg-ntb text-nt' ?>"><?= $r['kind'] === 'question' ? 'Question' : 'Comment' ?></span>
                    <?php if ($r['item']): ?>on <a href="<?= $e(CS::siteUrl($r['item']['path'] . '#c-' . (int) $r['id'])) ?>" target="_blank" rel="noopener" class="font-medium text-tx underline"><?= $e($r['item']['title']) ?></a><?php endif; ?>
                    <span>· <?= $e(date('d M Y, h:i A', strtotime((string) $r['created_at']))) ?></span>
                    <?php if ($r['status'] === 'held'): ?><span class="rounded bg-wnb px-1.5 text-wn">waiting for our check</span><?php endif; ?>
                </div>
                <p class="mt-2 text-[13.5px]"><strong><?= $e($r['author']['name']) ?>:</strong> <?= nl2br($e($r['body'])) ?></p>

                <?php foreach ($r['replies'] as $x): ?>
                    <div class="ml-4 mt-2 rounded-[8px] <?= (int) $x['is_creator_answer'] === 1 ? 'border-l-2 border-ac bg-acs' : 'bg-sf2' ?> px-3 py-2 text-[13px]">
                        <strong><?= $e($x['author']['name']) ?></strong><?= (int) $x['is_creator_answer'] === 1 ? ' <span class="text-[11px] text-act">· your answer</span>' : '' ?><?= $x['status'] === 'held' ? ' <span class="text-[11px] text-wn">· waiting for our check</span>' : '' ?>
                        <div class="mt-0.5 text-tx2"><?= nl2br($e($x['body'])) ?></div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-[12.5px]">
                    <?php if ($r['status'] === 'visible'): ?>
                        <button type="button" class="font-medium text-ac underline" @click="open = !open" x-text="open ? 'Close' : '<?= $answered ? 'Reply again' : 'Answer' ?>'"></button>
                    <?php endif; ?>
                    <form method="post" action="/creator/questions/<?= (int) $r['id'] ?>/hide" onsubmit="return confirm('Hide this from your post?');">
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="filter" value="<?= $e($filter) ?>">
                        <button class="text-tx3 underline">Hide from my post</button>
                    </form>
                </div>
                <?php if ($r['status'] === 'visible'): ?>
                    <form method="post" action="/creator/questions/<?= (int) $r['id'] ?>/reply" class="mt-2 space-y-2" x-show="open" x-cloak>
                        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="filter" value="<?= $e($filter) ?>">
                        <textarea name="body" rows="3" maxlength="2000" required class="w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-[13px] outline-none focus:border-ac" placeholder="Your answer…"></textarea>
                        <button class="h-8 rounded-[7px] bg-ac px-3 text-[12.5px] font-semibold text-white hover:bg-act">Post answer</button>
                    </form>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
