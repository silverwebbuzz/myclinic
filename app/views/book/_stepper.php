<?php
$currentStep = $isConfirmed ? 3 : 1; // default for confirmed page; step 1/2 is governed by Alpine in the wizard
?>
<div class="flex items-center justify-center gap-2 text-xs sm:gap-4 sm:text-sm">
    <?php
    $steps = [
        ['n' => 1, 'label' => 'Date & Slot'],
        ['n' => 2, 'label' => 'Your Details'],
        ['n' => 3, 'label' => 'Confirmed'],
    ];
    foreach ($steps as $i => $s):
        if ($isConfirmed) {
            $state = 'done';
        } elseif ($s['n'] === 1) {
            $state = 'current-or-done'; // can't tell from PHP; styled with Alpine below
        } else {
            $state = 'pending';
        }
    ?>
    <div class="flex items-center gap-2">
        <?php if (!$isConfirmed && $s['n'] <= 2): ?>
        <span :class="step >= <?= $s['n'] ?> ? 'bg-brand text-white' : 'bg-slate-200 text-slate-500'"
              class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition"><?= $s['n'] ?></span>
        <span :class="step >= <?= $s['n'] ?> ? 'text-brand font-semibold' : 'text-slate-500'"
              class="hidden sm:inline"><?= htmlspecialchars($s['label']) ?></span>
        <?php elseif (!$isConfirmed && $s['n'] === 3): ?>
        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-500"><?= $s['n'] ?></span>
        <span class="hidden text-slate-500 sm:inline"><?= htmlspecialchars($s['label']) ?></span>
        <?php else: ?>
        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand text-xs font-bold text-white"><?= $s['n'] ?></span>
        <span class="hidden font-semibold text-brand sm:inline"><?= htmlspecialchars($s['label']) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($i < count($steps) - 1): ?>
    <div class="h-px w-6 bg-slate-300 sm:w-12"></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
