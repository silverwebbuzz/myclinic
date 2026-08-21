<?php
// =====================================================================
// footer.php — shared footer + final CTA + closing tags.
//
// Optional var BEFORE requiring:
//   $hideFinalCta = true   — skips the "Ready to run your clinic?" block
//                            (set this on landing pages that already have a CTA)
// =====================================================================

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seo_slugs.php';
$hideFinalCta = $hideFinalCta ?? false;
$clinicCount = ecp_active_clinic_count();
?>

<?php if (!$hideFinalCta): ?>
    <section class="cta-block" id="cta">
        <div class="wrap reveal">
            <h2>Ready to run your clinic beautifully?</h2>
            <p class="lede">
                Join <?= ecp_num($clinicCount) ?> clinics across India. Start free in 2 minutes.<br>
                No credit card. No phone-tag with sales. Just a clean clinic.
            </p>
            <div class="hero-ctas">
                <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">
                    Start 30-day free trial
                </a>
                <a href="/book-a-demo" class="btn btn-ghost-dark btn-lg">
                    Schedule a 15-min demo →
                </a>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- Mega-city links — major SEO juice for /find-a-doctor/{city} pages.
     Wrapped in try/catch so a DB hiccup never blows up the entire page. -->
<?php
$footerCities = [];
try {
    if (function_exists('ecp_footer_top_cities')) {
        $footerCities = ecp_footer_top_cities(30) ?: [];
    }
} catch (\Throwable $e) {
    error_log('[footer-cities] ' . $e->getMessage());
    $footerCities = [];
}
?>
<!-- Our Presence — city directory strip (images can be added later) -->
<?php
$presenceCities = [
    'Agra', 'Ahmedabad', 'Aligarh', 'Bareilly', 'Bengaluru', 'Bhopal', 'Bhubaneswar', 'Bilaspur',
    'Chennai', 'Chhattisgarh', 'Coimbatore', 'Dadar', 'Delhi', 'Dhanbad', 'Ernakulam', 'Faridabad',
    'Ghaziabad', 'Goa', 'Gonda', 'Greater Noida', 'Gurgaon', 'Gwalior', 'Hyderabad', 'Indore',
    'Jabalpur', 'Jaipur', 'Jamshedpur', 'Jhansi', 'Jodhpur', 'Kalyan', 'Kerala', 'Kochi',
    'Kolkata', 'Kota', 'Lucknow', 'Ludhiana', 'Mangalore', 'Mulund', 'Mumbai', 'Navi Mumbai',
    'Nizamabad', 'Noida', 'Panaji', 'Patiala', 'Patna', 'Pune', 'Rajkot', 'Ranchi',
    'Ratnagiri', 'South Delhi', 'Surat', 'Tirupati', 'Udaipur', 'Varanasi', 'Venkateswara nagar',
    'Vijayawada', 'Visakhapatnam', 'West Bengal', 'West Delhi',
];
$presenceTitle = $presenceTitle ?? 'Available in 50+ Cities';
$presenceSub = $presenceSub ?? 'Find doctors & clinics across these cities';
?>
<section class="foot-presence" aria-labelledby="foot-presence-title">
    <div class="wrap">
        <h2 id="foot-presence-title" class="foot-presence-title"><?= e($presenceTitle) ?></h2>
        <p class="foot-presence-sub"><?= e($presenceSub) ?></p>
        <div class="foot-presence-rule" aria-hidden="true"></div>
        <div class="foot-presence-list">
            <?php foreach ($presenceCities as $i => $cityName): ?>
                <?php
                $slug = function_exists('ecp_slug_for_city') ? ecp_slug_for_city($cityName) : '';
                $href = $slug !== '' ? '/find-a-doctor/' . rawurlencode($slug) : '/find-a-doctor';
                ?>
                <?php if ($i > 0): ?><span class="foot-presence-sep" aria-hidden="true">|</span><?php endif; ?>
                <a class="foot-presence-link" href="<?= e($href) ?>"><?= e($cityName) ?></a>
            <?php endforeach; ?>
        </div>
        <!-- Optional image grid (hidden until assets are added):
             <div class="foot-presence-gallery" hidden>...</div>
        -->
    </div>
</section>

<?php if (!empty($footerCities)): ?>
    <section class="foot-cities">
        <div class="wrap">
            <h4>Doctors near you</h4>
            <ul>
                <?php foreach ($footerCities as $c): ?>
                    <?php
                    $cityName = (string) ($c['city'] ?? '');
                    if ($cityName === '') continue;
                    $slug = function_exists('ecp_slug_for_city') ? ecp_slug_for_city($cityName) : '';
                    if ($slug === '') continue;
                    ?>
                    <li><a href="/find-a-doctor/<?= e($slug) ?>"><?= e($cityName) ?></a></li>
                <?php endforeach; ?>
                <li><a href="/find-a-doctor" class="foot-cities-more">All cities →</a></li>
            </ul>
        </div>
    </section>
<?php endif; ?>

<footer class="foot">
    <div class="wrap">
        <div class="foot-grid">
            <div class="foot-brand">
                <a href="/" class="logo" aria-label="eClinicPro home">
                    <img src="/assets/img/logos/logo-2.svg" alt="eClinicPro" class="logo-img" width="160" height="160" />
                </a>
                <p>Book a verified doctor, or run your whole clinic — one simple system. Made in India, for Indian clinics. 🌿</p>
            </div>
            <div class="foot-col">
                <h5>For patients</h5>
                <ul>
                    <li><a href="/for-patients">Why eClinicPro</a></li>
                    <li><a href="/patient">Sign in / Register</a></li>
                    <li><a href="/find-a-doctor">Find a doctor</a></li>
                    <li><a href="/for-patients#family">Family profiles</a></li>
                    <li><a href="/for-patients#rx">E-prescriptions</a></li>
                </ul>
            </div>
            <div class="foot-col">
                <h5>Product</h5>
                <ul>
                    <li><a href="/find-a-doctor">Find a doctor</a></li>
                    <li><a href="/eclinicpro-health-store">Health Store</a></li>
                    <li><a href="/lab">Lab Tests</a></li>
                    <li><a href="/health-insurance">Health Insurance</a></li>
                    <li><a href="/clinic-management-software">For doctors</a></li>
                    <li><a href="/product-tour">Product tour</a></li>
                    <li><a href="/clinic-management-software#pricing">Pricing</a></li>
                </ul>
            </div>
            <div class="foot-col">
                <h5>Specialties</h5>
                <ul>
                    <li><a href="/gps">General practice</a></li>
                    <li><a href="/dentists">Dentistry</a></li>
                    <li><a href="/homeopathy-clinic-management-software">Homeopathy</a></li>
                    <li><a href="/dermatologists">Dermatology</a></li>
                    <li><a href="/pediatricians">Pediatrics</a></li>
                    <li><a href="/physiotherapists">Physiotherapy</a></li>
                </ul>
            </div>
            <div class="foot-col">
                <h5>Trust</h5>
                <ul>
                    <li><a href="/security">Security</a></li>
                    <li><a href="/customer-stories">Customer stories</a></li>
                    <li><a href="/security#compliance">HIPAA / GDPR</a></li>
                    <li><a href="/cervical-cancer">Cervical Cancer Awareness</a></li>
                    <li><a href="/find-a-doctor">Find a doctor</a></li>
                    <li><a href="/book-a-demo">Book a demo</a></li>
                </ul>
            </div>
            <div class="foot-col">
                <h5>Company</h5>
                <ul>
                    <li><a href="/become-a-partner">Become a partner</a></li>
                    <li><a href="#">About</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press kit</a></li>
                    <li><a href="/contact">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="foot-bottom">
            <div>© <?= date('Y') ?> eClinicPro — a brand of <a href="https://silverwebbuzz.com" target="_blank" rel="noopener">Silver Webbuzz Pvt Ltd</a> · Made with care for clinics across India 🌿</div>
            <div class="links">
                <a href="/privacy-policy">Privacy</a>
                <a href="/terms">Terms</a>
                <a href="/refund-policy">Refunds</a>
                <a href="/privacy-policy#grievance">Grievance</a>
                <a href="/security">Security</a>
            </div>
        </div>
    </div>
</footer>

<?php if (($activePage ?? '') === 'find'): ?>
    <?php $fdSearchBust = @filemtime(__DIR__ . '/../assets/js/find-doctor-search.js') ?: time(); ?>
    <script defer src="/assets/js/find-doctor-search.js?v=<?= (int) $fdSearchBust ?>"></script>
<?php endif; ?>

<!-- Reveal-on-scroll: light replacement for the React IntersectionObserver -->

<!--
  Removed from the global footer (2026-07-10, perf): jQuery 3.7.1, slick
  (x2: jsdelivr 1.8.1 + cloudflare 1.9.0) and swiper (x2: v11 + v12).
  These were render-blocking on EVERY page (~160 KiB from 4 CDNs) but:
    - jQuery + slick were dead code (only user was the commented-out init below)
    - swiper is used ONLY by /eclinicpro-health-store, which loads its own copy
  Lighthouse network tree showed these as the main critical-path chain on
  /find-a-doctor. If a future page needs a carousel, load swiper on that
  page only (as health-store does), not here.
-->


<script>
    document.addEventListener('DOMContentLoaded', () => {
        const els = document.querySelectorAll('.reveal');
        if (!els.length || !('IntersectionObserver' in window)) {
            els.forEach(el => el.classList.add('is-in'));
            return;
        }
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('is-in');
                    io.unobserve(e.target);
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -60px 0px'
        });
        els.forEach(el => io.observe(el));
    });
</script>

</body>

</html>