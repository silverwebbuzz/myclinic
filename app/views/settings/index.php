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

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Settings</h1>
    <p class="mt-1 text-sm text-slate-500">Personalize your clinic and manage preferences securely.</p>
</div>

<div class="flex flex-col gap-8 lg:flex-row">
    <nav class="flex flex-wrap gap-1 lg:w-56 lg:flex-col">
        <?php
        $navLink = static function (string $href, string $label, bool $active): string {
            $cls = $active
                ? 'bg-brand-light font-semibold text-brand'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900';
            return '<a href="' . htmlspecialchars($href) . '" class="rounded-lg px-3.5 py-2.5 text-sm transition ' . $cls . '">'
                . htmlspecialchars($label) . '</a>';
        };
        foreach ($tabs as $t) {
            echo $navLink('/settings?tab=' . urlencode($t), $tabLabels[$t] ?? $t, $tab === $t);
        }
        echo $navLink('/settings/password', 'Password', false);
        echo $navLink('/settings/sessions', 'Sessions', false);
        ?>
    </nav>
    <div class="min-w-0 flex-1 max-w-3xl">
        <?= $tabContent ?? '' ?>
    </div>
</div>
