<?php
/**
 * /company/mfa/setup — scan the QR code with an authenticator app, then type a code.
 * The QR code is drawn IN THE BROWSER from the otpauth URI (self-hosted
 * qrcode-generator) — the secret is never sent to a third-party QR service.
 *
 * @var bool $configured  false when the server has no encryption key
 * @var string $secretGrouped
 * @var string $otpauth
 * @var bool $required
 */
$pageTitle = 'Set up two-step sign-in';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Set up two-step sign-in</h1>
    <?php if (empty($configured)): ?>
        <p class="mt-3 text-sm text-tx2">Two-step sign-in isn't available on this server yet. Please contact
            <a class="text-act underline" href="mailto:help@eclinicpro.com">help@eclinicpro.com</a>.</p>
    <?php else: ?>
        <p class="mt-1 text-sm text-tx2">
            <?= !empty($required) ? 'To protect your workforce\'s health records, every company account signs in with a password <strong>and</strong> a code from an app on your phone.' : 'Add a code from an app on your phone to your password.' ?>
        </p>
        <?php if (!empty($error)): ?>
            <div class="mt-4 rounded-lg bg-erb px-3 py-2 text-sm text-er"><?= $e($error) ?></div>
        <?php endif; ?>

        <ol class="mt-5 space-y-5 text-sm">
            <li>
                <div class="font-medium">1. Install an authenticator app</div>
                <p class="mt-0.5 text-tx3">Google Authenticator, Microsoft Authenticator or any app that supports "TOTP" codes.</p>
            </li>
            <li>
                <div class="font-medium">2. Scan this QR code with the app</div>
                <div class="mt-3 flex flex-col items-center gap-3 rounded-[10px] border border-ln bg-sf2 p-4 sm:flex-row sm:items-start">
                    <div id="mfa-qr" class="grid h-[184px] w-[184px] flex-none place-items-center rounded-lg bg-white p-2 ring-1 ring-ln" data-uri="<?= $e($otpauth) ?>">
                        <span class="text-xs text-tx3">Loading QR code…</span>
                    </div>
                    <div class="min-w-0 text-tx3">
                        <p>Can't scan it? In the app choose <em>Enter a setup key</em> and type:</p>
                        <p class="mt-2 select-all break-words rounded-md bg-sf px-2.5 py-2 font-mono text-[13px] tracking-wide text-tx ring-1 ring-ln"><?= $e($secretGrouped) ?></p>
                        <p class="mt-2">Account: <span class="text-tx"><?= $e($email) ?></span> · Type: time-based</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="font-medium">3. Enter the 6-digit code the app shows</div>
                <form method="post" action="/company/mfa/setup" class="mt-2 flex flex-col gap-3 sm:flex-row">
                    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="next" value="<?= $e($next ?? '') ?>">
                    <input name="code" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456"
                           data-msg="Enter the 6 digits from your app"
                           class="w-full rounded-[7px] border border-ln bg-sf px-3 py-2 text-center font-mono text-lg tracking-[.3em] focus:border-ac focus:outline-none focus:ring-2 focus:ring-ac/15 sm:w-44">
                    <button class="rounded-[7px] bg-ac px-5 py-2.5 text-sm font-medium text-white hover:opacity-90">Turn on</button>
                </form>
            </li>
        </ol>
    <?php endif; ?>
</div>
<form method="post" action="/company/logout" class="mt-4 text-center text-sm text-tx2">
    <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
    Not now? <button class="font-medium text-act hover:underline">Sign out</button>
</form>
<script src="/assets/qrcode-generator-1.4.4.js"></script>
<script>
(function () {
    var box = document.getElementById('mfa-qr');
    if (!box || typeof qrcode !== 'function') return;
    var qr = qrcode(0, 'M');
    qr.addData(box.getAttribute('data-uri'));
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
    var svg = box.querySelector('svg');
    if (svg) { svg.setAttribute('width', '168'); svg.setAttribute('height', '168'); svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', 'QR code for your authenticator app'); }
})();
</script>
<?php
$content = ob_get_clean();
$maxWidth = 'max-w-xl';
require __DIR__ . '/_auth_layout.php';
