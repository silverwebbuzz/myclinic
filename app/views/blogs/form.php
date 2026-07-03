<?php
/** Doctor panel — create/edit blog post. */
$isEdit = !empty($post);
$postId = $isEdit ? (int) ($post['id'] ?? 0) : 0;
?>
<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="ui-section-title"><?= $isEdit ? 'Edit post' : 'New post' ?></h2>
        <a href="/blogs" class="text-sm text-slate-500 hover:text-brand">← Back to blogs</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <form method="post" action="/blogs/save" class="ui-card space-y-4 p-6">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
        <?php if ($postId > 0): ?>
        <input type="hidden" name="post_id" value="<?= $postId ?>">
        <?php endif; ?>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Title</span>
            <input type="text" name="title" required maxlength="200"
                   value="<?= htmlspecialchars((string) ($post['title'] ?? '')) ?>"
                   class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand">
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Excerpt <span class="font-normal text-slate-400">(optional — shown on profile)</span></span>
            <textarea name="excerpt" rows="2" maxlength="500"
                      class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"><?= htmlspecialchars((string) ($post['excerpt'] ?? '')) ?></textarea>
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Content</span>
            <textarea name="content" rows="14" required
                      class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono leading-relaxed focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"><?= htmlspecialchars(strip_tags((string) ($post['content'] ?? ''))) ?></textarea>
            <span class="mt-1 block text-xs text-slate-400">Plain text or basic HTML is supported.</span>
        </label>

        <label class="block">
            <span class="text-sm font-medium text-slate-700">Status</span>
            <select name="status" class="mt-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <?php
                $status = (string) ($post['status'] ?? 'draft');
                foreach (['draft' => 'Draft', 'publish' => 'Published', 'pending' => 'Pending review'] as $val => $label):
                ?>
                <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="flex flex-wrap gap-2 pt-2">
            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Save</button>
            <a href="/blogs" class="rounded-lg border px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>
