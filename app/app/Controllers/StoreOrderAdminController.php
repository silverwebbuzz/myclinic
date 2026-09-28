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
