<?php
// =====================================================================
// store/wishlist.php — /store/wishlist (customer's saved products)
// =====================================================================

require_once __DIR__ . '/_lib.php';
store_gate();

$me = ecp_patient_current();
$rows = [];
if ($me) {
    $rows = store_products_simple(
        'AND EXISTS (SELECT 1 FROM store_wishlist w WHERE w.product_id = p.id AND w.identity_id = :i)',
        ['i' => (int) $me['id']],
        'p.in_stock DESC, p.name',
        48
    );
}

$storeTitle = 'My wishlist | eClinicPro Store';
require __DIR__ . '/_header.php';
?>
<main class="st-wrap" style="padding-bottom:64px">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <span>Wishlist</span></nav>
  <header class="st-list-head">
    <span class="st-eyebrow">Saved for later</span>
    <h1 class="st-h2">My <em>wishlist</em></h1>
  </header>
  <div style="padding-top:28px">
    <?php if (!$me): ?>
      <div class="st-empty">
        <h3>Sign in to see your wishlist</h3>
        <p>Use your eClinicPro health account: the same login as your patient dashboard.</p>
        <button type="button" class="st-btn st-btn-primary" style="margin-top:10px" onclick="window.ecpAuth && window.ecpAuth.open('default')">Sign in with mobile</button>
      </div>
    <?php elseif (!$rows): ?>
      <div class="st-empty">
        <h3>Your wishlist is empty</h3>
        <p>Tap the ♡ on any product to save it here.</p>
        <a href="<?= store_url() ?>" class="st-btn st-btn-primary" style="margin-top:10px">Start shopping</a>
      </div>
    <?php else: ?>
      <div class="st-grid">
        <?php foreach ($rows as $p): ?>
          <?php require __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
