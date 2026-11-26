<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\CsrfService;
use App\Services\RxWhatsAppPackService;
use App\Services\RxWhatsAppService;
use App\Support\Layout;
use App\Support\View;

/**
 * Doctor panel: /prescriptions/whatsapp — this month's free WhatsApp
 * prescriptions, purchased balance, recent sends, and buying more.
 */
final class RxWhatsAppController
{
    public function index(Request $request): Response
    {
        $clinicId = (int) RequestContext::clinicId();
        $packs = array_map(static function (array $p): array {
            return $p + RxWhatsAppPackService::price((float) $p['price']);
        }, RxWhatsAppService::packs());

        return Response::html(Layout::page('prescriptions/whatsapp', [
            'csrf' => CsrfService::token(),
            'status' => RxWhatsAppService::status($clinicId),
            'packs' => $packs,
            'sends' => RxWhatsAppService::recentSends($clinicId, 30),
            'credits' => RxWhatsAppService::credits($clinicId, 20),
            'bought' => (int) ($request->query['bought'] ?? 0),
            'pending' => !empty($request->query['pending']),
            'error' => (string) ($request->query['error'] ?? ''),
        ], 'WhatsApp prescriptions'));
    }

    /** POST /prescriptions/whatsapp/buy — start a Razorpay payment for a pack. */
    public function buy(Request $request): Response
    {
        $clinicId = (int) RequestContext::clinicId();
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/prescriptions/whatsapp?error=' . urlencode('Security token expired, please try again.'));
        }

        $userId = (int) (RequestContext::user()['id'] ?? 0) ?: null;
        $result = RxWhatsAppPackService::checkout($clinicId, (int) ($request->post['pack'] ?? -1), $userId);
        AuditService::log($request, 'INSERT', 'rx_wa_orders', $clinicId);

        if (($result['type'] ?? '') === 'razorpay') {
            return Response::html(View::render('subscription/razorpay-checkout', [
                'key_id' => $result['key_id'],
                'order_id' => $result['order_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
                'name' => $result['name'],
                'prefill' => $result['prefill'],
                'mode' => $result['mode'],
                'return_url' => '/prescriptions/whatsapp/return',
                'cancel_url' => '/prescriptions/whatsapp',
            ]));
        }
        if (($result['type'] ?? '') === 'redirect') {
            return Response::redirect((string) $result['url']);
        }

        return Response::redirect('/prescriptions/whatsapp?error=' . urlencode((string) ($result['message'] ?? 'Could not start the payment.')));
    }

    /** GET /prescriptions/whatsapp/return — Razorpay hands the doctor back here. */
    public function paymentReturn(Request $request): Response
    {
        $clinicId = (int) RequestContext::clinicId();
        $orderId = (string) ($request->query['order_id'] ?? '');
        $order = $orderId !== '' ? RxWhatsAppPackService::findOrder($orderId) : null;

        // Only this clinic's own order.
        if ($order === null || (int) $order['clinic_id'] !== $clinicId) {
            return Response::redirect('/prescriptions/whatsapp?error=' . urlencode('Payment not found.'));
        }
        if (RxWhatsAppPackService::verifyOrder($orderId)) {
            return Response::redirect('/prescriptions/whatsapp?bought=' . (int) $order['qty']);
        }

        // Not confirmed yet — the webhook will credit it when Razorpay captures.
        return Response::redirect('/prescriptions/whatsapp?pending=1');
    }
}
