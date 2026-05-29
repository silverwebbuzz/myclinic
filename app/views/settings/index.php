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

<div class="flex flex-col gap-8 lg:flex-row" x-data="{ active: '<?= htmlspecialchars($tab) ?>' }">
    <!-- Sticky in-page jump nav -->
    <nav class="lg:sticky lg:top-20 lg:h-max lg:w-56 lg:self-start">
        <div class="flex flex-wrap gap-1 lg:flex-col">
            <?php
            $allTabs = $tabs;
            $allTabs[] = 'password';
            $allTabs[] = 'sessions';
            $tabLabels['password'] = 'Password';
            $tabLabels['sessions'] = 'Sessions';
            foreach ($allTabs as $t):
                $isAnchor = !in_array($t, ['password', 'sessions'], true);
                $href = $isAnchor ? '#sec-' . $t : '/settings/' . $t;
            ?>
            <a href="<?= htmlspecialchars($href) ?>"
               <?php if ($isAnchor): ?>@click="active = '<?= $t ?>'" :class="active === '<?= $t ?>' ? 'bg-brand-light font-semibold text-brand' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"<?php else: ?>class="text-slate-600 hover:bg-slate-100 hover:text-slate-900"<?php endif; ?>
               class="rounded-lg px-3.5 py-2.5 text-sm transition">
                <?= htmlspecialchars($tabLabels[$t] ?? $t) ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- All sections stacked -->
    <div class="min-w-0 flex-1 max-w-3xl space-y-6">
        <?php foreach ($tabs as $t): ?>
        <section id="sec-<?= htmlspecialchars($t) ?>" class="scroll-mt-24">
            <?= $sections[$t] ?? '' ?>
        </section>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Highlight the nav item for the section currently in view.
(function () {
    const root = document.querySelector('[x-data]');
    const sections = document.querySelectorAll('section[id^="sec-"]');
    if (!('IntersectionObserver' in window) || !sections.length) return;
    const obs = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting && root && root.__x) {
                root.__x.$data.active = e.target.id.replace('sec-', '');
            }
        });
    }, { rootMargin: '-20% 0px -70% 0px' });
    sections.forEach((s) => obs.observe(s));
})();
</script>
