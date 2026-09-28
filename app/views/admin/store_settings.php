<?php
/**
 * /admin/store/settings — master switch, preview link, basic settings.
 *
 * @var bool $enabled
 * @var string|null $previewUrl
 * @var bool $cryptoReady
 * @var array<string,string> $settings
 */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store settings — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="mx-auto max-w-3xl space-y-5 p-6">
    <h1 class="text-xl font-semibold">Store settings</h1>
    <?php require __DIR__ . '/_store_flash.php'; ?>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold">Storefront visibility</h2>
                <p class="text-sm text-slate-500">While hidden, eclinicpro.com/store shows a 404 to the public. Sellers can still register and onboard.</p>
            </div>
            <form method="post" action="/admin/store/settings">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                <input type="hidden" name="action" value="toggle_enabled">
                <button class="rounded px-4 py-2 text-sm font-medium text-white <?= $enabled ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700' ?>"
                        onclick="return confirm('<?= $enabled ? 'Hide the store from customers?' : 'Make the store LIVE for everyone?' ?>')">
                    <?= $enabled ? 'Hide store' : 'Go live' ?>
                </button>
            </form>
        </div>
        <p class="mt-3 text-sm">Current state: <strong class="<?= $enabled ? 'text-emerald-700' : 'text-slate-700' ?>"><?= $enabled ? 'LIVE' : 'Hidden' ?></strong></p>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Preview link</h2>
        <p class="text-sm text-slate-500">Open this once in a browser to see the hidden store in that browser (sets a cookie for 30 days). Don't share it publicly.</p>
        <?php if ($previewUrl): ?>
            <input readonly value="<?= $e($previewUrl) ?>" onclick="this.select()" class="mt-3 w-full rounded border bg-slate-50 px-3 py-2 font-mono text-xs">
        <?php else: ?>
            <p class="mt-3 text-sm text-slate-400">No preview link yet.</p>
        <?php endif; ?>
        <form method="post" action="/admin/store/settings" class="mt-3">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="new_preview_key">
            <button class="rounded border px-3 py-1.5 text-sm hover:bg-slate-50"><?= $previewUrl ? 'Generate a new link (old one stops working)' : 'Generate preview link' ?></button>
        </form>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <h2 class="font-semibold">Security</h2>
        <p class="mt-1 text-sm">
            Encryption key (<code>STORE_DATA_KEY</code>):
            <?php if ($cryptoReady): ?>
                <strong class="text-emerald-700">configured ✓</strong>
            <?php else: ?>
                <strong class="text-red-700">missing</strong>. Sellers can't save PAN or bank details until it's set. Add a line
                <code>STORE_DATA_KEY=…</code> to <code>app/.env</code>, generated once with
                <code>php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"</code>. Never change it afterwards.
            <?php endif; ?>
        </p>
    </section>

    <form method="post" action="/admin/store/settings" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <h2 class="font-semibold">Catalog rules</h2>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="store_require_product_approval" value="1" <?= $settings['store_require_product_approval'] === '1' ? 'checked' : '' ?>>
            New products need admin approval before going live
        </label>
        <label class="block text-sm">
            <span class="text-slate-600">Default return window (days)</span>
            <input type="number" min="0" max="30" name="store_default_return_window_days" value="<?= $e($settings['store_default_return_window_days']) ?>" class="mt-1 w-32 rounded border px-2 py-1.5 text-sm">
        </label>
        <button class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save</button>
    </form>
</main>
</body>
</html>
