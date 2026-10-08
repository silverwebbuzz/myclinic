<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\FulfilmentService;
use App\Services\Store\StoreRefundService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Seller orders (/vendor/orders). A seller sees ONLY their own sub-orders, and
 * only once paid. Customer contact details beyond what's needed to fulfil
 * (name + delivery city/pincode) are not shown; shipping labels (P8) carry
 * the full address.
 */
final class VendorOrderController
{
    public function index(Request $request): Response
    {
        FulfilmentService::autoCancelOverdue();
        $vendorId = (int) $this->vendor()['id'];
        $tab = (string) ($request->query['tab'] ?? 'todo');
        try {
            return $this->ordersPage($vendorId, $tab);
        } catch (\Throwable $e) {
            error_log('[VendorOrder::index] ' . $e->getMessage());
            SessionFlash::put('store_err', 'Orders are temporarily unavailable. Please try again shortly.');

            return $this->render('store_vendor/orders', ['rows' => [], 'tab' => $tab, 'counts' => []]);
        }
    }

    private function ordersPage(int $vendorId, string $tab): Response
    {
        $filter = match ($tab) {
            'todo' => "AND vo.status IN ('new','accepted','packed','ready_to_ship')",
            'shipped' => "AND vo.status IN ('shipped','delivered','completed','rto','lost_in_transit')",
            'cancelled' => "AND vo.status IN ('cancelled_by_customer','cancelled_by_vendor','cancelled_by_admin','auto_cancelled')",
            default => '',
        };
        $st = Database::connection()->prepare(
            "SELECT vo.*, o.order_no, o.paid_at, o.ship_address_json,
                    (SELECT COALESCE(SUM(oi.qty - oi.qty_cancelled), 0) FROM store_order_items oi WHERE oi.vendor_order_id = vo.id) AS item_count
               FROM store_vendor_orders vo
               JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.vendor_id = :v AND o.payment_status IN ('paid','partially_refunded','refunded') $filter
              ORDER BY (vo.status = 'new') DESC, vo.accept_by ASC, o.paid_at DESC LIMIT 200"
        );
        $st->execute(['v' => $vendorId]);
        $counts = Database::connection()->prepare(
            "SELECT SUM(vo.status = 'new') AS new_n, SUM(vo.status IN ('accepted','packed','ready_to_ship')) AS wip_n
               FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.vendor_id = :v AND o.payment_status IN ('paid','partially_refunded','refunded')"
        );
        $counts->execute(['v' => $vendorId]);

        return $this->render('store_vendor/orders', ['rows' => $st->fetchAll(), 'tab' => $tab, 'counts' => $counts->fetch() ?: []]);
    }

    public function show(Request $request, string $id): Response
    {
        $vo = $this->load((int) $id);
        if ($vo === null) {
            return Response::html('Order not found', 404);
        }

        $shipment = \App\Services\Store\ShippingService::activeShipment((int) $vo['id']);

        return $this->render('store_vendor/order_detail', [
            'vo' => $vo,
            'shipment' => $shipment,
            'fee' => \App\Services\Store\SellerFeeService::packageCharge($vo, $shipment),
            'charges' => \App\Services\Store\SellerFeeService::chargesFor((int) $vo['id']),
            'disputes' => \App\Services\Store\ChargeDisputeService::forPackage((int) $vo['id']),
            'suggest' => \App\Services\Store\ShippingService::suggestPackage((int) $vo['id']),
            'courierOn' => \App\Services\Store\ShiprocketClient::configured(),
            'taxDocs' => array_values(array_filter(
                \App\Services\Store\TaxDocumentService::forOrder((int) $vo['order_id'], (int) $this->vendor()['id']),
                static fn ($d) => (int) $d['vendor_order_id'] === (int) $vo['id']
            )),
        ]);
    }

    /** Book (or retry booking) courier pickup via Shiprocket. */
    public function book(Request $request, string $id): Response
    {
        $vendorId = (int) $this->vendor()['id'];
        // First booking needs the parcel-on-scale photo (weight-dispute evidence); retries don't.
        $vo = $this->load((int) $id);
        if ($vo !== null && \App\Services\Store\ShippingService::activeShipment((int) $vo['id']) === null) {
            $file = $_FILES['parcel_photo'] ?? [];
            $hasUpload = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if ($hasUpload || empty($vo['parcel_photo_path'])) {
                $saved = \App\Services\Store\ShippingService::saveParcelPhoto((int) $vo['id'], $vendorId, is_array($file) ? $file : []);
                if (!$saved['ok']) {
                    $this->flash($saved, '');

                    return Response::redirect('/vendor/orders/' . (int) $id);
                }
            }
        }
        $res = \App\Services\Store\ShippingService::book((int) $id, $vendorId, [
            'weight_g' => (int) ($request->post['weight_g'] ?? 0),
            'length_cm' => (float) ($request->post['length_cm'] ?? 0),
            'breadth_cm' => (float) ($request->post['breadth_cm'] ?? 0),
            'height_cm' => (float) ($request->post['height_cm'] ?? 0),
        ], 'vendor_user', $this->userId());
        $this->flash($res, 'Courier booked. Print the label, stick it on the package, and hand it over at pickup.');

        return Response::redirect('/vendor/orders/' . (int) $id);
    }

    /** The seller's own parcel photo for a package. */
    public function parcelPhoto(Request $request, string $id): Response
    {
        $vo = $this->load((int) $id);

        return $vo === null ? Response::html('Not found', 404) : \App\Services\Store\ShippingService::parcelPhotoResponse($vo);
    }

    /** Dispute one deduction on this package (held out of payouts until we answer). */
    public function dispute(Request $request, string $id, string $ledgerId): Response
    {
        try {
            $res = $this->load((int) $id) === null ? ['ok' => false, 'error' => 'Order not found.']
                : \App\Services\Store\ChargeDisputeService::open((int) $this->vendor()['id'], $this->userId(), (int) $id, (int) $ledgerId,
                    (string) ($request->post['reason'] ?? ''));
        } catch (\Throwable $e) {
            error_log('[VendorOrder::dispute] ' . $e->getMessage());
            $res = ['ok' => false, 'error' => 'Could not send the dispute right now. Please try again shortly.'];
        }
        $this->flash($res, 'Dispute sent. The charge is held out of your payouts until we reply.');

        return Response::redirect('/vendor/orders/' . (int) $id);
    }

    public function accept(Request $request, string $id): Response
    {
        $this->flash(FulfilmentService::accept((int) $this->vendor()['id'], (int) $id, $this->userId()), 'Order accepted. Pack it and mark it packed.');

        return Response::redirect('/vendor/orders/' . (int) $id);
    }

    public function packed(Request $request, string $id): Response
    {
        $this->flash(FulfilmentService::markPacked((int) $this->vendor()['id'], (int) $id, $this->userId()), 'Marked as packed. Shipping pickup comes next.');

        return Response::redirect('/vendor/orders/' . (int) $id);
    }

    /** Cancel selected units (e.g. out of stock) → partial refund to the customer. */
    public function cancelItems(Request $request, string $id): Response
    {
        $vo = $this->load((int) $id);
        if ($vo === null) {
            return Response::html('Order not found', 404);
        }
        $qty = [];
        foreach ((array) ($request->post['cancel'] ?? []) as $itemId => $q) {
            $qty[(int) $itemId] = (int) $q;
        }
        $reason = (string) ($request->post['reason'] ?? '');
        $note = trim((string) ($request->post['note'] ?? ''));
        $res = StoreRefundService::cancelItems((int) $vo['order_id'], $qty, $reason . ($note !== '' ? ': ' . $note : ''),
            'vendor_user', $this->userId(), !empty($request->post['restock']), (int) $this->vendor()['id']);
        $this->flash($res, 'Cancelled. The customer has been refunded ₹' . \App\Services\Store\ProductService::rupees((int) ($res['refunded'] ?? 0)) . '.');

        return Response::redirect('/vendor/orders/' . (int) $id);
    }

    // ---- helpers ----------------------------------------------------------------

    private function load(int $id): ?array
    {
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT vo.*, o.order_no, o.paid_at, o.ship_address_json, o.contact_name
               FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.id = :id AND vo.vendor_id = :v AND o.payment_status IN ('paid','partially_refunded','refunded')"
        );
        $st->execute(['id' => $id, 'v' => (int) $this->vendor()['id']]);
        $vo = $st->fetch();
        if (!$vo) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM store_order_items WHERE vendor_order_id = :vo ORDER BY id');
        $st->execute(['vo' => (int) $vo['id']]);
        $vo['items'] = $st->fetchAll();
        $vo['ship'] = json_decode((string) $vo['ship_address_json'], true) ?: [];

        return $vo;
    }

    /** @return array<string, mixed> */
    private function vendor(): array
    {
        return RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.');
    }

    private function userId(): int
    {
        return (int) (RequestContext::vendorUser()['id'] ?? 0);
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
            'vendor' => VendorService::find((int) $this->vendor()['id']) ?? $this->vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
