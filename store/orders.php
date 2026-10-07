<?php
// =====================================================================
// store/orders.php — /store/orders
// The signed-in customer's orders (newest first). Each row opens /store/order/{no}.
// =====================================================================

use App\Services\Store\OrderService;

require_once __DIR__ . '/_lib.php';
require_once __DIR__ . '/_app.php';
store_gate();
store_app_required();

$me = ecp_patient_current();
$storeTitle = 'My orders | eClinicPro Store';
if (!$me) {
    require __DIR__ . '/_header.php';
    ?>
    <main class="st-wrap" style="padding:40px 0 64px">
      <div class="st-empty"><h3>Sign in to see your orders</h3>
        <button type="button" class="st-btn st-btn-primary" style="margin-top:10px" onclick="window.ecpAuth && window.ecpAuth.open('default')">Sign in with mobile</button></div>
    </main>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

OrderService::expireStale();
try {
    $orders = OrderService::listForIdentity((int) $me['id']);
} catch (Throwable $e) {
    error_log('[store/orders] ' . $e->getMessage());
    $orders = [];
}
$statusText = [
    'pending_payment' => ['Awaiting payment', 'is-warn'], 'paid' => ['Confirmed', 'is-ok'],
    'partially_shipped' => ['Partly shipped', 'is-ok'], 'shipped' => ['Shipped', 'is-ok'],
    'partially_delivered' => ['Partly delivered', 'is-ok'], 'delivered' => ['Delivered', 'is-ok'], 'completed' => ['Delivered', 'is-ok'],
    'refunded' => ['Refunded', 'is-muted'], 'expired' => ['Not paid', 'is-muted'], 'cancelled' => ['Cancelled', 'is-muted'],
    'payment_failed' => ['Payment failed', 'is-err'],
];

require __DIR__ . '/_header.php';
?>
<main class="st-wrap" style="padding-bottom:64px">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <span>My orders</span></nav>
  <header class="st-list-head">
    <h1 class="st-h2">My orders</h1>
    <p class="st-lede">Track packages, download invoices, cancel or return items.</p>
  </header>

  <?php if (!$orders): ?>
    <div class="st-empty" style="margin-top:24px"><h3>No orders yet</h3>
      <p>When you buy something, it will appear here.</p>
      <a href="<?= store_url() ?>" class="st-btn st-btn-primary" style="margin-top:10px">Start shopping</a></div>
  <?php else: ?>
    <div style="display:grid;gap:12px;margin-top:20px">
      <?php foreach ($orders as $o): ?>
        <?php
        [$label, $cls] = $statusText[$o['status']] ?? [ucfirst(str_replace('_', ' ', (string) $o['status'])), 'is-muted'];
        if ($o['payment_status'] === 'partially_refunded' && $cls === 'is-ok') {
            $label .= ' · part refunded';
        }
        $preview = array_slice($o['items'], 0, 4);
        $more = count($o['items']) - count($preview);
        $names = implode(', ', array_map(static fn ($it) => $it['name'], array_slice($o['items'], 0, 2)));
        ?>
        <a href="<?= e(store_url('order/' . $o['order_no'])) ?>" class="st-seller-group" style="display:block;text-decoration:none;color:inherit">
          <div class="st-seller-group-head">
            <span><strong>Order <?= e($o['order_no']) ?></strong> · <?= e(date('d M Y', (int) strtotime((string) $o['placed_at']))) ?> · <?= (int) $o['packages'] ?> package<?= (int) $o['packages'] === 1 ? '' : 's' ?></span>
            <span class="st-status <?= $cls ?>" style="padding:3px 10px;font-size:12px"><?= e($label) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:12px;padding:12px 16px;flex-wrap:wrap">
            <span style="display:flex;gap:6px">
              <?php foreach ($preview as $it): ?>
                <span class="st-line-img" style="width:48px;height:48px;border-radius:10px"><?php if ($img = store_img($it['image_path'])): ?><img src="<?= e($img) ?>" alt=""><?php endif; ?></span>
              <?php endforeach; ?>
              <?php if ($more > 0): ?><span class="st-line-img" style="width:48px;height:48px;border-radius:10px;display:grid;place-items:center;font-size:13px">+<?= (int) $more ?></span><?php endif; ?>
            </span>
            <span class="st-line-meta" style="flex:1;min-width:160px"><?= e($names) ?><?= count($o['items']) > 2 ? ' and more' : '' ?></span>
            <strong><?= e(store_rupees((int) $o['grand_total_paise'])) ?></strong>
            <?php if ($o['status'] === 'pending_payment'): ?><span class="st-btn st-btn-primary st-btn-sm">Pay now</span><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php
require __DIR__ . '/_footer.php';
