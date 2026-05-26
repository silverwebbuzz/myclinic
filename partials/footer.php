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
            Join <?= ecp_num($clinicCount) ?> clinics worldwide. Start free in 2 minutes.<br>
            No credit card. No phone-tag with sales. Just a clean clinic.
        </p>
        <div class="hero-ctas">
            <a href="<?= e(ecp_portal_url('/register')) ?>" class="btn btn-primary btn-lg">
                Start free — no card needed
            </a>
            <a href="/book-a-demo" class="btn btn-ghost-dark btn-lg">
                Schedule a 15-min demo →
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Mega-city links — major SEO juice for /find-a-doctor/{city} pages. -->
<?php
// Top 30 cities by doctor count (cached for 6 hours to avoid querying on every page).
$footerCities = ecp_footer_top_cities(30);
?>
<?php if (!empty($footerCities)): ?>
<section class="foot-cities">
    <div class="wrap">
        <h4>Doctors near you</h4>
        <ul>
            <?php foreach ($footerCities as $c): ?>
            <li><a href="/find-a-doctor/<?= e(ecp_slug_for_city($c['city'])) ?>"><?= e($c['city']) ?></a></li>
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
                <span class="logo">e<em>ClinicPro</em></span>
                <p>The clinic operating system, for every specialty, in every country. Made with care for clinicians worldwide.</p>
            </div>
            <div class="foot-col">
                <h5>Product</h5>
                <ul>
                    <li><a href="/features">Features</a></li>
                    <li><a href="/product-tour">Product tour</a></li>
                    <li><a href="/#modules">Modules</a></li>
                    <li><a href="/pricing">Pricing</a></li>
                    <li><a href="#">Changelog</a></li>
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
                    <li><a href="/find-a-doctor">Find a doctor</a></li>
                    <li><a href="/book-a-demo">Book a demo</a></li>
                </ul>
            </div>
            <div class="foot-col">
                <h5>Company</h5>
                <ul>
                    <li><a href="#">About</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press kit</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="foot-bottom">
            <div>© <?= date('Y') ?> eClinicPro, Inc. · Made with care for clinics worldwide 🌿</div>
            <div class="links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="/security">Security</a>
                <a href="#">HIPAA</a>
                <a href="#">GDPR</a>
            </div>
        </div>
    </div>
</footer>

<!-- Reveal-on-scroll: light replacement for the React IntersectionObserver -->
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
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    els.forEach(el => io.observe(el));
});
</script>
</body>
</html>
