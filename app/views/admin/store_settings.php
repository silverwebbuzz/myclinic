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

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Shiprocket (courier)</h2>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $shiprocket['enabled'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= $shiprocket['enabled'] ? 'On' : 'Off' ?></span>
        </div>
        <p class="mt-1 text-sm text-slate-500">Use a Shiprocket <strong>API user</strong> (Shiprocket → Settings → API → Configure → Create API user), not your normal login. Sellers can book pickups only while this is on.</p>
        <form method="post" action="/admin/store/settings" class="mt-3 grid gap-3 sm:grid-cols-2" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="shiprocket_save">
            <label class="text-sm"><span class="text-slate-600">API user email</span>
                <input name="sr_email" type="email" value="<?= $e($shiprocket['email']) ?>" class="mt-1 w-full rounded border px-2 py-1.5"></label>
            <label class="text-sm"><span class="text-slate-600">API user password</span>
                <input name="sr_password" type="password" autocomplete="new-password" placeholder="<?= $shiprocket['has_password'] ? 'Saved (leave blank to keep)' : '' ?>" class="mt-1 w-full rounded border px-2 py-1.5"></label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sr_enabled" value="1" <?= $shiprocket['enabled'] ? 'checked' : '' ?>> Courier booking on</label>
            <div class="flex gap-2 sm:justify-end">
                <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save</button>
            </div>
        </form>
        <form method="post" action="/admin/store/settings" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="shiprocket_test">
            <button class="rounded border px-3 py-1.5 text-sm hover:bg-slate-50">Test connection</button>
        </form>
        <div class="mt-4 border-t pt-3 text-sm">
            <p class="font-medium">Tracking webhook</p>
            <p class="text-slate-500">In Shiprocket → Settings → API → Webhooks, add this URL and token (sent as the <code>x-api-key</code> header):</p>
            <input readonly value="<?= $e($shiprocket['webhook_url']) ?>" onclick="this.select()" class="mt-2 w-full rounded border bg-slate-50 px-3 py-1.5 font-mono text-xs">
            <?php if ($shiprocket['webhook_key'] !== ''): ?>
                <input readonly value="<?= $e($shiprocket['webhook_key']) ?>" onclick="this.select()" class="mt-2 w-full rounded border bg-slate-50 px-3 py-1.5 font-mono text-xs">
            <?php endif; ?>
            <form method="post" action="/admin/store/settings" class="mt-2">
                <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="shiprocket_webhook_key">
                <button class="rounded border px-3 py-1.5 text-sm hover:bg-slate-50"><?= $shiprocket['webhook_key'] !== '' ? 'Generate a new token' : 'Generate token' ?></button>
            </form>
            <p class="mt-2 text-xs text-slate-500">Without the webhook, tracking still updates every ~2 hours by polling (cron).</p>
        </div>
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

        <h2 class="pt-2 font-semibold">Orders &amp; money</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Shipping fee per seller (₹)</span>
                <input name="ship_flat" inputmode="decimal" value="<?= $e($shipping !== null ? \App\Services\Store\ProductService::rupees((int) $shipping['flat_fee_paise']) : '49') ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">Charged once per seller in an order (each seller ships separately).</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Free shipping when that seller's items total at least (₹)</span>
                <input name="ship_free_above" inputmode="decimal" value="<?= $e($shipping !== null && $shipping['free_above_paise'] !== null ? \App\Services\Store\ProductService::rupees((int) $shipping['free_above_paise']) : '') ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm" placeholder="Blank = never free">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Default commission (%)</span>
                <input name="store_default_commission_pct" type="number" step="0.01" min="0" max="50" value="<?= $e((int) $settings['store_default_commission_bp'] / 100) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">On the price the customer pays. 18% GST on commission is added on top (verify with your CA).</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Payment window (minutes)</span>
                <input name="store_payment_window_minutes" type="number" min="10" max="120" value="<?= $e($settings['store_payment_window_minutes']) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">Unpaid orders are cancelled after this and their stock is released.</span>
            </label>
        </div>
        <?php if ($shipping === null): ?><p class="text-xs text-amber-700">Import <code>2026_09_29_store_orders.sql</code> to enable shipping settings.</p><?php endif; ?>
        <button class="rounded bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Save</button>
    </form>
</main>
</body>
</html>
