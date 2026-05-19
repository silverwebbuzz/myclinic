<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\BillingGatewayService;

final class WebhookController
{
    public function stripe(Request $request): Response
    {
        $payload = $request->rawBody ?? '';
        $signature = $request->header('Stripe-Signature');

        if (!BillingGatewayService::handleStripeWebhook($payload, $signature)) {
            return Response::json(['error' => 'Invalid signature'], 400);
        }

        return Response::json(['received' => true]);
    }

    public function razorpay(Request $request): Response
    {
        $payload = $request->rawBody ?? '';
        $signature = $request->header('X-Razorpay-Signature');

        if (!BillingGatewayService::handleRazorpayWebhook($payload, $signature)) {
            return Response::json(['error' => 'Invalid signature'], 400);
        }

        return Response::json(['received' => true]);
    }

    public function photoPublished(Request $request): Response
    {
        $payload = json_decode($request->rawBody ?? '{}', true);
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/photo_webhook.log', date('c') . ' inbound: ' . ($request->rawBody ?? '') . "\n", FILE_APPEND);

        return Response::json(['received' => true, 'clinic_id' => $payload['clinic_id'] ?? null]);
    }
}
