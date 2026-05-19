<?php
$tabLabels = [
    'general' => 'General',
    'hours' => 'Working hours',
    'specialty' => 'Specialty',
    'leaves' => 'Doctor leaves',
    'notifications' => 'Notifications',
    'subscription' => 'Subscription',
    'team' => 'Team',
    'api' => 'API',
    'branding' => 'White-label',
    'consent-forms' => 'Consent forms',
];
?>
<?php if (!empty($message)): ?>
<p class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Settings saved.</p>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($_GET['error']) ?></p>
<?php endif; ?>

<div class="flex flex-col gap-6 lg:flex-row">
    <nav class="flex flex-wrap gap-1 lg:w-48 lg:flex-col">
        <?php foreach ($tabs as $t): ?>
        <a href="/settings?tab=<?= urlencode($t) ?>"
           class="rounded-lg px-3 py-2 text-sm <?= $tab === $t ? 'bg-emerald-50 font-medium text-emerald-800' : 'text-slate-600 hover:bg-slate-100' ?>">
            <?= htmlspecialchars($tabLabels[$t] ?? $t) ?>
        </a>
        <?php endforeach; ?>
        <a href="/settings/password" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">Password</a>
        <a href="/settings/sessions" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">Sessions</a>
    </nav>
    <div class="min-w-0 flex-1">
        <?= $tabContent ?? '' ?>
    </div>
</div>
