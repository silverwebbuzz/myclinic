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
<div class="mb-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">Settings saved.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<?= ui_page_header('Settings', 'Personalize your clinic and manage preferences securely.') ?>

<!-- Two-column card grid (reference 2: Medicare). No sub-menu — every
     section is a card laid out side by side. CSS columns let cards of
     different heights pack naturally; each card stays unbroken. -->
<div class="settings-grid">
    <?php foreach ($tabs as $t): ?>
    <div class="settings-grid-item">
        <?= $sections[$t] ?? '' ?>
    </div>
    <?php endforeach; ?>
</div>

<style>
    .settings-grid { column-gap: 1.5rem; }
    .settings-grid-item { break-inside: avoid; margin-bottom: 1.5rem; }
    @media (min-width: 1024px) { .settings-grid { column-count: 2; } }
</style>
