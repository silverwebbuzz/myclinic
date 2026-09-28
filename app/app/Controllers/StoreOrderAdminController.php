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

        return $order === null ? Response::html('Order not found', 404) : $this->render('admin/store_order_detail', ['order' => $order]);
    }

    /** Cancel an UNPAID order (releases reserved stock). Paid-order cancellation comes with refunds (P6+). */
    public function cancel(Request $request, string $id): Response
    {
        $ok = OrderService::release((int) $id, 'cancelled', 'Cancelled by admin: ' . trim((string) ($request->post['note'] ?? '')),
            'admin', (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($ok ? 'store_ok' : 'store_err', $ok ? 'Order cancelled and stock released.' : 'Only unpaid orders can be cancelled here.');

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
