<?php
// Registration progress bar: 1 Verify phone → 2 Enter code → 3 Your details
// → 4 Checkout → 5 Payment. Set $signupStep (1–5) before including.
$signupStep = (int) ($signupStep ?? 1);
$signupSteps = ['Verify phone', 'Enter code', 'Your details', 'Checkout', 'Payment'];
?>
<ol class="flex items-start text-[11px] text-slate-500" aria-label="Sign-up progress">
    <?php foreach ($signupSteps as $i => $label): ?>
        <?php $n = $i + 1; $done = $n < $signupStep; $current = $n === $signupStep; ?>
        <li class="flex flex-1 flex-col items-center gap-1 text-center <?= $done || $current ? 'text-emerald-700' : '' ?> <?= $current ? 'font-semibold' : '' ?>"
            <?= $current ? 'aria-current="step"' : '' ?>>
            <div class="flex w-full items-center">
                <span class="h-px flex-1 <?= $n === 1 ? 'bg-transparent' : ($done || $current ? 'bg-emerald-500' : 'bg-slate-200') ?>"></span>
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold <?= $done || $current ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500' ?> <?= $current ? 'ring-4 ring-emerald-100' : '' ?>">
                    <?= $done ? '✓' : $n ?>
                </span>
                <span class="h-px flex-1 <?= $n === count($signupSteps) ? 'bg-transparent' : ($done ? 'bg-emerald-500' : 'bg-slate-200') ?>"></span>
            </div>
            <span class="leading-tight"><?= htmlspecialchars($label) ?></span>
        </li>
    <?php endforeach; ?>
</ol>
