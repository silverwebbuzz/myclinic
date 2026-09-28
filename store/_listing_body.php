<?php
/**
 * Filters sidebar + toolbar + product grid + pager.
 * Expects $result (store_listing() output) and optional $sideLinks
 * (list of ['label','href','active','count?']) for a "Browse" block.
 */
$sideLinks = $sideLinks ?? [];
$curBrand = (int) ($_GET['brand'] ?? 0);
$curSeller = (int) ($_GET['seller'] ?? 0);
$curSort = (string) ($_GET['sort'] ?? '');
$sorts = ['' => 'Recommended', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'newest' => 'Newest', 'rating' => 'Top rated'];
if (!empty($_GET['q'])) {
    $sorts = ['relevance' => 'Best match'] + $sorts;
}
?>
<div class="st-list-layout" x-data="{ filters: false }">
  <aside class="st-filters" :class="filters ? 'is-open' : ''" aria-label="Filters">
    <?php if ($sideLinks): ?>
      <div class="st-filter-block">
        <h3>Browse</h3>
        <ul>
          <?php foreach ($sideLinks as $l): ?>
            <li><a href="<?= e($l['href']) ?>" class="<?= !empty($l['active']) ? 'is-active' : '' ?>"><?= e($l['label']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="get" class="st-filter-block">
      <?php foreach (['q', 'sort', 'brand', 'seller'] as $keep): ?>
        <?php if (!empty($_GET[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= e((string) $_GET[$keep]) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <h3>Price (₹)</h3>
      <div class="st-price-inputs">
        <input class="st-input" type="number" min="0" name="min" placeholder="Min" value="<?= e((string) ($_GET['min'] ?? '')) ?>" aria-label="Minimum price">
        <span>–</span>
        <input class="st-input" type="number" min="0" name="max" placeholder="Max" value="<?= e((string) ($_GET['max'] ?? '')) ?>" aria-label="Maximum price">
      </div>
      <label style="margin-top:10px"><input type="checkbox" name="in_stock" value="1" <?= !empty($_GET['in_stock']) ? 'checked' : '' ?>> In stock only</label>
      <button class="st-btn st-btn-ghost st-btn-sm" style="margin-top:10px;width:100%">Apply</button>
    </form>

    <?php if (count($result['brands']) > 0): ?>
      <div class="st-filter-block">
        <h3>Brand</h3>
        <ul>
          <?php foreach ($result['brands'] as $b): ?>
            <?php $on = $curBrand === (int) $b['id']; ?>
            <li><a href="<?= e(store_qs(['brand' => $on ? '' : (int) $b['id'], 'page' => ''])) ?>" class="<?= $on ? 'is-active' : '' ?>"><?= e($b['name']) ?> <span><?= (int) $b['n'] ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if (count($result['sellers']) > 1): ?>
      <div class="st-filter-block">
        <h3>Seller</h3>
        <ul>
          <?php foreach ($result['sellers'] as $s): ?>
            <?php $on = $curSeller === (int) $s['id']; ?>
            <li><a href="<?= e(store_qs(['seller' => $on ? '' : (int) $s['id'], 'page' => ''])) ?>" class="<?= $on ? 'is-active' : '' ?>"><?= e($s['name']) ?> <span><?= (int) $s['n'] ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </aside>

  <section>
    <div class="st-toolbar">
      <span><?= number_format($result['total']) ?> product<?= $result['total'] === 1 ? '' : 's' ?></span>
      <span style="display:flex;gap:8px;align-items:center">
        <button type="button" class="st-btn st-btn-ghost st-btn-sm st-filter-toggle" @click="filters = !filters">Filters</button>
        <form method="get">
          <?php foreach (['q', 'brand', 'seller', 'min', 'max', 'in_stock'] as $keep): ?>
            <?php if (!empty($_GET[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= e((string) $_GET[$keep]) ?>"><?php endif; ?>
          <?php endforeach; ?>
          <label class="st-sr" for="st-sort">Sort by</label>
          <select id="st-sort" name="sort" class="st-select" onchange="this.form.submit()">
            <?php foreach ($sorts as $k => $label): ?>
              <option value="<?= e($k) ?>" <?= $curSort === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </span>
    </div>

    <?php if (!$result['rows']): ?>
      <div class="st-empty">
        <h3>Nothing here yet</h3>
        <p>New products are being added by our sellers. Try clearing filters, or <a href="<?= store_url() ?>">browse the store</a>.</p>
      </div>
    <?php else: ?>
      <div class="st-grid is-3">
        <?php foreach ($result['rows'] as $p): ?>
          <?php require __DIR__ . '/_card.php'; ?>
        <?php endforeach; ?>
      </div>
      <?php if ($result['pages'] > 1): ?>
        <nav class="st-pager" aria-label="Pages">
          <?php if ($result['page'] > 1): ?><a href="<?= e(store_qs(['page' => $result['page'] - 1])) ?>" rel="prev">←</a><?php endif; ?>
          <?php for ($i = max(1, $result['page'] - 2); $i <= min($result['pages'], $result['page'] + 2); $i++): ?>
            <?php if ($i === $result['page']): ?><span class="is-current"><?= $i ?></span><?php else: ?><a href="<?= e(store_qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($result['page'] < $result['pages']): ?><a href="<?= e(store_qs(['page' => $result['page'] + 1])) ?>" rel="next">→</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
