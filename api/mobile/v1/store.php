<?php
// =====================================================================
// api/mobile/v1/store.php — eClinicPro Store catalog (public; Bearer
// optional, only used to fill `wishlisted` on product cards).
//
// Thin adapter over store/_lib.php — the SAME read queries the /store
// website uses, so visibility (live product + approved seller + active,
// unblocked category), ranking, facets and page size are identical.
//
//   GET ?action=home
//   GET ?action=categories
//   GET ?action=listing  scope=category|need|goal|brand|seller|search
//                        slug=<dept/need/goal/brand/seller slug>  sub=<subcategory slug>
//                        q=<text, scope=search>
//                        filters: brand_id, seller_id, min, max (whole rupees),
//                                 in_stock=1, sort=relevance|price_asc|price_desc|newest|rating,
//                                 page (24 per page)
//   GET ?action=product  slug=… (or id=…)
//   GET ?action=reviews  product_id=…
// =====================================================================

declare(strict_types=1);

require_once __DIR__ . '/_store.php';

ecp_m_require_method('GET');
ecp_ms_boot();

$action = (string) ($_GET['action'] ?? 'home');

switch ($action) {

    // ----- homepage blocks (sections with no data come back empty) ---------
    case 'home': {
        $tiles = array_values(array_filter(store_nav(), static fn ($n) => (int) $n['homepage_tile_order'] > 0 && empty($n['url'])));
        usort($tiles, static fn ($a, $b) => (int) $a['homepage_tile_order'] <=> (int) $b['homepage_tile_order']);
        $featured = store_products_simple('AND p.is_featured = 1', [], 'p.in_stock DESC, p.sold_count DESC', 8);
        $newest = store_products_simple('', [], 'p.published_at DESC', 8);
        if (!$featured) {            // same fallback as the web homepage
            $featured = $newest;
            $newest = [];
        }
        ecp_m_ok([
            'banners' => array_values(array_filter(array_map(static fn ($b) => [
                'title' => $b['title'] ?? null,
                'image' => store_img((string) $b['image_path']),
                // Web link (e.g. /store/c/…). The app may map known /store/ paths to screens.
                'link' => $b['link'] ?? null,
            ], store_home_banners()), static fn ($b) => $b['image'] !== null)),
            // "Shop by need" tiles → open listing scope=need&slug=…
            'needs' => array_map(static fn ($n) => [
                'slug' => (string) $n['slug'],
                'label' => (string) $n['label'],
                'image' => !empty($n['image_path']) ? store_img((string) $n['image_path']) : ecp_ms_site_asset(store_tile_image((string) $n['slug'])),
            ], $tiles),
            'featured' => ecp_ms_cards($featured),
            'new_arrivals' => ecp_ms_cards($newest),
            // Health goals → listing scope=goal&slug=…
            'goals' => array_map('ecp_ms_goal', store_goals()),
            'sellers' => array_map(static fn ($s) => [
                'slug' => (string) $s['slug'],
                'name' => (string) $s['display_name'],
                'logo' => store_img($s['logo_path'] !== null ? (string) $s['logo_path'] : null),
                'product_count' => (int) $s['n'],
            ], store_home_sellers()),
            'brands' => array_map(static fn ($b) => ['slug' => (string) $b['slug'], 'name' => (string) $b['name']], store_home_brands()),
        ]);
        break;
    }

    // ----- every department + subcategory ----------------------------------
    case 'categories': {
        ecp_m_ok([
            'departments' => array_map(static fn ($d) => [
                'id' => (int) $d['id'],
                'slug' => (string) $d['slug'],
                'name' => (string) $d['name'],
                'description' => $d['description'] ?? null,
                'subcategories' => array_map(static fn ($s) => [
                    'id' => (int) $s['id'], 'slug' => (string) $s['slug'], 'name' => (string) $s['name'],
                ], $d['subs']),
            ], store_tree()),
            'needs' => array_map(static fn ($n) => ['slug' => (string) $n['slug'], 'label' => (string) $n['label']],
                array_values(array_filter(store_nav(), static fn ($n) => empty($n['url'])))),
            'goals' => array_map('ecp_ms_goal', store_goals()),
        ]);
        break;
    }

    // ----- product grid (mirrors store/listing.php + store/seller.php) -----
    case 'listing': {
        $scope = (string) ($_GET['scope'] ?? 'search');
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        $sub = strtolower(trim((string) ($_GET['sub'] ?? '')));
        $filters = [
            'brand' => (int) ($_GET['brand_id'] ?? 0),
            'seller' => (int) ($_GET['seller_id'] ?? 0),
            'min' => max(0, (int) ($_GET['min'] ?? 0)),
            'max' => max(0, (int) ($_GET['max'] ?? 0)),
            'in_stock' => !empty($_GET['in_stock']),
            'sort' => (string) ($_GET['sort'] ?? ''),
            'page' => (int) ($_GET['page'] ?? 1),
        ];
        $head = ['title' => '', 'eyebrow' => 'Shop', 'intro' => null];
        $chips = [];     // sub-navigation (subcategories / other goals)
        $seller = null;

        switch ($scope) {
            case 'category': {
                $dept = null;
                $subRow = null;
                foreach (store_tree() as $d) {
                    if ($d['slug'] === $slug) {
                        $dept = $d;
                        foreach ($d['subs'] as $s) {
                            if ($sub !== '' && $s['slug'] === $sub) {
                                $subRow = $s;
                            }
                        }
                    }
                }
                if ($dept === null || ($sub !== '' && $subRow === null)) {
                    ecp_m_err('not_found', 404);
                }
                if ($subRow !== null) {
                    $head = ['title' => (string) $subRow['name'], 'eyebrow' => (string) $dept['name'], 'intro' => null];
                    $ids = [(int) $subRow['id']];
                } else {
                    $head = ['title' => (string) $dept['name'], 'eyebrow' => 'Shop', 'intro' => $dept['description'] ?? null];
                    $ids = array_map(static fn ($s) => (int) $s['id'], $dept['subs']);
                }
                foreach ($dept['subs'] as $s) {
                    $chips[] = ['label' => (string) $s['name'], 'scope' => 'category', 'slug' => (string) $dept['slug'],
                        'sub' => (string) $s['slug'], 'active' => $subRow !== null && (int) $subRow['id'] === (int) $s['id']];
                }
                $result = store_listing($filters + ['category_ids' => $ids]);
                break;
            }
            case 'need': {
                $item = null;
                foreach (store_nav() as $n) {
                    if ($n['slug'] === $slug && empty($n['url'])) {
                        $item = $n;
                    }
                }
                if ($item === null) {
                    ecp_m_err('not_found', 404);
                }
                $head['title'] = (string) $item['label'];
                $ids = store_expand_categories(array_map('intval', $item['category_ids']));
                foreach (store_tree() as $d) {
                    foreach ($d['subs'] as $s) {
                        if (in_array((int) $s['id'], $ids, true)) {
                            $chips[] = ['label' => (string) $s['name'], 'scope' => 'category', 'slug' => (string) $d['slug'],
                                'sub' => (string) $s['slug'], 'active' => false];
                        }
                    }
                }
                $result = store_listing($filters + ['category_ids' => $ids]);
                break;
            }
            case 'goal': {
                $goal = null;
                foreach (store_goals() as $g) {
                    if ($g['slug'] === $slug) {
                        $goal = $g;
                    }
                }
                if ($goal === null) {
                    ecp_m_err('not_found', 404);
                }
                $head = ['title' => (string) $goal['name'], 'eyebrow' => 'Shop by health goal', 'intro' => $goal['description'] ?? null];
                foreach (store_goals() as $g) {
                    $chips[] = ['label' => (string) $g['name'], 'scope' => 'goal', 'slug' => (string) $g['slug'], 'sub' => null,
                        'active' => (int) $g['id'] === (int) $goal['id']];
                }
                $result = store_listing($filters + ['concern_id' => (int) $goal['id']]);
                break;
            }
            case 'brand': {
                $st = ecp_db()->prepare('SELECT id, slug, name FROM store_brands WHERE slug = :s AND is_active = 1 LIMIT 1');
                $st->execute(['s' => $slug]);
                $brand = $st->fetch() ?: null;
                if ($brand === null) {
                    ecp_m_err('not_found', 404);
                }
                $head = ['title' => (string) $brand['name'], 'eyebrow' => 'Brand', 'intro' => null];
                $result = store_listing($filters + ['brand_id' => (int) $brand['id']]);
                break;
            }
            case 'seller': {
                $s = $slug !== '' ? store_seller($slug) : null;
                if ($s === null) {
                    ecp_m_err('not_found', 404);
                }
                $head = ['title' => (string) $s['display_name'], 'eyebrow' => 'Seller', 'intro' => $s['description'] ?? null];
                $seller = [
                    'id' => (int) $s['id'],
                    'slug' => (string) $s['slug'],
                    'name' => (string) $s['display_name'],
                    'description' => $s['description'] ?? null,
                    'logo' => store_img($s['logo_path'] !== null ? (string) $s['logo_path'] : null),
                    'banner' => store_img($s['banner_path'] !== null ? (string) $s['banner_path'] : null),
                    'location' => $s['location'] ?? null,
                    'rating_avg' => (int) $s['rating_count'] > 0 ? round((float) $s['rating_avg'], 1) : null,
                    'rating_count' => (int) $s['rating_count'],
                    'handling_days' => max(1, (int) $s['handling_days']),
                    'since' => $s['approved_at'] ?? null,
                ];
                $filters['seller'] = 0;   // no seller facet on a seller's own page
                $result = store_listing($filters + ['vendor_id' => (int) $s['id']]);
                $result['sellers'] = [];
                break;
            }
            case 'search': {
                $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
                $head = ['title' => $q !== '' ? 'Results for "' . $q . '"' : 'All products', 'eyebrow' => 'Search', 'intro' => null];
                $result = store_listing($filters + ['q' => $q]);
                break;
            }
            default:
                ecp_m_err('unknown_scope', 400);
        }

        ecp_m_ok([
            'title' => $head['title'],
            'eyebrow' => $head['eyebrow'],
            'intro' => $head['intro'] !== '' ? $head['intro'] : null,
            'seller' => $seller,
            'chips' => $chips,
            'products' => ecp_ms_cards($result['rows']),
            'total' => (int) $result['total'],
            'page' => (int) $result['page'],
            'pages' => (int) $result['pages'],
            'per_page' => STORE_PER_PAGE,
            'facets' => [
                'brands' => array_map(static fn ($b) => ['id' => (int) $b['id'], 'name' => (string) $b['name'], 'count' => (int) $b['n']], $result['brands']),
                'sellers' => array_map(static fn ($s) => ['id' => (int) $s['id'], 'name' => (string) $s['name'], 'count' => (int) $s['n']], $result['sellers']),
                'sorts' => [
                    ['key' => 'relevance', 'label' => 'Recommended'],
                    ['key' => 'price_asc', 'label' => 'Price: low to high'],
                    ['key' => 'price_desc', 'label' => 'Price: high to low'],
                    ['key' => 'newest', 'label' => 'Newest'],
                    ['key' => 'rating', 'label' => 'Top rated'],
                ],
            ],
        ]);
        break;
    }

    // ----- product detail (mirrors store/product.php) ----------------------
    case 'product': {
        $slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
        $id = (int) ($_GET['id'] ?? 0);
        if ($slug === '' && $id > 0) {
            // Order lines carry product_id only; resolve it to the slug.
            $st = ecp_db()->prepare('SELECT slug FROM store_products WHERE id = :i LIMIT 1');
            $st->execute(['i' => $id]);
            $slug = (string) ($st->fetchColumn() ?: '');
        }
        $p = $slug !== '' ? store_product($slug) : null;
        if ($p === null) {
            ecp_m_err('not_found', 404);
        }
        $noPromo = !empty($p['no_promotion']);
        $returnDays = !empty($p['is_returnable']) ? (int) ($p['return_window_days'] ?? $p['default_return_window_days'] ?? 7) : 0;
        $related = store_products_simple(
            'AND p.category_id = :cat AND p.id <> :pid',
            ['cat' => (int) $p['category_id'], 'pid' => (int) $p['id']],
            'p.in_stock DESC, p.sold_count DESC, p.published_at DESC',
            8
        );
        $specs = [];
        foreach ((array) $p['specs'] as $s) {
            if (is_array($s) && isset($s['label'], $s['value'])) {
                $specs[] = ['label' => (string) $s['label'], 'value' => (string) $s['value']];
            }
        }
        if (!empty($p['manufacturer'])) {
            $specs[] = ['label' => 'Manufacturer', 'value' => (string) $p['manufacturer']];
        }
        if (!empty($p['country_of_origin'])) {
            $specs[] = ['label' => 'Country of origin', 'value' => (string) $p['country_of_origin']];
        }
        if (!empty($p['license_number'])) {
            $specs[] = ['label' => 'Licence no.', 'value' => (string) $p['license_number']];
        }
        $handling = max(1, (int) $p['handling_days']);

        ecp_m_ok(['product' => [
            'id' => (int) $p['id'],
            'slug' => (string) $p['slug'],
            'name' => (string) $p['name'],
            'short_desc' => $p['short_desc'] ?: null,
            'description' => $p['description'] ?: null,   // plain text; keep line breaks
            'brand' => !empty($p['brand_name']) ? ['name' => (string) $p['brand_name'], 'slug' => (string) $p['brand_slug']] : null,
            'category' => ['name' => (string) $p['category_name'], 'slug' => (string) $p['category_slug'],
                'dept' => $p['dept'] ? ['name' => (string) $p['dept']['name'], 'slug' => (string) $p['dept']['slug']] : null],
            'images' => array_values(array_filter(array_map(static fn ($i) => store_img((string) $i['path']), $p['images']))),
            'variants' => array_map(static function ($v) use ($noPromo) {
                $avail = max(0, (int) $v['stock_qty'] - (int) $v['reserved_qty']);
                $price = (int) $v['price_paise'];
                $mrp = (int) $v['mrp_paise'];

                return [
                    'id' => (int) $v['id'],                 // send this to store_cart.php?action=add
                    'title' => $v['title'] !== null && $v['title'] !== '' ? (string) $v['title'] : null,
                    'price_paise' => $price,
                    'mrp_paise' => $mrp > $price ? $mrp : null,
                    'off_pct' => $noPromo ? 0 : store_off_pct($mrp, $price),
                    'in_stock' => $avail > 0,
                    'low_stock' => $avail > 0 && $avail <= 5,   // "only a few left"
                ];
            }, $p['variants']),
            'no_promotion' => $noPromo,
            'rating_avg' => (int) $p['rating_count'] > 0 ? round((float) $p['rating_avg'], 1) : null,
            'rating_count' => (int) $p['rating_count'],
            'wishlisted' => isset(store_wishlist_ids()[(int) $p['id']]),
            'seller' => [
                'name' => (string) $p['vendor_name'],
                'slug' => (string) $p['vendor_slug'],
                'logo' => store_img($p['vendor_logo'] !== null ? (string) $p['vendor_logo'] : null),
                'rating_avg' => (int) $p['vendor_rating_count'] > 0 ? round((float) $p['vendor_rating'], 1) : null,
                'ships_from' => $p['ships_from'] ? $p['ships_from']['city'] . ', ' . $p['ships_from']['state'] : null,
            ],
            'specs' => $specs,
            'returnable' => $returnDays > 0,
            'return_window_days' => $returnDays,
            'facts' => [
                'Usually dispatched within ' . $handling . ' day' . ($handling > 1 ? 's' : '') . '. Delivery across India.',
                $returnDays > 0 ? $returnDays . '-day returns if the product is damaged, wrong or defective.' : 'This product is not returnable.',
                'Genuine product from a verified seller' . (!empty($p['license_number']) ? '. Licence no. ' . $p['license_number'] : '') . '.',
            ],
            'tax_note' => 'Inclusive of all taxes',
            'web_url' => ecp_site_url('/store/p/' . $p['slug']),   // for the share sheet
        ],
            'reviews' => array_map('ecp_ms_review', store_reviews((int) $p['id'], 5)),
            'related' => ecp_ms_cards($related),
        ]);
        break;
    }

    // ----- published reviews for a product ---------------------------------
    case 'reviews': {
        $pid = (int) ($_GET['product_id'] ?? 0);
        if ($pid <= 0) {
            ecp_m_err('product_required', 400);
        }
        ecp_m_ok(['reviews' => array_map('ecp_ms_review', store_reviews($pid, 50))]);
        break;
    }

    default:
        ecp_m_err('unknown_action', 400);
}

// ---------------------------------------------------------------------

function ecp_ms_goal(array $g): array
{
    return [
        'id' => (int) $g['id'],
        'slug' => (string) $g['slug'],
        'name' => (string) $g['name'],
        'description' => $g['description'] ?? null,
        'image' => !empty($g['image_path']) ? store_img((string) $g['image_path']) : null,
    ];
}

function ecp_ms_review(array $r): array
{
    return [
        'rating' => (int) $r['rating'],
        'title' => $r['title'] ?? null,
        'body' => $r['body'] ?? null,
        'author' => (string) $r['display_name'],   // "First L." — never the full name
        'seller_reply' => $r['vendor_reply'] ?? null,
        'created_at' => (string) $r['created_at'],
    ];
}
