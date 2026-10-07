<?php
/**
 * /admin/store/policies/{slug} — edit seller rules & terms. Each save = new version sellers must accept.
 *
 * @var string $slug
 * @var array{title:string, body:string, version:int, updated_at:?string} $page
 * @var array{current:int, accepted:int, sellers:int} $stats
 * @var list<array<string,mixed>> $versions
 * @var array<string,string> $tokens
 * @var string $preview
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $e($page['title']) ?> — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .policy h2 { font-size: 1.05rem; font-weight: 600; margin: 1.1rem 0 .4rem; }
        .policy h3 { font-weight: 600; margin: .9rem 0 .3rem; }
        .policy p { margin: .4rem 0; }
        .policy ul { list-style: disc; padding-left: 1.25rem; margin: .4rem 0; }
        .policy ol { list-style: decimal; padding-left: 1.25rem; margin: .4rem 0; }
        .policy li { margin: .2rem 0; }
    </style>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold"><?= $e($page['title']) ?></h1>
            <p class="text-sm text-slate-500">Shown to every seller at <code>/vendor/terms</code>. Saving creates a <strong>new version</strong>; sellers see a banner until they accept it.</p>
        </div>
        <div class="text-right text-sm">
            <p>Version <strong><?= (int) $page['version'] ?></strong><?= $page['updated_at'] ? ' · ' . $e(date('d M Y, h:i A', (int) strtotime((string) $page['updated_at']))) : '' ?></p>
            <p class="text-slate-500">Accepted by <strong><?= (int) $stats['accepted'] ?></strong> of <?= (int) $stats['sellers'] ?> active sellers</p>
        </div>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <div class="grid gap-5 lg:grid-cols-2">
        <form method="post" action="/admin/store/policies/<?= $e(rawurlencode($slug)) ?>" class="space-y-3 rounded-xl border bg-white p-5 shadow-sm"
              onsubmit="return confirm('Save as a new version? Every seller will be asked to accept it again.')">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <label class="block text-sm"><span class="text-slate-600">Title</span>
                <input name="title" value="<?= $e($page['title']) ?>" maxlength="190" class="mt-1 w-full rounded border px-2 py-1.5"></label>
            <?php if (($defaultBody ?? '') !== '' && trim(str_replace("\r\n", "\n", $page['body'])) !== trim($defaultBody)): ?>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                    <strong>The standard text has been updated</strong> (for example: one delivery fee per order, sellers pay the courier, weight disputes).
                    Load it into the editor, check it and add back any changes of your own, then save as a new version.
                    <button type="button" class="ml-1 font-semibold text-amber-800 underline"
                            onclick="if (confirm('Replace the text in the editor with the latest standard text? Nothing is saved until you press Save.')) { document.getElementById('policy-body').value = document.getElementById('policy-default').value; }">Load the latest standard text</button>
                    <textarea id="policy-default" hidden><?= $e($defaultBody) ?></textarea>
                </div>
            <?php endif; ?>
            <label class="block text-sm"><span class="text-slate-600">Text</span>
                <textarea id="policy-body" name="body" rows="30" class="mt-1 w-full rounded border px-2 py-1.5 font-mono text-xs leading-5"><?= $e($page['body']) ?></textarea></label>
            <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                <p class="font-medium text-slate-700">Formatting</p>
                <p><code>## Heading</code> · <code>### Sub-heading</code> · <code>- bullet</code> · <code>1. numbered step</code> · <code>**bold**</code> · blank line = new paragraph</p>
                <p class="mt-2 font-medium text-slate-700">Live values (filled in automatically from settings)</p>
                <ul class="mt-1 grid gap-x-4 sm:grid-cols-2">
                    <?php foreach ($tokens as $k => $v): ?><li><code>{{<?= $e($k) ?>}}</code> → <?= $e($v) ?></li><?php endforeach; ?>
                </ul>
            </div>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save new version</button>
        </form>

        <div class="space-y-5">
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-500">Preview (current saved version, as sellers see it)</h2>
                <div class="policy mt-2 max-h-[70vh] overflow-y-auto text-sm text-slate-800"><?= $preview ?></div>
            </section>
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold">Version history</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    <?php foreach ($versions as $v): ?>
                        <li>v<?= (int) $v['version'] ?> · <?= $e(date('d M Y, h:i A', (int) strtotime((string) $v['created_at']))) ?><?= $v['created_by'] ? '' : ' <span class="text-slate-400">(default text)</span>' ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
    </div>
</main>
</body>
</html>
