<?php /** Storefront footer: closes .st-page (opened in _header.php), store JS, then the MAIN SITE footer (which closes body/html). The login modal comes from partials/header.php. */ ?>
<!-- Store trust strip (the full site footer follows). -->
<section class="st-trust">
  <div class="st-wrap st-trust-row">
    <span>✓ Genuine products</span>
    <span>✓ Verified sellers</span>
    <span>✓ Every listing reviewed by our team</span>
    <span>✓ GST invoice with every order</span>
    <a href="<?= e(ecp_portal_url('/vendor/register')) ?>" class="st-trust-sell">Sell on eClinicPro Store →</a>
  </div>
  <p class="st-wrap st-trust-note">Products are sold by independent sellers. Not a substitute for medical advice: consult your doctor before starting any supplement.</p>
</section>
</div><!-- /.st-page -->

<div x-data="{ msg: '', t: null }" @store-toast.window="msg = $event.detail; clearTimeout(t); t = setTimeout(() => msg = '', 3200)">
  <div class="st-toast" x-show="msg" x-cloak x-text="msg" role="status"></div>
</div>


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
<?php
$hideFinalCta = true;   // no clinic-software CTA on shop pages
require __DIR__ . '/../partials/footer.php';

