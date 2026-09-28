<?php
// =====================================================================
// store/categories.php — /store/categories (every department + subcategory)
// =====================================================================

require_once __DIR__ . '/_lib.php';
store_gate();

$storeTitle = 'All categories | eClinicPro Store';
$storeDesc = 'Browse every department on eClinicPro Store: nutrition, devices, mother & baby, personal care, Ayurveda and more.';
$storeCanonical = '/store/categories';
$storeActive = 'categories';
require __DIR__ . '/_header.php';
?>
<main class="st-wrap" style="padding-bottom:64px">
  <nav class="st-crumbs" aria-label="Breadcrumb"><a href="<?= store_url() ?>">Store</a> / <span>All categories</span></nav>
  <header class="st-list-head">
    <span class="st-eyebrow">Explore our range</span>
    <h1 class="st-h2">All <em>categories</em></h1>
  </header>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,280px),1fr));gap:28px 32px;padding-top:28px">
    <?php foreach (store_tree() as $d): ?>
      <?php if (!$d['subs']) { continue; } ?>
      <section>
        <h2 style="margin:0 0 10px;font:500 22px 'Newsreader',serif"><a href="<?= e(store_url('c/' . $d['slug'])) ?>" style="text-decoration:none;color:var(--st-navy)"><?= e($d['name']) ?></a></h2>
        <ul style="list-style:none;margin:0;padding:0;display:grid;gap:6px;font-size:14px">
          <?php foreach ($d['subs'] as $s): ?>
            <li><a href="<?= e(store_url('c/' . $d['slug'] . '/' . $s['slug'])) ?>" style="color:var(--st-ink-2);text-decoration:none"><?= e($s['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
