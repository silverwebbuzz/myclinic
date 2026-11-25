<?php
/**
 * /company/billing — plan & trial.
 *
 * @var array<string,mixed> $company
 * @var array{state:string,expired:bool,reason:string,days_left:int|null,ends_on:string|null} $plan
 * @var bool $canSeeBilling
 * @var int $trialDays
 * @var \App\Support\Locale\Locale $loc
 */
$pageTitle = 'Plan & trial';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = (string) ($company['status'] ?? 'pending');
$days = $plan['days_left'];
$ends = $plan['ends_on'] !== null ? $loc->date($plan['ends_on']) : null;
$mailto = 'mailto:hello@eclinicpro.com?subject=' . rawurlencode('Workforce Health plan for ' . ($company['legal_name'] ?? '') . ' (' . ($company['public_ref'] ?? '') . ')');

if ($status === 'pending') {
    $head = ['Free trial starts after approval', 'Once we approve your company you get ' . $trialDays . ' days free, with every feature.', 'bg-ntb text-nt', 'Under review'];
} elseif ($plan['expired']) {
    $head = [$plan['reason'] === 'trial' ? 'Your free trial has ended' : 'Your plan has ended',
        'It ended on ' . $ends . '. Your data is safe. Renew to carry on; nothing is deleted.', 'bg-erb text-er', 'Ended'];
} elseif ($plan['reason'] === 'trial') {
    $head = ['Free trial', $days . ' day' . ($days === 1 ? '' : 's') . ' left · ends ' . $ends . '.', $plan['state'] === 'expiring_soon' ? 'bg-wnb text-wn' : 'bg-inb text-in', 'Trial'];
} elseif ($plan['reason'] === 'plan') {
    $head = ['Paid plan', 'Paid until ' . $ends . ($days !== null && $days <= 30 ? ' (' . $days . ' day' . ($days === 1 ? '' : 's') . ' left)' : '') . '.', $plan['state'] === 'expiring_soon' ? 'bg-wnb text-wn' : 'bg-okb text-ok', 'Active'];
} else {
    $head = ['Active', 'Your account is active.', 'bg-okb text-ok', 'Active'];
}
ob_start();
?>
<h1 class="text-[20px] font-semibold tracking-[-.015em]">Plan &amp; trial</h1>

<section class="mt-4 rounded-[10px] border <?= $plan['expired'] ? 'border-er/30' : 'border-ln' ?> bg-sf p-6">
    <span class="inline-flex h-[26px] items-center rounded-full px-2.5 text-xs font-medium <?= $head[2] ?>"><?= $e($head[3]) ?></span>
    <h2 class="mt-3 text-lg font-semibold"><?= $e($head[0]) ?></h2>
    <p class="mt-1 text-tx2"><?= $e($head[1]) ?></p>

    <?php if ($canSeeBilling): ?>
        <?php if ($status !== 'pending'): ?>
            <div class="mt-5 rounded-[10px] bg-sf2 p-4">
                <div class="font-medium">How to <?= $plan['reason'] === 'plan' && !$plan['expired'] ? 'renew' : 'continue after the trial' ?></div>
                <p class="mt-1 text-tx2">Plans are priced by number of employees. Email us and we'll send a quote and an invoice; you can pay by bank transfer or card link. Online checkout is coming soon.</p>
                <a href="<?= $e($mailto) ?>" class="mt-3 inline-flex h-9 items-center rounded-[7px] bg-ac px-4 text-[13px] font-medium text-white hover:opacity-90">Email hello@eclinicpro.com</a>
            </div>
        <?php endif; ?>
        <dl class="mt-5 grid gap-x-8 gap-y-2 sm:grid-cols-2">
            <div class="flex justify-between gap-3"><dt class="text-tx3">Company reference</dt><dd><?= $e($company['public_ref'] ?? '') ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-tx3">Billing currency</dt><dd><?= $e($company['currency_code'] ?? '') ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-tx3">Trial ends</dt><dd><?= !empty($company['trial_ends_at']) ? $e($loc->date((string) $company['trial_ends_at'])) : '—' ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-tx3">Paid until</dt><dd><?= !empty($company['plan_expires_at']) ? $e($loc->date((string) $company['plan_expires_at'])) : '—' ?></dd></div>
        </dl>
    <?php elseif ($plan['expired']): ?>
        <p class="mt-4 rounded-[10px] bg-sf2 p-4 text-tx2">Please ask your company admin to renew the plan. Until then you can still sign out and manage your own sign-in security.</p>
    <?php endif; ?>
</section>

<section class="mt-4 rounded-[10px] border border-ln bg-sf p-5">
    <h2 class="text-[15px] font-semibold">Invoices</h2>
    <p class="mt-1 text-tx3">Your eClinicPro invoices will appear here.</p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
