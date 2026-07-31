<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to payment…</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #475569; }
        .box { text-align: center; max-width: 24rem; padding: 2rem; }
        .spinner { width: 2rem; height: 2rem; border: 3px solid #e2e8f0; border-top-color: #4f46e5; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #4f46e5; }
        button.retry { margin-top: 1rem; padding: 0.5rem 1rem; border: 1px solid #4f46e5; background: #4f46e5; color: #fff; border-radius: 0.5rem; cursor: pointer; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner" id="spinner"></div>
        <p id="status">Opening secure payment…</p>
        <p id="error" style="display:none;color:#b91c1c;font-size:0.875rem;"></p>
        <p id="actions" style="display:none;margin-top:1rem;">
            <button type="button" class="retry" id="retryBtn">Try again</button>
            <br>
            <a href="<?= htmlspecialchars($cancel_url ?? '/subscription') ?>" style="display:inline-block;margin-top:0.75rem;font-size:0.8rem;">Go back</a>
        </p>
    </div>

    <!-- Return form: posts the Razorpay result to our server for verification. -->
    <form id="returnForm" method="get" action="<?= htmlspecialchars($return_url ?? '/onboarding/billing/razorpay-return') ?>" style="display:none;">
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
                'description' => $name ?? 'Subscription',
                'prefill' => $prefill ?? [],
                'theme' => ['color' => '#4f46e5'],
            ], JSON_THROW_ON_ERROR) ?>;

            var statusEl = document.getElementById('status');
            var errorEl = document.getElementById('error');
            var actionsEl = document.getElementById('actions');
            var spinnerEl = document.getElementById('spinner');
            var cancelUrl = <?= json_encode($cancel_url ?? '/subscription', JSON_THROW_ON_ERROR) ?>;

            function showMessage(msg) {
                spinnerEl.style.display = 'none';
                statusEl.style.display = 'none';
                errorEl.textContent = msg;
                errorEl.style.display = 'block';
                actionsEl.style.display = 'block';
            }

            // On success, hand the result to our server to verify + activate.
            options.handler = function (response) {
                statusEl.textContent = 'Payment received — confirming…';
                spinnerEl.style.display = 'block';
                statusEl.style.display = 'block';
                errorEl.style.display = 'none';
                actionsEl.style.display = 'none';
                document.getElementById('rf_order').value = response.razorpay_order_id || options.order_id;
                document.getElementById('rf_payment').value = response.razorpay_payment_id || '';
                document.getElementById('rf_signature').value = response.razorpay_signature || '';
                document.getElementById('returnForm').submit();
            };

            // Cancelled: doctor closed the modal. Invoice stays pending; go back.
            options.modal = {
                ondismiss: function () {
                    showMessage('Payment cancelled. You can try again whenever you\'re ready.');
                },
                escape: true,
            };

            try {
                var rzp = new Razorpay(options);

                // Payment failed (declined card, bank error, etc.).
                rzp.on('payment.failed', function (resp) {
                    var reason = (resp && resp.error && resp.error.description)
                        ? resp.error.description
                        : 'Your payment could not be completed. Please try a different method.';
                    showMessage(reason);
                });

                rzp.open();

                document.getElementById('retryBtn').addEventListener('click', function () {
                    errorEl.style.display = 'none';
                    actionsEl.style.display = 'none';
                    statusEl.style.display = 'block';
                    spinnerEl.style.display = 'block';
                    statusEl.textContent = 'Opening secure payment…';
                    rzp.open();
                });
            } catch (e) {
                showMessage('Payment could not be started. Please try again.');
            }
        })();
    </script>
</body>
</html>
