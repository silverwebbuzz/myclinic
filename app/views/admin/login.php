<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Super Admin — eClinicPro</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Same console look as the seller login (store_vendor/_auth_layout.php). */
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; font-size: 13px; font-feature-settings: 'tnum' 1, 'cv11' 1; -webkit-font-smoothing: antialiased; }
    </style>
</head>
<body class="min-h-screen bg-[#f5f6f8] text-[#0f172a]">
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 grid h-10 w-10 place-items-center rounded-[10px] bg-[#059669] text-lg font-bold text-white">e</div>
            <div class="text-[20px] font-semibold tracking-[-.015em]">eClinicPro</div>
            <div class="text-[13px] text-[#64748b]">Super admin console</div>
        </div>
        <form method="post" action="/admin/login" class="space-y-4 rounded-[10px] border border-[#e4e7ec] bg-white p-7 shadow-sm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '') ?>">
            <h1 class="text-xl font-semibold">Platform admin sign in</h1>
            <?php if (!empty($error)): ?>
            <div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <label class="block text-sm">
                <span class="text-[#475569]">Email</span>
                <input name="email" type="email" required autocomplete="email"
                       class="mt-1 w-full rounded-[7px] border border-[#e4e7ec] bg-white px-3 py-2 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-[#059669]/15">
            </label>
            <label class="block text-sm">
                <span class="text-[#475569]">Password</span>
                <input name="password" type="password" required autocomplete="current-password"
                       class="mt-1 w-full rounded-[7px] border border-[#e4e7ec] bg-white px-3 py-2 focus:border-[#059669] focus:outline-none focus:ring-2 focus:ring-[#059669]/15">
            </label>
            <?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
            <div class="overflow-hidden">
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars((string) $captchaSiteKey) ?>"></div>
            </div>
            <?php endif; ?>
            <button type="submit" class="w-full rounded-[7px] bg-[#059669] py-2.5 text-sm font-medium text-white hover:opacity-90">Sign in</button>
        </form>
    </div>
</div>
<?php if (!empty($captchaEnabled) && !empty($captchaSiteKey)): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
</body>
</html>
