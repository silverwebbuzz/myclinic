<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\OrderService;
use App\Support\SessionFlash;
use App\Support\View;

/** /admin/store/orders — marketplace orders (super-admin). */
final class StoreOrderAdminController
{
    public function index(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? '');
        $q = trim((string) ($request->query['q'] ?? ''));
        \App\Services\Store\FulfilmentService::autoCancelOverdue();
        try {
            $data = OrderService::adminList($status, $q);
            $missing = false;
        } catch (\Throwable $e) {
            error_log('[StoreOrderAdmin::index] ' . $e->getMessage());
            $data = ['rows' => [], 'counts' => []];
            $missing = true;
        }

        return $this->render('admin/store_orders', $data + ['status' => $status, 'q' => $q, 'tableMissing' => $missing]);
    }

    public function show(Request $request, string $id): Response
    {
        $order = OrderService::load((int) $id);

        if ($order === null) {
            return Response::html('Order not found', 404);
        }
        $shipments = [];
        foreach (\App\Services\Store\ShippingService::forOrder((int) $order['id']) as $s) {
            $shipments[(int) $s['vendor_order_id']][] = $s;
        }

        return $this->render('admin/store_order_detail', [
            'order' => $order,
            'shipments' => $shipments,
            'courierOn' => \App\Services\Store\ShiprocketClient::configured(),
            'taxDocs' => \App\Services\Store\TaxDocumentService::forOrder((int) $order['id']),
        ]);
    }

    /** Cancel an UNPAID order (releases reserved stock). Paid-order cancellation comes with refunds (P6+). */
    public function cancel(Request $request, string $id): Response
    {
        $ok = OrderService::release((int) $id, 'cancelled', 'Cancelled by admin: ' . trim((string) ($request->post['note'] ?? '')),
            'admin', (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($ok ? 'store_ok' : 'store_err', $ok ? 'Order cancelled and stock released.' : 'Only unpaid orders can be cancelled here.');

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** Admin cancels selected units (any seller) → partial refund. */
    public function cancelItems(Request $request, string $id): Response
    {
        $qty = [];
        foreach ((array) ($request->post['cancel'] ?? []) as $itemId => $q) {
            $qty[(int) $itemId] = (int) $q;
        }
        $res = \App\Services\Store\StoreRefundService::cancelItems((int) $id, $qty,
            'Admin: ' . trim((string) ($request->post['reason'] ?? '')), 'admin',
            (int) (RequestContext::superAdmin()['id'] ?? 0), !empty($request->post['restock']));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok']
            ? 'Cancelled and refunded ₹' . \App\Services\Store\ProductService::rupees((int) $res['refunded']) . ' (' . $res['refund_no'] . ').'
            : ($res['error'] ?? 'Could not cancel.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    // ---- Shipments (P8) ------------------------------------------------------------

    /** Admin books (or finishes booking) courier pickup for a package. */
    public function shipBook(Request $request, string $id, string $voId): Response
    {
        $res = \App\Services\Store\ShippingService::book((int) $voId, null, [
            'weight_g' => (int) ($request->post['weight_g'] ?? 0),
            'length_cm' => (float) ($request->post['length_cm'] ?? 0),
            'breadth_cm' => (float) ($request->post['breadth_cm'] ?? 0),
            'height_cm' => (float) ($request->post['height_cm'] ?? 0),
        ], 'admin', (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Courier booked.' : ($res['error'] ?? 'Booking failed.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** Manual shipping (courier arranged outside Shiprocket). */
    public function markShipped(Request $request, string $id, string $voId): Response
    {
        $res = \App\Services\Store\FulfilmentService::adminMarkShipped((int) $voId, trim((string) ($request->post['courier'] ?? '')),
            trim((string) ($request->post['awb'] ?? '')), (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Marked shipped; the customer has been emailed.' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    public function markDelivered(Request $request, string $id, string $voId): Response
    {
        $res = \App\Services\Store\FulfilmentService::adminMarkDelivered((int) $voId, (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Marked delivered. The seller\'s earnings are pending until the return window ends.' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** Package dispatched but never delivered (returned to seller / lost / damaged) → refund + credit note. */
    public function refundUndelivered(Request $request, string $id, string $voId): Response
    {
        $cause = (string) ($request->post['cause'] ?? '');
        if (!in_array($cause, ['rto', 'lost', 'damaged'], true)) {
            SessionFlash::put('store_err', 'Choose what happened to the package.');

            return Response::redirect('/admin/store/orders/' . (int) $id);
        }
        $res = \App\Services\Store\StoreRefundService::refundUndelivered((int) $voId, $cause, !empty($request->post['refund_shipping']),
            !empty($request->post['restock']), !empty($request->post['compensate']), trim((string) ($request->post['note'] ?? '')),
            (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok']
            ? 'Refunded ₹' . \App\Services\Store\ProductService::rupees((int) $res['refunded']) . ' (' . $res['refund_no'] . '). A credit note was issued if the package had an invoice.'
            : ($res['error'] ?? 'Could not refund.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** Issue the package's invoice now (normally automatic at dispatch). */
    public function issueInvoice(Request $request, string $id, string $voId): Response
    {
        try {
            $docId = \App\Services\Store\TaxDocumentService::issueInvoice((int) $voId);
        } catch (\Throwable $e) {
            error_log('[StoreOrderAdmin::issueInvoice] ' . $e->getMessage());
            $docId = null;
        }
        SessionFlash::put($docId ? 'store_ok' : 'store_err', $docId ? 'Invoice issued.' : 'An invoice can be issued once the package is packed (and has items left).');

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    // ---- Seller charges (courier, weight disputes, RTO, return pickups) ---------------

    public function parcelPhoto(Request $request, string $id, string $voId): Response
    {
        $vo = $this->package((int) $id, (int) $voId);

        return $vo === null ? Response::html('Not found', 404) : \App\Services\Store\ShippingService::parcelPhotoResponse($vo);
    }

    /** Charge the seller (weight dispute, seller-caused RTO, return pickup, other). */
    public function charge(Request $request, string $id, string $voId): Response
    {
        $vo = $this->package((int) $id, (int) $voId);
        $amount = \App\Services\Store\ProductService::toPaise((string) ($request->post['amount'] ?? ''));
        $res = $vo === null ? ['ok' => false, 'error' => 'Package not found.']
            : \App\Services\Store\SettlementService::charge((int) $voId, (string) ($request->post['kind'] ?? ''), (int) $amount,
                (string) ($request->post['note'] ?? ''), (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Charge added. It will be deducted from the seller\'s next payout.' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    public function reverseCharge(Request $request, string $id, string $voId, string $ledgerId): Response
    {
        $res = $this->package((int) $id, (int) $voId) === null ? ['ok' => false, 'error' => 'Package not found.']
            : \App\Services\Store\SettlementService::reverseCharge((int) $ledgerId, (int) $voId, (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Charge reversed (credited back to the seller).' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** Enter the real forward courier charge (e.g. from Shiprocket's invoice). */
    public function courierCharge(Request $request, string $id, string $voId): Response
    {
        $amount = \App\Services\Store\ProductService::toPaise((string) ($request->post['courier'] ?? ''));
        try {
            $res = $this->package((int) $id, (int) $voId) === null || $amount === null ? ['ok' => false, 'error' => 'Enter the courier charge in rupees.']
                : \App\Services\Store\SettlementService::setCourierCharge((int) $voId, (int) $amount, (int) (RequestContext::superAdmin()['id'] ?? 0));
        } catch (\Throwable $e) {
            error_log('[StoreOrderAdmin::courierCharge] ' . $e->getMessage());
            $res = ['ok' => false, 'error' => 'Could not save. Import 2026_10_04_store_seller_shipping.sql first.'];
        }
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Courier charge saved.' : ($res['error'] ?? 'Failed.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** A package of this order (guards against mismatched ids in the URL). */
    private function package(int $orderId, int $voId): ?array
    {
        return \App\Core\QueryBuilder::table('store_vendor_orders')->where('id', '=', $voId)->where('order_id', '=', $orderId)->first();
    }

    public function shipCancel(Request $request, string $id, string $shipmentId): Response
    {
        $res = \App\Services\Store\ShippingService::cancel((int) $shipmentId, (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Shipment cancelled; the package is back to "packed".' : ($res['error'] ?? 'Could not cancel.'));

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    public function shipRefresh(Request $request, string $id, string $shipmentId): Response
    {
        $s = \App\Core\QueryBuilder::table('store_shipments')->where('id', '=', (int) $shipmentId)->first();
        $ok = $s !== null && \App\Services\Store\ShippingService::refresh($s);
        SessionFlash::put($ok ? 'store_ok' : 'store_err', $ok ? 'Tracking refreshed from Shiprocket.' : 'No tracking available yet.');

        return Response::redirect('/admin/store/orders/' . (int) $id);
    }

    /** Ask Razorpay about every payment on this order (missed webhook / lost callback). */
    public function recheck(Request $request, string $id): Response
    {
        $order = OrderService::load((int) $id);
        if ($order === null) {
            return Response::html('Order not found', 404);
        }
        $states = [];
        foreach ($order['payments'] as $p) {
            $states[] = $p['rzp_order_id'] . ': ' . \App\Services\Store\StorePaymentService::confirm((string) $p['rzp_order_id']);
        }
        SessionFlash::put('store_ok', $states ? 'Razorpay says: ' . implode(', ', $states) : 'No payment attempts on this order.');

        return Response::redirect('/admin/store/orders/' . (int) $id);
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
