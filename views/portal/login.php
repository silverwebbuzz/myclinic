<div class="space-y-4">
    <h1 class="text-xl font-semibold">Patient login</h1>
    <p class="text-sm text-slate-500">Sign in with the mobile number on file at <?= htmlspecialchars($clinic['name'] ?? 'the clinic') ?>.</p>

    <?php if (!empty($message)): ?>
    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php if (!empty($_GET['otp_sent'])): ?>
    <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">OTP sent. Check your phone.</p>
    <?php endif; ?>
    <?php if (!empty($dev_otp)): ?>
    <p class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-mono">Dev OTP: <?= htmlspecialchars($dev_otp) ?></p>
    <?php endif; ?>

    <form method="post" action="/portal/login/send-otp" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="block text-sm">Phone
            <input name="phone" type="tel" required value="<?= htmlspecialchars($_GET['phone'] ?? '') ?>" class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>
        <button type="submit" class="w-full rounded-lg bg-emerald-600 py-2 text-white font-medium">Send OTP</button>
    </form>

    <form method="post" action="/portal/login/verify" class="space-y-3 border-t pt-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <label class="block text-sm">Phone
            <input name="phone" type="tel" required class="mt-1 w-full rounded-lg border px-3 py-2">
        </label>
        <label class="block text-sm">OTP code
            <input name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required class="mt-1 w-full rounded-lg border px-3 py-2 font-mono">
        </label>
        <button type="submit" class="w-full rounded-lg border border-emerald-600 py-2 text-emerald-700 font-medium">Verify &amp; sign in</button>
    </form>
</div>
