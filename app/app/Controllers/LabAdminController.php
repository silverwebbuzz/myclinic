<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\LabProductDeletionService;
use App\Support\View;
use PDO;

/**
 * LabAdminController — /admin/lab/* (super-admin only).
 *
 * Manages the Thyrocare-sourced lab catalog:
 *   - Products  (lab_products + current lab_product_pricing row)
 *   - Categories(lab_categories)
 *   - Coupons   (lab_coupons — the logged-in "order here, get more" discount)
 *
 * Pricing model: public sees mrp (struck) + offer_rate (= thyrocare.com). A
 * logged-in coupon gives an extra LEAST(coupon_pct, product.max_discount_pct).
 * Catalog rows are seeded from build_lab_seed.py; admin edits price/flags here.
 */
final class LabAdminController
{
    private const PER_PAGE = 40;

    // ---- Products ----------------------------------------------------------

    public function products(Request $request): Response
    {
        $pdo = Database::connection();

        $q    = trim((string) ($request->query['q'] ?? ''));
        $type = strtoupper((string) ($request->query['type'] ?? ''));
        if (!in_array($type, ['TEST', 'PROFILE', 'OFFER'], true)) {
            $type = '';
        }
        $categoryId = (int) ($request->query['category'] ?? 0);
        $page = max(1, (int) ($request->query['page'] ?? 1));

        $where  = [];
        $params = [];
        if ($q !== '') {
            // Match the product's own name/code/test-code, AND products that
            // CONTAIN a parameter or belong to a CATEGORY matching the term —
            // so "diabetes" (a category) or "HbA1c" (a contained test) both
            // surface the relevant packages, not just exact-name hits.
            //
            // NOTE: the app runs PDO with EMULATE_PREPARES=false (native
            // prepares), which does NOT allow one named placeholder to be
            // reused across multiple positions — doing so throws HY093 and the
            // whole query silently fails (0 results). So every occurrence of
            // the search term gets its OWN placeholder + bound value.
            $like = '%' . $q . '%';
            $where[] = '(lp.name LIKE :q1 OR lp.code LIKE :q2 OR lp.thyrocare_code LIKE :q3
                OR EXISTS (
                    SELECT 1 FROM lab_product_parameters lpp_s
                    JOIN lab_parameters par_s ON par_s.id = lpp_s.parameter_id
                    WHERE lpp_s.product_id = lp.id AND par_s.name LIKE :q4
                )
                OR EXISTS (
                    SELECT 1 FROM lab_product_categories lpc_s
                    JOIN lab_categories c_s ON c_s.id = lpc_s.category_id
                    WHERE lpc_s.product_id = lp.id AND c_s.name LIKE :q5
                ))';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }
        if ($type !== '') {
            $where[] = 'lp.product_type = :type';
            $params[':type'] = $type;
        }
        // Category filter: restrict to products linked to the chosen category.
        $catJoin = '';
        if ($categoryId > 0) {
            $catJoin = 'JOIN lab_product_categories lpc_f ON lpc_f.product_id = lp.id AND lpc_f.category_id = :cat';
            $params[':cat'] = $categoryId;
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $rows = [];
        $total = 0;
        $allCategories = [];
        $tableMissing = false;
        try {
            // Category dropdown options (only those with products).
            $allCategories = $pdo->query(
                'SELECT c.id, c.name, COUNT(lpc.product_id) AS n
                   FROM lab_categories c
                   JOIN lab_product_categories lpc ON lpc.category_id = c.id
                  GROUP BY c.id ORDER BY c.name ASC'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT lp.id) FROM lab_products lp $catJoin $whereSql");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $offset = ($page - 1) * self::PER_PAGE;
            // Current price = the row with the latest effective_from per product.
            // Categories are aggregated into a comma list for the column.
            $sql = "SELECT lp.*,
                           pr.mrp, pr.offer_rate, pr.incentive_pct, pr.incentive_amt,
                           pr.max_discount_pct, pr.id AS pricing_id,
                           (SELECT GROUP_CONCAT(c2.name ORDER BY c2.name SEPARATOR ', ')
                              FROM lab_product_categories lpc2
                              JOIN lab_categories c2 ON c2.id = lpc2.category_id
                             WHERE lpc2.product_id = lp.id) AS category_names
                    FROM lab_products lp
                    $catJoin
                    LEFT JOIN lab_product_pricing pr
                           ON pr.id = (
                               SELECT p2.id FROM lab_product_pricing p2
                               WHERE p2.product_id = lp.id
                               ORDER BY p2.effective_from DESC, p2.id DESC LIMIT 1
                           )
                    $whereSql
                    ORDER BY lp.is_featured DESC, lp.booked_count DESC, lp.name ASC
                    LIMIT " . self::PER_PAGE . " OFFSET " . (int) $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $tableMissing = !$this->tableExists('lab_products');
        }

        return Response::html(View::render('admin/lab_products', [
            'admin'        => RequestContext::superAdmin(),
            'csrf'         => CsrfService::token(),
            'products'     => $rows,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'pages'        => (int) ceil(max(1, $total) / self::PER_PAGE),
            'q'            => $q,
            'type'         => $type,
            'categoryId'   => $categoryId,
            'allCategories'=> $allCategories,
            'tableMissing' => $tableMissing,
            'message'      => $request->query['message'] ?? null,
        ]));
    }

    /** GET /admin/lab/products/{id} — detail: parameters + full price history. */
    public function productDetail(Request $request, string $id): Response
    {
        $pdo = Database::connection();
        $pid = (int) $id;

        $product = null;
        $parameters = [];
        $categories = [];
        $priceHistory = [];
        try {
            $stmt = $pdo->prepare('SELECT * FROM lab_products WHERE id = ?');
            $stmt->execute([$pid]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($product) {
                $pstmt = $pdo->prepare(
                    'SELECT par.group_name, par.name
                       FROM lab_product_parameters lpp
                       JOIN lab_parameters par ON par.id = lpp.parameter_id
                      WHERE lpp.product_id = ?
                      ORDER BY par.group_name, par.name'
                );
                $pstmt->execute([$pid]);
                $parameters = $pstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $cstmt = $pdo->prepare(
                    'SELECT c.name FROM lab_product_categories lpc
                       JOIN lab_categories c ON c.id = lpc.category_id
                      WHERE lpc.product_id = ? ORDER BY c.name'
                );
                $cstmt->execute([$pid]);
                $categories = array_column($cstmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'name');

                $hstmt = $pdo->prepare(
                    'SELECT * FROM lab_product_pricing WHERE product_id = ?
                      ORDER BY effective_from DESC, id DESC'
                );
                $hstmt->execute([$pid]);
                $priceHistory = $hstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Throwable $e) { /* table missing */ }

        if (!$product) {
            return Response::redirect('/admin/lab/products?message=not_found');
        }

        return Response::html(View::render('admin/lab_product_detail', [
            'admin'        => RequestContext::superAdmin(),
            'csrf'         => CsrfService::token(),
            'product'      => $product,
            'parameters'   => $parameters,
            'categories'   => $categories,
            'priceHistory' => $priceHistory,
            'current'      => $priceHistory[0] ?? null,
            'message'      => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/lab/products/{id} — update flags + price (new dated row). */
    public function saveProduct(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/products');
        }
        $pid = (int) $id;
        $pdo = Database::connection();

        $isActive   = isset($request->post['is_active']) ? 1 : 0;
        $isFeatured = isset($request->post['is_featured']) ? 1 : 0;
        $sortOrder  = (int) ($request->post['sort_order'] ?? 100);
        $thyroCode  = trim((string) ($request->post['thyrocare_code'] ?? '')) ?: null;

        try {
            $pdo->prepare(
                'UPDATE lab_products
                    SET is_active = ?, is_featured = ?, sort_order = ?, thyrocare_code = ?
                  WHERE id = ?'
            )->execute([$isActive, $isFeatured, $sortOrder, $thyroCode, $pid]);

            // Price change → insert a NEW pricing row (history preserved) only
            // when a value actually differs from the current row.
            $mrp        = $this->money($request->post['mrp'] ?? null);
            $offerRate  = $this->money($request->post['offer_rate'] ?? null);
            $maxDisc    = max(0, min(100, (int) ($request->post['max_discount_pct'] ?? 0)));
            $incentive  = $request->post['incentive_pct'] ?? null;
            $incentive  = ($incentive === '' || $incentive === null) ? null : (float) $incentive;

            $cur = $pdo->prepare(
                'SELECT mrp, offer_rate, max_discount_pct, incentive_pct
                   FROM lab_product_pricing WHERE product_id = ?
                  ORDER BY effective_from DESC, id DESC LIMIT 1'
            );
            $cur->execute([$pid]);
            $c = $cur->fetch(PDO::FETCH_ASSOC) ?: null;

            $changed = !$c
                || (float) $c['mrp']              !== (float) $mrp
                || (float) $c['offer_rate']       !== (float) $offerRate
                || (int)   $c['max_discount_pct'] !== $maxDisc
                || (($c['incentive_pct'] === null ? null : (float) $c['incentive_pct']) !== $incentive);

            if ($changed) {
                $pdo->prepare(
                    'INSERT INTO lab_product_pricing
                        (product_id, mrp, offer_rate, incentive_pct, max_discount_pct, effective_from)
                     VALUES (?, ?, ?, ?, ?, CURRENT_DATE)'
                )->execute([$pid, $mrp, $offerRate, $incentive, $maxDisc]);
            }
        } catch (\Throwable $e) {
            return Response::redirect('/admin/lab/products/' . $pid . '?message=save_error');
        }

        return Response::redirect('/admin/lab/products/' . $pid . '?message=saved');
    }

    /** POST /admin/lab/products/{id}/toggle — flip is_active. */
    public function toggleProduct(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/products');
        }
        try {
            Database::connection()
                ->prepare('UPDATE lab_products SET is_active = 1 - is_active WHERE id = ?')
                ->execute([(int) $id]);
        } catch (\Throwable $e) { /* ignore */ }

        return Response::redirect($this->listReturnUrl($request, 'toggled'));
    }

    /** POST /admin/lab/products/{id}/feature — flip is_featured. */
    public function toggleFeatured(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/products');
        }
        try {
            Database::connection()
                ->prepare('UPDATE lab_products SET is_featured = 1 - is_featured WHERE id = ?')
                ->execute([(int) $id]);
        } catch (\Throwable $e) { /* ignore */ }

        return Response::redirect($this->listReturnUrl($request, 'featured_toggled'));
    }

    // ---- Deletion ----------------------------------------------------------

    /**
     * POST /admin/lab/products/{id}/delete — permanently remove one product.
     *
     * Guarded by a typed-name confirmation, same as the clinic-delete flow's
     * slug confirmation: the catalog has near-identical package names (three
     * "HEALTHY 2026 COUPLE PACKAGE WITH ECG" rows, for instance), so a stray
     * click on the wrong row is a real risk.
     */
    public function deleteProduct(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/products');
        }
        $pid = (int) $id;

        $stmt = Database::connection()->prepare('SELECT name FROM lab_products WHERE id = ?');
        $stmt->execute([$pid]);
        $name = $stmt->fetchColumn();
        if ($name === false) {
            return Response::redirect('/admin/lab/products?message=not_found');
        }

        $confirm = trim((string) ($request->post['confirm_name'] ?? ''));
        if (strcasecmp($confirm, (string) $name) !== 0) {
            return Response::redirect('/admin/lab/products/' . $pid . '?message=confirm_name_mismatch');
        }

        try {
            LabProductDeletionService::deleteByIds([$pid]);
        } catch (\Throwable $e) {
            return Response::redirect('/admin/lab/products/' . $pid . '?message=delete_failed');
        }

        return Response::redirect('/admin/lab/products?message=product_deleted');
    }

    /** POST /admin/lab/products/bulk-delete — remove the checked rows. */
    public function bulkDeleteProducts(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/products');
        }
        $ids = $request->post['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return Response::redirect($this->listReturnUrl($request, 'nothing_selected'));
        }

        try {
            $report = LabProductDeletionService::deleteByIds($ids);
        } catch (\Throwable $e) {
            return Response::redirect($this->listReturnUrl($request, 'delete_failed'));
        }

        return Response::redirect($this->listReturnUrl($request, 'deleted_' . $report['products']));
    }

    /**
     * GET /admin/lab/products/cleanup — paste a list of names your merchant
     * account can't fulfil, preview exactly what matches, then delete.
     *
     * Two-step by design: the preview step is what makes a 30-line paste safe,
     * because it shows unmatched lines (typos) BEFORE anything is destroyed.
     */
    public function cleanup(Request $request): Response
    {
        return Response::html(View::render('admin/lab_cleanup', [
            'admin'     => RequestContext::superAdmin(),
            'csrf'      => CsrfService::token(),
            'raw'       => '',
            'matched'   => [],
            'unmatched' => [],
            'previewed' => false,
            'message'   => $request->query['message'] ?? null,
            'report'    => null,
        ]));
    }

    /** POST /admin/lab/products/cleanup — action=preview | action=delete. */
    public function cleanupRun(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/products/cleanup');
        }

        $raw    = (string) ($request->post['names'] ?? '');
        $action = (string) ($request->post['action'] ?? 'preview');
        $lines  = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        $resolved = LabProductDeletionService::resolveNames($lines);
        $report   = null;

        if ($action === 'delete') {
            $ids = [];
            foreach ($resolved['matched'] as $hits) {
                foreach ($hits as $hit) {
                    $ids[] = $hit['id'];
                }
            }
            if ($ids !== []) {
                try {
                    $report = LabProductDeletionService::deleteByIds($ids);
                } catch (\Throwable $e) {
                    return Response::redirect('/admin/lab/products/cleanup?message=delete_failed');
                }
                // Re-resolve so the screen shows what's LEFT (should be nothing
                // matched, and any typo lines still listed as unmatched).
                $resolved = LabProductDeletionService::resolveNames($lines);
            }
        }

        return Response::html(View::render('admin/lab_cleanup', [
            'admin'     => RequestContext::superAdmin(),
            'csrf'      => CsrfService::token(),
            'raw'       => $raw,
            'matched'   => $resolved['matched'],
            'unmatched' => $resolved['unmatched'],
            'previewed' => true,
            'message'   => null,
            'report'    => $report,
        ]));
    }

    /**
     * Rebuild the /admin/lab/products URL preserving the current filter/page
     * so a row toggle returns you to the same view instead of the top.
     */
    private function listReturnUrl(Request $request, string $message): string
    {
        $keep = [];
        foreach (['q', 'type', 'category', 'page'] as $k) {
            $v = $request->post['return_' . $k] ?? '';
            if ($v !== '') {
                $keep[$k] = $v;
            }
        }
        $keep['message'] = $message;
        return '/admin/lab/products?' . http_build_query($keep);
    }

    // ---- Categories --------------------------------------------------------

    public function categories(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()->query(
                'SELECT c.*, (
                     SELECT COUNT(*) FROM lab_product_categories lpc WHERE lpc.category_id = c.id
                 ) AS product_count
                 FROM lab_categories c
                 ORDER BY c.sort_order ASC, c.name ASC'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { /* table missing */ }

        return Response::html(View::render('admin/lab_categories', [
            'admin'        => RequestContext::superAdmin(),
            'csrf'         => CsrfService::token(),
            'categories'   => $rows,
            'tableMissing' => $rows === [] && !$this->tableExists('lab_categories'),
            'message'      => $request->query['message'] ?? null,
        ]));
    }

    public function saveCategory(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/categories');
        }
        $id   = (int) ($request->post['id'] ?? 0);
        $name = trim((string) ($request->post['name'] ?? ''));
        if ($name === '') {
            return Response::redirect('/admin/lab/categories?message=missing_fields');
        }
        $slug = $this->slugify((string) ($request->post['slug'] ?? '') ?: $name);
        $data = [
            'name'       => $name,
            'is_active'  => isset($request->post['is_active']) ? 1 : 0,
            'sort_order' => (int) ($request->post['sort_order'] ?? 100),
        ];
        $pdo = Database::connection();
        try {
            if ($id > 0) {
                $pdo->prepare('UPDATE lab_categories SET name = ?, is_active = ?, sort_order = ? WHERE id = ?')
                    ->execute([$data['name'], $data['is_active'], $data['sort_order'], $id]);
            } else {
                $pdo->prepare('INSERT INTO lab_categories (name, slug, is_active, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$data['name'], $slug, $data['is_active'], $data['sort_order']]);
            }
        } catch (\Throwable $e) {
            return Response::redirect('/admin/lab/categories?message=save_error');
        }
        return Response::redirect('/admin/lab/categories?message=saved');
    }

    public function toggleCategory(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/categories');
        }
        try {
            Database::connection()
                ->prepare('UPDATE lab_categories SET is_active = 1 - is_active WHERE id = ?')
                ->execute([(int) $id]);
        } catch (\Throwable $e) { /* ignore */ }
        return Response::redirect('/admin/lab/categories?message=toggled');
    }

    // ---- Coupons -----------------------------------------------------------

    public function coupons(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()
                ->query('SELECT * FROM lab_coupons ORDER BY discount_pct ASC, code ASC')
                ->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { /* table missing */ }

        return Response::html(View::render('admin/lab_coupons', [
            'admin'        => RequestContext::superAdmin(),
            'csrf'         => CsrfService::token(),
            'coupons'      => $rows,
            'tableMissing' => $rows === [] && !$this->tableExists('lab_coupons'),
            'message'      => $request->query['message'] ?? null,
        ]));
    }

    public function saveCoupon(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/coupons');
        }
        $id   = (int) ($request->post['id'] ?? 0);
        $code = strtoupper(trim((string) ($request->post['code'] ?? '')));
        $pct  = max(0, min(100, (int) ($request->post['discount_pct'] ?? 0)));
        if ($code === '' || $pct <= 0) {
            return Response::redirect('/admin/lab/coupons?message=missing_fields');
        }
        $data = [
            'discount_pct'   => $pct,
            'description'    => trim((string) ($request->post['description'] ?? '')) ?: null,
            'requires_login' => isset($request->post['requires_login']) ? 1 : 0,
            'is_active'      => isset($request->post['is_active']) ? 1 : 0,
            'starts_at'      => trim((string) ($request->post['starts_at'] ?? '')) ?: null,
            'ends_at'        => trim((string) ($request->post['ends_at'] ?? '')) ?: null,
        ];
        $pdo = Database::connection();
        try {
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE lab_coupons SET discount_pct = ?, description = ?, requires_login = ?,
                            is_active = ?, starts_at = ?, ends_at = ? WHERE id = ?'
                )->execute([
                    $data['discount_pct'], $data['description'], $data['requires_login'],
                    $data['is_active'], $data['starts_at'], $data['ends_at'], $id,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO lab_coupons (code, discount_pct, description, requires_login, is_active, starts_at, ends_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $code, $data['discount_pct'], $data['description'], $data['requires_login'],
                    $data['is_active'], $data['starts_at'], $data['ends_at'],
                ]);
            }
        } catch (\Throwable $e) {
            return Response::redirect('/admin/lab/coupons?message=save_error');
        }
        return Response::redirect('/admin/lab/coupons?message=saved');
    }

    public function toggleCoupon(Request $request, string $id): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/lab/coupons');
        }
        try {
            Database::connection()
                ->prepare('UPDATE lab_coupons SET is_active = 1 - is_active WHERE id = ?')
                ->execute([(int) $id]);
        } catch (\Throwable $e) { /* ignore */ }
        return Response::redirect('/admin/lab/coupons?message=toggled');
    }

    // ---- helpers -----------------------------------------------------------

    private function money($raw): float
    {
        $v = (float) preg_replace('/[^0-9.]/', '', (string) $raw);
        return $v < 0 ? 0.0 : round($v, 2);
    }

    private function slugify(string $raw): string
    {
        $s = strtolower(trim($raw));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    private function tableExists(string $table): bool
    {
        try {
            Database::connection()->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
