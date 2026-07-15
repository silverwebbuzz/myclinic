<?php
/** /admin/payment-gateway — read-only subscription gateway status.
 * @var array<string,mixed> $gateway */
$g = $gateway;
$dot = static fn (bool $on): string => $on
    ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Configured</span>'
    : '<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Not set</span>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Gateway — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-3xl p-6 space-y-5">

        <div>
            <h1 class="text-xl font-semibold">Payment gateway</h1>
            <p class="text-sm text-slate-500">
                Subscription payments (clinics paying for plans) are processed via
                <strong>Razorpay</strong>. Credentials are stored in the server's
                <code>.env</code> for security and are <strong>read-only here</strong>.
            </p>
        </div>

        <!-- Active gateway summary -->
        <section class="rounded-xl border bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-400">Active gateway</div>
                    <div class="mt-0.5 text-lg font-semibold capitalize">
                        <?= htmlspecialchars($g['active']) ?>
                        <?php if ($g['active'] === 'simulate'): ?>
                        <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">No live keys — simulated</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($g['active'] === 'razorpay'): ?>
                <span class="rounded-full px-3 py-1 text-xs font-medium <?= $g['razorpay_mode'] === 'production' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                    <?= $g['razorpay_mode'] === 'production' ? '● Production (live)' : '● Sandbox (test)' ?>
                </span>
                <?php endif; ?>
            </div>
        </section>

        <!-- Credentials -->
        <section class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-semibold">Credentials</h2>

            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium">Razorpay</div>
                    <div class="text-xs text-slate-500">RAZORPAY_KEY_ID · RAZORPAY_KEY_SECRET · RAZORPAY_ENV · RAZORPAY_WEBHOOK_SECRET</div>
                    <?php if (!empty($g['key_prefix'])): ?>
                    <div class="mt-1 text-[11px] text-slate-400">Key prefix: <code><?= htmlspecialchars($g['key_prefix']) ?>…</code>
                        <?php if (str_starts_with((string) $g['key_prefix'], 'rzp_live') && $g['razorpay_mode'] !== 'production'): ?>
                        <span class="ml-1 text-rose-600">⚠ live key but RAZORPAY_ENV is sandbox</span>
                        <?php elseif (str_starts_with((string) $g['key_prefix'], 'rzp_test') && $g['razorpay_mode'] === 'production'): ?>
                        <span class="ml-1 text-rose-600">⚠ test key but RAZORPAY_ENV is production</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?= $dot((bool) $g['razorpay_set']) ?>
            </div>
        </section>

        <!-- Endpoints -->
        <section class="rounded-xl border bg-white p-5 shadow-sm space-y-2 text-sm">
            <h2 class="text-sm font-semibold">Endpoints</h2>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Razorpay API base</span>
                <code class="text-slate-700"><?= htmlspecialchars($g['razorpay_api_base']) ?></code>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Webhook URL</span>
                <code class="text-slate-700"><?= htmlspecialchars($g['razorpay_webhook']) ?></code>
            </div>
        </section>

        <!-- Setup checklist -->
        <section class="rounded-xl border bg-white p-5 shadow-sm space-y-2 text-sm">
            <h2 class="text-sm font-semibold">Going live</h2>
            <ol class="ml-4 list-decimal space-y-1 text-slate-600">
                <li>Sandbox: use <code>rzp_test_…</code> keys and set <code>RAZORPAY_ENV=sandbox</code>.</li>
                <li>Production: swap in <code>rzp_live_…</code> keys and set <code>RAZORPAY_ENV=production</code>.</li>
                <li>In the Razorpay dashboard → Webhooks, add the URL above and subscribe to
                    <code>payment.captured</code>, <code>payment.failed</code>, and <code>order.paid</code>.</li>
                <li>Copy the webhook secret into <code>RAZORPAY_WEBHOOK_SECRET</code>.</li>
            </ol>
        </section>

        <p class="text-xs text-slate-400">
            To change keys or switch sandbox/production, edit <code>.env</code> on the server and reload.
            Per-clinic patient-payment keys are managed by each clinic under Settings → Notifications.
        </p>
    </main>
</body>
</html>
