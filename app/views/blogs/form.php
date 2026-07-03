<?php
/** Doctor panel — create/edit blog post. */
$isEdit = !empty($post);
$postId = $isEdit ? (int) ($post['id'] ?? 0) : 0;
$contentHtml = (string) ($post['content'] ?? '');
$excerptText = (string) ($post['excerpt'] ?? '');
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">

<div class="mx-auto max-w-3xl space-y-4">
    <div class="flex items-center justify-between gap-3">
        <h2 class="ui-section-title"><?= $isEdit ? 'Edit post' : 'New post' ?></h2>
        <a href="/blogs" class="text-sm text-slate-500 hover:text-brand">← Back to blogs</a>
    </div>

    <?php if (!empty($_GET['error'])): ?>
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><?= htmlspecialchars((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <form method="post" action="/blogs/save" id="blog-post-form" class="ui-card space-y-4 p-6">
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
            <span class="text-sm font-medium text-slate-700">Excerpt <span class="font-normal text-slate-400">(optional — plain text shown on profile)</span></span>
            <textarea name="excerpt" rows="2" maxlength="500"
                      class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand"><?= htmlspecialchars($excerptText) ?></textarea>
        </label>

        <div class="block">
            <span class="text-sm font-medium text-slate-700">Content</span>
            <div id="blog-content-editor" class="blog-quill-editor blog-quill-editor--content mt-1 rounded-lg border border-slate-200 bg-white"></div>
            <textarea name="content" id="blog-content" class="hidden"><?= htmlspecialchars($contentHtml) ?></textarea>
        </div>

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

<style>
.blog-quill-editor .ql-toolbar.ql-snow {
    border: none;
    border-bottom: 1px solid #e2e8f0;
    border-radius: 0.5rem 0.5rem 0 0;
    background: #f8fafc;
}
.blog-quill-editor .ql-container.ql-snow {
    border: none;
    font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    font-size: 0.875rem;
}
.blog-quill-editor--content .ql-editor {
    min-height: 320px;
}
.blog-quill-editor .ql-editor.ql-blank::before {
    color: #94a3b8;
    font-style: normal;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    const contentField = document.getElementById('blog-content');
    const form = document.getElementById('blog-post-form');

    const quillContent = new Quill('#blog-content-editor', {
        theme: 'snow',
        placeholder: 'Write your post…',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link'],
                ['clean'],
            ],
        },
    });

    function isEmptyQuill(quill) {
        const text = quill.getText().replace(/\u00a0/g, ' ').trim();
        return text === '';
    }

    function loadHtml(quill, html) {
        const value = (html || '').trim();
        if (!value) return;
        quill.clipboard.dangerouslyPasteHTML(value);
    }

    loadHtml(quillContent, contentField.value);

    form.addEventListener('submit', function (e) {
        if (isEmptyQuill(quillContent)) {
            e.preventDefault();
            alert('Please add some content to your post.');
            quillContent.focus();
            return;
        }
        contentField.value = quillContent.root.innerHTML;
    });
})();
</script>
