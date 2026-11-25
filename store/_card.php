<?php
/**
 * Product card. Expects $p = a row with store_card_cols() columns.
 * Mirrors the mockup's ProductCard.
 */
$cardWish = store_wishlist_ids();
$cardPrice = (int) $p['min_price_paise'];
$cardMrp = (int) ($p['mrp_paise'] ?? 0);
$cardOff = store_off_pct($cardMrp, $cardPrice);
$cardImg = store_img($p['cover'] ?? null);
$cardAff = !empty($p['affiliate_url']);   // bought on the partner's site via /store/go/{id}
$cardUrl = $cardAff ? store_go_url((int) $p['id']) : store_url('p/' . $p['slug']);
$cardOut = $cardAff ? ' target="_blank" rel="sponsored nofollow noopener"' : '';
$cardOn = isset($cardWish[(int) $p['id']]);
?>
<article class="st-card">
  <a href="<?= e($cardUrl) ?>"<?= $cardOut ?> class="st-card-img" tabindex="-1" aria-hidden="true">
    <?php if ($cardImg): ?>
      <img src="<?= e($cardImg) ?>" alt="" loading="lazy" decoding="async">
    <?php else: ?>
      <span class="st-noimg">No photo</span>
    <?php endif; ?>
    <?php if ($cardOff >= 5 && empty($p['no_promotion'])): ?><span class="st-card-off"><?= $cardOff ?>% off</span><?php endif; ?>
    <?php if (empty($p['in_stock'])): ?><span class="st-card-oos">Out of stock</span><?php endif; ?>
  </a>
  <button type="button" class="st-wish <?= $cardOn ? 'is-on' : '' ?>" data-wish="<?= (int) $p['id'] ?>" aria-pressed="<?= $cardOn ? 'true' : 'false' ?>"
          aria-label="Save to wishlist" onclick="storeToggleWish(this, <?= (int) $p['id'] ?>)">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 20.5s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 3.2c0 5.4-7.5 10-7.5 10z"/></svg>
  </button>
  <div class="st-card-body">
    <?php if (!empty($p['brand_name'])): ?><span class="st-card-brand"><?= e($p['brand_name']) ?></span><?php endif; ?>
    <a href="<?= e($cardUrl) ?>"<?= $cardOut ?> class="st-card-name"><?= e($p['name']) ?></a>
    <?php if ($cardAff): ?>
      <span class="st-card-seller">Sold on <?= e(trim((string) ($p['affiliate_merchant'] ?? '')) ?: 'partner site') ?></span>
    <?php else: ?>
      <span class="st-card-seller">by <a href="<?= e(store_url('seller/' . $p['vendor_slug'])) ?>"><?= e($p['vendor_name']) ?></a></span>
    <?php endif; ?>
    <?php if ((int) ($p['rating_count'] ?? 0) > 0): ?>
      <span class="st-rating"><b>★ <?= e(number_format((float) $p['rating_avg'], 1)) ?></b> (<?= (int) $p['rating_count'] ?>)</span>
    <?php endif; ?>
    <div class="st-price">
      <?php if ((int) ($p['variant_count'] ?? 1) > 1): ?><span style="font-size:12.5px;color:var(--st-mute)">From</span><?php endif; ?>
      <b><?= e(store_rupees($cardPrice)) ?></b>
      <?php if ($cardOff > 0): ?><s><?= e(store_rupees($cardMrp)) ?></s><?php if (empty($p['no_promotion'])): ?><span class="st-off"><?= $cardOff ?>% off</span><?php endif; ?><?php endif; ?>
    </div>
    <?php if ($cardAff): ?>
      <a href="<?= e($cardUrl) ?>"<?= $cardOut ?> class="st-btn st-btn-ghost st-btn-sm st-card-buy"><span class="st-card-buy-t"><?= e(store_buy_label($p)) ?></span> <span aria-hidden="true">↗</span></a>
      <span class="st-card-aff">Ad · affiliate link · price may vary</span>
    <?php endif; ?>
  </div>
</article>
