<?php
/**
 * /admin/store/email-templates — wording + on/off for every store email.
 *
 * @var array<string, array{group: string, label: string, vars: list<string>, on: bool}> $registry
 * @var array<string, array{subject: string, title: string, body: string, cta: string, note: string}> $defaults
 * @var array<string, array<string, mixed>> $rows admin edits keyed by template_key
 * @var string $open template to show expanded
 */
use App\Services\Store\StoreEmailTemplates;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$input = 'mt-1 block w-full rounded border px-3 py-1.5 text-sm';
// Edited value if admin changed it, else the default (so the form always shows the real wording).
$val = static fn (?array $row, string $col, string $def): string => $row !== null && trim((string) ($row[$col] ?? '')) !== '' ? (string) $row[$col] : $def;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store email templates — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-6xl space-y-5 p-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Store email templates</h1>
            <p class="max-w-3xl text-sm text-slate-500">
                Edit the wording of every store email, or switch one off. Order details, item lines, totals, addresses and
                deadlines are filled in automatically. Use <code class="rounded bg-slate-200 px-1">{{placeholders}}</code> to insert
                values; leave a field as it is to keep the built-in wording.
            </p>
        </div>
        <a href="/admin/store/email" class="rounded border bg-white px-3 py-1.5 text-sm hover:bg-slate-50">Sender, test &amp; log →</a>
    </div>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <?php foreach (StoreEmailTemplates::GROUPS as $group => $groupLabel): ?>
        <section class="space-y-2">
            <h2 class="pt-2 text-sm font-semibold uppercase tracking-wide text-slate-500"><?= $e($groupLabel) ?></h2>
            <?php foreach ($registry as $key => $meta): if ($meta['group'] !== $group) { continue; }
                $row = $rows[$key] ?? null;
                $d = $defaults[$key];
                $enabled = $row !== null ? (int) $row['is_enabled'] === 1 : $meta['on'];
                $edited = $row !== null && array_filter([$row['subject'], $row['title'], $row['body'], $row['cta_label'], $row['note']], static fn ($v) => trim((string) $v) !== '');
            ?>
                <details id="<?= $e($key) ?>" class="group rounded-xl border bg-white shadow-sm" <?= $open === $key ? 'open' : '' ?>
                         data-preview="/admin/store/email-templates/<?= $e($key) ?>/preview">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3">
                        <span>
                            <span class="font-medium"><?= $e($meta['label']) ?></span>
                            <span class="ml-2 text-xs text-slate-400"><?= $e(str_replace(['{{', '}}'], ['‹', '›'], $val($row, 'subject', $d['subject']))) ?></span>
                        </span>
                        <span class="flex shrink-0 items-center gap-2 text-xs">
                            <?php if ($edited): ?><span class="rounded-full bg-sky-100 px-2 py-0.5 text-sky-800">Edited</span><?php endif; ?>
                            <span class="rounded-full px-2 py-0.5 <?= $enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= $enabled ? 'On' : 'Off' ?></span>
                            <svg class="h-4 w-4 text-slate-400 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                        </span>
                    </summary>
                    <div class="grid gap-5 border-t px-5 py-4 lg:grid-cols-2">
                        <form method="post" action="/admin/store/email-templates/<?= $e($key) ?>" class="space-y-3">
                            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                            <div class="rounded bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                Placeholders:
                                <?php foreach ($meta['vars'] as $v): ?><code class="mr-1 rounded border bg-white px-1">{{<?= $e($v) ?>}}</code><?php endforeach; ?>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="is_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                                Send this email
                            </label>
                            <label class="block text-sm"><span class="text-slate-600">Subject</span>
                                <input name="subject" value="<?= $e($val($row, 'subject', $d['subject'])) ?>" class="<?= $input ?>"></label>
                            <label class="block text-sm"><span class="text-slate-600">Heading</span>
                                <input name="title" value="<?= $e($val($row, 'title', $d['title'])) ?>" class="<?= $input ?>"></label>
                            <label class="block text-sm"><span class="text-slate-600">Message <span class="text-slate-400">(blank line between paragraphs)</span></span>
                                <textarea name="body" rows="5" class="<?= $input ?>"><?= $e($val($row, 'body', $d['body'])) ?></textarea></label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm"><span class="text-slate-600">Button text</span>
                                    <input name="cta_label" value="<?= $e($val($row, 'cta_label', $d['cta'])) ?>" class="<?= $input ?>"></label>
                            </div>
                            <label class="block text-sm"><span class="text-slate-600">Extra note <span class="text-slate-400">(shown after the details, optional)</span></span>
                                <textarea name="note" rows="2" class="<?= $input ?>"><?= $e($val($row, 'note', $d['note'])) ?></textarea></label>
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save</button>
                                <?php if ($row !== null): ?>
                                    <button formaction="/admin/store/email-templates/<?= $e($key) ?>/reset" formnovalidate
                                            onclick="return confirm('Go back to the built-in wording and switch the email on?')"
                                            class="rounded border px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Reset to default</button>
                                <?php endif; ?>
                                <a href="/admin/store/email-templates/<?= $e($key) ?>/preview" target="_blank" class="ml-auto text-sm text-sky-700 hover:underline">Open preview ↗</a>
                            </div>
                            <p class="text-xs text-slate-400">The button's link, the details box and item lines are added automatically. Save to update the preview.</p>
                        </form>
                        <div>
                            <div class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-400">Preview (sample data)</div>
                            <iframe title="Preview" class="h-[560px] w-full rounded-lg border bg-slate-100" data-src="/admin/store/email-templates/<?= $e($key) ?>/preview"></iframe>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</main>
<script>
// Load a preview only when its email is opened (there are ~45 of them).
document.querySelectorAll('details[data-preview]').forEach(function (d) {
    var load = function () {
        var f = d.querySelector('iframe[data-src]');
        if (d.open && f && !f.src) { f.src = f.getAttribute('data-src'); }
    };
    d.addEventListener('toggle', load);
    load();
});
</script>
</body>
</html>
