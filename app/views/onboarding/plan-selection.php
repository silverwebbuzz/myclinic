<?php
$title = 'Choose your plan — ManageClinic';
ob_start();
$plans = $plans ?? [];
$yearly = $yearly ?? false;
?>
<?php if (!empty($error)): ?>
<div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($cancelled)): ?>
<div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Payment was cancelled. You can try again or continue with Free.</div>
<?php endif; ?>
<?php if (!empty($simulated)): ?>
<div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">Plan activated (dev simulation). Continue setting up your clinic.</div>
<?php endif; ?>

<div class="mb-6 text-center">
    <h1 class="text-2xl font-semibold text-slate-900">Choose your plan</h1>
    <p class="mt-1 text-sm text-slate-500">Start free or try Clinic features for 14 days</p>
</div>

<div class="mb-6 flex justify-center" x-data="{ yearly: <?= $yearly ? 'true' : 'false' ?> }">
    <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm">
        <a href="?cycle=monthly" class="rounded-md px-4 py-1.5 <?= !$yearly ? 'bg-emerald-600 text-white' : 'text-slate-600' ?>">Monthly</a>
        <a href="?cycle=yearly" class="rounded-md px-4 py-1.5 <?= $yearly ? 'bg-emerald-600 text-white' : 'text-slate-600' ?>">Yearly <span class="text-xs opacity-80">-20%</span></a>
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($plans as $planId => $plan): ?>
    <form method="post" action="/onboarding/plan-selection" class="flex flex-col rounded-xl border <?= !empty($plan['featured']) ? 'border-emerald-500 ring-2 ring-emerald-500' : 'border-slate-200' ?> bg-white p-5 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="plan" value="<?= htmlspecialchars($planId) ?>">
        <input type="hidden" name="billing_cycle" value="<?= $yearly ? 'yearly' : 'monthly' ?>">

        <?php if (!empty($plan['featured'])): ?>
        <span class="mb-2 inline-block w-fit rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700">Recommended</span>
        <?php endif; ?>

        <h2 class="text-lg font-semibold"><?= htmlspecialchars($plan['name']) ?></h2>
        <p class="text-xs text-slate-500"><?= htmlspecialchars($plan['tagline']) ?></p>

        <div class="my-4">
            <?php if ($planId === 'free'): ?>
            <span class="text-3xl font-bold">$0</span>
            <span class="text-sm text-slate-500">/forever</span>
            <?php else: ?>
            <span class="text-3xl font-bold">$<?= $yearly ? (int) $plan['yearly_usd'] : (int) $plan['monthly_usd'] ?></span>
            <span class="text-sm text-slate-500">/<?= $yearly ? 'mo billed yearly' : 'mo' ?></span>
            <?php endif; ?>
        </div>

        <ul class="mb-4 flex-1 space-y-1.5 text-xs text-slate-600">
            <?php foreach ($plan['highlights'] as $h): ?>
            <li class="flex gap-1"><span class="text-emerald-500">✓</span> <?= htmlspecialchars($h) ?></li>
            <?php endforeach; ?>
            <?php foreach ($plan['limits'] ?? [] as $l): ?>
            <li class="flex gap-1 text-slate-400"><span>✗</span> <?= htmlspecialchars($l) ?></li>
            <?php endforeach; ?>
        </ul>

        <button type="submit" class="w-full rounded-lg py-2 text-sm font-medium <?= !empty($plan['featured']) ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-300 text-slate-700 hover:bg-slate-50' ?>">
            <?= $planId === 'free' ? 'Continue with Free' : 'Start ' . htmlspecialchars($plan['name']) . ' — 14-day trial' ?>
        </button>
    </form>
    <?php endforeach; ?>
</div>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/_layout.php';
