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
                    <?php // Lab Tests link hidden — /lab is the legacy storefront blueprint.
                          // Kept, not deleted: restore this line if /lab goes live again. ?>
                    <?php /* <li><a href="/lab">Lab Tests</a></li> */ ?>
                    <li><a href="/health-insurance">Health Insurance</a></li>
                    <li><a href="/clinic-management-software">For doctors</a></li>
                    <li><a href="/product-tour">Product tour</a></li>
                    <li><a href="/pricing">Pricing</a></li>
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


<!-- Floating chat widget (bottom-right). Opens a small panel with
     WhatsApp / call / email. No third-party script — plain links. -->
<?php
$chatPhone = '919998010029';
$chatMsg   = 'Hi eClinicPro, I have a question.';
?>
<div class="ecp-chat" id="ecpChat">
    <div class="ecp-chat-panel" id="ecpChatPanel" role="dialog" aria-labelledby="ecpChatTitle" hidden>
        <div class="ecp-chat-head">
            <div>
                <b id="ecpChatTitle">Chat with us</b>
                <span>We usually reply within a few minutes.</span>
            </div>
            <button type="button" class="ecp-chat-x" aria-label="Close chat" data-chat-close>&times;</button>
        </div>
        <div class="ecp-chat-body">
            <p class="ecp-chat-bubble">👋 Hi there! How can we help you today?</p>
            <a class="ecp-chat-opt ecp-chat-wa" href="https://wa.me/<?= e($chatPhone) ?>?text=<?= rawurlencode($chatMsg) ?>" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.2-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48 0 1.46 1.07 2.88 1.21 3.07.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.05 21.5h-.01a9.4 9.4 0 0 1-4.8-1.32l-.34-.2-3.57.94.95-3.48-.22-.36a9.4 9.4 0 0 1-1.44-5.02c0-5.2 4.23-9.43 9.44-9.43a9.37 9.37 0 0 1 9.43 9.44c0 5.2-4.23 9.43-9.44 9.43zm8.03-17.46A11.3 11.3 0 0 0 12.05.7C5.8.7.7 5.8.7 12.05c0 2 .52 3.95 1.52 5.67L.6 23.3l5.72-1.5a11.3 11.3 0 0 0 5.42 1.38h.01c6.25 0 11.35-5.1 11.35-11.35 0-3.03-1.18-5.88-3.32-8.02z"/></svg>
                Chat on WhatsApp
            </a>
            <a class="ecp-chat-opt" href="tel:+<?= e($chatPhone) ?>">📞 Call +91 99980 10029</a>
            <a class="ecp-chat-opt" href="mailto:hello@eclinicpro.com">✉️ hello@eclinicpro.com</a>
        </div>
    </div>
    <button type="button" class="ecp-chat-btn" id="ecpChatBtn" aria-label="Open chat" aria-expanded="false" aria-controls="ecpChatPanel">
        <svg class="ecp-chat-ic-open" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2zm3 6a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 7 10zm5 0a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4zm5 0a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z"/></svg>
        <svg class="ecp-chat-ic-close" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M18.3 5.7a1 1 0 0 0-1.4 0L12 10.6 7.1 5.7a1 1 0 0 0-1.4 1.4l4.9 4.9-4.9 4.9a1 1 0 1 0 1.4 1.4l4.9-4.9 4.9 4.9a1 1 0 0 0 1.4-1.4L13.4 12l4.9-4.9a1 1 0 0 0 0-1.4z"/></svg>
    </button>
</div>

<script>
    (function () {
        const btn = document.getElementById('ecpChatBtn');
        const panel = document.getElementById('ecpChatPanel');
        const root = document.getElementById('ecpChat');
        if (!btn || !panel) return;
        const setOpen = (open) => {
            panel.hidden = !open;
            root.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-label', open ? 'Close chat' : 'Open chat');
        };
        btn.addEventListener('click', () => setOpen(panel.hidden));
        panel.querySelector('[data-chat-close]').addEventListener('click', () => setOpen(false));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !panel.hidden) setOpen(false); });
        document.addEventListener('click', (e) => { if (!panel.hidden && !root.contains(e.target)) setOpen(false); });
    })();
</script>

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