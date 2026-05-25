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

$pageTitle = $pageTitle ?? 'eClinicPro — The clinic OS doctors love';
$metaDesc = $metaDesc ?? 'eClinicPro is the global clinic operating system. Pick your modules. Pay for what you use. Beautiful, fast, and made for every specialty.';
$activePage = $activePage ?? '';
$bodyClass = $bodyClass ?? '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="<?= e($metaDesc) ?>" />
    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <?php $stylesBust = @filemtime(__DIR__ . '/../assets/css/styles.css') ?: time(); ?>
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?= $stylesBust ?>" />
    <?php if (!empty($extraHead)) echo $extraHead; ?>

    <!-- Lightweight interactivity (mobile menu, reveal-on-scroll) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="<?= e($bodyClass) ?>"
      x-data="{
        mobileNav: false,
        patient: null,
        patientMenuOpen: false,
        loadPatient() {
          try {
            const raw = localStorage.getItem('ecp_patient');
            this.patient = raw ? JSON.parse(raw) : null;
          } catch (e) { this.patient = null; }
        },
        patientFirstName() {
          if (!this.patient) return '';
          const n = this.patient.name || this.patient.handle || '';
          return n.split(/\s+/)[0] || 'Patient';
        },
        patientInitial() {
          const n = this.patientFirstName();
          return n ? n.charAt(0).toUpperCase() : 'P';
        },
        signOut() {
          localStorage.removeItem('ecp_patient');
          this.patient = null;
          this.patientMenuOpen = false;
          // If we're already on the patient page, reload so it shows logged-out view.
          if (location.pathname.startsWith('/patient')) location.reload();
        }
      }"
      x-init="loadPatient(); window.addEventListener('storage', loadPatient);">

<header class="nav">
    <div class="nav-inner">
        <a href="/" class="logo">e<em>ClinicPro</em></a>

        <nav class="nav-links" :class="mobileNav ? 'is-open' : ''">
            <a href="/features" class="nav-link <?= nav_active('features') ?>">Features</a>
            <a href="/product-tour" class="nav-link <?= nav_active('tour') ?>">Tour</a>
            <a href="/#specialties" class="nav-link <?= nav_active('specialties') ?>">Specialties</a>
            <a href="/pricing" class="nav-link <?= nav_active('pricing') ?>">Pricing</a>
            <a href="/security" class="nav-link <?= nav_active('security') ?>">Security</a>
            <a href="/find-a-doctor" class="nav-link <?= nav_active('find') ?>">Find a doctor</a>
        </nav>

        <div class="nav-cta">
            <!-- Logged out: plain Patient panel link -->
            <a href="/patient" class="nav-signin" x-show="!patient">Patient panel</a>

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
