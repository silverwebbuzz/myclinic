<?php
// =====================================================================
// store/product.php — /store/p/{slug}
// =====================================================================

require_once __DIR__ . '/_lib.php';
store_gate();

$slug = strtolower((string) ($_GET['_r_a'] ?? ''));
unset($_GET['_r_a']);
$p = $slug !== '' ? store_product($slug) : null;
if ($p === null) {
    store_not_found();
}

$variants = $p['variants'];
$images = array_map(static fn ($i) => store_img($i['path']), $p['images']);
$images = array_values(array_filter($images));
$returnDays = !empty($p['is_returnable'])
    ? (int) ($p['return_window_days'] ?? $p['default_return_window_days'] ?? 7)
    : 0;
$wishOn = isset(store_wishlist_ids()[(int) $p['id']]);
$noPromo = !empty($p['no_promotion']);

// Variant payload for the Alpine picker.
$variantJs = array_map(static fn ($v) => [
    'id' => (int) $v['id'],
    'title' => (string) ($v['title'] ?? ''),
    'price' => (int) $v['price_paise'],
    'mrp' => (int) $v['mrp_paise'],
    'inStock' => (int) $v['stock_qty'] > (int) $v['reserved_qty'],
    'lowStock' => ((int) $v['stock_qty'] - (int) $v['reserved_qty']) > 0 && ((int) $v['stock_qty'] - (int) $v['reserved_qty']) <= 5,
], $variants);
$first = $variantJs[0] ?? ['price' => (int) $p['min_price_paise'], 'mrp' => 0, 'inStock' => false, 'lowStock' => false, 'title' => ''];

$related = store_products_simple(
    'AND p.category_id = :cat AND p.id <> :pid',
    ['cat' => (int) $p['category_id'], 'pid' => (int) $p['id']],
    'p.in_stock DESC, p.sold_count DESC, p.published_at DESC',
    8
);

// Structured data (Google product rich results).
$offers = [];
foreach ($variants as $v) {
    $offers[] = [
        '@type' => 'Offer',
        'sku' => $v['sku'],
        'price' => number_format((int) $v['price_paise'] / 100, 2, '.', ''),
        'priceCurrency' => 'INR',
        'availability' => (int) $v['stock_qty'] > (int) $v['reserved_qty'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'seller' => ['@type' => 'Organization', 'name' => $p['vendor_name']],
        'url' => ecp_site_url('/store/p/' . $p['slug']),
    ];
}
$storeJsonLd = array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $p['name'],
    'image' => $images ?: null,
    'description' => $p['short_desc'] ?: mb_substr((string) ($p['description'] ?? ''), 0, 300),
    'brand' => !empty($p['brand_name']) ? ['@type' => 'Brand', 'name' => $p['brand_name']] : null,
    'offers' => $offers ?: null,
]);

$storeTitle = ($p['seo_title'] ?: $p['name']) . ' | eClinicPro Store';
$storeDesc = $p['seo_description'] ?: ($p['short_desc'] ?: 'Buy ' . $p['name'] . ' from ' . $p['vendor_name'] . ' on eClinicPro Store.');
$storeCanonical = '/store/p/' . $p['slug'];
require __DIR__ . '/_header.php';
?>
<main class="st-wrap">
  <nav class="st-crumbs" aria-label="Breadcrumb">
    <a href="<?= store_url() ?>">Store</a>
    <?php if (!empty($p['dept'])): ?> / <a href="<?= e(store_url('c/' . $p['dept']['slug'])) ?>"><?= e($p['dept']['name']) ?></a>
      / <a href="<?= e(store_url('c/' . $p['dept']['slug'] . '/' . $p['category_slug'])) ?>"><?= e($p['category_name']) ?></a><?php endif; ?>
  </nav>

  <div class="st-pdp" x-data='storePdp(<?= e(json_encode(['variants' => $variantJs, 'images' => $images], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)'>
    <!-- Gallery -->
    <div>
      <div class="st-gallery-main">
        <?php if ($images): ?>
          <img :src="images[img]" src="<?= e($images[0]) ?>" alt="<?= e($p['name']) ?>">
        <?php else: ?>
          <div class="st-noimg" style="height:100%;display:grid;place-items:center;color:#b9b2a4">No photo</div>
        <?php endif; ?>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="st-thumbs">
          <?php foreach ($images as $i => $src): ?>
            <button type="button" :class="img === <?= $i ?> ? 'is-active' : ''" @click="img = <?= $i ?>" aria-label="Photo <?= $i + 1 ?>">
              <img src="<?= e($src) ?>" alt="" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Buy box -->
    <div>
      <?php if (!empty($p['brand_name'])): ?>
        <a class="st-pdp-brand" href="<?= e(store_url('brand/' . $p['brand_slug'])) ?>"><?= e($p['brand_name']) ?></a>
      <?php endif; ?>
      <h1 class="st-pdp-title"><?= e($p['name']) ?></h1>
      <?php if ((int) $p['rating_count'] > 0): ?>
        <p class="st-rating"><b>★ <?= e(number_format((float) $p['rating_avg'], 1)) ?></b> · <?= (int) $p['rating_count'] ?> ratings</p>
      <?php endif; ?>
      <?php if (!empty($p['short_desc'])): ?><p class="st-pdp-short"><?= e($p['short_desc']) ?></p><?php endif; ?>

      <div class="st-pdp-price">
        <b x-text="rupees(cur.price)"><?= e(store_rupees((int) $first['price'])) ?></b>
        <template x-if="cur.mrp > cur.price">
          <span><s x-text="'MRP ' + rupees(cur.mrp)"></s> <?php if (!$noPromo): ?><span class="st-off" x-text="off(cur) + '% off'"></span><?php endif; ?></span>
        </template>
      </div>
      <div class="st-pdp-tax">Inclusive of all taxes</div>

      <?php if (count($variantJs) > 1): ?>
        <div class="st-variants" role="radiogroup" aria-label="Choose an option">
          <template x-for="(v, i) in variants" :key="v.id">
            <button type="button" class="st-variant" role="radio" :aria-checked="sel === i" :class="{ 'is-active': sel === i, 'is-oos': !v.inStock }" @click="sel = i">
              <span x-text="v.title || ('Option ' + (i + 1))"></span>
              <small x-text="rupees(v.price) + (v.inStock ? '' : ' · out of stock')"></small>
            </button>
          </template>
        </div>
      <?php endif; ?>

      <p class="st-stock" :class="cur.inStock ? 'is-in' : 'is-out'"
         x-text="cur.inStock ? (cur.lowStock ? 'In stock: only a few left' : 'In stock') : 'Currently out of stock'"><?= $first['inStock'] ? 'In stock' : 'Currently out of stock' ?></p>

      <div class="st-buy">
        <button type="button" class="st-btn st-btn-primary" :disabled="!cur.inStock || busy" @click="addToCart(false)">
          <span x-text="cur.inStock ? 'Add to cart' : 'Out of stock'">Add to cart</span>
        </button>
        <button type="button" class="st-btn st-btn-ghost" :disabled="!cur.inStock || busy" @click="addToCart(true)" x-show="cur.inStock">Buy now</button>
        <button type="button" class="st-btn st-btn-ghost st-wish-inline <?= $wishOn ? 'is-on' : '' ?>" data-wish="<?= (int) $p['id'] ?>"
                aria-pressed="<?= $wishOn ? 'true' : 'false' ?>" onclick="storeToggleWish(this, <?= (int) $p['id'] ?>)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 3.2c0 5.4-7.5 10-7.5 10z"/></svg>
          Wishlist
        </button>
      </div>

      <div class="st-seller-box">
        <span class="st-seller-logo">
          <?php if (!empty($p['vendor_logo'])): ?><img src="<?= e((string) store_img($p['vendor_logo'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr((string) $p['vendor_name'], 0, 1))) ?><?php endif; ?>
        </span>
        <div>
          <div style="font-size:12.5px;color:var(--st-mute)">Sold by</div>
          <a href="<?= e(store_url('seller/' . $p['vendor_slug'])) ?>" style="font-weight:600;text-decoration:none"><?= e($p['vendor_name']) ?></a>
          <?php if ((int) $p['vendor_rating_count'] > 0): ?><span class="st-rating"> · ★ <?= e(number_format((float) $p['vendor_rating'], 1)) ?></span><?php endif; ?>
          <?php if (!empty($p['ships_from'])): ?><div style="font-size:13px;color:var(--st-ink-2)">Ships from <?= e($p['ships_from']['city']) ?>, <?= e($p['ships_from']['state']) ?></div><?php endif; ?>
        </div>
      </div>

      <ul class="st-facts">
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>
          <span>Usually dispatched within <?= max(1, (int) $p['handling_days']) ?> day<?= (int) $p['handling_days'] > 1 ? 's' : '' ?>. Delivery across India.</span></li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5"/></svg>
          <span><?= $returnDays > 0 ? $returnDays . '-day returns if the product is damaged, wrong or defective.' : 'This product is not returnable.' ?></span></li>
        <li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
          <span>Genuine product from a verified seller<?= !empty($p['license_number']) ? '. Licence no. ' . e($p['license_number']) : '' ?>.</span></li>
      </ul>
    </div>
  </div>

  <?php if (!empty($p['description']) || $p['specs'] || !empty($p['manufacturer'])): ?>
  <section class="st-tabs-section">
    <?php if (!empty($p['description'])): ?>
      <h2 class="st-h2" style="font-size:30px">About this product</h2>
      <div class="st-desc" style="margin-top:14px"><?= e($p['description']) ?></div>
    <?php endif; ?>
    <?php if ($p['specs'] || !empty($p['manufacturer'])): ?>
      <h2 class="st-h2" style="font-size:30px;margin-top:32px">Specifications</h2>
      <table class="st-specs" style="margin-top:14px">
        <?php foreach ($p['specs'] as $s): ?><tr><th><?= e($s['label']) ?></th><td><?= e($s['value']) ?></td></tr><?php endforeach; ?>
        <?php if (!empty($p['brand_name'])): ?><tr><th>Brand</th><td><?= e($p['brand_name']) ?></td></tr><?php endif; ?>
        <?php if (!empty($p['manufacturer'])): ?><tr><th>Manufacturer</th><td><?= e($p['manufacturer']) ?></td></tr><?php endif; ?>
        <?php if (!empty($p['country_of_origin'])): ?><tr><th>Country of origin</th><td><?= e($p['country_of_origin']) ?></td></tr><?php endif; ?>
        <?php if (!empty($p['license_number'])): ?><tr><th>Licence no.</th><td><?= e($p['license_number']) ?></td></tr><?php endif; ?>
        <tr><th>Sold by</th><td><?= e($p['vendor_name']) ?></td></tr>
      </table>
    <?php endif; ?>
    <p class="st-disclaimer">Product information is provided by the seller. It is not medical advice. Read the label and consult your doctor before use, especially if you are pregnant, nursing, taking medication or have a medical condition.</p>
  </section>
  <?php endif; ?>

  <?php $reviews = store_reviews((int) $p['id']); ?>
  <section class="st-tabs-section" id="reviews">
    <h2 class="st-h2" style="font-size:30px">Customer reviews</h2>
    <?php if (!$reviews): ?>
      <p class="st-summary-note">No reviews yet. Only customers who bought this product can review it.</p>
    <?php else: ?>
      <p class="st-rating" style="margin-top:6px"><b>★ <?= e(number_format((float) $p['rating_avg'], 1)) ?></b> out of 5 · <?= (int) $p['rating_count'] ?> verified review<?= (int) $p['rating_count'] === 1 ? '' : 's' ?></p>
      <div style="display:grid;gap:14px;margin-top:16px;max-width:780px">
        <?php foreach ($reviews as $rv): ?>
          <article style="padding:14px 16px;border:1px solid var(--st-line);border-radius:16px">
            <div style="color:var(--st-green-700)"><?= str_repeat('★', (int) $rv['rating']) ?><span style="color:#cfd6d2"><?= str_repeat('★', 5 - (int) $rv['rating']) ?></span>
              <?php if ($rv['title']): ?><strong style="color:var(--st-navy);margin-left:6px"><?= e($rv['title']) ?></strong><?php endif; ?></div>
            <?php if ($rv['body']): ?><p style="margin:6px 0 0;white-space:pre-line;color:var(--st-ink-2)"><?= e($rv['body']) ?></p><?php endif; ?>
            <div class="st-line-meta" style="margin-top:6px"><?= e($rv['display_name']) ?> · Verified buyer · <?= e(date('M Y', (int) strtotime((string) $rv['created_at']))) ?></div>
            <?php if ($rv['vendor_reply']): ?><div class="st-note" style="margin-top:8px;font-size:13px"><strong>Seller replied:</strong> <?= e($rv['vendor_reply']) ?></div><?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($related): ?>
  <section class="st-section" style="padding-top:36px">
    <div class="st-head"><h2 class="st-h2" style="font-size:32px">You may also like</h2></div>
    <div class="st-grid">
      <?php foreach ($related as $p2): ?>
        <?php $p = $p2; require __DIR__ . '/_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<script>
function storePdp(init) {
  return {
    variants: init.variants || [], images: init.images || [], sel: 0, img: 0, busy: false,
    get cur() { return this.variants[this.sel] || { id: 0, price: 0, mrp: 0, inStock: false, lowStock: false }; },
    async addToCart(goCheckout) {
      if (this.busy || !this.cur.inStock) return;
      this.busy = true;
      try {
        const r = await fetch('/api/store_cart', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ action: 'add', variant_id: this.cur.id, qty: 1 }),
        });
        const j = await r.json().catch(() => ({}));
        if (!j.ok) { storeToast(j.error || 'Could not add to cart. Please try again.'); return; }
        const badge = document.getElementById('st-cart-count');
        if (badge) badge.textContent = j.count;
        if (goCheckout) { window.location.href = '/store/checkout'; return; }
        storeToast('Added to cart');
      } catch (e) {
        storeToast('Network error. Please try again.');
      } finally {
        this.busy = false;
      }
    },
    rupees(p) { const r = p / 100; return '₹' + (p % 100 === 0 ? r.toLocaleString('en-IN') : r.toLocaleString('en-IN', { minimumFractionDigits: 2 })); },
    off(v) { return v.mrp > v.price ? Math.round((1 - v.price / v.mrp) * 100) : 0; },
  };
}
</script>
<?php require __DIR__ . '/_footer.php'; ?>
