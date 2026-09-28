<?php
/** @var list<array<string,mixed>> $rows */
$pageTitle = 'Reviews';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<h1 class="text-2xl font-semibold">Reviews</h1>
<p class="mt-1 text-sm text-slate-500">Published reviews from verified buyers. A short, polite public reply builds trust. Never share customer details or make medical claims.</p>
<div class="mt-4 space-y-3">
    <?php if (!$rows): ?><p class="rounded-2xl border border-[#ece8df] bg-white p-10 text-center text-sm text-slate-500">No published reviews yet.</p><?php endif; ?>
    <?php foreach ($rows as $rv): ?>
        <article class="rounded-2xl border border-[#ece8df] bg-white p-5 text-sm">
            <div class="flex flex-wrap justify-between gap-2">
                <span><span class="text-amber-500"><?= str_repeat('★', (int) $rv['rating']) ?></span><span class="text-slate-300"><?= str_repeat('★', 5 - (int) $rv['rating']) ?></span>
                    <strong class="ml-1"><?= $e($rv['title'] ?? '') ?></strong></span>
                <span class="text-xs text-slate-500"><?= $e($rv['product_name']) ?> · <?= $e(substr((string) $rv['created_at'], 0, 10)) ?></span>
            </div>
            <?php if (!empty($rv['body'])): ?><p class="mt-2 whitespace-pre-line text-slate-700"><?= $e($rv['body']) ?></p><?php endif; ?>
            <form method="post" action="/vendor/reviews/<?= (int) $rv['id'] ?>/reply" class="mt-3 flex gap-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input name="reply" maxlength="1000" value="<?= $e($rv['vendor_reply'] ?? '') ?>" placeholder="Public reply (optional)" class="flex-1 rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-full bg-[#0e4d34] px-4 py-2 text-white hover:bg-[#17774f]"><?= empty($rv['vendor_reply']) ? 'Reply' : 'Update' ?></button>
            </form>
        </article>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
