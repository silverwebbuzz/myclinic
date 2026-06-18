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
                Subscription payments (clinics paying for plans). Credentials are stored in
                the server's <code>.env</code> for security and are <strong>read-only here</strong>.
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
                <?php if ($g['active'] === 'cashfree'): ?>
                <span class="rounded-full px-3 py-1 text-xs font-medium <?= $g['cashfree_mode'] === 'production' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                    <?= $g['cashfree_mode'] === 'production' ? '● Production' : '● Sandbox' ?>
                </span>
                <?php endif; ?>
            </div>
        </section>

        <!-- Per-gateway key status -->
        <section class="rounded-xl border bg-white p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-semibold">Credentials</h2>

            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <div class="font-medium">Cashfree <span class="text-xs text-slate-400">(primary)</span></div>
                    <div class="text-xs text-slate-500">CASHFREE_APP_ID · CASHFREE_SECRET_KEY · CASHFREE_ENV</div>
                </div>
                <?= $dot((bool) $g['cashfree_set']) ?>
            </div>

            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium">Razorpay <span class="text-xs text-slate-400">(fallback)</span></div>
                    <div class="text-xs text-slate-500">RAZORPAY_KEY_ID · RAZORPAY_KEY_SECRET</div>
                </div>
                <?= $dot((bool) $g['razorpay_set']) ?>
            </div>
        </section>

        <!-- Endpoints -->
        <section class="rounded-xl border bg-white p-5 shadow-sm space-y-2 text-sm">
            <h2 class="text-sm font-semibold">Endpoints</h2>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Cashfree API base</span>
                <code class="text-slate-700"><?= htmlspecialchars($g['cashfree_api_base']) ?></code>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Cashfree webhook</span>
                <code class="text-slate-700"><?= htmlspecialchars($g['cashfree_webhook']) ?></code>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-slate-500">Razorpay webhook</span>
                <code class="text-slate-700"><?= htmlspecialchars($g['razorpay_webhook']) ?></code>
            </div>
        </section>

        <p class="text-xs text-slate-400">
            To change keys or switch sandbox/production, edit <code>.env</code> on the server and reload.
            Per-clinic patient-payment keys (Razorpay) are managed by each clinic under Settings → Notifications.
        </p>
    </main>
</body>
</html>
