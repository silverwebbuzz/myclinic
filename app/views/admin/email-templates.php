<?php
/** /admin/email-templates — edit transactional email content (super admin). */
$msgText = match ($message ?? '') {
    'saved' => ['ok', 'Template saved. New emails will use your content.'],
    'save_failed' => ['err', 'Could not save — check the database / migration 037.'],
    'reset' => ['ok', 'Template reset to the built-in default.'],
    'test_sent' => ['ok', 'Test email sent.'],
    'test_failed' => ['err', 'Test send failed — check Email delivery settings.'],
    'test_invalid' => ['err', 'Enter a valid email and template to test.'],
    'unknown_template' => ['err', 'Unknown template.'],
    default => null,
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Templates — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-3xl p-6 space-y-5">

        <div>
            <h1 class="text-xl font-semibold">Email templates</h1>
            <p class="text-sm text-slate-500">
                Edit the wording of automated emails. The eClinicPro logo header and footer
                are added automatically — you only edit the content. Use
                <code class="rounded bg-slate-200 px-1">{{placeholders}}</code> to insert dynamic values.
                Leave a template inactive to use the built-in default.
            </p>
        </div>

        <?php if ($msgText !== null): ?>
        <div class="rounded-lg border px-4 py-3 text-sm <?= $msgText[0] === 'ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?>">
            <?= htmlspecialchars($msgText[1]) ?>
        </div>
        <?php endif; ?>

        <!-- Send a test -->
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <h2 class="text-sm font-semibold">Send a test</h2>
            <p class="mt-1 text-xs text-slate-500">Sends with sample data, using whatever content is active (your edits or the default).</p>
            <form method="post" action="/admin/email-templates/test" class="mt-3 flex flex-wrap items-end gap-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <label class="block text-xs">
                    <span class="text-slate-600">Send to</span>
                    <input type="email" name="test_to" required value="<?= htmlspecialchars((string) ($admin['email'] ?? '')) ?>"
                           class="mt-1 block w-64 rounded border px-2 py-1.5 text-sm" placeholder="you@example.com">
                </label>
                <label class="block text-xs">
                    <span class="text-slate-600">Template</span>
                    <select name="test_template" class="mt-1 block rounded border px-2 py-1.5 text-sm">
                        <?php foreach ($registry as $key => $meta): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Send test</button>
            </form>
        </section>

        <!-- One editor per template -->
        <?php foreach ($registry as $key => $meta):
            $row = $rows[$key] ?? null;
            $hasOverride = $row !== null;
            $active = $hasOverride ? (int) ($row['is_active'] ?? 0) === 1 : false;
        ?>
        <section id="<?= htmlspecialchars($key) ?>" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold"><?= htmlspecialchars($meta['label']) ?></h2>
                    <code class="text-xs text-slate-400"><?= htmlspecialchars($key) ?></code>
                </div>
                <?php if ($hasOverride): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium <?= $active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $active ? 'Custom (active)' : 'Custom (inactive)' ?>
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Using default</span>
                <?php endif; ?>
            </div>

            <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                Available placeholders:
                <?php foreach ($meta['vars'] as $v): ?>
                <code class="rounded bg-white border px-1">{{<?= htmlspecialchars($v) ?>}}</code>
                <?php endforeach; ?>
            </div>

            <form method="post" action="/admin/email-templates/<?= htmlspecialchars($key) ?>" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <label class="block text-xs">
                    <span class="text-slate-600">Subject <span class="text-slate-400">(leave blank to keep the default subject)</span></span>
                    <input type="text" name="subject" value="<?= htmlspecialchars((string) ($row['subject'] ?? '')) ?>"
                           class="mt-1 block w-full rounded border px-2 py-1.5 text-sm">
                </label>

                <label class="block text-xs">
                    <span class="text-slate-600">Greeting</span>
                    <input type="text" name="greeting" value="<?= htmlspecialchars((string) ($row['greeting'] ?? '')) ?>"
                           placeholder="Hello {{doctor_name}}," class="mt-1 block w-full rounded border px-2 py-1.5 text-sm">
                </label>

                <label class="block text-xs">
                    <span class="text-slate-600">Body paragraphs <span class="text-slate-400">(separate paragraphs with a blank line)</span></span>
                    <textarea name="body" rows="5" class="mt-1 block w-full rounded border px-2 py-1.5 text-sm font-mono"><?= htmlspecialchars((string) ($row['body'] ?? '')) ?></textarea>
                </label>

                <label class="block text-xs">
                    <span class="text-slate-600">Bullet list <span class="text-slate-400">(one item per line, optional)</span></span>
                    <textarea name="bullets" rows="3" class="mt-1 block w-full rounded border px-2 py-1.5 text-sm font-mono"><?= htmlspecialchars((string) ($row['bullets'] ?? '')) ?></textarea>
                </label>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs">
                        <span class="text-slate-600">Button label <span class="text-slate-400">(optional)</span></span>
                        <input type="text" name="cta_label" value="<?= htmlspecialchars((string) ($row['cta_label'] ?? '')) ?>"
                               placeholder="Open dashboard" class="mt-1 block w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                    <label class="block text-xs">
                        <span class="text-slate-600">Button URL <span class="text-slate-400">(optional)</span></span>
                        <input type="text" name="cta_url" value="<?= htmlspecialchars((string) ($row['cta_url'] ?? '')) ?>"
                               placeholder="{{login_url}}" class="mt-1 block w-full rounded border px-2 py-1.5 text-sm">
                    </label>
                </div>

                <label class="block text-xs">
                    <span class="text-slate-600">Sign-off</span>
                    <textarea name="sign_off" rows="3" class="mt-1 block w-full rounded border px-2 py-1.5 text-sm font-mono"><?= htmlspecialchars((string) ($row['sign_off'] ?? '')) ?></textarea>
                </label>

                <label class="flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox" name="is_active" value="1" <?= (!$hasOverride || $active) ? 'checked' : '' ?>>
                    Use this custom content (uncheck to fall back to the built-in default)
                </label>

                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save</button>
                    <?php if ($hasOverride): ?>
                    <button type="submit" formaction="/admin/email-templates/<?= htmlspecialchars($key) ?>/reset"
                            class="rounded border px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Reset to default</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        <?php endforeach; ?>

        <p class="text-xs text-slate-500">
            Tip: to pre-fill an editor with the current default wording, send yourself a test first to see how it reads,
            then paste &amp; tweak. The logo header and footer are always added automatically.
        </p>
    </main>
</body>
</html>
