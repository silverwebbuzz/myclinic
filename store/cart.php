<?php
// =====================================================================
// store/cart.php — /store/cart. Grouped by seller; totals from
// App\Services\Store\PricingService (the same code checkout uses).
// =====================================================================

require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_app.php';
store_gate();
store_app_required();

$cart = store_cart(false);
$items = $cart !== null ? \App\Services\Store\CartService::items((int) $cart['id']) : [];
$meForCoupon = ecp_patient_current();
$cc = $cart !== null ? \App\Services\Store\PricingService::cartCoupon($cart, $meForCoupon ? (int) $meForCoupon['id'] : null) : ['coupon' => null, 'error' => null];
$quote = \App\Services\Store\PricingService::quote($items, $cc['coupon']);
$problems = array_filter($items, static fn ($i) => $i['problem'] !== null);
$priceChanged = array_filter($items, static fn ($i) => $i['problem'] === null && $i['price_changed']);
if ($cart !== null && $priceChanged) {
    // Shown once: the customer has now seen the new prices.
    \App\Services\Store\CartService::acknowledgePrices((int) $cart['id']);
}
$me = ecp_patient_current();

// Group ALL lines (incl. problem ones) by seller for display.
$bySeller = [];
foreach ($items as $it) {
    $bySeller[(int) $it['vendor_id']]['name'] = $it['vendor_name'];
    $bySeller[(int) $it['vendor_id']]['slug'] = $it['vendor_slug'];
    $bySeller[(int) $it['vendor_id']]['lines'][] = $it;
}
$groupQuote = [];
foreach ($quote['groups'] as $g) {
    $groupQuote[(int) $g['vendor_id']] = $g;
}

$storeTitle = 'Your cart | eClinicPro Store';
require __DIR__ . '/_header.php';
?>
<main class="st-wrap" style="padding-bottom:64px" x-data="storeCart()">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <span>Cart</span></nav>
  <header class="st-list-head"><h1 class="st-h2">Your <em>cart</em></h1></header>

  <?php if (!$items): ?>
    <div class="st-empty" style="margin-top:28px">
      <h3>Your cart is empty</h3>
      <p>Browse the store and add products you need.</p>
      <a href="<?= store_url() ?>" class="st-btn st-btn-primary" style="margin-top:10px">Start shopping</a>
    </div>
  <?php else: ?>
    <?php if ($priceChanged): ?>
      <div class="st-note st-note-warn">Some prices changed since you added them. The cart below shows today's prices.</div>
    <?php endif; ?>
    <?php if ($problems): ?>
      <div class="st-note st-note-err">Some items need your attention before checkout (marked below).</div>
    <?php endif; ?>

    <div class="st-checkout-grid">
      <div>
        <?php foreach ($bySeller as $vid => $s): ?>
          <?php $g = $groupQuote[$vid] ?? null; ?>
          <section class="st-seller-group">
            <div class="st-seller-group-head">
              <span>Sold by <a href="<?= e(store_url('seller/' . $s['slug'])) ?>"><strong><?= e($s['name']) ?></strong></a></span>
              <?php if ($g !== null): ?>
                <span class="st-ship-note">
                  <?php if ($g['shipping'] === 0): ?>
                    Free shipping
                  <?php else: ?>
                    Shipping <?= e(store_rupees((int) $g['shipping'])) ?>
                    <?php if ($g['free_above'] !== null): ?> · add <?= e(store_rupees((int) $g['free_above'] - (int) $g['subtotal'])) ?> more from this seller for free shipping<?php endif; ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
            </div>
            <?php foreach ($s['lines'] as $l): ?>
              <?php $img = store_img($l['cover']); $off = store_off_pct((int) $l['mrp_paise'], (int) $l['price_paise']); ?>
              <div class="st-line <?= $l['problem'] !== null ? 'has-problem' : '' ?>">
                <a href="<?= e(store_url('p/' . $l['slug'])) ?>" class="st-line-img"><?php if ($img): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?></a>
                <div class="st-line-body">
                  <a href="<?= e(store_url('p/' . $l['slug'])) ?>" class="st-line-name"><?= e($l['name']) ?></a>
                  <?php if (!empty($l['variant_title'])): ?><div class="st-line-meta"><?= e($l['variant_title']) ?></div><?php endif; ?>
                  <div class="st-price" style="padding-top:4px">
                    <b><?= e(store_rupees((int) $l['price_paise'])) ?></b>
                    <?php if ($off > 0): ?><s><?= e(store_rupees((int) $l['mrp_paise'])) ?></s><?php if (empty($l['no_promotion'])): ?><span class="st-off"><?= $off ?>% off</span><?php endif; ?><?php endif; ?>
                  </div>
                  <?php if ($l['problem'] !== null): ?><div class="st-line-problem"><?= e($l['problem']) ?></div><?php endif; ?>
                  <div class="st-line-actions">
                    <?php if ($l['problem'] === null || (int) $l['available_qty'] > 0): ?>
                      <label class="st-sr" for="qty-<?= (int) $l['item_id'] ?>">Quantity</label>
                      <select id="qty-<?= (int) $l['item_id'] ?>" class="st-select" style="height:36px" @change="setQty(<?= (int) $l['item_id'] ?>, $event.target.value)">
                        <?php $max = max(1, min(\App\Services\Store\CartService::MAX_QTY_PER_LINE, max((int) $l['qty'], (int) $l['available_qty']))); ?>
                        <?php for ($q = 1; $q <= $max; $q++): ?>
                          <option value="<?= $q ?>" <?= $q === (int) $l['qty'] ? 'selected' : '' ?> <?= $q > (int) $l['available_qty'] ? 'disabled' : '' ?>>Qty <?= $q ?></option>
                        <?php endfor; ?>
                      </select>
                    <?php endif; ?>
                    <button type="button" class="st-link-btn" @click="setQty(<?= (int) $l['item_id'] ?>, 0)">Remove</button>
                    <button type="button" class="st-link-btn" onclick="storeToggleWish(this, <?= (int) $l['product_id'] ?>)" data-wish="<?= (int) $l['product_id'] ?>">Save to wishlist</button>
                  </div>
                </div>
                <div class="st-line-total"><?= $l['problem'] === null ? e(store_rupees((int) $l['price_paise'] * (int) $l['qty'])) : '' ?></div>
              </div>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </div>

      <aside class="st-summary">
        <h2>Order summary</h2>
        <dl>
          <dt>Items (<?= (int) $quote['item_count'] ?>)</dt><dd><?= e(store_rupees((int) $quote['subtotal'])) ?></dd>
          <?php if ($quote['savings'] > 0): ?><dt>You save</dt><dd class="st-save">−<?= e(store_rupees((int) $quote['savings'])) ?> <small>vs MRP</small></dd><?php endif; ?>
          <?php if ($quote['discount'] > 0): ?><dt>Coupon <?= e($quote['coupon']['code']) ?></dt><dd class="st-save">−<?= e(store_rupees((int) $quote['discount'])) ?></dd><?php endif; ?>
          <dt>Shipping<?= count($quote['groups']) > 1 ? ' (' . count($quote['groups']) . ' sellers)' : '' ?></dt><dd><?= $quote['shipping'] > 0 ? e(store_rupees((int) $quote['shipping'])) : 'Free' ?></dd>
          <dt class="st-total">Total</dt><dd class="st-total"><?= e(store_rupees((int) $quote['grand_total'])) ?></dd>
        </dl>
        <div class="st-coupon" x-data="{ code: '', busy: false, err: '' }">
          <?php if ($quote['coupon'] !== null): ?>
            <div class="st-coupon-applied">
              <span><strong><?= e($quote['coupon']['code']) ?></strong> · <?= e($quote['coupon']['label']) ?>
                <?php if (!$quote['coupon']['applied']): ?><br><small class="st-line-problem" style="font-weight:500"><?= e($quote['coupon']['note'] ?? 'Not applicable to these items.') ?></small><?php endif; ?></span>
              <button type="button" class="st-link-btn" @click="setCoupon('')">Remove</button>
            </div>
          <?php else: ?>
            <?php if ($cc['error']): ?><p class="st-line-problem" style="margin:0 0 6px"><?= e($cc['error']) ?></p><?php endif; ?>
            <form @submit.prevent="setCoupon(code)" style="display:flex;gap:6px">
              <label class="st-sr" for="st-coupon-in">Coupon code</label>
              <input id="st-coupon-in" x-model="code" class="st-input" placeholder="Coupon code" style="text-transform:uppercase;height:38px">
              <button class="st-btn st-btn-ghost st-btn-sm" :disabled="busy || !code">Apply</button>
            </form>
          <?php endif; ?>
          <p class="st-line-problem" x-show="err" x-text="err" x-cloak style="margin:6px 0 0"></p>
        </div>
        <p class="st-summary-note">Prices include GST. Each seller ships separately; you'll get tracking for each package.</p>
        <?php if ($quote['item_count'] === 0): ?>
          <button class="st-btn st-btn-primary" style="width:100%" disabled>Checkout</button>
        <?php elseif ($me): ?>
          <a href="<?= store_url('checkout') ?>" class="st-btn st-btn-primary" style="width:100%">Proceed to checkout</a>
        <?php else: ?>
          <button type="button" class="st-btn st-btn-primary" style="width:100%" onclick="window.ecpAuth && window.ecpAuth.open('default')">Sign in to check out</button>
          <p class="st-summary-note" style="text-align:center">Use your mobile number. Your cart is kept.</p>
        <?php endif; ?>
        <?php if ($problems && $quote['item_count'] > 0): ?>
          <p class="st-summary-note">Items marked above aren't included in this total.</p>
        <?php endif; ?>
      </aside>
    </div>
  <?php endif; ?>
</main>
<script>
async function setCoupon(code) {
  const box = document.querySelector('.st-coupon');
  const r = await fetch('/api/store_cart', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ action: 'coupon', code: (code || '').trim().toUpperCase() }),
  }).catch(() => null);
  const j = r ? await r.json().catch(() => ({})) : {};
  if (j.ok) { location.reload(); return; }
  if (box && box._x_dataStack) { box._x_dataStack[0].err = j.error || 'Could not apply the coupon.'; }
  else { storeToast(j.error || 'Could not apply the coupon.'); }
}
function storeCart() {
  return {
    busy: false,
    async setQty(itemId, qty) {
      if (this.busy) return;
      this.busy = true;
      try {
        const r = await fetch('/api/store_cart', {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ action: 'set', item_id: itemId, qty: parseInt(qty, 10) || 0 }),
        });
        const j = await r.json().catch(() => ({}));
        if (!j.ok) { storeToast(j.error || 'Could not update your cart.'); this.busy = false; return; }
        window.location.reload();
      } catch (e) {
        storeToast('Network error. Please try again.');
        this.busy = false;
      }
    },
  };
}
</script>
<?php require __DIR__ . '/_footer.php'; ?>
