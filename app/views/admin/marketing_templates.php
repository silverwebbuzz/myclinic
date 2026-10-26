<?php
/** /admin/marketing/templates — WhatsApp draft templates (default + per specialty). */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
$overrides = array_diff_key($templates, ['default' => 1]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Marketing templates · Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>

    <main class="mx-auto max-w-5xl px-4 sm:px-6 py-6">
        <a href="/admin/marketing" class="text-xs text-slate-500 hover:underline">← Doctor marketing</a>
        <h1 class="mt-1 text-xl font-semibold">WhatsApp message templates</h1>
        <p class="text-sm text-slate-500">The default is used for every doctor unless their specialty has its own template.</p>

        <?php if ($message === 'saved'): ?>
        <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Templates saved.</div>
        <?php endif; ?>
        <?php foreach ($errors as $e): ?>
        <div class="mt-3 rounded border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800"><?= $h($e) ?></div>
        <?php endforeach; ?>

        <div class="mt-4 rounded-xl border bg-white p-4 text-xs text-slate-600">
            <div class="font-semibold text-slate-800">Placeholders</div>
            <div class="mt-2 grid gap-1 sm:grid-cols-2">
                <?php foreach ($placeholders as $k => $desc): ?>
                <div><code class="rounded bg-slate-100 px-1">{{<?= $h($k) ?>}}</code> <?= $h($desc) ?></div>
                <?php endforeach; ?>
            </div>
            <p class="mt-3">Missing data is left out automatically: a line whose placeholder has no value is dropped. Wrap an optional part in
                <code class="rounded bg-slate-100 px-1">[[ … ]]</code> to drop only that part, e.g. <code class="rounded bg-slate-100 px-1">profile[[ at {{clinic_name}}]][[ in {{city}}]]</code>.</p>
        </div>

        <?php if ($preview !== null): ?>
        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4">
            <div class="text-xs font-semibold text-sky-900">Preview: <?= $h($preview['code'] === 'default' ? 'Default' : ($labels[$preview['code']] ?? $preview['code'])) ?>
                <?php if ($preview['sample']): ?> · sample: <?= $h($preview['sample']['doctor_name'] ?: $preview['sample']['name']) ?>, <?= $h($preview['sample']['city']) ?><?php endif; ?>
                <span class="font-normal text-sky-700">(not saved yet)</span></div>
            <?php if ($preview['text'] !== null): ?>
            <pre class="mt-2 whitespace-pre-wrap rounded bg-white p-3 font-sans text-sm text-slate-800"><?= $h($preview['text']) ?></pre>
            <?php else: ?>
            <p class="mt-2 text-sm text-slate-600">No published, unclaimed listing with a mobile number in this specialty to preview with.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="post" action="/admin/marketing/templates" class="mt-4 space-y-4">
            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

            <section class="rounded-xl border bg-white p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Default template</h2>
                    <button type="submit" name="preview" value="default" class="rounded border px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Preview</button>
                </div>
                <textarea name="default" rows="14" class="mt-2 w-full rounded border p-2 font-mono text-sm"><?= $h($templates['default']) ?></textarea>
            </section>

            <div id="overrides" class="space-y-4">
                <?php foreach ($overrides as $code => $body): ?>
                <section class="rounded-xl border bg-white p-4" data-override>
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="code[]" class="rounded border px-2 py-1 text-sm">
                            <?php foreach ($specialties as $s): ?>
                            <option value="<?= $h($s['code']) ?>" <?= $s['code'] === $code ? 'selected' : '' ?>><?= $h($s['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="preview" value="<?= $h($code) ?>" class="rounded border px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">Preview</button>
                        <button type="button" onclick="this.closest('[data-override]').remove()" class="ml-auto text-xs text-rose-600 hover:underline">Remove</button>
                    </div>
                    <textarea name="body[]" rows="12" class="mt-2 w-full rounded border p-2 font-mono text-sm"><?= $h($body) ?></textarea>
                </section>
                <?php endforeach; ?>
            </div>

            <template id="overrideTpl">
                <section class="rounded-xl border bg-white p-4" data-override>
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="code[]" class="rounded border px-2 py-1 text-sm">
                            <?php foreach ($specialties as $s): ?>
                            <option value="<?= $h($s['code']) ?>"><?= $h($s['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="text-[11px] text-slate-400">Save, then Preview</span>
                        <button type="button" onclick="this.closest('[data-override]').remove()" class="ml-auto text-xs text-rose-600 hover:underline">Remove</button>
                    </div>
                    <textarea name="body[]" rows="12" class="mt-2 w-full rounded border p-2 font-mono text-sm"><?= $h($templates['default']) ?></textarea>
                </section>
            </template>

            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="document.getElementById('overrides').append(document.getElementById('overrideTpl').content.cloneNode(true))"
                        class="rounded border bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">+ Specialty-specific template</button>
                <button type="submit" class="rounded bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Save templates</button>
            </div>
        </form>
    </main>
</body>
</html>
