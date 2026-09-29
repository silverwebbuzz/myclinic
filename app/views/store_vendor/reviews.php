<?php
/** @var list<array<string,mixed>> $rows */
$pageTitle = 'Reviews';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<h1 class="text-[22px] font-semibold tracking-[-.015em]">Reviews</h1>
<p class="mt-1 text-sm text-tx3">Published reviews from verified buyers. A short, polite public reply builds trust. Never share customer details or make medical claims.</p>
<div class="mt-4 space-y-3">
    <?php if (!$rows): ?><p class="rounded-[10px] border border-ln bg-sf p-10 text-center text-sm text-tx3">No published reviews yet.</p><?php endif; ?>
    <?php foreach ($rows as $rv): ?>
        <article class="rounded-[10px] border border-ln bg-sf p-5 text-sm">
            <div class="flex flex-wrap justify-between gap-2">
                <span><span class="text-amber-500"><?= str_repeat('★', (int) $rv['rating']) ?></span><span class="text-slate-300"><?= str_repeat('★', 5 - (int) $rv['rating']) ?></span>
                    <strong class="ml-1"><?= $e($rv['title'] ?? '') ?></strong></span>
                <span class="text-xs text-tx3"><?= $e($rv['product_name']) ?> · <?= $e(substr((string) $rv['created_at'], 0, 10)) ?></span>
            </div>
            <?php if (!empty($rv['body'])): ?><p class="mt-2 whitespace-pre-line text-tx2"><?= $e($rv['body']) ?></p><?php endif; ?>
            <form method="post" action="/vendor/reviews/<?= (int) $rv['id'] ?>/reply" class="mt-3 flex gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="reply" maxlength="1000" value="<?= $e($rv['vendor_reply'] ?? '') ?>" placeholder="Public reply (optional)" class="flex-1 rounded-[7px] border border-ln bg-sf px-3 py-2">
                <button class="rounded-[7px] bg-ac px-4 py-1.5 text-[13px] font-medium text-white hover:opacity-90"><?= empty($rv['vendor_reply']) ? 'Reply' : 'Update' ?></button>
            </form>
        </article>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
