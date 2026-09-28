<?php
/** Login / register shell. Views set $pageTitle and $content. */
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $e($pageTitle ?? 'Sellers') ?> — eClinicPro Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-[#faf7f1] text-[#13294b]">
<div class="flex min-h-screen items-center justify-center px-4 py-10">
    <div class="w-full <?= $e($maxWidth ?? 'max-w-md') ?>">
        <div class="mb-6 text-center">
            <div class="text-2xl font-semibold tracking-tight">eClinic<span class="text-[#17774f]">Pro</span> Store</div>
            <div class="text-sm text-slate-500">Seller portal</div>
        </div>
        <?= $content ?? '' ?>
    </div>
</div>
</body>
</html>
