<?php
/**
 * Storefront header. Set before including:
 *   $storeTitle (string), $storeDesc (string), $storeCanonical (?string path),
 *   $storeActive (?string nav slug), $storeJsonLd (?array), $storeQuery (?string search box value)
 */
$storeTitle = $storeTitle ?? 'eClinicPro Store';
$storeDesc = $storeDesc ?? 'Trusted health, wellness and everyday care products from verified sellers.';
$storeActive = $storeActive ?? '';
$storeQuery = $storeQuery ?? '';
$me = ecp_patient_current();
$wishCount = count(store_wishlist_ids());
$navItems = store_nav();
$primaryNav = array_slice(array_values(array_filter($navItems, static fn ($n) => empty($n['url']))), 0, 8);
$cssBust = @filemtime(__DIR__ . '/../assets/css/store.css') ?: '1';
$alpineBust = @filemtime(__DIR__ . '/../assets/js/alpine-3.14.1.min.js') ?: '3141';
?><!DOCTYPE html>
<html lang="en-IN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($storeTitle) ?></title>
<meta name="description" content="<?= e($storeDesc) ?>">
<?php if (!store_is_live()): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?php if (!empty($storeCanonical)): ?><link rel="canonical" href="<?= e(ecp_site_url($storeCanonical)) ?>"><?php endif; ?>
<meta property="og:title" content="<?= e($storeTitle) ?>">
<meta property="og:description" content="<?= e($storeDesc) ?>">
<meta property="og:site_name" content="eClinicPro Store">
<link rel="icon" href="/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;1,6..72,400&display=swap">
<link rel="stylesheet" href="/assets/css/store.css?v=<?= (int) $cssBust ?>">
<script defer src="/assets/js/alpine-3.14.1.min.js?v=<?= (int) $alpineBust ?>"></script>
<?php if (!empty($storeJsonLd)): ?>
<script type="application/ld+json"><?= json_encode($storeJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>
</head>
<body class="st-body" x-data="{ menu: false, search: false }">
<?php if (!store_is_live()): ?>
<div class="st-preview">Preview mode: the store is hidden from customers until you press <strong>Go live</strong> in admin.</div>
<?php endif; ?>
<div class="st-announce">Genuine products · Verified sellers · Every listing reviewed by our team</div>

<header class="st-header">
  <div class="st-wrap st-header-row">
    <a href="<?= store_url() ?>" class="st-logo" aria-label="eClinicPro Store home">
      <span class="st-logo-mark"></span>
      <span class="st-logo-text">eClinic<em>Pro</em><small>Store</small></span>
    </a>

    <form action="<?= store_url('search') ?>" method="get" class="st-search st-desktop-only" role="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
      <label class="st-sr" for="st-q">Search the store</label>
      <input id="st-q" type="search" name="q" value="<?= e($storeQuery) ?>" placeholder="Search vitamins, devices, baby care, skin care…" autocomplete="off">
    </form>

    <div class="st-icons">
      <button type="button" class="st-icon-btn st-mobile-only" @click="search = !search" aria-label="Search">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
      </button>
      <?php if ($me): ?>
        <a href="/patient" class="st-icon-btn st-desktop-only" aria-label="My account" title="Hi, <?= e(ecp_patient_first_name($me)) ?>">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c1.2-3.8 4-5.5 7.5-5.5s6.3 1.7 7.5 5.5"/></svg>
        </a>
      <?php else: ?>
        <button type="button" class="st-icon-btn st-desktop-only" aria-label="Sign in" onclick="window.ecpAuth && window.ecpAuth.open('default')">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c1.2-3.8 4-5.5 7.5-5.5s6.3 1.7 7.5 5.5"/></svg>
        </button>
      <?php endif; ?>
      <a href="<?= store_url('wishlist') ?>" class="st-icon-btn" aria-label="Wishlist">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 3.2c0 5.4-7.5 10-7.5 10z"/></svg>
        <span class="st-badge" id="st-wish-count" <?= $wishCount ? '' : 'hidden' ?>><?= $wishCount ?></span>
      </a>
      <a href="<?= store_url('cart') ?>" class="st-cart-pill" aria-label="Cart">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg>
        <span class="st-cart-count" id="st-cart-count"><?= store_cart_count() ?></span>
      </a>
      <button type="button" class="st-icon-btn st-mobile-only" @click="menu = !menu" aria-label="Menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>

  <nav class="st-wrap st-nav st-desktop-only" aria-label="Shop by need">
    <a href="<?= store_url() ?>" class="<?= $storeActive === 'home' ? 'is-active' : '' ?>">Shop</a>
    <?php foreach ($primaryNav as $n): ?>
      <a href="<?= store_url('need/' . $n['slug']) ?>" class="<?= $storeActive === $n['slug'] ? 'is-active' : '' ?>"><?= e($n['label']) ?></a>
    <?php endforeach; ?>
    <a href="<?= store_url('categories') ?>" class="<?= $storeActive === 'categories' ? 'is-active' : '' ?>">All categories</a>
  </nav>

  <div class="st-mobile-search" x-show="search" x-cloak>
    <form action="<?= store_url('search') ?>" method="get" class="st-search" role="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
      <input type="search" name="q" value="<?= e($storeQuery) ?>" placeholder="Search the store…" aria-label="Search the store">
    </form>
  </div>
  <nav class="st-mobile-menu" x-show="menu" x-cloak aria-label="Menu">
    <a href="<?= store_url() ?>">Shop home</a>
    <?php foreach ($navItems as $n): ?>
      <a href="<?= !empty($n['url']) ? e($n['url']) : store_url('need/' . $n['slug']) ?>"><?= e($n['label']) ?></a>
    <?php endforeach; ?>
    <a href="<?= store_url('categories') ?>">All categories</a>
    <?php if ($me): ?><a href="/patient">My account</a><?php else: ?><a href="#" @click.prevent="menu = false; window.ecpAuth && window.ecpAuth.open('default')">Sign in</a><?php endif; ?>
  </nav>
</header>
