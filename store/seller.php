<?php
// =====================================================================
// store/seller.php — /store/seller/{slug} (public seller storefront)
// =====================================================================

require_once __DIR__ . '/_lib.php';
store_gate();

$slug = strtolower((string) ($_GET['_r_a'] ?? ''));
unset($_GET['_r_a']);
$seller = $slug !== '' ? store_seller($slug) : null;
if ($seller === null) {
    store_not_found();
}

$result = store_listing([
    'vendor_id' => (int) $seller['id'],
    'brand' => (int) ($_GET['brand'] ?? 0),
    'min' => max(0, (int) ($_GET['min'] ?? 0)),
    'max' => max(0, (int) ($_GET['max'] ?? 0)),
    'in_stock' => !empty($_GET['in_stock']),
    'sort' => (string) ($_GET['sort'] ?? ''),
    'page' => (int) ($_GET['page'] ?? 1),
]);
$result['sellers'] = [];   // no "seller" facet on a seller's own page

$storeTitle = $seller['display_name'] . ' | Seller on eClinicPro Store';
$storeDesc = $seller['description']
    ? mb_substr((string) $seller['description'], 0, 160)
    : 'Shop products from ' . $seller['display_name'] . ', a verified seller on eClinicPro Store.';
$storeCanonical = '/store/seller/' . $seller['slug'];
require __DIR__ . '/_header.php';
?>
<section class="st-seller-hero">
  <div class="st-wrap">
    <nav class="st-crumbs" style="padding-top:0;margin-bottom:14px" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <span>Sellers</span></nav>
    <div class="st-seller-hero-row">
      <span class="st-seller-logo">
        <?php if (!empty($seller['logo_path'])): ?><img src="<?= e((string) store_img($seller['logo_path'])) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr((string) $seller['display_name'], 0, 1))) ?><?php endif; ?>
      </span>
      <div>
        <span class="st-eyebrow" style="margin-bottom:4px">Verified seller</span>
        <h1 class="st-h2"><?= e($seller['display_name']) ?></h1>
        <div class="st-seller-meta">
          <?php if ((int) $seller['rating_count'] > 0): ?><span>★ <?= e(number_format((float) $seller['rating_avg'], 1)) ?> (<?= (int) $seller['rating_count'] ?> ratings)</span><?php endif; ?>
          <?php if (!empty($seller['location'])): ?><span>Ships from <?= e($seller['location']) ?></span><?php endif; ?>
          <span>Dispatch in <?= max(1, (int) $seller['handling_days']) ?> day<?= (int) $seller['handling_days'] > 1 ? 's' : '' ?></span>
          <span><?= (int) $seller['default_return_window_days'] > 0 ? (int) $seller['default_return_window_days'] . '-day returns on damaged, wrong or defective items' : 'No returns' ?></span>
          <?php if (!empty($seller['approved_at'])): ?><span>Selling since <?= e(date('M Y', (int) strtotime((string) $seller['approved_at']))) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php if (!empty($seller['description'])): ?><p class="st-lede" style="max-width:760px;white-space:pre-line"><?= e($seller['description']) ?></p><?php endif; ?>
  </div>
</section>
<main class="st-wrap">
  <?php require __DIR__ . '/_listing_body.php'; ?>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
