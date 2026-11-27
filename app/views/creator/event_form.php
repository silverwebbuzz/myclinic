<?php
/**
 * /creator/events/new?kind= and /creator/events/{id} — create / edit / status of one camp or event.
 *
 * @var array<string,mixed> $creator
 * @var array<string,mixed> $form
 * @var array<string,mixed>|null $existing
 * @var list<array{slug:string,name:string}> $categories
 * @var list<array{name:string,slug:string,state:string}> $cities
 */
use App\Services\Wellness\EventService as ES;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isCamp = $form['kind'] === 'free_camp';
$status = (string) ($existing['status'] ?? 'draft');
$id = $existing ? (int) $existing['id'] : null;
$pageTitle = $id ? ($existing['title'] ?? 'Listing') : ($isCamp ? 'New free camp' : 'New event');
$editable = !$existing || in_array($status, ES::EDITABLE, true);
$ended = $existing && strtotime((string) $existing['end_at']) < time();
$isAdmin = false;
ob_start();
?>
<div class="max-w-3xl">
    <a href="/creator/events" class="text-[12.5px] text-tx3 hover:underline">← Camps &amp; events</a>
    <div class="mt-1 flex flex-wrap items-center gap-2">
        <h1 class="text-[17px] font-semibold"><?= $id ? $e($existing['title']) : ($isCamp ? 'New free health camp' : 'New event') ?></h1>
        <?php if ($id): ?>
            <span class="rounded-full bg-sf2 px-2 py-0.5 text-[11.5px] font-medium text-tx2 ring-1 ring-ln"><?= $e(ES::STATUS_LABELS[$status] ?? $status) ?><?= $ended && $status === 'published' ? ' · ended' : '' ?></span>
        <?php endif; ?>
    </div>

    <?php if ($id): ?>
        <div class="mt-3 space-y-2">
            <?php if ($status === 'changes_requested' && $existing['review_note']): ?>
                <p class="rounded-[8px] bg-erb px-3 py-2.5 text-[13px] text-er"><strong>Changes needed:</strong> <?= $e($existing['review_note']) ?></p>
            <?php elseif ($status === 'rejected'): ?>
                <p class="rounded-[8px] bg-erb px-3 py-2.5 text-[13px] text-er"><strong>Not accepted:</strong> <?= $e($existing['review_note'] ?? '') ?></p>
            <?php elseif ($status === 'submitted'): ?>
                <p class="rounded-[8px] bg-wnb px-3 py-2.5 text-[13px] text-wn">Waiting for review — we’ll email you. Need to change something? Take it back first.</p>
            <?php elseif ($status === 'published'): ?>
                <p class="rounded-[8px] bg-okb px-3 py-2.5 text-[13px] text-ok">Live at <a class="font-semibold underline" target="_blank" rel="noopener" href="<?= $e(ES::publicUrl($existing)) ?>"><?= $e(preg_replace('#^https?://#', '', ES::publicUrl($existing))) ?></a>. Editing sends it back to review and hides it until we approve.</p>
            <?php elseif ($status === 'cancelled'): ?>
                <p class="rounded-[8px] bg-ntb px-3 py-2.5 text-[13px] text-nt">Cancelled: <?= $e($existing['cancel_reason'] ?? '') ?></p>
            <?php elseif ($status === 'unpublished'): ?>
                <p class="rounded-[8px] bg-ntb px-3 py-2.5 text-[13px] text-nt">Hidden from the public<?= $existing['review_note'] ? ': ' . $e($existing['review_note']) : '' ?>. Edit and send for review to bring it back.</p>
            <?php endif; ?>
            <?php if (!empty($existing['poster_path'])): ?>
                <img src="/creator/events/<?= $id ?>/poster" alt="Poster" class="max-h-56 rounded-[8px] border border-ln">
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($editable): ?>
        <form method="post" action="<?= $id ? '/creator/events/' . $id : '/creator/events' ?>" enctype="multipart/form-data" class="mt-4">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <?php require __DIR__ . '/_event_fields.php'; ?>
            <?php if ($form['kind'] === 'event' && \App\Services\Wellness\Quota::enabled()): $qs = \App\Services\Wellness\Quota::status((int) $creator['id'], ['event']); $ql = (int) ($qs['types']['event']['limit'] ?? 0); $qu = (int) ($qs['types']['event']['used'] ?? 0); ?>
                <p class="mt-4 text-[12px] text-tx3">Events this month: <?= min($qu, $ql) ?> of <?= $ql ?> free used · <?= (int) $qs['balance'] ?> credit<?= (int) $qs['balance'] === 1 ? '' : 's' ?>. <?= $qu >= $ql ? 'Sending this uses 1 credit. ' : '' ?>Edits to a post that was already approved are free. <a href="/creator/credits" class="underline">Credits</a></p>
            <?php endif; ?>
            <div class="mt-4 flex flex-wrap gap-2">
                <button name="intent" value="submit" class="h-9 rounded-[8px] bg-ac px-4 text-[13px] font-semibold text-white hover:bg-act"><?= $status === 'published' ? 'Send changes for review' : 'Send for review' ?></button>
                <?php if ($status !== 'published'): ?>
                    <button name="intent" value="draft" formnovalidate class="h-9 rounded-[8px] border border-ln bg-sf px-4 text-[13px] font-medium hover:bg-sf2">Save draft</button>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($id): ?>
        <div class="mt-6 flex flex-wrap gap-3 border-t border-ln pt-4">
            <?php if ($status === 'submitted'): ?>
                <form method="post" action="/creator/events/<?= $id ?>/withdraw"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="h-8 rounded-[7px] border border-ln bg-sf px-3 text-[12.5px] font-medium hover:bg-sf2">Take back to edit</button></form>
            <?php endif; ?>
            <?php if ($status === 'published' && !$ended): ?>
                <form method="post" action="/creator/events/<?= $id ?>/cancel" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Mark this as cancelled? Visitors will see it won’t happen.');">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input name="cancel_reason" maxlength="300" required placeholder="Why / new date (shown to visitors)" class="h-8 w-72 rounded-[7px] border border-ln px-2 text-[12.5px]">
                    <button class="h-8 rounded-[7px] border border-er/30 bg-sf px-3 text-[12.5px] font-medium text-er hover:bg-erb">Cancel this <?= $isCamp ? 'camp' : 'event' ?></button>
                </form>
            <?php endif; ?>
            <?php if (in_array($status, ['draft', 'rejected', 'changes_requested'], true) && empty($existing['published_at'])): ?>
                <form method="post" action="/creator/events/<?= $id ?>/delete" onsubmit="return confirm('Delete this draft?');"><input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <button class="h-8 rounded-[7px] px-3 text-[12.5px] text-er underline">Delete</button></form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
