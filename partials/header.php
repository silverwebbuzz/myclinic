<?php
// =====================================================================
// header.php — shared HTML head + top nav. Required by every page.
//
// Pages set these vars BEFORE requiring this file:
//   $pageTitle    — browser tab title (e.g. 'Pricing — eClinicPro')
//   $metaDesc     — meta description tag content
//   $activePage   — slug for highlighting current nav item: 'features' | 'tour' |
//                   'specialties' | 'pricing' | 'security' | '' (home)
//   $bodyClass    — optional extra CSS class for <body>
// =====================================================================

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/patient_auth.php';   // gives us ecp_patient_current()

// Partner referral capture: if a visitor arrives via ?ref=CODE, drop a 30-day
// cookie so the partner is credited when this visitor later registers a clinic.
// Set before any output (this file is required at the top of every page).
if (!empty($_GET['ref']) && empty($_COOKIE['mc_ref'])) {
    $ecpRef = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $_GET['ref']));
    if ($ecpRef !== '' && strlen($ecpRef) <= 20) {
        setcookie('mc_ref', $ecpRef, [
            'expires'  => time() + (30 * 86400),
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['mc_ref'] = $ecpRef;
    }
}

$pageTitle = $pageTitle ?? 'eClinicPro — The clinic OS doctors love';
$metaDesc = $metaDesc ?? 'eClinicPro is the global clinic operating system. Pick your modules. Pay for what you use. Beautiful, fast, and made for every specialty.';
$activePage = $activePage ?? '';
$bodyClass = $bodyClass ?? '';

// ---- Canonical URL + social meta (one place; pages can override $canonicalUrl) ----
// Build from the current request unless a page set $canonicalUrl explicitly
// (e.g. SEO city pages already set their own — see find-a-doctor.php).
if (!isset($canonicalUrl)) {
    $reqPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    // Drop trailing .php if present — Apache rewrites canonical URLs anyway.
    $reqPath = preg_replace('/\.php$/', '', (string) $reqPath);
    $canonicalUrl = ecp_site_url($reqPath);
}
$ogImage = $ogImage ?? ecp_site_url('/assets/img/logos/logo.png');
$ogType  = $ogType  ?? 'website';

// Resolve the logged-in patient once, server-side. Passed to the header
// markup AND echoed into a tiny JSON blob the client can read on first paint
// without waiting for an API roundtrip.
$ecpPatient = ecp_patient_current();

// If the visitor is logged in, never let CloudFlare / browser caches serve
// this HTML to anyone else. Each user gets a per-request render.
if ($ecpPatient) {
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Cookie');
}
$ecpPatientJson = $ecpPatient
    ? json_encode([
        'id'         => (int) $ecpPatient['id'],
        'name'       => $ecpPatient['name'],
        'first_name' => $ecpPatient['first_name'] ?? null,
        'handle'     => $ecpPatient['phone'],
        'has_photo'  => !empty($ecpPatient['photo_path']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    : 'null';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?= e($metaDesc) ?>" />
    <?php if (!empty($noindex)): ?>
    <meta name="robots" content="noindex, nofollow" />
    <?php endif; ?>
    <meta name="theme-color" content="#0F9B6E" />
    <title><?= e($pageTitle) ?></title>

    <!-- Canonical -->
    <link rel="canonical" href="<?= e($canonicalUrl) ?>" />

    <!-- Open Graph (Facebook, WhatsApp, LinkedIn previews) -->
    <meta property="og:site_name" content="eClinicPro" />
    <meta property="og:type"      content="<?= e($ogType) ?>" />
    <meta property="og:title"     content="<?= e($pageTitle) ?>" />
    <meta property="og:description" content="<?= e($metaDesc) ?>" />
    <meta property="og:url"       content="<?= e($canonicalUrl) ?>" />
    <meta property="og:image"     content="<?= e($ogImage) ?>" />
    <meta property="og:locale"    content="en_IN" />

    <!-- Twitter Card -->
    <meta name="twitter:card"        content="summary_large_image" />
    <meta name="twitter:title"       content="<?= e($pageTitle) ?>" />
    <meta name="twitter:description" content="<?= e($metaDesc) ?>" />
    <meta name="twitter:image"       content="<?= e($ogImage) ?>" />

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/logos/favicon.svg" />
    <link rel="icon" type="image/png" sizes="64x64" href="/assets/img/logos/favicon.png" />
    <link rel="apple-touch-icon" href="/assets/img/logos/apple-touch-icon.png" />

    <!-- Site-wide Organization JSON-LD (trust signal) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "eClinicPro",
        "url": "<?= e(ecp_site_url('/')) ?>",
        "logo": "<?= e(ecp_site_url('/assets/img/logos/logo.png')) ?>",
        "description": "The clinic operating system — patient records, prescriptions, appointments, billing — all in one place.",
        "sameAs": [],
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer support",
            "email": "hello@eclinicpro.com",
            "areaServed": "IN",
            "availableLanguage": ["English", "Hindi"]
        }
    }
    </script>

    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-YTM2L1L5RZ"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-YTM2L1L5RZ');
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Non-blocking font load: swap-in once downloaded, system font shown first. -->
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap"
          media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap"></noscript>

    <?php $stylesBust = @filemtime(__DIR__ . '/../assets/css/styles.css') ?: time(); ?>
    <!-- Critical above-the-fold CSS, inlined so the header/nav paints without
         waiting on the 180 KB styles.css. Copied verbatim from styles.css
         (:root, reset, body, .nav*, .logo*, .btn*, mobile burger). Keep in
         sync if those base rules change. -->
    <style id="ecp-critical-css">
:root{--bg:#FFFFFF;--bg-2:#F5F5F7;--bg-3:#FAFAFB;--ink:#0A0A0A;--ink-2:#1C1C1E;--mute:#6E6E73;--line:rgba(0,0,0,.08);--line-2:rgba(0,0,0,.05);--teal-50:#E0F4EE;--teal-100:#C6EBDE;--teal-400:#2DC08A;--teal-600:#0F9B6E;--teal-700:#0B7F5A;--teal-800:#076B4C;--teal-950:#03382A;--blue-600:#1A6FC4;--blue-50:#E8F1FC;--red:#FF453A;--green:#30D158;--amber:#FF9F0A}
*{box-sizing:border-box;margin:0;padding:0}
html,body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;font-feature-settings:"ss01","cv11";background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;scroll-behavior:smooth;overflow-x:hidden;max-width:100%}
img,svg,video{max-width:100%}
a{color:inherit;text-decoration:none}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:980px;padding:12px 22px;font-size:15px;font-weight:500;transition:all .2s ease;white-space:nowrap;border:1px solid transparent}
.btn-primary{background:var(--teal-600);color:#fff}
.nav{position:fixed;top:0;left:0;right:0;z-index:100;background:#fff;backdrop-filter:saturate(180%) blur(20px);-webkit-backdrop-filter:saturate(180%) blur(20px);border-bottom:.5px solid var(--line);transition:all .25s ease;height:80px;display:flex;align-items:center}
.nav-inner{max-width:1280px;margin:0 auto;padding:0 20px;width:100%}
.nav .nav-inner{display:flex;align-items:center;gap:28px}
.logo{font-size:18px;font-weight:600;letter-spacing:-.4px;color:var(--ink);display:inline-flex;align-items:center;width:180px}
.logo em{color:var(--teal-600);font-style:normal;font-weight:600}
.logo-img{display:block;height:100%;width:100%;object-fit:contain;object-position:center center}
.nav-links{display:flex;gap:24px;flex:1;min-width:0}
.nav-link{font-size:16px;font-weight:500;color:var(--ink-2);position:relative;padding:4px 0;transition:color .15s}
.nav-cta{display:flex;gap:8px;align-items:center;flex-shrink:0}
.nav .btn{padding:7px 16px;font-size:16px}
.nav-signin{font-size:14px;font-weight:500;color:var(--ink-2)}
.nav-user{position:relative}
.nav-user-btn{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--line);border-radius:999px;padding:4px 12px 4px 4px;font:inherit;font-size:13.5px;color:var(--ink);cursor:pointer}
.nav-user-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--teal-400),var(--teal-700));color:#fff;display:grid;place-items:center;font-weight:700;font-size:13px;letter-spacing:-.3px;overflow:hidden}
.nav-user-photo{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.nav-user-hi{font-weight:500;color:var(--ink-2);white-space:nowrap;max-width:140px;overflow:hidden;text-overflow:ellipsis}
.nav-user-caret{color:var(--mute)}
.nav-burger{display:none;background:transparent;border:0;margin-left:4px;cursor:pointer;color:var(--ink);border-radius:8px}
.nav-burger svg{width:28px;height:28px}
@media (max-width:900px){.nav-inner{padding:0 20px;gap:12px}.nav-burger{display:inline-flex;align-items:center;justify-content:center}.nav-signin{display:none}.nav-user-hi{display:none}.nav-user-caret{display:none}.nav .btn{padding:8px 14px;font-size:13px;line-height:1.2}.nav-links{display:none}}
    </style>
    <!-- Full stylesheet loaded async so it no longer blocks first paint. -->
    <link rel="preload" as="style" href="/assets/css/styles.css?v=<?= $stylesBust ?>"
          onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="/assets/css/styles.css?v=<?= $stylesBust ?>" /></noscript>
    <?php if (!empty($extraHead)) echo $extraHead; ?>

    <!-- Alpine pinned to exact version for CDN cacheability (was @3.x.x — re-fetched on every floating release) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://analytics.ahrefs.com/analytics.js" data-key="4woSp6JOsZmShXEwnBQwUQ" async></script>
    <script>
        var ahrefs_analytics_script = document.createElement('script');
        ahrefs_analytics_script.async = true;
        ahrefs_analytics_script.src = 'https://analytics.ahrefs.com/analytics.js';
        ahrefs_analytics_script.setAttribute('data-key', '4woSp6JOsZmShXEwnBQwUQ');
        document.getElementsByTagName('head')[0].appendChild(ahrefs_analytics_script);
    </script>

</head>
<!-- Hand the initial session blob to JS via a separate JSON script tag
     so we don't have to embed PHP inside an Alpine x-data attribute. -->
<script>window.ECP_PATIENT = <?= $ecpPatientJson ?>;</script>
<script>window.ECP_PORTAL_URL = <?= json_encode(rtrim(ecp_portal_url('/'), '/'), JSON_UNESCAPED_SLASHES) ?>;</script>
<script>window.ECP_SITE_URL = <?= json_encode(rtrim(ecp_site_url('/'), '/'), JSON_UNESCAPED_SLASHES) ?>;</script>

<body class="<?= e($bodyClass) ?>"
      x-data="ecpHeader()"
      x-init="loadPatient(); window.addEventListener('storage', loadPatient); window.addEventListener('ecp:patient-login', (e) => { if (e.detail) this.patient = e.detail; })">

<header class="nav">
    <div class="nav-inner">
        <a href="/" class="logo" aria-label="eClinicPro home">
            <img src="/assets/img/logos/logo.svg" alt="eClinicPro" class="logo-img" width="160" height="40" />
        </a>

        <nav class="nav-links" :class="mobileNav ? 'is-open' : ''">
            <a href="/find-a-doctor" class="nav-link <?= nav_active('find') ?>">Find a doctor</a>
            <a href="/cervical-cancer" class="nav-link nav-link-awareness <?= nav_active('cervical') ?>">Cervical Cancer</a>
            <a href="/features" class="nav-link <?= nav_active('features') ?>">For doctors</a>
            <a href="/#specialties" class="nav-link <?= nav_active('specialties') ?>">Specialties</a>
            <a href="/security" class="nav-link <?= nav_active('security') ?>">Security</a>
        </nav>

        <div class="nav-cta">
            <!-- Logged out: opens the shared login modal. -->
            <button type="button" class="nav-signin" x-show="!patient"
                    @click="window.ecpAuth && window.ecpAuth.open('default')"
                    style="background: none; border: 0; cursor: pointer; padding: 0; font: inherit;">
                Patient login
            </button>

            <!-- Logged in: greeting + avatar dropdown -->
            <div class="nav-user" x-show="patient" @click.outside="patientMenuOpen = false">
                <button type="button" class="nav-user-btn" @click="patientMenuOpen = !patientMenuOpen">
                    <span class="nav-user-avatar">
                        <template x-if="patient && patient.has_photo">
                            <img src="/api/patient_profile?action=photo" alt="" class="nav-user-photo">
                        </template>
                        <template x-if="!patient || !patient.has_photo">
                            <span x-text="patientInitial()"></span>
                        </template>
                    </span>
                    <span class="nav-user-hi">Hi, <strong x-text="patientFirstName()"></strong></span>
                    <svg class="nav-user-caret" :class="patientMenuOpen ? 'open' : ''" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="nav-user-menu" x-show="patientMenuOpen" x-transition.opacity>
                    <a href="/patient" class="nav-user-item">My Health</a>
                    <a href="/find-a-doctor" class="nav-user-item">Find a doctor</a>
                    <button type="button" class="nav-user-item danger" @click="signOut()">Sign out</button>
                </div>
            </div>

            <a href="<?= e(ecp_portal_url('/login')) ?>" class="btn btn-primary">Doctor panel</a>
            <button type="button" @click="mobileNav = !mobileNav"
                    class="nav-burger" aria-label="Menu">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
        </div>
    </div>
</header>

<script>
// Header / nav state. Defined as a real function so we don't have to
// stuff JS (with comments, awaits, and braces) into an HTML attribute,
// which breaks parsing in some browsers + makes Alpine angry.
function ecpHeader() {
  return {
    mobileNav: false,
    patient: window.ECP_PATIENT || null,
    patientMenuOpen: false,

    loadPatient() {
      if (window.ECP_PATIENT) {
        this.patient = window.ECP_PATIENT;
        try {
          localStorage.setItem('ecp_patient', JSON.stringify(this.patient));
        } catch (e) {}
        return;
      }
      // Server says logged out — don't show a stale localStorage session in the header.
      this.patient = null;
      try { localStorage.removeItem('ecp_patient'); } catch (e) {}
    },

    patientFirstName() {
      if (!this.patient) return '';
      const n = this.patient.first_name || this.patient.name || this.patient.handle || '';
      return n.split(/\s+/)[0] || 'there';
    },

    patientInitial() {
      const n = this.patientFirstName();
      return n ? n.charAt(0).toUpperCase() : 'P';
    },

    async signOut() {
      try {
        await fetch('/api/patient_auth?action=logout', {
          method: 'POST',
          credentials: 'same-origin',
        });
      } catch (e) { /* ignore */ }
      try { localStorage.removeItem('ecp_patient'); } catch (e) {}
      this.patient = null;
      this.patientMenuOpen = false;
      location.reload();
    },
  };
}
</script>

<?php require __DIR__ . '/auth-modal.php'; ?>
<?php require __DIR__ . '/doctor-claim-modal.php'; ?>
