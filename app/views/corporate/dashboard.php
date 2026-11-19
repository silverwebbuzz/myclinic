<?php
/**
 * /company/dashboard — account status, trial and setup checklist.
 *
 * @var array<string,mixed> $company
 * @var array<string,mixed> $user
 * @var \App\Support\Locale\Locale $loc
 * @var array<string,mixed>|null $country
 * @var list<string> $roles
 * @var array<string,mixed> $plan  SubscriptionStatus::forClinic() result
 * @var int|null $trialDaysLeft
 * @var list<array{0:string,1:string,2:bool,3:?string}> $checklist  href null = "Coming soon", '' = no button
 */
$pageTitle = 'Dashboard';
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$firstName = explode(' ', trim((string) ($user['name'] ?? '')))[0] ?: 'there';
$status = (string) ($company['status'] ?? 'pending');
$doneCount = count(array_filter($checklist, static fn ($i) => $i[2]));

if ($status === 'pending') {
    $planLine = ['Trial starts after approval', (int) \App\Services\Corporate\CompanyService::setting('corp_trial_days', '30') . '-day free trial', 'bg-ntb text-nt'];
} elseif (!empty($company['plan_expires_at'])) {
    $planLine = ['Plan active', 'Until ' . $loc->date((string) $company['plan_expires_at']), ($plan['state'] ?? '') === 'expiring_soon' ? 'bg-wnb text-wn' : 'bg-okb text-ok'];
} elseif ($trialDaysLeft !== null) {
    $planLine = $trialDaysLeft >= 0
        ? ['Free trial', $trialDaysLeft . ' day' . ($trialDaysLeft === 1 ? '' : 's') . ' left · ends ' . $loc->date((string) $company['trial_ends_at']), $trialDaysLeft <= 5 ? 'bg-wnb text-wn' : 'bg-inb text-in']
        : ['Trial ended', 'Ended ' . $loc->date((string) $company['trial_ends_at']), 'bg-erb text-er'];
} else {
    $planLine = ['Active', '', 'bg-okb text-ok'];
}
ob_start();
?>
<div class="flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-[20px] font-semibold tracking-[-.015em]"><?= !empty($welcome) ? 'Welcome, ' : 'Hello, ' ?><?= $e($firstName) ?></h1>
        <p class="mt-0.5 text-tx3"><?= $e($company['legal_name'] ?? '') ?> · <?= $e($company['public_ref'] ?? '') ?></p>
    </div>
</div>

<?php if (empty($user['email_verified_at'])): ?>
    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-[10px] border border-in/20 bg-inb px-4 py-3 text-in">
        <span class="flex-1"><strong>Please confirm your email.</strong> We sent a link to <?= $e($user['email'] ?? '') ?>.</span>
        <form method="post" action="/company/verify-email/resend">
            <input type="hidden" name="_csrf" value="<?= $e($csrf) ?>">
            <button class="h-8 rounded-[7px] border border-in/30 bg-sf px-3 text-[13px] font-medium text-in hover:bg-sf2">Resend link</button>
        </form>
    </div>
<?php endif; ?>

<?php if (($screening ?? null) !== null && $screening['people'] > 0): ?>
    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <a href="/company/screening?bucket=overdue" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">Checks overdue</div><div class="mt-0.5 text-[22px] font-semibold <?= $screening['overdue'] > 0 ? 'text-er' : 'text-tx2' ?>"><?= number_format((int) $screening['overdue']) ?></div></a>
        <a href="/company/screening?bucket=soon" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">Due in 30 days</div><div class="mt-0.5 text-[22px] font-semibold text-wn"><?= number_format((int) $screening['soon']) ?></div></a>
        <a href="/company/screening?bucket=done" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">Done in last 12 months</div><div class="mt-0.5 text-[22px] font-semibold text-ok"><?= number_format((int) $screening['done_12m']) ?></div></a>
        <a href="/company/screening" class="rounded-[10px] border border-ln bg-sf px-4 py-3 hover:bg-sf2"><div class="text-[12px] text-tx3">People up to date</div><div class="mt-0.5 text-[22px] font-semibold"><?= (int) $screening['up_to_date_pct'] ?>%</div><div class="text-[12px] text-tx3"><?= number_format((int) $screening['people_ok']) ?> of <?= number_format((int) $screening['people']) ?></div></a>
    </div>
<?php endif; ?>

<div class="mt-4 grid gap-4 lg:grid-cols-3">
    <section class="rounded-[10px] border border-ln bg-sf p-5 lg:col-span-2">
        <div class="flex items-center justify-between">
            <h2 class="text-[15px] font-semibold">Get set up</h2>
            <span class="text-tx3"><?= $doneCount ?> of <?= count($checklist) ?> done</span>
        </div>
        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-ln2">
            <div class="h-full rounded-full bg-ac" style="width: <?= (int) round($doneCount / max(1, count($checklist)) * 100) ?>%"></div>
        </div>
        <ul class="mt-4 divide-y divide-ln2">
            <?php foreach ($checklist as [$label, $hint, $done, $href]): ?>
                <li class="flex items-start gap-3 py-3">
                    <span class="mt-0.5 grid h-5 w-5 flex-none place-items-center rounded-full text-[11px] font-bold <?= $done ? 'bg-ok text-white' : 'border border-ln text-tx3' ?>"><?= $done ? '✓' : '' ?></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-medium <?= $done ? 'text-tx3 line-through' : '' ?>"><?= $e($label) ?></div>
                        <div class="text-tx3"><?= $e($hint) ?></div>
                    </div>
                    <?php if (!$done): ?>
                        <?php if ($href === ''): ?>
                        <?php elseif ($href !== null): ?>
                            <a href="<?= $e($href) ?>" class="h-8 flex-none rounded-[7px] border border-ln px-3 leading-8 font-medium hover:bg-sf2">Start</a>
                        <?php else: ?>
                            <span class="flex-none rounded px-1.5 py-px text-[11px] font-medium text-tx3 ring-1 ring-ln">Coming soon</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <div class="flex flex-col gap-4">
        <section class="rounded-[10px] border border-ln bg-sf p-5">
            <h2 class="text-[15px] font-semibold">Plan</h2>
            <span class="mt-3 inline-flex h-[26px] items-center rounded-full px-2.5 text-xs font-medium <?= $planLine[2] ?>"><?= $e($planLine[0]) ?></span>
            <?php if ($planLine[1] !== ''): ?><p class="mt-2 text-tx2"><?= $e($planLine[1]) ?></p><?php endif; ?>
            <p class="mt-3 text-tx3">Questions about plans? Email <a href="mailto:hello@eclinicpro.com" class="text-act hover:underline">hello@eclinicpro.com</a>.</p>
        </section>

        <section class="rounded-[10px] border border-ln bg-sf p-5">
            <h2 class="text-[15px] font-semibold">Your account</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-tx3">Role</dt><dd class="text-right"><?= $e(implode(', ', $roles) ?: '—') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-tx3">Country</dt><dd class="text-right"><?= $e($country['name'] ?? ($company['country_code'] ?? '')) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-tx3">Time zone</dt><dd class="text-right"><?= $e($loc->timezone) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-tx3">Currency</dt><dd class="text-right"><?= $e($loc->currency) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-tx3">Registered</dt><dd class="text-right"><?= $e($loc->dateTime((string) ($company['created_at'] ?? ''))) ?></dd></div>
            </dl>
        </section>
    </div>
</div>

<section class="mt-4 rounded-[10px] border border-dashed border-ln bg-sf p-5">
    <h2 class="text-[15px] font-semibold">Coming next</h2>
    <p class="mt-1 text-tx2"><?= $status === 'pending' ? 'Once your company is approved you\'ll be able to set up your workforce and screening programmes. Next, you\'ll' : 'Soon you\'ll be able to' ?>
        book examinations with partner clinics and labs, and receive fitness certificates (the outcome only; medical results stay with the doctor).</p>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/_layout.php';
