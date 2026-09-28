<?php /** Storefront footer + shared login modal + wishlist/toast JS. */ ?>
<footer class="st-footer">
  <div class="st-wrap">
    <div class="st-footer-grid">
      <div>
        <div class="st-logo-text" style="color:#fff">eClinic<em>Pro</em> Store</div>
        <p style="margin:12px 0 0;max-width:340px">Health, wellness and everyday care products from verified sellers, brought to you by eClinicPro.</p>
      </div>
      <div>
        <h4>Shop</h4>
        <ul>
          <?php foreach (array_slice(array_values(array_filter(store_nav(), static fn ($n) => empty($n['url']))), 0, 6) as $n): ?>
            <li><a href="<?= store_url('need/' . $n['slug']) ?>"><?= e($n['label']) ?></a></li>
          <?php endforeach; ?>
          <li><a href="<?= store_url('categories') ?>">All categories</a></li>
        </ul>
      </div>
      <div>
        <h4>Health</h4>
        <ul>
          <li><a href="/lab">Book a lab test</a></li>
          <li><a href="/find-a-doctor">Find a doctor</a></li>
          <li><a href="/patient">My health account</a></li>
        </ul>
      </div>
      <div>
        <h4>Sell &amp; help</h4>
        <ul>
          <li><a href="<?= e(ecp_portal_url('/vendor/register')) ?>">Sell on eClinicPro Store</a></li>
          <li><a href="/contact">Contact us</a></li>
          <li><a href="/refund-policy">Refund policy</a></li>
          <li><a href="/privacy-policy">Privacy policy</a></li>
          <li><a href="/terms">Terms</a></li>
        </ul>
      </div>
    </div>
    <div class="st-footer-bottom">
      <span>© <?= date('Y') ?> eClinicPro. Products are sold by independent sellers.</span>
      <span>Not a substitute for medical advice. Consult your doctor before starting any supplement.</span>
    </div>
  </div>
</footer>

<div x-data="{ msg: '', t: null }" @store-toast.window="msg = $event.detail; clearTimeout(t); t = setTimeout(() => msg = '', 3200)">
  <div class="st-toast" x-show="msg" x-cloak x-text="msg" role="status"></div>
</div>

<?php require __DIR__ . '/../partials/auth-modal.php'; ?>

<script>
function storeToast(msg) { window.dispatchEvent(new CustomEvent('store-toast', { detail: msg })); }

/** Toggle a product in the wishlist; opens the login modal when signed out. */
async function storeToggleWish(btn, productId) {
  const send = async () => {
    const r = await fetch('/api/store_wishlist', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ product_id: productId }),
    });
    if (r.status === 401) { window.ecpAuth && window.ecpAuth.open('default'); return; }
    const j = await r.json().catch(() => ({}));
    if (!j.ok) { storeToast('Could not update your wishlist. Please try again.'); return; }
    document.querySelectorAll('[data-wish="' + productId + '"]').forEach(b => {
      b.classList.toggle('is-on', j.saved);
      b.setAttribute('aria-pressed', j.saved ? 'true' : 'false');
    });
    const badge = document.getElementById('st-wish-count');
    if (badge) { badge.textContent = j.count; badge.hidden = !j.count; }
    storeToast(j.saved ? 'Saved to your wishlist' : 'Removed from your wishlist');
  };
  try { await send(); } catch (e) { storeToast('Network error. Please try again.'); }
}
</script>
</body>
</html>
