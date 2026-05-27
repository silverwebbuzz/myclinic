<?php
/**
 * Patient header partial — reusable across patient detail + visit screen.
 *
 * Required vars in scope:
 *   $patient   — patient row (id, name, uhid, age, gender, phone, photo_path,
 *                allergies, blood_group, etc.)
 *
 * Optional:
 *   $visitCount  — int, displayed beside ID if set
 *   $allergies   — list<string> decoded already (saves a call)
 *   $compact     — bool, renders the slim sticky variant
 */
$photoUrl = !empty($patient['photo_path']) ? '/' . ltrim((string) $patient['photo_path'], '/') : null;
$allergies = $allergies ?? [];
$compact = !empty($compact);
$initials = strtoupper(substr(trim((string) ($patient['name'] ?? '')), 0, 1)) ?: '?';

$age = $patient['age'] ?? null;
$gender = match (strtoupper((string) ($patient['gender'] ?? ''))) {
    'M' => 'Male',
    'F' => 'Female',
    default => '',
};
$phone = (string) ($patient['phone'] ?? '');
$visitCount = $visitCount ?? null;
?>
<div class="<?= $compact ? 'pt-2 pb-3' : 'p-5' ?> flex flex-wrap items-center gap-4 rounded-xl border bg-white">
    <?php if ($photoUrl): ?>
        <img src="<?= htmlspecialchars($photoUrl) ?>" alt=""
             class="<?= $compact ? 'h-10 w-10' : 'h-14 w-14' ?> rounded-full object-cover">
    <?php else: ?>
        <span class="flex <?= $compact ? 'h-10 w-10 text-base' : 'h-14 w-14 text-xl' ?>
                     items-center justify-center rounded-full bg-emerald-100 font-bold text-emerald-700">
            <?= htmlspecialchars($initials) ?>
        </span>
    <?php endif; ?>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-2">
            <h2 class="<?= $compact ? 'text-base' : 'text-lg' ?> font-semibold text-slate-900 truncate">
                <?= htmlspecialchars((string) ($patient['name'] ?? 'Patient')) ?>
            </h2>
            <?php if ($age !== null && $age !== ''): ?>
                <span class="text-sm text-slate-500"><?= (int) $age ?> yrs</span>
            <?php endif; ?>
            <?php if ($gender): ?>
                <span class="text-sm text-slate-500"><?= htmlspecialchars($gender) ?></span>
            <?php endif; ?>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">
            <span>ID <?= htmlspecialchars((string) ($patient['uhid'] ?? $patient['id'] ?? '')) ?></span>
            <?php if ($phone): ?>
                <span class="mx-1.5">·</span>
                <a href="tel:<?= htmlspecialchars($phone) ?>" class="text-emerald-700 hover:underline"><?= htmlspecialchars($phone) ?></a>
            <?php endif; ?>
            <?php if ($visitCount !== null): ?>
                <span class="mx-1.5">·</span>
                <span><?= (int) $visitCount ?> visit<?= (int) $visitCount === 1 ? '' : 's' ?></span>
            <?php endif; ?>
            <?php if (!empty($patient['blood_group'])): ?>
                <span class="mx-1.5">·</span>
                <span class="text-rose-700"><?= htmlspecialchars((string) $patient['blood_group']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (!empty($allergies)): ?>
            <p class="mt-1 text-xs text-rose-700">
                ⚠ Allergies: <?= htmlspecialchars(implode(', ', $allergies)) ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (!$compact): ?>
        <div class="ml-auto flex items-center gap-2">
            <a href="/patients/<?= (int) $patient['id'] ?>"
               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Patient profile
            </a>
        </div>
    <?php endif; ?>
</div>
