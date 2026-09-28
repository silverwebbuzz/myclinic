<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Seller orders (/vendor/orders). A seller sees ONLY their own sub-orders, and
 * only once paid. Customer contact details beyond what's needed to fulfil
 * (name + delivery city/pincode) are not shown; shipping labels (P8) carry
 * the full address. Accept/pack/cancel actions arrive with P7.
 */
final class VendorOrderController
{
    public function index(Request $request): Response
    {
        $vendorId = (int) $this->vendor()['id'];
        $st = Database::connection()->prepare(
            "SELECT vo.*, o.order_no, o.paid_at, o.ship_address_json,
                    (SELECT COALESCE(SUM(oi.qty), 0) FROM store_order_items oi WHERE oi.vendor_order_id = vo.id) AS item_count
               FROM store_vendor_orders vo
               JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.vendor_id = :v AND o.payment_status IN ('paid','partially_refunded','refunded')
              ORDER BY o.paid_at DESC, vo.id DESC LIMIT 200"
        );
        $st->execute(['v' => $vendorId]);

        return $this->render('store_vendor/orders', ['rows' => $st->fetchAll()]);
    }

    public function show(Request $request, string $id): Response
    {
        $vendorId = (int) $this->vendor()['id'];
        $pdo = Database::connection();
        $st = $pdo->prepare(
            "SELECT vo.*, o.order_no, o.paid_at, o.ship_address_json, o.contact_name
               FROM store_vendor_orders vo JOIN store_orders o ON o.id = vo.order_id
              WHERE vo.id = :id AND vo.vendor_id = :v AND o.payment_status IN ('paid','partially_refunded','refunded')"
        );
        $st->execute(['id' => (int) $id, 'v' => $vendorId]);
        $vo = $st->fetch();
        if (!$vo) {
            return Response::html('Order not found', 404);
        }
        $st = $pdo->prepare('SELECT * FROM store_order_items WHERE vendor_order_id = :vo ORDER BY id');
        $st->execute(['vo' => (int) $vo['id']]);
        $vo['items'] = $st->fetchAll();
        $vo['ship'] = json_decode((string) $vo['ship_address_json'], true) ?: [];

        return $this->render('store_vendor/order_detail', ['vo' => $vo]);
    }

    /** @return array<string, mixed> */
    private function vendor(): array
    {
        return RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.');
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
