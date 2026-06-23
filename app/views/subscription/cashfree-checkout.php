<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to payment…</title>
    <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f8fafc; color: #475569; }
        .box { text-align: center; max-width: 24rem; padding: 2rem; }
        .spinner { width: 2rem; height: 2rem; border: 3px solid #e2e8f0; border-top-color: #0f766e; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #0f766e; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner" id="spinner"></div>
        <p id="status">Opening secure payment…</p>
        <p id="error" style="display:none;color:#b91c1c;font-size:0.875rem;"></p>
        <p id="retry" style="display:none;margin-top:1rem;">
            <a href="<?= htmlspecialchars($cancel_url ?? '/settings?tab=subscription') ?>">Go back</a>
        </p>
    </div>
    <script>
        (function () {
            const sessionId = <?= json_encode($payment_session_id, JSON_THROW_ON_ERROR) ?>;
            const mode = <?= json_encode($mode, JSON_THROW_ON_ERROR) ?>;
            const statusEl = document.getElementById('status');
            const errorEl = document.getElementById('error');
            const retryEl = document.getElementById('retry');
            const spinnerEl = document.getElementById('spinner');

            function showError(msg) {
                spinnerEl.style.display = 'none';
                statusEl.style.display = 'none';
                errorEl.textContent = msg;
                errorEl.style.display = 'block';
                retryEl.style.display = 'block';
            }

            try {
                const cashfree = Cashfree({ mode: mode });
                const checkout = cashfree.checkout({
                    paymentSessionId: sessionId,
                    redirectTarget: '_self',
                });
                if (checkout && typeof checkout.then === 'function') {
                    checkout.then(function (result) {
                        if (result && result.error) {
                            showError(result.error.message || 'Payment could not be started. Please try again.');
                        }
                    }).catch(function () {
                        showError('Payment could not be started. Please try again.');
                    });
                }
            } catch (e) {
                showError('Payment could not be started. Please try again.');
            }
        })();
    </script>
</body>
</html>
