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
        $filter = match ($tab) {
            'todo' => "AND vo.status IN ('new','accepted','packed','ready_to_ship')",
            'shipped' => "AND vo.status IN ('shipped','delivered','completed','rto')",
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

        return $vo === null ? Response::html('Order not found', 404) : $this->render('store_vendor/order_detail', ['vo' => $vo]);
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
