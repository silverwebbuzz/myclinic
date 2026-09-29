<?php
// =====================================================================
// store/index.php — eClinicPro Store homepage (/store/), per the
// "eClinicPro Shop Homepage" mockup. Sections render only when they
// have data, so an empty catalog still looks intentional.
// =====================================================================

require_once __DIR__ . '/_lib.php';
store_gate();

$tiles = array_values(array_filter(store_nav(), static fn ($n) => (int) $n['homepage_tile_order'] > 0 && empty($n['url'])));
usort($tiles, static fn ($a, $b) => (int) $a['homepage_tile_order'] <=> (int) $b['homepage_tile_order']);
$goals = store_goals();

$featured = store_products_simple('AND p.is_featured = 1', [], 'p.in_stock DESC, p.sold_count DESC', 8);
$newest = store_products_simple('', [], 'p.published_at DESC', 8);
if (!$featured) {
    $featured = $newest;
    $newest = [];
}

$sellers = [];
$brands = [];
$banners = [];
$db = ecp_db();
if ($db) {
    try {
        $banners = $db->query(
            "SELECT title, image_path, link FROM store_banners
              WHERE is_active = 1 AND placement = 'home_strip'
                AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
              ORDER BY sort_order, id DESC LIMIT 3"
        )->fetchAll();
    } catch (Throwable) {
        $banners = [];
    }
    try {
        $sellers = $db->query(
            "SELECT v.slug, v.display_name, v.logo_path,
                    (SELECT COUNT(*) FROM store_products p WHERE p.vendor_id = v.id AND p.status = 'live' AND p.deleted_at IS NULL) AS n
               FROM store_vendors v
              WHERE v.status = 'approved'
              ORDER BY v.is_featured DESC, n DESC LIMIT 6"
        )->fetchAll();
        $sellers = array_values(array_filter($sellers, static fn ($s) => (int) $s['n'] > 0));
        $brands = $db->query(
            "SELECT b.slug, b.name FROM store_brands b
              WHERE b.is_active = 1 AND EXISTS (SELECT 1 FROM store_products p WHERE p.brand_id = b.id AND p.status = 'live' AND p.deleted_at IS NULL)
              ORDER BY b.is_featured DESC, b.name LIMIT 16"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[store home] ' . $e->getMessage());
    }
}

$storeTitle = 'eClinicPro Store: Health, Wellness & Everyday Care';
$storeDesc = 'Trusted health, wellness and everyday essentials from verified sellers: supplements, health devices, mother & baby, personal care and more.';
$storeCanonical = '/store/';
$storeActive = 'home';
require __DIR__ . '/_header.php';
?>
<main>
  <!-- Hero -->
  <section class="st-hero">
    <div class="st-wrap st-hero-grid">
      <div class="st-hero-copy">
        <span class="st-eyebrow" style="margin:0">Better healthcare. Better everyday.</span>
        <h1 class="st-h1">Healthy choices for <em>every stage</em> of life.</h1>
        <p>Trusted healthcare, wellness and everyday essentials, carefully selected for you and your family.</p>
        <div class="st-hero-ctas">
          <a href="#shop-by-need" class="st-btn st-btn-primary">Shop now <span>→</span></a>
          <a href="<?= store_url('categories') ?>" class="st-btn st-btn-ghost">Explore categories</a>
        </div>
      </div>
      <div class="st-hero-img">
        <img src="/assets/img/shop/hero.jpg" alt="Smiling baby with baby care essentials" fetchpriority="high">
        <a href="<?= store_url('need/baby-kids') ?>" class="st-hero-chip"><span>→</span>Baby care essentials</a>
      </div>
    </div>
  </section>

  <!-- Trust strip -->
  <section class="st-trust">
    <div class="st-wrap st-trust-grid">
      <div class="st-trust-item"><span class="st-trust-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg></span><div><strong>Verified sellers</strong><span>Every seller's KYC and licences checked</span></div></div>
      <div class="st-trust-item"><span class="st-trust-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8a3 3 0 0 1 6 0"/></svg></span><div><strong>Genuine products</strong><span>Every product reviewed before it goes live</span></div></div>
      <div class="st-trust-item"><span class="st-trust-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/></svg></span><div><strong>Clear information</strong><span>Seller, licence and return policy on every product</span></div></div>
      <div class="st-trust-item"><span class="st-trust-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 3.2c0 5.4-7.5 10-7.5 10z"/></svg></span><div><strong>Healthcare first</strong><span>From the team behind eClinicPro</span></div></div>
    </div>
  </section>

  <?php if ($banners): ?>
  <section class="st-wrap" style="padding-top:28px">
    <div class="st-banners">
      <?php foreach ($banners as $b): ?>
        <?php $img = store_img($b['image_path']); ?>
        <?php if ($img): ?>
          <a href="<?= e($b['link'] ?: store_url()) ?>" class="st-banner"><img src="<?= e($img) ?>" alt="<?= e($b['title'] ?? '') ?>" loading="lazy"></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Shop by need -->
  <?php if ($tiles): ?>
  <section id="shop-by-need" class="st-section" style="scroll-margin-top:120px">
    <div class="st-wrap">
      <div class="st-head">
        <div>
          <span class="st-eyebrow">Explore our range</span>
          <h2 class="st-h2">Shop by <em>need</em></h2>
          <p class="st-lede">Everything you need for better everyday health.</p>
        </div>
        <a href="<?= store_url('categories') ?>" class="st-more">View all categories <span>→</span></a>
      </div>
      <div class="st-tiles">
        <?php foreach ($tiles as $t): ?>
          <a href="<?= e(store_url('need/' . $t['slug'])) ?>" class="st-tile">
            <div class="st-tile-img"><img src="<?= e(store_tile_image((string) $t['slug'])) ?>" alt="" loading="lazy"></div>
            <div class="st-tile-foot"><span class="st-tile-title"><?= e($t['label']) ?></span><span class="st-arrow">→</span></div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Featured products -->
  <section class="st-section is-cream">
    <div class="st-wrap">
      <div class="st-head">
        <div>
          <span class="st-eyebrow">Handpicked</span>
          <h2 class="st-h2">Featured <em>products</em></h2>
        </div>
      </div>
      <?php if ($featured): ?>
        <div class="st-grid">
          <?php foreach ($featured as $p): ?><?php require __DIR__ . '/_card.php'; ?><?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="st-empty" style="background:#fff">
          <h3>Products are on their way</h3>
          <p>Our first sellers are adding their range right now. Check back soon.</p>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Health goals -->
  <?php if ($goals): ?>
  <section class="st-section">
    <div class="st-wrap">
      <div class="st-head">
        <div>
          <span class="st-eyebrow">Shop by health goal</span>
          <h2 class="st-h2">What are you <em>working on?</em></h2>
        </div>
      </div>
      <div class="st-chips">
        <?php foreach ($goals as $g): ?>
          <a href="<?= e(store_url('goal/' . $g['slug'])) ?>" class="st-chip"><?= e($g['name']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- New arrivals -->
  <?php if ($newest): ?>
  <section class="st-section is-cream">
    <div class="st-wrap">
      <div class="st-head"><div><span class="st-eyebrow">Just in</span><h2 class="st-h2">New <em>arrivals</em></h2></div></div>
      <div class="st-grid">
        <?php foreach ($newest as $p): ?><?php require __DIR__ . '/_card.php'; ?><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Sellers -->
  <?php if ($sellers): ?>
  <section class="st-section">
    <div class="st-wrap">
      <div class="st-head"><div><span class="st-eyebrow">Verified sellers</span><h2 class="st-h2">Shop by <em>store</em></h2></div></div>
      <div class="st-tiles">
        <?php foreach ($sellers as $s): ?>
          <a href="<?= e(store_url('seller/' . $s['slug'])) ?>" class="st-tile" style="align-items:center;text-align:center;padding:22px 14px">
            <span class="st-seller-logo" style="width:72px;height:72px;border-radius:18px;font-size:24px">
              <?php if (!empty($s['logo_path'])): ?><img src="<?= e((string) store_img($s['logo_path'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr((string) $s['display_name'], 0, 1))) ?><?php endif; ?>
            </span>
            <span class="st-tile-title" style="font-size:18px"><?= e($s['display_name']) ?></span>
            <span style="font-size:12.5px;color:var(--st-mute)"><?= (int) $s['n'] ?> product<?= (int) $s['n'] === 1 ? '' : 's' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Brands -->
  <?php if ($brands): ?>
  <section class="st-section is-cream" style="padding-top:48px;padding-bottom:48px">
    <div class="st-wrap">
      <div class="st-head" style="margin-bottom:20px"><div><span class="st-eyebrow">Trusted names</span><h2 class="st-h2" style="font-size:34px">Shop by <em>brand</em></h2></div></div>
      <div class="st-chips">
        <?php foreach ($brands as $b): ?><a href="<?= e(store_url('brand/' . $b['slug'])) ?>" class="st-chip"><?= e($b['name']) ?></a><?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- Why us -->
  <section class="st-section">
    <div class="st-wrap">
      <div class="st-head"><div><span class="st-eyebrow">Why eClinicPro Store</span><h2 class="st-h2">Built by a <em>healthcare</em> company</h2></div></div>
      <div class="st-why">
        <div><strong>Checked sellers</strong><p>Sellers submit PAN, bank and licence documents (FSSAI, AYUSH, device registration) before they can list.</p></div>
        <div><strong>Reviewed listings</strong><p>Every product is reviewed by our team. No miracle claims, no prescription drugs.</p></div>
        <div><strong>Clear information</strong><p>Seller name, location, licence number and return policy shown on every product.</p></div>
        <div><strong>Connected care</strong><p>Book lab tests and find doctors on eClinicPro, all with the same account.</p></div>
      </div>
    </div>
  </section>

  <!-- Sell band -->
  <section class="st-section" style="padding-top:0">
    <div class="st-wrap">
      <div class="st-band">
        <div>
          <span class="st-eyebrow">For brands &amp; distributors</span>
          <h2 class="st-h2" style="font-size:36px">Sell on <em>eClinicPro Store</em></h2>
          <p class="st-lede">Reach customers who trust eClinicPro for their health. Simple onboarding, transparent commission.</p>
        </div>
        <div><a href="/sell-on-eclinicpro" class="st-btn st-btn-primary">Become a seller <span>→</span></a></div>
      </div>
    </div>
  </section>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
