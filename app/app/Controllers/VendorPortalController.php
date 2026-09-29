<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\StoreCrypto;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Store seller portal (/vendor/*) — onboarding for chunk 1: dashboard checklist,
 * business profile, addresses, bank account, KYC documents, submit for review.
 *
 * The vendor id ALWAYS comes from RequestContext (set by VendorAuthMiddleware),
 * never from the URL or form — a seller can only ever touch their own rows.
 */
final class VendorPortalController
{
    public function dashboard(Request $request): Response
    {
        $vendor = $this->vendor();

        return $this->render('store_vendor/dashboard', [
            'checklist' => VendorService::checklist($vendor),
            'welcome' => !empty($request->query['welcome']),
            'stats' => $vendor['status'] === 'approved' ? self::stats((int) $vendor['id']) : null,
        ]);
    }

    /** Seller KPIs for the dashboard (own data only). @return array<string, mixed> */
    private static function stats(int $vendorId): array
    {
        $pdo = \App\Core\Database::connection();
        $q = static function (string $sql) use ($pdo, $vendorId): int {
            try {
                $st = $pdo->prepare($sql);
                $st->execute(['v' => $vendorId]);

                return (int) $st->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };
        $paid = "JOIN store_orders o ON o.id = vo.order_id AND o.payment_status IN ('paid','partially_refunded','refunded')";
        $lowStock = [];
        try {
            $st = $pdo->prepare(
                "SELECT p.id, p.name, sv.title, sv.sku, sv.stock_qty, sv.reserved_qty, sv.low_stock_threshold
                   FROM store_product_variants sv JOIN store_products p ON p.id = sv.product_id
                  WHERE sv.vendor_id = :v AND sv.is_active = 1 AND p.status = 'live' AND p.deleted_at IS NULL
                    AND sv.stock_qty <= sv.reserved_qty + sv.low_stock_threshold
                  ORDER BY sv.stock_qty - sv.reserved_qty LIMIT 20"
            );
            $st->execute(['v' => $vendorId]);
            $lowStock = $st->fetchAll();
        } catch (\Throwable) {
        }
        $bal = ['available' => 0, 'pending' => 0];
        try {
            $bal = \App\Services\Store\SettlementService::balances($vendorId);
        } catch (\Throwable) {
        }
        $vendor = VendorService::find($vendorId) ?? [];
        $rows = static function (string $sql) use ($pdo, $vendorId): array {
            try {
                $st = $pdo->prepare($sql);
                $st->execute(['v' => $vendorId]);

                return $st->fetchAll();
            } catch (\Throwable) {
                return [];
            }
        };
        // Last 8 paid packages, newest first.
        $recent = $rows(
            "SELECT vo.id, vo.sub_order_no, vo.status, o.paid_at, o.ship_address_json,
                    vo.items_subtotal_paise - vo.vendor_discount_paise AS value_paise,
                    (SELECT COALESCE(SUM(oi.qty - oi.qty_cancelled), 0) FROM store_order_items oi WHERE oi.vendor_order_id = vo.id) AS units
               FROM store_vendor_orders vo $paid WHERE vo.vendor_id = :v ORDER BY o.paid_at DESC LIMIT 8"
        );
        // Sales per day, last 14 days (gaps filled in the view).
        $daily = [];
        foreach ($rows(
            "SELECT DATE(o.paid_at) AS d, COUNT(*) AS n, SUM(vo.items_subtotal_paise - vo.vendor_discount_paise) AS s
               FROM store_vendor_orders vo $paid WHERE vo.vendor_id = :v AND o.paid_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
              GROUP BY DATE(o.paid_at)"
        ) as $r) {
            $daily[(string) $r['d']] = ['n' => (int) $r['n'], 's' => (int) $r['s']];
        }
        // What happened to packages paid in the last 30 days.
        $mix = ['delivered' => 0, 'in_progress' => 0, 'not_delivered' => 0];
        foreach ($rows(
            "SELECT vo.status, COUNT(*) AS n FROM store_vendor_orders vo $paid
              WHERE vo.vendor_id = :v AND o.paid_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY vo.status"
        ) as $r) {
            $k = in_array($r['status'], ['delivered', 'completed'], true) ? 'delivered'
                : (in_array($r['status'], ['new', 'accepted', 'packed', 'ready_to_ship', 'shipped'], true) ? 'in_progress' : 'not_delivered');
            $mix[$k] += (int) $r['n'];
        }
        $gstIssues = 0;
        try {
            $gstIssues = count(\App\Services\Store\HsnService::mismatches($vendorId, 100));
        } catch (\Throwable) {
        }

        return [
            'recent' => $recent,
            'daily' => $daily,
            'mix' => $mix,
            'gst_issues' => $gstIssues,
            'month_orders' => $q("SELECT COUNT(*) FROM store_vendor_orders vo $paid WHERE vo.vendor_id = :v AND o.paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'packed_unbooked' => $q("SELECT COUNT(*) FROM store_vendor_orders vo $paid WHERE vo.vendor_id = :v AND vo.status = 'packed'"),
            'to_accept' => $q("SELECT COUNT(*) FROM store_vendor_orders vo $paid WHERE vo.vendor_id = :v AND vo.status = 'new'"),
            'in_progress' => $q("SELECT COUNT(*) FROM store_vendor_orders vo $paid WHERE vo.vendor_id = :v AND vo.status IN ('accepted','packed','ready_to_ship')"),
            'month_sales' => $q("SELECT COALESCE(SUM(vo.items_subtotal_paise - vo.vendor_discount_paise),0) FROM store_vendor_orders vo $paid
                                  WHERE vo.vendor_id = :v AND o.paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
            'open_returns' => $q("SELECT COUNT(*) FROM store_returns r JOIN store_vendor_orders vo ON vo.id = r.vendor_order_id
                                  WHERE vo.vendor_id = :v AND r.status IN ('requested','approved','pickup_scheduled','picked_up','received')"),
            'live_products' => $q("SELECT COUNT(*) FROM store_products WHERE vendor_id = :v AND status = 'live' AND deleted_at IS NULL"),
            'available' => (int) $bal['available'],
            'pending' => (int) $bal['pending'],
            'rating' => (float) ($vendor['rating_avg'] ?? 0),
            'rating_count' => (int) ($vendor['rating_count'] ?? 0),
            'low_stock' => $lowStock,
        ];
    }

    public function reviews(Request $request): Response
    {
        $rows = [];
        try {
            $rows = \App\Services\Store\ReviewService::list((int) $this->vendor()['id'], 'published');
        } catch (\Throwable) {
        }

        return $this->render('store_vendor/reviews', ['rows' => $rows]);
    }

    public function replyReview(Request $request, string $id): Response
    {
        $this->flash(\App\Services\Store\ReviewService::reply((int) $this->vendor()['id'], (int) $id, (string) ($request->post['reply'] ?? '')), 'Reply saved.');

        return Response::redirect('/vendor/reviews');
    }

    public function submit(Request $request): Response
    {
        $result = VendorService::submitForReview($this->vendor());
        $this->flash($result, 'Submitted! Our team will review your account, usually within 2 working days.');

        return Response::redirect('/vendor/dashboard');
    }

    // ---- Profile ----------------------------------------------------------

    public function profile(Request $request): Response
    {
        $vendor = $this->vendor();

        return $this->render('store_vendor/profile', [
            'locked' => VendorService::legalFieldsLocked($vendor),
            'cryptoReady' => StoreCrypto::isConfigured(),
            'businessTypes' => VendorService::BUSINESS_TYPES,
            'commissionBp' => \App\Services\Store\CommissionService::effectiveVendorRateBp((int) $vendor['id']),
        ]);
    }

    public function saveProfile(Request $request): Response
    {
        $logo = $_FILES['logo'] ?? null;
        $result = VendorService::updateProfile($this->vendor(), $request->post, is_array($logo) ? $logo : null);
        $this->flash($result, 'Profile saved.');

        return Response::redirect('/vendor/profile');
    }

    // ---- Addresses --------------------------------------------------------

    public function addresses(Request $request): Response
    {
        return $this->render('store_vendor/addresses', [
            'addresses' => VendorService::addresses((int) $this->vendor()['id']),
        ]);
    }

    public function addAddress(Request $request): Response
    {
        $this->flash(VendorService::addAddress((int) $this->vendor()['id'], $request->post), 'Address saved.');

        return Response::redirect('/vendor/addresses');
    }

    public function removeAddress(Request $request, string $id): Response
    {
        $ok = VendorService::deactivateAddress((int) $this->vendor()['id'], (int) $id);
        $this->flash(['ok' => $ok, 'error' => 'Address not found.'], 'Address removed.');

        return Response::redirect('/vendor/addresses');
    }

    // ---- Bank ---------------------------------------------------------------

    public function bank(Request $request): Response
    {
        return $this->render('store_vendor/bank', [
            'bank' => VendorService::primaryBank((int) $this->vendor()['id']),
            'cryptoReady' => StoreCrypto::isConfigured(),
        ]);
    }

    public function saveBank(Request $request): Response
    {
        $this->flash(VendorService::saveBank((int) $this->vendor()['id'], $request->post),
            'Bank details saved. We\'ll verify them before your first payout.');

        return Response::redirect('/vendor/bank');
    }

    // ---- Documents ----------------------------------------------------------

    public function documents(Request $request): Response
    {
        return $this->render('store_vendor/documents', [
            'documents' => VendorService::documents((int) $this->vendor()['id']),
            'docTypes' => VendorService::DOC_TYPES,
        ]);
    }

    public function uploadDocument(Request $request): Response
    {
        $file = $_FILES['document'] ?? [];
        $result = VendorService::storeDocument(
            (int) $this->vendor()['id'],
            (string) ($request->post['doc_type'] ?? ''),
            is_array($file) ? $file : [],
            (string) ($request->post['doc_number'] ?? ''),
            (string) ($request->post['valid_until'] ?? ''),
        );
        $this->flash($result, 'Document uploaded.');

        return Response::redirect('/vendor/documents');
    }

    public function documentFile(Request $request, string $id): Response
    {
        // Scoped by vendor_id: another seller's document id simply isn't found.
        $doc = QueryBuilder::table('store_vendor_documents')
            ->where('id', '=', (int) $id)
            ->where('vendor_id', '=', (int) $this->vendor()['id'])
            ->first();

        return $doc === null ? Response::html('Not found', 404) : VendorService::documentResponse($doc);
    }

    // ---- Helpers ------------------------------------------------------------

    /** @return array<string, mixed> */
    private function vendor(): array
    {
        return RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.');
    }

    /** @param array{ok: bool, error?: string} $result */
    private function flash(array $result, string $success): void
    {
        SessionFlash::put($result['ok'] ? 'store_ok' : 'store_err', $result['ok'] ? $success : ($result['error'] ?? 'Something went wrong.'));
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data = []): Response
    {
        // Re-read so pages reflect a save made in this same request cycle.
        $vendor = VendorService::find((int) $this->vendor()['id']) ?? $this->vendor();

        return Response::html(View::render($view, $data + [
            'vendor' => $vendor,
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
