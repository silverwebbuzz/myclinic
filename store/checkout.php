<?php
// =====================================================================
// store/checkout.php — /store/checkout
//   GET  : delivery address + contact + per-seller summary
//   POST : CheckoutService::place() → /store/order/{order_no}
// Login required (customers = patient identities).
// =====================================================================

use App\Services\Store\AddressService;
use App\Services\Store\CartService;
use App\Services\Store\CheckoutService;
use App\Services\Store\PricingService;

require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_app.php';
store_gate();
store_app_required();

$me = ecp_patient_current();
$error = null;
$old = [];

if ($me) {
    $identityId = (int) $me['id'];
    $cart = store_cart(false);
    $items = $cart !== null ? CartService::items((int) $cart['id']) : [];
    if (!$items || array_filter($items, static fn ($i) => $i['problem'] !== null)) {
        header('Location: /store/cart');   // empty, or something needs fixing first
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $old = $_POST;
        if (!store_same_origin()) {
            $error = 'Please reload the page and try again.';
        } else {
            $addressId = (string) ($_POST['address_id'] ?? '');
            if ($addressId === 'new' || $addressId === '') {
                $res = AddressService::create($identityId, (array) ($_POST['addr'] ?? []));
                $addressId = $res['ok'] ? (string) $res['id'] : '';
                if ($res['ok']) {
                    $old['address_id'] = $addressId;   // re-render picks the saved address, not a second copy
                } else {
                    $error = $res['error'] ?? 'Please check the delivery address.';
                }
            }
            $address = $error === null ? AddressService::find($identityId, (int) $addressId) : null;
            if ($error === null && $address === null) {
                $error = 'Choose a delivery address.';
            }
            $contact = [
                'name' => trim((string) ($_POST['contact_name'] ?? '')),
                'phone' => \App\Services\Store\VendorService::normalizePhone((string) ($_POST['contact_phone'] ?? '')),
                'email' => trim((string) ($_POST['contact_email'] ?? '')) ?: null,
            ];
            if ($error === null && mb_strlen($contact['name']) < 2) {
                $error = 'Enter your name.';
            } elseif ($error === null && !preg_match('/^\+91[6-9]\d{9}$/', $contact['phone'])) {
                $error = 'Enter a valid 10-digit mobile number.';
            } elseif ($error === null && $contact['email'] !== null && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'That email address doesn\'t look right.';
            }
            if ($error === null) {
                $clientTotal = isset($_POST['client_total']) && ctype_digit((string) $_POST['client_total']) ? (int) $_POST['client_total'] : null;
                $res = CheckoutService::place($identityId, (int) $cart['id'], $address, $contact, (string) ($_POST['checkout_key'] ?? ''), $clientTotal);
                if ($res['ok']) {
                    header('Location: /store/order/' . rawurlencode((string) $res['order_no']) . '?pay=1');   // opens Razorpay
                    exit;
                }
                $error = $res['error'] ?? 'We couldn\'t place your order.';
                $items = CartService::items((int) $cart['id']);   // stock may have changed
            }
        }
    }

    $cc = PricingService::cartCoupon(store_cart(false) ?? [], $identityId);
    $quote = PricingService::quote($items, $cc['coupon'], $identityId);
    $addresses = AddressService::list($identityId);
}

$storeTitle = 'Checkout | eClinicPro Store';
require __DIR__ . '/_header.php';
$sel = (string) ($old['address_id'] ?? ($addresses[0]['id'] ?? 'new'));
?>
<main class="st-wrap" style="padding-bottom:64px">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <a href="<?= store_url('cart') ?>">Cart</a> / <span>Checkout</span></nav>
  <header class="st-list-head"><h1 class="st-h2">Checkout</h1></header>

  <?php if (!$me): ?>
    <div class="st-empty" style="margin-top:28px">
      <h3>Sign in to check out</h3>
      <p>Use your mobile number. It's the same account as your eClinicPro health dashboard, and your cart is kept.</p>
      <button type="button" class="st-btn st-btn-primary" style="margin-top:10px" onclick="window.ecpAuth && window.ecpAuth.open('default')">Sign in with mobile</button>
    </div>
  <?php else: ?>
    <?php if ($error): ?><div class="st-note st-note-err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= store_url('checkout') ?>" class="st-checkout-grid" x-data="{ addr: '<?= e($sel) ?>', placing: false }" @submit="placing = true">
      <input type="hidden" name="checkout_key" value="<?= e(store_uuid4()) ?>">
      <input type="hidden" name="client_total" value="<?= (int) $quote['grand_total'] ?>">
      <div>
        <section class="st-panel">
          <h2 class="st-panel-title">1. Delivery address</h2>
          <?php foreach ($addresses as $a): ?>
            <label class="st-radio-card" :class="addr === '<?= (int) $a['id'] ?>' ? 'is-on' : ''">
              <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>" x-model="addr">
              <span><strong><?= e($a['name']) ?></strong><?= $a['label'] ? ' · ' . e($a['label']) : '' ?> · <?= e($a['phone']) ?><br>
                <span class="st-line-meta"><?= e($a['line1']) ?><?= $a['line2'] ? ', ' . e($a['line2']) : '' ?><?= $a['landmark'] ? ' (near ' . e($a['landmark']) . ')' : '' ?>, <?= e($a['city']) ?>, <?= e($a['state']) ?> – <?= e($a['pincode']) ?></span></span>
            </label>
          <?php endforeach; ?>
          <label class="st-radio-card" :class="addr === 'new' ? 'is-on' : ''">
            <input type="radio" name="address_id" value="new" x-model="addr">
            <span><strong>+ Add a new address</strong></span>
          </label>
          <?php $na = (array) ($old['addr'] ?? []); ?>
          <div class="st-form-grid" x-show="addr === 'new'" x-cloak>
            <label>Full name<input class="st-input" name="addr[name]" value="<?= e((string) ($na['name'] ?? ($me['name'] ?? ''))) ?>" :required="addr === 'new'"></label>
            <label>Mobile<input class="st-input" name="addr[phone]" inputmode="numeric" value="<?= e((string) ($na['phone'] ?? preg_replace('/^\+91/', '', (string) ($me['phone'] ?? '')))) ?>" :required="addr === 'new'"></label>
            <label class="is-wide">House / flat, building, street<input class="st-input" name="addr[line1]" value="<?= e((string) ($na['line1'] ?? '')) ?>" :required="addr === 'new'"></label>
            <label class="is-wide">Area, locality <span>(optional)</span><input class="st-input" name="addr[line2]" value="<?= e((string) ($na['line2'] ?? '')) ?>"></label>
            <label>Landmark <span>(optional)</span><input class="st-input" name="addr[landmark]" value="<?= e((string) ($na['landmark'] ?? '')) ?>"></label>
            <label>Pincode<input class="st-input" name="addr[pincode]" inputmode="numeric" maxlength="6" value="<?= e((string) ($na['pincode'] ?? '')) ?>" :required="addr === 'new'"></label>
            <label>City<input class="st-input" name="addr[city]" value="<?= e((string) ($na['city'] ?? '')) ?>" :required="addr === 'new'"></label>
            <label>State<select class="st-input st-select" name="addr[state]" :required="addr === 'new'">
              <option value="">Choose…</option>
              <?php $naState = \App\Services\Store\GstStates::name(\App\Services\Store\GstStates::codeFor((string) ($na['state'] ?? ''))); ?>
              <?php foreach (\App\Services\Store\GstStates::STATES as $stName): ?>
                <option value="<?= e($stName) ?>" <?= $naState === $stName ? 'selected' : '' ?>><?= e($stName) ?></option>
              <?php endforeach; ?>
            </select></label>
            <label>Save as <span>(optional)</span><input class="st-input" name="addr[label]" placeholder="Home / Office" value="<?= e((string) ($na['label'] ?? '')) ?>"></label>
          </div>
        </section>

        <section class="st-panel">
          <h2 class="st-panel-title">2. Contact for this order</h2>
          <div class="st-form-grid">
            <label>Name<input class="st-input" name="contact_name" required value="<?= e((string) ($old['contact_name'] ?? ($me['name'] ?? ''))) ?>"></label>
            <label>Mobile<input class="st-input" name="contact_phone" required inputmode="numeric" value="<?= e((string) ($old['contact_phone'] ?? preg_replace('/^\+91/', '', (string) ($me['phone'] ?? '')))) ?>"></label>
            <label class="is-wide">Email <span>(optional, for order updates)</span><input class="st-input" type="email" name="contact_email" value="<?= e((string) ($old['contact_email'] ?? ($me['email'] ?? ''))) ?>"></label>
          </div>
        </section>

        <section class="st-panel">
          <h2 class="st-panel-title">3. Review items</h2>
          <?php foreach ($quote['groups'] as $g): ?>
            <div class="st-seller-group" style="margin:0 0 14px">
              <div class="st-seller-group-head">
                <span>Package from <strong><?= e($g['vendor_name']) ?></strong></span>
                <?php if (count($quote['groups']) > 1): ?><span class="st-ship-note">Ships separately</span><?php endif; ?>
              </div>
              <?php foreach ($g['lines'] as $l): ?>
                <div class="st-line" style="grid-template-columns:56px 1fr auto">
                  <span class="st-line-img" style="width:56px;height:56px"><?php if ($img = store_img($l['cover'])): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?></span>
                  <div class="st-line-body"><span class="st-line-name"><?= e($l['name']) ?></span>
                    <div class="st-line-meta"><?= $l['variant_title'] ? e($l['variant_title']) . ' · ' : '' ?>Qty <?= (int) $l['qty'] ?></div></div>
                  <div class="st-line-total"><?= e(store_rupees((int) $l['line_total_paise'])) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </section>
      </div>

      <aside class="st-summary">
        <h2>Order summary</h2>
        <dl>
          <dt>Items (<?= (int) $quote['item_count'] ?>)</dt><dd><?= e(store_rupees((int) $quote['subtotal'])) ?></dd>
          <?php if ($quote['discount'] > 0): ?><dt>Coupon <?= e($quote['coupon']['code']) ?></dt><dd class="st-save">−<?= e(store_rupees((int) $quote['discount'])) ?></dd><?php endif; ?>
          <?php if ($quote['points_discount'] > 0): ?><dt><?= $quote['points']['kind'] === 'welcome' ? 'Welcome points' : 'eClinicPro Points' ?> (<?= (int) $quote['points']['used'] ?> pts)</dt><dd class="st-save">−<?= e(store_rupees((int) $quote['points_discount'])) ?></dd><?php endif; ?>
          <dt>Delivery</dt><dd><?= $quote['shipping'] > 0 ? e(store_rupees((int) $quote['shipping'])) : 'Free' ?></dd>
          <dt class="st-total">To pay</dt><dd class="st-total"><?= e(store_rupees((int) $quote['grand_total'])) ?></dd>
        </dl>
        <?php if (($quote['points']['welcome_add_paise'] ?? null) !== null): ?>
          <p class="st-summary-note">Add <strong><?= e(store_rupees((int) $quote['points']['welcome_add_paise'])) ?></strong> more to use your <?= (int) $quote['points']['welcome'] ?> welcome points (₹<?= (int) $quote['points']['welcome'] ?> off).</p>
        <?php endif; ?>
        <?php if (($quote['points']['earns'] ?? 0) > 0): ?>
          <p class="st-summary-note">⭐ This order earns <strong><?= (int) $quote['points']['earns'] ?> points</strong> after delivery.</p>
        <?php endif; ?>
        <p class="st-summary-note">Includes <?= e(store_rupees((int) $quote['tax_included'])) ?> GST. Items are reserved for you for <?= (int) store_setting('store_payment_window_minutes', '30') ?> minutes while you pay.</p>
        <button class="st-btn st-btn-primary" style="width:100%" :disabled="placing">
          <span x-text="placing ? 'Placing order…' : 'Place order & pay'">Place order &amp; pay</span>
        </button>
        <p class="st-summary-note">By placing the order you agree to the <a href="/terms">terms</a> and <a href="/refund-policy">refund policy</a>. Products are sold by the sellers shown.</p>
      </aside>
    </form>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
