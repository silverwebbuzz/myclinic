<?php
/** @var array<string, mixed> $p */
$gradient = (int) ($p['avatar_gradient'] ?? 1);
$initials = (string) ($p['avatar_initials'] ?? 'DR');
$primaryImage = $p['photo_url'] ?? null;
$hoursLines = $hoursLines ?? ecp_profile_hours_lines($p['opening_hours'] ?? null);
$hoursRows = ecp_profile_hours_table_rows($p['opening_hours'] ?? null);
$showTimingsTab = $hoursRows !== [];
$showDoctorsTab = $showDoctorsTab ?? (($p['entity_type'] ?? '') === 'clinic');
$specFindUrl = !empty($p['specialty_url_slug']) && !empty($p['city_slug'])
    ? '/find-a-doctor/' . e($p['specialty_url_slug']) . '-in-' . e($p['city_slug'])
    : '/find-a-doctor/' . e($p['city_slug'] ?? '');

/**
 * Inline stroke-style SVG icons (currentColor) — replaces emoji so the page
 * looks consistent across OSes and matches the app's SVG icon language.
 */
if (!function_exists('dp_icon')) {
    function dp_icon(string $name): string
    {
        $paths = [
            'pin'      => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            'phone'    => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
            'stetho'   => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 12 0V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><circle cx="20" cy="10" r="2"/><path d="M8 15v1a6 6 0 0 0 6 6 6 6 0 0 0 6-6v-4"/>',
            'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
            'language' => '<path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1m14 20-5-10-5 10m2-4h6"/>',
            'rupee'    => '<path d="M6 3h12M6 8h12M6 13l8.5 8M6 13h3a5 5 0 0 0 0-10"/>',
            'compass'  => '<circle cx="12" cy="12" r="9"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
            'clinic'   => '<path d="M3 21h18M5 21V7l8-4 8 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/>',
            'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
            'star'     => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        ];
        $body = $paths[$name] ?? '';
        return '<svg class="dp-i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
    }
}

$ratingVal = (float) ($p['rating'] ?? 0);
?>
<main class="dp" x-data="{ tab: 'overview', heroBroken: false }">
    <div class="wrap-wide dp-wrap">
        <nav class="dp-crumbs" aria-label="Breadcrumb">
            <a href="/">Home</a><span>›</span>
            <a href="/find-a-doctor">Doctors</a><span>›</span>
            <a href="/find-a-doctor/<?= e($p['city_slug'] ?? '') ?>"><?= e($p['city'] ?? '') ?></a><span>›</span>
            <a href="<?= $specFindUrl ?>"><?= e($p['specialty_label'] ?? '') ?></a><span>›</span>
            <span class="current"><?= e($p['display_name'] ?? '') ?></span>
        </nav>
        <div class="dp-layout">
            <div class="dp-main">
                <section class="dp-hero">
                    <div class="dp-hero-media">
                        <?php if ($primaryImage): ?>
                            <img src="<?= e($primaryImage) ?>" alt="<?= e($p['display_name'] ?? '') ?>" class="dp-hero-img" loading="eager" x-show="!heroBroken" @error="heroBroken = true">
                        <?php endif; ?>
                        <div class="dp-hero-avatar g<?= $gradient ?>" x-show="<?= $primaryImage ? 'heroBroken' : 'true' ?>" <?= $primaryImage ? 'x-cloak' : '' ?>><img class="dp-hero-avatar-default" src="<?= e(ecp_default_doctor_avatar()) ?>" alt="<?= e($p['display_name'] ?? '') ?>"></div>
                    </div>
                    <div class="dp-hero-body">
                        <div class="dp-hero-top">
                            <span class="dp-entity-pill"><?= ($p['entity_type'] ?? '') === 'clinic' ? 'Clinic' : 'Doctor' ?></span>
                            <?php if (!empty($p['is_claimed'])): ?><span class="dp-verified"><?= dp_icon('check') ?>Verified</span><?php endif; ?>
                        </div>
                        <h1 class="dp-title"><?= e($p['display_name'] ?? '') ?></h1>
                        <p class="dp-spec"><?= e($p['specialty_label'] ?? '') ?></p>
                        <?php if ($ratingVal > 0): ?>
                            <div class="dp-rating">
                                <span class="dp-stars" style="--pct: <?= max(0, min(100, $ratingVal / 5 * 100)) ?>%" aria-hidden="true">★★★★★</span>
                                <span class="dp-rating-val"><?= number_format($ratingVal, 1) ?></span>
                                <span class="dp-rating-count"><?= number_format((int) ($p['reviews'] ?? 0)) ?> reviews</span>
                            </div>
                        <?php endif; ?>
                        <div class="dp-meta-list">
                            <?php if (!empty($p['address']) || !empty($p['area']) || !empty($p['city'])): ?>
                                <div class="dp-meta-row"><?= dp_icon('pin') ?><span><?= e(trim(($p['address'] ?? '') !== '' ? $p['address'] : implode(', ', array_filter([$p['area'] ?? '', $p['city'] ?? '', $p['state'] ?? ''])))) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($p['phone'])): ?>
                                <div class="dp-meta-row"><?= dp_icon('phone') ?><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $p['phone'])) ?>"><?= e($p['phone']) ?></a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <div class="dp-content-block">
                    <nav class="dp-tabs">
                        <button type="button" class="dp-tab" :class="tab === 'overview' ? 'on' : ''" @click="tab = 'overview'">Overview</button>
                        <?php if ($showTimingsTab): ?><button type="button" class="dp-tab" :class="tab === 'timings' ? 'on' : ''" @click="tab = 'timings'">Timings</button><?php endif; ?>
                        <button type="button" class="dp-tab" :class="tab === 'treatments' ? 'on' : ''" @click="tab = 'treatments'">Treatments</button>
                        <?php if ($showDoctorsTab): ?><button type="button" class="dp-tab" :class="tab === 'doctors' ? 'on' : ''" @click="tab = 'doctors'">Doctors</button><?php endif; ?>
                        <button type="button" class="dp-tab" :class="tab === 'photos' ? 'on' : ''" @click="tab = 'photos'">Photos</button>
                    </nav>
                    <div class="dp-panels">
                        <section class="dp-panel" x-show="tab === 'overview'" x-cloak>
                            <?php
                            $langList = array_values(array_filter(array_map('strval', (array) ($p['languages'] ?? []))));
                            $feeVal = (float) ($p['fee'] ?? 0);
                            $facts = [];
                            if (!empty($p['specialty_label'])) {
                                $facts[] = ['stetho', 'Specialty', e((string) $p['specialty_label'])];
                            }
                            if (!empty($p['city'])) {
                                $facts[] = ['pin', 'Location', e(trim(implode(', ', array_filter([$p['area'] ?? '', $p['city'] ?? '', $p['state'] ?? '']))))];
                            }
                            if ($langList) {
                                $facts[] = ['language', 'Languages', e(implode(', ', $langList))];
                            }
                            if ($feeVal > 0) {
                                $facts[] = ['rupee', 'Consultation fee', e((string) ($p['currency'] ?? '₹') . number_format($feeVal))];
                            }
                            if ($ratingVal > 0) {
                                $facts[] = ['star', 'Rating', '<span class="dp-stars dp-stars-sm" style="--pct: ' . (max(0, min(100, $ratingVal / 5 * 100))) . '%">★★★★★</span> ' . number_format($ratingVal, 1) . ' <span class="dp-fact-muted">(' . number_format((int) ($p['reviews'] ?? 0)) . ' reviews)</span>'];
                            }
                            if (!empty($p['website'])) {
                                $facts[] = ['globe', 'Website', '<a href="' . e((string) $p['website']) . '" target="_blank" rel="noopener nofollow">' . e(preg_replace('#^https?://#', '', (string) $p['website'])) . '</a>'];
                            }
                            ?>
                            <?php if ($facts): ?>
                            <dl class="dp-facts">
                                <?php foreach ($facts as [$icon, $label, $value]): ?>
                                <div class="dp-fact">
                                    <dt><span class="dp-fact-ico"><?= dp_icon($icon) ?></span><?= e($label) ?></dt>
                                    <dd><?= $value ?></dd>
                                </div>
                                <?php endforeach; ?>
                            </dl>
                            <?php endif; ?>
                            <h2>About</h2>
                            <p class="dp-about"><?= !empty($p['bio']) ? nl2br(e($p['bio'])) : e(($p['display_name'] ?? '') . ' is a ' . strtolower($p['specialty_label'] ?? 'doctor') . ' in ' . ($p['city'] ?? '') . '.') ?></p>
                        </section>
                        <?php if ($showTimingsTab): ?>
                            <section class="dp-panel" x-show="tab === 'timings'" x-cloak>
                                <div class="dp-hours-wrap">
                                    <table class="dp-hours-table" aria-label="Clinic timings">
                                        <thead>
                                            <tr>
                                                <th>Day</th>
                                                <th>Morning</th>
                                                <th>Evening</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hoursRows as $row): ?>
                                                <tr>
                                                    <td><?= e((string) $row['day']) ?></td>
                                                    <td><?= e((string) $row['morning']) ?></td>
                                                    <td><?= e((string) $row['evening']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        <?php endif; ?>
                        <section class="dp-panel" x-show="tab === 'treatments'" x-cloak>
                            <h2><?= !empty($p['treatments_custom']) ? 'Services offered' : 'Treatments &amp; services' ?></h2>
                            <ul class="dp-treatment-list"><?php foreach (($p['treatments'] ?? []) as $t): ?><li><?= e((string) $t) ?></li><?php endforeach; ?></ul>
                            <?php if (empty($p['treatments_custom'])): ?>
                            <p class="dp-note">Common treatments for <?= e(strtolower($p['specialty_label'] ?? 'this specialty')) ?>. The clinic will confirm services during your visit.</p>
                            <?php endif; ?>
                        </section>
                        <?php if ($showDoctorsTab): ?>
                            <section class="dp-panel" x-show="tab === 'doctors'" x-cloak>
                                <h2>Doctors</h2>
                                <div class="dp-doctor-grid">
                                    <?php foreach (($p['doctors'] ?? []) as $doc): ?>
                                        <a href="<?= e($doc['profile_url'] ?? '#') ?>" class="dp-doctor-card">
                                            <div class="dp-doctor-photo g<?= (int) ($doc['avatar_gradient'] ?? 1) ?>"><?= e($doc['avatar_initials'] ?? 'DR') ?></div>
                                            <div><div class="dp-doctor-name"><?= e($doc['name'] ?? '') ?></div><div class="dp-doctor-spec"><?= e($doc['spec_label'] ?? '') ?></div></div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                        <section class="dp-panel" x-show="tab === 'photos'" x-cloak>
                            <h2>Photos</h2>
                            <?php if (!empty($p['images'])): ?>
                                <div class="dp-gallery"><?php foreach ($p['images'] as $img): ?><figure class="dp-gallery-item"><img src="<?= e($img['url'] ?? '') ?>" alt="<?= e($img['alt'] ?? '') ?>" loading="lazy" onerror="this.closest('.dp-gallery-item')?.remove()"></figure><?php endforeach; ?></div>
                            <?php else: ?>
                                <div class="dp-gallery-empty"><div class="dp-hero-avatar g<?= $gradient ?>"><?= e($initials) ?></div></div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
            </div>
            <aside class="dp-side" id="book">
                <div id="dp-book-widget">
                    <?php require __DIR__ . '/profile-booking-sidebar.php'; ?>
                </div>
                <?php if (!empty($p['phone']) || !empty($p['directions_url'])): ?>
                <div class="dp-side-card dp-side-actions">
                    <?php if (!empty($p['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $p['phone'])) ?>" class="dp-btn dp-btn-call"><?= dp_icon('phone') ?>Call Now</a><?php endif; ?>
                    <?php if (!empty($p['directions_url'])): ?><a href="<?= e($p['directions_url']) ?>" target="_blank" rel="noopener" class="dp-btn dp-btn-ghost"><?= dp_icon('compass') ?>Directions</a><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (empty($p['is_claimed']) && empty($p['tenant_slug'])): ?>
                <div class="dp-side-card dp-claim-card">
                    <div class="dp-claim-head"><span class="dp-claim-ico"><?= dp_icon('clinic') ?></span><strong>Is this your <?= ($p['entity_type'] ?? '') === 'clinic' ? 'clinic' : 'practice' ?>?</strong></div>
                    <p class="dp-claim-text">Claim this listing to manage your info, add timings and accept online bookings — free.</p>
                    <a href="/onboarding/get-listed" class="dp-btn dp-btn-book">Claim this <?= ($p['entity_type'] ?? '') === 'clinic' ? 'clinic' : 'listing' ?></a>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
    <button type="button" class="dp-book-fab" onclick="document.getElementById('book')?.scrollIntoView({behavior:'smooth',block:'start'})">Book appointment</button>
    <script>
    if (window.location.hash === '#book') {
        requestAnimationFrame(function () {
            var el = document.getElementById('book');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
    </script>
</main>
