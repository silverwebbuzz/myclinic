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
    $canonicalUrl = 'https://eclinicpro.com' . $reqPath;
}
$ogImage = $ogImage ?? 'https://eclinicpro.com/assets/og-default.png';
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
    <link rel="icon" type="image/png" href="/assets/favicon.png" />
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png" />

    <!-- Site-wide Organization JSON-LD (trust signal) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "eClinicPro",
        "url": "https://eclinicpro.com",
        "logo": "https://eclinicpro.com/assets/og-default.png",
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
          href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap">
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
          media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"></noscript>

    <?php $stylesBust = @filemtime(__DIR__ . '/../assets/css/styles.css') ?: time(); ?>
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?= $stylesBust ?>" />
    <?php if (!empty($extraHead)) echo $extraHead; ?>

    <!-- Alpine pinned to exact version for CDN cacheability (was @3.x.x — re-fetched on every floating release) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<!-- Hand the initial session blob to JS via a separate JSON script tag
     so we don't have to embed PHP inside an Alpine x-data attribute. -->
<script>window.ECP_PATIENT = <?= $ecpPatientJson ?>;</script>

<body class="<?= e($bodyClass) ?>"
      x-data="ecpHeader()"
      x-init="loadPatient(); window.addEventListener('storage', loadPatient)">

<header class="nav">
    <div class="nav-inner">
        <a href="/" class="logo">e<em>ClinicPro</em></a>

        <nav class="nav-links" :class="mobileNav ? 'is-open' : ''">
            <a href="/find-a-doctor" class="nav-link <?= nav_active('find') ?>">Find a doctor</a>
            <a href="/features" class="nav-link <?= nav_active('features') ?>">For doctors</a>
            <a href="/#specialties" class="nav-link <?= nav_active('specialties') ?>">Specialties</a>
            <a href="/pricing" class="nav-link <?= nav_active('pricing') ?>">Pricing</a>
            <a href="/security" class="nav-link <?= nav_active('security') ?>">Security</a>
        </nav>

        <div class="nav-cta">
            <!-- Logged out: opens the shared login modal. -->
            <button type="button" class="nav-signin" x-show="!patient"
                    @click="window.ecpAuth && window.ecpAuth.open('default')"
                    style="background: none; border: 0; cursor: pointer; padding: 0; font: inherit;">
                Sign in
            </button>

            <!-- Logged in: greeting + avatar dropdown -->
            <div class="nav-user" x-show="patient" @click.outside="patientMenuOpen = false">
                <button type="button" class="nav-user-btn" @click="patientMenuOpen = !patientMenuOpen">
                    <span class="nav-user-avatar" x-text="patientInitial()"></span>
                    <span class="nav-user-hi">Hi, <strong x-text="patientFirstName()"></strong></span>
                    <svg class="nav-user-caret" :class="patientMenuOpen ? 'open' : ''" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="nav-user-menu" x-show="patientMenuOpen" x-transition.opacity>
                    <a href="/patient" class="nav-user-item">My panel</a>
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
      // Server already gave us a session blob? Trust it.
      if (this.patient) return;
      try {
        const raw = localStorage.getItem('ecp_patient');
        this.patient = raw ? JSON.parse(raw) : null;
      } catch (e) {
        this.patient = null;
      }
    },

    patientFirstName() {
      if (!this.patient) return '';
      const n = this.patient.first_name || this.patient.name || this.patient.handle || '';
      return n.split(/\s+/)[0] || 'Patient';
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
<?php require __DIR__ . '/lead-book-modal.php'; ?>
