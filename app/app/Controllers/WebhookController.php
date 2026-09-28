<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\BillingGatewayService;
use App\Services\WhatsAppWebhookService;
use App\Support\MessagingSettings;

final class WebhookController
{
    /**
     * Meta WhatsApp webhook.
     * GET  → verification handshake (hub.challenge).
     * POST → delivery statuses + inbound messages (signature-verified).
     */
    public function whatsapp(Request $request): Response
    {
        // Meta verification handshake (subscribe).
        if ($request->method === 'GET') {
            $mode = $request->query['hub_mode'] ?? ($request->query['hub.mode'] ?? null);
            $token = $request->query['hub_verify_token'] ?? ($request->query['hub.verify_token'] ?? null);
            $challenge = $request->query['hub_challenge'] ?? ($request->query['hub.challenge'] ?? '');
            if ($mode === 'subscribe' && $token !== null && $token === MessagingSettings::waVerifyToken()) {
                // Meta just needs the challenge echoed back with HTTP 200.
                return Response::html((string) $challenge, 200);
            }
            return Response::json(['error' => 'verification failed'], 403);
        }

        $raw = $request->rawBody ?? '';
        if (!WhatsAppWebhookService::verifySignature($raw, $request->header('X-Hub-Signature-256'))) {
            return Response::json(['error' => 'invalid signature'], 401);
        }

        $event = json_decode($raw, true);
        if (is_array($event)) {
            try {
                WhatsAppWebhookService::handle($event);
            } catch (\Throwable $e) {
                // Always 200 to Meta so it doesn't retry-storm; log internally.
                error_log('[wa-webhook] ' . $e->getMessage());
            }
        }
        return Response::json(['received' => true]);
    }

    /**
     * Stripe webhook — DECOMMISSIONED (Phase 4). eClinicPro is India-only,
     * Razorpay-only. Returns 410 Gone for one release cycle so any lingering
     * Stripe webhook config fails loudly, then this method is deleted.
     */
    public function stripe(Request $request): Response
    {
        return Response::json(['error' => 'Stripe is no longer supported'], 410);
    }

    /**
     * Razorpay webhook — subscription payments. Handles payment.captured /
     * order.paid (activate) and payment.failed (mark invoice failed).
     * Signature-verified in BillingGatewayService.
     */
    /**
     * Shiprocket shipment-status webhook for the store. Authenticated by the
     * x-api-key token configured in Shiprocket (= store_shiprocket_webhook_key).
     * Always 200 for authenticated calls so Shiprocket doesn't disable the hook.
     */
    public function storeTracking(Request $request): Response
    {
        $ok = \App\Services\Store\ShippingService::handleWebhook($request->rawBody ?? '', $request->header('x-api-key'));

        return $ok ? Response::json(['received' => true]) : Response::json(['error' => 'Unauthorized'], 401);
    }

    public function razorpay(Request $request): Response
    {
        $payload = $request->rawBody ?? '';
        $signature = $request->header('X-Razorpay-Signature');

        // Store marketplace payments share this Razorpay account + webhook URL.
        // Route them BEFORE the subscription handler, which would otherwise
        // find no clinic, answer 400 and make Razorpay retry (and eventually
        // disable the webhook).
        $event = json_decode($payload, true);
        if (is_array($event) && \App\Services\Store\StorePaymentService::isStoreEvent($event)) {
            $ok = \App\Services\Store\StorePaymentService::handleWebhook($payload, $signature, $request->header('X-Razorpay-Event-Id'));

            return $ok ? Response::json(['received' => true]) : Response::json(['error' => 'Invalid signature'], 400);
        }

        if (!BillingGatewayService::handleRazorpayWebhook($payload, $signature)) {
            return Response::json(['error' => 'Invalid signature'], 400);
        }

        return Response::json(['received' => true]);
    }
}
