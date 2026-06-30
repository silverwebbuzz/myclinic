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
                <h5>Product</h5>
                <ul>
                    <li><a href="/find-a-doctor">Find a doctor</a></li>
                    <li><a href="/eclinicpro-health-store">Health Store</a></li>
                    <li><a href="/lab">Lab Tests</a></li>
                    <li><a href="/health-insurance">Health Insurance</a></li>
                    <li><a href="/features">For doctors</a></li>
                    <li><a href="/product-tour">Product tour</a></li>
                    <li><a href="/features#pricing">Pricing</a></li>
                </ul>
            </div>
            <div class="foot-col">
                <h5>Specialties</h5>
                <ul>
                    <li><a href="/gps">General practice</a></li>
                    <li><a href="/dentists">Dentistry</a></li>
                    <li><a href="/homeopaths">Homeopathy</a></li>
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
                    <li><a href="#">Contact</a></li>
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

<!-- jQuery (must be first) -->
<!-- jQuery (FIRST) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Slick JS (AFTER jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.js"></script>



<!-- Slick Init -->
<!-- <script>
jQuery(document).ready(function ($) {

    if ($('.shop-list').length) {
        $('.shop-list').slick({
            slidesToShow: 5,
            slidesToScroll: 1,
            arrows: true,
            dots: false,
            autoplay: true,
            autoplaySpeed: 3000,
            responsive: [
                {
                    breakpoint: 1024,
                    settings: { slidesToShow: 3 }
                },
                {
                    breakpoint: 768,
                    settings: { slidesToShow: 2 }
                },
                {
                    breakpoint: 480,
                    settings: { slidesToShow: 1 }
                }
            ]
        });
    }

});
</script> -->


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