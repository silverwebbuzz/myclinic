<?php
/** /admin/health-tips/{id} — review one contributor post. */
use App\Services\HealthTipAdminService as HT;

$h = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = (string) $tip['status'];
$messages = [
    'published' => 'Published — it is now live on the public site.',
    'changes_requested' => 'Sent back to the contributor with your note.',
    'rejected' => 'Rejected. The contributor sees your note.',
    'unpublished' => 'Unpublished — it is no longer public.',
];
$errors = [
    'note_required' => 'Please write a note telling the contributor what to change / why.',
    'invalid_transition' => 'That action isn’t possible from the post’s current status.',
    'not_found' => 'Post not found.',
];
$statusBadge = [
    'draft' => 'bg-slate-100 text-slate-600',
    'submitted' => 'bg-sky-100 text-sky-800',
    'changes_requested' => 'bg-amber-100 text-amber-800',
    'rejected' => 'bg-rose-100 text-rose-800',
    'published' => 'bg-emerald-100 text-emerald-800',
    'unpublished' => 'bg-slate-200 text-slate-700',
];
$facts = array_filter([
    'Servings' => $tip['servings'],
    'Prep' => $tip['prep_minutes'] !== null ? $tip['prep_minutes'] . ' min' : null,
    'Cook' => $tip['cook_minutes'] !== null ? $tip['cook_minutes'] . ' min' : null,
    'Protein / serving' => $tip['protein_g'] !== null ? $tip['protein_g'] . ' g' : null,
    'Calories / serving' => $tip['calories_kcal'] !== null ? $tip['calories_kcal'] . ' kcal' : null,
    'Duration' => $tip['duration_minutes'] !== null ? $tip['duration_minutes'] . ' min' : null,
    'Difficulty' => $tip['difficulty'] ? ucfirst((string) $tip['difficulty']) : null,
    'Reps / sets' => $tip['reps_sets'],
    'Equipment' => $tip['equipment'],
    'Body area' => $tip['body_area'],
], static fn ($v) => $v !== null && $v !== '');

// Words that usually signal a medical claim — highlighted for the reviewer.
$claimWords = ['cure', 'cures', 'cured', 'heal', 'heals', 'treat', 'treats', 'treatment', 'reverse', 'reverses', 'remedy', 'prevent', 'prevents', 'diabetes', 'thyroid', 'cancer', 'pcos', 'pcod', 'bp', 'blood pressure', 'cholesterol', 'detox', 'medicine', 'dose', 'dosage', 'guaranteed', 'miracle'];
$allText = mb_strtolower(implode(' ', array_merge(
    [$tip['title'], $tip['summary'], $tip['body'], $tip['precautions']],
    $tip['steps'],
    array_column($tip['ingredients'], 'item'),
)));
$flagged = [];
foreach ($claimWords as $w) {
    if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $allText)) {
        $flagged[] = $w;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $h($tip['title']) ?> · Health Tips review</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-6xl px-6 py-6">
        <a href="/admin/health-tips?status=<?= $h($status) ?>" class="text-sm text-slate-500 hover:underline">&larr; Back to <?= $h(strtolower(HT::STATUSES[$status] ?? 'list')) ?></a>

        <?php if ($message && isset($messages[$message])): ?>
        <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800"><?= $h($messages[$message]) ?></div>
        <?php endif; ?>
        <?php if ($error && isset($errors[$error])): ?>
        <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($errors[$error]) ?></div>
        <?php endif; ?>

        <div class="mt-3 grid gap-6 lg:grid-cols-3">
            <!-- The post, as readers would see it -->
            <article class="ui-card p-6 lg:col-span-2">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700"><?= $h(HT::TYPES[$tip['type']] ?? $tip['type']) ?></span>
                    <span class="rounded-full px-2.5 py-1 font-medium <?= $statusBadge[$status] ?? '' ?>"><?= $h(HT::STATUSES[$status] ?? $status) ?></span>
                </div>
                <h1 class="mt-3 text-2xl font-semibold"><?= $h($tip['title']) ?></h1>
                <?php if ($tip['summary']): ?><p class="mt-2 text-slate-600"><?= $h($tip['summary']) ?></p><?php endif; ?>

                <?php if ($tip['media']): ?>
                <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <?php foreach ($tip['media'] as $i => $m): ?>
                        <a href="/admin/health-tips/media/<?= (int) $m['id'] ?>" target="_blank" class="relative block overflow-hidden rounded border bg-slate-50">
                            <img src="/admin/health-tips/media/<?= (int) $m['id'] ?>" alt="" class="aspect-square w-full object-cover">
                            <?php if ($i === 0): ?><span class="absolute left-1 top-1 rounded bg-emerald-600 px-1.5 text-xs text-white">Cover</span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($tip['video_url']): ?>
                <p class="mt-4 text-sm">🎬 Video: <a href="<?= $h($tip['video_url']) ?>" target="_blank" rel="noopener" class="text-sky-700 underline break-all"><?= $h($tip['video_url']) ?></a>
                    <span class="text-slate-400">— check it’s the contributor’s own account.</span></p>
                <?php endif; ?>

                <?php if ($facts): ?>
                <dl class="mt-4 grid grid-cols-2 gap-3 rounded border bg-slate-50 p-3 text-sm sm:grid-cols-3">
                    <?php foreach ($facts as $k => $v): ?><div><dt class="text-xs text-slate-400"><?= $h($k) ?></dt><dd class="font-medium"><?= $h($v) ?></dd></div><?php endforeach; ?>
                </dl>
                <?php endif; ?>

                <?php if ($tip['precautions']): ?>
                <div class="mt-4 rounded bg-amber-50 p-3 text-sm text-amber-900"><strong>Take care:</strong> <?= nl2br($h($tip['precautions'])) ?></div>
                <?php endif; ?>

                <?php if ($tip['body']): ?>
                <div class="mt-4 whitespace-pre-line text-[15px] leading-relaxed text-slate-800"><?= $h($tip['body']) ?></div>
                <?php endif; ?>

                <?php if ($tip['ingredients']): ?>
                <h2 class="mt-6 font-semibold">Ingredients</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    <?php foreach ($tip['ingredients'] as $ing): ?><li><?php if ($ing['quantity']): ?><strong><?= $h($ing['quantity']) ?></strong> <?php endif; ?><?= $h($ing['item']) ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if ($tip['steps']): ?>
                <h2 class="mt-6 font-semibold"><?= $tip['type'] === 'recipe' ? 'Method' : 'Steps' ?></h2>
                <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm leading-relaxed">
                    <?php foreach ($tip['steps'] as $s): ?><li class="whitespace-pre-line"><?= $h($s) ?></li><?php endforeach; ?>
                </ol>
                <?php endif; ?>

                <?php if ($tip['tags']): ?>
                <div class="mt-6 flex flex-wrap gap-1.5">
                    <?php foreach ($tip['tags'] as $tg): ?><span class="rounded-full border px-2.5 py-0.5 text-xs text-slate-600"><?= $h($tg['name']) ?></span><?php endforeach; ?>
                </div>
                <?php endif; ?>
            </article>

            <!-- Decision + context -->
            <aside class="space-y-4">
                <div class="ui-card p-5">
                    <h2 class="font-semibold">Your decision</h2>

                    <?php if ($flagged): ?>
                    <div class="mt-3 rounded border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                        <strong>Check for medical claims.</strong> Found: <?= $h(implode(', ', $flagged)) ?>.
                        Fine if purely descriptive; send back if it claims to cure, treat or prevent anything.
                    </div>
                    <?php endif; ?>

                    <form method="post" action="/admin/health-tips/<?= (int) $tip['id'] ?>/review" class="mt-3 space-y-3">
                        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                        <?php if (!empty($tip['review_note'])): ?>
                            <p class="rounded bg-slate-50 p-2 text-xs text-slate-600"><strong>Previous note:</strong> <?= $h($tip['review_note']) ?></p>
                        <?php endif; ?>
                        <label class="block text-xs text-slate-600">Note to the contributor
                            <span class="text-slate-400">(required to send back or reject — they see it in their studio)</span>
                            <textarea name="note" rows="4" maxlength="1000" class="mt-1 w-full rounded border px-2 py-1.5 text-sm" placeholder="e.g. Lovely recipe! Please change “thyroid remedy” to “nutrient-rich” and add a photo of the finished dish."></textarea>
                        </label>
                        <div class="flex flex-col gap-2">
                            <?php if (in_array($status, ['submitted', 'changes_requested', 'unpublished'], true)): ?>
                                <button name="decision" value="publish" class="rounded bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"><?= $status === 'unpublished' ? 'Publish again' : 'Approve & publish' ?></button>
                            <?php endif; ?>
                            <?php if (in_array($status, ['submitted', 'published'], true)): ?>
                                <button name="decision" value="changes" class="rounded bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600">Send back for changes</button>
                            <?php endif; ?>
                            <?php if (in_array($status, ['submitted', 'changes_requested'], true)): ?>
                                <button name="decision" value="reject" onclick="return confirm('Reject this post? The contributor can’t edit a rejected post.');" class="rounded border border-rose-300 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                            <?php endif; ?>
                            <?php if ($status === 'published'): ?>
                                <button name="decision" value="unpublish" onclick="return confirm('Take this post offline?');" class="rounded border bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unpublish</button>
                            <?php endif; ?>
                            <?php if ($status === 'draft' || $status === 'rejected'): ?>
                                <p class="text-sm text-slate-500"><?= $status === 'draft' ? 'The contributor is still writing this — nothing to review yet.' : 'Rejected — no further action.' ?></p>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php if ($status === 'published'): ?>
                        <a href="<?= $h(HT::siteUrl('/health-tips/' . $tip['slug'])) ?>" target="_blank" rel="noopener" class="mt-3 block text-center text-sm text-sky-700 underline">View live page ↗</a>
                    <?php endif; ?>
                </div>

                <div class="ui-card p-5 text-sm">
                    <h2 class="font-semibold">Contributor</h2>
                    <p class="mt-2 font-medium"><?= $h($tip['display_name']) ?>
                        <?php if ($tip['contributor_status'] !== 'active'): ?><span class="ml-1 rounded bg-slate-200 px-1.5 text-xs">suspended</span><?php endif; ?></p>
                    <dl class="mt-2 space-y-1 text-slate-600">
                        <div>Account: <?= $h($tip['identity_name'] ?? '—') ?> · <?= $h($tip['identity_phone'] ?? '—') ?></div>
                        <?php if ($tip['identity_email']): ?><div>Email: <?= $h($tip['identity_email']) ?></div><?php endif; ?>
                        <?php if ($tip['instagram_handle']): ?><div>Instagram: <a class="text-sky-700 underline" target="_blank" rel="noopener" href="https://www.instagram.com/<?= $h($tip['instagram_handle']) ?>/">@<?= $h($tip['instagram_handle']) ?></a></div><?php endif; ?>
                        <div>Terms: <?= $tip['terms_accepted_at'] ? 'accepted ' . $h(date('d M Y', strtotime((string) $tip['terms_accepted_at']))) : '<span class="text-rose-600">not accepted</span>' ?></div>
                    </dl>
                    <a href="/admin/signups/<?= (int) $tip['identity_id'] ?>" class="mt-3 inline-block text-slate-700 underline">Open account</a>
                </div>

                <div class="ui-card p-5 text-xs text-slate-500 space-y-1">
                    <div>Created: <?= $h(date('d M Y, h:i A', strtotime((string) $tip['created_at']))) ?></div>
                    <?php if ($tip['submitted_at']): ?><div>Last sent for review: <?= $h(date('d M Y, h:i A', strtotime((string) $tip['submitted_at']))) ?></div><?php endif; ?>
                    <?php if ($tip['reviewed_at']): ?><div>Last reviewed: <?= $h(date('d M Y, h:i A', strtotime((string) $tip['reviewed_at']))) ?></div><?php endif; ?>
                    <?php if ($tip['published_at']): ?><div>First published: <?= $h(date('d M Y', strtotime((string) $tip['published_at']))) ?> · <?= (int) $tip['view_count'] ?> views · ♥ <?= (int) ($tip['save_count'] ?? 0) ?> saves</div><?php endif; ?>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
