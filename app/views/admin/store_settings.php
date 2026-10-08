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

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Payments (Razorpay)</h2>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= !$razorpay['configured'] ? 'bg-red-100 text-red-800' : ($razorpay['mode'] === 'production' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') ?>">
                Store: <?= !$razorpay['configured'] ? 'keys missing' : ($razorpay['mode'] === 'production' ? 'LIVE' : 'SANDBOX') ?><?= $razorpay['key_hint'] !== '' ? ' · ' . $e($razorpay['key_hint']) : '' ?>
            </span>
        </div>
        <p class="mt-1 text-sm text-slate-500">Same Razorpay account and credentials as the main site (from <code>app/.env</code>); nothing to enter here.
            Switch the <strong>store</strong> between live and sandbox on its own. The main site stays <strong><?= $razorpay['site_mode'] === 'production' ? 'live' : 'sandbox' ?></strong> either way.</p>
        <form method="post" action="/admin/store/settings" class="mt-3 flex flex-wrap items-center gap-4"
              onsubmit="return this.rzp_mode.value !== 'production' || confirm('Switch the store to LIVE payments? Customers will be charged real money.')">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="razorpay_save">
            <label class="flex items-center gap-1.5 text-sm <?= $razorpay['live_ready'] ? '' : 'text-slate-400' ?>">
                <input type="radio" name="rzp_mode" value="production" <?= $razorpay['mode'] === 'production' ? 'checked' : '' ?> <?= $razorpay['live_ready'] ? '' : 'disabled' ?>> Live
            </label>
            <label class="flex items-center gap-1.5 text-sm <?= $razorpay['sandbox_ready'] ? '' : 'text-slate-400' ?>">
                <input type="radio" name="rzp_mode" value="sandbox" <?= $razorpay['mode'] === 'sandbox' ? 'checked' : '' ?> <?= $razorpay['sandbox_ready'] ? '' : 'disabled' ?>> Sandbox (test payments)
            </label>
            <button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save</button>
        </form>
        <?php if (!$razorpay['sandbox_ready'] || !$razorpay['live_ready']): ?>
            <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">
                <?php if (!$razorpay['sandbox_ready']): ?>
                    <p><strong>Sandbox is unavailable:</strong> Razorpay test mode uses a separate key pair. Add it <strong>once</strong> to <code>app/.env</code> (Razorpay Dashboard → switch to Test mode → Account &amp; Settings → API keys):</p>
                    <pre class="mt-1 rounded bg-white p-2 font-mono">RAZORPAY_TEST_KEY_ID=rzp_test_…
RAZORPAY_TEST_KEY_SECRET=…
RAZORPAY_TEST_WEBHOOK_SECRET=…   (optional: the secret of the Test-mode webhook)</pre>
                <?php endif; ?>
                <?php if (!$razorpay['live_ready']): ?>
                    <p class="mt-2"><strong>Live is unavailable:</strong> add <code>RAZORPAY_LIVE_KEY_ID</code> / <code>RAZORPAY_LIVE_KEY_SECRET</code> to <code>app/.env</code> (or use rzp_live_ keys for the main site).</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="mt-2 text-xs text-slate-500">Switch only when no store payment is in progress: refunds use the keys active at that moment. For sandbox, also add the webhook <code>https://app.eclinicpro.com/webhooks/razorpay</code> in Razorpay's Test mode.</p>
        <form method="post" action="/admin/store/settings" class="mt-2">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="razorpay_test">
            <button class="rounded border px-3 py-1.5 text-sm hover:bg-slate-50">Test connection</button>
        </form>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Invoicing (GST)</h2>
            <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $invoicing['ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= $invoicing['ready'] ? 'Ready' : 'Details missing' ?></span>
        </div>
        <p class="mt-1 text-sm text-slate-500">Sellers' goods are invoiced in <em>their</em> name automatically. The <strong>delivery charge</strong> is eClinicPro's own service, so eClinicPro issues a separate small invoice for it, using these details. Until they're filled in, delivery charges are not invoiced. See <a class="underline" href="/admin/store/gst">GST register</a>.</p>
        <form method="post" action="/admin/store/settings" class="mt-3 grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="invoicing_save">
            <label class="text-sm"><span class="text-slate-600">Legal name (as on GST registration)</span>
                <input name="platform_legal_name" value="<?= $e($invoicing['legal_name']) ?>" class="mt-1 w-full rounded border px-2 py-1.5"></label>
            <label class="text-sm"><span class="text-slate-600">GSTIN</span>
                <input name="platform_gstin" maxlength="15" value="<?= $e($invoicing['gstin']) ?>" class="mt-1 w-full rounded border px-2 py-1.5 font-mono uppercase"></label>
            <label class="text-sm sm:col-span-2"><span class="text-slate-600">Registered address (printed on invoices)</span>
                <textarea name="platform_address" rows="2" class="mt-1 w-full rounded border px-2 py-1.5"><?= $e($invoicing['address']) ?></textarea></label>
            <label class="text-sm"><span class="text-slate-600">Courts city for disputes (seller terms)</span>
                <input name="legal_city" maxlength="80" value="<?= $e($invoicing['legal_city']) ?>" placeholder="e.g. Ahmedabad, Gujarat" class="mt-1 w-full rounded border px-2 py-1.5"></label>
            <label class="text-sm"><span class="text-slate-600">Grievance email (seller terms)</span>
                <input name="grievance_email" type="email" maxlength="190" value="<?= $e($invoicing['grievance_email']) ?>" placeholder="hello@eclinicpro.com" class="mt-1 w-full rounded border px-2 py-1.5"></label>
            <label class="text-sm"><span class="text-slate-600">SAC code for delivery charges</span>
                <input name="delivery_sac" maxlength="8" value="<?= $e($invoicing['sac']) ?>" class="mt-1 w-full rounded border px-2 py-1.5">
                <span class="text-xs text-slate-400">996812 = courier services. Confirm with your CA.</span></label>
            <label class="text-sm"><span class="text-slate-600">GST included in the delivery charge</span>
                <select name="delivery_gst_bp" class="mt-1 w-full rounded border px-2 py-1.5">
                    <?php foreach (\App\Services\Store\CatalogService::GST_RATES_BP as $bp => $label): ?>
                        <option value="<?= (int) $bp ?>" <?= (int) $bp === (int) $invoicing['gst_bp'] ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="require_gstin" value="1" <?= $invoicing['require_gstin'] ? 'checked' : '' ?>>
                Sellers must have a GSTIN to be approved <span class="text-slate-400">(recommended; without one, their invoices become a bill of supply with no GST)</span></label>
            <div class="sm:col-span-2"><button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save invoicing details</button></div>
        </form>
    </section>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">Seller terms &amp; payouts</h2>
            <a href="/admin/store/policies/seller_terms" class="text-sm text-sky-700 hover:underline">Edit the seller terms text →</a>
        </div>
        <p class="mt-1 text-sm text-slate-500">These numbers appear in the seller terms (as <code>{{tokens}}</code>) and drive the system, so the terms always match what actually happens.
            <strong>A change here changes the terms sellers already accepted.</strong> If it makes things worse for sellers (for example a shorter dispute period), also publish a new version of the terms so they get notice.</p>
        <form method="post" action="/admin/store/settings" class="mt-3 grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <input type="hidden" name="action" value="seller_terms_save">
            <label class="text-sm"><span class="text-slate-600">Weekly payout day</span>
                <select name="store_payout_weekday" class="mt-1 w-full rounded border px-2 py-1.5">
                    <?php foreach (\App\Services\Store\StorePolicyService::WEEKDAYS as $n => $day): ?>
                        <option value="<?= (int) $n ?>" <?= (int) $n === (int) ($sellerTerms['store_payout_weekday'] ?? 2) ? 'selected' : '' ?>><?= $e($day) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="text-xs text-slate-400">Everything whose return window has ended is paid on this day.</span></label>
            <label class="text-sm"><span class="text-slate-600">Minimum payout (₹)</span>
                <input name="min_payout" inputmode="decimal" value="<?= $e(\App\Services\Store\ProductService::rupees((int) ($sellerTerms['store_min_payout_paise'] ?? 10000))) ?>" class="mt-1 w-full rounded border px-2 py-1.5">
                <span class="text-xs text-slate-400">Smaller balances roll over to the next week.</span></label>
            <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="store_payout_auto_batch" value="1" class="mt-1" <?= ($sellerTerms['store_payout_auto_batch'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span>Create the payout batch automatically on the payout day <span class="text-slate-400">(the store team gets an email; you still transfer the money and mark each payout paid with the UTR on <a class="underline" href="/admin/store/payouts">Payouts</a>)</span></span></label>
            <?php foreach (\App\Services\Store\StorePolicyService::TERMS_SETTINGS as $key => [$label, $min, $max, $default, $help]): ?>
                <label class="text-sm"><span class="text-slate-600"><?= $e($label) ?></span>
                    <input type="number" name="<?= $e($key) ?>" min="<?= (int) $min ?>" max="<?= (int) $max ?>" value="<?= $e($sellerTerms[$key] ?? $default) ?>" class="mt-1 w-full rounded border px-2 py-1.5">
                    <span class="text-xs text-slate-400"><code>{{<?= $e(substr($key, 6)) ?>}}</code><?= $help !== '' ? ' · ' . $e($help) : '' ?></span></label>
            <?php endforeach; ?>
            <div class="sm:col-span-2"><button class="rounded bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Save seller terms &amp; payouts</button></div>
        </form>
    </section>

    <form method="post" action="/admin/store/settings" class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
        <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
        <input type="hidden" name="action" value="save">
        <h2 class="font-semibold">Catalog rules</h2>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="store_require_product_approval" value="1" <?= $settings['store_require_product_approval'] === '1' ? 'checked' : '' ?>>
            New products need admin approval before going live
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="store_reviews_auto_publish" value="1" <?= ($settings['store_reviews_auto_publish'] ?? '0') === '1' ? 'checked' : '' ?>>
            Publish customer reviews without moderation <span class="text-slate-400">(not recommended for health products)</span>
        </label>

        <h2 class="pt-2 font-semibold">Orders &amp; money</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-slate-600">Customer delivery fee per order (₹)</span>
                <input name="ship_flat" inputmode="decimal" value="<?= $e($shipping !== null ? \App\Services\Store\ProductService::rupees((int) $shipping['flat_fee_paise']) : '49') ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">Charged once per order, however many sellers. It is split across the packages and reduces each seller's courier charge.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Free delivery when the order's items total at least (₹)</span>
                <input name="ship_free_above" inputmode="decimal" value="<?= $e($shipping !== null && $shipping['free_above_paise'] !== null ? \App\Services\Store\ProductService::rupees((int) $shipping['free_above_paise']) : '') ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm" placeholder="Blank = never free">
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Courier estimate: first 500 g (₹, GST incl.)</span>
                <input name="courier_base" inputmode="decimal" value="<?= $e(\App\Services\Store\ProductService::rupees((int) ($settings['store_courier_est_base_paise'] ?? 6500))) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">Used for the sellers' earnings calculator, and at delivery when the real Shiprocket charge isn't known. Sellers pay the courier minus the customer's delivery fee share.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Courier estimate: each extra 500 g (₹, GST incl.)</span>
                <input name="courier_addl" inputmode="decimal" value="<?= $e(\App\Services\Store\ProductService::rupees((int) ($settings['store_courier_est_addl_paise'] ?? 4000))) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">Set these to your average Shiprocket rates across zones.</span>
            </label>
            <label class="block text-sm">
                <span class="text-slate-600">Default commission (%)</span>
                <input name="store_default_commission_pct" type="number" step="0.01" min="0" max="50" value="<?= $e((int) $settings['store_default_commission_bp'] / 100) ?>" class="mt-1 w-full rounded border px-2 py-1.5 text-sm">
                <span class="text-xs text-slate-400">On the seller's price before GST (after any discount the seller funds): ₹118 incl. 18% GST → 10% of ₹100. 18% GST on commission is added on top (verify with your CA).</span>
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
