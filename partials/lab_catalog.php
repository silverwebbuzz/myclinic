<?php
/**
 * Shared lab catalog + detail-page content builder for lab.php / lab-detail.php.
 * Images: free Unsplash License — https://unsplash.com/license
 */

declare(strict_types=1);

if (!function_exists('lab_photo')) {
    function lab_photo(string $photoId, int $w = 800, ?int $h = null): string
    {
        $url = 'https://images.unsplash.com/' . $photoId . '?auto=format&fit=crop&w=' . $w . '&q=80';
        if ($h !== null) {
            $url .= '&h=' . $h;
        }
        return $url;
    }
}

function ecp_lab_photos(): array
{
    static $photos = null;
    if ($photos !== null) {
        return $photos;
    }
    $photos = [
        'hero-lab' => 'photo-1582719471384-894fbb16e074',
        'hero-family' => 'photo-1511895426328-dc8714191300',
        'pkg-full-body-advanced' => 'photo-1700832082200-af7deeb63d9b',
        'pkg-diabetes-care' => 'photo-1683727186226-910f31a9da45',
        'pkg-thyroid-total' => 'photo-1631217868264-e5b90bb7e133',
        'pkg-heart-health' => 'photo-1505751172876-fa1923c5c528',
        'pkg-vitamin-deficiency' => 'photo-1550572017-edd951b55104',
        'pkg-womens-wellness' => 'photo-1559839734-2b71ea197ec2',
        'pkg-senior-citizen' => 'photo-1582750433449-648ed127bb54',
        'pkg-fever-infection' => 'photo-1631549916768-4119b2e5f926',
        'organs-family' => 'photo-1476703993599-0035a21b17a9',
        'organ-heart' => 'photo-1618939304347-e91b1f33d2ab',
        'organ-lungs' => 'photo-1530497610245-94d3c16cda28',
        'organ-liver' => 'photo-1532187863486-abf9dbad1b69',
        'organ-kidney' => 'photo-1579154204601-01588f351e67',
        'organ-thyroid' => 'photo-1631217868264-e5b90bb7e133',
        'organ-diabetes' => 'photo-1683727186226-910f31a9da45',
        'organ-bone' => 'photo-1559757175-5700dde675bc',
        'organ-brain' => 'photo-1559757148-5c350d0d3c56',
        'organ-full-body' => 'photo-1700832082200-af7deeb63d9b',
        'organ-vitamins' => 'photo-1550572017-edd951b55104',
        'organ-immunity' => 'photo-1544367567-0f2fcb009e0b',
        'organ-pregnancy' => 'photo-1584515933487-779824d29309',
        'symptom-fever' => 'photo-1584820927498-cfe5211fd8bf',
        'symptom-cough' => 'photo-1666214280557-f1b5022eb634',
        'symptom-body-pain' => 'photo-1559757175-0eb30cd8c063',
        'symptom-stomach' => 'photo-1516574187841-cb9cc2ca948b',
        'symptom-skin' => 'photo-1571019613454-1cb2f99b2d8b',
        'symptom-fatigue' => 'photo-1544005313-94ddf0286df2',
        'symptom-weight' => 'photo-1571019614242-c5c5dee9f50b',
        'symptom-heart-health' => 'photo-1505751172876-fa1923c5c528',
        'life-child' => 'photo-1503454537195-1dcabb73ffb9',
        'life-mens' => 'photo-1612349317150-e413f6a5b16d',
        'life-womens' => 'photo-1438761681033-6461ffad8d80',
        'life-pregnancy' => 'photo-1584515933487-779824d29309',
        'life-senior' => 'photo-1582750433449-648ed127bb54',
        'life-fitness' => 'photo-1571019614242-c5c5dee9f50b',
        'step-choose' => 'photo-1576091160399-112ba8d25d1d',
        'step-home' => 'photo-1511895426328-dc8714191300',
        'step-lab' => 'photo-1532187863486-abf9dbad1b69',
        'step-reports' => 'photo-1576091160399-112ba8d25d1d',
        'why-nabl' => 'photo-1582719478250-c89cae4dc85b',
        'why-home' => 'photo-1600880292203-757bb62b4baf',
        'why-reports-app' => 'photo-1576091160399-112ba8d25d1d',
        'why-fast' => 'photo-1638202993928-7267aad84c31',
        'why-doctor' => 'photo-1612349317150-e413f6a5b16d',
        'why-savings' => 'photo-1587854692152-cbe660dbde88',
        'why-hygiene' => 'photo-1582719471384-894fbb16e074',
        'why-india' => 'photo-1516574187841-cb9cc2ca948b',
        'partner-nabl' => 'photo-1582719478250-c89cae4dc85b',
        'partner-pathology' => 'photo-1579165466741-7f35e4755660',
        'partner-partners' => 'photo-1700832082200-af7deeb63d9b',
        'partner-centers' => 'photo-1511174511562-5f7f18b874f8',
        // Extra gallery pool
        'gal-1' => 'photo-1700832082200-af7deeb63d9b',
        'gal-2' => 'photo-1582719471384-894fbb16e074',
        'gal-3' => 'photo-1532187863486-abf9dbad1b69',
        'gal-4' => 'photo-1579154204601-01588f351e67',
        'gal-5' => 'photo-1559839734-2b71ea197ec2',
        'gal-6' => 'photo-1612349317150-e413f6a5b16d',
        'gal-7' => 'photo-1511895426328-dc8714191300',
        'gal-8' => 'photo-1666214280557-f1b5022eb634',
    ];
    return $photos;
}

function ecp_lab_photo_key(string $type, string $slug): string
{
    $map = [
        'package' => 'pkg-',
        'organ' => 'organ-',
        'symptom' => 'symptom-',
        'life' => 'life-',
        'step' => 'step-',
        'why' => 'why-',
        'partner' => 'partner-',
    ];
    return ($map[$type] ?? '') . $slug;
}

function ecp_lab_detail_url(string $type, string $slug): string
{
    return '/lab/' . rawurlencode($type) . '/' . rawurlencode($slug);
}

/** Test count at/above which a package is treated as a "Full Body" checkup. */
const ECP_LAB_FULL_BODY_MIN_TESTS = 40;

/**
 * Maps a curated storefront filter tab -> the lab_categories slugs that feed it.
 * A package appears under a tab when any of its linked categories matches.
 * 'popular' is derived from is_featured/booked_count, and 'full-body' from the
 * test count (see ECP_LAB_FULL_BODY_MIN_TESTS) — neither is a real category.
 */
function ecp_lab_tab_category_map(): array
{
    return [
        // 'full-body' handled by test_count, not categories (see ecp_lab_db_packages)
        'diabetes' => ['diabetes', 'metabolic', 'diabetes-renal', 'diabetes-cardiac'],
        'thyroid'  => ['thyroid', 'autoimmune-thyroid'],
        'vitamins' => ['vitamin', 'elements', 'anaemia'],
        'women'    => ['pregnancy', 'infertility', 'autoimmune-pregnancy', 'anaemia-pregnancy'],
        'heart'    => ['cardiac', 'heart-health', 'cardiac-risk-markers', 'arthritis-cardiac'],
        'fever'    => ['infection', 'infectious-disease', 'typhoid', 'malaria', 'covid', 'hepatitis'],
        'senior'   => ['arthritis', 'renal', 'cardiac', 'arthritis-renal'],
    ];
}

/**
 * The storefront filter tabs, in display order. Keys are the data-cats values
 * the JS filter matches; values are the button labels. Tuned for the Indian
 * market: Full Body first (most-searched), then the high-demand conditions.
 */
function ecp_lab_package_filters(): array
{
    return [
        'all'       => 'All Packages',
        'popular'   => 'Most Popular',
        'full-body' => 'Full Body',
        'diabetes'  => 'Diabetes',
        'thyroid'   => 'Thyroid',
        'vitamins'  => 'Vitamins',
        'women'     => 'Women',
        'heart'     => 'Heart',
        'fever'     => 'Fever & Infection',
        'senior'    => 'Senior Citizen',
    ];
}

/**
 * DB-backed packages for the /lab grid, in the SAME positional tuple shape as
 * the static 'packages' array so lab.php's render loop is unchanged:
 *   [slug, name, highlights[], tests[], paramCount, price, mrp, off%, cats[], badge]
 *
 * Selection: featured first, then most-booked packages/offers. Each package's
 * `cats` are the storefront filter tabs it belongs to (mapped from its
 * lab_categories), always including 'popular' so it shows under "Most Popular".
 *
 * Returns [] when the DB/tables are unavailable — caller keeps the static list.
 *
 * @return list<array>
 */
function ecp_lab_db_packages(int $limit = 12): array
{
    if (!function_exists('ecp_db')) {
        return [];
    }
    $db = ecp_db();
    if (!$db) {
        return [];
    }

    try {
        // Grid rows: current price = latest effective_from per product.
        $stmt = $db->prepare(
            "SELECT lp.id, lp.slug, lp.name, lp.test_count, lp.is_featured,
                    pr.mrp, pr.offer_rate
             FROM lab_products lp
             JOIN lab_product_pricing pr ON pr.id = (
                 SELECT p2.id FROM lab_product_pricing p2
                 WHERE p2.product_id = lp.id
                 ORDER BY p2.effective_from DESC, p2.id DESC LIMIT 1
             )
             WHERE lp.is_active = 1
               AND lp.product_type IN ('PROFILE', 'OFFER')
               AND pr.offer_rate > 0
             ORDER BY lp.is_featured DESC, lp.booked_count DESC, lp.name ASC
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $in  = implode(',', array_fill(0, count($ids), '?'));

        // Up to 6 sample test names per package (for the card's test-chip list).
        $testsByProduct = [];
        $tstmt = $db->prepare(
            "SELECT lpp.product_id, par.name
             FROM lab_product_parameters lpp
             JOIN lab_parameters par ON par.id = lpp.parameter_id
             WHERE lpp.product_id IN ($in)
             ORDER BY par.name"
        );
        $tstmt->execute($ids);
        foreach ($tstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pid = (int) $r['product_id'];
            if (count($testsByProduct[$pid] ?? []) < 6) {
                $testsByProduct[$pid][] = ecp_lab_titlecase($r['name']);
            }
        }

        // Category slugs per package (for tab mapping).
        $catsByProduct = [];
        $cstmt = $db->prepare(
            "SELECT lpc.product_id, c.slug
             FROM lab_product_categories lpc
             JOIN lab_categories c ON c.id = lpc.category_id
             WHERE lpc.product_id IN ($in)"
        );
        $cstmt->execute($ids);
        foreach ($cstmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $catsByProduct[(int) $r['product_id']][] = $r['slug'];
        }

        $tabMap = ecp_lab_tab_category_map();

        $out = [];
        foreach ($rows as $row) {
            $pid   = (int) $row['id'];
            $mrp   = (float) $row['mrp'];
            $offer = (float) $row['offer_rate'];
            $offPct = $mrp > 0 ? (int) round(($mrp - $offer) / $mrp * 100) : 0;

            // Which storefront tabs this package belongs to.
            $prodCats = $catsByProduct[$pid] ?? [];
            $tabs = ['popular']; // always eligible for "Most Popular"
            // Full Body = comprehensive panel (by test count, not category).
            if ((int) $row['test_count'] >= ECP_LAB_FULL_BODY_MIN_TESTS) {
                $tabs[] = 'full-body';
            }
            foreach ($tabMap as $tab => $slugs) {
                if (array_intersect($prodCats, $slugs)) {
                    $tabs[] = $tab;
                }
            }
            $tabs = array_values(array_unique($tabs));

            $tests = $testsByProduct[$pid] ?? [];
            $highlights = [
                (int) $row['test_count'] . ' tests in one package',
                $offPct > 0 ? "Save up to {$offPct}% vs individual tests" : 'Comprehensive health screening',
                'Free home sample collection & digital reports',
            ];

            $out[] = [
                (string) $row['slug'],
                ecp_lab_titlecase($row['name']),
                $highlights,
                $tests,
                (string) (int) $row['test_count'],
                (string) (int) round($offer),
                (string) (int) round($mrp),
                (string) $offPct,
                $tabs,
                !empty($row['is_featured']) ? 'Bestseller' : '',
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[ecp_lab_db_packages] ' . $e->getMessage());
        return [];
    }
}

/**
 * Full autocomplete index from the DB: every active package/offer plus the most
 * popular individual tests. Shaped like lab.php's $labSearchIndex package rows
 * (type/slug/title/meta/params/price/mrp/off/url/q) so the JS renderer is
 * unchanged. Returns [] when the DB is unavailable (caller keeps the static index).
 *
 * @return list<array<string,mixed>>
 */
function ecp_lab_db_search_index(int $testLimit = 150): array
{
    if (!function_exists('ecp_db')) {
        return [];
    }
    $db = ecp_db();
    if (!$db) {
        return [];
    }
    try {
        // All packages/offers + top individual tests, each with current price.
        $stmt = $db->prepare(
            "SELECT lp.product_type, lp.slug, lp.name, lp.test_count,
                    pr.mrp, pr.offer_rate
             FROM lab_products lp
             JOIN lab_product_pricing pr ON pr.id = (
                 SELECT p2.id FROM lab_product_pricing p2
                 WHERE p2.product_id = lp.id
                 ORDER BY p2.effective_from DESC, p2.id DESC LIMIT 1
             )
             WHERE lp.is_active = 1 AND pr.offer_rate > 0
               AND (
                   lp.product_type IN ('PROFILE', 'OFFER')
                   OR lp.id IN (
                       SELECT id FROM (
                           SELECT id FROM lab_products
                           WHERE is_active = 1 AND product_type = 'TEST'
                           ORDER BY booked_count DESC, name ASC
                           LIMIT :tl
                       ) t
                   )
               )
             ORDER BY (lp.product_type = 'TEST'), lp.booked_count DESC, lp.name ASC"
        );
        $stmt->bindValue(':tl', $testLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $r) {
            $isPackage = $r['product_type'] !== 'TEST';
            $mrp   = (float) $r['mrp'];
            $offer = (float) $r['offer_rate'];
            $offPct = $mrp > 0 ? (int) round(($mrp - $offer) / $mrp * 100) : 0;
            $title = ecp_lab_titlecase($r['name']);
            $out[] = [
                'type'   => 'package', // detail route is /lab/package/{slug} for both
                'slug'   => (string) $r['slug'],
                'title'  => $title,
                'meta'   => $isPackage
                    ? 'Package | Includes ' . (int) $r['test_count'] . ' Tests'
                    : 'Test | Single parameter',
                'params' => (string) (int) $r['test_count'],
                'price'  => (string) (int) round($offer),
                'mrp'    => (string) (int) round($mrp),
                'off'    => (string) $offPct,
                'url'    => ecp_lab_detail_url('package', (string) $r['slug']),
                'q'      => strtolower($title),
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('[ecp_lab_db_search_index] ' . $e->getMessage());
        return [];
    }
}

/** Thyrocare names are ALL CAPS; present them in Title Case for the storefront. */
function ecp_lab_titlecase(string $name): string
{
    // Only re-case fully-uppercase strings; leave mixed-case names as-is.
    if ($name !== mb_strtoupper($name)) {
        return $name;
    }
    $out = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE);
    // Keep common lab acronyms uppercase.
    return preg_replace_callback(
        '/\b(cbc|tsh|t3|t4|hba1c|crp|hiv|hcg|psa|esr|ldl|hdl|vldl|ecg|pcr|dna|rna|std|uti|hs-crp|apo)\b/i',
        static fn ($m) => mb_strtoupper($m[1]),
        $out
    ) ?? $out;
}

function ecp_lab_raw_catalog(): array
{
    static $raw = null;
    if ($raw !== null) {
        return $raw;
    }

    // Prefer live DB packages; fall back to the curated static list below when
    // the DB or lab_* tables are unavailable (keeps the page working offline).
    $dbPackages = ecp_lab_db_packages(12);

    $raw = [
        'packages' => $dbPackages !== [] ? $dbPackages : [
            ['full-body-advanced', 'Full Body Checkup Advanced', ['Complete body health analysis', 'Includes blood, urine & vital markers', 'Ideal for annual health checkup'], ['CBC', 'Lipid Profile', 'Liver Function', 'Kidney Function', 'Thyroid (T3 T4 TSH)', 'HbA1c', 'Vitamin D', 'Vitamin B12'], '85', '1499', '4200', '64', ['popular', 'preventive'], 'Bestseller'],
            ['diabetes-care', 'Diabetes Care Profile', ['Monitors blood sugar levels', 'Includes HbA1c & fasting sugar', 'Early diabetes detection'], ['Fasting Blood Sugar', 'HbA1c', 'Lipid Profile', 'Kidney Function', 'Urine Routine'], '42', '699', '1800', '61', ['popular', 'diabetes'], ''],
            ['thyroid-total', 'Thyroid Profile - Total', ['Complete thyroid function test', 'Includes T3, T4 & TSH', 'Detects hypo & hyperthyroidism'], ['T3', 'T4', 'TSH', 'Anti-TPO'], '4', '499', '1200', '58', ['thyroid'], ''],
            ['heart-health', 'Heart Health Package', ['Assesses cardiovascular risk', 'Includes lipid & cardiac markers', 'Ideal for heart health monitoring'], ['Lipid Profile', 'ECG Review', 'hs-CRP', 'Homocysteine', 'Blood Pressure'], '30', '999', '2600', '62', ['popular', 'heart'], ''],
            ['vitamin-deficiency', 'Vitamin Deficiency Package', ['Checks key vitamin levels', 'Includes Vitamin D & B12', 'Identifies nutritional gaps'], ['Vitamin D', 'Vitamin B12', 'Calcium', 'Iron Studies', 'Folate'], '12', '899', '2400', '63', ['preventive'], ''],
            ['womens-wellness', "Women's Wellness - Full", ['Comprehensive women\'s health panel', 'Hormones, iron & bone markers', 'Ideal for preventive screening'], ['CBC', 'Thyroid', 'Iron Studies', 'Vitamin D', 'Vitamin B12', 'PCOS Markers', 'Calcium'], '60', '1299', '3500', '63', ['popular', 'women'], 'Popular'],
            ['senior-citizen', 'Senior Citizen Package', ['Age-focused full body screening', 'Covers chronic & lifestyle risks', 'Recommended for 60+ adults'], ['Full Body Profile', 'Diabetes', 'Cardiac', 'Bone Health', 'Liver & Kidney'], '90', '1799', '5000', '64', ['popular', 'senior'], ''],
            ['fever-infection', 'Fever / Infection Panel', ['Detects common infections', 'Includes dengue, malaria & typhoid', 'Quick fever diagnosis panel'], ['CBC', 'Malaria', 'Dengue NS1', 'Typhoid', 'CRP', 'Urine Routine'], '20', '799', '2000', '60', ['preventive'], ''],
        ],
        'packageFilters' => [
            'all' => 'All Packages', 'popular' => 'Most Popular', 'heart' => 'Heart', 'diabetes' => 'Diabetes',
            'thyroid' => 'Thyroid', 'women' => 'Women', 'senior' => 'Senior Citizen', 'preventive' => 'Preventive', 'kids' => 'Kids',
        ],
        'organs' => [
            ['heart', 'Heart', 'ECG, Lipid Profile & Heart Health Tests'],
            ['lungs', 'Lungs', 'Lung Function, Allergy & Respiratory Tests'],
            ['liver', 'Liver', 'Liver Function, Hepatitis & Detoxification Tests'],
            ['kidney', 'Kidney', 'Kidney Function, Urea, Creatinine & More'],
            ['thyroid', 'Thyroid', 'Thyroid Profile, TSH, T3, T4 & More'],
            ['diabetes', 'Diabetes', 'Blood Sugar, HbA1c & Diabetes Monitoring'],
            ['bone', 'Bone', 'Calcium, Vitamin D & Bone Density Tests'],
            ['brain', 'Brain', 'Mental Wellness, Vitamin B12 & Neurological Tests'],
            ['full-body', 'Full Body', 'Comprehensive Full Body Health Checkups'],
            ['vitamins', 'Vitamins', 'Vitamin D, B12, Iron & Other Vitamin Tests'],
            ['immunity', 'Immunity', 'Immunity Profile & Nutritional Deficiency'],
            ['pregnancy', 'Pregnancy', 'PCOS, Pregnancy & Women Wellness Tests'],
        ],
        'concerns' => [
            ['fever', 'Fever', 'Cold, flu, viral infections & more'],
            ['cough', 'Cough', 'Dry cough, wet cough, allergy & more'],
            ['body-pain', 'Body Pain', 'Muscle pain, joint pain, back pain & more'],
            ['stomach', 'Stomach Issues', 'Acidity, gas, pain, bloating & more'],
            ['skin', 'Skin Problems', 'Acne, allergy, rashes, itching & more'],
            ['fatigue', 'Fatigue', 'Tiredness, weakness, low energy & more'],
            ['weight', 'Weight Issues', 'Weight gain, weight loss & more'],
            ['heart-health', 'Heart Health', 'Chest pain, BP, cholesterol & heart risk'],
        ],
        'lifeStage' => [
            ['child', '👶', 'Child Health', 'Growth, immunity, nutrition & essential tests for a healthy future.', 'blue'],
            ['mens', '👨', "Men's Wellness", 'Stay strong, active & stress-free with preventive health checks.', 'green'],
            ['womens', '👩', "Women's Wellness", 'Hormonal balance, fitness & vital tests for women at every stage.', 'purple'],
            ['pregnancy', '🤰', 'Pregnancy Care', 'Nutritional support, safe monitoring & essential tests for a healthy journey.', 'pink'],
            ['senior', '👴', 'Senior Care', 'Comprehensive aging care & chronic disease management.', 'orange'],
            ['fitness', '🏃', 'Fitness Check', 'Stay active, improve performance & track your metabolic health.', 'teal'],
        ],
        'why' => [
            ['nabl', 'NABL-Accredited Labs', 'Trusted & accurate results you can rely on.'],
            ['home', 'Free Home Sample Collection', 'Safe, convenient & hassle-free collection.'],
            ['reports-app', 'Digital Reports on App', 'Access your reports anytime, anywhere.'],
            ['fast', 'Reports Within 24 Hours', 'Quick turnaround for faster insights.'],
            ['doctor', 'Free Doctor Consultation', 'Expert advice with every test you book.'],
            ['savings', 'Up to 70% Off MRP', 'Affordable pricing on 5000+ tests.'],
            ['hygiene', 'Safe & Hygienic Collection', 'Trained professionals with proper safety.'],
            ['india', 'Available Across India', 'Pan-India network at your service.'],
        ],
        'steps' => [
            ['01', 'choose', 'Choose a Test or Package', 'Search or browse by organ, symptom or life stage.'],
            ['02', 'home', 'Book Home Collection', 'Pick a time slot — our phlebotomist visits you.'],
            ['03', 'lab', 'Sample Tested at NABL Lab', 'Processed at accredited partner labs near you.'],
            ['04', 'reports', 'Get Digital Reports', 'Reports on the app + a free doctor consult.'],
        ],
        'partners' => [
            ['nabl', 'NABL-Accredited Labs', 'Tests processed at NABL-approved labs.'],
            ['pathology', 'Certified Pathology Network', 'A trusted network of certified labs.'],
            ['partners', 'Trusted Diagnostic Partners', 'Partnered with reliable diagnostic experts.'],
            ['centers', 'Pan-India Collection Centers', 'Wide reach for your convenience.'],
        ],
    ];
    return $raw;
}

/** @return list<array<string,mixed>> */
function ecp_lab_items(?string $type = null): array
{
    static $all = null;
    if ($all === null) {
        $photos = ecp_lab_photos();
        $raw = ecp_lab_raw_catalog();
        $all = [];

        foreach ($raw['packages'] as $row) {
            [$slug, $name, $highlights, $tests, $params, $price, $mrp, $off, $cats, $badge] = $row;
            $key = 'pkg-' . $slug;
            $all[] = [
                'type' => 'package', 'slug' => $slug, 'title' => $name,
                'subtitle' => $params . ' tests included · Up to ' . $off . '% off',
                'blurb' => $highlights[0] ?? '',
                'highlights' => $highlights, 'tests' => $tests, 'params' => $params,
                'price' => $price, 'mrp' => $mrp, 'off' => $off, 'cats' => $cats, 'badge' => $badge,
                'photo' => $photos[$key] ?? $photos['gal-1'],
                'crumb' => 'Health Packages',
            ];
        }
        foreach ($raw['organs'] as $row) {
            [$slug, $label, $desc] = $row;
            $key = 'organ-' . $slug;
            $all[] = [
                'type' => 'organ', 'slug' => $slug, 'title' => $label . ' Health Tests',
                'subtitle' => $desc, 'blurb' => $desc, 'highlights' => [],
                'photo' => $photos[$key] ?? $photos['gal-1'], 'crumb' => 'Body Organ & System',
            ];
        }
        foreach ($raw['concerns'] as $row) {
            [$slug, $title, $desc] = $row;
            $key = 'symptom-' . $slug;
            $all[] = [
                'type' => 'symptom', 'slug' => $slug, 'title' => $title,
                'subtitle' => $desc, 'blurb' => $desc, 'highlights' => [],
                'photo' => $photos[$key] ?? $photos['gal-1'], 'crumb' => 'Book by Symptom',
            ];
        }
        foreach ($raw['lifeStage'] as $row) {
            [$slug, $ico, $name, $blurb, $accent] = $row;
            $key = 'life-' . $slug;
            $all[] = [
                'type' => 'life', 'slug' => $slug, 'title' => $name, 'subtitle' => $blurb,
                'blurb' => $blurb, 'highlights' => [], 'emoji' => $ico, 'accent' => $accent,
                'photo' => $photos[$key] ?? $photos['gal-1'], 'crumb' => 'Life Stage Packages',
            ];
        }
        foreach ($raw['why'] as $row) {
            [$slug, $title, $blurb] = $row;
            $key = 'why-' . $slug;
            $all[] = [
                'type' => 'why', 'slug' => $slug, 'title' => $title, 'subtitle' => $blurb,
                'blurb' => $blurb, 'highlights' => [],
                'photo' => $photos[$key] ?? $photos['gal-1'], 'crumb' => 'Why eClinicPro',
            ];
        }
        foreach ($raw['steps'] as $row) {
            [$num, $slug, $title, $blurb] = $row;
            $key = 'step-' . $slug;
            $all[] = [
                'type' => 'step', 'slug' => $slug, 'title' => $title, 'subtitle' => 'Step ' . $num . ' · ' . $blurb,
                'blurb' => $blurb, 'highlights' => [], 'step_num' => $num,
                'photo' => $photos[$key] ?? $photos['gal-1'], 'crumb' => 'How It Works',
            ];
        }
        foreach ($raw['partners'] as $row) {
            [$slug, $title, $blurb] = $row;
            $key = 'partner-' . $slug;
            $all[] = [
                'type' => 'partner', 'slug' => $slug, 'title' => $title, 'subtitle' => $blurb,
                'blurb' => $blurb, 'highlights' => [],
                'photo' => $photos[$key] ?? $photos['gal-1'], 'crumb' => 'Accredited Labs',
            ];
        }
    }

    if ($type === null) {
        return $all;
    }
    return array_values(array_filter($all, static fn($i) => $i['type'] === $type));
}

function ecp_lab_find_item(string $type, string $slug): ?array
{
    foreach (ecp_lab_items($type) as $item) {
        if ($item['slug'] === $slug) {
            return $item;
        }
    }
    // The grid only lists the top ~12 packages, but a detail URL may point at
    // any of the 300+ DB packages/offers. Resolve those directly by slug.
    if ($type === 'package') {
        return ecp_lab_db_find_package($slug);
    }
    return null;
}

/**
 * Look up a single package/offer by slug from the DB and shape it like an
 * ecp_lab_items() 'package' entry (so lab-detail.php renders it unchanged).
 * Returns null when not found or the DB is unavailable.
 */
function ecp_lab_db_find_package(string $slug): ?array
{
    if (!function_exists('ecp_db')) {
        return null;
    }
    $db = ecp_db();
    if (!$db) {
        return null;
    }
    try {
        $stmt = $db->prepare(
            "SELECT lp.id, lp.slug, lp.name, lp.test_count, lp.description,
                    pr.mrp, pr.offer_rate
             FROM lab_products lp
             JOIN lab_product_pricing pr ON pr.id = (
                 SELECT p2.id FROM lab_product_pricing p2
                 WHERE p2.product_id = lp.id
                 ORDER BY p2.effective_from DESC, p2.id DESC LIMIT 1
             )
             WHERE lp.slug = ? AND lp.is_active = 1
               AND lp.product_type IN ('PROFILE', 'OFFER')
             LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        // Full test list (grouped names) for the detail page.
        $tstmt = $db->prepare(
            "SELECT par.name
             FROM lab_product_parameters lpp
             JOIN lab_parameters par ON par.id = lpp.parameter_id
             WHERE lpp.product_id = ?
             ORDER BY par.group_name, par.name"
        );
        $tstmt->execute([(int) $row['id']]);
        $tests = array_map(
            static fn ($r) => ecp_lab_titlecase($r['name']),
            $tstmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );

        $mrp   = (float) $row['mrp'];
        $offer = (float) $row['offer_rate'];
        $offPct = $mrp > 0 ? (int) round(($mrp - $offer) / $mrp * 100) : 0;
        $photos = ecp_lab_photos();

        return [
            'type'       => 'package',
            'slug'       => (string) $row['slug'],
            'title'      => ecp_lab_titlecase($row['name']),
            'subtitle'   => (int) $row['test_count'] . ' tests included'
                            . ($offPct > 0 ? ' · Up to ' . $offPct . '% off' : ''),
            'blurb'      => $row['description'] ?: 'A comprehensive health package with '
                            . (int) $row['test_count'] . ' tests, sample collected at home.',
            'highlights' => [
                (int) $row['test_count'] . ' tests in one package',
                'Free home sample collection & digital reports',
                'Free doctor consultation with your report',
            ],
            'tests'  => $tests,
            'params' => (string) (int) $row['test_count'],
            'price'  => (string) (int) round($offer),
            'mrp'    => (string) (int) round($mrp),
            'off'    => (string) $offPct,
            'cats'   => ['popular'],
            'badge'  => '',
            'photo'  => $photos['pkg-' . $slug]
                        ?? $photos['gal-' . (1 + (abs(crc32($slug)) % 8))]
                        ?? $photos['hero-lab'],
            'crumb'  => 'Health Packages',
        ];
    } catch (Throwable $e) {
        error_log('[ecp_lab_db_find_package] ' . $e->getMessage());
        return null;
    }
}

/**
 * Build rich, type-specific detail content from a catalog item.
 * @return array<string,mixed>
 */
function ecp_lab_build_detail(array $item): array
{
    $type = $item['type'];
    $title = $item['title'];
    $photos = ecp_lab_photos();
    $hero = lab_photo($item['photo'], 1600, 900);
    $galleryKeys = ['gal-1', 'gal-2', 'gal-3', 'gal-4', 'gal-5', 'gal-6'];
    // Rotate gallery by slug hash so pages feel unique
    $seed = crc32($type . ':' . $item['slug']);
    $rotated = [];
    for ($i = 0; $i < 4; $i++) {
        $rotated[] = lab_photo($photos[$galleryKeys[($seed + $i) % count($galleryKeys)]], 800, 600);
    }
    // Prefer item photo first in gallery
    array_unshift($rotated, lab_photo($item['photo'], 900, 700));
    $rotated = array_slice(array_unique($rotated), 0, 4);

    $related = array_values(array_filter(
        ecp_lab_items($type),
        static fn($r) => $r['slug'] !== $item['slug']
    ));
    shuffle($related);
    $related = array_slice($related, 0, 4);

    $baseProcess = [
        ['Choose', 'Select this ' . ($type === 'package' ? 'package' : 'option') . ' or related tests that match your needs.'],
        ['Book', 'Pick a convenient home collection slot — a trained phlebotomist visits you.'],
        ['Test', 'Your sample is processed at NABL-accredited partner labs.'],
        ['Report', 'Get digital reports on the app plus a free doctor consult.'],
    ];

    $detail = [
        'hero_image' => $hero,
        'gallery' => $rotated,
        'related' => $related,
        'theme' => $type,
        'eyebrow' => strtoupper(str_replace('-', ' ', $type)),
    ];

    switch ($type) {
        case 'package':
            $tests = $item['tests'] ?? [];
            $highlights = $item['highlights'] ?? [];
            $detail += [
                'overview' => "The {$title} is a carefully curated diagnostic panel designed for clear, actionable insights. It includes {$item['params']} parameters spanning the markers clinicians use most often for prevention and early detection.",
                'about' => "Built for people who want a reliable health snapshot without guesswork, this package brings together essential blood and related markers in one booking. Home sample collection, NABL-lab processing, and digital reports keep the journey simple from start to finish.",
                'features' => array_merge($highlights, array_map(static fn($t) => "Includes {$t}", array_slice($tests, 0, 4))),
                'benefits' => [
                    'Save time with a single booking instead of ordering tests one by one',
                    'Transparent pricing with up to ' . ($item['off'] ?? '60') . '% off MRP',
                    'Reports typically available within 24 hours*',
                    'Free doctor consultation to help interpret results',
                    'Safe home collection by trained professionals',
                ],
                'process' => $baseProcess,
                'stats' => [
                    ['value' => ($item['params'] ?? '—') . '+', 'label' => 'Parameters'],
                    ['value' => '₹' . ($item['price'] ?? '—'), 'label' => 'Offer price'],
                    ['value' => ($item['off'] ?? '—') . '%', 'label' => 'Off MRP'],
                    ['value' => '24h*', 'label' => 'Report turnaround'],
                ],
                'faq' => [
                    ['q' => "What is included in {$title}?", 'a' => 'This package includes ' . implode(', ', array_slice($tests, 0, 6)) . (count($tests) > 6 ? ', and more' : '') . ' — ' . ($item['params'] ?? '') . ' parameters in total.'],
                    ['q' => 'Do I need to fast before the test?', 'a' => 'Some markers (such as fasting sugar or lipid profile) may require overnight fasting. You will see exact prep instructions at booking.'],
                    ['q' => 'How do I get my reports?', 'a' => 'Digital reports are delivered on the eClinicPro app, with an optional free doctor consult to review findings.'],
                    ['q' => 'Is home collection available?', 'a' => 'Yes. Choose a time slot and our phlebotomist will collect your sample at home.'],
                ],
                'cta_title' => "Ready to book {$title}?",
                'cta_text' => 'Secure your slot for home collection and get digital reports with a free doctor consult.',
            ];
            break;

        case 'organ':
            $organ = str_replace(' Health Tests', '', $title);
            $detail += [
                'overview' => "Explore lab tests focused on {$organ} health. Whether you are screening preventively or following up on symptoms, these panels help you and your clinician understand how this system is functioning.",
                'about' => "{$organ}-focused diagnostics look at the biomarkers most relevant to this organ or system. On eClinicPro you can browse related packages, book home collection, and receive NABL-lab reports digitally.",
                'features' => [
                    "Targeted {$organ} biomarker panels",
                    'Matched packages for common concerns',
                    'Home sample collection available',
                    'NABL-accredited lab processing',
                    'Digital reports + doctor consult option',
                ],
                'benefits' => [
                    "Catch {$organ}-related issues earlier with the right markers",
                    'Avoid unnecessary full panels when you need focused testing',
                    'Clear next steps after reviewing results with a clinician',
                    'Convenient booking without clinic queues',
                ],
                'process' => [
                    ['Browse', "Review {$organ} tests and related packages."],
                    ['Book', 'Schedule home collection at a time that suits you.'],
                    ['Analyse', 'Samples are tested at accredited labs.'],
                    ['Act', 'Discuss digital reports with a doctor if needed.'],
                ],
                'stats' => [
                    ['value' => 'NABL', 'label' => 'Lab network'],
                    ['value' => 'Home', 'label' => 'Collection'],
                    ['value' => '24h*', 'label' => 'Reports'],
                    ['value' => 'Free', 'label' => 'Consult*'],
                ],
                'faq' => [
                    ['q' => "Which {$organ} tests should I start with?", 'a' => "Start with the most common {$organ} markers for your age and symptoms, or choose a curated package that groups them together."],
                    ['q' => 'Can I combine with other organ panels?', 'a' => 'Yes. Many people add related systems (for example heart + diabetes) for a broader view.'],
                    ['q' => 'How soon will I get results?', 'a' => 'Most panels return digital reports within about 24 hours*, depending on the specific tests.'],
                ],
                'cta_title' => "Find the right {$organ} tests",
                'cta_text' => 'Browse packages related to this system and book home collection in minutes.',
            ];
            break;

        case 'symptom':
            $detail += [
                'overview' => "{$title} can have many causes — from short-lived infections to longer-term conditions. The right lab tests help narrow possibilities so you and your clinician can decide what to do next.",
                'about' => "This guide highlights tests commonly considered when {$title} is a concern ({$item['blurb']}). It is educational, not a diagnosis. Always seek medical advice for severe, sudden, or lasting symptoms.",
                'features' => [
                    'Symptom-matched test suggestions',
                    'Curated packages for common patterns',
                    'Home collection when you feel unwell',
                    'Fast digital reporting',
                    'Optional doctor consult on results',
                ],
                'benefits' => [
                    'Move from guesswork to measurable markers',
                    'Spot infections, deficiencies, or metabolic issues earlier',
                    'Reduce unnecessary hospital visits for routine blood work',
                    'Keep family members tested with the same easy flow',
                ],
                'process' => $baseProcess,
                'stats' => [
                    ['value' => 'Quick', 'label' => 'Booking'],
                    ['value' => 'Safe', 'label' => 'Collection'],
                    ['value' => '24h*', 'label' => 'Reports'],
                    ['value' => 'Expert', 'label' => 'Review'],
                ],
                'faq' => [
                    ['q' => "Should I test immediately for {$title}?", 'a' => 'Mild short-lived symptoms often settle on their own. Persistent, severe, or recurring symptoms deserve clinical advice and may need lab work.'],
                    ['q' => 'Which package is best?', 'a' => 'Fever/infection, full body, vitamin, or organ-specific packages may apply depending on your history. Use filters on the lab page or ask a doctor.'],
                    ['q' => 'Is this a diagnosis?', 'a' => 'No. Lab results support clinical decisions; only a qualified clinician can diagnose.'],
                ],
                'cta_title' => "Tests related to {$title}",
                'cta_text' => 'Book a relevant package or panel with home collection and digital reports.',
            ];
            break;

        case 'life':
            $detail += [
                'overview' => "{$title} packages focus on the screenings that matter most at this stage of life. {$item['blurb']}",
                'about' => "Preventive testing looks different at every age. This life-stage pathway groups the markers clinicians often recommend so you can stay ahead of silent risks and track change over time.",
                'features' => [
                    'Age-appropriate screening focus',
                    'Bundled pricing vs à-la-carte tests',
                    'Home sample collection',
                    'Digital reports for easy sharing with your doctor',
                    'Optional free consult on findings',
                ],
                'benefits' => [
                    'Screening tailored to life stage — not one-size-fits-all',
                    'Peace of mind for you and your family',
                    'Baseline results to compare year after year',
                    'Convenient booking around work and family schedules',
                ],
                'process' => $baseProcess,
                'stats' => [
                    ['value' => 'Stage', 'label' => 'Specific care'],
                    ['value' => 'Home', 'label' => 'Collection'],
                    ['value' => 'NABL', 'label' => 'Labs'],
                    ['value' => 'App', 'label' => 'Reports'],
                ],
                'faq' => [
                    ['q' => "Who is {$title} for?", 'a' => $item['blurb']],
                    ['q' => 'How often should I repeat tests?', 'a' => 'Many adults screen annually; your clinician may suggest a different cadence based on results and risk factors.'],
                    ['q' => 'Can multiple family members book together?', 'a' => 'Yes. Book separate profiles or slots so each person gets their own reports.'],
                ],
                'cta_title' => "Explore {$title}",
                'cta_text' => 'Choose a matching package and schedule home collection today.',
            ];
            break;

        case 'step':
            $num = $item['step_num'] ?? '';
            $detail += [
                'overview' => "Step {$num}: {$title}. {$item['blurb']}",
                'about' => "eClinicPro keeps diagnostics simple — from discovery to digital reports. This step is a key part of a four-stage journey designed around convenience, accuracy, and clinical follow-up.",
                'features' => [
                    'Clear guidance at every stage',
                    'Mobile-friendly booking experience',
                    'Trained collection staff',
                    'Accredited lab partners',
                    'Reports you can revisit anytime',
                ],
                'benefits' => [
                    'Less friction than traditional walk-in labs',
                    'Transparent status from booking to report',
                    'Support if you need help choosing tests',
                ],
                'process' => $baseProcess,
                'stats' => [
                    ['value' => $num ?: '—', 'label' => 'Step'],
                    ['value' => '4', 'label' => 'Total steps'],
                    ['value' => 'Easy', 'label' => 'Flow'],
                    ['value' => '24h*', 'label' => 'Reports'],
                ],
                'faq' => [
                    ['q' => 'Do I have to complete steps in order?', 'a' => 'Yes — choose tests, book collection, lab processing, then reports. You can pause between steps if needed.'],
                    ['q' => 'What if I need to reschedule collection?', 'a' => 'Contact support or use your booking tools (when live) to pick another slot.'],
                ],
                'cta_title' => 'Continue your lab journey',
                'cta_text' => 'Browse packages and start with the test that fits you best.',
            ];
            break;

        case 'why':
        case 'partner':
            $detail += [
                'overview' => "{$title} — {$item['blurb']}",
                'about' => "Quality, safety, and trust sit at the centre of eClinicPro diagnostics. {$title} is one of the reasons people choose to book lab tests with us: clear standards, careful handling, and results you can act on.",
                'features' => [
                    $item['blurb'],
                    'Consistent processes across partner labs',
                    'Privacy-minded handling of health data',
                    'Support from booking through reports',
                    'Designed for India-wide convenience',
                ],
                'benefits' => [
                    'Confidence in where your sample is processed',
                    'Fewer surprises on quality and turnaround',
                    'A single place to book, track, and review',
                ],
                'process' => $baseProcess,
                'stats' => [
                    ['value' => 'NABL', 'label' => 'Standards'],
                    ['value' => 'Pan-IN', 'label' => 'Reach'],
                    ['value' => 'Secure', 'label' => 'Data'],
                    ['value' => '24h*', 'label' => 'Speed'],
                ],
                'faq' => [
                    ['q' => "What does {$title} mean for me?", 'a' => $item['blurb'] . ' In practice, it means clearer expectations on quality, convenience, and how your results are delivered.'],
                    ['q' => 'Are partner labs named on this page?', 'a' => 'Named partnerships are shown only after agreements are signed. Until then we describe accredited, certified networks generically.'],
                ],
                'cta_title' => 'Book with confidence',
                'cta_text' => 'Explore packages backed by accredited processing and careful home collection.',
            ];
            break;

        default:
            $detail += [
                'overview' => $item['subtitle'] ?? $item['blurb'] ?? $title,
                'about' => 'Learn more about this offering on eClinicPro Lab Tests & Health Packages.',
                'features' => ['Home collection', 'NABL labs', 'Digital reports', 'Doctor consult'],
                'benefits' => ['Convenient', 'Trusted', 'Affordable', 'Fast'],
                'process' => $baseProcess,
                'stats' => [
                    ['value' => 'NABL', 'label' => 'Labs'],
                    ['value' => 'Home', 'label' => 'Collection'],
                    ['value' => '24h*', 'label' => 'Reports'],
                    ['value' => 'App', 'label' => 'Access'],
                ],
                'faq' => [
                    ['q' => 'How do I book?', 'a' => 'Open the lab page, choose a package, and complete booking when the flow is live.'],
                ],
                'cta_title' => 'Explore lab tests',
                'cta_text' => 'Return to the catalog to find the right package.',
            ];
    }

    // Deduplicate features / trim
    $detail['features'] = array_values(array_unique(array_filter($detail['features'])));
    $detail['features'] = array_slice($detail['features'], 0, 6);

    return array_merge($item, $detail);
}
