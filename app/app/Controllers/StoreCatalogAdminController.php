<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\CatalogService;
use App\Services\Store\ProductService;
use App\Services\Store\StoreAudit;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /admin/store/{products,categories,brands} — catalog moderation (super-admin).
 */
final class StoreCatalogAdminController
{
    private const PRODUCT_STATUSES = ['draft', 'pending_review', 'live', 'rejected', 'disabled', 'archived'];

    // ---- Products -------------------------------------------------------------

    public function products(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'pending_review');
        if ($status !== '' && !in_array($status, self::PRODUCT_STATUSES, true)) {
            $status = '';
        }
        $q = trim((string) ($request->query['q'] ?? ''));
        $vendorId = (int) ($request->query['vendor'] ?? 0);
        try {
            $data = ProductService::adminList($status, $q, $vendorId);
            $missing = false;
        } catch (\Throwable $e) {
            error_log('[StoreCatalogAdmin::products] ' . $e->getMessage());
            $data = ['rows' => [], 'counts' => []];
            $missing = true;
        }

        return $this->render('admin/store_products', [
            'rows' => $data['rows'], 'counts' => $data['counts'], 'status' => $status, 'q' => $q,
            'vendorId' => $vendorId, 'tableMissing' => $missing,
        ]);
    }

    public function productDetail(Request $request, string $id): Response
    {
        $product = ProductService::load((int) $id);
        if ($product === null) {
            return Response::html('Product not found', 404);
        }
        $vendor = VendorService::find((int) $product['vendor_id']);
        $category = CatalogService::category((int) $product['category_id']);
        $dept = $category !== null ? CatalogService::category((int) $category['parent_id']) : null;

        $names = static function (array $ids, string $table): array {
            if (!$ids) {
                return [];
            }
            $in = implode(',', array_map('intval', $ids));

            return array_column(Database::connection()->query("SELECT name FROM $table WHERE id IN ($in)")->fetchAll(), 'name');
        };

        return $this->render('admin/store_product_detail', [
            'product' => $product,
            'vendor' => $vendor,
            'category' => $category,
            'dept' => $dept,
            'brand' => !empty($product['brand_id']) ? QueryBuilder::table('store_brands')->where('id', '=', (int) $product['brand_id'])->first() : null,
            'secondaryNames' => $names($product['secondary_ids'], 'store_categories'),
            'concernNames' => $names($product['concern_ids'], 'store_concerns'),
            'licences' => $vendor !== null ? CatalogService::vendorLicences((int) $vendor['id']) : [],
            'problems' => $vendor !== null ? ProductService::submitProblems($vendor, $product) : [],
        ]);
    }

    public function productDecision(Request $request, string $id): Response
    {
        $result = ProductService::adminDecision(
            (int) $id,
            (string) ($request->post['action'] ?? ''),
            (string) ($request->post['note'] ?? ''),
            (string) ($request->post['regulatory_class'] ?? ''),
        );
        $this->flash($result, 'Product updated.');
        $next = (string) ($request->post['next'] ?? '');

        return Response::redirect($result['ok'] && $next === 'queue' ? '/admin/store/products?status=pending_review' : '/admin/store/products/' . (int) $id);
    }

    public function productFeature(Request $request, string $id): Response
    {
        ProductService::adminToggleFeatured((int) $id);

        return Response::redirect('/admin/store/products/' . (int) $id);
    }

    // ---- Categories -----------------------------------------------------------

    public function categories(Request $request): Response
    {
        try {
            $tree = CatalogService::tree(false);
            $counts = [];
            foreach (Database::connection()->query(
                "SELECT pc.category_id, COUNT(*) n FROM store_product_categories pc
                   JOIN store_products p ON p.id = pc.product_id AND p.status = 'live'
                  GROUP BY pc.category_id"
            )->fetchAll() as $r) {
                $counts[(int) $r['category_id']] = (int) $r['n'];
            }
        } catch (\Throwable $e) {
            error_log('[StoreCatalogAdmin::categories] ' . $e->getMessage());
            $tree = [];
            $counts = [];
        }
        $dept = (string) ($request->query['dept'] ?? '');

        return $this->render('admin/store_categories', [
            'tree' => $tree, 'counts' => $counts, 'dept' => $dept,
            'classes' => CatalogService::CLASS_LABELS, 'docTypes' => VendorService::DOC_TYPES,
        ]);
    }

    public function saveCategory(Request $request, string $id): Response
    {
        $cat = CatalogService::category((int) $id);
        if ($cat === null) {
            return Response::html('Not found', 404);
        }
        $p = $request->post;
        $upd = [
            'name' => mb_substr(trim((string) ($p['name'] ?? $cat['name'])), 0, 160) ?: $cat['name'],
            'is_active' => !empty($p['is_active']) ? 1 : 0,
            'sort_order' => max(0, min(9999, (int) ($p['sort_order'] ?? $cat['sort_order']))),
            'description' => trim((string) ($p['description'] ?? '')) ?: null,
        ];
        if ((int) $cat['parent_id'] !== 0) {
            $class = (string) ($p['regulatory_class'] ?? $cat['regulatory_class']);
            $mode = (string) ($p['listing_mode'] ?? $cat['listing_mode']);
            $doc = (string) ($p['required_vendor_doc'] ?? '');
            $upd['regulatory_class'] = array_key_exists($class, CatalogService::CLASS_LABELS) ? $class : $cat['regulatory_class'];
            $upd['listing_mode'] = in_array($mode, ['open', 'review', 'blocked'], true) ? $mode : $cat['listing_mode'];
            $upd['required_vendor_doc'] = array_key_exists($doc, VendorService::DOC_TYPES) ? $doc : null;
            $upd['review_reason'] = mb_substr(trim((string) ($p['review_reason'] ?? '')), 0, 255) ?: null;
            $upd['no_promotion'] = !empty($p['no_promotion']) ? 1 : 0;
        }
        QueryBuilder::table('store_categories')->where('id', '=', (int) $id)->update($upd);
        StoreAudit::log('category.update', 'category', (int) $id, $cat, $upd);
        SessionFlash::put('store_ok', 'Saved "' . $upd['name'] . '".');
        $dept = (int) $cat['parent_id'] === 0 ? $cat['slug'] : (CatalogService::category((int) $cat['parent_id'])['slug'] ?? '');

        return Response::redirect('/admin/store/categories?dept=' . rawurlencode((string) $dept) . '#cat-' . (int) $id);
    }

    // ---- Brands ---------------------------------------------------------------

    public function brands(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()->query(
                "SELECT b.*, (SELECT COUNT(*) FROM store_products p WHERE p.brand_id = b.id AND p.deleted_at IS NULL) AS product_count
                   FROM store_brands b ORDER BY b.is_active DESC, b.name"
            )->fetchAll();
        } catch (\Throwable $e) {
            error_log('[StoreCatalogAdmin::brands] ' . $e->getMessage());
        }

        return $this->render('admin/store_brands', ['rows' => $rows]);
    }

    public function saveBrand(Request $request): Response
    {
        $id = (int) ($request->post['id'] ?? 0);
        $name = trim((string) ($request->post['name'] ?? ''));
        if ($name === '') {
            SessionFlash::put('store_err', 'Brand name is required.');

            return Response::redirect('/admin/store/brands');
        }
        if ($id > 0) {
            $upd = ['name' => mb_substr($name, 0, 160), 'is_active' => !empty($request->post['is_active']) ? 1 : 0,
                'is_featured' => !empty($request->post['is_featured']) ? 1 : 0];
            QueryBuilder::table('store_brands')->where('id', '=', $id)->update($upd);
            StoreAudit::log('brand.update', 'brand', $id, null, $upd);
        } else {
            CatalogService::brandIdFor($name);
        }
        SessionFlash::put('store_ok', 'Brand saved.');

        return Response::redirect('/admin/store/brands');
    }

    // ---- Helpers --------------------------------------------------------------

    /** @param array{ok: bool, error?: string} $result */
    private function flash(array $result, string $success): void
    {
        SessionFlash::put($result['ok'] ? 'store_ok' : 'store_err', $result['ok'] ? $success : ($result['error'] ?? 'Something went wrong.'));
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
