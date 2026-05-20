<?php
$title = 'You\'re all set! — ManageClinic';
$specLabel = $specialties[$clinic['specialty'] ?? 'gp']['label'] ?? 'General Practice';
ob_start();
?>
<div class="text-center" x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)">
    <div x-show="show" x-transition class="pointer-events-none fixed inset-0 flex items-center justify-center overflow-hidden">
        <div class="text-6xl animate-bounce">🎉</div>
    </div>

    <h1 class="text-3xl font-semibold text-slate-900">Your clinic is ready!</h1>
    <p class="mt-2 text-slate-500">Here's what you configured</p>

    <div class="mx-auto mt-8 max-w-md rounded-xl border border-slate-200 bg-white p-6 text-left text-sm">
        <ul class="space-y-2">
            <li class="flex justify-between"><span class="text-slate-500">Specialty</span><span class="font-medium"><?= htmlspecialchars($specLabel) ?></span></li>
            <li class="flex justify-between"><span class="text-slate-500">Plan</span><span class="font-medium"><?= htmlspecialchars($plan['name'] ?? 'Free') ?></span></li>
            <li class="flex justify-between"><span class="text-slate-500">Seats</span><span class="font-medium"><?= (int) ($clinic['seat_limit'] ?? 2) ?> included</span></li>
            <li class="flex justify-between"><span class="text-slate-500">Patient ID</span><span class="font-medium"><?= htmlspecialchars($config['uhid_prefix'] ?? 'MC') ?>-00001</span></li>
            <?php if (!empty($clinic['trial_ends_at'])): ?>
            <li class="flex justify-between"><span class="text-slate-500">Trial ends</span><span class="font-medium"><?= htmlspecialchars($clinic['trial_ends_at']) ?></span></li>
            <?php endif; ?>
        </ul>
    </div>

    <form method="post" action="/onboarding/complete" class="mt-8">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="rounded-lg bg-emerald-600 px-8 py-3 text-sm font-medium text-white hover:bg-emerald-700">
            Go to Dashboard →
        </button>
    </form>

    <div class="mt-10 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-left">
        <p class="text-xs font-medium text-slate-600 mb-2">Getting started checklist</p>
        <ul class="space-y-1 text-xs text-slate-500">
            <li class="text-emerald-600">✓ Clinic setup done</li>
            <li>☐ Add first patient</li>
            <li>☐ Book first appointment</li>
            <li>☐ Add payment method</li>
            <li>☐ Invite a team member</li>
        </ul>
    </div>
</div>
<?php
$innerContent = ob_get_clean();
require __DIR__ . '/_layout.php';
