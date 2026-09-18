<?php
/**
 * Registration step 4 — Checkout. One plan: Standard, ₹999/month + GST.
 * Review and continue to payment (POST /register/payment).
 *
 * @var array{base: float, tax: float, gross: float, percent: float} $price
 */
$title = 'Checkout — eClinicPro';
$maxWidth = 'max-w-3xl';
$inr = static fn (float $n, int $dec = 0): string => '₹' . number_format($n, $dec);
$clinicName = (string) ($clinic['name'] ?? '');
$phone = preg_replace('/\D/', '', (string) ($clinic['phone'] ?? '')) ?? '';
if (strlen($phone) > 10) {
    $phone = substr($phone, -10);
}
$included = [
    'All modules — records, prescriptions, billing, queue, reports',
    'Unlimited patients & staff users',
    'Teleconsultation & public doctor profile',
    'GST invoice emailed after payment',
    'Free setup help & data import',
    'Renew month to month — no long contract',
];
ob_start();
?>
<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">

    <?php $signupStep = 4; require __DIR__ . '/_signup_steps.php'; ?>

    <div class="mt-6 flex flex-wrap items-end justify-between gap-2">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Review your plan</h1>
            <p class="mt-1 text-sm text-slate-500">One simple plan with everything included.</p>
        </div>
        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Account created ✓</span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars((string) $error) ?></div>
    <?php endif; ?>
    <?php if (!empty($notice)): ?>
        <div class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800"><?= htmlspecialchars((string) $notice) ?></div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 md:grid-cols-5">

        <!-- The plan -->
        <div class="md:col-span-3">
            <div class="rounded-xl border-2 border-emerald-600 bg-emerald-50/60 p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-slate-900"><?= htmlspecialchars(\App\Services\BillingGatewayService::planName()) ?></span>
                    <span class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">Monthly</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-3xl font-bold tracking-tight text-slate-900"><?= $inr($price['list']) ?></span>
                    <span class="text-sm text-emerald-700">/month + GST</span>
                </div>
                <p class="mt-1 text-xs text-slate-500">Billed monthly. Renew each month from Settings → Subscription.</p>
                <ul class="mt-4 grid gap-1.5 text-sm text-slate-700 sm:grid-cols-2">
                    <?php foreach ($included as $item): ?>
                        <li class="flex gap-2"><span class="font-bold text-emerald-600">✓</span><?= htmlspecialchars($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Order summary -->
        <aside class="md:col-span-2">
            <div class="rounded-xl border border-slate-200 p-5">
                <p class="text-sm font-semibold text-slate-900">Order summary</p>
                <dl class="mt-3 space-y-1 border-b border-slate-100 pb-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Clinic</dt><dd class="truncate text-right text-slate-800"><?= htmlspecialchars($clinicName) ?></dd></div>
                    <?php if ($phone !== ''): ?>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Mobile</dt><dd class="text-slate-800">+91 <?= htmlspecialchars($phone) ?></dd></div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Plan</dt><dd class="text-slate-800"><?= htmlspecialchars(\App\Services\BillingGatewayService::planName()) ?> · Monthly</dd></div>
                </dl>
                <!-- Discount code -->
                <div class="mt-3 border-b border-slate-100 pb-3">
                    <?php if (!empty($discount)): ?>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2">
                            <div class="min-w-0 text-sm">
                                <span class="font-mono font-semibold text-emerald-800"><?= htmlspecialchars((string) $discount['code']) ?></span>
                                <span class="text-emerald-700"> · <?= htmlspecialchars((string) $discountLabel) ?></span>
                            </div>
                            <form method="post" action="/register/checkout/code">
                                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" class="text-xs font-medium text-slate-500 hover:text-rose-600 hover:underline">Remove</button>
                            </form>
                        </div>
                        <?php if (!empty($codeApplied)): ?>
                            <p class="mt-1.5 text-xs text-emerald-700">Discount applied — you save <?= $inr($price['discount'], 2) ?> + GST.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <form method="post" action="/register/checkout/code" x-data="{ open: <?= !empty($codeError) ? 'true' : 'false' ?> }">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                            <button type="button" x-show="!open" @click="open = true; $nextTick(() => $refs.code.focus())"
                                    class="text-sm font-medium text-emerald-700 hover:underline">Have a discount code?</button>
                            <div x-show="open" x-cloak class="flex gap-2">
                                <input x-ref="code" name="code" type="text" autocomplete="off" maxlength="40" placeholder="Enter code"
                                       class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                <button type="submit" class="rounded-lg border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Apply</button>
                            </div>
                        </form>
                        <?php if (!empty($codeError)): ?>
                            <p class="mt-1.5 text-xs text-rose-600"><?= htmlspecialchars((string) $codeError) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">1 month</dt><dd class="text-slate-800"><?= $inr($price['list'], 2) ?></dd></div>
                    <?php if ($price['discount'] > 0): ?>
                        <div class="flex justify-between gap-3"><dt class="text-emerald-700">Discount (<?= htmlspecialchars((string) $price['code']) ?>)</dt><dd class="whitespace-nowrap text-emerald-700">− <?= $inr($price['discount'], 2) ?></dd></div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">GST (<?= (int) $price['percent'] ?>%)</dt><dd class="text-slate-800"><?= $inr($price['tax'], 2) ?></dd></div>
                    <div class="flex justify-between gap-3 border-t border-dashed border-slate-200 pt-2 text-base font-semibold">
                        <dt class="text-slate-900">Total today</dt><dd class="text-slate-900"><?= $inr($price['gross'], 2) ?></dd>
                    </div>
                </dl>

                <form method="post" action="/register/payment">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="mt-5 w-full rounded-lg bg-emerald-600 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        <?= $price['gross'] > 0 ? 'Continue to payment →' : 'Activate my account →' ?>
                    </button>
                </form>
                <p class="mt-3 text-center text-[11px] leading-relaxed text-slate-500">
                    🔒 Secure payment by Razorpay<br>UPI · Cards · Net banking · Wallets
                </p>
            </div>
        </aside>
    </div>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
        <span>Questions? WhatsApp us at <a class="text-emerald-700 hover:underline" href="https://wa.me/919998010029" target="_blank" rel="noopener">+91 99980 10029</a></span>
        <form method="post" action="/logout">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <button type="submit" class="text-slate-500 hover:text-slate-700 hover:underline">Log out</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
