<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\CouponService;
use App\Services\Store\ReviewService;
use App\Services\Store\StoreAudit;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/** /admin/store/{dashboard,coupons,reviews,banners} — P11 (super-admin). */
final class StoreMerchAdminController
{
    // ---- Dashboard ------------------------------------------------------------

    public function dashboard(Request $request): Response
    {
        $pdo = Database::connection();
        $one = static function (string $sql) use ($pdo): int {
            try {
                return (int) $pdo->query($sql)->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };
        $all = static function (string $sql) use ($pdo): array {
            try {
                return $pdo->query($sql)->fetchAll();
            } catch (\Throwable) {
                return [];
            }
        };

        $kpi = [
            'today_gmv' => $one("SELECT COALESCE(SUM(grand_total_paise),0) FROM store_orders WHERE paid_at >= CURDATE()"),
            'today_orders' => $one("SELECT COUNT(*) FROM store_orders WHERE paid_at >= CURDATE()"),
            'month_gmv' => $one("SELECT COALESCE(SUM(grand_total_paise),0) FROM store_orders WHERE paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'month_orders' => $one("SELECT COUNT(*) FROM store_orders WHERE paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            // Commission is booked when a package is delivered (negative ledger entry) and given back
            // when an item is returned (positive entry), in the month each happens. Shown separately so
            // a month with returns of last month's orders doesn't look like a bare negative number.
            'month_commission_earned' => $one("SELECT COALESCE(-SUM(amount_paise),0) FROM store_vendor_ledger WHERE entry_type = 'commission_debit' AND amount_paise < 0 AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'month_commission_reversed' => $one("SELECT COALESCE(SUM(amount_paise),0) FROM store_vendor_ledger WHERE entry_type = 'commission_debit' AND amount_paise > 0 AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'month_refunds' => $one("SELECT COALESCE(SUM(amount_paise),0) FROM store_refunds WHERE status <> 'failed' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'customers' => $one("SELECT COUNT(DISTINCT identity_id) FROM store_orders WHERE paid_at IS NOT NULL"),
            'live_products' => $one("SELECT COUNT(*) FROM store_products WHERE status = 'live' AND deleted_at IS NULL"),
            'sellers' => $one("SELECT COUNT(*) FROM store_vendors WHERE status = 'approved'"),
        ];
        $todo = [
            ['Sellers awaiting approval', $one("SELECT COUNT(*) FROM store_vendors WHERE status = 'pending_review'"), '/admin/store/vendors?status=pending_review'],
            ['Products awaiting review', $one("SELECT COUNT(*) FROM store_products WHERE status = 'pending_review' AND deleted_at IS NULL"), '/admin/store/products?status=pending_review'],
            ['Packages not accepted in 24h', $one("SELECT COUNT(*) FROM store_vendor_orders WHERE status = 'new' AND accept_by < DATE_ADD(NOW(), INTERVAL 24 HOUR)"), '/admin/store/orders?status=paid'],
            ['Packed but no courier booked', $one("SELECT COUNT(*) FROM store_vendor_orders vo WHERE vo.status = 'packed' AND NOT EXISTS (SELECT 1 FROM store_shipments s WHERE s.vendor_order_id = vo.id AND s.status <> 'cancelled')"), '/admin/store/orders'],
            ['Shipment problems', $one("SELECT COUNT(*) FROM store_shipments WHERE status IN ('awb_failed','pickup_failed','ndr','lost','damaged','rto_initiated')"), '/admin/store/orders'],
            ['Returns awaiting decision', $one("SELECT COUNT(*) FROM store_returns WHERE status IN ('requested','qc_failed')"), '/admin/store/returns'],
            ['Reviews to moderate', $one("SELECT COUNT(*) FROM store_reviews WHERE status = 'pending'"), '/admin/store/reviews'],
            ['Failed refunds', $one("SELECT COUNT(*) FROM store_refunds WHERE status = 'failed'"), '/admin/store/orders'],
            ['Payouts approved, not yet paid', $one("SELECT COUNT(*) FROM store_payouts WHERE status IN ('approved','processing')"), '/admin/store/payouts?status=approved'],
            ['Returned to seller, customer not yet refunded', $one("SELECT COUNT(*) FROM store_vendor_orders vo WHERE vo.status = 'rto'
                AND EXISTS (SELECT 1 FROM store_order_items oi WHERE oi.vendor_order_id = vo.id AND oi.qty > oi.qty_cancelled + oi.qty_returned)"), '/admin/store/orders'],
            ['Sellers yet to accept the latest terms', $one("SELECT COUNT(*) FROM store_vendors v JOIN store_policy_pages p ON p.slug = 'seller_terms'
                WHERE v.status = 'approved' AND NOT EXISTS (SELECT 1 FROM store_policy_acceptances a WHERE a.vendor_id = v.id AND a.slug = 'seller_terms' AND a.version = p.version)"),
                '/admin/store/policies/seller_terms'],
            ['eClinicPro GST details missing (delivery charges not invoiced)', \App\Services\Store\TaxDocumentService::platformReady() ? 0 : 1, '/admin/store/settings'],
        ];
        $days = $all(
            "SELECT DATE(paid_at) d, COUNT(*) n, SUM(grand_total_paise) s FROM store_orders
              WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(paid_at) ORDER BY d DESC"
        );
        $topSellers = $all(
            "SELECT v.display_name, COUNT(*) n, SUM(vo.items_subtotal_paise - vo.vendor_discount_paise) s
               FROM store_vendor_orders vo JOIN store_vendors v ON v.id = vo.vendor_id JOIN store_orders o ON o.id = vo.order_id
              WHERE o.paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY v.id, v.display_name ORDER BY s DESC LIMIT 8"
        );

        return $this->render('admin/store_dashboard', compact('kpi', 'todo', 'days', 'topSellers'));
    }

    // ---- Coupons --------------------------------------------------------------

    public function coupons(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()->query(
                'SELECT c.*, v.display_name AS vendor_name, cat.name AS category_name
                   FROM store_coupons c LEFT JOIN store_vendors v ON v.id = c.vendor_id LEFT JOIN store_categories cat ON cat.id = c.category_id
                  ORDER BY c.is_active DESC, c.id DESC'
            )->fetchAll();
        } catch (\Throwable $e) {
            error_log('[StoreMerch::coupons] ' . $e->getMessage());
        }
        $vendors = Database::connection()->query("SELECT id, display_name FROM store_vendors WHERE status = 'approved' ORDER BY display_name")->fetchAll();
        $depts = Database::connection()->query('SELECT id, name, parent_id FROM store_categories WHERE is_active = 1 ORDER BY parent_id, sort_order')->fetchAll();

        return $this->render('admin/store_coupons', ['rows' => $rows, 'vendors' => $vendors, 'categories' => $depts]);
    }

    public function saveCoupon(Request $request): Response
    {
        $this->flash(CouponService::save($request->post, $this->adminId()), 'Coupon saved.');

        return Response::redirect('/admin/store/coupons');
    }

    // ---- Reviews --------------------------------------------------------------

    public function reviews(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'pending');
        try {
            $rows = ReviewService::list(null, $status);
        } catch (\Throwable $e) {
            error_log('[StoreMerch::reviews] ' . $e->getMessage());
            $rows = [];
        }

        return $this->render('admin/store_reviews', ['rows' => $rows, 'status' => $status]);
    }

    public function moderateReview(Request $request, string $id): Response
    {
        $this->flash(ReviewService::moderate((int) $id, (string) ($request->post['decision'] ?? '')), 'Review updated.');

        return Response::redirect('/admin/store/reviews?status=' . rawurlencode((string) ($request->post['back'] ?? 'pending')));
    }

    // ---- Banners --------------------------------------------------------------

    public function banners(Request $request): Response
    {
        $rows = [];
        try {
            $rows = Database::connection()->query('SELECT * FROM store_banners ORDER BY is_active DESC, sort_order, id DESC')->fetchAll();
        } catch (\Throwable $e) {
            error_log('[StoreMerch::banners] ' . $e->getMessage());
        }

        return $this->render('admin/store_banners', ['rows' => $rows]);
    }

    public function saveBanner(Request $request): Response
    {
        $id = (int) ($request->post['id'] ?? 0);
        $link = trim((string) ($request->post['link'] ?? ''));
        if ($link !== '' && !preg_match('#^(/store/|https://eclinicpro\.com/)#', $link)) {
            $this->flash(['ok' => false, 'error' => 'Link must be a store page, e.g. /store/need/baby-kids.'], '');

            return Response::redirect('/admin/store/banners');
        }
        $row = [
            'placement' => 'home_strip',
            'title' => mb_substr(trim((string) ($request->post['title'] ?? '')), 0, 190) ?: null,
            'link' => $link !== '' ? $link : null,
            'starts_at' => !empty($request->post['starts_at']) ? date('Y-m-d 00:00:00', (int) strtotime((string) $request->post['starts_at'])) : null,
            'ends_at' => !empty($request->post['ends_at']) ? date('Y-m-d 23:59:59', (int) strtotime((string) $request->post['ends_at'])) : null,
            'sort_order' => max(0, min(99, (int) ($request->post['sort_order'] ?? 0))),
            'is_active' => !empty($request->post['is_active']) ? 1 : 0,
        ];
        $file = $_FILES['image'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $img = VendorService::storePublicImage($file, 'banners', 'banner', 3 * 1024 * 1024);
            if (!$img['ok']) {
                $this->flash($img, '');

                return Response::redirect('/admin/store/banners');
            }
            $row['image_path'] = $img['path'];
        } elseif ($id === 0) {
            $this->flash(['ok' => false, 'error' => 'Choose an image for the new banner.'], '');

            return Response::redirect('/admin/store/banners');
        }
        if ($id > 0) {
            QueryBuilder::table('store_banners')->where('id', '=', $id)->update($row);
        } else {
            $id = QueryBuilder::table('store_banners')->insert($row);
        }
        StoreAudit::log('banner.save', 'banner', $id, null, $row);
        $this->flash(['ok' => true], 'Banner saved.');

        return Response::redirect('/admin/store/banners');
    }

    // ---- helpers --------------------------------------------------------------

    private function adminId(): int
    {
        return (int) (RequestContext::superAdmin()['id'] ?? 0);
    }

    /** @param array{ok: bool, error?: string} $res */
    private function flash(array $res, string $ok): void
    {
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? $ok : ($res['error'] ?? 'Something went wrong.'));
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
