<?php

declare(strict_types=1);

/**
 * eClinicPro Store — TEST data for trying every screen on the LIVE database.
 *
 * Creates 15 test sellers (all statuses), ~170 products with variants, 25 test
 * customers and ~45 orders covering every order / payment / shipping / return /
 * refund / payout state, priced by the app's own PricingService so every total,
 * GST split, commission and seller balance matches what real checkout produces.
 *
 * Usage (from the app/ folder, as the site user — NOT root):
 *   php database/seeds/store_test_seed.php          # remove old test data, then seed fresh
 *   php database/seeds/store_test_seed.php --wipe   # remove ALL test data only
 *
 * Logins: see TEST_CREDENTIALS.md next to this file (sellers: Test@12345;
 * customers: OTP 123456 while app/.env has STORE_TEST_OTP=1).
 *
 * What makes a row "test data" (App\Services\Store\StoreTestData):
 *   sellers  slug test-…            customers  phone +91 90000 00001–00099 + email test.patientNN@example.com
 *   orders   order_no ECS…-TST###   emails     …@example.com (never delivered)
 * --wipe removes exactly those and everything hanging off them. Real sellers,
 * customers and orders are never touched. If a REAL customer ordered a test
 * product, that seller is kept (closed, products archived) and reported.
 *
 * Side effects kept away from real people / records:
 *   - no emails, WhatsApp or SMS are sent while seeding (only notifier-free services are called);
 *   - no Razorpay calls: payments/refunds carry fake pay_TEST… / rfnd_TEST… ids;
 *   - GST documents only in the TEST sellers' own series (I{id}-…); nothing in
 *     eClinicPro's ECP-… series (see StoreTestData guards).
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Services\Store\AddressService;
use App\Services\Store\CommissionService;
use App\Services\Store\PricingService;
use App\Services\Store\ProductService;
use App\Services\Store\ReviewService;
use App\Services\Store\SettlementService;
use App\Services\Store\StoreCrypto;
use App\Services\Store\StoreSettings;
use App\Services\Store\StoreTestData as T;
use App\Services\Store\TaxDocumentService;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}
$base = dirname(__DIR__, 2);
if (is_file($base . '/.env')) {
    Dotenv::createImmutable($base)->safeLoad();
}
$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const SEED_PASSWORD = 'Test@12345';
const IMG_DIR_REL = '/uploads/store/products/test';

$WIPE_ONLY = in_array('--wipe', $argv ?? [], true);
mt_srand(20260930);
$NOW = time();

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

function out(string $s): void
{
    echo $s . "\n";
}

function q(string $sql, array $p = []): PDOStatement
{
    $st = Database::connection()->prepare($sql);
    $st->execute($p);

    return $st;
}

function ins(string $table, array $row): int
{
    return QueryBuilder::table($table)->insert($row);
}

/** Timestamp $days (may be fractional) before now. */
function ago(float $days): int
{
    return (int) ($GLOBALS['NOW'] - $days * 86400);
}

function dt(int $ts): string
{
    return date('Y-m-d H:i:s', $ts);
}

function slugify(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
}

/** Rupees → paise, rounded to whole rupees. */
function rs(float $rupees): int
{
    return (int) round($rupees) * 100;
}

function hist(int $orderId, ?int $voId, string $entity, ?int $entityId, ?string $from, string $to, string $actor, ?int $actorId, string $note, int $at): void
{
    ins('store_order_status_history', [
        'order_id' => $orderId, 'vendor_order_id' => $voId, 'entity' => $entity, 'entity_id' => $entityId,
        'from_status' => $from, 'to_status' => $to, 'actor_type' => $actor, 'actor_id' => $actorId,
        'note' => mb_substr($note, 0, 500), 'created_at' => dt($at),
    ]);
}

function tableExists(string $t): bool
{
    return (int) q('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t', ['t' => $t])->fetchColumn() > 0;
}

/** @return list<string> tables (store_* and a few patient ones) that have $column */
function tablesWith(string $column): array
{
    return q(
        "SELECT table_name FROM information_schema.columns
          WHERE table_schema = DATABASE() AND column_name = :c AND table_name LIKE 'store\\_%'",
        ['c' => $column]
    )->fetchAll(PDO::FETCH_COLUMN);
}

function idList(array $ids): string
{
    $ids = array_values(array_unique(array_map('intval', $ids)));

    return $ids ? implode(',', $ids) : '0';
}

// ===========================================================================
// 1. WIPE — remove every test row (always runs first, so re-seeding is clean)
// ===========================================================================

function wipe(string $base): void
{
    $pdo = Database::connection();
    $vendorsAll = q("SELECT id FROM store_vendors WHERE slug LIKE :p", ['p' => T::VENDOR_SLUG_PREFIX . '%'])->fetchAll(PDO::FETCH_COLUMN);
    $identities = q('SELECT id FROM patient_identities WHERE phone LIKE :p AND email LIKE :e',
        ['p' => T::PHONE_PREFIX . '%', 'e' => T::CUSTOMER_EMAIL_LIKE])->fetchAll(PDO::FETCH_COLUMN);
    $testPhones = q('SELECT phone FROM patient_identities WHERE id IN (' . idList($identities) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $phoneList = implode(',', array_map(static fn ($p) => $pdo->quote((string) $p), $testPhones)) ?: "''";
    $orders = q(
        'SELECT id FROM store_orders WHERE order_no REGEXP :p OR identity_id IN (' . idList($identities) . ')',
        ['p' => T::ORDER_NO_REGEXP]
    )->fetchAll(PDO::FETCH_COLUMN);

    // Sellers that REAL customers bought from are kept (closed + archived), never deleted.
    $realOrders = q(
        'SELECT DISTINCT o.order_no, vo.vendor_id FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
          WHERE vo.vendor_id IN (' . idList($vendorsAll) . ') AND vo.order_id NOT IN (' . idList($orders) . ')'
    )->fetchAll();
    $keep = array_unique(array_map(static fn ($r) => (int) $r['vendor_id'], $realOrders));
    $vendors = array_values(array_diff(array_map('intval', $vendorsAll), $keep));

    $products = q('SELECT id FROM store_products WHERE vendor_id IN (' . idList($vendors) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $variants = q('SELECT id FROM store_product_variants WHERE vendor_id IN (' . idList($vendors) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $vendorUsers = q('SELECT id FROM store_vendor_users WHERE vendor_id IN (' . idList($vendors) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $payments = q('SELECT id FROM store_payments WHERE order_id IN (' . idList($orders) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $refunds = q('SELECT id FROM store_refunds WHERE order_id IN (' . idList($orders) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $returns = q('SELECT id FROM store_returns WHERE order_id IN (' . idList($orders) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $shipments = q('SELECT id FROM store_shipments WHERE order_id IN (' . idList($orders) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $docs = q('SELECT id FROM store_tax_documents WHERE order_id IN (' . idList($orders) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $payouts = q('SELECT id FROM store_payouts WHERE vendor_id IN (' . idList($vendors) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $vendorOrders = q('SELECT id FROM store_vendor_orders WHERE order_id IN (' . idList($orders) . ')')->fetchAll(PDO::FETCH_COLUMN);
    $sellerInv = tableExists('store_seller_invoices')
        ? q('SELECT id FROM store_seller_invoices WHERE vendor_id IN (' . idList($vendors) . ')')->fetchAll(PDO::FETCH_COLUMN) : [];
    $carts = q('SELECT id FROM store_carts WHERE identity_id IN (' . idList($identities) . ')')->fetchAll(PDO::FETCH_COLUMN);

    $n = 0;
    $del = static function (string $sql) use (&$n): void {
        try {
            $n += Database::connection()->exec($sql);
        } catch (Throwable $e) {
            out('  (skipped: ' . $e->getMessage() . ')');
        }
    };
    // Children by parent ids first (no foreign keys on store tables; sets were read above).
    $del('DELETE FROM store_tax_document_lines WHERE document_id IN (' . idList($docs) . ')');
    $del('DELETE FROM store_tax_documents WHERE id IN (' . idList($docs) . ')');
    $del('DELETE FROM store_refund_items WHERE refund_id IN (' . idList($refunds) . ')');
    $del('DELETE FROM store_payment_transactions WHERE payment_id IN (' . idList($payments) . ')');
    $del('DELETE FROM store_return_items WHERE return_id IN (' . idList($returns) . ')');
    $del('DELETE FROM store_shipment_items WHERE shipment_id IN (' . idList($shipments) . ')');
    $del('DELETE FROM store_shipment_events WHERE shipment_id IN (' . idList($shipments) . ')');
    $del('DELETE FROM store_cart_items WHERE cart_id IN (' . idList($carts) . ') OR variant_id IN (' . idList($variants) . ')');
    if ($sellerInv) {
        $del('DELETE FROM store_seller_invoice_lines WHERE invoice_id IN (' . idList($sellerInv) . ')');
    }
    $del('DELETE FROM store_variant_options WHERE variant_id IN (' . idList($variants) . ')');
    if (tableExists('store_payout_requests')) {
        $del('DELETE FROM store_payout_requests WHERE payout_id IN (' . idList($payouts) . ') OR vendor_id IN (' . idList($vendors) . ')');
    }
    // Everything keyed by order / product / variant / customer / seller.
    foreach (tablesWith('order_id') as $t) {
        $del("DELETE FROM `$t` WHERE order_id IN (" . idList($orders) . ')');
    }
    foreach (tablesWith('product_id') as $t) {
        $del("DELETE FROM `$t` WHERE product_id IN (" . idList($products) . ')');
    }
    foreach (tablesWith('variant_id') as $t) {
        $del("DELETE FROM `$t` WHERE variant_id IN (" . idList($variants) . ')');
    }
    foreach (tablesWith('identity_id') as $t) {
        $del("DELETE FROM `$t` WHERE identity_id IN (" . idList($identities) . ')');
    }
    foreach (tablesWith('vendor_id') as $t) {
        $del("DELETE FROM `$t` WHERE vendor_id IN (" . idList($vendors) . ')');
    }
    if (tableExists('store_vendor_password_resets')) {
        foreach (['vendor_user_id', 'user_id'] as $col) {
            if ((int) q("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'store_vendor_password_resets' AND column_name = :c", ['c' => $col])->fetchColumn()) {
                $del("DELETE FROM store_vendor_password_resets WHERE $col IN (" . idList($vendorUsers) . ')');
            }
        }
    }
    $del('DELETE FROM store_products WHERE id IN (' . idList($products) . ')');
    $del('DELETE FROM store_orders WHERE id IN (' . idList($orders) . ')');
    $del("DELETE FROM store_coupons WHERE code LIKE 'TEST%'");
    $del("DELETE FROM store_brands WHERE slug LIKE 'test-%'");
    $del("DELETE FROM store_attribute_values WHERE attribute_id IN (SELECT id FROM store_attributes WHERE code LIKE 'test\\_%')");
    $del("DELETE FROM store_attributes WHERE code LIKE 'test\\_%'");
    $seqKeys = implode(',', array_map(static fn ($v) => $pdo->quote('v:' . (int) $v), $vendors)) ?: "''";
    $del("DELETE FROM store_tax_doc_sequences WHERE issuer_key IN ($seqKeys)");
    $del("DELETE FROM store_notifications WHERE (recipient_type = 'customer' AND recipient_id IN (" . idList($identities) . "))
             OR (recipient_type = 'vendor_user' AND recipient_id IN (" . idList($vendorUsers) . '))');
    $del("DELETE FROM store_audit_log WHERE (entity_type = 'vendor' AND entity_id IN (" . idList($vendors) . "))
             OR (entity_type = 'product' AND entity_id IN (" . idList($products) . "))
             OR (entity_type = 'payout' AND entity_id IN (" . idList($payouts) . "))
             OR (entity_type = 'vendor_order' AND entity_id IN (" . idList($vendorOrders) . "))
             OR (actor_type = 'vendor_user' AND actor_id IN (" . idList($vendorUsers) . "))
             OR (actor_type = 'customer' AND actor_id IN (" . idList($identities) . '))');
    if (tableExists('store_email_log')) {
        $del("DELETE FROM store_email_log WHERE recipient LIKE '%@" . T::EMAIL_DOMAIN . "' AND (recipient LIKE 'test.%')");
        $del("DELETE FROM store_email_log WHERE recipient IN ($phoneList)");   // WhatsApp rows (patch 2026_10_05)
    }
    $del("DELETE FROM notifications WHERE clinic_id = 0 AND template LIKE 'store\\_%' AND to_number IN ($phoneList)");
    $del('DELETE FROM store_vendor_users WHERE id IN (' . idList($vendorUsers) . ')');
    $del('DELETE FROM store_vendors WHERE id IN (' . idList($vendors) . ')');
    // Customers: sessions / consents / wishlists cascade from patient_identities.
    $del("DELETE FROM patient_otp_codes WHERE handle IN ($phoneList)");
    if (tableExists('patient_otp_rate_limits')) {
        $del("DELETE FROM patient_otp_rate_limits WHERE phone IN ($phoneList)");
    }
    if (tableExists('messaging_consent')) {
        $del('DELETE FROM messaging_consent WHERE patient_identity_id IN (' . idList($identities) . ')');
    }
    $del('DELETE FROM patient_identities WHERE id IN (' . idList($identities) . ')');

    // Generated product images.
    $dir = $base . '/public' . IMG_DIR_REL;
    foreach (glob($dir . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($dir);

    out('Removed test data: ' . count($vendors) . ' sellers, ' . count($products) . ' products, ' . count($identities)
        . ' customers, ' . count($orders) . " orders ($n rows in total).");
    if ($realOrders) {
        foreach ($keep as $vid) {
            q("UPDATE store_vendors SET status = 'closed', status_reason = 'Test seller: kept because real customers ordered from it' WHERE id = :v", ['v' => $vid]);
            q("UPDATE store_products SET status = 'archived' WHERE vendor_id = :v", ['v' => $vid]);
        }
        out('KEPT (closed, products archived) because REAL customers ordered test products — check these orders:');
        foreach ($realOrders as $r) {
            out('  order ' . $r['order_no'] . ' (seller #' . $r['vendor_id'] . ')');
        }
    }
}

wipe($base);
if ($WIPE_ONLY) {
    exit(0);
}

// Refuse to seed next to real GST documents in eClinicPro's own series: test packages
// must never get an ECP-D… delivery invoice. The guard in TaxDocumentService already
// skips test sellers; this is a belt-and-braces check that the code on the server is current.
if (!defined(T::class . '::CUSTOMER_EMAIL_LIKE')) {
    exit("Upload the current app/app/Services/Store/StoreTestData.php first.\n");
}
// Never take over a REAL patient's number: the test range must be free (or test-only).
$clash = q('SELECT phone FROM patient_identities WHERE phone LIKE :p AND (email IS NULL OR email NOT LIKE :e)',
    ['p' => T::PHONE_PREFIX . '%', 'e' => T::CUSTOMER_EMAIL_LIKE])->fetchAll(PDO::FETCH_COLUMN);
if ($clash) {
    exit('Stopped: real patient account(s) use test number(s) ' . implode(', ', $clash) . ". Nothing was seeded.\n");
}

// ===========================================================================
// 2. Master data the test products need
// ===========================================================================

out('Seeding…');

// ---- Categories: open subcategories per department, plus 'review' ones for pending products ----
$cats = [];   // dept slug => ['open' => [rows], 'review' => [rows]]
foreach (q(
    "SELECT c.id, c.slug, c.name, c.parent_id, c.listing_mode, c.regulatory_class, c.required_vendor_doc, c.no_promotion, d.slug AS dept
       FROM store_categories c JOIN store_categories d ON d.id = c.parent_id
      WHERE c.is_active = 1 AND d.is_active = 1 AND d.parent_id = 0 AND c.listing_mode <> 'blocked'
      ORDER BY c.sort_order, c.id"
)->fetchAll() as $c) {
    $cats[$c['dept']][$c['listing_mode'] === 'review' ? 'review' : 'open'][] = $c;
}
$concernsByCat = [];
foreach (q('SELECT category_id, concern_id FROM store_concern_categories')->fetchAll() as $r) {
    $concernsByCat[(int) $r['category_id']][] = (int) $r['concern_id'];
}

// ---- Variant attributes (test_ codes so --wipe can remove them) ----
$ATTR = [
    'test_pack_size' => ['Pack size', 'select'],
    'test_flavour' => ['Flavour', 'select'],
    'test_weight' => ['Net weight', 'select'],
    'test_volume' => ['Volume', 'select'],
    'test_size' => ['Size', 'select'],
    'test_colour' => ['Colour', 'select'],
];
$attrId = [];
foreach ($ATTR as $code => [$name, $type]) {
    $attrId[$code] = ins('store_attributes', ['code' => $code, 'name' => $name, 'type' => $type, 'is_filterable' => 1, 'is_variant_axis' => 1]);
}
$attrValueId = [];   // "code|value" => id
function attrValue(string $code, string $value): int
{
    global $attrValueId, $attrId;
    $k = $code . '|' . $value;
    if (!isset($attrValueId[$k])) {
        $attrValueId[$k] = ins('store_attribute_values', [
            'attribute_id' => $attrId[$code], 'value' => $value, 'slug' => slugify($value) ?: 'v', 'sort_order' => count($attrValueId),
        ]);
    }

    return $attrValueId[$k];
}

// ---- Brands ----
$BRANDS = ['NutriCore', 'AyurRoots', 'LittleBloom', 'MediTrack', 'FitFuel', 'GlowLab', 'BrightSmile', 'Femora', 'CalmCo', 'GreenBowl', 'ClearSight', 'HomeEase', 'VitalMan', 'HerbHouse', 'QuickCare'];
$brandId = [];
foreach ($BRANDS as $i => $b) {
    $brandId[$i] = ins('store_brands', ['slug' => 'test-' . slugify($b), 'name' => $b . ' (Test)', 'is_featured' => $i < 4 ? 1 : 0, 'is_active' => 1]);
}

// ===========================================================================
// 3. Product templates per department
//    [name, axis, values|null, base MRP ₹ (first variant), HSN, GST bp, returnable, has_expiry, weight g, specs]
//    axis: pack | caps | flavour | weight | volume | size | colour | count | protein (flavour × weight) | null
// ===========================================================================

$AXES = [
    'pack' => ['test_pack_size', ['30 tablets', '60 tablets', '120 tablets'], [1, 1.8, 3.2]],
    'caps' => ['test_pack_size', ['30 capsules', '60 capsules', '90 capsules'], [1, 1.8, 2.5]],
    'count' => ['test_pack_size', ['Pack of 1', 'Pack of 2', 'Pack of 4'], [1, 1.9, 3.6]],
    'flavour' => ['test_flavour', ['Chocolate', 'Vanilla', 'Unflavoured'], [1, 1, 0.95]],
    'weight' => ['test_weight', ['250 g', '500 g', '1 kg'], [1, 1.8, 3.3]],
    'volume' => ['test_volume', ['100 ml', '200 ml', '500 ml'], [1, 1.7, 3.6]],
    'size' => ['test_size', ['S', 'M', 'L', 'XL'], [1, 1, 1.05, 1.1]],
    'colour' => ['test_colour', ['Black', 'Blue', 'Grey'], [1, 1, 1]],
];

$T = [
    'nutrition-supplements' => [
        ['Vitamin D3 2000 IU Tablets', 'pack', null, 399, '2106', 1800, 1, 1, 80, ['Form' => 'Tablet', 'Dosage' => '1 tablet daily after a meal']],
        ['Multivitamin for Men & Women', 'pack', null, 549, '2106', 1800, 1, 1, 90, ['Form' => 'Tablet', 'Key nutrients' => '24 vitamins & minerals']],
        ['Omega-3 Fish Oil 1000 mg', 'caps', null, 699, '2106', 1800, 1, 1, 120, ['Form' => 'Softgel', 'EPA/DHA' => '180/120 mg']],
        ['Vitamin B12 Methylcobalamin 1500 mcg', 'pack', null, 299, '2106', 1800, 1, 1, 60, ['Form' => 'Sublingual tablet']],
        ['Biotin 10000 mcg for Hair & Nails', 'caps', null, 499, '2106', 1800, 1, 1, 70, ['Form' => 'Veg capsule']],
        ['Calcium + Magnesium + Zinc', 'pack', null, 349, '2106', 1800, 1, 1, 110, ['Form' => 'Tablet']],
    ],
    'senior-wellness' => [
        ['Adjustable Aluminium Walking Stick', 'colour', null, 899, '9021', 500, 1, 0, 650, ['Material' => 'Aluminium', 'Height' => '72–94 cm']],
        ['Adult Diaper Pants', 'size', null, 649, '9619', 500, 0, 0, 900, ['Count' => '10 pants', 'Absorbency' => 'Up to 12 hours']],
        ['Weekly Pill Organiser (AM/PM)', 'colour', null, 249, '9018', 500, 1, 0, 150, ['Compartments' => '14']],
        ['Glucosamine + Chondroitin Joint Care', 'pack', null, 799, '2106', 1800, 1, 1, 120, ['Form' => 'Tablet']],
        ['Bath Grab Bar with Suction', null, null, 1299, '9021', 500, 1, 0, 700, ['Load' => 'Up to 80 kg']],
    ],
    'joint-bone-mobility' => [
        ['Knee Cap Support (Pair)', 'size', null, 499, '9021', 500, 1, 0, 180, ['Material' => 'Breathable knit']],
        ['Ortho Pain Relief Oil', 'volume', null, 245, '3004', 500, 1, 1, 150, ['Type' => 'Ayurvedic proprietary medicine']],
        ['Lumbar Support Belt', 'size', null, 899, '9021', 500, 1, 0, 400, ['Use' => 'Lower-back support']],
        ['Rubber Hot Water Bag 2 L', 'colour', null, 399, '9019', 500, 1, 0, 450, ['Capacity' => '2 litres']],
        ['Calcium Citrate + Vitamin D3', 'pack', null, 425, '2106', 1800, 1, 1, 100, ['Form' => 'Tablet']],
    ],
    'ayurveda-herbal' => [
        ['Ashwagandha KSM-66 Tablets', 'pack', null, 450, '3004', 500, 1, 1, 80, ['Type' => 'Ayurvedic', 'Extract' => '500 mg']],
        ['Triphala Churna', 'weight', null, 199, '3004', 500, 1, 1, 260, ['Type' => 'Ayurvedic powder']],
        ['Chyawanprash with Saffron', 'weight', null, 299, '3004', 500, 1, 1, 520, ['Type' => 'Ayurvedic']],
        ['Giloy Amla Juice', 'volume', null, 249, '3004', 500, 1, 1, 560, ['Type' => 'Ayurvedic juice']],
        ['Tulsi Drops 5-in-1', 'volume', null, 180, '3004', 500, 1, 1, 60, ['Type' => 'Ayurvedic drops']],
        ['Herbal Green Tea Tulsi Ginger', 'count', null, 225, '0902', 500, 1, 1, 90, ['Bags per pack' => '25']],
    ],
    'gut-digestive-health' => [
        ['Probiotic 30 Billion CFU', 'caps', null, 649, '2106', 1800, 1, 1, 60, ['Strains' => '10']],
        ['Psyllium Husk (Isabgol)', 'weight', null, 199, '2106', 500, 1, 1, 260, ['Fibre' => '85%']],
        ['Digestive Enzyme Complex', 'pack', null, 399, '2106', 1800, 1, 1, 70, ['Form' => 'Tablet']],
        ['Apple Cider Vinegar with Mother', 'volume', null, 349, '2106', 1800, 1, 1, 560, ['Acidity' => '5%']],
        ['Aloe Vera Juice', 'volume', null, 299, '2106', 1800, 1, 1, 1050, ['Aloin free' => 'Yes']],
    ],
    'hair-scalp' => [
        ['Onion Hair Oil with Bhringraj', 'volume', null, 349, '3305', 500, 1, 1, 150, ['Hair type' => 'All']],
        ['Anti-Dandruff Shampoo 1% Ketoconazole-free', 'volume', null, 299, '3305', 500, 1, 1, 230, ['Hair type' => 'Oily scalp']],
        ['Hair Growth Serum with Redensyl', 'volume', null, 799, '3304', 1800, 1, 1, 90, ['Use' => 'Night']],
        ['Biotin Hair Gummies', 'pack', ['30 gummies', '60 gummies'], 599, '2106', 1800, 1, 1, 150, ['Flavour' => 'Mixed berry']],
    ],
    'mother-baby' => [
        ['Baby Diaper Pants', 'size', null, 699, '9619', 500, 0, 0, 1200, ['Count' => '44 pants']],
        ['Baby Massage Oil', 'volume', null, 249, '3304', 1800, 1, 1, 130, ['Base' => 'Almond & olive']],
        ['Water Baby Wipes', 'count', null, 199, '3401', 500, 0, 1, 350, ['Wipes per pack' => '72']],
        ['Maternity Support Belt', 'size', null, 999, '9021', 500, 1, 0, 350, ['Trimester' => '2nd & 3rd']],
        ['Disposable Nursing Pads', 'count', null, 299, '9619', 500, 0, 0, 200, ['Pads per pack' => '24']],
        ['Gentle Baby Lotion', 'volume', null, 275, '3304', 1800, 1, 1, 230, ['Fragrance' => 'Mild']],
    ],
    'personal-hygiene' => [
        ['Hand Sanitizer 70% Alcohol', 'volume', null, 149, '3808', 1800, 1, 1, 230, ['Alcohol' => '70% v/v']],
        ['Liquid Hand Wash Refill', 'volume', null, 179, '3401', 500, 1, 1, 560, ['Fragrance' => 'Lemon']],
        ['Ultra-Thin Sanitary Pads XL', 'count', null, 199, '9619', 500, 0, 0, 250, ['Pads per pack' => '15']],
        ['3-Ply Surgical Face Masks', 'count', ['Box of 50', 'Box of 100'], 299, '6307', 500, 0, 0, 300, ['BFE' => '≥ 95%']],
        ['pH-Balanced Intimate Wash', 'volume', null, 249, '3401', 500, 1, 1, 130, ['pH' => '3.5']],
    ],
    'home-health-monitoring' => [
        ['Digital BP Monitor (Upper Arm)', null, null, 2499, '9018', 500, 1, 0, 700, ['Memory' => '2 × 60 readings', 'Warranty' => '5 years']],
        ['Fingertip Pulse Oximeter', null, null, 1499, '9018', 500, 1, 0, 120, ['Display' => 'OLED']],
        ['Digital Thermometer (Flexible Tip)', null, null, 249, '9025', 500, 1, 0, 40, ['Reading time' => '10 s']],
        ['Glucometer Starter Kit', null, null, 1299, '9027', 500, 1, 0, 300, ['Includes' => 'Meter, 10 strips, lancets']],
        ['Glucose Test Strips', 'count', ['50 strips', '100 strips'], 899, '3822', 500, 0, 1, 100, ['Compatible with' => 'Test Glucometer Kit']],
        ['Digital Weighing Scale', 'colour', null, 1199, '9018', 500, 1, 0, 1600, ['Capacity' => '180 kg']],
    ],
    'first-aid-home-care' => [
        ['Family First Aid Kit (60 items)', null, null, 799, '3006', 500, 1, 0, 600, ['Items' => '60']],
        ['Waterproof Adhesive Bandages', 'count', ['Pack of 50', 'Pack of 100'], 149, '3005', 500, 0, 1, 60, ['Sizes' => 'Assorted']],
        ['Elastic Crepe Bandage', 'size', ['8 cm', '10 cm', '15 cm'], 129, '3005', 500, 1, 0, 90, ['Length' => '4 m stretched']],
        ['Absorbent Cotton Roll', 'weight', ['100 g', '400 g'], 99, '3005', 500, 0, 0, 120, ['Grade' => 'IP']],
        ['Reusable Hot & Cold Gel Pack', null, null, 349, '9019', 500, 1, 0, 400, ['Use' => 'Microwave / freezer']],
    ],
    'fitness-sports-nutrition' => [
        ['Whey Protein Isolate', 'protein', null, 2499, '2106', 1800, 1, 1, 1100, ['Protein per scoop' => '25 g']],
        ['Creatine Monohydrate Micronised', 'weight', ['100 g', '250 g'], 599, '2106', 1800, 1, 1, 280, ['Serving' => '3 g']],
        ['Electrolyte Drink Mix', 'flavour', ['Lemon', 'Orange', 'Watermelon'], 349, '2202', 1800, 1, 1, 300, ['Sachets' => '20']],
        ['Resistance Bands Set (5 levels)', 'colour', null, 699, '9019', 500, 1, 0, 450, ['Levels' => '5']],
        ['Anti-Slip Yoga Mat 6 mm', 'colour', null, 999, '9019', 500, 1, 0, 1100, ['Thickness' => '6 mm']],
        ['Protein Shaker Bottle 700 ml', 'colour', null, 299, '9019', 500, 1, 0, 200, ['BPA free' => 'Yes']],
    ],
    'skin-personal-care' => [
        ['Vitamin C 10% Face Serum', 'volume', ['15 ml', '30 ml'], 599, '3304', 1800, 1, 1, 80, ['Skin type' => 'All']],
        ['Sunscreen SPF 50 PA++++', 'volume', ['50 ml', '100 ml'], 499, '3304', 1800, 1, 1, 90, ['Finish' => 'Matte']],
        ['Ceramide Moisturising Cream', 'volume', ['50 ml', '100 ml'], 449, '3304', 1800, 1, 1, 90, ['Skin type' => 'Dry']],
        ['Salicylic Acid Face Wash', 'volume', null, 349, '3304', 1800, 1, 1, 130, ['Skin type' => 'Oily / acne-prone']],
        ['SPF 15 Lip Balm', 'count', null, 149, '3304', 1800, 1, 1, 20, ['Flavour' => 'Strawberry']],
        ['Shea Body Lotion', 'volume', null, 299, '3304', 1800, 1, 1, 240, ['Skin type' => 'Normal to dry']],
    ],
    'oral-dental-care' => [
        ['Sensitive Relief Toothpaste', 'count', null, 145, '3306', 500, 1, 1, 120, ['Fluoride' => '1000 ppm']],
        ['Rechargeable Electric Toothbrush', 'colour', null, 1999, '9018', 500, 1, 0, 300, ['Modes' => '3', 'Battery' => '30 days']],
        ['Mint Waxed Dental Floss', 'count', null, 125, '3306', 500, 1, 1, 30, ['Length' => '50 m']],
        ['Alcohol-Free Mouthwash', 'volume', ['250 ml', '500 ml'], 199, '3306', 500, 1, 1, 280, ['Flavour' => 'Cool mint']],
        ['Copper Tongue Cleaner', 'count', null, 99, '3306', 500, 0, 0, 40, ['Material' => 'Pure copper']],
        ['Ultra-Soft Toothbrush', 'count', null, 99, '3306', 500, 0, 0, 25, ['Bristles' => 'Ultra soft']],
    ],
    'womens-health' => [
        ['Iron + Folic Acid + B12', 'pack', null, 299, '2106', 1800, 1, 1, 70, ['Form' => 'Tablet']],
        ['Medical-Grade Menstrual Cup', 'size', ['Small', 'Large'], 399, '9018', 500, 0, 0, 60, ['Material' => 'Silicone']],
        ['Period Cramp Heat Patches', 'count', null, 249, '3005', 500, 0, 1, 50, ['Heat' => 'Up to 8 hours']],
        ['Myo-Inositol PCOS Support', 'caps', null, 799, '2106', 1800, 1, 1, 90, ['Ratio' => '40:1']],
        ['Pregnancy Test Kit', 'count', null, 99, '3822', 500, 0, 1, 20, ['Accuracy' => '99%']],
    ],
    'sleep-stress' => [
        ['Melatonin 3 mg Sleep Gummies', 'pack', ['30 gummies', '60 gummies'], 499, '2106', 1800, 1, 1, 150, ['Flavour' => 'Blueberry']],
        ['Contoured Sleep Eye Mask', 'colour', null, 349, '6307', 500, 1, 0, 60, ['Material' => 'Memory foam']],
        ['Chamomile Herbal Tea', 'count', null, 249, '0902', 500, 1, 1, 60, ['Bags per pack' => '20']],
        ['Ultrasonic Aroma Diffuser', 'colour', null, 1499, '9019', 1800, 1, 0, 450, ['Tank' => '300 ml']],
        ['Lavender Essential Oil', 'volume', ['10 ml', '30 ml'], 299, '3304', 1800, 1, 1, 40, ['Purity' => '100%']],
    ],
    'mental-wellness' => [
        ['Brahmi Memory Support Tablets', 'pack', null, 299, '3004', 500, 1, 1, 70, ['Type' => 'Ayurvedic']],
        ['Stress Relief Roll-On', 'volume', ['8 ml', '15 ml'], 249, '3304', 1800, 1, 1, 30, ['Blend' => 'Lavender, peppermint']],
        ['Ashwagandha Calm Gummies', 'pack', ['30 gummies', '60 gummies'], 549, '2106', 1800, 1, 1, 150, ['Flavour' => 'Mango']],
        ['Magnesium Glycinate 400 mg', 'caps', null, 649, '2106', 1800, 1, 1, 90, ['Form' => 'Veg capsule']],
    ],
    'healthy-food' => [
        ['Rolled Oats High Fibre', 'weight', ['500 g', '1 kg'], 199, '2106', 500, 1, 1, 520, ['Fibre' => '10 g/100 g']],
        ['Raw Forest Honey', 'weight', ['250 g', '500 g'], 299, '0409', 500, 1, 1, 520, ['Processing' => 'Unheated']],
        ['Unsweetened Peanut Butter', 'weight', ['350 g', '1 kg'], 299, '2106', 1800, 1, 1, 400, ['Protein' => '30 g/100 g']],
        ['Millet Muesli No Added Sugar', 'weight', ['400 g', '750 g'], 349, '2106', 1800, 1, 1, 420, ['Millets' => 'Ragi, jowar, bajra']],
        ['Organic Chia Seeds', 'weight', ['200 g', '500 g'], 249, '2106', 500, 1, 1, 220, ['Omega-3' => '17 g/100 g']],
    ],
    'eye-care' => [
        ['Lubricating Eye Drops', 'volume', ['10 ml'], 199, '3004', 500, 0, 1, 30, ['Preservative' => 'Free']],
        ['Blue-Light Blocking Glasses', 'colour', null, 999, '9004', 500, 1, 0, 80, ['Lens' => 'Anti-glare']],
        ['Lutein + Zeaxanthin Eye Vitamins', 'caps', null, 699, '2106', 1800, 1, 1, 70, ['Lutein' => '20 mg']],
        ['Multipurpose Lens Solution', 'volume', ['120 ml', '360 ml'], 349, '3004', 500, 0, 1, 380, ['For' => 'Soft lenses']],
        ['Reading Glasses', 'size', ['+1.0', '+1.5', '+2.0', '+2.5'], 499, '9004', 500, 1, 0, 60, ['Frame' => 'Unisex']],
    ],
    'home-wellness' => [
        ['Steam Inhaler & Vaporiser', null, null, 699, '9019', 500, 1, 0, 400, ['Power' => '150 W']],
        ['Cool Mist Humidifier', 'colour', null, 1999, '9019', 1800, 1, 0, 1200, ['Tank' => '2.5 L']],
        ['Deep Tissue Massage Gun', 'colour', null, 3499, '9019', 1800, 1, 0, 900, ['Speeds' => '6']],
        ['Shiatsu Foot Massager', null, null, 4999, '9019', 1800, 1, 0, 4200, ['Heat' => 'Yes']],
        ['Acupressure Mat & Pillow Set', 'colour', null, 1299, '9019', 500, 1, 0, 800, ['Spikes' => '6210']],
    ],
    'mens-health' => [
        ['Testosterone Support Complex', 'caps', null, 999, '2106', 1800, 1, 1, 90, ['Form' => 'Veg capsule']],
        ['Beard Growth Oil', 'volume', ['30 ml', '50 ml'], 399, '3304', 1800, 1, 1, 60, ['Base' => 'Argan & jojoba']],
        ['Pure Himalayan Shilajit Resin', 'weight', ['10 g', '20 g'], 899, '3004', 500, 1, 1, 40, ['Type' => 'Ayurvedic']],
        ["Men's Daily Multivitamin", 'pack', null, 599, '2106', 1800, 1, 1, 90, ['Form' => 'Tablet']],
        ['Hair Fall Control Shampoo', 'volume', null, 349, '3305', 500, 1, 1, 230, ['Hair type' => 'Thinning hair']],
    ],
];

// ===========================================================================
// 4. Sellers
// ===========================================================================

$STATE_CODE = ['Gujarat' => '24', 'Kerala' => '32', 'Maharashtra' => '27', 'Delhi' => '07', 'Karnataka' => '29', 'Tamil Nadu' => '33',
    'Telangana' => '36', 'Rajasthan' => '08', 'Uttar Pradesh' => '09', 'West Bengal' => '19'];

// [slug, display name, business type, city, state, pincode, departments, status, featured, own commission bp, products, handling days, return days]
$V = [
    ['wellness-pharma', 'Test Wellness Pharma', 'pvt_ltd', 'Ahmedabad', 'Gujarat', '380015', ['nutrition-supplements', 'senior-wellness', 'joint-bone-mobility'], 'approved', 1, 1200, 15, 2, 7],
    ['ayur-naturals', 'Test Ayur Naturals', 'llp', 'Kochi', 'Kerala', '682016', ['ayurveda-herbal', 'gut-digestive-health', 'hair-scalp'], 'approved', 0, null, 14, 2, 7],
    ['baby-care-co', 'Test Baby Care Co', 'pvt_ltd', 'Mumbai', 'Maharashtra', '400053', ['mother-baby', 'personal-hygiene'], 'approved', 1, null, 12, 1, 7],
    ['surgicals', 'Test Surgicals & Devices', 'partnership', 'New Delhi', 'Delhi', '110005', ['home-health-monitoring', 'first-aid-home-care'], 'approved', 0, 800, 11, 2, 10],
    ['fitfuel', 'Test FitFuel Nutrition', 'pvt_ltd', 'Bengaluru', 'Karnataka', '560038', ['fitness-sports-nutrition', 'nutrition-supplements'], 'approved', 1, null, 12, 1, 7],
    ['skin-studio', 'Test Skin Studio', 'proprietorship', 'Pune', 'Maharashtra', '411001', ['skin-personal-care', 'hair-scalp'], 'approved', 0, null, 10, 2, 10],
    ['smile-oral-care', 'Test Smile Oral Care', 'proprietorship', 'Chennai', 'Tamil Nadu', '600017', ['oral-dental-care', 'personal-hygiene'], 'approved', 0, null, 11, 2, 7],
    ['her-health', 'Test Her Health', 'llp', 'Hyderabad', 'Telangana', '500034', ['womens-health', 'personal-hygiene'], 'approved', 0, null, 10, 2, 7],
    ['calm-mind', 'Test Calm Mind Co', 'proprietorship', 'Jaipur', 'Rajasthan', '302001', ['sleep-stress', 'mental-wellness'], 'approved', 0, null, 9, 3, 7],
    ['green-pantry', 'Test Green Pantry', 'partnership', 'Lucknow', 'Uttar Pradesh', '226001', ['healthy-food', 'gut-digestive-health'], 'approved', 0, null, 10, 3, 7],
    ['vision-plus', 'Test Vision Plus', 'proprietorship', 'Kolkata', 'West Bengal', '700016', ['eye-care', 'home-health-monitoring'], 'approved', 0, null, 10, 2, 7],
    ['home-wellness', 'Test Home Wellness', 'proprietorship', 'Surat', 'Gujarat', '395007', ['home-wellness', 'senior-wellness'], 'approved', 0, null, 10, 2, 7],
    ['mens-vitality', "Test Men's Vitality", 'pvt_ltd', 'Nagpur', 'Maharashtra', '440010', ['mens-health'], 'pending_review', 0, null, 5, 2, 7],
    ['herbal-roots', 'Test Herbal Roots', 'proprietorship', 'Rajkot', 'Gujarat', '360001', ['ayurveda-herbal'], 'rejected', 0, null, 4, 2, 7],
    ['quickmeds', 'Test QuickMeds', 'proprietorship', 'Delhi', 'Delhi', '110092', ['first-aid-home-care', 'personal-hygiene'], 'suspended', 0, null, 8, 2, 7],
];
$REASONS = [
    'rejected' => 'Test: GST certificate name does not match the PAN. Please re-upload.',
    'suspended' => 'Test: suspended after repeated late dispatches (3 SLA misses this month).',
];
$FIRST = ['Aarav', 'Diya', 'Kabir', 'Meera', 'Rohan', 'Isha', 'Arjun', 'Kavya', 'Vihaan', 'Ananya', 'Reyansh', 'Saanvi', 'Aditya', 'Pooja', 'Nikhil'];
$LAST = ['Shah', 'Nair', 'Mehta', 'Kapoor', 'Rao', 'Joshi', 'Iyer', 'Reddy', 'Sharma', 'Verma', 'Bose', 'Patel', 'Deshmukh', 'Solanki', 'Gupta'];

$crypto = StoreCrypto::isConfigured();
if (!$crypto) {
    out('  NOTE: STORE_DATA_KEY is not set in app/.env, so test bank accounts / PAN are skipped (payout screens will show "no bank account").');
}
$passwordHash = password_hash(SEED_PASSWORD, PASSWORD_DEFAULT);
$vendors = [];   // index => row info
foreach ($V as $i => [$slug, $name, $btype, $city, $state, $pin, $depts, $status, $featured, $commBp, $nProducts, $handling, $retDays]) {
    $n = $i + 1;
    $sc = $STATE_CODE[$state];
    $pan = sprintf('TSTP%s%04dQ', chr(65 + $i), 1000 + $n);
    $gstin = $slug === 'home-wellness' ? null : $sc . $pan . '1Z' . chr(65 + $i);   // one seller without GSTIN: bill of supply
    $contact = $FIRST[$i] . ' ' . $LAST[$i];
    $email = sprintf('test.seller%02d@%s', $n, T::EMAIL_DOMAIN);
    $phone = sprintf('+9190000001%02d', $n);   // seller contact numbers (not customer logins)
    $createdAt = ago(90 - $i * 2);
    $vid = ins('store_vendors', [
        'slug' => T::VENDOR_SLUG_PREFIX . $slug, 'display_name' => $name, 'legal_name' => $name . ($btype === 'pvt_ltd' ? ' Private Limited' : ($btype === 'llp' ? ' LLP' : '')),
        'business_type' => $btype, 'gstin' => $gstin, 'pan_enc' => $crypto ? StoreCrypto::encrypt($pan) : null, 'pan_last4' => substr($pan, -4),
        'contact_name' => $contact, 'email' => $email, 'phone' => $phone,
        'description' => "TEST SELLER — created by the store test seeder. {$name} ships from {$city}. Every product, order and payout here is test data.",
        'status' => $status, 'status_reason' => $REASONS[$status] ?? null, 'handling_days' => $handling, 'default_return_window_days' => $retDays,
        'is_featured' => $featured, 'submitted_at' => $status !== 'draft' ? dt($createdAt + 3600) : null,
        'approved_at' => in_array($status, ['approved', 'suspended'], true) ? dt($createdAt + 86400) : null,
        'created_at' => dt($createdAt),
    ]);
    $owner = ins('store_vendor_users', [
        'vendor_id' => $vid, 'name' => $contact, 'email' => $email, 'password_hash' => $passwordHash, 'role' => 'owner', 'status' => 'active',
        'email_verified_at' => dt($createdAt + 600), 'created_at' => dt($createdAt),
    ]);
    if (in_array($n, [1, 3], true)) {   // a staff login on two sellers (+ one disabled staff)
        ins('store_vendor_users', [
            'vendor_id' => $vid, 'name' => 'Staff of ' . $name, 'email' => sprintf('test.staff%02d@%s', $n, T::EMAIL_DOMAIN),
            'password_hash' => $passwordHash, 'role' => 'staff', 'status' => $n === 3 ? 'disabled' : 'active', 'email_verified_at' => dt($createdAt + 900),
        ]);
    }
    $addr = ['contact_name' => $contact, 'phone' => $phone, 'email' => $email, 'line1' => (10 + $n) . ', Test Industrial Estate, Phase ' . ($n % 3 + 1),
        'line2' => 'Near Test Circle', 'city' => $city, 'state' => $state, 'state_code' => $sc, 'pincode' => $pin];
    $pickupId = ins('store_vendor_addresses', $addr + ['vendor_id' => $vid, 'type' => 'pickup', 'is_default' => 1]);
    ins('store_vendor_addresses', $addr + ['vendor_id' => $vid, 'type' => 'return', 'is_default' => 1]);
    ins('store_vendor_addresses', $addr + ['vendor_id' => $vid, 'type' => 'registered', 'is_default' => 1, 'line1' => 'Regd. office: ' . $addr['line1']]);
    if ($crypto) {
        $acct = sprintf('9%010d', 50000 + $n * 7919);
        ins('store_vendor_bank_accounts', [
            'vendor_id' => $vid, 'holder_name' => $name, 'account_no_enc' => StoreCrypto::encrypt($acct), 'account_last4' => substr($acct, -4),
            'ifsc' => sprintf('HDFC0%06d', 100 + $n), 'bank_name' => 'HDFC Bank (test)', 'upi_id' => 'testseller' . $n . '@upi',
            'is_primary' => 1, 'status' => $n === 10 ? 'pending' : ($status === 'approved' || $status === 'suspended' ? 'verified' : 'pending'),
            'verified_at' => $status === 'approved' && $n !== 10 ? dt($createdAt + 2 * 86400) : null,
        ]);
    }
    // KYC documents: GST, PAN, cheque (+ licences below, once we know the categories).
    foreach (['gst_cert' => $gstin, 'pan' => $pan, 'cancelled_cheque' => null] as $type => $no) {
        if ($type === 'gst_cert' && $gstin === null) {
            continue;
        }
        ins('store_vendor_documents', [
            'vendor_id' => $vid, 'doc_type' => $type, 'doc_number' => $no, 'file_path' => 'test/' . $slug . '-' . $type . '.pdf',
            'original_name' => $type . '.pdf', 'status' => in_array($status, ['approved', 'suspended'], true) ? 'approved' : ($status === 'rejected' && $type === 'gst_cert' ? 'rejected' : 'pending'),
            'reject_reason' => $status === 'rejected' && $type === 'gst_cert' ? 'Test: name on the certificate does not match the PAN.' : null,
            'reviewed_at' => in_array($status, ['approved', 'suspended', 'rejected'], true) ? dt($createdAt + 86400) : null,
            'created_at' => dt($createdAt + 1800),
        ]);
    }
    if ($commBp !== null) {
        ins('store_commission_rules', ['scope' => 'vendor', 'vendor_id' => $vid, 'type' => 'percent', 'rate_bp' => $commBp, 'is_active' => 1]);
    }
    $vendors[$n] = ['id' => $vid, 'slug' => T::VENDOR_SLUG_PREFIX . $slug, 'name' => $name, 'status' => $status, 'owner' => $owner, 'email' => $email,
        'depts' => $depts, 'n_products' => $nProducts, 'brand' => $brandId[$i], 'brand_name' => $BRANDS[$i], 'pickup_id' => $pickupId, 'featured' => $featured,
        'ret_days' => $retDays, 'short' => $slug, 'docs' => []];
}
// Seller-specific commission: FitFuel pays 15% on sports nutrition only (vendor + category rule).
$fitDept = (int) q("SELECT id FROM store_categories WHERE parent_id = 0 AND slug = 'fitness-sports-nutrition'")->fetchColumn();
if ($fitDept) {
    ins('store_commission_rules', ['scope' => 'vendor_category', 'vendor_id' => $vendors[5]['id'], 'category_id' => $fitDept, 'type' => 'percent', 'rate_bp' => 1500, 'is_active' => 1]);
}

// ===========================================================================
// 5. Products + variants + images
// ===========================================================================

$imgDir = $base . '/public' . IMG_DIR_REL;
if (!is_dir($imgDir)) {
    @mkdir($imgDir, 0755, true);
}
$canDraw = function_exists('imagecreatetruecolor') && is_dir($imgDir) && is_writable($imgDir);
if (!$canDraw) {
    out('  NOTE: product images skipped (GD missing or ' . $imgDir . ' not writable).');
}

function drawImage(string $file, string $title, string $sub, int $seed): bool
{
    $w = 800;
    $im = imagecreatetruecolor($w, $w);
    $palette = [[15, 155, 110], [37, 99, 235], [219, 39, 119], [234, 88, 12], [124, 58, 237], [8, 145, 178], [101, 163, 13], [220, 38, 38]];
    [$r, $g, $b] = $palette[$seed % count($palette)];
    imagefill($im, 0, 0, imagecolorallocate($im, 248, 250, 252));
    imagefilledrectangle($im, 60, 60, $w - 60, $w - 60, imagecolorallocate($im, $r, $g, $b));
    $white = imagecolorallocate($im, 255, 255, 255);
    $lines = explode("\n", wordwrap($title, 22, "\n", true));
    $y = 300 - count($lines) * 18;
    foreach ($lines as $line) {
        imagestring($im, 5, (int) (($w - imagefontwidth(5) * strlen($line)) / 2), $y, $line, $white);
        $y += 36;
    }
    imagestring($im, 4, (int) (($w - imagefontwidth(4) * strlen($sub)) / 2), $y + 30, $sub, $white);
    imagestring($im, 3, (int) (($w - imagefontwidth(3) * 20) / 2), $w - 120, 'TEST PRODUCT IMAGE', $white);
    $ok = imagepng($im, $file);
    imagedestroy($im);

    return $ok;
}

$productIds = [];
$liveVariants = [];   // vendor n => list of variant ids buyable (live, active, in stock)
$docNeeds = [];       // vendor n => doc types needed by their categories
$pIndex = 0;
foreach ($vendors as $n => &$v) {
    $pool = [];
    foreach ($v['depts'] as $d) {
        foreach ($T[$d] ?? [] as $tpl) {
            $pool[] = [$d, $tpl];
        }
    }
    // Interleave departments, then take as many as this seller lists.
    usort($pool, static fn ($a, $b) => [array_search($a[1], $T[$a[0]], true), $a[0]] <=> [array_search($b[1], $T[$b[0]], true), $b[0]]);
    $pool = array_slice($pool, 0, $v['n_products']);
    $count = count($pool);
    foreach ($pool as $k => [$dept, $tpl]) {
        [$tname, $axis, $values, $mrp, $hsn, $gst, $returnable, $expiry, $weight, $specs] = $tpl;
        $pIndex++;
        // Status mix: most live; last = draft, second-last = pending review (sellers that are approved).
        $status = 'live';
        $reviewNote = null;
        if ($v['status'] === 'approved') {
            if ($k === $count - 1) {
                $status = 'draft';
            } elseif ($k === $count - 2) {
                $status = 'pending_review';
            } elseif ($k === $count - 3 && in_array($n, [2, 5, 9], true)) {
                $status = 'rejected';
                $reviewNote = 'Test: images must show the actual label and the FSSAI/licence number.';
            } elseif ($k === $count - 3 && $n === 4) {
                $status = 'disabled';
                $reviewNote = 'Test: disabled by admin after a customer complaint.';
            }
        } elseif ($v['status'] === 'pending_review') {
            $status = $k < 3 ? 'pending_review' : 'draft';
        } elseif ($v['status'] === 'rejected') {
            $status = 'draft';
        }   // suspended seller: products stay 'live' but are hidden because the seller is suspended
        $pool2 = $cats[$dept][$status === 'pending_review' && !empty($cats[$dept]['review']) ? 'review' : 'open'] ?? ($cats[$dept]['open'] ?? []);
        if (!$pool2) {
            continue;
        }
        $cat = $pool2[($pIndex * 7) % count($pool2)];
        $name = $v['brand_name'] . ' ' . $tname;
        $slug = 'test-' . $v['short'] . '-' . slugify($tname);
        $licenceDoc = in_array((string) $cat['required_vendor_doc'], ['fssai_license', 'ayush_license', 'medical_device_registration'], true) ? (string) $cat['required_vendor_doc'] : null;
        if ($cat['required_vendor_doc']) {
            $docNeeds[$n][(string) $cat['required_vendor_doc']] = true;
        }
        $discount = [0, 5, 10, 15, 20, 25, 30][$pIndex % 7];
        $created = ago(60 - ($pIndex % 50));
        $specRows = [];
        foreach ($specs + ['Country of origin' => 'India', 'Sold by' => $v['name']] as $label => $value) {
            $specRows[] = ['label' => $label, 'value' => $value];
        }
        $pid = ins('store_products', [
            'vendor_id' => $v['id'], 'category_id' => (int) $cat['id'], 'brand_id' => $v['brand'], 'slug' => $slug, 'name' => $name,
            'short_desc' => 'TEST PRODUCT: ' . $tname . ' from ' . $v['brand_name'] . '. ' . ($discount ? "Save {$discount}% on MRP." : 'Everyday price.'),
            'description' => '<p><strong>This is test data</strong> created by the store test seeder.</p><p>' . htmlspecialchars($tname)
                . ' by ' . htmlspecialchars($v['brand_name']) . '.</p><ul><li>Quality checked</li><li>Ships in ' . ($v['depts'] ? 2 : 3) . ' days</li><li>'
                . ($returnable ? 'Easy returns' : 'Not returnable (hygiene)') . '</li></ul>',
            'specs_json' => json_encode($specRows, JSON_UNESCAPED_UNICODE),
            'status' => $status, 'review_note' => $reviewNote,
            'regulatory_class' => (string) $cat['regulatory_class'],
            'license_number' => $licenceDoc !== null ? sprintf('TEST-%s-%04d', strtoupper(substr($licenceDoc, 0, 4)), 1000 + $n) : null,
            'hsn_code' => $hsn, 'gst_bp' => $gst, 'is_returnable' => $returnable, 'return_window_days' => $returnable && $pIndex % 9 === 0 ? 15 : null,
            'has_expiry' => $expiry, 'manufacturer' => $v['brand_name'] . ' Healthcare (test)', 'country_of_origin' => 'India',
            'is_featured' => $v['featured'] && $k < 2 ? 1 : 0,
            'seo_title' => $name . ' | eClinicPro Store (test)', 'seo_description' => 'Test listing for ' . $tname,
            'published_at' => $status === 'live' ? dt($created + 86400) : null, 'created_at' => dt($created),
        ]);
        $productIds[] = $pid;
        ins('store_product_categories', ['product_id' => $pid, 'category_id' => (int) $cat['id'], 'is_primary' => 1]);
        // Some products also appear in a second subcategory of the same department.
        $second = $pool2[($pIndex * 7 + 3) % count($pool2)];
        if ($pIndex % 3 === 0 && (int) $second['id'] !== (int) $cat['id']) {
            ins('store_product_categories', ['product_id' => $pid, 'category_id' => (int) $second['id'], 'is_primary' => 0]);
        }
        foreach (array_slice($concernsByCat[(int) $cat['id']] ?? [], 0, 1 + $pIndex % 2) as $cid) {
            ins('store_product_concerns', ['product_id' => $pid, 'concern_id' => $cid]);
        }
        // Product-level commission rule (fixed ₹25/unit) on one Ayur Naturals product.
        if ($n === 2 && $k === 0) {
            ins('store_commission_rules', ['scope' => 'product', 'vendor_id' => $v['id'], 'product_id' => $pid, 'type' => 'fixed', 'fixed_paise' => 2500, 'is_active' => 1]);
        }

        // ---- Variants ----
        $combos = [];   // list of [title, [attr code => value], multiplier]
        if ($axis === 'protein') {
            foreach (['Chocolate', 'Vanilla', 'Cookies & Cream'] as $fi => $fl) {
                foreach ([['1 kg', 1], ['2 kg', 1.85]] as [$wt, $m]) {
                    $combos[] = ["$fl / $wt", ['test_flavour' => $fl, 'test_weight' => $wt], $m];
                }
            }
        } elseif ($axis !== null) {
            [$code, $defVals, $mults] = $AXES[$axis];
            $vals = $values ?? $defVals;
            foreach ($vals as $vi => $val) {
                $combos[] = [$val, [$code => $val], $mults[$vi] ?? (1 + 0.8 * $vi)];
            }
        } else {
            $combos[] = [null, [], 1];
        }
        foreach ($combos as $vi => [$vtitle, $opts, $mult]) {
            $vMrp = max(49, (int) (round($mrp * $mult / 5) * 5));
            $vPrice = max(1, (int) floor($vMrp * (100 - $discount) / 100));
            $stock = 20 + (($pIndex * 37 + $vi * 11) % 130);
            $threshold = 5;
            $active = 1;
            if ($vi === 1 && $pIndex % 5 === 0) {
                $stock = 0;            // out of stock variant
            } elseif ($vi === 0 && $pIndex % 6 === 0) {
                $stock = 3;            // low stock (below the alert level)
            } elseif ($vi === count($combos) - 1 && count($combos) > 2 && $pIndex % 8 === 0) {
                $active = 0;           // variant switched off by the seller
            }
            $vid2 = ins('store_product_variants', [
                'product_id' => $pid, 'vendor_id' => $v['id'], 'sku' => sprintf('TST%02d-%03d-%d', $n, $k + 1, $vi + 1), 'title' => $vtitle,
                'mrp_paise' => rs($vMrp), 'price_paise' => rs($vPrice), 'stock_qty' => $stock, 'low_stock_threshold' => $threshold,
                'weight_g' => (int) round($weight * $mult), 'length_mm' => 150 + ($vi * 20), 'breadth_mm' => 90 + ($vi * 10), 'height_mm' => 60 + ($vi * 15),
                'barcode' => sprintf('890%010d', $pIndex * 10 + $vi), 'is_active' => $active, 'created_at' => dt($created),
            ]);
            foreach ($opts as $code => $val) {
                ins('store_variant_options', ['variant_id' => $vid2, 'attribute_value_id' => attrValue($code, $val)]);
            }
            if ($status === 'live' && $active && $stock >= 15 && $v['status'] === 'approved' && !(int) $cat['no_promotion']) {
                $liveVariants[$n][] = $vid2;
            }
            ins('store_inventory_movements', ['variant_id' => $vid2, 'delta' => $stock, 'reason' => 'manual', 'ref_type' => 'seed', 'actor_type' => 'vendor_user', 'actor_id' => $v['owner'], 'created_at' => dt($created)]);
        }

        // ---- Images: a cover for every product, a second one for some ----
        if ($canDraw) {
            foreach ([0, 1] as $ii) {
                if ($ii === 1 && $pIndex % 3 !== 0) {
                    break;
                }
                $file = $slug . ($ii ? '-2' : '') . '.png';
                if (drawImage($imgDir . '/' . $file, $name, $ii ? 'Back of pack' : ($combos[0][0] ?? $v['brand_name']), $pIndex + $ii)) {
                    ins('store_product_images', ['product_id' => $pid, 'path' => IMG_DIR_REL . '/' . $file, 'alt' => $name, 'width' => 800, 'height' => 800, 'sort_order' => $ii]);
                }
            }
        }
        ProductService::refreshDenormalized($pid);
    }
}
unset($v);

// Licences the sellers' categories require (+ one expired, one pending, one rejected for the KYC screens).
foreach ($vendors as $n => $v) {
    foreach (array_keys($docNeeds[$n] ?? []) as $type) {
        $ok = in_array($v['status'], ['approved', 'suspended'], true);
        ins('store_vendor_documents', [
            'vendor_id' => $v['id'], 'doc_type' => $type, 'doc_number' => sprintf('TEST-%s-%04d', strtoupper(substr($type, 0, 4)), 1000 + $n),
            'file_path' => 'test/' . $v['short'] . '-' . $type . '.pdf', 'original_name' => $type . '.pdf',
            'status' => $ok ? 'approved' : 'pending', 'valid_until' => date('Y-m-d', ago(-365)), 'reviewed_at' => $ok ? dt(ago(80)) : null,
        ]);
    }
}
ins('store_vendor_documents', ['vendor_id' => $vendors[5]['id'], 'doc_type' => 'fssai_license', 'doc_number' => 'TEST-FSSA-OLD5', 'file_path' => 'test/fitfuel-old-fssai.pdf',
    'original_name' => 'old-fssai.pdf', 'status' => 'expired', 'valid_until' => date('Y-m-d', ago(20)), 'reviewed_at' => dt(ago(400))]);
ins('store_vendor_documents', ['vendor_id' => $vendors[8]['id'], 'doc_type' => 'trade_license', 'doc_number' => 'TEST-TRADE-0008', 'file_path' => 'test/her-health-trade.pdf',
    'original_name' => 'trade.pdf', 'status' => 'pending']);
ins('store_vendor_documents', ['vendor_id' => $vendors[7]['id'], 'doc_type' => 'other', 'doc_number' => null, 'file_path' => 'test/smile-other.pdf',
    'original_name' => 'shop-photo.pdf', 'status' => 'rejected', 'reject_reason' => 'Test: photo is blurred, please upload again.', 'reviewed_at' => dt(ago(5))]);

// ===========================================================================
// 6. Customers (patients) + addresses, carts, wishlists
// ===========================================================================

$CUSTOMERS = [
    ['Priya Sharma', 'F', 'Ahmedabad', 'Gujarat', '380054'], ['Rahul Verma', 'M', 'Mumbai', 'Maharashtra', '400076'],
    ['Sneha Iyer', 'F', 'Chennai', 'Tamil Nadu', '600040'], ['Amit Patel', 'M', 'Surat', 'Gujarat', '395009'],
    ['Neha Kapoor', 'F', 'New Delhi', 'Delhi', '110017'], ['Vikram Singh', 'M', 'Jaipur', 'Rajasthan', '302017'],
    ['Anjali Nair', 'F', 'Kochi', 'Kerala', '682020'], ['Karan Mehta', 'M', 'Pune', 'Maharashtra', '411045'],
    ['Pooja Reddy', 'F', 'Hyderabad', 'Telangana', '500081'], ['Siddharth Rao', 'M', 'Bengaluru', 'Karnataka', '560066'],
    ['Riya Bose', 'F', 'Kolkata', 'West Bengal', '700091'], ['Manish Gupta', 'M', 'Lucknow', 'Uttar Pradesh', '226010'],
    ['Kavita Joshi', 'F', 'Vadodara', 'Gujarat', '390007'], ['Arjun Desai', 'M', 'Nagpur', 'Maharashtra', '440022'],
    ['Divya Menon', 'F', 'Thiruvananthapuram', 'Kerala', '695004'], ['Rohit Malhotra', 'M', 'Gurugram', 'Delhi', '110037'],
    ['Aishwarya Pillai', 'F', 'Coimbatore', 'Tamil Nadu', '641018'], ['Sanjay Kumar', 'M', 'Noida', 'Uttar Pradesh', '201301'],
    ['Meghna Das', 'F', 'Howrah', 'West Bengal', '711101'], ['Tarun Chawla', 'M', 'Udaipur', 'Rajasthan', '313001'],
    ['Nisha Agarwal', 'F', 'Rajkot', 'Gujarat', '360005'], ['Harsh Vora', 'M', 'Mysuru', 'Karnataka', '570017'],
    ['Lakshmi Krishnan', 'F', 'Madurai', 'Tamil Nadu', '625020'], ['Farhan Qureshi', 'M', 'Hyderabad', 'Telangana', '500034'],
    ['Blocked Customer (Test)', 'M', 'Mumbai', 'Maharashtra', '400001'],
];
$cust = [];   // index 1..25 => [id, name, phone, email, address row]
foreach ($CUSTOMERS as $i => [$name, $g, $city, $state, $pin]) {
    $n = $i + 1;
    $phone = sprintf('%s%02d', T::PHONE_PREFIX, $n);
    $email = sprintf('test.patient%02d@%s', $n, T::EMAIL_DOMAIN);
    [$first] = explode(' ', $name);
    $created = ago(120 - $n * 3);
    $iid = ins('patient_identities', [
        'phone' => $phone, 'email' => $email, 'phone_verified_at' => dt($created), 'whatsapp_status' => 'no',
        'name' => $name, 'first_name' => $first, 'last_name' => trim(substr($name, strlen($first))) ?: null,
        'dob' => date('Y-m-d', ago(365 * (22 + ($n * 7) % 45))), 'gender' => $g, 'source' => 'self_signup', 'is_active' => 1,
        'address_line1' => (100 + $n) . ', Test Residency', 'address_city' => $city, 'address_state' => $state, 'address_postal_code' => $pin,
        'address_country' => 'IN', 'created_at' => dt($created),
    ]);
    $home = [
        'identity_id' => $iid, 'label' => 'Home', 'name' => $name, 'phone' => $phone, 'line1' => (100 + $n) . ', Test Residency, Sector ' . ($n % 9 + 1),
        'line2' => 'Test Road', 'landmark' => $n % 2 ? 'Opp. Test Park' : null, 'city' => $city, 'state' => $state, 'state_code' => $STATE_CODE[$state] ?? null,
        'pincode' => $pin, 'country' => 'IN', 'is_default' => 1, 'created_at' => dt($created),
    ];
    if ($n % 4 === 0) {   // a second (office) address for some customers
        ins('store_addresses', ['label' => 'Office', 'line1' => 'Floor ' . ($n % 7 + 1) . ', Test Tech Park', 'is_default' => 0] + $home);
    }
    $home['id'] = ins('store_addresses', $home);
    $cust[$n] = ['id' => $iid, 'name' => $name, 'phone' => $phone, 'email' => $email, 'addr' => $home];
}
ins('store_customer_flags', ['identity_id' => $cust[25]['id'], 'is_blocked' => 1, 'blocked_reason' => 'Test: repeated COD refusals / chargebacks.', 'notes' => 'Test data']);

// ===========================================================================
// 7. Coupons (all restricted to TEST sellers so real products are never discounted)
// ===========================================================================

$coupons = [];
foreach ([
    ['TESTSAVE10', 'percent', 1000, 15000, 29900, 'platform', 1, null, 30, 200],   // 10% up to ₹150 on ₹299+, Wellness Pharma
    ['TESTFLAT50', 'fixed', 5000, null, 49900, 'platform', 3, null, 30, 100],      // ₹50 off ₹499+, Baby Care
    ['TESTFREESHIP', 'free_shipping', 0, null, 0, 'platform', 7, null, 30, null],  // free delivery, Smile Oral Care
    ['TESTSELLER15', 'percent', 1500, 20000, 0, 'vendor', 6, null, 30, 50],        // 15% funded by Skin Studio
    ['TESTEXPIRED', 'percent', 2000, null, 0, 'platform', 1, null, -7, null],      // already ended (error message test)
] as [$code, $type, $value, $max, $min, $fundedBy, $vn, $cat, $days, $limit]) {
    $coupons[$code] = ins('store_coupons', [
        'code' => $code, 'type' => $type, 'value' => $value, 'max_discount_paise' => $max, 'min_order_paise' => $min, 'funded_by' => $fundedBy,
        'vendor_id' => $vendors[$vn]['id'], 'category_id' => $cat, 'starts_at' => dt(ago(30)), 'ends_at' => dt(ago(-$days)),
        'limit_total' => $limit, 'limit_per_customer' => 3, 'is_active' => 1,
    ]);
}

// Carts + wishlists (for the cart / wishlist pages).
foreach ([1 => [[1, 0, 2], [3, 1, 1]], 2 => [[6, 0, 1]], 5 => [[5, 2, 1], [10, 0, 3]]] as $cn => $lines) {
    $cartId = ins('store_carts', ['token' => bin2hex(random_bytes(32)), 'identity_id' => $cust[$cn]['id'], 'coupon_code' => $cn === 2 ? 'TESTSELLER15' : null,
        'expires_at' => dt(ago(-30))]);
    foreach ($lines as [$vn, $pick, $qty]) {
        $varId = $liveVariants[$vn][$pick] ?? null;
        if ($varId) {
            $price = (int) q('SELECT price_paise FROM store_product_variants WHERE id = :v', ['v' => $varId])->fetchColumn();
            ins('store_cart_items', ['cart_id' => $cartId, 'variant_id' => $varId, 'qty' => $qty, 'price_seen_paise' => $cn === 5 ? $price + 2000 : $price]);
        }
    }
}
foreach ([1, 2, 3, 4, 9] as $cn) {
    foreach (array_slice($productIds, $cn * 3, 3) as $pid) {
        q('INSERT IGNORE INTO store_wishlist (identity_id, product_id) VALUES (:i, :p)', ['i' => $cust[$cn]['id'], 'p' => $pid]);
    }
}

// ===========================================================================
// 8. Orders — built exactly like CheckoutService (priced by PricingService)
// ===========================================================================

$orderSeq = 0;
$refundSeq = 0;
$returnSeq = 0;
$payoutSeq = 0;
$FORWARD_EVENTS = [   // [raw status, internal, rank, location, activity, hours after pickup booking]
    ['AWB ASSIGNED', 'awb_assigned', 20, 'Seller city', 'AWB assigned', 0],
    ['PICKUP SCHEDULED', 'pickup_scheduled', 30, 'Seller city', 'Pickup scheduled for tomorrow', 2],
    ['PICKED UP', 'picked_up', 40, 'Seller city', 'Shipment picked up', 20],
    ['IN TRANSIT', 'in_transit', 50, 'Regional hub', 'In transit to destination hub', 36],
    ['REACHED AT DESTINATION HUB', 'in_transit', 50, 'Destination hub', 'Reached destination hub', 60],
    ['OUT FOR DELIVERY', 'out_for_delivery', 60, 'Customer city', 'Out for delivery', 70],
    ['DELIVERED', 'delivered', 100, 'Customer city', 'Delivered to customer', 76],
];

/** Cart-shaped rows for [variant_id => qty] (same SELECT as CartService::items). */
function cartRows(array $lines): array
{
    $ids = idList(array_keys($lines));
    $rows = q(
        "SELECT sv.id AS variant_id, sv.sku, sv.title AS variant_title, sv.mrp_paise, sv.price_paise, sv.weight_g,
                p.id AS product_id, p.slug, p.name, p.gst_bp, p.hsn_code, p.category_id, pc.parent_id AS dept_id, pc.no_promotion,
                v.id AS vendor_id, v.display_name AS vendor_name, v.slug AS vendor_slug,
                (SELECT i.path FROM store_product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
           FROM store_product_variants sv JOIN store_products p ON p.id = sv.product_id JOIN store_vendors v ON v.id = p.vendor_id
           LEFT JOIN store_categories pc ON pc.id = p.category_id
          WHERE sv.id IN ($ids) ORDER BY v.display_name, sv.id"
    )->fetchAll();
    foreach ($rows as &$r) {
        $r['qty'] = $lines[(int) $r['variant_id']];
        $r['problem'] = null;
    }

    return $rows;
}

/**
 * Place an order (pending_payment, stock reserved) — mirrors CheckoutService::place.
 *
 * @param list<array{0:int,1:int,2:int}> $picks [vendor n, variant pick index, qty]
 * @return array<string, mixed> order row + ['vos' => vendor_id => vo row, 'items' => rows]
 */
function placeOrder(int $custN, array $picks, ?string $coupon, int $at, string $source = 'web'): array
{
    global $cust, $liveVariants, $orderSeq, $coupons;
    $lines = [];
    foreach ($picks as [$vn, $pick, $qty]) {
        $list = $liveVariants[$vn] ?? [];
        if (!$list) {
            continue;
        }
        $varId = $list[$pick % count($list)];
        $lines[$varId] = ($lines[$varId] ?? 0) + $qty;
    }
    $items = cartRows($lines);
    $couponRow = $coupon !== null ? q('SELECT * FROM store_coupons WHERE id = :c', ['c' => $coupons[$coupon]])->fetch() : null;
    $quote = PricingService::quote($items, $couponRow);
    $applied = $quote['coupon'] !== null && $quote['coupon']['applied'];
    $orderSeq++;
    $orderNo = 'ECS' . date('ymd', $at) . '-TST' . sprintf('%03d', $orderSeq);   // real format, see StoreTestData::ORDER_NO_REGEXP
    $c = $cust[$custN];
    $ship = AddressService::snapshot($c['addr']);
    $orderId = ins('store_orders', [
        'order_no' => $orderNo, 'identity_id' => $c['id'], 'status' => 'pending_payment', 'payment_status' => 'pending',
        'contact_name' => $c['name'], 'contact_phone' => $c['phone'], 'contact_email' => $c['email'],
        'ship_address_json' => json_encode($ship, JSON_UNESCAPED_UNICODE), 'bill_address_json' => json_encode($ship, JSON_UNESCAPED_UNICODE),
        'items_subtotal_paise' => $quote['subtotal'], 'discount_paise' => $quote['discount'],
        'coupon_id' => $applied ? (int) $quote['coupon']['id'] : null, 'coupon_code' => $applied ? (string) $quote['coupon']['code'] : null,
        'shipping_paise' => $quote['shipping'], 'tax_included_paise' => $quote['tax_included'], 'platform_fee_paise' => 0,
        'grand_total_paise' => $quote['grand_total'], 'client_total_paise' => $quote['grand_total'],
        'checkout_key' => sprintf('%08x-%04x-4%03x-a%03x-%012x', $orderSeq, mt_rand(0, 0xffff), mt_rand(0, 0xfff), mt_rand(0, 0xfff), mt_rand(0, 0xffffffffffff)),
        'expires_at' => dt($at + max(5, StoreSettings::int('store_payment_window_minutes', 30)) * 60),
        'placed_at' => dt($at), 'source' => $source, 'user_agent' => 'store_test_seed',
    ]);
    $vos = [];
    foreach ($quote['groups'] as $i => $g) {
        $pickup = q(
            "SELECT id, contact_name, phone, line1, line2, city, state, pincode, sr_pickup_name FROM store_vendor_addresses
              WHERE vendor_id = :v AND type = 'pickup' AND is_active = 1 ORDER BY is_default DESC, id DESC LIMIT 1",
            ['v' => (int) $g['vendor_id']]
        )->fetch() ?: null;
        $voId = ins('store_vendor_orders', [
            'sub_order_no' => $orderNo . '-' . chr(65 + $i), 'order_id' => $orderId, 'vendor_id' => (int) $g['vendor_id'], 'status' => 'pending_payment',
            'items_subtotal_paise' => $g['subtotal'], 'vendor_discount_paise' => $g['vendor_discount'], 'platform_discount_paise' => $g['platform_discount'],
            'shipping_paise' => $g['shipping'], 'tax_included_paise' => $g['tax'], 'commission_paise' => $g['commission'],
            'commission_gst_paise' => $g['commission_gst'], 'vendor_payable_paise' => $g['vendor_payable'],
            'pickup_address_id' => $pickup['id'] ?? null, 'pickup_snapshot_json' => $pickup ? json_encode($pickup, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => dt($at),
        ]);
        foreach ($g['lines'] as $l) {
            ins('store_order_items', [
                'order_id' => $orderId, 'vendor_order_id' => $voId, 'vendor_id' => (int) $g['vendor_id'], 'product_id' => (int) $l['product_id'],
                'variant_id' => (int) $l['variant_id'], 'sku' => $l['sku'], 'name' => mb_substr((string) $l['name'], 0, 255),
                'variant_title' => $l['variant_title'], 'image_path' => $l['cover'], 'category_id' => (int) $l['category_id'],
                'hsn_code' => $l['hsn_code'], 'gst_bp' => (int) $l['gst_bp'], 'qty' => (int) $l['qty'], 'mrp_paise' => (int) $l['mrp_paise'],
                'unit_price_paise' => $l['unit_price_paise'], 'line_subtotal_paise' => $l['line_subtotal_paise'],
                'vendor_discount_paise' => $l['vendor_discount_paise'], 'platform_discount_paise' => $l['platform_discount_paise'],
                'tax_included_paise' => $l['tax_included_paise'], 'line_total_paise' => $l['line_total_paise'],
                'commission_type' => $l['commission_type'], 'commission_rate_bp' => $l['commission_rate_bp'], 'commission_rule_id' => $l['commission_rule_id'],
                'commission_paise' => $l['commission_paise'], 'vendor_payable_paise' => $l['vendor_payable_paise'],
            ]);
        }
        $vos[(int) $g['vendor_id']] = $voId;
    }
    foreach ($items as $it) {   // reserve stock
        q('UPDATE store_product_variants SET reserved_qty = reserved_qty + :q WHERE id = :v', ['q' => (int) $it['qty'], 'v' => (int) $it['variant_id']]);
        ins('store_inventory_movements', ['variant_id' => (int) $it['variant_id'], 'delta' => -(int) $it['qty'], 'reason' => 'reserve', 'ref_type' => 'order', 'ref_id' => $orderId, 'actor_type' => 'system', 'created_at' => dt($at)]);
    }
    hist($orderId, null, 'order', $orderId, null, 'pending_payment', 'customer', $c['id'], 'Order placed', $at);
    $payId = ins('store_payments', [
        'order_id' => $orderId, 'gateway' => 'razorpay', 'rzp_order_id' => sprintf('order_TEST%06d', $orderSeq), 'amount_paise' => $quote['grand_total'],
        'currency' => 'INR', 'status' => 'created', 'created_at' => dt($at + 20),
    ]);

    return ['id' => $orderId, 'no' => $orderNo, 'cust' => $custN, 'identity' => $c['id'], 'vos' => $vos, 'pay' => $payId, 'total' => $quote['grand_total'], 'at' => $at, 'coupon' => $applied ? $quote['coupon'] : null, 'discount' => $quote['discount']];
}

/** Release an unpaid order — mirrors OrderService::release. */
function releaseUnpaid(array $o, string $to, string $note, int $at, string $actor = 'system'): void
{
    q("UPDATE store_orders SET status = :s, cancelled_at = :t WHERE id = :id", ['s' => $to, 't' => dt($at), 'id' => $o['id']]);
    foreach (q('SELECT variant_id, qty FROM store_order_items WHERE order_id = :o', ['o' => $o['id']])->fetchAll() as $it) {
        q('UPDATE store_product_variants SET reserved_qty = IF(reserved_qty > :q, reserved_qty - :q2, 0) WHERE id = :v', ['q' => (int) $it['qty'], 'q2' => (int) $it['qty'], 'v' => (int) $it['variant_id']]);
        ins('store_inventory_movements', ['variant_id' => (int) $it['variant_id'], 'delta' => (int) $it['qty'], 'reason' => 'release', 'ref_type' => 'order', 'ref_id' => $o['id'], 'actor_type' => 'system', 'created_at' => dt($at)]);
    }
    q("UPDATE store_vendor_orders SET status = :s, cancel_reason = :r, cancelled_by_type = :t WHERE order_id = :o AND status = 'pending_payment'", [
        's' => $to === 'expired' ? 'auto_cancelled' : 'cancelled_by_customer', 'r' => $note, 't' => $actor === 'customer' ? 'customer' : 'system', 'o' => $o['id'],
    ]);
    hist($o['id'], null, 'order', $o['id'], 'pending_payment', $to, $actor, $actor === 'customer' ? $o['identity'] : null, $note, $at);
}

/** Payment captured — mirrors StorePaymentService::markPaid (without the email). */
function pay(array $o, int $at, string $method = 'upi', ?int $acceptBy = null): void
{
    global $orderSeq;
    $payRef = sprintf('pay_TEST%06d', $o['pay']);
    q("UPDATE store_payments SET rzp_payment_id = :r, status = 'captured', method = :m, captured_at = :t WHERE id = :id",
        ['r' => $payRef, 'm' => $method, 't' => dt($at), 'id' => $o['pay']]);
    ins('store_payment_transactions', ['payment_id' => $o['pay'], 'type' => 'capture', 'gateway_ref' => $payRef, 'amount_paise' => $o['total'], 'status' => 'captured',
        'payload' => json_encode(['id' => $payRef, 'method' => $method, 'test' => true]), 'created_at' => dt($at)]);
    foreach (q('SELECT variant_id, qty, product_id FROM store_order_items WHERE order_id = :o', ['o' => $o['id']])->fetchAll() as $r) {
        $qn = (int) $r['qty'];
        q('UPDATE store_product_variants SET stock_qty = IF(stock_qty > :q1, stock_qty - :q2, 0), reserved_qty = IF(reserved_qty > :q3, reserved_qty - :q4, 0) WHERE id = :v',
            ['q1' => $qn - 1, 'q2' => $qn, 'q3' => $qn - 1, 'q4' => $qn, 'v' => (int) $r['variant_id']]);
        ins('store_inventory_movements', ['variant_id' => (int) $r['variant_id'], 'delta' => -$qn, 'reason' => 'commit', 'ref_type' => 'order', 'ref_id' => $o['id'], 'actor_type' => 'system', 'created_at' => dt($at)]);
        q('UPDATE store_products SET sold_count = sold_count + :q WHERE id = :p', ['q' => $qn, 'p' => (int) $r['product_id']]);
    }
    q("UPDATE store_orders SET status = 'paid', payment_status = 'paid', paid_at = :t WHERE id = :id", ['t' => dt($at), 'id' => $o['id']]);
    $sla = max(1, StoreSettings::int('store_vendor_accept_sla_hours', 48));
    q("UPDATE store_vendor_orders SET status = 'new', accept_by = :ab WHERE order_id = :o", ['ab' => dt($acceptBy ?? ($at + $sla * 3600)), 'o' => $o['id']]);
    hist($o['id'], null, 'order', $o['id'], 'pending_payment', 'paid', 'webhook', null, "Payment $payRef captured ($method)", $at);
    if ($o['coupon'] !== null) {
        q('INSERT IGNORE INTO store_coupon_redemptions (coupon_id, order_id, identity_id, discount_paise, created_at) VALUES (:c, :o, :i, :d, :t)',
            ['c' => (int) $o['coupon']['id'], 'o' => $o['id'], 'i' => $o['identity'], 'd' => $o['discount'], 't' => dt($at)]);
        q('UPDATE store_coupons SET used_count = used_count + 1 WHERE id = :c', ['c' => (int) $o['coupon']['id']]);
    }
}

/** Parent order status from its packages — mirrors OrderService::recomputeStatus, with a timestamp. */
function recompute(int $orderId, int $at): void
{
    $o = q('SELECT status FROM store_orders WHERE id = :id', ['id' => $orderId])->fetch();
    if (!$o || in_array($o['status'], ['pending_payment', 'expired', 'payment_failed'], true)) {
        return;
    }
    $statuses = q('SELECT status FROM store_vendor_orders WHERE order_id = :o', ['o' => $orderId])->fetchAll(PDO::FETCH_COLUMN);
    $live = array_values(array_diff($statuses, ['cancelled_by_customer', 'cancelled_by_vendor', 'cancelled_by_admin', 'auto_cancelled', 'rto', 'lost_in_transit']));
    $count = static fn (array $want) => count(array_intersect($live, $want));
    if (!$live) {
        $to = 'cancelled';
    } elseif ($count(['delivered', 'completed']) === count($live)) {
        $to = $count(['completed']) === count($live) ? 'completed' : 'delivered';
    } elseif ($count(['delivered', 'completed']) > 0) {
        $to = 'partially_delivered';
    } elseif ($count(['shipped']) === count($live)) {
        $to = 'shipped';
    } elseif ($count(['shipped']) > 0) {
        $to = 'partially_shipped';
    } else {
        $to = 'paid';
    }
    if ($to !== $o['status']) {
        q('UPDATE store_orders SET status = :s' . ($to === 'cancelled' ? ', cancelled_at = :t' : '') . ' WHERE id = :id',
            ['s' => $to, 'id' => $orderId] + ($to === 'cancelled' ? ['t' => dt($at)] : []));
        hist($orderId, null, 'order', $orderId, (string) $o['status'], $to, 'system', null, '', $at);
    }
}

/**
 * Move one package forward to $target, with timestamps from $at.
 * targets: new | accepted | packed | ready_to_ship | in_transit | out_for_delivery | delivered | completed | rto | lost
 */
function advance(array $o, int $voId, string $target, int $at, ?int $courierPaise = null): int
{
    global $FORWARD_EVENTS, $vendors;
    $vo = q('SELECT * FROM store_vendor_orders WHERE id = :id', ['id' => $voId])->fetch();
    $owner = null;
    foreach ($vendors as $v) {
        if ((int) $v['id'] === (int) $vo['vendor_id']) {
            $owner = $v;
        }
    }
    $order = ['new' => 0, 'accepted' => 1, 'packed' => 2, 'ready_to_ship' => 3, 'in_transit' => 4, 'out_for_delivery' => 5, 'delivered' => 6, 'completed' => 7, 'rto' => 5, 'lost' => 4];
    $lvl = $order[$target];
    $t = $at;
    if ($lvl >= 1) {
        $t += 3 * 3600;
        q("UPDATE store_vendor_orders SET status = 'accepted', accepted_at = :t WHERE id = :id", ['t' => dt($t), 'id' => $voId]);
        hist($o['id'], $voId, 'vendor_order', $voId, 'new', 'accepted', 'vendor_user', $owner['owner'], 'Accepted by seller', $t);
    }
    if ($lvl >= 2) {
        $t += 5 * 3600;
        q("UPDATE store_vendor_orders SET status = 'packed', packed_at = :t WHERE id = :id", ['t' => dt($t), 'id' => $voId]);
        hist($o['id'], $voId, 'vendor_order', $voId, 'accepted', 'packed', 'vendor_user', $owner['owner'], 'Packed by seller', $t);
    }
    if ($lvl >= 3) {
        // Courier booked (Shiprocket-shaped shipment, fake AWB; never polled: next_poll_at NULL).
        $t += 3600;
        $w = (int) q('SELECT COALESCE(SUM(oi.qty * sv.weight_g), 0) FROM store_order_items oi JOIN store_product_variants sv ON sv.id = oi.variant_id WHERE oi.vendor_order_id = :v', ['v' => $voId])->fetchColumn();
        $orderRow = q('SELECT ship_address_json FROM store_orders WHERE id = :id', ['id' => $o['id']])->fetch();
        $sid = ins('store_shipments', [
            'shipment_no' => $vo['sub_order_no'] . '-S1', 'direction' => 'forward', 'order_id' => $o['id'], 'vendor_order_id' => $voId, 'vendor_id' => (int) $vo['vendor_id'],
            'status' => 'pickup_scheduled', 'status_rank' => 30, 'awb_code' => sprintf('TESTAWB%07d', $voId), 'courier_id' => 99, 'courier_name' => 'Delhivery Surface (test)',
            'pickup_snapshot_json' => $vo['pickup_snapshot_json'], 'delivery_snapshot_json' => $orderRow['ship_address_json'],
            'weight_g' => max(100, $w), 'length_mm' => 250, 'breadth_mm' => 180, 'height_mm' => 120, 'declared_value_paise' => (int) $vo['items_subtotal_paise'],
            'actual_charge_paise' => $courierPaise, 'pickup_scheduled_for' => date('Y-m-d', $t + 86400), 'last_raw_status' => 'PICKUP SCHEDULED', 'last_event_at' => dt($t),
            'created_at' => dt($t),
        ]);
        foreach (q('SELECT id, qty FROM store_order_items WHERE vendor_order_id = :v', ['v' => $voId])->fetchAll() as $it) {
            ins('store_shipment_items', ['shipment_id' => $sid, 'order_item_id' => (int) $it['id'], 'qty' => (int) $it['qty']]);
        }
        q("UPDATE store_vendor_orders SET status = 'ready_to_ship' WHERE id = :id", ['id' => $voId]);
        hist($o['id'], $voId, 'vendor_order', $voId, 'packed', 'ready_to_ship', 'vendor_user', $owner['owner'], 'Courier pickup booked', $t);
        $stopAt = ['ready_to_ship' => 'pickup_scheduled', 'in_transit' => 'REACHED AT DESTINATION HUB', 'lost' => 'IN TRANSIT', 'out_for_delivery' => 'OUT FOR DELIVERY', 'rto' => 'OUT FOR DELIVERY',
            'delivered' => 'DELIVERED', 'completed' => 'DELIVERED'][$target];
        $booked = $t;
        foreach ($FORWARD_EVENTS as [$raw, $internal, $rank, $loc, $act, $h]) {
            $et = $booked + $h * 3600;
            ins('store_shipment_events', ['shipment_id' => $sid, 'raw_status' => $raw, 'internal_status' => $internal, 'location' => $loc, 'activity' => $act,
                'event_at' => dt($et), 'source' => 'webhook', 'dedupe_key' => hash('sha256', 'TESTAWB' . $voId . '|' . $raw . '|' . $et)]);
            $upd = ['status' => $internal, 'status_rank' => $rank, 'last_raw_status' => $raw, 'last_event_at' => dt($et)];
            if ($internal === 'picked_up') {
                $upd['picked_up_at'] = dt($et);
                q("UPDATE store_vendor_orders SET status = 'shipped', shipped_at = :t WHERE id = :id", ['t' => dt($et), 'id' => $voId]);
                hist($o['id'], $voId, 'vendor_order', $voId, 'ready_to_ship', 'shipped', 'webhook', null, 'Courier: picked_up', $et);
            }
            if ($internal === 'delivered') {
                $upd['delivered_at'] = dt($et);
            }
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update($upd);
            $t = $et;
            if ($raw === $stopAt || $internal === $stopAt) {
                break;
            }
        }
        TaxDocumentService::issueInvoice($voId);   // seller's tax invoice at dispatch (test seller's own series)
        if ($target === 'rto') {
            foreach ([['UNDELIVERED', 'ndr', 0, 'Customer not reachable'], ['RTO INITIATED', 'rto_initiated', 110, 'Return to origin initiated'], ['RTO DELIVERED', 'rto_delivered', 120, 'Returned to seller']] as $k => [$raw, $internal, $rank, $act]) {
                $et = $t + (6 + $k * 48) * 3600;
                ins('store_shipment_events', ['shipment_id' => $sid, 'raw_status' => $raw, 'internal_status' => $internal, 'location' => 'Customer city', 'activity' => $act,
                    'event_at' => dt($et), 'source' => 'webhook', 'dedupe_key' => hash('sha256', 'TESTAWB' . $voId . '|' . $raw . '|' . $et)]);
                $t = $et;
            }
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update(['status' => 'rto_delivered', 'status_rank' => 120, 'rto_at' => dt($t), 'last_raw_status' => 'RTO DELIVERED', 'last_event_at' => dt($t)]);
            q("UPDATE store_vendor_orders SET status = 'rto' WHERE id = :id", ['id' => $voId]);
            hist($o['id'], $voId, 'vendor_order', $voId, 'shipped', 'rto', 'webhook', null, 'Courier: rto_delivered', $t);
        }
        if ($target === 'lost') {
            $t += 5 * 86400;
            ins('store_shipment_events', ['shipment_id' => $sid, 'raw_status' => 'LOST', 'internal_status' => 'lost', 'location' => 'Regional hub', 'activity' => 'Shipment lost in transit',
                'event_at' => dt($t), 'source' => 'webhook', 'dedupe_key' => hash('sha256', 'TESTAWB' . $voId . '|LOST|' . $t)]);
            QueryBuilder::table('store_shipments')->where('id', '=', $sid)->update(['status' => 'lost', 'status_rank' => 140, 'last_raw_status' => 'LOST', 'last_event_at' => dt($t)]);
            hist($o['id'], $voId, 'shipment', $sid, null, 'lost', 'webhook', null, 'Courier exception', $t);
        }
    }
    if ($lvl >= 6 && $target !== 'rto') {
        $days = (int) $owner['ret_days'];
        q("UPDATE store_vendor_orders SET status = 'delivered', delivered_at = :t, settle_after = :sa WHERE id = :id",
            ['t' => dt($t), 'sa' => dt($t + $days * 86400), 'id' => $voId]);
        hist($o['id'], $voId, 'vendor_order', $voId, 'shipped', 'delivered', 'webhook', null, 'Courier: delivered', $t);
        SettlementService::recordDelivered($voId);   // seller ledger: sale, commission, GST on commission, courier
        q('UPDATE store_vendor_ledger SET created_at = :t WHERE vendor_order_id = :v', ['t' => dt($t), 'v' => $voId]);
        if ($target === 'completed') {
            $sa = $t + $days * 86400;
            q("UPDATE store_vendor_orders SET status = 'completed', settlement_status = 'eligible' WHERE id = :id", ['id' => $voId]);
            q("UPDATE store_vendor_ledger SET status = 'available' WHERE vendor_order_id = :v AND status = 'pending'", ['v' => $voId]);
            hist($o['id'], $voId, 'vendor_order', $voId, 'delivered', 'completed', 'system', null, 'Return window closed', $sa);
            $t = $sa;
        }
    }
    recompute($o['id'], $t);

    return $t;
}

/**
 * Cancel units with a refund — mirrors StoreRefundService::cancelItems (Razorpay replaced by a fake refund id).
 *
 * @param array<int, int>|null $itemQty order_item_id => qty; null = everything open in $voId
 */
function cancelItems(array $o, ?int $voId, ?array $itemQty, string $reason, string $actor, ?int $actorId, bool $restock, int $at, ?string $undelivered = null, bool $refundShipping = false, bool $compensate = false): void
{
    global $refundSeq;
    $all = [];
    foreach (q('SELECT oi.*, vo.status AS vo_status, vo.id AS vo_id FROM store_order_items oi JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id WHERE oi.order_id = :o', ['o' => $o['id']])->fetchAll() as $r) {
        $all[(int) $r['id']] = $r;
    }
    if ($itemQty === null) {
        $itemQty = [];
        foreach ($all as $id => $it) {
            if ((int) $it['vo_id'] === $voId && (int) $it['qty'] - (int) $it['qty_cancelled'] - (int) $it['qty_returned'] > 0) {
                $itemQty[$id] = (int) $it['qty'] - (int) $it['qty_cancelled'] - (int) $it['qty_returned'];
            }
        }
    }
    $pay = q('SELECT * FROM store_payments WHERE id = :id', ['id' => $o['pay']])->fetch();
    $amount = 0;
    $lines = [];
    $touched = [];
    foreach ($itemQty as $itemId => $qn) {
        $it = $all[$itemId];
        $lineAmt = PricingService::unitShare((int) $it['line_total_paise'], (int) $it['qty'], (int) $it['qty_refunded'], $qn);
        $amount += $lineAmt;
        $lines[] = ['item' => $it, 'qty' => $qn, 'amount' => $lineAmt];
        $touched[(int) $it['vo_id']] = true;
    }
    $shippingBack = [];
    foreach (array_keys($touched) as $vid) {
        $fully = true;
        foreach ($all as $it) {
            if ((int) $it['vo_id'] === $vid && (int) $it['qty_cancelled'] + (int) ($itemQty[(int) $it['id']] ?? 0) < (int) $it['qty']) {
                $fully = false;
            }
        }
        if ($fully) {
            $vo = q('SELECT shipping_paise FROM store_vendor_orders WHERE id = :id', ['id' => $vid])->fetch();
            $shippingBack[$vid] = $undelivered !== null && !$refundShipping ? 0 : (int) $vo['shipping_paise'];
            $amount += $shippingBack[$vid];
        }
    }
    $refundSeq++;
    $refundNo = sprintf('RFTEST-%04d', $refundSeq);
    $refundId = ins('store_refunds', [
        'refund_no' => $refundNo, 'order_id' => $o['id'], 'vendor_order_id' => count($touched) === 1 ? (int) array_key_first($touched) : null,
        'payment_id' => $o['pay'], 'rzp_refund_id' => sprintf('rfnd_TEST%06d', $refundSeq), 'amount_paise' => $amount,
        'reason' => $undelivered ?? ['customer' => 'customer_cancel', 'vendor_user' => 'vendor_cancel', 'admin' => 'admin'][$actor] ?? 'vendor_sla',
        'note' => $reason, 'status' => 'processed', 'initiated_by_type' => $actor, 'initiated_by_id' => $actorId, 'processed_at' => dt($at + 3600), 'created_at' => dt($at),
    ]);
    ins('store_payment_transactions', ['payment_id' => $o['pay'], 'type' => 'refund', 'gateway_ref' => sprintf('rfnd_TEST%06d', $refundSeq), 'amount_paise' => $amount, 'status' => 'processed', 'created_at' => dt($at)]);
    foreach ($lines as $l) {
        $it = $l['item'];
        $qn = $l['qty'];
        ins('store_refund_items', ['refund_id' => $refundId, 'order_item_id' => (int) $it['id'], 'qty' => $qn, 'amount_paise' => $l['amount'], 'shipping_paise' => 0]);
        q("UPDATE store_order_items SET qty_cancelled = qty_cancelled + :q1, qty_refunded = qty_refunded + :q2, status = IF(qty_cancelled >= qty, 'cancelled', 'partially_cancelled') WHERE id = :id",
            ['q1' => $qn, 'q2' => $qn, 'id' => (int) $it['id']]);
        $vp = (int) round((int) $it['vendor_payable_paise'] * $qn / max(1, (int) $it['qty']));
        $cm = (int) round((int) $it['commission_paise'] * $qn / max(1, (int) $it['qty']));
        q('UPDATE store_vendor_orders SET vendor_payable_paise = vendor_payable_paise - :vp, commission_paise = IF(commission_paise > :c1, commission_paise - :c2, 0) WHERE id = :id',
            ['vp' => $vp, 'c1' => $cm, 'c2' => $cm, 'id' => (int) $it['vo_id']]);
        if ($undelivered !== null && $undelivered !== 'rto' && $compensate && $vp > 0) {
            q("INSERT IGNORE INTO store_vendor_ledger (vendor_id, vendor_order_id, order_item_id, entry_type, amount_paise, status, available_at, dedupe_key, memo, created_by_type, created_at)
               VALUES (:v, :vo, :oi, 'adjustment', :a, 'available', :t, :k, :m, 'admin', :t2)", [
                'v' => (int) $it['vendor_id'], 'vo' => (int) $it['vo_id'], 'oi' => (int) $it['id'], 'a' => $vp, 't' => dt($at),
                'k' => 'lost:' . $refundId . ':item:' . (int) $it['id'], 'm' => 'Compensation: ' . $qn . ' × ' . $it['sku'] . ' ' . $undelivered . ' in transit', 't2' => dt($at),
            ]);
        }
        if ($restock) {
            q('UPDATE store_product_variants SET stock_qty = stock_qty + :q WHERE id = :v', ['q' => $qn, 'v' => (int) $it['variant_id']]);
            ins('store_inventory_movements', ['variant_id' => (int) $it['variant_id'], 'delta' => $qn, 'reason' => 'restock_return', 'ref_type' => 'refund', 'ref_id' => $refundId,
                'actor_type' => in_array($actor, ['vendor_user', 'admin'], true) ? $actor : 'system', 'created_at' => dt($at)]);
        }
    }
    $voStatus = $undelivered !== null ? ($undelivered === 'rto' ? 'rto' : 'lost_in_transit')
        : ['customer' => 'cancelled_by_customer', 'vendor_user' => 'cancelled_by_vendor', 'admin' => 'cancelled_by_admin'][$actor] ?? 'auto_cancelled';
    foreach ($shippingBack as $vid => $ship) {
        q('UPDATE store_vendor_orders SET status = :s, cancel_reason = :r, cancelled_by_type = :t, cancelled_by_id = :i WHERE id = :id',
            ['s' => $voStatus, 'r' => $reason, 't' => in_array($actor, ['customer', 'vendor_user', 'admin'], true) ? $actor : 'system', 'i' => $actorId, 'id' => $vid]);
        if ($ship > 0) {
            q('UPDATE store_refund_items SET shipping_paise = :s WHERE refund_id = :r AND order_item_id IN (SELECT id FROM store_order_items WHERE vendor_order_id = :vo) ORDER BY order_item_id LIMIT 1',
                ['s' => $ship, 'r' => $refundId, 'vo' => $vid]);
        }
        hist($o['id'], $vid, 'vendor_order', $vid, null, $voStatus, $actor, $actorId, $reason, $at);
    }
    TaxDocumentService::creditForRefund($refundId, $undelivered ?? 'cancel');
    $new = (int) $pay['refunded_paise'] + $amount;
    $full = $new >= (int) $pay['amount_paise'];
    q('UPDATE store_payments SET refunded_paise = :r, status = :s WHERE id = :id', ['r' => $new, 's' => $full ? 'refunded' : 'partially_refunded', 'id' => $o['pay']]);
    q('UPDATE store_orders SET payment_status = :s WHERE id = :id', ['s' => $full ? 'refunded' : 'partially_refunded', 'id' => $o['id']]);
    hist($o['id'], null, 'payment', $o['pay'], null, $full ? 'refunded' : 'partially_refunded', $actor, $actorId, "Refund $refundNo of Rs. " . ProductService::rupees($amount) . ': ' . $reason, $at);
    recompute($o['id'], $at);
}

/**
 * A return on a delivered package, taken to $to — mirrors ReturnService (request/approve/reject/receive/refund).
 * to: requested | approved | pickup_scheduled | picked_up | received | qc_failed | rejected | refunded
 */
function makeReturn(array $o, int $voId, ?array $itemQty, string $reason, string $to, int $at, bool $restock = true): void
{
    global $returnSeq, $refundSeq, $vendors;
    $vo = q('SELECT * FROM store_vendor_orders WHERE id = :id', ['id' => $voId])->fetch();
    $owner = null;
    foreach ($vendors as $v) {
        if ((int) $v['id'] === (int) $vo['vendor_id']) {
            $owner = $v['owner'];
        }
    }
    $items = q('SELECT * FROM store_order_items WHERE vendor_order_id = :v ORDER BY id', ['v' => $voId])->fetchAll();
    if ($itemQty === null) {
        $itemQty = [(int) $items[0]['id'] => (int) $items[0]['qty']];
    }
    $labels = ['damaged' => 'Arrived damaged', 'defective' => 'Defective / not working', 'wrong_item' => 'Wrong item sent', 'expired' => 'Expired or near expiry',
        'not_as_described' => 'Not as described', 'other' => 'Other'];
    $returnSeq++;
    $rid = ins('store_returns', [
        'return_no' => sprintf('RTTEST-%04d', $returnSeq), 'order_id' => $o['id'], 'vendor_order_id' => $voId, 'identity_id' => $o['identity'], 'status' => 'requested',
        'reason_code' => $reason, 'customer_note' => 'Test return: ' . strtolower($labels[$reason]) . '.', 'photos_json' => '[]', 'created_at' => dt($at),
    ]);
    foreach ($itemQty as $itemId => $qn) {
        ins('store_return_items', ['return_id' => $rid, 'order_item_id' => $itemId, 'qty' => $qn, 'reason' => $labels[$reason]]);
    }
    hist($o['id'], $voId, 'return', $rid, null, 'requested', 'customer', $o['identity'], $labels[$reason], $at);
    if ($to === 'requested') {
        return;
    }
    $t = $at + 20 * 3600;
    if ($to === 'rejected') {
        q("UPDATE store_returns SET status = 'rejected', vendor_decision = 'rejected', vendor_decided_at = :t, vendor_note = :n WHERE id = :id",
            ['t' => dt($t), 'n' => 'Test: seal is broken — used items can\'t be returned.', 'id' => $rid]);
        hist($o['id'], $voId, 'return', $rid, 'requested', 'rejected', 'vendor_user', $owner, 'Seal broken', $t);

        return;
    }
    q("UPDATE store_returns SET status = 'approved', vendor_decision = 'approved', vendor_decided_at = :t, vendor_note = :n WHERE id = :id",
        ['t' => dt($t), 'n' => 'Approved — pickup will be arranged.', 'id' => $rid]);
    hist($o['id'], $voId, 'return', $rid, 'requested', 'approved', 'vendor_user', $owner, 'Approved', $t);
    if ($to === 'approved') {
        return;
    }
    // Reverse pickup (customer → seller).
    $t += 6 * 3600;
    $sid = ins('store_shipments', [
        'shipment_no' => $vo['sub_order_no'] . '-R' . $returnSeq, 'direction' => 'return', 'order_id' => $o['id'], 'vendor_order_id' => $voId, 'vendor_id' => (int) $vo['vendor_id'],
        'return_id' => $rid, 'status' => 'pickup_scheduled', 'status_rank' => 30, 'awb_code' => sprintf('TESTRAWB%06d', $rid), 'courier_name' => 'Delhivery Reverse (test)',
        'weight_g' => 500, 'length_mm' => 200, 'breadth_mm' => 150, 'height_mm' => 100, 'pickup_scheduled_for' => date('Y-m-d', $t + 86400),
        'last_raw_status' => 'PICKUP SCHEDULED', 'last_event_at' => dt($t), 'created_at' => dt($t),
    ]);
    q("UPDATE store_returns SET status = 'pickup_scheduled', return_shipment_id = :s WHERE id = :id", ['s' => $sid, 'id' => $rid]);
    hist($o['id'], $voId, 'return', $rid, 'approved', 'pickup_scheduled', 'system', null, 'Reverse pickup booked', $t);
    if ($to === 'pickup_scheduled') {
        return;
    }
    $t += 30 * 3600;
    q("UPDATE store_shipments SET status = 'picked_up', status_rank = 40, picked_up_at = :t, last_raw_status = 'PICKED UP', last_event_at = :t2 WHERE id = :id", ['t' => dt($t), 't2' => dt($t), 'id' => $sid]);
    q("UPDATE store_returns SET status = 'picked_up' WHERE id = :id", ['id' => $rid]);
    hist($o['id'], $voId, 'return', $rid, 'pickup_scheduled', 'picked_up', 'webhook', null, 'Courier: picked_up', $t);
    if ($to === 'picked_up') {
        return;
    }
    $t += 60 * 3600;
    q("UPDATE store_shipments SET status = 'delivered', status_rank = 100, delivered_at = :t, last_raw_status = 'DELIVERED', last_event_at = :t2 WHERE id = :id", ['t' => dt($t), 't2' => dt($t), 'id' => $sid]);
    q("UPDATE store_returns SET status = 'received' WHERE id = :id", ['id' => $rid]);
    hist($o['id'], $voId, 'return', $rid, 'picked_up', 'received', 'webhook', null, 'Courier: delivered', $t);
    if ($to === 'received') {
        return;
    }
    $t += 8 * 3600;
    $qcOk = $to !== 'qc_failed';
    q("UPDATE store_returns SET status = :s WHERE id = :id", ['s' => $qcOk ? 'qc_passed' : 'qc_failed', 'id' => $rid]);
    q('UPDATE store_return_items SET condition_on_receipt = :c WHERE return_id = :r', ['c' => $qcOk ? 'ok' : 'damaged', 'r' => $rid]);
    hist($o['id'], $voId, 'return', $rid, 'received', $qcOk ? 'qc_passed' : 'qc_failed', 'vendor_user', $owner, $qcOk ? 'Item OK' : 'Test: item received used / damaged', $t);
    if (!$qcOk) {
        return;
    }
    // Refund + ledger reversal (ReturnService::refund).
    $t += 3600;
    $pay = q('SELECT * FROM store_payments WHERE id = :id', ['id' => $o['pay']])->fetch();
    $lines = q('SELECT ri.qty AS rqty, oi.* FROM store_return_items ri JOIN store_order_items oi ON oi.id = ri.order_item_id WHERE ri.return_id = :r', ['r' => $rid])->fetchAll();
    $amount = 0;
    foreach ($lines as &$l) {
        $l['_refund'] = PricingService::unitShare((int) $l['line_total_paise'], (int) $l['qty'], (int) $l['qty_refunded'], (int) $l['rqty']);
        $l['_seller'] = PricingService::unitShare(PricingService::sellerRevenue($l), (int) $l['qty'], (int) $l['qty_refunded'], (int) $l['rqty']);
        $amount += $l['_refund'];
    }
    unset($l);
    $refundSeq++;
    $refundNo = sprintf('RFTEST-%04d', $refundSeq);
    $refundId = ins('store_refunds', [
        'refund_no' => $refundNo, 'order_id' => $o['id'], 'vendor_order_id' => $voId, 'return_id' => $rid, 'payment_id' => $o['pay'],
        'rzp_refund_id' => sprintf('rfnd_TEST%06d', $refundSeq), 'amount_paise' => $amount, 'reason' => 'return', 'note' => 'Return ' . sprintf('RTTEST-%04d', $returnSeq),
        'status' => 'processed', 'initiated_by_type' => 'vendor_user', 'initiated_by_id' => $owner, 'processed_at' => dt($t + 3600), 'created_at' => dt($t),
    ]);
    ins('store_payment_transactions', ['payment_id' => $o['pay'], 'type' => 'refund', 'gateway_ref' => sprintf('rfnd_TEST%06d', $refundSeq), 'amount_paise' => $amount, 'status' => 'processed', 'created_at' => dt($t)]);
    foreach ($lines as $l) {
        $qn = (int) $l['rqty'];
        ins('store_refund_items', ['refund_id' => $refundId, 'order_item_id' => (int) $l['id'], 'qty' => $qn, 'amount_paise' => (int) $l['_refund'], 'shipping_paise' => 0]);
        q("UPDATE store_order_items SET qty_returned = qty_returned + :q1, qty_refunded = qty_refunded + :q2, status = IF(qty_returned + qty_cancelled >= qty, 'returned', 'partially_returned') WHERE id = :id",
            ['q1' => $qn, 'q2' => $qn, 'id' => (int) $l['id']]);
        if ($restock) {
            q('UPDATE store_product_variants SET stock_qty = stock_qty + :q WHERE id = :v', ['q' => $qn, 'v' => (int) $l['variant_id']]);
            ins('store_inventory_movements', ['variant_id' => (int) $l['variant_id'], 'delta' => $qn, 'reason' => 'restock_return', 'ref_type' => 'return', 'ref_id' => $rid, 'actor_type' => 'vendor_user', 'created_at' => dt($t)]);
        }
        $commShare = (int) round((int) $l['commission_paise'] * $qn / max(1, (int) $l['qty']));
        $gstShare = (int) round($commShare * CommissionService::COMMISSION_GST_BP / 10000);
        $memo = 'Return ' . sprintf('RTTEST-%04d', $returnSeq) . ' (' . $qn . ' × ' . $l['sku'] . ')';
        foreach ([
            ['refund_reversal', -(int) $l['_seller'], "return:$rid:item:{$l['id']}", $memo],
            ['commission_debit', $commShare, "return:$rid:item:{$l['id']}:comm", 'Commission refunded: ' . $memo],
            ['commission_gst_debit', $gstShare, "return:$rid:item:{$l['id']}:commgst", 'GST on commission refunded: ' . $memo],
        ] as [$type, $amt, $key, $m]) {
            if ($amt !== 0) {
                q("INSERT IGNORE INTO store_vendor_ledger (vendor_id, vendor_order_id, order_item_id, entry_type, amount_paise, status, available_at, dedupe_key, memo, created_by_type, created_at)
                   VALUES (:v, :vo, :oi, :ty, :a, 'available', :t, :k, :m, 'system', :t2)",
                    ['v' => (int) $vo['vendor_id'], 'vo' => $voId, 'oi' => (int) $l['id'], 'ty' => $type, 'a' => $amt, 't' => dt($t), 'k' => $key, 'm' => $m, 't2' => dt($t)]);
            }
        }
    }
    TaxDocumentService::creditForRefund($refundId, 'return');
    $new = (int) $pay['refunded_paise'] + $amount;
    $full = $new >= (int) $pay['amount_paise'];
    q('UPDATE store_payments SET refunded_paise = :r, status = :s WHERE id = :id', ['r' => $new, 's' => $full ? 'refunded' : 'partially_refunded', 'id' => $o['pay']]);
    q('UPDATE store_orders SET payment_status = :s WHERE id = :id', ['s' => $full ? 'refunded' : 'partially_refunded', 'id' => $o['id']]);
    q("UPDATE store_returns SET status = 'refunded', refund_amount_paise = :a WHERE id = :id", ['a' => $amount, 'id' => $rid]);
    hist($o['id'], $voId, 'return', $rid, 'qc_passed', 'refunded', 'vendor_user', $owner, "Refund $refundNo of Rs. " . ProductService::rupees($amount), $t);
}

function voOf(array $o, int $vendorN): int
{
    global $vendors;

    return (int) $o['vos'][$vendors[$vendorN]['id']];
}

// ---------------------------------------------------------------------------
// The scenarios. [customer, [[seller, variant pick, qty], …], coupon, days ago]
// ---------------------------------------------------------------------------

$S = [];   // label => order (for the summary)
$h = 3600;

// -- Unpaid --
$o = placeOrder(1, [[1, 0, 1], [1, 3, 2]], null, ago(0.1));
q('UPDATE store_orders SET expires_at = :e WHERE id = :id', ['e' => dt(ago(-3)), 'id' => $o['id']]);   // stays "awaiting payment" for 3 days, then the maintenance job expires it
$S['Awaiting payment (pending_payment)'] = $o;

$o = placeOrder(2, [[3, 1, 1]], null, ago(1));
q("UPDATE store_payments SET status = 'failed', failure_code = 'BAD_REQUEST_ERROR', failure_reason = 'Test: payment declined by the bank' WHERE id = :id", ['id' => $o['pay']]);
ins('store_payment_transactions', ['payment_id' => $o['pay'], 'type' => 'attempt', 'gateway_ref' => sprintf('pay_TESTF%05d', $o['pay']), 'amount_paise' => $o['total'], 'status' => 'failed', 'created_at' => dt(ago(1) + 300)]);
releaseUnpaid($o, 'payment_failed', 'Payment failed', ago(1) + 600, 'customer');
$S['Payment failed'] = $o;

$o = placeOrder(3, [[5, 0, 1]], null, ago(4));
releaseUnpaid($o, 'expired', 'Payment window expired', ago(4) + 1900);
$S['Expired (never paid)'] = $o;

$o = placeOrder(4, [[6, 2, 1]], null, ago(6));
releaseUnpaid($o, 'cancelled', 'Replaced by a newer checkout', ago(6) + 900, 'customer');
$S['Cancelled before payment'] = $o;

// -- Paid, seller action pending / in progress --
$o = placeOrder(5, [[1, 1, 1], [4, 0, 1]], null, ago(0.5));
pay($o, ago(0.5) + 120, 'card', ago(-1.6));   // accept deadline ~38 h from now
$S['New — awaiting seller acceptance (2 sellers)'] = $o;

$o = placeOrder(6, [[2, 0, 2]], null, ago(1.2));
pay($o, ago(1.2) + 90, 'upi');
advance($o, voOf($o, 2), 'accepted', ago(1.2) + 200);
$S['Accepted by seller'] = $o;

$o = placeOrder(7, [[3, 2, 1], [3, 0, 1]], null, ago(1.5), 'mobile');
pay($o, ago(1.5) + 60, 'upi');
advance($o, voOf($o, 3), 'packed', ago(1.5) + 200);
$S['Packed (mobile app order)'] = $o;

$o = placeOrder(8, [[4, 1, 1]], null, ago(2));
pay($o, ago(2) + 60, 'netbanking');
advance($o, voOf($o, 4), 'ready_to_ship', ago(2) + 100);
$S['Ready to ship (courier booked)'] = $o;

$o = placeOrder(9, [[5, 1, 1], [5, 4, 2]], null, ago(3));
pay($o, ago(3) + 60, 'card');
advance($o, voOf($o, 5), 'in_transit', ago(3) + 100, 8500);
$S['Shipped — in transit'] = $o;

$o = placeOrder(10, [[6, 0, 2]], null, ago(3.2));
pay($o, ago(3.2) + 60, 'upi');
advance($o, voOf($o, 6), 'out_for_delivery', ago(3.2) + 100);
$S['Out for delivery'] = $o;

// -- Delivered (return window open) --
foreach ([[11, [[7, 0, 1], [7, 2, 2]], 2], [12, [[8, 1, 1]], 3], [13, [[9, 0, 1]], 4], [14, [[10, 1, 2]], 5]] as $k => [$cn, $picks, $d]) {
    $o = placeOrder($cn, $picks, null, ago($d + 4));
    pay($o, ago($d + 4) + 60, ['upi', 'card', 'upi', 'wallet'][$k]);
    advance($o, (int) array_values($o['vos'])[0], 'delivered', ago($d + 4) + 100, $k % 2 ? null : 7200);
    $S['Delivered (return window open) #' . ($k + 1)] = $o;
}

// -- Completed (return window over → seller balance available) --
$completed = [];
foreach ([[15, [[1, 0, 2], [1, 2, 1]]], [16, [[1, 4, 1]]], [17, [[3, 0, 3]]], [18, [[5, 2, 1], [5, 0, 1]]], [19, [[6, 1, 2]]], [20, [[2, 1, 1], [2, 3, 1]]], [21, [[4, 2, 1]]], [22, [[11, 0, 1]]], [23, [[12, 0, 1]]]] as $k => [$cn, $picks]) {
    $d = 25 + $k * 2;
    $o = placeOrder($cn, $picks, null, ago($d));
    pay($o, ago($d) + 60, $k % 3 ? 'upi' : 'card');
    advance($o, (int) array_values($o['vos'])[0], 'completed', ago($d) + 100, $k % 2 ? 6500 : null);
    $completed[] = $o;
    $S['Completed #' . ($k + 1)] = $o;
}

// -- Coupons --
$o = placeOrder(1, [[1, 1, 2], [1, 2, 1]], 'TESTSAVE10', ago(30));
pay($o, ago(30) + 60, 'upi');
advance($o, voOf($o, 1), 'completed', ago(30) + 100);
$S['Completed with platform coupon TESTSAVE10'] = $o;

$o = placeOrder(9, [[6, 0, 1], [6, 2, 1]], 'TESTSELLER15', ago(8));
pay($o, ago(8) + 60, 'card');
advance($o, voOf($o, 6), 'delivered', ago(8) + 100);
$S['Delivered with seller-funded coupon TESTSELLER15'] = $o;

$o = placeOrder(24, [[7, 1, 1]], 'TESTFREESHIP', ago(2.5));
pay($o, ago(2.5) + 60, 'upi', ago(-1.2));   // still awaiting acceptance: deadline in the future
$S['New with TESTFREESHIP (free delivery)'] = $o;

// -- Multi-seller: partially shipped / partially delivered --
$o = placeOrder(2, [[1, 2, 1], [5, 3, 1], [7, 3, 1]], null, ago(4));
pay($o, ago(4) + 60, 'card');
advance($o, voOf($o, 1), 'in_transit', ago(4) + 100);
advance($o, voOf($o, 5), 'accepted', ago(4) + 100);
// The third seller hasn't accepted yet: keep its deadline in the future so the
// maintenance job doesn't auto-cancel it (that would try a Razorpay refund of a fake payment).
q('UPDATE store_vendor_orders SET accept_by = :a WHERE id = :id', ['a' => dt(ago(-1)), 'id' => voOf($o, 7)]);
$S['Partially shipped (3 sellers)'] = $o;

$o = placeOrder(3, [[3, 3, 1], [8, 0, 1]], null, ago(7));
pay($o, ago(7) + 60, 'upi');
advance($o, voOf($o, 3), 'delivered', ago(7) + 100);
advance($o, voOf($o, 8), 'in_transit', ago(7) + 5 * $h);
$S['Partially delivered (2 sellers)'] = $o;

// -- Cancellations with refunds --
$o = placeOrder(4, [[2, 2, 1], [2, 4, 1]], null, ago(5));
pay($o, ago(5) + 60, 'upi');
cancelItems($o, voOf($o, 2), null, 'Test: ordered by mistake', 'customer', $o['identity'], true, ago(5) + 2 * $h);
$S['Cancelled by customer (full refund)'] = $o;

$o = placeOrder(5, [[9, 1, 1]], null, ago(6));
pay($o, ago(6) + 60, 'card');
cancelItems($o, voOf($o, 9), null, 'Test: out of stock at the warehouse', 'vendor_user', $vendors[9]['owner'], false, ago(6) + 5 * $h);
$S['Rejected / cancelled by seller'] = $o;

$o = placeOrder(6, [[10, 0, 1], [10, 2, 1]], null, ago(9));
pay($o, ago(9) + 60, 'upi');
advance($o, voOf($o, 10), 'packed', ago(9) + 100);
cancelItems($o, voOf($o, 10), null, 'Test: suspected fraud — cancelled by eClinicPro', 'admin', null, true, ago(9) + 12 * $h);
$S['Cancelled by admin'] = $o;

$o = placeOrder(7, [[11, 1, 1]], null, ago(10));
pay($o, ago(10) + 60, 'upi');
cancelItems($o, voOf($o, 11), null, 'Seller did not accept the order in time', 'system', null, true, ago(10) + 49 * $h);
$S['Auto-cancelled (seller missed 48 h deadline)'] = $o;

$o = placeOrder(8, [[1, 0, 2], [1, 3, 1]], null, ago(12));
pay($o, ago(12) + 60, 'card');
$firstItem = (int) q('SELECT id FROM store_order_items WHERE vendor_order_id = :v ORDER BY id LIMIT 1', ['v' => voOf($o, 1)])->fetchColumn();
cancelItems($o, null, [$firstItem => 1], 'Test: need only one of these', 'customer', $o['identity'], true, ago(12) + 2 * $h);
advance($o, voOf($o, 1), 'delivered', ago(12) + 3 * $h);
$S['Partly cancelled (1 unit), rest delivered'] = $o;

// -- Undelivered: RTO and lost --
$o = placeOrder(10, [[4, 3, 1]], null, ago(15));
pay($o, ago(15) + 60, 'upi');
advance($o, voOf($o, 4), 'rto', ago(15) + 100);
cancelItems($o, voOf($o, 4), null, 'Returned to seller: customer not reachable', 'admin', null, true, ago(6), 'rto');
$S['RTO — returned to seller, refunded (delivery fee kept)'] = $o;

$o = placeOrder(11, [[5, 4, 1]], null, ago(16));
pay($o, ago(16) + 60, 'card');
advance($o, voOf($o, 5), 'lost', ago(16) + 100);
cancelItems($o, voOf($o, 5), null, 'Lost in transit', 'admin', null, false, ago(8), 'lost', true, true);
$S['Lost in transit — full refund + seller compensated'] = $o;

// -- Returns (all on delivered packages inside the return window) --
$returnPlan = [
    ['requested', 'damaged', 12, [[1, 1, 1]]],
    ['approved', 'wrong_item', 13, [[3, 1, 1]]],
    ['pickup_scheduled', 'defective', 14, [[4, 0, 1]]],
    ['picked_up', 'not_as_described', 15, [[6, 1, 1]]],
    ['received', 'expired', 16, [[5, 0, 1]]],
    ['qc_failed', 'defective', 17, [[4, 2, 1]]],
    ['rejected', 'other', 18, [[2, 0, 1]]],
    ['refunded', 'damaged', 19, [[3, 0, 2]]],
    ['refunded', 'wrong_item', 20, [[7, 0, 1], [7, 3, 1]]],
];
foreach ($returnPlan as $k => [$to, $why, $cn, $picks]) {
    $o = placeOrder($cn, $picks, null, ago(9));
    pay($o, ago(9) + 60, 'upi');
    $vn = $picks[0][0];
    advance($o, voOf($o, $vn), 'delivered', ago(9) + 100);
    $items = q('SELECT id, qty FROM store_order_items WHERE vendor_order_id = :v ORDER BY id', ['v' => voOf($o, $vn)])->fetchAll();
    $iq = [(int) $items[0]['id'] => $k === 7 ? 1 : (int) $items[0]['qty']];   // #8: partial return (1 of 2 units)
    if ($k === 8) {   // #9: everything in the package comes back
        $iq = [];
        foreach ($items as $it) {
            $iq[(int) $it['id']] = (int) $it['qty'];
        }
    }
    makeReturn($o, voOf($o, $vn), $iq, $why, $to, ago(3) + $k * 1200);
    $S['Return: ' . $to . ($k === 7 ? ' (1 of 2 units)' : ($k === 8 ? ' (whole package)' : ''))] = $o;
}

// A blocked customer's old order.
$o = placeOrder(25, [[12, 1, 1]], null, ago(40));
pay($o, ago(40) + 60, 'upi');
advance($o, voOf($o, 12), 'completed', ago(40) + 100);
$S['Blocked customer — old completed order'] = $o;

// ===========================================================================
// 9. Payouts: paid, approved, seller request (draft), declined request
// ===========================================================================

$payoutInfo = [];
foreach ([[1, 'paid'], [3, 'approved'], [5, 'requested'], [6, 'declined']] as [$vn, $state]) {
    $v = $vendors[$vn];
    $res = SettlementService::createForVendor($v['id'], 0);
    if (!$res['ok']) {
        out('  NOTE: no payout for ' . $v['name'] . ': ' . ($res['error'] ?? ''));
        continue;
    }
    $pid = (int) $res['payout_id'];
    $payoutSeq++;
    q('UPDATE store_payouts SET payout_no = :n, created_at = :t WHERE id = :id', ['n' => sprintf('POTEST-%04d', $payoutSeq), 't' => dt(ago(6 - $payoutSeq)), 'id' => $pid]);
    if ($state === 'paid' || $state === 'approved') {
        q("UPDATE store_payouts SET status = 'approved', approved_at = :t WHERE id = :id", ['t' => dt(ago(4)), 'id' => $pid]);
    }
    if ($state === 'paid') {
        q("UPDATE store_payouts SET status = 'paid', reference = 'TESTUTR0000123456', paid_at = :t WHERE id = :id", ['t' => dt(ago(3)), 'id' => $pid]);
        q("UPDATE store_vendor_ledger SET status = 'paid' WHERE payout_id = :p", ['p' => $pid]);
        q("UPDATE store_vendor_orders SET settlement_status = 'paid' WHERE id IN (SELECT DISTINCT vendor_order_id FROM store_vendor_ledger WHERE payout_id = :p AND vendor_order_id IS NOT NULL)", ['p' => $pid]);
    }
    if (($state === 'requested' || $state === 'declined') && tableExists('store_payout_requests')) {
        ins('store_payout_requests', ['payout_id' => $pid, 'vendor_id' => $v['id'], 'vendor_user_id' => $v['owner'], 'note' => 'Test: please release this week\'s earnings.',
            'decline_reason' => $state === 'declined' ? 'Test: bank account under re-verification. Please request again next week.' : null, 'requested_at' => dt(ago(2))]);
    }
    if ($state === 'declined') {   // cancel → entries go back to available
        q("UPDATE store_payouts SET status = 'cancelled' WHERE id = :id", ['id' => $pid]);
        q("UPDATE store_vendor_orders SET settlement_status = 'eligible' WHERE id IN (SELECT DISTINCT vendor_order_id FROM store_vendor_ledger WHERE payout_id = :p AND vendor_order_id IS NOT NULL)", ['p' => $pid]);
        q("UPDATE store_vendor_ledger SET status = 'available', payout_id = NULL WHERE payout_id = :p", ['p' => $pid]);
    }
    $payoutInfo[] = $v['name'] . ': ' . $state;
}
// An admin adjustment and a penalty, so the ledger shows both.
SettlementService::adjust($vendors[3]['id'], 15000, 'Test: goodwill credit for a courier delay', 0);
SettlementService::adjust($vendors[5]['id'], -10000, 'Test: late dispatch penalty', 0);
q("UPDATE store_vendor_ledger SET created_by_id = NULL WHERE vendor_id IN (" . idList([$vendors[3]['id'], $vendors[5]['id']]) . ") AND created_by_type = 'admin' AND created_by_id = 0");

// ===========================================================================
// 10. Reviews (published with/without seller reply, pending, rejected)
// ===========================================================================

$reviewTexts = [
    [5, 'Excellent quality', 'Works exactly as described. Delivery was quick and the packaging was good.'],
    [4, 'Good value', 'Good product for the price. Would buy again.'],
    [5, 'Highly recommend', 'My doctor suggested this and it has helped a lot.'],
    [3, 'Okay', 'Average. Took a while to see any difference.'],
    [2, 'Packaging damaged', 'The outer box was dented but the product was fine.'],
    [4, 'Nice', 'Pleasant to use, no side effects so far.'],
    [1, 'Not for me', 'Did not suit me at all.'],
];
$reviewCount = 0;
foreach (q(
    "SELECT oi.id, oi.product_id, oi.vendor_id, o.identity_id, vo.delivered_at FROM store_order_items oi
       JOIN store_vendor_orders vo ON vo.id = oi.vendor_order_id AND vo.status IN ('delivered','completed')
       JOIN store_orders o ON o.id = oi.order_id
      WHERE o.order_no REGEXP :p AND oi.qty_returned = 0 ORDER BY oi.id",
    ['p' => T::ORDER_NO_REGEXP]
)->fetchAll() as $k => $it) {
    if ($reviewCount >= 16) {
        break;
    }
    [$rating, $title, $body] = $reviewTexts[$k % count($reviewTexts)];
    $status = $k % 8 === 6 ? 'pending' : ($k % 8 === 7 ? 'rejected' : 'published');
    $at = strtotime((string) $it['delivered_at']) + 2 * 86400;
    ins('store_reviews', [
        'product_id' => (int) $it['product_id'], 'vendor_id' => (int) $it['vendor_id'], 'identity_id' => (int) $it['identity_id'], 'order_item_id' => (int) $it['id'],
        'rating' => $rating, 'title' => 'Test: ' . $title, 'body' => $body, 'status' => $status,
        'vendor_reply' => $status === 'published' && $k % 3 === 0 ? 'Thank you for your feedback! (test reply)' : null,
        'replied_at' => $status === 'published' && $k % 3 === 0 ? dt($at + 86400) : null, 'created_at' => dt(min($at, $NOW - 3600)),
    ]);
    ReviewService::recompute((int) $it['product_id'], (int) $it['vendor_id']);
    $reviewCount++;
}

// ===========================================================================
// 11. Final touches + summary
// ===========================================================================

foreach ($productIds as $pid) {
    ProductService::refreshDenormalized($pid);
}

out('');
out('Done. Test data created:');
out('  sellers   ' . count($vendors) . ' (12 approved, 1 pending review, 1 rejected, 1 suspended)');
out('  products  ' . count($productIds) . ' (' . (int) q('SELECT COUNT(*) FROM store_product_variants WHERE vendor_id IN (' . idList(array_column($vendors, 'id')) . ')')->fetchColumn() . ' variants)');
out('  customers ' . count($cust) . '  (mobiles 9000000001 – 9000000025, OTP 123456 with STORE_TEST_OTP=1)');
out('  orders    ' . $orderSeq . ', refunds ' . $refundSeq . ', returns ' . $returnSeq . ', payouts ' . count($payoutInfo) . ', reviews ' . $reviewCount);
out('');
out('Scenarios (order number → what it shows):');
foreach ($S as $label => $o) {
    $st = q('SELECT status, payment_status FROM store_orders WHERE id = :id', ['id' => $o['id']])->fetch();
    out(sprintf('  %-18s %-10s %-19s %s', $o['no'], $st['status'], $st['payment_status'], $label));
}
out('');
out('Payouts: ' . implode('; ', $payoutInfo));
out('Seller logins: test.seller01@example.com … test.seller15@example.com, password ' . SEED_PASSWORD);
out('Remove everything later: php database/seeds/store_test_seed.php --wipe');
