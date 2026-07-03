<?php
/** Doctor panel — blog posts list. */
$messages = [
    'post_saved' => 'Post saved as draft.',
    'post_published' => 'Post published successfully.',
    'post_deleted' => 'Post deleted.',
];
$flash = isset($message) ? ($messages[$message] ?? null) : null;
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="ui-section-title">Blogs</h2>
            <p class="text-sm text-slate-500 mt-1">Manage health articles published on your public profile.</p>
        </div>
        <a href="/blogs/new" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">
            + New post
        </a>
    </div>

    <?php if ($flash): ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>

    <div class="ui-card overflow-hidden">
        <?php if (empty($posts)): ?>
        <p class="p-8 text-center text-sm text-slate-500">No posts yet. Write your first article to help patients learn from your expertise.</p>
        <?php else: ?>
        <ul class="divide-y">
            <?php foreach ($posts as $post): ?>
            <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-4">
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-slate-900"><?= htmlspecialchars((string) $post['title']) ?></p>
                    <p class="mt-1 text-xs text-slate-500">
                        <?= $post['date'] ? htmlspecialchars(date('d M Y', strtotime((string) $post['date']))) : '' ?>
                        ·
                        <span class="<?= ($post['status'] ?? '') === 'publish' ? 'text-emerald-600' : 'text-amber-600' ?>">
                            <?= htmlspecialchars(ucfirst((string) ($post['status'] ?? 'draft'))) ?>
                        </span>
                    </p>
                    <?php if (!empty($post['excerpt'])): ?>
                    <p class="mt-1 text-sm text-slate-600 line-clamp-2"><?= htmlspecialchars((string) $post['excerpt']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if (($post['status'] ?? '') === 'publish' && !empty($post['link'])): ?>
                    <a href="<?= htmlspecialchars((string) $post['link']) ?>" target="_blank" rel="noopener"
                       class="text-xs text-slate-600 hover:underline">View</a>
                    <?php endif; ?>
                    <a href="/blogs/<?= (int) $post['id'] ?>/edit"
                       class="rounded-lg border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                    <?php if (($post['status'] ?? '') !== 'publish'): ?>
                    <form method="post" action="/blogs/<?= (int) $post['id'] ?>/publish" class="inline">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Publish</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" action="/blogs/<?= (int) $post['id'] ?>/delete" class="inline"
                          onsubmit="return confirm('Delete this post permanently?');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
                        <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
