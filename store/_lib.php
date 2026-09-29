<?php
// =====================================================================
// store/_lib.php — eClinicPro Store (customer storefront) core.
//
//   - Visibility gate: store_enabled = 1, or a valid admin preview cookie.
//     Otherwise every /store page renders the site 404 (the store "doesn't
//     exist" until you press Go live in /admin/store/settings).
//   - Read-only catalog queries for the storefront (PDO via ecp_db()).
//
// Customers are patients: login = the shared ecp_pid patient session.
//
// Visible product = live + not deleted + seller approved + primary
// category active and not blocked. Every listing query uses store_visible_sql().
//
// PDO runs with EMULATE_PREPARES=false: never reuse a named placeholder.
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/../partials/helpers.php';
require_once __DIR__ . '/../partials/patient_auth.php';

const STORE_PER_PAGE = 24;
const STORE_PREVIEW_COOKIE = 'ecp_store_preview';

// ---------------------------------------------------------------------
// Settings + visibility gate
// ---------------------------------------------------------------------

function store_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $db = ecp_db();
        if ($db) {
            try {
                $rows = $db->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'store\\_%'")->fetchAll();
                foreach ($rows as $r) {
                    $cache[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
                }
            } catch (Throwable $e) {
                error_log('[store_setting] ' . $e->getMessage());
            }
        }
    }

    return $cache[$key] ?? $default;
}

function store_is_live(): bool
{
    return store_setting('store_enabled', '0') === '1';
}

/** True when the store is live, or this browser holds a valid preview cookie. */
function store_can_view(): bool
{
    if (store_is_live()) {
        return true;
    }
    $key = store_setting('store_preview_key');
    if ($key === '') {
        return false;
    }
    $given = (string) ($_GET['preview'] ?? '');
    if ($given !== '' && hash_equals($key, $given)) {
        $secure = ($_SERVER['HTTPS'] ?? '') === 'on' || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie(STORE_PREVIEW_COOKIE, $key, [
            // Path "/" so /api/store_* endpoints see it too.
            'expires' => time() + 30 * 86400, 'path' => '/', 'secure' => $secure,
            'httponly' => true, 'samesite' => 'Lax',
        ]);

        return true;
    }
    $cookie = (string) ($_COOKIE[STORE_PREVIEW_COOKIE] ?? '');

    return $cookie !== '' && hash_equals($key, $cookie);
}

/** Call at the top of every storefront page. Renders the site 404 and exits when hidden. */
function store_gate(): void
{
    if (!ecp_db() || !store_can_view()) {
        require __DIR__ . '/../404.php';
        exit;
    }
    if (!store_is_live()) {
        header('X-Robots-Tag: noindex, nofollow');   // preview mode: never index
    }
}

function store_not_found(): never
{
    require __DIR__ . '/../404.php';
    exit;
}

// ---------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------

function store_rupees(int $paise): string
{
    return '₹' . ($paise % 100 === 0 ? number_format($paise / 100) : number_format($paise / 100, 2));
}

function store_off_pct(int $mrp, int $price): int
{
    return ($mrp > 0 && $price > 0 && $price < $mrp) ? (int) round((1 - $price / $mrp) * 100) : 0;
}

/** Uploaded store images live on the portal domain (app.eclinicpro.com/uploads/store/…). */
function store_img(?string $path): ?string
{
    $path = (string) $path;
    if ($path === '') {
        return null;
    }

    return str_starts_with($path, 'http') ? $path : ecp_portal_url($path);
}

function store_url(string $path = ''): string
{
    return '/store' . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

/** Keep current query params, override some, drop empties. */
function store_qs(array $override = []): string
{
    $q = array_merge($_GET, $override);
    unset($q['preview']);
    $q = array_filter($q, static fn ($v) => $v !== '' && $v !== null && $v !== []);

    return $q ? '?' . http_build_query($q) : '';
}

// ---------------------------------------------------------------------
// Taxonomy
// ---------------------------------------------------------------------

/** @return list<array<string,mixed>> departments, each with 'subs' */
function store_tree(): array
{
    static $tree = null;
    if ($tree !== null) {
        return $tree;
    }
    $tree = [];
    $db = ecp_db();
    if (!$db) {
        return $tree;
    }
    try {
        $rows = $db->query(
            "SELECT id, parent_id, slug, name, description, group_key, sort_order
               FROM store_categories
              WHERE is_active = 1 AND (parent_id = 0 OR listing_mode <> 'blocked')
              ORDER BY parent_id, sort_order, name"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[store_tree] ' . $e->getMessage());

        return $tree;
    }
    $depts = [];
    foreach ($rows as $r) {
        if ((int) $r['parent_id'] === 0) {
            $depts[(int) $r['id']] = $r + ['subs' => []];
        }
    }
    foreach ($rows as $r) {
        if ((int) $r['parent_id'] !== 0 && isset($depts[(int) $r['parent_id']])) {
            $depts[(int) $r['parent_id']]['subs'][] = $r;
        }
    }

    return $tree = array_values($depts);
}

/** @return list<array<string,mixed>> "Shop by need" menu items (with target category ids) */
function store_nav(): array
{
    static $nav = null;
    if ($nav !== null) {
        return $nav;
    }
    $nav = [];
    $db = ecp_db();
    if (!$db) {
        return $nav;
    }
    try {
        $items = $db->query('SELECT * FROM store_nav_items WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
        $targets = [];
        foreach ($db->query('SELECT nav_item_id, category_id FROM store_nav_item_targets')->fetchAll() as $t) {
            $targets[(int) $t['nav_item_id']][] = (int) $t['category_id'];
        }
        foreach ($items as $it) {
            $it['category_ids'] = $targets[(int) $it['id']] ?? [];
            $nav[] = $it;
        }
    } catch (Throwable $e) {
        error_log('[store_nav] ' . $e->getMessage());
    }

    return $nav;
}

/** @return list<array<string,mixed>> health goals (with target category ids) */
function store_goals(): array
{
    static $goals = null;
    if ($goals !== null) {
        return $goals;
    }
    $goals = [];
    $db = ecp_db();
    if (!$db) {
        return $goals;
    }
    try {
        $rows = $db->query('SELECT * FROM store_concerns WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
        $targets = [];
        foreach ($db->query('SELECT concern_id, category_id FROM store_concern_categories')->fetchAll() as $t) {
            $targets[(int) $t['concern_id']][] = (int) $t['category_id'];
        }
        foreach ($rows as $r) {
            $r['category_ids'] = $targets[(int) $r['id']] ?? [];
            $goals[] = $r;
        }
    } catch (Throwable $e) {
        error_log('[store_goals] ' . $e->getMessage());
    }

    return $goals;
}

/**
 * Expand category ids so a department id also covers all its subcategories.
 *
 * @param list<int> $ids
 * @return list<int>
 */
function store_expand_categories(array $ids): array
{
    $out = [];
    foreach (store_tree() as $d) {
        $deptSelected = in_array((int) $d['id'], $ids, true);
        foreach ($d['subs'] as $s) {
            if ($deptSelected || in_array((int) $s['id'], $ids, true)) {
                $out[(int) $s['id']] = true;
            }
        }
    }

    return array_keys($out);
}

// ---------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------

/** SQL fragment: FROM + visibility WHERE. Aliases: p product, v vendor, pc primary category, b brand. */
function store_visible_sql(): string
{
    return "FROM store_products p
            JOIN store_vendors v ON v.id = p.vendor_id AND v.status = 'approved'
            JOIN store_categories pc ON pc.id = p.category_id AND pc.is_active = 1 AND pc.listing_mode <> 'blocked'
            LEFT JOIN store_brands b ON b.id = p.brand_id
           WHERE p.status = 'live' AND p.deleted_at IS NULL";
}

/** Columns a product card needs. */
function store_card_cols(): string
{
    return "p.id, p.slug, p.name, p.min_price_paise, p.in_stock, p.rating_avg, p.rating_count, p.is_featured,
            p.category_id, pc.no_promotion,
            b.name AS brand_name, v.display_name AS vendor_name, v.slug AS vendor_slug,
            (SELECT i.path FROM store_product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover,
            (SELECT sv.mrp_paise FROM store_product_variants sv WHERE sv.product_id = p.id AND sv.is_active = 1
              ORDER BY sv.price_paise LIMIT 1) AS mrp_paise,
            (SELECT COUNT(*) FROM store_product_variants sv2 WHERE sv2.product_id = p.id AND sv2.is_active = 1) AS variant_count";
}

/**
 * Filtered, sorted, paginated product listing.
 *
 * @param array{category_ids?: list<int>, vendor_id?: int, brand_id?: int, concern_id?: int, q?: string,
 *              brand?: int, seller?: int, min?: int, max?: int, in_stock?: bool, sort?: string, page?: int} $f
 * @return array{rows: list<array<string,mixed>>, total: int, page: int, pages: int, brands: list<array<string,mixed>>, sellers: list<array<string,mixed>>}
 */
function store_listing(array $f): array
{
    $empty = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'brands' => [], 'sellers' => []];
    $db = ecp_db();
    if (!$db) {
        return $empty;
    }

    // Scope = what the page is about (category/goal/brand/seller/search).
    $scope = [];
    $params = [];
    if (isset($f['category_ids'])) {
        $ids = array_values(array_filter(array_map('intval', $f['category_ids'])));
        if (!$ids) {
            return $empty;
        }
        $scope[] = 'EXISTS (SELECT 1 FROM store_product_categories spc WHERE spc.product_id = p.id AND spc.category_id IN (' . implode(',', $ids) . '))';
    }
    if (!empty($f['concern_id'])) {
        // Tagged with the goal directly, OR placed in one of the goal's categories.
        $goalCats = [];
        foreach (store_goals() as $g) {
            if ((int) $g['id'] === (int) $f['concern_id']) {
                $goalCats = array_map('intval', $g['category_ids']);
            }
        }
        $catSql = $goalCats ? ' OR EXISTS (SELECT 1 FROM store_product_categories spc2 WHERE spc2.product_id = p.id AND spc2.category_id IN (' . implode(',', $goalCats) . '))' : '';
        $scope[] = '(EXISTS (SELECT 1 FROM store_product_concerns spco WHERE spco.product_id = p.id AND spco.concern_id = :concern)' . $catSql . ')';
        $params['concern'] = (int) $f['concern_id'];
    }
    if (!empty($f['vendor_id'])) {
        $scope[] = 'p.vendor_id = :scope_vendor';
        $params['scope_vendor'] = (int) $f['vendor_id'];
    }
    if (!empty($f['brand_id'])) {
        $scope[] = 'p.brand_id = :scope_brand';
        $params['scope_brand'] = (int) $f['brand_id'];
    }
    $q = trim((string) ($f['q'] ?? ''));
    $hasFulltext = false;
    if ($q !== '') {
        $like = '%' . $q . '%';
        $scope[] = '(MATCH(p.name, p.short_desc) AGAINST (:ft1 IN NATURAL LANGUAGE MODE) OR p.name LIKE :lk1 OR b.name LIKE :lk2)';
        $params['ft1'] = $q;
        $params['lk1'] = $like;
        $params['lk2'] = $like;
        $hasFulltext = true;
    }

    $base = store_visible_sql() . ($scope ? ' AND ' . implode(' AND ', $scope) : '');

    // Facets are computed on the SCOPE (before the user's brand/seller/price filters).
    $brands = $sellers = [];
    try {
        $st = $db->prepare("SELECT b.id, b.name, COUNT(*) n $base AND b.id IS NOT NULL GROUP BY b.id, b.name ORDER BY n DESC, b.name LIMIT 30");
        $st->execute($params);
        $brands = $st->fetchAll();
        $st = $db->prepare("SELECT v.id, v.display_name AS name, COUNT(*) n $base GROUP BY v.id, v.display_name ORDER BY n DESC LIMIT 30");
        $st->execute($params);
        $sellers = $st->fetchAll();
    } catch (Throwable $e) {
        error_log('[store_listing facets] ' . $e->getMessage());
    }

    // User filters.
    $filters = [];
    if (!empty($f['brand'])) {
        $filters[] = 'p.brand_id = :f_brand';
        $params['f_brand'] = (int) $f['brand'];
    }
    if (!empty($f['seller'])) {
        $filters[] = 'p.vendor_id = :f_seller';
        $params['f_seller'] = (int) $f['seller'];
    }
    if (!empty($f['min'])) {
        $filters[] = 'p.min_price_paise >= :f_min';
        $params['f_min'] = (int) $f['min'] * 100;
    }
    if (!empty($f['max'])) {
        $filters[] = 'p.min_price_paise <= :f_max';
        $params['f_max'] = (int) $f['max'] * 100;
    }
    if (!empty($f['in_stock'])) {
        $filters[] = 'p.in_stock = 1';
    }
    $where = $base . ($filters ? ' AND ' . implode(' AND ', $filters) : '');

    $sort = (string) ($f['sort'] ?? '');
    $order = match ($sort) {
        'price_asc' => 'p.min_price_paise ASC',
        'price_desc' => 'p.min_price_paise DESC',
        'newest' => 'p.published_at DESC',
        'rating' => 'p.rating_avg DESC, p.rating_count DESC',
        default => 'p.in_stock DESC, p.is_featured DESC, p.sold_count DESC, p.published_at DESC',
    };
    $relevance = '';
    if ($hasFulltext && ($sort === '' || $sort === 'relevance')) {
        $relevance = 'MATCH(p.name, p.short_desc) AGAINST (:ft2 IN NATURAL LANGUAGE MODE) DESC, ';
        $params['ft2'] = $q;
    }

    $page = max(1, (int) ($f['page'] ?? 1));
    $rows = [];
    $total = 0;
    try {
        $countParams = $params;
        unset($countParams['ft2']);
        $st = $db->prepare("SELECT COUNT(*) $where");
        $st->execute($countParams);
        $total = (int) $st->fetchColumn();
        $pages = max(1, (int) ceil($total / STORE_PER_PAGE));
        $page = min($page, $pages);
        $offset = ($page - 1) * STORE_PER_PAGE;
        $st = $db->prepare('SELECT ' . store_card_cols() . " $where ORDER BY $relevance$order LIMIT " . STORE_PER_PAGE . " OFFSET $offset");
        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        error_log('[store_listing] ' . $e->getMessage());
    }

    return [
        'rows' => $rows, 'total' => $total, 'page' => $page,
        'pages' => max(1, (int) ceil($total / STORE_PER_PAGE)),
        'brands' => $brands, 'sellers' => $sellers,
    ];
}

/** @return list<array<string,mixed>> */
function store_products_simple(string $extraWhere, array $params, string $order, int $limit): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT ' . store_card_cols() . ' ' . store_visible_sql() . ' ' . $extraWhere . " ORDER BY $order LIMIT " . max(1, min(48, $limit)));
        $st->execute($params);

        return $st->fetchAll();
    } catch (Throwable $e) {
        error_log('[store_products_simple] ' . $e->getMessage());

        return [];
    }
}

/** Full product for the detail page, or null if not visible. */
function store_product(string $slug): ?array
{
    $db = ecp_db();
    if (!$db) {
        return null;
    }
    try {
        $st = $db->prepare(
            "SELECT p.*, pc.name AS category_name, pc.slug AS category_slug, pc.parent_id AS dept_id, pc.no_promotion,
                    b.name AS brand_name, b.slug AS brand_slug,
                    v.display_name AS vendor_name, v.slug AS vendor_slug, v.logo_path AS vendor_logo,
                    v.rating_avg AS vendor_rating, v.rating_count AS vendor_rating_count,
                    v.handling_days, v.default_return_window_days
             " . store_visible_sql() . ' AND p.slug = :slug LIMIT 1'
        );
        $st->execute(['slug' => $slug]);
        $p = $st->fetch();
        if (!$p) {
            return null;
        }
        $pid = (int) $p['id'];
        $st = $db->prepare('SELECT id, title, sku, mrp_paise, price_paise, stock_qty, reserved_qty FROM store_product_variants WHERE product_id = :p AND is_active = 1 ORDER BY price_paise');
        $st->execute(['p' => $pid]);
        $p['variants'] = $st->fetchAll();
        $st = $db->prepare('SELECT path, alt FROM store_product_images WHERE product_id = :p ORDER BY sort_order, id');
        $st->execute(['p' => $pid]);
        $p['images'] = $st->fetchAll();
        $st = $db->prepare('SELECT slug, name FROM store_categories WHERE id = :d');
        $st->execute(['d' => (int) $p['dept_id']]);
        $p['dept'] = $st->fetch() ?: null;
        $st = $db->prepare(
            "SELECT city, state FROM store_vendor_addresses
              WHERE vendor_id = :v AND type = 'pickup' AND is_active = 1 ORDER BY is_default DESC, id DESC LIMIT 1"
        );
        $st->execute(['v' => (int) $p['vendor_id']]);
        $p['ships_from'] = $st->fetch() ?: null;
        $p['specs'] = json_decode((string) ($p['specs_json'] ?? ''), true) ?: [];

        return $p;
    } catch (Throwable $e) {
        error_log('[store_product] ' . $e->getMessage());

        return null;
    }
}

/** Approved seller by slug, with location and live product count. */
function store_seller(string $slug): ?array
{
    $db = ecp_db();
    if (!$db) {
        return null;
    }
    try {
        $st = $db->prepare(
            "SELECT v.id, v.slug, v.display_name, v.description, v.logo_path, v.banner_path, v.rating_avg, v.rating_count,
                    v.handling_days, v.default_return_window_days, v.approved_at,
                    (SELECT CONCAT(a.city, ', ', a.state) FROM store_vendor_addresses a
                      WHERE a.vendor_id = v.id AND a.type = 'pickup' AND a.is_active = 1 ORDER BY a.is_default DESC, a.id DESC LIMIT 1) AS location
               FROM store_vendors v WHERE v.slug = :s AND v.status = 'approved' LIMIT 1"
        );
        $st->execute(['s' => $slug]);

        return $st->fetch() ?: null;
    } catch (Throwable $e) {
        error_log('[store_seller] ' . $e->getMessage());

        return null;
    }
}

/** Published reviews for a product, with the reviewer's first name + initial only. @return list<array<string,mixed>> */
function store_reviews(int $productId, int $limit = 20): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        $st = $db->prepare(
            "SELECT r.rating, r.title, r.body, r.vendor_reply, r.created_at, pi.name
               FROM store_reviews r LEFT JOIN patient_identities pi ON pi.id = r.identity_id
              WHERE r.product_id = :p AND r.status = 'published' ORDER BY r.id DESC LIMIT " . max(1, min(50, $limit))
        );
        $st->execute(['p' => $productId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $parts = preg_split('/\s+/', trim((string) ($r['name'] ?? ''))) ?: [];
            $r['display_name'] = ($parts[0] ?? '') !== '' ? $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '') : 'Verified buyer';
            unset($r['name']);
        }

        return $rows;
    } catch (Throwable) {
        return [];
    }
}

// ---------------------------------------------------------------------
// Wishlist (customer = patient identity)
// ---------------------------------------------------------------------

/** @return array<int,true> product ids in the logged-in customer's wishlist */
function store_wishlist_ids(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $ids = [];
    $me = ecp_patient_current();
    $db = ecp_db();
    if (!$me || !$db) {
        return $ids;
    }
    try {
        $st = $db->prepare('SELECT product_id FROM store_wishlist WHERE identity_id = :i');
        $st->execute(['i' => (int) $me['id']]);
        foreach ($st->fetchAll() as $r) {
            $ids[(int) $r['product_id']] = true;
        }
    } catch (Throwable $e) {
        error_log('[store_wishlist_ids] ' . $e->getMessage());
    }

    return $ids;
}

/**
 * Add/remove a product from a customer's wishlist (web api/store_wishlist.php + mobile).
 *
 * @return array{ok: bool, error?: string, saved?: bool, count?: int}
 */
function store_wishlist_toggle(int $identityId, int $productId): array
{
    $db = ecp_db();
    if (!$db) {
        return ['ok' => false, 'error' => 'db_unavailable'];
    }
    if ($productId <= 0) {
        return ['ok' => false, 'error' => 'product_required'];
    }
    $st = $db->prepare("SELECT id FROM store_products WHERE id = :p AND status = 'live' AND deleted_at IS NULL");
    $st->execute(['p' => $productId]);
    $exists = $st->fetchColumn() !== false;

    $st = $db->prepare('SELECT 1 FROM store_wishlist WHERE identity_id = :i AND product_id = :p');
    $st->execute(['i' => $identityId, 'p' => $productId]);
    $saved = $st->fetchColumn() !== false;

    if ($saved) {
        $db->prepare('DELETE FROM store_wishlist WHERE identity_id = :i AND product_id = :p')
           ->execute(['i' => $identityId, 'p' => $productId]);
        $saved = false;
    } elseif ($exists) {
        $db->prepare('INSERT IGNORE INTO store_wishlist (identity_id, product_id) VALUES (:i, :p)')
           ->execute(['i' => $identityId, 'p' => $productId]);
        $saved = true;
    } else {
        return ['ok' => false, 'error' => 'product_not_found'];
    }

    $st = $db->prepare('SELECT COUNT(*) FROM store_wishlist WHERE identity_id = :i');
    $st->execute(['i' => $identityId]);

    return ['ok' => true, 'saved' => $saved, 'count' => (int) $st->fetchColumn()];
}

// ---------------------------------------------------------------------
// Homepage blocks (web store/index.php + mobile home)
// ---------------------------------------------------------------------

/** @return list<array<string,mixed>> active "home_strip" banners (≤3) */
function store_home_banners(): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        return $db->query(
            "SELECT title, image_path, link FROM store_banners
              WHERE is_active = 1 AND placement = 'home_strip'
                AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())
              ORDER BY sort_order, id DESC LIMIT 3"
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

/** @return list<array<string,mixed>> approved sellers with live products, featured first */
function store_home_sellers(): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        $sellers = $db->query(
            "SELECT v.slug, v.display_name, v.logo_path,
                    (SELECT COUNT(*) FROM store_products p WHERE p.vendor_id = v.id AND p.status = 'live' AND p.deleted_at IS NULL) AS n
               FROM store_vendors v
              WHERE v.status = 'approved'
              ORDER BY v.is_featured DESC, n DESC LIMIT 6"
        )->fetchAll();

        return array_values(array_filter($sellers, static fn ($s) => (int) $s['n'] > 0));
    } catch (Throwable $e) {
        error_log('[store_home_sellers] ' . $e->getMessage());

        return [];
    }
}

/** @return list<array<string,mixed>> active brands with live products, featured first */
function store_home_brands(): array
{
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        return $db->query(
            "SELECT b.slug, b.name FROM store_brands b
              WHERE b.is_active = 1 AND EXISTS (SELECT 1 FROM store_products p WHERE p.brand_id = b.id AND p.status = 'live' AND p.deleted_at IS NULL)
              ORDER BY b.is_featured DESC, b.name LIMIT 16"
        )->fetchAll();
    } catch (Throwable $e) {
        error_log('[store_home_brands] ' . $e->getMessage());

        return [];
    }
}

/**
 * Items in the visitor's cart, for the header badge. One cheap query; doesn't
 * boot the portal classes (full cart logic lives in App\Services\Store\CartService).
 */
function store_cart_count(): int
{
    $db = ecp_db();
    if (!$db) {
        return 0;
    }
    $me = ecp_patient_current();
    $token = (string) ($_COOKIE['ecp_cart'] ?? '');
    try {
        if ($me) {
            $st = $db->prepare(
                'SELECT COALESCE(SUM(ci.qty), 0) FROM store_cart_items ci
                   JOIN store_carts c ON c.id = ci.cart_id
                  WHERE c.identity_id = :i OR (c.identity_id IS NULL AND c.token = :t)'
            );
            $st->execute(['i' => (int) $me['id'], 't' => $token]);
        } elseif (preg_match('/^[a-f0-9]{64}$/', $token)) {
            $st = $db->prepare(
                'SELECT COALESCE(SUM(ci.qty), 0) FROM store_cart_items ci
                   JOIN store_carts c ON c.id = ci.cart_id WHERE c.token = :t AND c.identity_id IS NULL'
            );
            $st->execute(['t' => $token]);
        } else {
            return 0;
        }

        return (int) $st->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

// ---------------------------------------------------------------------
// Homepage imagery for the "Shop by need" tiles (mockup photos)
// ---------------------------------------------------------------------

function store_tile_image(string $navSlug): string
{
    $map = [
        'nutrition-supplements' => 'cat_supp.jpg',
        'hair-skin' => 'j3.jpg',
        'baby-kids' => 'cat_baby.jpg',
        'home-health-devices' => 'cat_med.jpg',
        'personal-hygiene' => 'cat_pc.jpg',
        'senior-care' => 'pc_life.jpg',
        'pregnancy-mother-care' => 'mom_baby.jpg',
        'fitness-sports' => 'protein_hero.jpg',
        'womens-health' => 'skin_woman.jpg',
    ];

    return '/assets/img/shop/' . ($map[$navSlug] ?? 'cat_supp.jpg');
}
