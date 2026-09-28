<?php

declare(strict_types=1);

namespace App\Services\Store;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Store products: seller create/edit (product + variants + photos + placement),
 * submit for review, and admin decisions.
 *
 * Status flow:  draft → pending_review → live      (admin approves)
 *                              ↘ rejected → (seller edits) → pending_review
 *               live → disabled (admin) ; any → archived (seller)
 *
 * On a LIVE product, price/stock edits apply immediately; changes to name,
 * category, brand, description or photos send it back to pending_review when
 * approval is required (plan D6).
 */
final class ProductService
{
    public const MAX_IMAGES = 8;
    private const IMAGE_MAX_BYTES = 3 * 1024 * 1024;
    /** Fields whose change re-triggers review on a live product. */
    private const REVIEWED_FIELDS = ['name', 'category_id', 'brand_id', 'short_desc', 'description'];
    /** Licence types whose number must be printed on the product page. */
    private const LICENCE_ON_LABEL = ['fssai_license', 'ayush_license', 'medical_device_registration'];

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public static function listForVendor(int $vendorId, string $status = ''): array
    {
        $sql = 'SELECT p.*, c.name AS category_name,
                       (SELECT path FROM store_product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover,
                       (SELECT COUNT(*) FROM store_product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS variant_count,
                       (SELECT COALESCE(SUM(v.stock_qty), 0) FROM store_product_variants v WHERE v.product_id = p.id AND v.is_active = 1) AS total_stock
                  FROM store_products p
                  LEFT JOIN store_categories c ON c.id = p.category_id
                 WHERE p.vendor_id = :v AND p.deleted_at IS NULL'
            . ($status !== '' ? ' AND p.status = :st' : '')
            . ' ORDER BY p.updated_at DESC LIMIT 500';
        $params = ['v' => $vendorId];
        if ($status !== '') {
            $params['st'] = $status;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Vendor-scoped fetch: another seller's product id is simply "not found". */
    public static function findForVendor(int $productId, int $vendorId): ?array
    {
        return QueryBuilder::table('store_products')
            ->where('id', '=', $productId)
            ->where('vendor_id', '=', $vendorId)
            ->where('deleted_at', 'IS')
            ->first();
    }

    /**
     * Product plus everything the edit/review screens need.
     *
     * @return array<string, mixed>|null
     */
    public static function load(int $productId): ?array
    {
        $p = QueryBuilder::table('store_products')->where('id', '=', $productId)->first();
        if ($p === null) {
            return null;
        }
        $pdo = Database::connection();

        $st = $pdo->prepare('SELECT * FROM store_product_variants WHERE product_id = :p ORDER BY is_active DESC, id');
        $st->execute(['p' => $productId]);
        $p['variants'] = $st->fetchAll();

        $st = $pdo->prepare('SELECT * FROM store_product_images WHERE product_id = :p ORDER BY sort_order, id');
        $st->execute(['p' => $productId]);
        $p['images'] = $st->fetchAll();

        $st = $pdo->prepare('SELECT category_id FROM store_product_categories WHERE product_id = :p AND is_primary = 0');
        $st->execute(['p' => $productId]);
        $p['secondary_ids'] = array_map('intval', array_column($st->fetchAll(), 'category_id'));

        $st = $pdo->prepare('SELECT concern_id FROM store_product_concerns WHERE product_id = :p');
        $st->execute(['p' => $productId]);
        $p['concern_ids'] = array_map('intval', array_column($st->fetchAll(), 'concern_id'));

        $p['specs'] = json_decode((string) ($p['specs_json'] ?? ''), true) ?: [];

        return $p;
    }

    /** @return array<string, int> status => count, for one seller */
    public static function vendorCounts(int $vendorId): array
    {
        $st = Database::connection()->prepare(
            'SELECT status, COUNT(*) n FROM store_products WHERE vendor_id = :v AND deleted_at IS NULL GROUP BY status'
        );
        $st->execute(['v' => $vendorId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Seller save
    // ------------------------------------------------------------------

    /**
     * Create (when $existing is null) or update a product from the seller form.
     *
     * @param array<string, mixed> $vendor
     * @param array<string, mixed>|null $existing store_products row (already vendor-scoped)
     * @param array<string, mixed> $in POST
     * @return array{ok: bool, error?: string, id?: int, sent_to_review?: bool}
     */
    public static function saveFromVendor(array $vendor, ?array $existing, array $in): array
    {
        $vendorId = (int) $vendor['id'];
        if (($vendor['status'] ?? '') !== 'approved') {
            return ['ok' => false, 'error' => 'Products can be added once your seller account is approved.'];
        }
        if ($existing !== null && in_array($existing['status'], ['disabled'], true)) {
            return ['ok' => false, 'error' => 'This product was disabled by the eClinicPro team and can\'t be edited. Contact support.'];
        }

        // ---- Core fields ----
        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['name'] ?? '')) ?? '');
        if (mb_strlen($name) < 3) {
            return ['ok' => false, 'error' => 'Product name is required (at least 3 characters).'];
        }
        $categoryId = (int) ($in['category_id'] ?? 0);
        $category = $categoryId > 0 ? CatalogService::category($categoryId) : null;
        if ($category === null) {
            return ['ok' => false, 'error' => 'Choose a category.'];
        }
        $licences = CatalogService::vendorLicences($vendorId);
        $unchangedCategory = $existing !== null && (int) $existing['category_id'] === $categoryId;
        $why = CatalogService::listingBlockReason($category, $licences);
        if ($why !== null && !$unchangedCategory) {
            return ['ok' => false, 'error' => $why];
        }

        $brandId = (int) ($in['brand_id'] ?? 0);
        if (trim((string) ($in['brand_new'] ?? '')) !== '') {
            $brandId = (int) CatalogService::brandIdFor((string) $in['brand_new']);
        } elseif ($brandId > 0 && QueryBuilder::table('store_brands')->where('id', '=', $brandId)->first() === null) {
            $brandId = 0;
        }

        // Blank must NOT fall through as (int) 0 = "0%": make the seller choose.
        $gstRaw = trim((string) ($in['gst_bp'] ?? ''));
        $gst = (int) $gstRaw;
        if ($gstRaw === '' || !ctype_digit($gstRaw) || !array_key_exists($gst, CatalogService::GST_RATES_BP)) {
            return ['ok' => false, 'error' => 'Choose the GST rate for this product (ask your CA if unsure; it depends on the HSN code).'];
        }
        $hsn = preg_replace('/\D/', '', (string) ($in['hsn_code'] ?? '')) ?? '';
        if ($hsn !== '' && !preg_match('/^\d{4,8}$/', $hsn)) {
            return ['ok' => false, 'error' => 'HSN code should be 4–8 digits.'];
        }
        $returnWindow = trim((string) ($in['return_window_days'] ?? ''));

        $specs = [];
        foreach ((array) ($in['specs'] ?? []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label !== '' && $value !== '') {
                $specs[] = ['label' => mb_substr($label, 0, 80), 'value' => mb_substr($value, 0, 300)];
            }
        }

        $data = [
            'name' => mb_substr($name, 0, 255),
            'category_id' => $categoryId,
            'brand_id' => $brandId > 0 ? $brandId : null,
            'short_desc' => mb_substr(trim((string) ($in['short_desc'] ?? '')), 0, 500) ?: null,
            'description' => trim((string) ($in['description'] ?? '')) ?: null,   // plain text; escaped on output
            'specs_json' => $specs ? json_encode($specs, JSON_UNESCAPED_UNICODE) : null,
            'license_number' => mb_substr(trim((string) ($in['license_number'] ?? '')), 0, 80) ?: null,
            'hsn_code' => $hsn !== '' ? $hsn : null,
            'gst_bp' => $gst,
            'is_returnable' => !empty($in['is_returnable']) ? 1 : 0,
            'return_window_days' => $returnWindow === '' ? null : max(0, min(30, (int) $returnWindow)),
            'has_expiry' => !empty($in['has_expiry']) ? 1 : 0,
            'manufacturer' => mb_substr(trim((string) ($in['manufacturer'] ?? '')), 0, 190) ?: null,
            'country_of_origin' => mb_substr(trim((string) ($in['country_of_origin'] ?? '')), 0, 60) ?: 'India',
            'seo_title' => mb_substr(trim((string) ($in['seo_title'] ?? '')), 0, 190) ?: null,
            'seo_description' => mb_substr(trim((string) ($in['seo_description'] ?? '')), 0, 300) ?: null,
        ];

        // ---- Variants ----
        $variants = self::parseVariants((array) ($in['variants'] ?? []));
        if (isset($variants['error'])) {
            return ['ok' => false, 'error' => $variants['error']];
        }
        $variants = $variants['rows'];
        if (!array_filter($variants, static fn ($v) => $v['is_active'])) {
            return ['ok' => false, 'error' => 'Add at least one active variant (price and stock).'];
        }
        $skus = array_map('strtolower', array_column($variants, 'sku'));
        if (count($skus) !== count(array_unique($skus))) {
            return ['ok' => false, 'error' => 'Each variant needs a different SKU.'];
        }

        // ---- Secondary categories + goals ----
        $secondary = [];
        foreach ((array) ($in['secondary_ids'] ?? []) as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0 || $sid === $categoryId || isset($secondary[$sid])) {
                continue;
            }
            $sc = CatalogService::category($sid);
            if ($sc !== null && CatalogService::listingBlockReason($sc, $licences) === null) {
                $secondary[$sid] = true;
            }
        }
        $secondary = array_slice(array_keys($secondary), 0, 3);
        $validConcernIds = array_map('intval', array_column(CatalogService::concerns(), 'id'));
        $concernIds = array_values(array_intersect($validConcernIds, array_map('intval', (array) ($in['concern_ids'] ?? []))));

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $sentToReview = false;
            if ($existing === null) {
                $data['vendor_id'] = $vendorId;
                $data['slug'] = self::uniqueSlug($name);
                $data['status'] = 'draft';
                $data['regulatory_class'] = (string) $category['regulatory_class'];
                $productId = QueryBuilder::table('store_products')->insert($data);
            } else {
                $productId = (int) $existing['id'];
                if ($existing['status'] === 'live' && self::changedReviewedFields($existing, $data) && self::needsReview($category)) {
                    $data['status'] = 'pending_review';
                    $sentToReview = true;
                }
                if ((int) $existing['category_id'] !== $categoryId) {
                    $data['regulatory_class'] = (string) $category['regulatory_class'];
                }
                QueryBuilder::table('store_products')->where('id', '=', $productId)->update($data);
            }

            // Placement: primary + secondaries (store_products.category_id mirrors the primary).
            $pdo->prepare('DELETE FROM store_product_categories WHERE product_id = :p')->execute(['p' => $productId]);
            $ins = $pdo->prepare('INSERT INTO store_product_categories (product_id, category_id, is_primary) VALUES (:p, :c, :pr)');
            $ins->execute(['p' => $productId, 'c' => $categoryId, 'pr' => 1]);
            foreach ($secondary as $sid) {
                $ins->execute(['p' => $productId, 'c' => $sid, 'pr' => 0]);
            }

            $pdo->prepare('DELETE FROM store_product_concerns WHERE product_id = :p')->execute(['p' => $productId]);
            $insC = $pdo->prepare('INSERT INTO store_product_concerns (product_id, concern_id) VALUES (:p, :c)');
            foreach ($concernIds as $cid) {
                $insC->execute(['p' => $productId, 'c' => $cid]);
            }

            $skuError = self::saveVariants($productId, $vendorId, $variants);
            if ($skuError !== null) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => $skuError];
            }

            self::refreshDenormalized($productId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[ProductService::saveFromVendor] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not save the product. Please try again.'];
        }

        StoreAudit::log($existing === null ? 'product.create' : 'product.update', 'product', $productId, $existing, $data);

        return ['ok' => true, 'id' => $productId, 'sent_to_review' => $sentToReview];
    }

    /**
     * @param array<int|string, mixed> $rows
     * @return array{rows?: list<array<string, mixed>>, error?: string}
     */
    private static function parseVariants(array $rows): array
    {
        $out = [];
        $n = 0;
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $n++;
            $sku = strtoupper(trim((string) ($r['sku'] ?? '')));
            $title = trim((string) ($r['title'] ?? ''));
            $mrp = self::toPaise((string) ($r['mrp'] ?? ''));
            $price = self::toPaise((string) ($r['price'] ?? ''));
            $blank = $sku === '' && $title === '' && $mrp === null && $price === null;
            if ($blank && empty($r['id'])) {
                continue; // empty new row
            }
            $label = $title !== '' ? "Variant \"$title\"" : "Variant $n";
            if ($sku === '' || !preg_match('/^[A-Z0-9][A-Z0-9._\-\/]{0,79}$/', $sku)) {
                return ['error' => "$label: SKU is required (letters, numbers, - _ . /)."];
            }
            if ($mrp === null || $mrp <= 0) {
                return ['error' => "$label: enter the MRP."];
            }
            if ($price === null || $price <= 0) {
                return ['error' => "$label: enter the selling price."];
            }
            if ($price > $mrp) {
                return ['error' => "$label: selling price can't be higher than MRP."];
            }
            $stock = trim((string) ($r['stock_qty'] ?? '0'));
            if (!preg_match('/^\d{1,7}$/', $stock)) {
                return ['error' => "$label: stock must be a whole number."];
            }
            $out[] = [
                'id' => (int) ($r['id'] ?? 0),
                'sku' => $sku,
                'title' => $title !== '' ? mb_substr($title, 0, 190) : null,
                'mrp_paise' => $mrp,
                'price_paise' => $price,
                'stock_qty' => (int) $stock,
                'low_stock_threshold' => max(0, min(100000, (int) ($r['low_stock_threshold'] ?? 5))),
                'weight_g' => max(0, min(100000, (int) ($r['weight_g'] ?? 0))),
                'length_mm' => max(0, min(5000, (int) round((float) ($r['length_cm'] ?? 0) * 10))),
                'breadth_mm' => max(0, min(5000, (int) round((float) ($r['breadth_cm'] ?? 0) * 10))),
                'height_mm' => max(0, min(5000, (int) round((float) ($r['height_cm'] ?? 0) * 10))),
                'barcode' => mb_substr(trim((string) ($r['barcode'] ?? '')), 0, 40) ?: null,
                'is_active' => empty($r['remove']) ? 1 : 0,
            ];
        }

        return ['rows' => $out];
    }

    /**
     * Upserts variants. Variants missing from the form are deactivated, never
     * deleted (future orders will reference them). Stock changes are logged.
     *
     * @param list<array<string, mixed>> $rows
     */
    private static function saveVariants(int $productId, int $vendorId, array $rows): ?string
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT * FROM store_product_variants WHERE product_id = :p');
        $st->execute(['p' => $productId]);
        $current = [];
        foreach ($st->fetchAll() as $v) {
            $current[(int) $v['id']] = $v;
        }

        $dupCheck = $pdo->prepare('SELECT id FROM store_product_variants WHERE vendor_id = :v AND sku = :s AND id <> :id LIMIT 1');
        $seen = [];
        foreach ($rows as $row) {
            $dupCheck->execute(['v' => $vendorId, 's' => $row['sku'], 'id' => $row['id']]);
            if ($dupCheck->fetchColumn() !== false) {
                return 'SKU ' . $row['sku'] . ' is already used by another of your products.';
            }
            $fields = $row;
            unset($fields['id']);

            if ($row['id'] > 0 && isset($current[$row['id']])) {
                $old = $current[$row['id']];
                QueryBuilder::table('store_product_variants')->where('id', '=', $row['id'])->update($fields);
                $delta = (int) $row['stock_qty'] - (int) $old['stock_qty'];
                if ($delta !== 0) {
                    self::logStock($row['id'], $delta);
                }
                $seen[$row['id']] = true;
            } else {
                $fields['product_id'] = $productId;
                $fields['vendor_id'] = $vendorId;
                $id = QueryBuilder::table('store_product_variants')->insert($fields);
                if ((int) $row['stock_qty'] > 0) {
                    self::logStock($id, (int) $row['stock_qty']);
                }
                $seen[$id] = true;
            }
        }
        foreach ($current as $id => $v) {
            if (!isset($seen[$id]) && (int) $v['is_active'] === 1) {
                QueryBuilder::table('store_product_variants')->where('id', '=', $id)->update(['is_active' => 0]);
            }
        }

        return null;
    }

    private static function logStock(int $variantId, int $delta): void
    {
        $user = \App\Core\RequestContext::vendorUser();
        QueryBuilder::table('store_inventory_movements')->insert([
            'variant_id' => $variantId,
            'delta' => $delta,
            'reason' => 'manual',
            'ref_type' => 'product_form',
            'actor_type' => $user !== null ? 'vendor_user' : 'admin',
            'actor_id' => $user !== null ? (int) $user['id'] : (int) (\App\Core\RequestContext::superAdmin()['id'] ?? 0),
        ]);
    }

    /** Recompute listing columns from active variants. */
    public static function refreshDenormalized(int $productId): void
    {
        $st = Database::connection()->prepare(
            'UPDATE store_products p
                SET p.min_price_paise = COALESCE((SELECT MIN(v.price_paise) FROM store_product_variants v
                                                   WHERE v.product_id = :p1 AND v.is_active = 1), 0),
                    p.in_stock = EXISTS (SELECT 1 FROM store_product_variants v2
                                          WHERE v2.product_id = :p2 AND v2.is_active = 1 AND v2.stock_qty > v2.reserved_qty)
              WHERE p.id = :p3'
        );
        $st->execute(['p1' => $productId, 'p2' => $productId, 'p3' => $productId]);
    }

    // ------------------------------------------------------------------
    // Photos
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $files $_FILES['images'] (multiple)
     * @return array{ok: bool, error?: string, added?: int}
     */
    public static function addImages(array $product, array $files): array
    {
        $productId = (int) $product['id'];
        if ($product['status'] === 'disabled') {
            return ['ok' => false, 'error' => 'This product was disabled by the eClinicPro team.'];
        }
        $existing = QueryBuilder::table('store_product_images')->where('product_id', '=', $productId)->count();
        $names = (array) ($files['name'] ?? []);
        $added = 0;
        $errors = [];
        foreach (array_keys($names) as $i) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($existing + $added >= self::MAX_IMAGES) {
                $errors[] = 'Maximum ' . self::MAX_IMAGES . ' photos per product.';
                break;
            }
            $one = [
                'name' => $files['name'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'size' => $files['size'][$i] ?? 0,
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            ];
            $res = VendorService::storePublicImage($one, 'products/' . (int) $product['vendor_id'], 'p' . $productId, self::IMAGE_MAX_BYTES);
            if (!$res['ok']) {
                $errors[] = (string) $one['name'] . ': ' . $res['error'];
                continue;
            }
            $size = @getimagesize(\App\Core\Application::basePath() . '/public' . $res['path']);
            QueryBuilder::table('store_product_images')->insert([
                'product_id' => $productId,
                'path' => $res['path'],
                'alt' => mb_substr((string) $product['name'], 0, 190),
                'width' => $size ? min(65535, (int) $size[0]) : null,
                'height' => $size ? min(65535, (int) $size[1]) : null,
                'sort_order' => $existing + $added,
            ]);
            $added++;
        }
        if ($added > 0) {
            StoreAudit::log('product.images_add', 'product', $productId, null, ['added' => $added]);
            self::reviewAfterMediaChange($product);
        }
        if ($added === 0) {
            return ['ok' => false, 'error' => $errors ? implode(' ', $errors) : 'Choose one or more photos.'];
        }

        return ['ok' => true, 'added' => $added, 'error' => implode(' ', $errors)];
    }

    public static function deleteImage(array $product, int $imageId): bool
    {
        $img = QueryBuilder::table('store_product_images')
            ->where('id', '=', $imageId)
            ->where('product_id', '=', (int) $product['id'])
            ->first();
        if ($img === null) {
            return false;
        }
        QueryBuilder::table('store_product_images')->where('id', '=', $imageId)->delete();
        $abs = \App\Core\Application::basePath() . '/public' . $img['path'];
        if (str_starts_with((string) $img['path'], '/uploads/store/products/') && is_file($abs)) {
            @unlink($abs);
        }
        StoreAudit::log('product.image_delete', 'product', (int) $product['id']);
        self::reviewAfterMediaChange($product);

        return true;
    }

    public static function makeCover(array $product, int $imageId): bool
    {
        $pdo = Database::connection();
        $st = $pdo->prepare('SELECT id FROM store_product_images WHERE product_id = :p ORDER BY sort_order, id');
        $st->execute(['p' => (int) $product['id']]);
        $ids = array_map('intval', array_column($st->fetchAll(), 'id'));
        if (!in_array($imageId, $ids, true)) {
            return false;
        }
        $order = array_merge([$imageId], array_values(array_diff($ids, [$imageId])));
        $upd = $pdo->prepare('UPDATE store_product_images SET sort_order = :s WHERE id = :id');
        foreach ($order as $i => $id) {
            $upd->execute(['s' => $i, 'id' => $id]);
        }

        return true;
    }

    private static function reviewAfterMediaChange(array $product): void
    {
        if ($product['status'] !== 'live') {
            return;
        }
        $cat = CatalogService::category((int) $product['category_id']);
        if ($cat !== null && self::needsReview($cat)) {
            QueryBuilder::table('store_products')->where('id', '=', (int) $product['id'])->update(['status' => 'pending_review']);
            StoreAudit::log('product.back_to_review', 'product', (int) $product['id'], ['status' => 'live'], ['status' => 'pending_review']);
        }
    }

    // ------------------------------------------------------------------
    // Seller lifecycle
    // ------------------------------------------------------------------

    /**
     * What's still missing before a product can be submitted.
     *
     * @return list<string>
     */
    public static function submitProblems(array $vendor, array $loaded): array
    {
        $problems = [];
        $active = array_filter($loaded['variants'], static fn ($v) => (int) $v['is_active'] === 1);
        if (!$active) {
            $problems[] = 'Add at least one active variant.';
        }
        foreach ($active as $v) {
            if ((int) $v['weight_g'] <= 0) {
                $problems[] = 'Enter the packed weight for variant ' . ($v['title'] ?: $v['sku']) . ' (couriers charge by weight).';
            }
        }
        if (!$loaded['images']) {
            $problems[] = 'Add at least one product photo.';
        }
        if (empty($loaded['short_desc']) && empty($loaded['description'])) {
            $problems[] = 'Add a short description.';
        }
        $cat = CatalogService::category((int) $loaded['category_id']);
        if ($cat === null) {
            $problems[] = 'Choose a category.';
        } else {
            $why = CatalogService::listingBlockReason($cat, CatalogService::vendorLicences((int) $vendor['id']));
            if ($why !== null) {
                $problems[] = $why;
            }
            if (in_array((string) $cat['required_vendor_doc'], self::LICENCE_ON_LABEL, true) && empty($loaded['license_number'])) {
                $problems[] = 'Enter the licence / registration number printed on this product\'s label.';
            }
        }

        return $problems;
    }

    /** @return array{ok: bool, error?: string, live?: bool} */
    public static function submit(array $vendor, array $product): array
    {
        if (($vendor['status'] ?? '') !== 'approved') {
            return ['ok' => false, 'error' => 'Your seller account must be approved to publish products.'];
        }
        if (!in_array($product['status'], ['draft', 'rejected'], true)) {
            return ['ok' => false, 'error' => 'This product is already ' . str_replace('_', ' ', (string) $product['status']) . '.'];
        }
        $loaded = self::load((int) $product['id']);
        $problems = $loaded !== null ? self::submitProblems($vendor, $loaded) : ['Product not found.'];
        if ($problems) {
            return ['ok' => false, 'error' => implode(' ', $problems)];
        }
        $cat = CatalogService::category((int) $product['category_id']);
        $goLive = $cat !== null && !self::needsReview($cat);
        $upd = $goLive
            ? ['status' => 'live', 'review_note' => null, 'published_at' => $product['published_at'] ?? date('Y-m-d H:i:s')]
            : ['status' => 'pending_review', 'review_note' => null];
        QueryBuilder::table('store_products')->where('id', '=', (int) $product['id'])->update($upd);
        StoreAudit::log('product.submit', 'product', (int) $product['id'], ['status' => $product['status']], $upd);

        return ['ok' => true, 'live' => $goLive];
    }

    /** Seller hides (archives) or restores a product. */
    public static function setArchived(array $product, bool $archive): bool
    {
        if ($archive && in_array($product['status'], ['archived', 'disabled'], true)) {
            return false;
        }
        if (!$archive && $product['status'] !== 'archived') {
            return false;
        }
        $to = $archive ? 'archived' : 'draft';
        QueryBuilder::table('store_products')->where('id', '=', (int) $product['id'])->update(['status' => $to]);
        StoreAudit::log('product.' . ($archive ? 'archive' : 'unarchive'), 'product', (int) $product['id'], ['status' => $product['status']], ['status' => $to]);

        return true;
    }

    // ------------------------------------------------------------------
    // Admin
    // ------------------------------------------------------------------

    /** @return array{rows: list<array<string, mixed>>, counts: array<string, int>} */
    public static function adminList(string $status, string $q, int $vendorId): array
    {
        $pdo = Database::connection();
        $counts = [];
        foreach ($pdo->query('SELECT status, COUNT(*) n FROM store_products WHERE deleted_at IS NULL GROUP BY status')->fetchAll() as $r) {
            $counts[(string) $r['status']] = (int) $r['n'];
        }
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($status !== '') {
            $where[] = 'p.status = :st';
            $params['st'] = $status;
        }
        if ($vendorId > 0) {
            $where[] = 'p.vendor_id = :vid';
            $params['vid'] = $vendorId;
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.slug LIKE :q2 OR EXISTS (SELECT 1 FROM store_product_variants sv WHERE sv.product_id = p.id AND sv.sku LIKE :q3))';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }
        $sql = 'SELECT p.*, v.display_name AS vendor_name, c.name AS category_name, c.listing_mode,
                       (SELECT path FROM store_product_images i WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS cover
                  FROM store_products p
                  JOIN store_vendors v ON v.id = p.vendor_id
                  LEFT JOIN store_categories c ON c.id = p.category_id
                 WHERE ' . implode(' AND ', $where) . '
                 ORDER BY p.updated_at DESC LIMIT 300';
        $st = $pdo->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'counts' => $counts];
    }

    public static function pendingReviewCount(): int
    {
        try {
            return (int) Database::connection()
                ->query("SELECT COUNT(*) FROM store_products WHERE status = 'pending_review' AND deleted_at IS NULL")
                ->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{ok: bool, error?: string} */
    public static function adminDecision(int $productId, string $action, string $note, string $class): array
    {
        $p = QueryBuilder::table('store_products')->where('id', '=', $productId)->first();
        if ($p === null) {
            return ['ok' => false, 'error' => 'Product not found.'];
        }
        $from = (string) $p['status'];
        $upd = [];
        switch ($action) {
            case 'approve':
                if (!in_array($from, ['pending_review', 'rejected', 'disabled'], true)) {
                    return ['ok' => false, 'error' => "Can't approve a product that is $from."];
                }
                if (!array_key_exists($class, CatalogService::CLASS_LABELS) || $class === 'drug_restricted') {
                    return ['ok' => false, 'error' => 'Choose a valid regulatory class (drug-restricted products cannot go live).'];
                }
                $upd = ['status' => 'live', 'review_note' => null, 'regulatory_class' => $class,
                    'published_at' => $p['published_at'] ?? date('Y-m-d H:i:s')];
                break;
            case 'reject':
                if ($from !== 'pending_review') {
                    return ['ok' => false, 'error' => 'Only products awaiting review can be sent back.'];
                }
                if (trim($note) === '') {
                    return ['ok' => false, 'error' => 'Tell the seller what to fix.'];
                }
                $upd = ['status' => 'rejected', 'review_note' => mb_substr(trim($note), 0, 500)];
                break;
            case 'disable':
                if (!in_array($from, ['live', 'pending_review'], true)) {
                    return ['ok' => false, 'error' => "Can't disable a product that is $from."];
                }
                if (trim($note) === '') {
                    return ['ok' => false, 'error' => 'Give a reason (the seller will see it).'];
                }
                $upd = ['status' => 'disabled', 'review_note' => mb_substr(trim($note), 0, 500)];
                break;
            default:
                return ['ok' => false, 'error' => 'Unknown action.'];
        }
        QueryBuilder::table('store_products')->where('id', '=', $productId)->update($upd);
        StoreAudit::log('product.' . $action, 'product', $productId, ['status' => $from], $upd);

        return ['ok' => true];
    }

    public static function adminToggleFeatured(int $productId): void
    {
        Database::connection()->prepare('UPDATE store_products SET is_featured = 1 - is_featured WHERE id = :id')
            ->execute(['id' => $productId]);
        StoreAudit::log('product.toggle_featured', 'product', $productId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Review is needed when the global setting says so, or the category is review-only. */
    public static function needsReview(array $category): bool
    {
        return StoreSettings::get('store_require_product_approval', '1') === '1'
            || ($category['listing_mode'] ?? '') === 'review';
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $data
     */
    private static function changedReviewedFields(array $existing, array $data): bool
    {
        foreach (self::REVIEWED_FIELDS as $f) {
            if ((string) ($existing[$f] ?? '') !== (string) ($data[$f] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /** "1,299.50" / "1299" → paise; null when blank or invalid. */
    public static function toPaise(string $rupees): ?int
    {
        $s = str_replace([',', '₹', ' '], '', trim($rupees));
        if ($s === '' || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $s)) {
            return null;
        }

        return (int) round(((float) $s) * 100);
    }

    public static function rupees(int $paise): string
    {
        return $paise % 100 === 0 ? number_format($paise / 100) : number_format($paise / 100, 2);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = substr(VendorService::slugify($name) ?: 'product', 0, 170);
        $slug = $base;
        $i = 2;
        while (QueryBuilder::table('store_products')->where('slug', '=', $slug)->first() !== null) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
