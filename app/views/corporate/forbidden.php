<?php
/** 403 inside the company portal. */
$pageTitle = 'No access';
ob_start();
?>
<div class="mx-auto mt-10 max-w-md rounded-[10px] border border-ln bg-sf p-7 text-center">
    <div class="mx-auto grid h-10 w-10 place-items-center rounded-full bg-wnb text-lg font-bold text-wn">!</div>
    <h1 class="mt-3 text-lg font-semibold">You don't have access to this page</h1>
    <p class="mt-2 text-tx2">Your role doesn't include it. If you need it for your work, ask your company admin to change your role.</p>
    <a href="/company/dashboard" class="mt-5 inline-flex h-9 items-center rounded-[7px] bg-ac px-4 text-[13px] font-medium text-white hover:opacity-90">Back to dashboard</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
