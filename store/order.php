<?php
// =====================================================================
// store/order.php — /store/order/{order_no}
// The customer's own order: packages per seller, totals, status.
// P5: orders wait in `pending_payment`; the Razorpay step (P6) plugs in here.
// =====================================================================

use App\Services\Store\OrderService;

require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_app.php';
store_gate();
store_app_required();

$orderNo = strtoupper((string) ($_GET['_r_a'] ?? ''));
unset($_GET['_r_a']);
$me = ecp_patient_current();
if (!$me) {
    $storeTitle = 'Your order | eClinicPro Store';
    require __DIR__ . '/_header.php';
    ?>
    <main class="st-wrap" style="padding:40px 0 64px">
      <div class="st-empty"><h3>Sign in to view this order</h3>
        <button type="button" class="st-btn st-btn-primary" style="margin-top:10px" onclick="window.ecpAuth && window.ecpAuth.open('default')">Sign in with mobile</button></div>
    </main>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

OrderService::expireStale();
$order = preg_match('/^ECS[0-9]{6}-[A-Z0-9]{5,8}$/', $orderNo) ? OrderService::findForIdentity($orderNo, (int) $me['id']) : null;
if ($order === null) {
    store_not_found();
}

// Customer cancels their own unpaid order.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'cancel' && store_same_origin()) {
    OrderService::release((int) $order['id'], 'cancelled', 'Cancelled by customer before payment', 'customer', (int) $me['id']);
    header('Location: /store/order/' . rawurlencode($orderNo));
    exit;
}

$packageStatus = [
    'pending_payment' => 'Awaiting payment', 'new' => 'Confirmed: seller preparing', 'accepted' => 'Seller preparing',
    'packed' => 'Packed', 'ready_to_ship' => 'Ready to ship', 'shipped' => 'Shipped', 'delivered' => 'Delivered',
    'completed' => 'Delivered', 'auto_cancelled' => 'Cancelled', 'cancelled_by_customer' => 'Cancelled',
    'cancelled_by_vendor' => 'Cancelled by seller', 'cancelled_by_admin' => 'Cancelled', 'rto' => 'Returned to seller',
];
$statusText = [
    'pending_payment' => ['Awaiting payment', 'is-warn'],
    'paid' => ['Order confirmed', 'is-ok'],
    'refunded' => ['Refunded', 'is-muted'],
    'expired' => ['Payment window expired', 'is-muted'],
    'cancelled' => ['Cancelled', 'is-muted'],
    'payment_failed' => ['Payment failed', 'is-err'],
][$order['status']] ?? [ucwords(str_replace('_', ' ', (string) $order['status'])), 'is-muted'];
$a = $order['ship_address'];

$storeTitle = 'Order ' . $order['order_no'] . ' | eClinicPro Store';
require __DIR__ . '/_header.php';
?>
<main class="st-wrap" style="padding-bottom:64px">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <span>Order <?= e($order['order_no']) ?></span></nav>
  <header class="st-list-head" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-end">
    <div>
      <span class="st-eyebrow">Order <?= e($order['order_no']) ?></span>
      <h1 class="st-h2"><?= e($statusText[0]) ?></h1>
      <p class="st-lede">Placed <?= e(date('j M Y, g:i a', (int) strtotime((string) $order['placed_at']))) ?> · <?= count($order['vendor_orders']) ?> package<?= count($order['vendor_orders']) === 1 ? '' : 's' ?></p>
    </div>
    <span class="st-status <?= $statusText[1] ?>"><?= e($statusText[0]) ?></span>
  </header>

  <?php if ($order['status'] === 'pending_payment'): ?>
    <div class="st-pay-box" x-data="storePay('<?= e($order['order_no']) ?>', <?= !empty($_GET['pay']) ? 'true' : 'false' ?>)" x-init="init()">
      <div>
        <strong>Complete your payment of <?= e(store_rupees((int) $order['grand_total_paise'])) ?></strong>
        <p class="st-summary-note" style="margin:4px 0 0">Items are reserved until <?= e(date('g:i a', (int) strtotime((string) $order['expires_at']))) ?>. UPI, cards, net banking and wallets accepted via Razorpay.</p>
        <p class="st-line-problem" x-show="msg" x-text="msg" x-cloak></p>
      </div>
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <button type="button" class="st-btn st-btn-primary" :disabled="busy" @click="pay()">
          <span x-text="busy ? 'Please wait…' : 'Pay <?= e(store_rupees((int) $order['grand_total_paise'])) ?>'">Pay now</span>
        </button>
        <form method="post" onsubmit="return confirm('Cancel this order? Nothing has been charged.')">
          <input type="hidden" name="action" value="cancel">
          <button class="st-link-btn">Cancel order</button>
        </form>
      </div>
    </div>
  <?php elseif (in_array($order['status'], ['paid', 'partially_shipped', 'shipped', 'partially_delivered', 'delivered', 'completed'], true)): ?>
    <div class="st-note" style="margin-top:20px;background:var(--st-green-25);border-color:#cfe5d6">
      <strong>Thank you, payment received.</strong> Each seller now packs their items; you'll see tracking here once packages ship.
      <?= !empty($order['contact_email']) ? 'A confirmation has been emailed to ' . e($order['contact_email']) . '.' : '' ?>
    </div>
  <?php elseif ($order['status'] === 'refunded'): ?>
    <div class="st-note st-note-warn" style="margin-top:20px">Your payment arrived after this order had closed and the items were no longer available, so it has been <strong>refunded in full</strong>. Refunds reach your account in 5–7 working days.</div>
  <?php elseif (in_array($order['status'], ['expired', 'cancelled'], true)): ?>
    <div class="st-note" style="margin-top:20px">This order was not paid, so nothing was charged. <a href="<?= store_url('cart') ?>">Go to your cart</a> to try again.</div>
  <?php endif; ?>

  <div class="st-checkout-grid" style="margin-top:24px">
    <div>
      <?php foreach ($order['vendor_orders'] as $vo): ?>
        <section class="st-seller-group">
          <div class="st-seller-group-head">
            <span>Package <?= e(substr((string) $vo['sub_order_no'], -1)) ?> · sold by <a href="<?= e(store_url('seller/' . $vo['vendor_slug'])) ?>"><strong><?= e($vo['vendor_name']) ?></strong></a></span>
            <span class="st-ship-note"><?= e($packageStatus[$vo['status']] ?? ucfirst(str_replace('_', ' ', (string) $vo['status']))) ?> · <?= (int) $vo['shipping_paise'] > 0 ? 'shipping ' . e(store_rupees((int) $vo['shipping_paise'])) : 'free shipping' ?></span>
          </div>
          <?php foreach ($vo['items'] as $it): ?>
            <div class="st-line" style="grid-template-columns:56px 1fr auto">
              <span class="st-line-img" style="width:56px;height:56px"><?php if ($img = store_img($it['image_path'])): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?></span>
              <div class="st-line-body"><span class="st-line-name"><?= e($it['name']) ?></span>
                <div class="st-line-meta"><?= $it['variant_title'] ? e($it['variant_title']) . ' · ' : '' ?>Qty <?= (int) $it['qty'] ?> × <?= e(store_rupees((int) $it['unit_price_paise'])) ?></div></div>
              <div class="st-line-total"><?= e(store_rupees((int) $it['line_total_paise'])) ?></div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>
    </div>
    <aside class="st-summary">
      <h2>Payment summary</h2>
      <dl>
        <dt>Items</dt><dd><?= e(store_rupees((int) $order['items_subtotal_paise'])) ?></dd>
        <dt>Shipping</dt><dd><?= (int) $order['shipping_paise'] > 0 ? e(store_rupees((int) $order['shipping_paise'])) : 'Free' ?></dd>
        <dt class="st-total">Total</dt><dd class="st-total"><?= e(store_rupees((int) $order['grand_total_paise'])) ?></dd>
      </dl>
      <p class="st-summary-note">Includes <?= e(store_rupees((int) $order['tax_included_paise'])) ?> GST.</p>
      <h2 style="margin-top:18px">Delivering to</h2>
      <p class="st-summary-note" style="color:var(--st-navy)"><?= e($a['name'] ?? '') ?> · <?= e($a['phone'] ?? '') ?><br>
        <?= e($a['line1'] ?? '') ?><?= !empty($a['line2']) ? ', ' . e($a['line2']) : '' ?><br><?= e($a['city'] ?? '') ?>, <?= e($a['state'] ?? '') ?> – <?= e($a['pincode'] ?? '') ?></p>
    </aside>
  </div>
</main>
<?php if ($order['status'] === 'pending_payment'): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function storePay(orderNo, autoOpen) {
  const post = (body) => fetch('/api/store_pay', {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(Object.assign({ order_no: orderNo }, body)),
  }).then(r => r.json().catch(() => ({})));

  return {
    busy: false, msg: '',
    init() { if (autoOpen) { history.replaceState(null, '', location.pathname); this.pay(); } },
    async pay() {
      if (this.busy) return;
      this.busy = true; this.msg = '';
      const s = await post({ action: 'start' }).catch(() => ({}));
      if (!s.ok) { this.busy = false; this.msg = s.error || 'Could not start the payment. Please try again.'; return; }
      if (typeof Razorpay === 'undefined') { this.busy = false; this.msg = 'The payment window could not load. Check your connection and try again.'; return; }
      const c = s.checkout;
      const rzp = new Razorpay({
        key: c.key, order_id: c.order_id, amount: c.amount, currency: c.currency,
        name: c.name, description: c.description, prefill: c.prefill, notes: c.notes, theme: c.theme,
        handler: async (resp) => {
          this.msg = 'Confirming your payment…';
          const v = await post(Object.assign({ action: 'verify' }, resp)).catch(() => ({}));
          // Paid, or still processing: reload either way; the webhook finishes the job.
          if (!v.ok && v.state !== 'pending') this.msg = 'We are confirming your payment. This page will update shortly.';
          setTimeout(() => location.reload(), v.ok ? 0 : 2500);
        },
        modal: { ondismiss: () => { this.busy = false; } },
      });
      rzp.on('payment.failed', (r) => {
        this.busy = false;
        this.msg = 'Payment failed' + (r && r.error && r.error.description ? ': ' + r.error.description : '') + '. You can try again.';
      });
      rzp.open();
    },
  };
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
