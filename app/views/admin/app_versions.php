<?php
/**
 * /admin/app-versions — patient app update check (api/mobile/v1/app_version.php).
 *
 * @var string $csrf
 * @var array<string, string> $values platform_settings key => value
 * @var list<string> $errors
 * @var bool $saved
 * @var array<string, array{build: int, json: string}> $previews
 */
use App\Support\AppUpdatePolicy;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$val = static fn (string $p, string $f): string => (string) ($values[AppUpdatePolicy::key($p, $f)] ?? '');
$input = 'mt-1 block w-full rounded border px-3 py-1.5 text-sm';
$placeholders = [
    'android' => 'https://play.google.com/store/apps/details?id=com.eclinicpro.eclinicpro_patient',
    'ios' => 'https://apps.apple.com/in/app/eclinicpro/id0000000000',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>App versions — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-4xl space-y-6 p-6">
    <div>
        <h1 class="text-xl font-semibold">Mobile app versions</h1>
        <p class="text-sm text-slate-500">What the patient app's update check tells users. Builds are the number after the “+” in the app version
            (1.2.0+<strong>7</strong>), compared as numbers. Below the <strong>minimum build</strong> the app must update; below the
            <strong>latest build</strong> it may. Leave a platform completely empty to switch its update check off.</p>
    </div>

    <?php if ($saved): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">Saved. Apps see the change within 5 minutes.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
            <p class="font-medium">Nothing was saved. Please fix:</p>
            <ul class="mt-1 list-disc pl-5"><?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/app-versions" class="space-y-6">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <?php foreach (AppUpdatePolicy::PLATFORMS as $p => $label): ?>
            <section class="rounded-xl border bg-white p-5 shadow-sm">
                <h2 class="font-semibold"><?= $e($label) ?></h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <label class="block text-sm">
                        <span class="text-slate-600">Latest version</span>
                        <input name="<?= $p ?>_latest_version" value="<?= $e($val($p, 'latest_version')) ?>" maxlength="20" placeholder="1.2.0" class="<?= $input ?>">
                        <span class="mt-1 block text-xs text-slate-400">Shown to users only.</span>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Latest build</span>
                        <input name="<?= $p ?>_latest_build" value="<?= $e($val($p, 'latest_build')) ?>" inputmode="numeric" placeholder="7" class="<?= $input ?>">
                        <span class="mt-1 block text-xs text-slate-400">Below this: “Update available”.</span>
                    </label>
                    <label class="block text-sm">
                        <span class="text-slate-600">Minimum build</span>
                        <input name="<?= $p ?>_min_build" value="<?= $e($val($p, 'min_build')) ?>" inputmode="numeric" placeholder="5" class="<?= $input ?>">
                        <span class="mt-1 block text-xs text-slate-400">Below this: must update. Empty = none.</span>
                    </label>
                    <label class="block text-sm sm:col-span-3">
                        <span class="text-slate-600">Store URL</span>
                        <input name="<?= $p ?>_store_url" value="<?= $e($val($p, 'store_url')) ?>" placeholder="<?= $e($placeholders[$p]) ?>" class="<?= $input ?>">
                    </label>
                    <label class="block text-sm sm:col-span-3">
                        <span class="text-slate-600">Dialog title</span>
                        <input name="<?= $p ?>_update_title" value="<?= $e($val($p, 'update_title')) ?>" maxlength="80" placeholder="Empty = “Update available” / “Update required”" class="<?= $input ?>">
                    </label>
                    <label class="block text-sm sm:col-span-3">
                        <span class="text-slate-600">Dialog message</span>
                        <textarea name="<?= $p ?>_update_message" rows="2" maxlength="500" placeholder="Empty = a standard message" class="<?= $input ?>"><?= $e($val($p, 'update_message')) ?></textarea>
                    </label>
                    <label class="block text-sm sm:col-span-3">
                        <span class="text-slate-600">What's new (one per line, up to <?= AppUpdatePolicy::MAX_NOTES ?>)</span>
                        <textarea name="<?= $p ?>_update_notes" rows="3" placeholder="Faster checkout&#10;Bug fixes" class="<?= $input ?>"><?= $e($val($p, 'update_notes')) ?></textarea>
                    </label>
                </div>
            </section>
        <?php endforeach; ?>
        <button class="rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Save both</button>
    </form>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Preview</h2>
        <p class="mt-1 text-sm text-slate-500">The exact JSON the app receives from <code>api/mobile/v1/app_version.php</code> for a given build, using the <strong>saved</strong> settings.</p>
        <form method="get" action="/admin/app-versions" class="mt-3 flex flex-wrap items-end gap-3">
            <?php foreach (AppUpdatePolicy::PLATFORMS as $p => $label): ?>
                <label class="block text-sm">
                    <span class="text-slate-600"><?= $e($label) ?> build</span>
                    <input name="<?= $p ?>_build" value="<?= (int) $previews[$p]['build'] ?>" inputmode="numeric" class="mt-1 block w-24 rounded border px-3 py-1.5 text-sm">
                </label>
            <?php endforeach; ?>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Show</button>
        </form>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <?php foreach (AppUpdatePolicy::PLATFORMS as $p => $label): ?>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                        GET ?platform=<?= $e($p) ?>&amp;build=<?= (int) $previews[$p]['build'] ?>
                    </div>
                    <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs leading-relaxed text-slate-100"><?= $e($previews[$p]['json']) ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
