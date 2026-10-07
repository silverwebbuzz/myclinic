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
\App\Services\Store\FulfilmentService::autoCancelOverdue();
$order = preg_match('/^ECS[0-9]{6}-[A-Z0-9]{5,8}$/', $orderNo) ? OrderService::findForIdentity($orderNo, (int) $me['id']) : null;
if ($order === null) {
    store_not_found();
}

// GST invoice / credit note for this order (printable page).
if (isset($_GET['doc'])) {
    $doc = \App\Services\Store\TaxDocumentService::load((int) $_GET['doc']);
    if ($doc === null || (int) $doc['order_id'] !== (int) $order['id']) {
        store_not_found();
    }
    $backUrl = '/store/order/' . rawurlencode($orderNo);
    header('X-Robots-Tag: noindex');
    require dirname(__DIR__) . '/app/views/components/store_tax_document.php';
    exit;
}
$taxDocsByVo = [];
foreach (\App\Services\Store\TaxDocumentService::forOrder((int) $order['id']) as $d) {
    $taxDocsByVo[(int) $d['vendor_order_id']][] = $d;
}

// Customer cancels their own unpaid order.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'cancel' && store_same_origin()) {
    OrderService::release((int) $order['id'], 'cancelled', 'Cancelled by customer before payment', 'customer', (int) $me['id']);
    header('Location: /store/order/' . rawurlencode($orderNo));
    exit;
}
// Customer cancels items from a package the seller hasn't packed yet → partial refund.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'cancel_items' && store_same_origin()) {
    $qty = [];
    foreach ((array) ($_POST['cancel'] ?? []) as $itemId => $q) {
        $qty[(int) $itemId] = (int) $q;
    }
    $res = \App\Services\Store\StoreRefundService::cancelItems((int) $order['id'], $qty,
        'Customer: ' . mb_substr(trim((string) ($_POST['reason'] ?? 'Changed my mind')), 0, 120), 'customer', (int) $me['id'], true);
    $msg = $res['ok']
        ? 'Cancelled. ' . store_rupees((int) $res['refunded']) . ' is being refunded to your original payment method (5–7 working days).'
        : ($res['error'] ?? 'Could not cancel. Please try again.');
    header('Location: /store/order/' . rawurlencode($orderNo) . '?' . http_build_query(['r' => $res['ok'] ? 'ok' : 'err', 'm' => $msg]));
    exit;
}
// Customer reviews a delivered item.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'review' && store_same_origin()) {
    $res = \App\Services\Store\ReviewService::create((int) $me['id'], (int) ($_POST['order_item_id'] ?? 0), (int) ($_POST['rating'] ?? 0),
        (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''));
    $msg = $res['ok'] ? 'Thanks for your review! It will appear on the product page after a quick check.' : ($res['error'] ?? 'Could not save your review.');
    header('Location: /store/order/' . rawurlencode($orderNo) . '?' . http_build_query(['r' => $res['ok'] ? 'ok' : 'err', 'm' => $msg]));
    exit;
}
// Customer requests a return on a delivered package.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'return_request' && store_same_origin()) {
    $qty = [];
    foreach ((array) ($_POST['ret'] ?? []) as $itemId => $q) {
        $qty[(int) $itemId] = (int) $q;
    }
    $photos = isset($_FILES['photos']) && is_array($_FILES['photos']) ? $_FILES['photos'] : [];
    $res = \App\Services\Store\ReturnService::request((int) $me['id'], (int) ($_POST['vendor_order_id'] ?? 0), $qty,
        (string) ($_POST['reason'] ?? ''), (string) ($_POST['note'] ?? ''), $photos);
    $msg = $res['ok']
        ? 'Return ' . $res['return_no'] . ' submitted. The seller will review it within 2 days; we\'ll email you.'
        : ($res['error'] ?? 'Could not submit the return.');
    header('Location: /store/order/' . rawurlencode($orderNo) . '?' . http_build_query(['r' => $res['ok'] ? 'ok' : 'err', 'm' => $msg]));
    exit;
}
$flash = isset($_GET['m']) ? ['ok' => ($_GET['r'] ?? '') === 'ok', 'msg' => mb_substr((string) $_GET['m'], 0, 300)] : null;

$packageStatus = [
    'pending_payment' => 'Awaiting payment', 'new' => 'Confirmed: seller preparing', 'accepted' => 'Seller preparing',
    'packed' => 'Packed', 'ready_to_ship' => 'Ready to ship', 'shipped' => 'Shipped', 'delivered' => 'Delivered',
    'completed' => 'Delivered', 'auto_cancelled' => 'Cancelled', 'cancelled_by_customer' => 'Cancelled',
    'cancelled_by_vendor' => 'Cancelled by seller', 'cancelled_by_admin' => 'Cancelled', 'rto' => 'Not delivered: returned to seller',
    'lost_in_transit' => 'Lost or damaged in transit: refunded',
];
$statusText = [
    'pending_payment' => ['Awaiting payment', 'is-warn'],
    'paid' => ['Order confirmed', 'is-ok'],
    'refunded' => ['Refunded', 'is-muted'],
    'partially_shipped' => ['Partly shipped', 'is-ok'],
    'shipped' => ['Shipped', 'is-ok'],
    'partially_delivered' => ['Partly delivered', 'is-ok'],
    'delivered' => ['Delivered', 'is-ok'],
    'completed' => ['Completed', 'is-ok'],
    'expired' => ['Payment window expired', 'is-muted'],
    'cancelled' => ['Cancelled', 'is-muted'],
    'payment_failed' => ['Payment failed', 'is-err'],
][$order['status']] ?? [ucwords(str_replace('_', ' ', (string) $order['status'])), 'is-muted'];
$a = $order['ship_address'];
$reviewable = \App\Services\Store\ReviewService::reviewable((int) $me['id'], (int) $order['id']);
$shipByVo = [];
foreach (\App\Services\Store\ShippingService::forOrder((int) $order['id']) as $s) {
    if ($s['status'] !== 'cancelled' && $s['direction'] === 'forward') {
        $shipByVo[(int) $s['vendor_order_id']] = $s;   // latest active shipment per package
    }
}
$trackLabel = [
    'sr_order_created' => 'Preparing shipment', 'awb_assigned' => 'Courier assigned', 'pickup_scheduled' => 'Pickup scheduled',
    'pickup_failed' => 'Pickup being rescheduled', 'picked_up' => 'Picked up', 'in_transit' => 'In transit',
    'out_for_delivery' => 'Out for delivery', 'ndr' => 'Delivery attempt failed: courier will retry',
    'delivered' => 'Delivered', 'rto_initiated' => 'Returning to seller', 'rto_delivered' => 'Returned to seller',
    'lost' => 'Lost in transit (we\'re on it)', 'damaged' => 'Damaged in transit (we\'re on it)',
];

$storeTitle = 'Order ' . $order['order_no'] . ' | eClinicPro Store';
require __DIR__ . '/_header.php';
?>
<main class="st-wrap" style="padding-bottom:64px">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <a href="<?= store_url('orders') ?>">My orders</a> / <span>Order <?= e($order['order_no']) ?></span></nav>
  <header class="st-list-head" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;align-items:flex-end">
    <div>
      <span class="st-eyebrow">Order <?= e($order['order_no']) ?></span>
      <h1 class="st-h2"><?= e($statusText[0]) ?></h1>
      <p class="st-lede">Placed <?= e(date('j M Y, g:i a', (int) strtotime((string) $order['placed_at']))) ?> · <?= count($order['vendor_orders']) ?> package<?= count($order['vendor_orders']) === 1 ? '' : 's' ?></p>
    </div>
    <span class="st-status <?= $statusText[1] ?>"><?= e($statusText[0]) ?></span>
  </header>

  <?php if ($flash): ?>
    <div class="st-note <?= $flash['ok'] ? '' : 'st-note-err' ?>" role="status" style="margin-top:20px"><?= e($flash['msg']) ?></div>
  <?php endif; ?>
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
  <?php elseif (in_array($order['status'], ['expired', 'cancelled'], true) && $order['payment_status'] === 'pending'): ?>
    <div class="st-note" style="margin-top:20px">This order was not paid, so nothing was charged. <a href="<?= store_url('cart') ?>">Go to your cart</a> to try again.</div>
  <?php elseif ($order['status'] === 'cancelled'): ?>
    <div class="st-note st-note-warn" style="margin-top:20px">This order was cancelled and <strong>refunded</strong> to your original payment method (see the summary). Refunds usually arrive in 5–7 working days.</div>
  <?php endif; ?>

  <div class="st-checkout-grid" style="margin-top:24px">
    <div>
      <?php foreach ($order['vendor_orders'] as $vo): ?>
        <section class="st-seller-group">
          <div class="st-seller-group-head">
            <span>Package <?= e(substr((string) $vo['sub_order_no'], -1)) ?> · sold by <a href="<?= e(store_url('seller/' . $vo['vendor_slug'])) ?>"><strong><?= e($vo['vendor_name']) ?></strong></a></span>
            <span class="st-ship-note"><?= e($packageStatus[$vo['status']] ?? ucfirst(str_replace('_', ' ', (string) $vo['status']))) ?></span>
          </div>
          <?php foreach ($vo['items'] as $it): ?>
            <div class="st-line" style="grid-template-columns:56px 1fr auto">
              <span class="st-line-img" style="width:56px;height:56px"><?php if ($img = store_img($it['image_path'])): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?></span>
              <div class="st-line-body"><span class="st-line-name"><?= e($it['name']) ?></span>
                <div class="st-line-meta"><?= $it['variant_title'] ? e($it['variant_title']) . ' · ' : '' ?>Qty <?= (int) $it['qty'] ?> × <?= e(store_rupees((int) $it['unit_price_paise'])) ?></div>
                <?php if ((int) $it['qty_cancelled'] > 0): ?><div class="st-line-problem"><?= (int) $it['qty_cancelled'] ?> cancelled &amp; refunded</div><?php endif; ?>
                <?php if (isset($reviewable[(int) $it['id']])): ?>
                  <details style="margin-top:6px">
                    <summary class="st-link-btn" style="list-style:none">★ Rate this product</summary>
                    <form method="post" style="display:grid;gap:6px;margin-top:8px;font-size:14px">
                      <input type="hidden" name="action" value="review">
                      <input type="hidden" name="order_item_id" value="<?= (int) $it['id'] ?>">
                      <select name="rating" class="st-select" required style="height:34px">
                        <option value="">Your rating…</option>
                        <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"><?= str_repeat('★', $s) . str_repeat('☆', 5 - $s) ?></option><?php endfor; ?>
                      </select>
                      <input name="title" maxlength="190" class="st-input" placeholder="Headline (optional)">
                      <textarea name="body" rows="3" maxlength="3000" class="st-input" style="height:auto;padding:8px 12px" placeholder="How was it? Quality, packaging, value…"></textarea>
                      <p class="st-summary-note" style="margin:0">Please share your experience with the product, not medical claims.</p>
                      <button class="st-btn st-btn-ghost st-btn-sm">Submit review</button>
                    </form>
                  </details>
                <?php endif; ?></div>
              <div class="st-line-total"><?= e(store_rupees((int) $it['line_total_paise'])) ?></div>
            </div>
          <?php endforeach; ?>
          <?php $sh = $shipByVo[(int) $vo['id']] ?? null; ?>
          <?php if ($sh !== null && !empty($sh['awb_code'])): ?>
            <div class="st-track">
              <div class="st-track-head">
                <strong><?= e($trackLabel[$sh['status']] ?? ucfirst(str_replace('_', ' ', (string) $sh['status']))) ?></strong>
                <span class="st-line-meta"><?= !empty($sh['courier_name']) ? e($sh['courier_name']) . ' · ' : '' ?>AWB <?= e($sh['awb_code']) ?></span>
                <a class="st-link-btn" href="https://shiprocket.co/tracking/<?= rawurlencode((string) $sh['awb_code']) ?>" target="_blank" rel="noopener">Track package ↗</a>
              </div>
              <?php if ($sh['events']): ?>
                <ol class="st-track-events">
                  <?php foreach (array_slice($sh['events'], 0, 4) as $ev): ?>
                    <li><span><?= e(date('j M, g:i a', (int) strtotime((string) $ev['event_at']))) ?></span> <?= e(ucwords(strtolower((string) $ev['raw_status']))) ?><?= !empty($ev['location']) ? ' · ' . e($ev['location']) : '' ?></li>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($taxDocsByVo[(int) $vo['id']])): ?>
            <p class="st-line-meta" style="margin:8px 0 0">
              <?php foreach ($taxDocsByVo[(int) $vo['id']] as $i => $d): ?>
                <?= $i > 0 ? ' · ' : '' ?><a class="st-link-btn" href="?doc=<?= (int) $d['id'] ?>" target="_blank" rel="noopener"><?= e($d['doc_type'] === 'credit_note' ? 'Credit note' : ($d['issuer'] === 'platform' ? 'Delivery invoice' : 'Tax invoice')) ?> <?= e($d['doc_no']) ?></a>
              <?php endforeach; ?>
            </p>
          <?php endif; ?>
          <?php
          $openLines = array_filter($vo['items'], static fn ($it) => (int) $it['qty'] > (int) $it['qty_cancelled']);
          $customerCanCancel = in_array($vo['status'], \App\Services\Store\StoreRefundService::CANCELLABLE['customer'], true)
              && in_array($order['payment_status'], ['paid', 'partially_refunded'], true) && $openLines;
          ?>
          <?php foreach ($order['returns'][(int) $vo['id']] ?? [] as $rt): ?>
            <?php $rtLabel = ['requested' => 'waiting for the seller', 'approved' => 'approved: pickup being arranged', 'pickup_scheduled' => 'pickup scheduled',
                'picked_up' => 'on its way back', 'received' => 'received by seller', 'qc_passed' => 'checked', 'qc_failed' => 'under review by eClinicPro',
                'refunded' => 'refunded ' . store_rupees((int) $rt['refund_amount_paise']), 'rejected' => 'not accepted' . (!empty($rt['vendor_note']) ? ': ' . $rt['vendor_note'] : ''),
                'closed' => 'closed'][$rt['status']] ?? $rt['status']; ?>
            <div class="st-note" style="margin:8px 0 0;font-size:13px">Return <?= e($rt['return_no']) ?>: <strong><?= e($rtLabel) ?></strong></div>
          <?php endforeach; ?>
          <?php $returnable = \App\Services\Store\ReturnService::returnable($vo); ?>
          <?php if ($returnable): ?>
            <details style="padding:10px 0 4px">
              <summary class="st-link-btn" style="list-style:none">Return items (until <?= e(date('j M', (int) strtotime((string) $vo['settle_after']))) ?>)</summary>
              <form method="post" enctype="multipart/form-data" style="margin-top:10px;display:grid;gap:8px;font-size:14px">
                <input type="hidden" name="action" value="return_request">
                <input type="hidden" name="vendor_order_id" value="<?= (int) $vo['id'] ?>">
                <?php foreach ($vo['items'] as $it): ?>
                  <?php if (!isset($returnable[(int) $it['id']])) { continue; } ?>
                  <label style="display:flex;justify-content:space-between;gap:10px;align-items:center"><span><?= e($it['name']) ?></span>
                    <select name="ret[<?= (int) $it['id'] ?>]" class="st-select" style="height:34px">
                      <?php for ($q = 0; $q <= $returnable[(int) $it['id']]; $q++): ?><option value="<?= $q ?>"><?= $q === 0 ? 'Keep' : 'Return ' . $q ?></option><?php endfor; ?>
                    </select></label>
                <?php endforeach; ?>
                <select name="reason" class="st-select" required>
                  <?php foreach (\App\Services\Store\ReturnService::REASONS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?>
                </select>
                <textarea name="note" rows="2" maxlength="1000" class="st-input" style="height:auto;padding:8px 12px" placeholder="What's wrong? (helps the seller decide faster)"></textarea>
                <label style="font-size:13px;color:var(--st-ink-2)">Photos (up to 3, required for damaged / wrong / expired / defective)
                  <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple style="display:block;margin-top:4px"></label>
                <button class="st-btn st-btn-ghost st-btn-sm">Request return</button>
                <p class="st-summary-note" style="margin:0">Products that aren't returnable (e.g. opened food or hygiene items) aren't listed. You're refunded after the seller receives and checks the item.</p>
              </form>
            </details>
          <?php endif; ?>
          <?php if ($customerCanCancel): ?>
            <details style="padding:10px 0 4px">
              <summary class="st-link-btn" style="list-style:none">Cancel items from this package</summary>
              <form method="post" style="margin-top:10px;display:grid;gap:8px;font-size:14px" onsubmit="return confirm('Cancel the selected items? You will be refunded.')">
                <input type="hidden" name="action" value="cancel_items">
                <?php foreach ($openLines as $it): ?>
                  <?php $left = (int) $it['qty'] - (int) $it['qty_cancelled']; ?>
                  <label style="display:flex;justify-content:space-between;gap:10px;align-items:center"><span><?= e($it['name']) ?></span>
                    <select name="cancel[<?= (int) $it['id'] ?>]" class="st-select" style="height:34px">
                      <?php for ($q = 0; $q <= $left; $q++): ?><option value="<?= $q ?>"><?= $q === 0 ? 'Keep' : 'Cancel ' . $q ?></option><?php endfor; ?>
                    </select></label>
                <?php endforeach; ?>
                <select name="reason" class="st-select">
                  <option>Changed my mind</option><option>Ordered by mistake</option><option>Found a better price</option><option>Delivery is too slow</option><option>Other</option>
                </select>
                <button class="st-btn st-btn-ghost st-btn-sm">Cancel selected &amp; refund</button>
              </form>
            </details>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
    <aside class="st-summary">
      <h2>Payment summary</h2>
      <dl>
        <dt>Items</dt><dd><?= e(store_rupees((int) $order['items_subtotal_paise'])) ?></dd>
        <?php if ((int) $order['discount_paise'] > 0): ?><dt>Coupon <?= e((string) $order['coupon_code']) ?></dt><dd class="st-save">−<?= e(store_rupees((int) $order['discount_paise'])) ?></dd><?php endif; ?>
        <?php if ((int) ($order['points_discount_paise'] ?? 0) > 0): ?><dt><?= ($order['points_kind'] ?? '') === 'welcome' ? 'Welcome points' : 'eClinicPro Points' ?> (<?= (int) $order['points_used'] ?> pts)</dt><dd class="st-save">−<?= e(store_rupees((int) $order['points_discount_paise'])) ?></dd><?php endif; ?>
        <dt>Delivery</dt><dd><?= (int) $order['shipping_paise'] > 0 ? e(store_rupees((int) $order['shipping_paise'])) : 'Free' ?></dd>
        <dt class="st-total">Total</dt><dd class="st-total"><?= e(store_rupees((int) $order['grand_total_paise'])) ?></dd>
      </dl>
      <p class="st-summary-note">Includes <?= e(store_rupees((int) $order['tax_included_paise'])) ?> GST.</p>
      <?php foreach ($order['refunds'] as $rf): ?>
        <p class="st-summary-note" style="color:var(--st-green-700)">Refund <?= e(store_rupees((int) $rf['amount_paise'])) ?> · <?= $rf['status'] === 'processed' ? 'processed' : 'in progress' ?> (<?= e($rf['refund_no']) ?>)</p>
      <?php endforeach; ?>
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
