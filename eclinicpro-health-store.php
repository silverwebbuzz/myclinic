<?php
// =====================================================================
// eclinicpro-health-store.php — STATIC BLUEPRINT for the future store.
//
// This is a visual reference page (not a working store). It shows the
// full taxonomy — health conditions, categories + subcategories, brands,
// specialist recommendations, collections — so the same structure can be
// rebuilt in Shopify later. No DB, no cart, no checkout. Every clickable
// element triggers a "Coming soon" toast.
//
// Built on the shared site shell (header/footer partials) and styled with
// the existing assets/css/styles.css design tokens (.store-* block).
// =====================================================================

// NOTE: do NOT require/run the clean-URL router here. This page IS a target
// the router dispatches to (/eclinicpro-health-store -> this file). Calling
// the router again would re-require this file and recurse → HTTP 500.
// Pages like features.php / find-a-doctor.php render directly, same as here.
require_once __DIR__ . '/partials/helpers.php';

$pageTitle  = 'HealthPro — Your Health, Our Priority';
$metaDesc   = 'Trusted health products for you and your family. Shop medical devices, nutrition, personal care, baby care and more — doctor-recommended wellness.';
$activePage = 'store';
$bodyClass  = 'hp-store-page';
$hideFinalCta = true;
$noindex = true; // keep out of Google until the store is live —
// URL stays shareable for partner demos.

// ---------------------------------------------------------------------
// PAGE DATA — images use /assets/img/store/ where available
// ---------------------------------------------------------------------
$hpImg = '/assets/img/store/';

$conditions = [
    ['🩸', 'Diabetes Care',       'c-red'],
    ['❤️', 'Heart Health',        'c-pink'],
    ['⚖️', 'Weight Management',   'c-amber'],
    ['🛡️', 'Immunity Support',    'c-teal'],
    ['🍽️', 'Digestive Health',    'c-orange'],
    ['🦴', 'Joint & Bone Care',   'c-blue'],
    ['😴', 'Sleep & Stress Care', 'c-purple'],
    ['🌸', "Women's Health",      'c-rose'],
    ['🤰', 'Pregnancy Care',      'c-red'],
    ['👶', 'Baby Care',           'c-sky'],
    ['👴', 'Elderly Care',        'c-teal'],
    ['👁️', 'Eye Care',            'c-blue'],
];

$categories = [
    [
        'Health & Medical Devices',
        $hpImg . 'hero_products.png',
        ['Thermometers', 'Blood Pressure Monitors', 'Glucose Monitors', 'Pulse Oximeters', 'Nebulizers']
    ],
    [
        'Nutrition & Healthy Foods',
        $hpImg . 'product_weighing_scale.png',
        ['Protein Supplements', 'Vitamins & Minerals', 'Herbal Supplements', 'Healthy Snacks', 'Meal Replacements']
    ],
    [
        'Personal Care',
        $hpImg . 'product_thermometer.png',
        ['Skin Care', 'Hair Care', 'Oral Care', 'Body Care', 'Hygiene Products']
    ],
    [
        'Baby & Mother Care',
        $hpImg . 'product_pulse_oximeter.png',
        ['Baby Feeding', 'Baby Skin Care', 'Diapers & Wipes', 'Nursing Products', 'Baby Monitors']
    ],
    [
        'Fitness & Recovery',
        $hpImg . 'product_bp_monitor.png',
        ['Fitness Equipment', 'Muscle Recovery', 'Pain Relief', 'Yoga & Wellness', 'Massage Devices']
    ],
    [
        'Ayurveda & Medical Care',
        $hpImg . 'product_glucometer.png',
        ['Herbal Supplements', 'Ayurvedic Oils', 'Herbal Teas', 'Natural Wellness', 'Immunity Boosters']
    ],
];

$specialists = [
    ['🩸', 'For Diabetes Management'],
    ['❤️', 'For Heart Health'],
    ['⚖️', 'For Weight Loss'],
    ['🛡️', 'For Immunity Boost'],
    ['🦴', 'For Joint Pain Relief'],
    ['🌸', "For Women's Health"],
    ['👶', 'For Baby Care'],
    ['🦵', 'For Bone & Wellness'],
];

$doctorProducts = [
    ['Accu-Chek', 'Accu-Chek Active', $hpImg . 'product_glucometer.png', '₹1,299', '₹1,699', '24% off', 412],
    ['Omron', 'Omron HEM-7120 BP Monitor', $hpImg . 'product_bp_monitor.png', '₹2,499', '₹3,299', '24% off', 786],
    ['Dr. Morepen', 'Dr. Morepen BP-09 BP Monitor', $hpImg . 'product_bp_monitor.png', '₹1,850', '₹2,499', '26% off', 234],
    ['Rossmax', 'Rossmax Digital Thermometer', $hpImg . 'product_thermometer.png', '₹599', '₹899', '33% off', 312],
    ['Dr. Odin', 'Dr. Odin Pulse Oximeter', $hpImg . 'product_pulse_oximeter.png', '₹899', '₹1,299', '31% off', 189],
    ['Beurer', 'Beurer FT-09 Thermometer', $hpImg . 'product_thermometer.png', '₹799', '₹1,199', '33% off', 456],
];

$concerns = [
    ['🩸', '280+', 'Diabetes Care Products'],
    ['❤️', '120+', 'Heart Health Products'],
    ['💊', '300+', 'Vitamin & Supp. Products'],
    ['⚖️', '330+', 'Weight Management Products'],
    ['🛡️', '145+', 'Immunity Support Products'],
    ['😴', '220+', 'Sleep & Stress Products'],
];

$dealProducts = [
    ['Beurer', 'Beurer FT-09 Thermometer', $hpImg . 'product_thermometer.png', '₹799', '₹1,199', '33% off', 456, '30% OFF'],
    ['OneTouch', 'OneTouch Select Plus Glucometer', $hpImg . 'product_glucometer.png', '₹1,549', '₹2,099', '26% off', 678, '25% OFF'],
    ['Accu-Sure', 'Accu-Sure GSM BP Monitor', $hpImg . 'product_bp_monitor.png', '₹1,899', '₹2,699', '30% off', 312, '30% OFF'],
    ['Omron', 'Omron HN-288 Weighing Scale', $hpImg . 'product_weighing_scale.png', '₹1,799', '₹2,499', '28% off', 234, '20% OFF'],
    ['Beurer', 'Beurer EM-44 Pulse Massager', $hpImg . 'product_pulse_oximeter.png', '₹5,799', '₹7,999', '28% off', 145, '28% OFF'],
    ['Dr. Odin', 'Dr. Odin Pulse Oximeter', $hpImg . 'product_pulse_oximeter.png', '₹899', '₹1,299', '31% off', 189, '22% OFF'],
];

$brands = [
    ['Accu-Chek', '#e8323e', 'ACCU-CHEK'],
    ['Omron', '#0066b3', 'OMRON'],
    ['Dr. Morepen', '#00a884', 'Dr.Morepen'],
    ['Beurer', '#333', 'beurer'],
    ['Rossmax', '#c41e3a', 'ROSSMAX'],
    ['OneTouch', '#005eb8', 'OneTouch'],
    ['Dr. Odin', '#1a7a4e', 'Dr.Odin'],
];

$whyShop = [
    ['✓', 'Genuine Products', '100% authentic products sourced directly from brands and certified distributors'],
    ['🎧', 'Expert Support', '24×7 customer care with certified health advisors to assist you'],
    ['🔒', 'Secure Payments', '256-bit SSL encryption for all payment transactions — your data is safe'],
    ['↩', 'Easy Returns', 'Hassle-free 30-day return policy with free pickup from your doorstep'],
    ['🚀', 'Fast Delivery', 'Same-day delivery in 50+ cities and express delivery on every order'],
];

$articles = [
    ['Diabetes Care', 'How to Control Diabetes Naturally at Home', 'May 3, 2024', '5 min read', $hpImg . 'product_glucometer.png'],
    ['Heart Health', '10 Tips for a Healthy Heart — Diet & Lifestyle', 'May 3, 2024', '4 min read', $hpImg . 'product_bp_monitor.png'],
    ['Immunity', 'Strengthen Your Immunity with These Superfoods', 'May 3, 2024', '6 min read', $hpImg . 'product_pulse_oximeter.png'],
    ['Weight Loss', 'Best Foods for Weight Loss — Nutritionist Picks', 'May 3, 2024', '7 min read', $hpImg . 'product_weighing_scale.png'],
    ['Sleep Health', 'How to Improve Sleep Quality — Expert Tips', 'May 3, 2024', '5 min read', $hpImg . 'product_thermometer.png'],
    ['Buying Guide', 'Guide to Blood Pressure Monitoring at Home', 'May 3, 2024', '8 min read', $hpImg . 'hero_products.png'],
];

require __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">


<style>
    /* ════════════════════════════════════════
       STORE HEADER (topbar + main nav)
    ════════════════════════════════════════ */
    .hp-topbar {
        background: var(--hp-green);
        border-bottom: none;
        font-size: 12px;
        color: rgba(255, 255, 255, 0.92);
        padding: 8px 0;
    }

    .hero.relative {
        min-height: 70vh;
        padding: 80px 0 0;
    }

    .hp-topbar-inner,
    .hp-header-inner {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: nowrap;
    }

    .hp-header-inner .hamburger {
        display: none;
    }

    .hp-topbar-left,
    .hp-topbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .hp-topbar a {
        color: rgba(255, 255, 255, 0.92);
        text-decoration: none;
        transition: color 0.2s;
    }

    .hp-topbar a:hover {
        color: #fff;
    }

    .hp-header {
        background: #fff;
        border-bottom: 1px solid #e8ece9;
        padding: 14px 0;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: 0 1px 8px rgba(0, 0, 0, 0.04);
    }

    .hp-logo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 22px;
        font-weight: 800;
        color: #0d1f12;
        text-decoration: none;
        flex-shrink: 0;
    }

    .hp-logo-icon {
        width: 34px;
        height: 34px;
        background: var(--hp-green);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 20px;
        font-weight: 700;
        line-height: 1;
    }

    .hp-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 1;
        justify-content: center;
    }

    .hp-nav a {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 8px 12px;
        font-size: 13.5px;
        font-weight: 600;
        color: #1a2e20;
        text-decoration: none;
        border-radius: 8px;
        transition: background 0.2s, color 0.2s;
        white-space: nowrap;
    }

    .hp-nav a:hover {
        background: var(--hp-green-light);
        color: var(--hp-green);
    }

    .hp-nav .caret {
        font-size: 10px;
        opacity: 0.5;
    }

    .hp-search-wrap {
        display: flex;
        align-items: stretch;
        flex: 1;
        max-width: 340px;
        border: 1.5px solid #d4ead9;
        border-radius: 10px;
        overflow: hidden;
        background: #f8faf9;
    }

    .hp-search-wrap input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 10px 14px;
        font-size: 13px;
        font-family: inherit;
        outline: none;
        color: #1a2e20;
        min-width: 0;
    }

    .hp-search-btn {
        background: var(--hp-green);
        color: #fff;
        padding: 0 20px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: background 0.2s;
    }

    .hp-search-btn:hover {
        background: var(--hp-green-dark);
    }

    .hp-cart {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        border: 1.5px solid #e4ede7;
        background: #f8faf9;
        cursor: pointer;
        flex-shrink: 0;
        font-size: 20px;
        transition: border-color 0.2s;
    }

    .hp-cart:hover {
        border-color: #1a7a4e;
    }

    .hp-cart-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: var(--hp-green);
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ════════════════════════════════════════
       STORE FOOTER
    ════════════════════════════════════════ */
    .hp-footer {
        background: #121212;
        color: #b0b8b3;
        padding: 56px 0 0;
        font-size: 13px;
    }

    .hp-footer .container {
        display: block;
    }

    .hp-footer-grid {
        display: grid;
        grid-template-columns: 1.4fr repeat(4, 1fr) 1.1fr;
        gap: 24px;
        padding-bottom: 40px;
        border-bottom: 1px solid #2a2a2a;
    }

    .hp-footer-brand .hp-logo {
        color: #fff;
        margin-bottom: 14px;
    }

    .hp-footer-brand p {
        line-height: 1.7;
        margin-bottom: 18px;
        max-width: 280px;
    }

    .hp-social {
        display: flex;
        gap: 10px;
    }

    .hp-social a {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #2a2a2a;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        transition: background 0.2s;
    }

    .hp-social a:hover {
        background: #1a7a4e;
    }

    .hp-footer-col h5 {
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .hp-footer-col ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .hp-footer-col li {
        margin-bottom: 10px;
    }

    .hp-footer-col a {
        color: #b0b8b3;
        text-decoration: none;
        transition: color 0.2s;
    }

    .hp-footer-col a:hover {
        color: #2eb87a;
    }

    .hp-footer-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
        padding: 20px 0;
    }

    .hp-footer-payments h5 {
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 14px;
    }

    .hp-pay-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .hp-payments span {
        font-size: 11px;
        color: #6b7a70;
        margin-right: 4px;
    }

    .hp-pay-icon {
        background: #2a2a2a;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        letter-spacing: 0.03em;
    }

    .hp-copyright {
        text-align: center;
        padding: 14px 0 20px;
        font-size: 12px;
        color: #6b7a70;
        border-top: 1px solid #2a2a2a;
    }

    /* ════════════════════════════════════════
       HERO BANNER
    ════════════════════════════════════════ */
    .hero {
        background: linear-gradient(180deg, #f8fbf9 0%, #fff 100%);
        overflow: hidden;
        position: relative;
    }

    .hero-inner {
        display: grid;
        grid-template-columns: 1fr 1fr;
        align-items: center;
        /* gap: 32px; */
        gap: 0px;
        min-height: 380px;
        padding: 48px 0;
    }

    .hero-content {
        position: relative;
        z-index: 2;
    }

    .hero-tag {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #d4f0e4;
        color: #1a7a4e;
        font-size: 12px;
        font-weight: 700;
        padding: 5px 14px;
        border-radius: 999px;
        margin-bottom: 16px;
        letter-spacing: 0.04em;
        margin-right: auto;
        width: fit-content;
    }

    .hero h1 {
        font-size: 48px;
        font-weight: 700;
        line-height: 1.1;
        color: #0d1f12;
        margin-bottom: 14px;
        text-align: left;
    }

    .hero h1 span {
        color: #1fa97c;
    }

    .hero-sub {
        font-size: 16px;
        line-height: 1.7;
        color: #4e6e56;
        margin-bottom: 28px;
        max-width: 420px;
        text-align: left;
    }

    .hero-badges {
        display: flex;
        /* flex-wrap: wrap; */
        gap: 16px;
        margin-bottom: 28px;
    }

    .badge {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 600;
        text-align: left;
        white-space: nowrap;
    }

    .badge .bdg-ic {
        font-size: 16px;
    }

    .hero-btns {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #1a7a4e;
        color: #fff;
        padding: 13px 28px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.22s;
        box-shadow: 0 6px 20px rgba(26, 122, 78, 0.3);
    }

    .btn-primary:hover {
        background: #145f3c;
        transform: translateY(-2px);
        /* box-shadow: 0 10px 28px rgba(26, 122, 78, 0.38); */
    }

    .btn-outline {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 2px solid #1a7a4e;
        color: #1a7a4e;
        padding: 11px 28px;
        border-radius: 999px;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.22s;
    }

    .btn-outline:hover {
        background: #1a7a4e;
        color: #fff;
        transform: translateY(-2px);
    }

    .hero-img-wrap {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .hero-img-wrap img {
        width: 100%;
        max-width: 480px;
        border-radius: 20px;
        filter: drop-shadow(0 20px 40px rgba(26, 122, 78, 0.15));
    }

    .discount-badge {
        position: absolute;
        top: 16px;
        right: 16px;
        background: var(--hp-green);
        color: #fff;
        width: 96px;
        height: 96px;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        box-shadow: 0 8px 24px rgba(0, 168, 132, 0.35);
        text-align: center;
        line-height: 1.15;
        padding: 10px;
    }

    .discount-badge .pct {
        font-size: 13px;
        font-weight: 800;
        line-height: 1.2;
    }

    .discount-badge .off {
        font-size: 10px;
        font-weight: 700;
        opacity: 0.95;
    }

    .hero .container,
    .conditions .container,
    .categories .container,
    .specialist .container,
    .dr-products .container,
    .brands .container,
    .articles .container,
    .why-us .container,
    .mega-menu .container,
    .concerns .container,
    .deals .container,
    .trust-strip .container,
    .newsletter .container,
    .hp-footer .container {
        display: block;
    }


    .hero .hero-inner {
        max-width: none;
    }

    /* ════════════════════════════════════════
       SECTION HEADER
    ════════════════════════════════════════ */
    .sec-head {
        text-align: center;
        margin-bottom: 36px;
    }

    .sec-head h2 {
        font-size: clamp(22px, 3vw, 34px);
        font-weight: 800;
        color: #0d1f12;
        margin-bottom: 8px;
    }

    .sec-head p {
        font-size: 14px;
        color: #6b8a72;
        line-height: 1.6;
    }

    .sec-head .sec-sub {
        font-size: 13px;
        color: #8eab97;
    }

    /* ════════════════════════════════════════
       CONDITION SECTION
    ════════════════════════════════════════ */
    .conditions {
        background: #fff;
        padding: 60px 0;
    }

    .cond-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }

    .cond-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 20px 12px;
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e8ece9;
        cursor: pointer;
        transition: all 0.22s;
        text-align: center;
    }

    .cond-card:hover {
        border-color: var(--hp-green);
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0, 168, 132, 0.12);
    }

    .cond-icon-wrap {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .cond-icon-wrap.c-red {
        background: linear-gradient(135deg, #ffe8e6, #ffb4ae);
    }

    .cond-icon-wrap.c-pink {
        background: linear-gradient(135deg, #ffe0ec, #ffb3cc);
    }

    .cond-icon-wrap.c-amber {
        background: linear-gradient(135deg, #fff3d6, #ffd98a);
    }

    .cond-icon-wrap.c-teal {
        background: linear-gradient(135deg, #d4f5e9, #8fdcb8);
    }

    .cond-icon-wrap.c-orange {
        background: linear-gradient(135deg, #ffe8d6, #ffc48a);
    }

    .cond-icon-wrap.c-blue {
        background: linear-gradient(135deg, #dceeff, #9ecfff);
    }

    .cond-icon-wrap.c-purple {
        background: linear-gradient(135deg, #ede5ff, #c9b3ff);
    }

    .cond-icon-wrap.c-rose {
        background: linear-gradient(135deg, #fce4f0, #f5a8cc);
    }

    .cond-icon-wrap.c-sky {
        background: linear-gradient(135deg, #d6f0ff, #8fd4ff);
    }

    .cond-name {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--hp-ink);
        line-height: 1.3;
    }

    /* ════════════════════════════════════════
       CATEGORY SECTION
    ════════════════════════════════════════ */
    .categories {
        padding: 60px 0;
        background: #f6f9f7;
    }

    .cat-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
    }

    .cat-card {
        background: #fff;
        border-radius: 12px;
        padding: 0;
        border: 1px solid #e8ece9;
        cursor: pointer;
        transition: all 0.22s;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        text-align: left;
    }

    .cat-card:hover {
        border-color: var(--hp-green);
        transform: translateY(-3px);
        box-shadow: 0 10px 28px rgba(0, 168, 132, 0.1);
    }

    .cat-img-wrap {
        height: 130px;
        background: linear-gradient(180deg, #f4f8f6 0%, #eef4f1 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 14px;
        border-bottom: 1px solid #eef2ef;
    }

    .cat-img-wrap img {
        max-width: 100%;
        max-height: 110px;
        object-fit: contain;
        filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.08));
    }

    .cat-card-body {
        padding: 14px 14px 16px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .cat-name {
        font-size: 13px;
        font-weight: 700;
        color: var(--hp-ink);
        margin-bottom: 8px;
        line-height: 1.35;
    }

    .cat-list {
        font-size: 11.5px;
        color: var(--hp-mute);
        margin: 0 0 10px;
        line-height: 1.65;
        flex: 1;
        list-style: none;
        padding: 0;
    }

    .cat-list li {
        list-style: none;
        position: relative;
        padding-left: 10px;
    }

    .cat-list li::before {
        content: '•';
        position: absolute;
        left: 0;
        color: var(--hp-green);
    }

    .cat-view-all {
        font-size: 12px;
        font-weight: 600;
        color: var(--hp-green);
        margin-top: auto;
    }





    .health-section {
        max-width: 1200px;
        margin: auto;
        padding: 50px 20px;
        text-align: center;
    }

    .health-section h2 {
        font-size: clamp(22px, 3vw, 34px);
        font-weight: 800;
        color: #0d1f12;
        margin-bottom: 8px;
    }

    .subtitle {
        color: #6b7280;
        margin-bottom: 35px;
        font-size: 14px;
    }

    .health-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }

    .health-card {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 18px 20px;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.25s ease;
    }

    .health-card:hover {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
        transform: translateY(-2px);
    }

    .icon-box {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .health-card span {
        font-size: 15px;
        font-weight: 500;
        color: #111827;
    }

    .view-btn {
        margin-top: 35px;
        padding: 12px 28px;
        border-radius: 999px;
        background: #ffffff;
        border: 2px solid #c7ead9;
        color: #0f766e;
        font-size: 14px;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .view-btn:hover {
        background: #ecfdf5;
    }





    /* View All */
    .view-all-wrap {
        text-align: center;
        margin-top: 28px;
    }

    .btn-view-all {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1.5px solid var(--hp-green);
        color: var(--hp-green);
        padding: 10px 28px;
        border-radius: 999px;
        font-size: 13.5px;
        font-weight: 600;
        transition: all 0.2s;
        background: transparent;
        cursor: pointer;
    }

    .btn-view-all:hover {
        background: var(--hp-green);
        color: #fff;
    }

    /* ════════════════════════════════════════
       SPECIALIST RECOMMENDATION
    ════════════════════════════════════════ */
    .specialist {
        background: #fff;
        padding: 48px 0;
    }

    .spec-pills {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .spec-pill {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #f6f9f7;
        border: 1.5px solid #e4ede7;
        border-radius: 12px;
        padding: 14px 18px;
        font-size: 13px;
        font-weight: 600;
        color: #1a2e20;
        cursor: pointer;
        transition: all 0.2s;
        justify-content: flex-start;
    }

    .spec-pill:hover {
        background: #f6f9f7;
        color: #000;
        border-color: rgb(156 186 245);
    }

    .spec-pill .sicon {
        font-size: 18px;
    }

    /* ════════════════════════════════════════
       DOCTOR RECOMMENDED PRODUCTS
    ════════════════════════════════════════ */
    .dr-products {
        background: #f6f9f7;
        padding: 60px 0;
    }

    .products-scroll,
    .deals-grid {
        display: flex;
        gap: 14px;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        padding-bottom: 6px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        scrollbar-color: var(--hp-green) #eef2ef;
    }

    .products-scroll::-webkit-scrollbar,
    .deals-grid::-webkit-scrollbar {
        height: 5px;
    }

    .products-scroll::-webkit-scrollbar-thumb,
    .deals-grid::-webkit-scrollbar-thumb {
        background: var(--hp-green);
        border-radius: 4px;
    }

    .products-scroll .prod-card,
    .deals-grid .prod-card {
        flex: 0 0 calc((100% - 70px) / 6);
        min-width: 168px;
        scroll-snap-align: start;
    }

    .prod-card {
        background: #fff;
        border-radius: 16px;
        border: 1.5px solid #e4ede7;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.22s;
        position: relative;
    }

    .prod-card:hover {
        border-color: var(--hp-green);
        transform: translateY(5px);
        box-shadow: 0 12px 32px rgba(0, 168, 132, 0.12);
    }

    .prod-badge-top {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #e8323e;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        z-index: 99;
    }

    .prod-badge-top.sale {
        background: #1a7a4e;
    }

    .prod-badge-top.new {
        background: #f59e0b;
        color: #1a2e20;
    }

    .prod-img-wrap {
        width: 100%;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f0faf4, #e8f5ee);
        padding: 16px;
        overflow: hidden;
    }

    .prod-img-wrap img {
        max-height: 130px;
        object-fit: contain;
        transition: transform 0.3s;
    }

    .prod-card:hover .prod-img-wrap img {
        transform: scale(1.06);
    }

    .prod-img-placeholder {
        width: 100%;
        height: 130px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
    }

    .prod-info {
        padding: 12px 14px 14px;
    }

    .prod-brand {
        font-size: 11px;
        color: #8eab97;
        font-weight: 500;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .prod-name {
        font-size: 13px;
        font-weight: 600;
        color: #0d1f12;
        margin-bottom: 6px;
        line-height: 1.4;
    }

    .prod-stars {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 8px;
    }

    .stars {
        color: #f59e0b;
        font-size: 12px;
    }

    .prod-stars .count {
        font-size: 11px;
        color: #8eab97;
    }

    .prod-price {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    .price-now {
        font-size: 15px;
        font-weight: 800;
        color: var(--hp-green);
    }

    .price-old {
        font-size: 12px;
        color: #8eab97;
        text-decoration: line-through;
    }

    .price-off {
        font-size: 11px;
        color: var(--hp-green);
        font-weight: 700;
    }

    .btn-add-cart {
        width: 100%;
        background: var(--hp-green);
        color: #fff;
        padding: 9px;
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }

    .btn-add-cart:hover {
        background: var(--hp-green-dark);
    }

    /* ════════════════════════════════════════
       POPULAR HEALTH CONCERNS
    ════════════════════════════════════════ */
    .concerns {
        background: #f4f7f5;
        padding: 44px 0;
        border-top: 1px solid #eef2ef;
        border-bottom: 1px solid #eef2ef;
    }

    .concerns-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 16px;
    }

    .concern-card {
        background: transparent;
        border-radius: 0;
        padding: 16px 8px;
        text-align: center;
        border: none;
        cursor: pointer;
        transition: all 0.22s;
        border: 1px solid #e5e7eb;
        border-radius: 15px;
    }

    .concern-card:hover {
        transform: translateY(-2px);
    }

    .concern-icon {
        font-size: 28px;
        margin-bottom: 8px;
    }

    .concern-count {
        font-size: 17px;
        font-weight: 800;
        color: var(--hp-green);
        line-height: 1.2;
    }

    .concern-label {
        font-size: 11px;
        color: #4e6e56;
        font-weight: 600;
        margin-top: 4px;
        line-height: 1.4;
    }

    .concern-desc {
        display: none;
    }

    /* ════════════════════════════════════════
       BEST DEALS
    ════════════════════════════════════════ */
    .deals {
        background: #f6f9f7;
        padding: 60px 0;
    }

    .brands {
        background: #fff;
        padding: 48px 0;
        border-top: 1px solid #eef2ef;
    }

    .deals-grid {
        /* carousel — see .products-scroll, .deals-grid flex rule */
    }

    .brands-row {
        display: flex;
        flex-wrap: nowrap;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        overflow-x: auto;
        padding: 8px 0;
    }

    .brand-logo {
        flex: 0 0 auto;
        font-size: 15px;
        font-weight: 800;
        letter-spacing: 0.02em;
        padding: 12px 20px;
        border: 1px solid #e8ece9;
        border-radius: 10px;
        background: #fff;
        filter: grayscale(1);
        opacity: 0.75;
        transition: all 0.2s;
        cursor: pointer;
        white-space: nowrap;
    }

    .brand-logo:hover {
        filter: none;
        opacity: 1;
        border-color: var(--hp-green);
        box-shadow: 0 4px 16px rgba(0, 168, 132, 0.1);
    }

    /* ════════════════════════════════════════
       WHY US
    ════════════════════════════════════════ */
    .why-us {
        background: #fff;
        padding: 60px 0;
        border-top: 1px solid #e4ede7;
    }

    .why-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 24px;
    }

    .why-card {
        text-align: center;
        padding: 20px 10px;
    }

    .why-icon-ring {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        border: 2px solid var(--hp-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin: 0 auto 14px;
        color: var(--hp-green);
        background: var(--hp-green-light);
    }

    .why-title {
        font-size: 14px;
        font-weight: 700;
        color: #0d1f12;
        margin-bottom: 8px;
    }

    .why-desc {
        font-size: 12px;
        color: #6b8a72;
        line-height: 1.55;
    }

    /* ════════════════════════════════════════
       ARTICLES
    ════════════════════════════════════════ */
    .articles {
        background: #f6f9f7;
        padding: 60px 0;
    }

    .articles-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }

    .article-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        border: 1.5px solid #e4ede7;
        cursor: pointer;
        transition: all 0.22s;
    }

    .article-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
        border-color: var(--hp-green);
    }

    .article-img {
        width: 100%;
        height: 150px;
        object-fit: contain;
        background: #f4f8f6;
        padding: 12px;
    }

    .article-img-placeholder {
        width: 100%;
        height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
    }

    .article-body {
        padding: 16px;
    }

    .article-tag {
        font-size: 10px;
        font-weight: 700;
        color: #1a7a4e;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 6px;
    }

    .article-title {
        font-size: 13px;
        font-weight: 700;
        color: #0d1f12;
        margin-bottom: 6px;
        line-height: 1.4;
    }

    .article-meta {
        font-size: 11px;
        color: #8eab97;
    }

    /* ════════════════════════════════════════
       NEWSLETTER
    ════════════════════════════════════════ */
    .newsletter {
        background: #eef4f8;
        padding: 60px 0;
    }

    .newsletter-inner {
        background: transparent;
        border-radius: 0;
        padding: 0;
        display: grid;
        grid-template-columns: 1fr auto auto;
        align-items: center;
        gap: 40px;
        flex-wrap: nowrap;
        border: none;
    }

    .newsletter-content {
        max-width: 480px;
    }

    .newsletter-content h3 {
        font-size: 26px;
        font-weight: 800;
        color: #0d1f12;
        margin-bottom: 8px;
    }

    .newsletter-content p {
        font-size: 14px;
        color: #4e6e56;
        margin-bottom: 18px;
    }

    .newsletter-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .newsletter-perks {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .newsletter-perk {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #1a2e20;
    }

    .newsletter-perk span {
        font-size: 20px;
    }

    .newsletter-art {
        flex-shrink: 0;
    }

    .newsletter-art svg {
        width: 150px;
        height: auto;
        display: block;
    }

    .nl-input {
        padding: 13px 18px;
        border: 1.5px solid #c0e0cf;
        border-radius: 10px;
        font-size: 14px;
        font-family: inherit;
        background: #fff;
        min-width: 260px;
        outline: none;
        color: #1a2e20;
    }

    .nl-input:focus {
        border-color: #1a7a4e;
    }

    .nl-btn {
        background: #0d1f12;
        color: #fff;
        padding: 13px 28px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }

    .nl-btn:hover {
        background: #1a2e20;
    }

    /* ════════════════════════════════════════
       TRUST STRIP
    ════════════════════════════════════════ */
    .trust-strip {
        background: #1a7a4e;
        padding: 48px 0;
    }

    .trust-banner-head {
        text-align: center;
        margin-bottom: 32px;
        color: #fff;
    }

    .trust-banner-head h2 {
        font-size: clamp(20px, 3vw, 28px);
        font-weight: 800;
        margin-bottom: 8px;
    }

    .trust-banner-head p {
        font-size: 14px;
        opacity: 0.9;
    }

    .trust-row {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 32px;
        align-items: center;
    }

    .trust-item {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #fff;
    }

    .trust-icon {
        font-size: 24px;
    }

    .trust-info {}

    .trust-val {
        font-size: 14px;
        font-weight: 800;
    }

    .trust-label {
        font-size: 11px;
        opacity: 0.8;
    }

    /* ════════════════════════════════════════
       COMING SOON MODAL
    ════════════════════════════════════════ */
    .modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(13, 31, 18, 0.72);
        backdrop-filter: blur(6px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-box {
        background: #fff;
        border-radius: 28px;
        padding: 52px 48px;
        max-width: 520px;
        width: 100%;
        text-align: center;
        box-shadow: 0 32px 80px rgba(0, 0, 0, 0.25);
        position: relative;
        transform: scale(0.88) translateY(20px);
        transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .modal-overlay.active .modal-box {
        transform: scale(1) translateY(0);
    }

    .modal-close {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #f0f4f1;
        font-size: 18px;
        color: #4e6e56;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s, color 0.2s;
    }

    .modal-close:hover {
        background: #e8323e;
        color: #fff;
    }

    .modal-rocket {
        font-size: 72px;
        margin-bottom: 20px;
        display: block;
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-10px);
        }
    }

    .modal-title {
        font-size: 28px;
        font-weight: 800;
        color: #0d1f12;
        margin-bottom: 12px;
        line-height: 1.2;
    }

    .modal-title span {
        color: #1a7a4e;
    }

    .modal-sub {
        font-size: 16px;
        color: #4e6e56;
        line-height: 1.6;
        margin-bottom: 28px;
    }

    .modal-prod-name {
        background: linear-gradient(135deg, #e8f5ee, #d4f0e4);
        border: 1.5px solid #c0e0cf;
        border-radius: 12px;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 700;
        color: #0d1f12;
        margin-bottom: 24px;
    }

    .modal-notify-form {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
    }

    .modal-notify-form input {
        flex: 1;
        padding: 12px 16px;
        border: 1.5px solid #d6e8dc;
        border-radius: 10px;
        font-size: 13.5px;
        font-family: inherit;
        outline: none;
        color: #1a2e20;
    }

    .modal-notify-form input:focus {
        border-color: #1a7a4e;
    }

    .modal-notify-btn {
        background: #1a7a4e;
        color: #fff;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        transition: background 0.2s;
        white-space: nowrap;
    }

    .modal-notify-btn:hover {
        background: #145f3c;
    }

    .modal-back {
        font-size: 13px;
        color: #8eab97;
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .modal-back:hover {
        color: #1a7a4e;
    }

    .modal-dots {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-bottom: 24px;
    }

    .modal-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #d4f0e4;
        animation: dotPulse 1.5s ease-in-out infinite;
    }

    .modal-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .modal-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes dotPulse {

        0%,
        100% {
            background: #d4f0e4;
            transform: scale(1);
        }

        50% {
            background: #1a7a4e;
            transform: scale(1.3);
        }
    }

    /* ════════════════════════════════════════
       MOBILE HAMBURGER
    ════════════════════════════════════════ */
    .hamburger {
        display: none;
        flex-direction: column;
        gap: 5px;
        cursor: pointer;
        padding: 4px;
    }

    .hamburger span {
        display: block;
        width: 24px;
        height: 2px;
        background: #1a2e20;
        border-radius: 2px;
        transition: all 0.3s;
    }

    /* ════════════════════════════════════════
       RESPONSIVE
    ════════════════════════════════════════ */
    @media (max-width: 1100px) {
        .hp-nav {
            display: none;
        }

        .hp-header-inner .hamburger {
            display: flex;
        }

        .cond-grid {
            grid-template-columns: repeat(4, 1fr);
        }

        .cat-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .products-scroll .prod-card,
        .deals-grid .prod-card {
            flex: 0 0 calc((100% - 42px) / 3);
            min-width: 160px;
        }

        .spec-pills {
            grid-template-columns: repeat(2, 1fr);
        }

        .products-scroll {
            grid-template-columns: repeat(3, 1fr);
        }

        .deals-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .why-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .articles-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .hp-footer-grid {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .concerns-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .newsletter-inner {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .newsletter-perks {
            flex-direction: row;
            justify-content: center;
            flex-wrap: wrap;
        }

        .newsletter-art {
            display: none;
        }
    }

    @media (max-width: 768px) {

        .hp-topbar-left span:not(:first-child),
        .hp-topbar-right {
            display: none;
        }

        .hp-search-wrap {
            max-width: none;
            flex: 1;
        }

        .hero-inner {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .hero-img-wrap {
            display: none;
        }

        .hero-badges {
            justify-content: center;
        }

        .hero-btns {
            justify-content: center;
        }

        .cond-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .cat-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .products-scroll {
            grid-template-columns: repeat(2, 1fr);
        }

        .deals-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .why-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .articles-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .footer-grid {
            grid-template-columns: 1fr 1fr;
        }

        .hp-footer-grid {
            grid-template-columns: 1fr 1fr;
        }

        .concerns-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .newsletter-inner {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .cat-grid {
            grid-template-columns: 1fr;
        }

        .cat-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .products-scroll .prod-card,
        .deals-grid .prod-card {
            flex: 0 0 calc(50% - 8px);
            min-width: 150px;
        }

        .spec-pills {
            grid-template-columns: 1fr;
        }

        .products-scroll {
            grid-template-columns: repeat(2, 1fr);
        }

        .deals-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .why-grid {
            grid-template-columns: 1fr;
        }

        .articles-grid {
            grid-template-columns: 1fr;
        }

        .footer-grid {
            grid-template-columns: 1fr;
        }

        .hp-footer-grid {
            grid-template-columns: 1fr;
        }

        .cond-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .modal-box {
            padding: 36px 24px;
        }

        .modal-title {
            font-size: 22px;
        }

        .modal-notify-form {
            flex-direction: column;
        }
    }

    /* ════════════════════════════════════════
       UTILITY
    ════════════════════════════════════════ */
    .section-divider {
        border: none;
        border-top: 1px solid #e4ede7;
    }

    .reveal-anim {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.55s ease, transform 0.55s ease;
    }

    .reveal-anim.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .hero-bg-image {
        position: absolute;
        z-index: 0;
        user-select: none;
        right: 10%;
        max-width: 40%;
        height: 70%;
        display: flex;
    }

    .hero-bg-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center center;
        display: block;
    }

    .shop-list,
    .product-slider-list {
        display: flex;
        margin: 0 -20px;
        width: 100%;
    }

    .shop-list .items,
    .product-slider-list .items {
        padding: 20px;
    }

    .shop-list .icon-box {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1.5px solid #1fa97c;
        box-shadow: 0 0 10px 0hsl(160, 69.00%, 0.2);
        overflow: hidden;
        transition: all 0.3s ease;
        -webkit-transition: all 0.3s ease;
        -moz-transition: all 0.3s ease;
        -ms-transition: all 0.3s ease;
        -o-transition: all 0.3s ease;
    }

    .shop-list .icon-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        object-position: center center;
        display: block;

    }

    .shop-list .shop-card:hover .icon-box {
        box-shadow: 0 0px 30px 0 rgba(0, 128, 96, 0.25);
        transform: scale(1.05);
        -webkit-transform: scale(1.05);
        -moz-transform: scale(1.05);
        -ms-transform: scale(1.05);
        -o-transform: scale(1.05);
    }

    .shop-list .shop-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .shop-list .shop-card h4 {
        font-size: 20px;
        list-style: none;
        line-height: 1.2;
        font-weight: 400;
        color: var(--ink);
        text-align: center;
    }


    @media (prefers-color-scheme: light) {
        :root {
            background-color: #fff;
            color: #213547;
        }
    }

    img {
        block-size: auto;
        max-inline-size: 100%;
        vertical-align: middle;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-family: "Georgia", serif;
        font-weight: 700;
        line-height: 1.1;
        margin-block: 1.25rem;
    }

    /* ── Layout ── */
    .container {
        inline-size: 100%;
    }

    /* .swiper-section-product .container {
        max-width: 1200px;
        margin: auto;
         display: flex; 
        gap: 60px;
        align-items: center;
    } */

    .swiper-section-product .container>* {
        margin-inline: auto;
        max-inline-size: min(840px, calc(100% - 2.5rem));
    }

    .container>.alignwide {
        max-inline-size: 1080px;
    }

    /* ── Swiper ── */
    .swiper {
        max-inline-size: 1240px;
        margin-inline: auto;
        padding-block: 2rem;
    }

    /* Slides */
    .swiper-slide {
        width: 475px;
        opacity: 0;
        transition-property: opacity, transform;
    }

    .swiper-slide img {
        border-radius: 8px;
        display: block;
        width: 100%;
        height: auto;
    }

    /* Active slide — fully visible */
    .swiper-slide.swiper-slide-active {
        opacity: 1;
    }

    /* Adjacent slides (prev / next) — visible with overlay */
    .swiper-slide.swiper-slide-prev,
    .swiper-slide.swiper-slide-next,
    .swiper-slide:has(+ .swiper-slide-prev),
    .swiper-slide.swiper-slide-next+.swiper-slide {
        display: grid;
        grid-template-areas: "stack";
        opacity: 1;
    }

    .swiper-slide.swiper-slide-prev img,
    .swiper-slide.swiper-slide-next img,
    .swiper-slide:has(+ .swiper-slide-prev) img,
    .swiper-slide.swiper-slide-next+.swiper-slide img {
        grid-area: stack;
    }

    /* Overlay pseudo-element */
    .swiper-slide.swiper-slide-prev::after,
    .swiper-slide.swiper-slide-next::after {
        background-color: rgba(255, 255, 255, 0.10);
        content: "";
        display: block;
        grid-area: stack;
        border-radius: 8px;
    }

    .swiper-slide:has(+ .swiper-slide-prev)::after,
    .swiper-slide.swiper-slide-next+.swiper-slide::after {
        background-color: rgba(255, 255, 255, 0.15);
        content: "";
        display: block;
        grid-area: stack;
        border-radius: 8px;
    }

    /* ── Navigation Arrows ── */
    .swiper-button-prev,
    .swiper-button-next {
        color: #4da6ff;
        --swiper-navigation-size: 40px;
        transition: color 0.2s, transform 0.15s;
    }

    .swiper-button-prev:hover,
    .swiper-button-next:hover {
        color: #fff;
    }

    .swiper-button-prev:active,
    .swiper-button-next:active {
        transform: translateY(-50%) scale(0.9);
    }

    /* ── Optional heading ── */
    .slider-title {
        text-align: center;
        font-family: "Georgia", serif;
        font-size: clamp(1.728rem, 1.3448rem + 1.703vw, 2.6647rem);
        font-weight: 700;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 1.5rem;
        letter-spacing: -0.01em;
    }

    @media (prefers-color-scheme: light) {
        .slider-title {
            color: #213547;
        }
    }


    .brand-section .container {
        max-width: 1240px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .brand-slider {
        display: flex;
        align-items: center;
        margin: 0 -20px;
        width: 100%;
    }

    .brand-slider .items {
        padding: 20px;
    }

    .brand-slider .slick-list {
        overflow: visible;
        width: 100%;
    }

    .brand-section {
        padding: 50px 0;
        background: #fff;
    }


    .brand-item {
        min-width: 160px;
        height: 80px;
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #eee;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .brand-item img {
        max-width: 120px;
        max-height: 48px;
        object-fit: contain;
        filter: grayscale(100%);
        opacity: 0.8;
        transition: all 0.3s ease;
    }

    .brand-item:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
        transform: translateY(-3px);
    }

    .brand-item:hover img {
        filter: grayscale(0);
        opacity: 1;
    }
</style>

<!-- ══════════════════════════════════════
     HERO BANNER
══════════════════════════════════════ -->
<section class="hero relative">
    <div class="hero-bg-image"><img src="/assets/img/store/image.png" alt="Background image"></div>
    <div class="container">
        <div class="hero-inner">
            <div class="hero-content reveal-anim">
                <h1>Your Health,<br><span>Our Priority</span></h1>
                <p class="hero-sub">Trusted health products for you and your family. Live a healthier life with HealthPro.</p>
                <div class="hero-badges">
                    <div class="badge"><span class="bdg-ic">✅</span> 100% Authentic</div>
                    <div class="badge"><span class="bdg-ic">🔒</span> Secure Payment</div>
                    <div class="badge"><span class="bdg-ic">🚀</span> Fast Delivery</div>
                    <div class="badge"><span class="bdg-ic">↩️</span> Easy Returns</div>
                </div>
            </div>
            <!-- <div class="hero-img-wrap reveal-anim">
                <img
                    src="/assets/img/store/image.png"
                    alt="Health medical devices - glucometer, blood pressure monitor, thermometer, pulse oximeter"
                    loading="eager" />
                <!-- <div class="discount-badge">
                    <span class="pct">UP TO 30%</span>
                    <span class="off">OFF ON ALL ITEMS</span>
                </div>
            </div> -->
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     SHOP BY HEALTH CONDITION without slider
══════════════════════════════════════ -->
<!-- <section class="conditions reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Shop by Health Condition</h2>
            <p class="sec-sub">Find the right products to manage your health conditions</p>
        </div>
        <div class="cond-grid" id="condGrid">
            <?php foreach ($conditions as $c): ?>
                <div class="cond-card" data-product="<?= e($c[1]) ?> Products" data-soon>
                    <span class="cond-icon-wrap <?= e($c[2]) ?>"><?= $c[0] ?></span>
                    <span class="cond-name"><?= e($c[1]) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all-wrap">
            <button class="btn-view-all" id="viewAllCondBtn" data-soon>View All Categories →</button>
        </div>
    </div>
</section> -->

<!-- <section class="health-section">
    <div class="sec-head">
        <h2>Shop by Health Condition</h2>
        <p class="sec-sub">Find the right products to manage your health and wellness</p>
    </div>

    <div class="health-grid" id="healthGrid">
        Cards generated by JS 
    </div>

    <button class="view-btn">View All Categories</button>
</section> -->



<!-- Shop by Health Condition with slider -->

<section class="health-section">
    <div class="sec-head">
        <h2>Shop by Health Condition</h2>
        <p class="sec-sub">Find the right products to manage your health and wellness</p>
    </div>

    <div class="shop-list">


        <div class="items">
            <a href="#" class="shop-card">
                <div class="icon-box">
                    <img src="/assets/img/store/product_bp_monitor.png" alt="Health Condition">
                </div>
                <h4>Health Condition</h4>
            </a>
        </div>
        <div class="items">
            <a href="#" class="shop-card">
                <div class="icon-box">
                    <img src="/assets/img/store/product_bp_monitor.png" alt="Health Condition">
                </div>
                <h4>Health Condition</h4>
            </a>
        </div>
        <div class="items">
            <a href="#" class="shop-card">
                <div class="icon-box">
                    <img src="/assets/img/store/product_bp_monitor.png" alt="Health Condition">
                </div>
                <h4>Health Condition</h4>
            </a>
        </div>
        <div class="items">
            <a href="#" class="shop-card">
                <div class="icon-box">
                    <img src="/assets/img/store/product_bp_monitor.png" alt="Health Condition">
                </div>
                <h4>Health Condition</h4>
            </a>
        </div>
        <div class="items">
            <a href="#" class="shop-card">
                <div class="icon-box">
                    <img src="/assets/img/store/product_bp_monitor.png" alt="Health Condition">
                </div>
                <h4>Health Condition</h4>
            </a>
        </div>
        <div class="items">
            <a href="#" class="shop-card">
                <div class="icon-box">
                    <img src="/assets/img/store/product_bp_monitor.png" alt="Health Condition">
                </div>
                <h4>Health Condition</h4>
            </a>
        </div>

    </div>

</section>







<!-- ══════════════════════════════════════
     SHOP BY CATEGORY without slider
══════════════════════════════════════ -->
<!-- <section class="categories reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Shop by Category</h2>
            <p class="sec-sub">Explore our wide range of health products</p>
        </div>
        <div class="cat-grid" id="catGrid">
            <?php foreach ($categories as $cat): ?>
                <div class="cat-card" data-product="<?= e($cat[0]) ?>" data-soon>
                    <div class="cat-img-wrap">
                        <img src="<?= e($cat[1]) ?>" alt="<?= e($cat[0]) ?>" loading="lazy" />
                    </div>
                    <div class="cat-card-body">
                        <div class="cat-name"><?= e($cat[0]) ?></div>
                        <ul class="cat-list">
                            <?php foreach ($cat[2] as $sub): ?>
                                <li><?= e($sub) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <span class="cat-view-all">View All →</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->



<!-- Shop by Category with slider -->



<section class="swiper-section-product">
    <div class="swiper-section-product-container">

        <div class="sec-head">
            <h2>Shop by Category</h2>
            <p class="sec-sub">Explore our wide range of health products</p>
        </div>

        <div class="swiper alignwide">
            <div class="swiper-wrapper">

                <div class="swiper-slide">
                    <a href="https://eclinicpro.com/" target="_blank">
                        <img src="/assets/img/store/product_bp_monitor.png" alt="Photo 1" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_weighing_scale.png" alt="Photo 2" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_thermometer.png" alt="Photo 3" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_pulse_oximeter.png" alt="Photo 4" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_weighing_scale.png" alt="Photo 5" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_glucometer.png" alt="Photo 6" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_weighing_scale.png" alt="Photo 7" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_thermometer.png" alt="Photo 8" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_pulse_oximeter.png" alt="Photo 9" />
                    </a>
                </div>
                <div class="swiper-slide">
                    <a href="#">
                        <img src="/assets/img/store/product_glucometer.png" alt="Photo 10" />
                    </a>
                </div>

            </div>

            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════
     SPECIALIST RECOMMENDATION
══════════════════════════════════════ -->
<section class="specialist reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Browse by Specialist Recommendation</h2>
            <p class="sec-sub">Curated by health experts to manage your specific health needs</p>
        </div>
        <div class="spec-pills" id="specPills">
            <?php foreach ($specialists as $sp): ?>
                <div class="spec-pill" data-product="<?= e($sp[1]) ?>" data-soon>
                    <span class="sicon"><?= $sp[0] ?></span> <?= e($sp[1]) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     DOCTOR RECOMMENDED PRODUCTS
══════════════════════════════════════ -->
<section class="dr-products reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Doctor-Recommended Products</h2>
            <p class="sec-sub">Trusted by doctors to keep you healthy</p>
        </div>
        <div class="products-scroll" id="drProductsGrid">
            <?php foreach ($doctorProducts as $p): ?>
                <div class="prod-card" data-product="<?= e($p[1]) ?>" data-soon>
                    <div class="prod-img-wrap">
                        <img src="<?= e($p[2]) ?>" alt="<?= e($p[1]) ?>" loading="lazy" />
                    </div>
                    <div class="prod-info">
                        <div class="prod-brand"><?= e($p[0]) ?></div>
                        <div class="prod-name"><?= e($p[1]) ?></div>
                        <div class="prod-stars"><span class="stars">★★★★★</span><span class="count">(<?= (int) $p[6] ?>)</span></div>
                        <div class="prod-price">
                            <span class="price-now"><?= e($p[3]) ?></span>
                            <span class="price-old"><?= e($p[4]) ?></span>
                            <span class="price-off"><?= e($p[5]) ?></span>
                        </div>
                        <button class="btn-add-cart" type="button">Add to Cart</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     POPULAR HEALTH CONCERNS
══════════════════════════════════════ -->
<section class="concerns reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Popular Health Concerns</h2>
            <p class="subtitle">We're here to help you stay informed</p>
        </div>
        <div class="concerns-grid" id="concernsGrid">
            <?php foreach ($concerns as $cn): ?>
                <div class="concern-card" data-product="<?= e($cn[2]) ?>" data-soon>
                    <div class="concern-icon"><?= $cn[0] ?></div>
                    <div class="concern-count"><?= e($cn[1]) ?></div>
                    <div class="concern-label"><?= e($cn[2]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     BEST DEALS & OFFERS
══════════════════════════════════════ -->
<section class="deals reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Best Deals &amp; Offers</h2>
            <p class="sec-sub">Grab the best deals on top health products before they're gone!</p>
        </div>
        <div class="deals-grid" id="dealsGrid">
            <?php foreach ($dealProducts as $p): ?>
                <div class="prod-card" data-product="<?= e($p[1]) ?>" data-soon>
                    <span class="prod-badge-top"><?= e($p[7]) ?></span>
                    <div class="prod-img-wrap">
                        <img src="<?= e($p[2]) ?>" alt="<?= e($p[1]) ?>" loading="lazy" />
                    </div>
                    <div class="prod-info">
                        <div class="prod-brand"><?= e($p[0]) ?></div>
                        <div class="prod-name"><?= e($p[1]) ?></div>
                        <div class="prod-stars"><span class="stars">★★★★★</span><span class="count">(<?= (int) $p[6] ?>)</span></div>
                        <div class="prod-price">
                            <span class="price-now"><?= e($p[3]) ?></span>
                            <span class="price-old"><?= e($p[4]) ?></span>
                            <span class="price-off"><?= e($p[5]) ?></span>
                        </div>
                        <button class="btn-add-cart" type="button">Add to Cart</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     SHOP BY BRANDS without slider
══════════════════════════════════════ -->
<!-- <section class="brands reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Shop by Brands</h2>
            <p class="sec-sub">World-class brands you can trust</p>
        </div>
        <div class="brands-row" id="brandsRow">
            <?php foreach ($brands as $b): ?>
                <div class="brand-logo" data-product="<?= e($b[0]) ?> Products" data-soon style="color:<?= e($b[1]) ?>"><?= e($b[2]) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section> -->


<!-- Shop by Brands with slider -->

<section class="brand-section">
    <div class="container">
        <div class="brand-slider">
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/cc/Dr_reddys_logo_%281%29.jpg"
                        alt="Accu-Chek">
                </div>
            </div>
            <div class="items">

                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/be/Cipla_logo.svg/120px-Cipla_logo.svg.png"
                        alt="Omron">
                </div>
            </div>
            <div class="items">

                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Cadila_Pharmaceuticals_Logo.jpg/120px-Cadila_Pharmaceuticals_Logo.jpg"
                        alt="Dr Morepen">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fe/Logo_Sun_Pharmaceutical.png/120px-Logo_Sun_Pharmaceutical.png"
                        alt="Beurer">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/65/Ayurkey_logo_horizontal-light_734X250_colored.jpg/120px-Ayurkey_logo_horizontal-light_734X250_colored.jpg"
                        alt="Rossmax">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fb/Alkem_Laboratories_logo.png/120px-Alkem_Laboratories_logo.png"
                        alt="OneTouch">
                </div>
            </div>
            <div class="items">

                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b3/USV_%28company%29.svg/120px-USV_%28company%29.svg.png"
                        alt="Dr Odin">
                </div>
            </div>
            <div class="items">

                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/c/cc/Dr_reddys_logo_%281%29.jpg"
                        alt="Accu-Chek">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/be/Cipla_logo.svg/120px-Cipla_logo.svg.png"
                        alt="Omron">
                </div>
            </div>

            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e8/Cadila_Pharmaceuticals_Logo.jpg/120px-Cadila_Pharmaceuticals_Logo.jpg"
                        alt="Dr Morepen">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fe/Logo_Sun_Pharmaceutical.png/120px-Logo_Sun_Pharmaceutical.png"
                        alt="Beurer">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/6/65/Ayurkey_logo_horizontal-light_734X250_colored.jpg/120px-Ayurkey_logo_horizontal-light_734X250_colored.jpg"
                        alt="Rossmax">
                </div>
            </div>
            <div class="items">
                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/f/fb/Alkem_Laboratories_logo.png/120px-Alkem_Laboratories_logo.png"
                        alt="OneTouch">
                </div>
            </div>

            <div class="items">


                <div class="brand-item">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b3/USV_%28company%29.svg/120px-USV_%28company%29.svg.png"
                        alt="Dr Odin">
                </div>
            </div>

        </div>
    </div>
</section>


<!-- ══════════════════════════════════════
     WHY SHOP
══════════════════════════════════════ -->
<section class="why-us">
    <div class="container">
        <div class="sec-head">
            <h2>Why Shop From HealthPro?</h2>
            <p class="sec-sub">Shop with confidence — we've got you covered</p>
        </div>
        <div class="why-grid">
            <?php foreach ($whyShop as $w): ?>
                <div class="why-card">
                    <div class="why-icon-ring"><?= $w[0] ?></div>
                    <div class="why-title"><?= e($w[1]) ?></div>
                    <div class="why-desc"><?= e($w[2]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     HEALTH ARTICLES
══════════════════════════════════════ -->
<section class="articles reveal-anim">
    <div class="container">
        <div class="sec-head">
            <h2>Health Articles &amp; Buying Guides</h2>
            <p class="sec-sub">Stay informed with expert health tips and product guides</p>
        </div>
        <div class="articles-grid" id="articlesGrid">
            <?php foreach ($articles as $a): ?>
                <div class="article-card" data-product="<?= e($a[1]) ?>" data-soon>
                    <img class="article-img" src="<?= e($a[4]) ?>" alt="<?= e($a[1]) ?>" loading="lazy" />
                    <div class="article-body">
                        <div class="article-tag"><?= e($a[0]) ?></div>
                        <div class="article-title"><?= e($a[1]) ?></div>
                        <div class="article-meta"><?= e($a[2]) ?> · <?= e($a[3]) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="view-all-wrap">
            <button class="btn-view-all" id="viewAllArticlesBtn" data-soon>View All Articles →</button>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     NEWSLETTER
══════════════════════════════════════ -->
<section class="newsletter reveal-anim">
    <div class="container">
        <div class="newsletter-inner">
            <div class="newsletter-content">
                <h3>Shop Smart, Stay Healthy</h3>
                <p>Get the latest deals, health tips, and product launches straight to your inbox — no spam, ever.</p>
                <div class="newsletter-form">
                    <input class="nl-input" type="email" placeholder="Enter your email address" id="nlEmail" />
                    <button class="nl-btn" id="nlBtn" data-soon>Subscribe</button>
                </div>
            </div>
            <div class="newsletter-perks">
                <div class="newsletter-perk"><span>📋</span> Expert Tips</div>
                <div class="newsletter-perk"><span>🎁</span> Exclusive Offers</div>
                <div class="newsletter-perk"><span>📦</span> Product Updates</div>
            </div>
            <div class="newsletter-art" aria-hidden="true">
                <svg viewBox="0 0 160 180" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M40 55h80l-12 95H52L40 55z" fill="#00a884" opacity="0.15" />
                    <path d="M48 48h64l8 20H40l8-20z" fill="#00a884" />
                    <path d="M44 68h72v88H44V68z" fill="#fff" stroke="#00a884" stroke-width="2" />
                    <rect x="58" y="82" width="18" height="36" rx="4" fill="#e8323e" opacity="0.8" />
                    <rect x="82" y="78" width="14" height="40" rx="3" fill="#0066b3" opacity="0.8" />
                    <rect x="98" y="86" width="12" height="28" rx="3" fill="#00a884" opacity="0.9" />
                    <circle cx="80" cy="140" r="8" fill="#ffd98a" />
                </svg>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     TRUST STRIP
══════════════════════════════════════ -->
<div class="trust-strip">
    <div class="container">
        <div class="trust-banner-head">
            <h2>Your Complete Health &amp; Wellness Destination</h2>
            <p>Trusted by thousands of customers for genuine health products.</p>
        </div>
        <div class="trust-row">
            <div class="trust-item">
                <span class="trust-icon">📦</span>
                <div class="trust-info">
                    <div class="trust-val">10K+ Products</div>
                    <div class="trust-label">Genuine &amp; Authentic</div>
                </div>
            </div>
            <div class="trust-item">
                <span class="trust-icon">⭐</span>
                <div class="trust-info">
                    <div class="trust-val">5M+ Happy Customers</div>
                    <div class="trust-label">Trusted by Families</div>
                </div>
            </div>
            <div class="trust-item">
                <span class="trust-icon">🎧</span>
                <div class="trust-info">
                    <div class="trust-val">24/7 Support</div>
                    <div class="trust-label">Expert Assistance</div>
                </div>
            </div>
            <div class="trust-item">
                <span class="trust-icon">✅</span>
                <div class="trust-info">
                    <div class="trust-val">100% Satisfaction</div>
                    <div class="trust-label">Quality Guaranteed</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     HEALTHPRO FOOTER
══════════════════════════════════════ -->


<!-- "Coming soon" toast (shared) ---------------------------------------- -->
<div id="storeToast" class="store-toast" role="status" aria-live="polite">Coming soon — the store is launching shortly. 🚀</div>
<script>
    (function() {
        /* ── Toast ─────────────────────────────────────────────────────── */
        var toast = document.getElementById('storeToast');
        var timer = null;

        function showToast() {
            if (!toast) return;
            toast.classList.add('is-on');
            clearTimeout(timer);
            timer = setTimeout(function() {
                toast.classList.remove('is-on');
            }, 2800);
        }

        /* ── Modal ─────────────────────────────────────────────────────── */
        var modal = document.getElementById('storeModal');
        var prodLabel = document.getElementById('modalProdName');
        var closeBtn = document.getElementById('modalCloseBtn');
        var backBtn = document.getElementById('modalBackBtn');
        var notifyBtn = document.getElementById('modalNotifyBtn');

        function openModal(productName) {
            if (!modal) {
                showToast();
                return;
            }
            if (prodLabel && productName) prodLabel.textContent = '🛒 ' + productName;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            if (!modal) return;
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (backBtn) backBtn.addEventListener('click', closeModal);
        if (modal) modal.addEventListener('click', function(ev) {
            if (ev.target === modal) closeModal();
        });
        document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape') closeModal();
        });

        if (notifyBtn) notifyBtn.addEventListener('click', function() {
            var email = document.getElementById('modalEmail');
            var val = email ? email.value.trim() : '';
            if (!val || !/^[^@]+@[^@]+\.[^@]+$/.test(val)) {
                if (email) {
                    email.style.borderColor = '#e8323e';
                    email.focus();
                }
                return;
            }
            notifyBtn.textContent = '✅ You\'re on the list!';
            notifyBtn.style.background = '#145f3c';
            notifyBtn.disabled = true;
            setTimeout(closeModal, 1600);
        });

        /* ── Wire every [data-soon] element ────────────────────────────── */
        document.addEventListener('click', function(ev) {
            var el = ev.target.closest('[data-soon]');
            if (!el) return;
            ev.preventDefault();
            var name = el.getAttribute('data-product') || '';
            openModal(name);
        });

        /* ── Scroll-reveal animation (IntersectionObserver) ────────────── */
        if ('IntersectionObserver' in window) {
            var revealEls = document.querySelectorAll('.reveal-anim');
            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        io.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.08
            });
            revealEls.forEach(function(el) {
                io.observe(el);
            });
        } else {
            /* Fallback: show immediately in very old browsers */
            document.querySelectorAll('.reveal-anim').forEach(function(el) {
                el.classList.add('visible');
            });
        }
    })();
</script>

<script>
    /* ===========================
           SHOP BY HEALTH CONDITION
        =========================== */
    const healthData = [{
            icon: "🩸",
            title: "Diabetes Care",
            color: "#ecfdf5"
        },
        {
            icon: "❤️",
            title: "Heart Health",
            color: "#fff1f2"
        },
        {
            icon: "⚖️",
            title: "Weight Management",
            color: "#fff7ed"
        },
        {
            icon: "🛡️",
            title: "Immunity Support",
            color: "#ecfeff"
        },
        {
            icon: "🍽️",
            title: "Digestive Health",
            color: "#fffbeb"
        },
        {
            icon: "🦴",
            title: "Joint & Bone Care",
            color: "#eff6ff"
        },
        {
            icon: "😴",
            title: "Sleep & Stress Care",
            color: "#faf5ff"
        },
        {
            icon: "🌸",
            title: "Women's Health",
            color: "#fdf2f8"
        },
        {
            icon: "🤰",
            title: "Pregnancy Care",
            color: "#fff1f2"
        },
        {
            icon: "👶",
            title: "Baby Care",
            color: "#f0f9ff"
        },
        {
            icon: "👴",
            title: "Elderly Care",
            color: "#ecfdf5"
        },
        {
            icon: "👁️",
            title: "Eye Care",
            color: "#eff6ff"
        }
    ];

    const healthGrid = document.getElementById("healthGrid");

    healthData.forEach(item => {
        const card = document.createElement("div");
        card.className = "health-card";
        card.innerHTML = `
        <div class="icon-box" style="background:${item.color}">
            ${item.icon}
        </div>
        <span>${item.title}</span>
    `;
        card.dataset.soon = "";
        healthGrid.appendChild(card);
    });
</script>






<?php require __DIR__ . '/partials/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Slick JS (AFTER jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/slick-carousel/1.9.0/slick.min.js"></script>

<script>
    jQuery(window).on('load', function() {
        if (jQuery('.shop-list').length && !jQuery('.shop-list').hasClass('slick-initialized')) {
            jQuery('.shop-list').slick({
                slidesToShow: 5,
                arrows: false,
                dots: false,
                slidesToScroll: 1,
                infinite: true,
                pauseOnHover: false,
                autoplay: true,
                autoplaySpeed: 3000,
                responsive: [{
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 3
                    }
                }]
            });
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log(
            document.querySelector('.product-slider-list'),
            document.querySelector('.swiper-wrapper'),
            document.querySelectorAll('.swiper-slide').length
        );

        var slider = document.querySelector('.product-slider-list');

        if (!slider) return;

        if (!slider.classList.contains('swiper-initialized')) {
            new Swiper(slider, {
                slidesPerView: 3,
                spaceBetween: 30,
                centeredSlides: true,
                loop: true,
                grabCursor: true,

                breakpoints: {
                    0: {
                        slidesPerView: 1
                    },
                    768: {
                        slidesPerView: 2
                    },
                    1024: {
                        slidesPerView: 3
                    }
                }
            });
        }

    });
</script>

<script>
    const swiper = new Swiper('.swiper', {
        slidesPerView: 3,
        centeredSlides: true,
        centeredSlidesBounds: true,
        normalizeSlideIndex: false,
        grabCursor: true,
        loop: true,
        loopPreventsSliding: false,
        effect: 'coverflow',
        coverflowEffect: {
            rotate: 0,
            stretch: 125,
            depth: 500,
            slideShadows: false,
            modifier: 1,
        },
        speed: 600,
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        keyboard: {
            enabled: true,
        },
    });
</script>

<script>
    $(document).ready(function() {
        $('.brand-slider').slick({
            slidesToShow: 6,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 0,
            speed: 3000,
            cssEase: 'linear',
            arrows: false,
            dots: false,
            pauseOnHover: false,
            infinite: true,

            responsive: [{
                    breakpoint: 1024,
                    settings: {
                        slidesToShow: 4
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3
                    }
                },
                {
                    breakpoint: 480,
                    settings: {
                        slidesToShow: 2
                    }
                }
            ]
        });
    });
</script>