<?php
$enabled = !empty($enabled);
$siteKey = $siteKey ?? '';
$secretKey = $secretKey ?? '';
$message = $message ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>reCAPTCHA — Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100">
    <?php require __DIR__ . '/_nav.php'; ?>
    <main class="mx-auto max-w-4xl p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Google reCAPTCHA v2</h1>
            <span class="rounded-full px-3 py-1 text-xs font-semibold <?= !empty($enabled) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>">
                <?= !empty($enabled) ? 'ENABLED' : 'OFF' ?>
            </span>
        </div>

        <?php if (!empty($message)): ?>
            <div class="rounded border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                Settings saved.
            </div>
        <?php endif; ?>

        <section class="rounded-xl border bg-white p-5">
            <p class="mb-4 text-sm text-slate-600">
                Controls CAPTCHA on register, login, forgot username, forgot password, and reset password pages.
            </p>
            <form method="post" action="/admin/recaptcha" class="grid gap-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrf) ?>">

                <label class="flex items-center gap-2 text-sm">
                    <input class="ui-checkbox" type="checkbox" name="recaptcha_enabled" value="1" <?= !empty($enabled) ? 'checked' : '' ?>>
                    <span class="font-medium">Enable Google reCAPTCHA</span>
                </label>

                <label class="block text-sm">
                    <span class="text-slate-700">Site Key</span>
                    <input type="text" name="recaptcha_site_key" value="<?= htmlspecialchars((string) $siteKey) ?>"
                           class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
                </label>

                <label class="block text-sm">
                    <span class="text-slate-700">Secret Key</span>
                    <input type="text" name="recaptcha_secret_key" value="<?= htmlspecialchars((string) $secretKey) ?>"
                           class="mt-1 w-full rounded border border-slate-300 px-3 py-2" autocomplete="off">
                </label>

                <div>
                    <button type="submit" class="rounded bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">
                        Save reCAPTCHA settings
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
