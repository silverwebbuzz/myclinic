<?php
/** @var array<string, mixed> $p */
$gradient = (int) ($p['avatar_gradient'] ?? 1);
$initials = (string) ($p['avatar_initials'] ?? 'DR');
$primaryImage = $p['photo_url'] ?? null;
$hoursLines = $hoursLines ?? ecp_profile_hours_lines($p['opening_hours'] ?? null);
$showDoctorsTab = $showDoctorsTab ?? (($p['entity_type'] ?? '') === 'clinic');
$specFindUrl = !empty($p['specialty_url_slug']) && !empty($p['city_slug'])
    ? '/find-a-doctor/' . e($p['specialty_url_slug']) . '-in-' . e($p['city_slug'])
    : '/find-a-doctor/' . e($p['city_slug'] ?? '');
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
                            <?php if (!empty($p['is_claimed'])): ?><span class="dp-verified">✓ Verified by doctor</span><?php endif; ?>
                        </div>
                        <h1 class="dp-title"><?= e($p['display_name'] ?? '') ?></h1>
                        <p class="dp-spec"><?= e($p['specialty_label'] ?? '') ?></p>
                        <?php if (($p['rating'] ?? 0) > 0): ?>
                            <div class="dp-rating"><span class="dp-rating-val"><?= number_format((float) $p['rating'], 1) ?></span><span class="dp-rating-count"><?= (int) ($p['reviews'] ?? 0) ?> reviews</span></div>
                        <?php endif; ?>
                        <div class="dp-meta-list">
                            <?php if (!empty($p['address']) || !empty($p['area']) || !empty($p['city'])): ?>
                                <div class="dp-meta-row"><span>📍</span><span><?= e(trim(($p['address'] ?? '') !== '' ? $p['address'] : implode(', ', array_filter([$p['area'] ?? '', $p['city'] ?? '', $p['state'] ?? ''])))) ?></span></div>
                            <?php endif; ?>
                            <?php if (!empty($p['phone'])): ?>
                                <div class="dp-meta-row"><span>📞</span><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $p['phone'])) ?>"><?= e($p['phone']) ?></a></div>
                            <?php endif; ?>
                            <?php if ($hoursLines): ?>
                                <div class="dp-meta-row"><span>🕒</span><span><?= e($hoursLines[0]) ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
                <div class="dp-content-block">
                    <nav class="dp-tabs">
                        <button type="button" class="dp-tab" :class="tab === 'overview' ? 'on' : ''" @click="tab = 'overview'">Overview</button>
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
                                $facts[] = ['🩺', 'Specialty', e((string) $p['specialty_label'])];
                            }
                            if (!empty($p['city'])) {
                                $facts[] = ['📍', 'Location', e(trim(implode(', ', array_filter([$p['area'] ?? '', $p['city'] ?? '', $p['state'] ?? '']))))];
                            }
                            if ($langList) {
                                $facts[] = ['🗣️', 'Languages', e(implode(', ', $langList))];
                            }
                            if ($feeVal > 0) {
                                $facts[] = ['💳', 'Consultation fee', e((string) ($p['currency'] ?? '₹') . number_format($feeVal))];
                            }
                            if (($p['rating'] ?? 0) > 0) {
                                $facts[] = ['⭐', 'Rating', number_format((float) $p['rating'], 1) . ' (' . (int) ($p['reviews'] ?? 0) . ' reviews)'];
                            }
                            if (!empty($p['website'])) {
                                $facts[] = ['🌐', 'Website', '<a href="' . e((string) $p['website']) . '" target="_blank" rel="noopener nofollow">' . e(preg_replace('#^https?://#', '', (string) $p['website'])) . '</a>'];
                            }
                            ?>
                            <?php if ($facts): ?>
                            <dl class="dp-facts">
                                <?php foreach ($facts as [$icon, $label, $value]): ?>
                                <div class="dp-fact">
                                    <dt><span class="dp-fact-ico"><?= $icon ?></span><?= e($label) ?></dt>
                                    <dd><?= $value ?></dd>
                                </div>
                                <?php endforeach; ?>
                            </dl>
                            <?php endif; ?>
                            <h2>About</h2>
                            <p class="dp-about"><?= !empty($p['bio']) ? nl2br(e($p['bio'])) : e(($p['display_name'] ?? '') . ' is a ' . strtolower($p['specialty_label'] ?? 'doctor') . ' in ' . ($p['city'] ?? '') . '.') ?></p>
                            <?php if ($hoursLines): ?><h3 class="dp-subhead">Timings</h3><ul class="dp-hours"><?php foreach ($hoursLines as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        </section>
                        <section class="dp-panel" x-show="tab === 'treatments'" x-cloak>
                            <h2>Treatments</h2>
                            <ul class="dp-treatment-list"><?php foreach (($p['treatments'] ?? []) as $t): ?><li><?= e((string) $t) ?></li><?php endforeach; ?></ul>
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
                    <?php if (!empty($p['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/', '', (string) $p['phone'])) ?>" class="dp-btn dp-btn-call">📞 Call Now</a><?php endif; ?>
                    <?php if (!empty($p['directions_url'])): ?><a href="<?= e($p['directions_url']) ?>" target="_blank" rel="noopener" class="dp-btn dp-btn-ghost">🧭 Directions</a><?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (empty($p['is_claimed']) && empty($p['tenant_slug'])): ?>
                <div class="dp-side-card dp-claim-card">
                    <div class="dp-claim-head"><span class="dp-claim-ico">🏥</span><strong>Is this your <?= ($p['entity_type'] ?? '') === 'clinic' ? 'clinic' : 'practice' ?>?</strong></div>
                    <p class="dp-claim-text">Claim this listing to manage your info, add timings and accept online bookings — free.</p>
                    <a href="/onboarding/get-listed" class="dp-btn dp-btn-book">Claim this <?= ($p['entity_type'] ?? '') === 'clinic' ? 'clinic' : 'listing' ?> →</a>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
    <button type="button" class="dp-book-fab" onclick="document.getElementById('book')?.scrollIntoView({behavior:'smooth',block:'start'})">📅 Book</button>
    <script>
    if (window.location.hash === '#book') {
        requestAnimationFrame(function () {
            var el = document.getElementById('book');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
    </script>
</main>
