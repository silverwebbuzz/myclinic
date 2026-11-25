<?php
/** Sign-in / register shell for the company portal. Views set $pageTitle and $content (and optional $maxWidth). */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($pageTitle ?? 'Company portal') ?> · eClinicPro Workforce Health</title>
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body class="min-h-screen bg-bg font-sans text-tx">
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full <?= $e($maxWidth ?? 'max-w-md') ?>">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 grid h-10 w-10 place-items-center rounded-[10px] bg-ac text-lg font-bold text-white">e</div>
            <div class="text-[20px] font-semibold tracking-[-.015em]">eClinicPro Workforce Health</div>
            <div class="text-[13px] text-tx3">Company portal</div>
        </div>
        <?= $content ?? '' ?>
        <p class="mt-8 text-center text-xs text-tx3">
            <a href="https://eclinicpro.com/corporate" class="hover:underline">About Workforce Health</a> ·
            <a href="https://eclinicpro.com/terms" class="hover:underline">Terms</a> ·
            <a href="https://eclinicpro.com/privacy-policy" class="hover:underline">Privacy</a> ·
            <a href="mailto:help@eclinicpro.com" class="hover:underline">help@eclinicpro.com</a>
        </p>
    </div>
</div>
</body>
</html>
