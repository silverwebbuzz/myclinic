<?php
/**
 * Registration step 5 — Payment. Opens Razorpay Checkout for the order that
 * SignupCheckoutController::pay() just created. On success the result goes to
 * /onboarding/billing/razorpay-return, which verifies with Razorpay, unlocks
 * the clinic and continues into onboarding.
 */
$title = 'Payment — eClinicPro';
$maxWidth = 'max-w-lg';
$inr = static fn (float $n, int $dec = 0): string => '₹' . number_format($n, $dec);
ob_start();
?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <?php $signupStep = 5; require __DIR__ . '/_signup_steps.php'; ?>

    <h1 class="mt-6 text-xl font-semibold text-slate-900">Complete your payment</h1>
    <p class="mt-1 text-sm text-slate-500">Your account is activated as soon as the payment is confirmed.</p>

    <dl class="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Clinic</dt><dd class="truncate text-right text-slate-800"><?= htmlspecialchars((string) ($clinic['name'] ?? '')) ?></dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Plan</dt><dd class="text-slate-800"><?= htmlspecialchars(\App\Services\BillingGatewayService::planName()) ?> · Monthly</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">1 month</dt><dd class="text-slate-800"><?= $inr($price['list'], 2) ?></dd></div>
        <?php if (($price['discount'] ?? 0) > 0): ?>
            <div class="flex justify-between gap-3"><dt class="text-emerald-700">Discount (<?= htmlspecialchars((string) $price['code']) ?>)</dt><dd class="whitespace-nowrap text-emerald-700">− <?= $inr($price['discount'], 2) ?></dd></div>
        <?php endif; ?>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">GST (18%)</dt><dd class="text-slate-800"><?= $inr($price['tax'], 2) ?></dd></div>
        <div class="flex justify-between gap-3 border-t border-dashed border-slate-200 pt-2 text-base font-semibold"><dt class="text-slate-900">Total</dt><dd class="text-slate-900"><?= $inr($price['gross'], 2) ?></dd></div>
    </dl>

    <div id="payStatus" class="mt-4 hidden items-center justify-center gap-2 text-sm text-slate-600">
        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-emerald-600"></span>
        <span id="payStatusText">Opening secure payment…</span>
    </div>
    <p id="payError" class="mt-4 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"></p>

    <button type="button" id="payBtn" class="mt-5 w-full rounded-lg bg-emerald-600 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
        Pay <?= $inr($price['gross'], 2) ?> securely
    </button>
    <p class="mt-3 text-center text-[11px] text-slate-500">🔒 Payments by Razorpay · UPI · Cards · Net banking · Wallets</p>
    <p class="mt-4 text-center text-sm">
        <a href="<?= htmlspecialchars($back_url) ?>" class="text-emerald-700 hover:underline">← Back to checkout</a>
    </p>
</div>

<!-- Razorpay result → server verifies with Razorpay and activates. -->
<form id="returnForm" method="get" action="<?= htmlspecialchars($return_url) ?>" class="hidden">
    <input type="hidden" name="order_id" id="rf_order">
    <input type="hidden" name="payment_id" id="rf_payment">
    <input type="hidden" name="signature" id="rf_signature">
</form>

<script>
(function () {
    var options = <?= json_encode([
        'key' => $key_id,
        'order_id' => $order_id,
        'amount' => $amount,
        'currency' => $currency ?? 'INR',
        'name' => 'eClinicPro',
        'description' => $name,
        'prefill' => $prefill ?? [],
        'theme' => ['color' => '#059669'],
    ], JSON_THROW_ON_ERROR | JSON_HEX_TAG) ?>;

    var btn = document.getElementById('payBtn');
    var statusEl = document.getElementById('payStatus');
    var statusText = document.getElementById('payStatusText');
    var errorEl = document.getElementById('payError');

    function busy(text) {
        statusText.textContent = text;
        statusEl.classList.remove('hidden');
        statusEl.classList.add('flex');
        errorEl.classList.add('hidden');
        btn.disabled = true;
        btn.classList.add('opacity-60');
    }
    function fail(msg) {
        statusEl.classList.add('hidden');
        statusEl.classList.remove('flex');
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
        btn.disabled = false;
        btn.classList.remove('opacity-60');
        btn.textContent = 'Try again';
    }

    options.handler = function (response) {
        busy('Payment received — activating your account…');
        document.getElementById('rf_order').value = response.razorpay_order_id || options.order_id;
        document.getElementById('rf_payment').value = response.razorpay_payment_id || '';
        document.getElementById('rf_signature').value = response.razorpay_signature || '';
        document.getElementById('returnForm').submit();
    };
    options.modal = {
        ondismiss: function () { fail('Payment cancelled. You can try again whenever you are ready.'); },
        escape: true,
    };

    var rzp;
    try {
        rzp = new Razorpay(options);
        rzp.on('payment.failed', function (resp) {
            fail((resp && resp.error && resp.error.description) || 'Your payment could not be completed. Please try another method.');
        });
    } catch (e) {
        fail('Payment could not be started. Please refresh the page and try again.');
        return;
    }

    function open() { busy('Opening secure payment…'); rzp.open(); }
    btn.addEventListener('click', open);
    open();
})();
</script>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/guest.php';
