<?php
// =====================================================================
// store/listing.php — every product-grid page except sellers:
//   /store/c/{dept}[/{sub}]   category (department or subcategory)
//   /store/need/{slug}        "Shop by need" menu item
//   /store/goal/{slug}        "Shop by health goal"
//   /store/brand/{slug}       brand
//   /store/search?q=          search
// Routing params arrive as _r_type/_r_a/_r_b (see .htaccess + request_router).
// =====================================================================

require_once __DIR__ . '/_lib.php';
store_gate();

$type = (string) ($_GET['_r_type'] ?? 'search');
$a = strtolower((string) ($_GET['_r_a'] ?? ''));
$b = strtolower((string) ($_GET['_r_b'] ?? ''));
unset($_GET['_r_type'], $_GET['_r_a'], $_GET['_r_b']);   // keep filter/sort links clean

$filters = [
    'brand' => (int) ($_GET['brand'] ?? 0),
    'seller' => (int) ($_GET['seller'] ?? 0),
    'min' => max(0, (int) ($_GET['min'] ?? 0)),
    'max' => max(0, (int) ($_GET['max'] ?? 0)),
    'in_stock' => !empty($_GET['in_stock']),
    'sort' => (string) ($_GET['sort'] ?? ''),
    'page' => (int) ($_GET['page'] ?? 1),
];

$eyebrow = 'Shop';
$title = '';
$intro = '';
$crumbs = [['Store', store_url()]];
$sideLinks = [];
$storeActive = '';
$canonical = null;

switch ($type) {
    case 'category':
        $dept = null;
        $sub = null;
        foreach (store_tree() as $d) {
            if ($d['slug'] === $a) {
                $dept = $d;
                foreach ($d['subs'] as $s) {
                    if ($b !== '' && $s['slug'] === $b) {
                        $sub = $s;
                    }
                }
            }
        }
        if ($dept === null || ($b !== '' && $sub === null)) {
            store_not_found();
        }
        $crumbs[] = [$dept['name'], store_url('c/' . $dept['slug'])];
        if ($sub !== null) {
            $crumbs[] = [$sub['name'], null];
            $title = $sub['name'];
            $eyebrow = $dept['name'];
            $scopeIds = [(int) $sub['id']];
        } else {
            $title = $dept['name'];
            $intro = (string) ($dept['description'] ?? '');
            $scopeIds = array_map(static fn ($s) => (int) $s['id'], $dept['subs']);
        }
        $canonical = 'store/c/' . $dept['slug'] . ($sub ? '/' . $sub['slug'] : '');
        foreach ($dept['subs'] as $s) {
            $sideLinks[] = ['label' => $s['name'], 'href' => store_url('c/' . $dept['slug'] . '/' . $s['slug']), 'active' => $sub && $sub['id'] === $s['id']];
        }
        $result = store_listing($filters + ['category_ids' => $scopeIds]);
        break;

    case 'need':
        $item = null;
        foreach (store_nav() as $n) {
            if ($n['slug'] === $a && empty($n['url'])) {
                $item = $n;
            }
        }
        if ($item === null) {
            store_not_found();
        }
        $title = $item['label'];
        $storeActive = $item['slug'];
        $crumbs[] = [$item['label'], null];
        $canonical = 'store/need/' . $item['slug'];
        $scopeIds = store_expand_categories(array_map('intval', $item['category_ids']));
        // Side links: the subcategories this menu item covers.
        foreach (store_tree() as $d) {
            foreach ($d['subs'] as $s) {
                if (in_array((int) $s['id'], $scopeIds, true)) {
                    $sideLinks[] = ['label' => $s['name'], 'href' => store_url('c/' . $d['slug'] . '/' . $s['slug'])];
                }
            }
        }
        $result = store_listing($filters + ['category_ids' => $scopeIds]);
        break;

    case 'goal':
        $goal = null;
        foreach (store_goals() as $g) {
            if ($g['slug'] === $a) {
                $goal = $g;
            }
        }
        if ($goal === null) {
            store_not_found();
        }
        $eyebrow = 'Shop by health goal';
        $title = $goal['name'];
        $intro = (string) ($goal['description'] ?? '');
        $crumbs[] = [$goal['name'], null];
        $canonical = 'store/goal/' . $goal['slug'];
        foreach (store_goals() as $g) {
            $sideLinks[] = ['label' => $g['name'], 'href' => store_url('goal/' . $g['slug']), 'active' => $g['id'] === $goal['id']];
        }
        $result = store_listing($filters + ['concern_id' => (int) $goal['id']]);
        break;

    case 'brand':
        $brand = null;
        $db = ecp_db();
        if ($db) {
            $st = $db->prepare('SELECT id, slug, name FROM store_brands WHERE slug = :s AND is_active = 1 LIMIT 1');
            $st->execute(['s' => $a]);
            $brand = $st->fetch() ?: null;
        }
        if ($brand === null) {
            store_not_found();
        }
        $eyebrow = 'Brand';
        $title = $brand['name'];
        $crumbs[] = [$brand['name'], null];
        $canonical = 'store/brand/' . $brand['slug'];
        $result = store_listing($filters + ['brand_id' => (int) $brand['id']]);
        break;

    default: // search
        $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
        $eyebrow = 'Search';
        $title = $q !== '' ? 'Results for “' . $q . '”' : 'Search the store';
        $crumbs[] = ['Search', null];
        $storeQuery = $q;
        $result = $q !== ''
            ? store_listing($filters + ['q' => $q])
            : ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'brands' => [], 'sellers' => []];
        break;
}

$storeTitle = $title . ' | eClinicPro Store';
$storeDesc = $intro !== '' ? $intro : 'Shop ' . $title . ' from verified sellers on eClinicPro Store. Genuine products, delivered across India.';
$storeCanonical = $canonical !== null ? '/' . $canonical : null;
require __DIR__ . '/_header.php';
?>
<main class="st-wrap">
  <nav class="st-crumbs" aria-label="Breadcrumb">
    <?php foreach ($crumbs as $i => [$label, $href]): ?>
      <?= $i > 0 ? ' / ' : '' ?><?php if ($href): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><span><?= e($label) ?></span><?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <header class="st-list-head">
    <span class="st-eyebrow"><?= e($eyebrow) ?></span>
    <h1 class="st-h2"><?= e($title) ?></h1>
    <?php if ($intro !== ''): ?><p class="st-lede"><?= e($intro) ?></p><?php endif; ?>
    <?php if ($type === 'search'): ?>
      <form action="<?= store_url('search') ?>" method="get" class="st-search st-mobile-only" style="margin-top:14px;max-width:none">
        <input type="search" name="q" value="<?= e($storeQuery ?? '') ?>" placeholder="Search the store…" aria-label="Search the store">
      </form>
    <?php endif; ?>
  </header>
  <?php require __DIR__ . '/_listing_body.php'; ?>
</main>
<?php require __DIR__ . '/_footer.php'; ?>
