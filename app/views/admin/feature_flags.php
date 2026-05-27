<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feature Flags — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-5xl p-6 space-y-4">

        <div>
            <h1 class="text-xl font-semibold">Feature flags</h1>
            <p class="text-sm text-slate-500">
                Bucket-3 features stay coded but hidden until you flip them on here.
                Use <code>scope=all</code> for a global rollout, <code>scope=beta</code> with
                a comma-separated tenant ID list for staged rollouts.
            </p>
        </div>

        <?php if ($message): ?>
        <div class="rounded bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm text-emerald-800">
            <?= htmlspecialchars((string) $message) ?>
        </div>
        <?php endif; ?>

        <?php if (empty($flags)): ?>
            <div class="rounded-xl border bg-white p-6 text-sm text-slate-500">
                The <code>feature_flags</code> table doesn't exist yet. Run Phase 1 migrations first.
            </div>
        <?php else: ?>
            <div class="space-y-3">
            <?php foreach ($flags as $f):
                $betaIds = $f['beta_tenant_ids'] ? json_decode((string) $f['beta_tenant_ids'], true) : [];
                $betaStr = is_array($betaIds) ? implode(', ', $betaIds) : '';
            ?>
            <form method="post" action="/admin/feature-flags/<?= htmlspecialchars($f['flag_key']) ?>"
                  class="rounded-xl border bg-white p-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-semibold text-sm"><?= htmlspecialchars($f['flag_key']) ?></div>
                        <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($f['description'] ?? '') ?></p>
                    </div>
                    <label class="inline-flex items-center text-xs">
                        <input type="checkbox" name="is_enabled" value="1"
                               <?= !empty($f['is_enabled']) ? 'checked' : '' ?>
                               class="mr-1">
                        Enabled
                    </label>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs">
                    <label>
                        Scope:
                        <select name="scope" class="ml-1 rounded border px-2 py-1 text-xs">
                            <option value="all" <?= $f['scope'] === 'all' ? 'selected' : '' ?>>all</option>
                            <option value="beta" <?= $f['scope'] === 'beta' ? 'selected' : '' ?>>beta</option>
                            <option value="tenant" <?= $f['scope'] === 'tenant' ? 'selected' : '' ?>>tenant</option>
                        </select>
                    </label>
                    <label class="flex-1">
                        Beta tenant IDs (comma-sep):
                        <input type="text" name="beta_tenant_ids"
                               value="<?= htmlspecialchars($betaStr) ?>"
                               placeholder="e.g. 12,34,56"
                               class="ml-1 rounded border px-2 py-1 text-xs w-72">
                    </label>
                    <button type="submit" class="rounded bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-900">
                        Save
                    </button>
                </div>
            </form>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</body>
</html>
