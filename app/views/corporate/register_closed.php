<?php
/** /company/register while sign-ups are switched off (platform_settings.corp_signup_enabled = 0). */
$pageTitle = 'Registration closed';
ob_start();
?>
<div class="rounded-[10px] border border-ln bg-sf p-7 shadow-sm">
    <h1 class="text-xl font-semibold">Registration is paused</h1>
    <p class="mt-3 text-sm leading-relaxed text-tx2">We're not taking new company sign-ups online right now.
        To get started, email <a class="text-act underline" href="mailto:hello@eclinicpro.com">hello@eclinicpro.com</a> and our team will set up your account.</p>
    <a href="/company/login" class="mt-5 block w-full rounded-[7px] bg-ac py-2.5 text-center text-sm font-medium text-white hover:opacity-90">Back to sign in</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/_auth_layout.php';
