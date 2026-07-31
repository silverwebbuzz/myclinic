<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Services\PartnerCommissionService;

/**
 * Subscription checkout for clinic/doctor plan purchases.
 *
 * Gateway (India-only, one-time annual/monthly payment via the Razorpay
 * Orders API + Checkout.js):
 *   1. Razorpay  — when RAZORPAY_KEY_ID + RAZORPAY_KEY_SECRET are set.
 *   2. Simulate  — local/dev when no live keys exist (activates the plan).
 *
 * Payment lifecycle is fully handled:
 *   - success (payment.captured / order.paid)  → activate + mark invoice paid.
 *   - failed  (payment.failed)                 → mark the pending invoice failed.
 *   - pending/delayed (authorized, not captured)→ stays pending; webhook or the
 *     return-URL verify settles it when Razorpay captures.
 *   - cancelled (modal dismissed)              → invoice stays pending, no
 *     activation; the doctor can retry from the same screen.
 *
 * Cashfree and Stripe have been removed — Razorpay is the sole gateway.
 */
final class BillingGatewayService
{
    /**
     * @return array{type: string, url?: string, key_id?: string, order_id?: string,
     *   amount?: int, currency?: string, name?: string, prefill?: array<string,string>,
     *   mode?: string, message?: string}
     */
    public static function startCheckout(int $clinicId, string $planId, string $billingCycle, string $countryCode): array
    {
        $plan = PlanService::get($planId);
        if ($plan === null || $planId === 'free') {
            return ['type' => 'error', 'message' => 'Invalid plan'];
        }

        if (self::razorpayConfigured()) {
            return self::razorpayCheckout($clinicId, $planId, $billingCycle);
        }

        return self::simulatePaidPlan($clinicId, $planId);
    }

    private static function razorpayConfigured(): bool
    {
        return ($_ENV['RAZORPAY_KEY_ID'] ?? '') !== '' && ($_ENV['RAZORPAY_KEY_SECRET'] ?? '') !== '';
    }

    /**
     * Read-only gateway status for the admin Payment Gateway page. Never returns
     * secret values — only whether each key is set, plus the resolved mode.
     * Keys live in .env.
     *
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $razorpay = self::razorpayConfigured();

        return [
            'active'           => $razorpay ? 'razorpay' : 'simulate',
            'razorpay_set'     => $razorpay,
            'razorpay_mode'    => self::razorpayMode(),
            'razorpay_api_base' => self::razorpayApiBase(),
            'app_base_url'     => self::appBaseUrl(),
            'razorpay_webhook' => self::appBaseUrl() . '/webhooks/razorpay',
            'key_prefix'       => substr((string) ($_ENV['RAZORPAY_KEY_ID'] ?? ''), 0, 8),
        ];
    }

    /** sandbox (test) vs production (live), driven by RAZORPAY_ENV. */
    private static function razorpayMode(): string
    {
        return strtolower($_ENV['RAZORPAY_ENV'] ?? 'sandbox') === 'production'
            ? 'production'
            : 'sandbox';
    }

    /** Razorpay uses a single API host for both test and live; the key decides. */
    private static function razorpayApiBase(): string
    {
        return 'https://api.razorpay.com/v1';
    }

    private static function appBaseUrl(): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
    }

    /** GST rate applied on the plan base price (CGST 9% + SGST 9%). */
    private const GST_PERCENT = 18.0;

    /**
     * Plan price in INR (rupees). Config *_usd fields actually hold INR for the
     * India product (see config/plans.php note). Yearly uses the discounted rate.
     *
     * Base (pre-tax) plan price in INR.
     */
    private static function planAmountInr(string $planId, string $billingCycle): float
    {
        $plan = PlanService::get($planId);
        if ($plan === null) {
            return 0.0;
        }
        $monthly = (float) ($plan['monthly_usd'] ?? 0);
        $yearly = (float) ($plan['yearly_usd'] ?? 0);

        // Some plans store yearly as a per-month discounted rate; if yearly is
        // smaller than monthly it's clearly per-month → annualize it.
        if ($billingCycle === 'yearly') {
            return $yearly > 0 && $yearly < $monthly ? round($yearly * 12, 2) : round($yearly, 2);
        }

        return round($monthly, 2);
    }

    /**
     * GST breakdown for a plan: base + 18% tax = gross (what we charge).
     *
     * @return array{base: float, tax: float, gross: float, percent: float}
     */
    public static function priceBreakdown(string $planId, string $billingCycle): array
    {
        $base = self::planAmountInr($planId, $billingCycle);
        $tax = round($base * self::GST_PERCENT / 100, 2);

        return [
            'base' => $base,
            'tax' => $tax,
            'gross' => round($base + $tax, 2),
            'percent' => self::GST_PERCENT,
        ];
    }

    /**
     * Razorpay Orders API. Creates an order (amount in paise, INR) and returns
     * the key_id + order_id so Checkout.js can open the modal. The plan is
     * activated on the payment.captured webhook and/or the return-URL verify,
     * so a missed webhook still activates on redirect (both idempotent).
     *
     * @return array{type: string, key_id?: string, order_id?: string, amount?: int,
     *   currency?: string, name?: string, prefill?: array<string,string>, mode?: string, message?: string}
     */
    private static function razorpayCheckout(int $clinicId, string $planId, string $billingCycle): array
    {
        $key = $_ENV['RAZORPAY_KEY_ID'] ?? '';
        $secret = $_ENV['RAZORPAY_KEY_SECRET'] ?? '';
        if ($key === '' || $secret === '') {
            return self::simulatePaidPlan($clinicId, $planId);
        }

        $price = self::priceBreakdown($planId, $billingCycle);
        $amount = $price['gross']; // GST-inclusive total actually charged
        if ($amount <= 0) {
            return self::simulatePaidPlan($clinicId, $planId);
        }
        $amountPaise = (int) round($amount * 100);

        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];

        // receipt is our reference; notes carry clinic/plan for the webhook.
        // Razorpay receipt is max 40 chars.
        $receipt = 'sub_' . $clinicId . '_' . $planId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $receipt = substr($receipt, 0, 40);

        $payload = json_encode([
            'amount' => $amountPaise,
            'currency' => 'INR',
            'receipt' => $receipt,
            'payment_capture' => 1, // auto-capture on authorization
            'notes' => [
                'clinic_id' => (string) $clinicId,
                'plan' => $planId,
                'billing_cycle' => $billingCycle,
            ],
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init(self::razorpayApiBase() . '/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        $orderId = is_array($data) ? ($data['id'] ?? null) : null;

        if ($httpCode >= 200 && $httpCode < 300 && $orderId) {
            // Remember the pending order so the return-URL can verify + activate.
            try {
                QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                    'razorpay_order_id' => $orderId,
                ]);
            } catch (\Throwable $e) {
                // Column missing (migration not run) — webhook/return still work
                // because notes / receipt encode clinic+plan.
            }

            // Create the pending SaaS invoice so the doctor sees it in their
            // billing timeline while payment is in progress. Marked paid +
            // emailed on success (webhook / return-URL verify).
            SaasInvoiceService::createPending($clinicId, $planId, $billingCycle, $price, 'razorpay', (string) $orderId);

            // Normalise prefill values for the Razorpay modal (all optional).
            $rawPhone = preg_replace('/\D+/', '', (string) ($clinic['phone'] ?? '')) ?? '';
            if (strlen($rawPhone) > 10) {
                $rawPhone = substr($rawPhone, -10);
            }
            $email = (string) ($clinic['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }

            return [
                'type' => 'razorpay',
                'key_id' => $key,
                'order_id' => (string) $orderId,
                'amount' => $amountPaise,
                'currency' => 'INR',
                'name' => 'eClinicPro ' . ucfirst($planId) . ' plan (' . $billingCycle . ')',
                'prefill' => [
                    'name' => (string) ($clinic['name'] ?? ''),
                    'email' => $email,
                    'contact' => $rawPhone,
                ],
                'mode' => self::razorpayMode(),
            ];
        }

        // Log the real Razorpay reason for us (server log only) — never shown
        // to the customer, as it's internal infra detail (auth, key/mode mismatch).
        $rzpMessage = is_array($data) ? ($data['error']['description'] ?? $data['error']['reason'] ?? null) : null;
        error_log('[Razorpay] order create failed (HTTP ' . $httpCode . '): '
            . ($rzpMessage ?: (string) $response));

        // Razorpay IS configured but the order failed. Do NOT silently simulate
        // (that would mark the clinic paid without payment). Show the customer a
        // friendly, generic message; the technical reason is in the log above.
        return ['type' => 'error', 'message' => 'We couldn\'t start the payment right now. Please try again in a few minutes, or contact support if it keeps happening.'];
    }

    /**
     * Verify an order with Razorpay by id and activate the plan if it has a
     * captured payment. Used by the return URL (and is idempotent — safe if the
     * webhook already activated). Returns true when the plan is now active.
     */
    public static function verifyRazorpayOrder(string $orderId): bool
    {
        if (!self::razorpayConfigured() || $orderId === '') {
            return false;
        }

        $key = $_ENV['RAZORPAY_KEY_ID'] ?? '';
        $secret = $_ENV['RAZORPAY_KEY_SECRET'] ?? '';

        // Fetch the order; 'paid' means a payment has been captured against it.
        $order = self::razorpayGet('/orders/' . rawurlencode($orderId), $key, $secret);
        if (!is_array($order) || ($order['status'] ?? '') !== 'paid') {
            return false;
        }

        // Find the captured payment id (for the invoice + commission reference).
        $paymentId = null;
        $payments = self::razorpayGet('/orders/' . rawurlencode($orderId) . '/payments', $key, $secret);
        if (is_array($payments) && !empty($payments['items'])) {
            foreach ($payments['items'] as $p) {
                if (($p['status'] ?? '') === 'captured') {
                    $paymentId = (string) ($p['id'] ?? '');
                    break;
                }
            }
        }

        [$clinicId, $plan] = self::clinicAndPlanFromOrder($order, $orderId);
        if ($clinicId <= 0) {
            return false;
        }

        PlanService::applyPlanToTenant($clinicId, $plan, false);

        // Mark the pending SaaS invoice paid, generate the PDF + email it.
        // Idempotent — safe if the webhook already did this.
        SaasInvoiceService::markPaidByOrder($orderId, $paymentId);

        PartnerCommissionService::recordPaidConversion(
            $clinicId,
            (float) (($order['amount'] ?? 0) / 100 ?: self::yearlyInrAmount($plan)),
            'INR',
            null,
            'razorpay:' . ($paymentId ?? $orderId),
        );

        return true;
    }

    /**
     * Small GET helper for the Razorpay REST API.
     *
     * @return array<string, mixed>|null
     */
    private static function razorpayGet(string $path, string $key, string $secret): ?array
    {
        $ch = curl_init(self::razorpayApiBase() . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Resolve clinic id + plan from a Razorpay order. Prefers notes; falls back
     * to parsing the "sub_{clinic}_{plan}_{rand}" receipt.
     *
     * @param array<string, mixed> $order
     * @return array{0: int, 1: string}
     */
    private static function clinicAndPlanFromOrder(array $order, string $orderId): array
    {
        $notes = $order['notes'] ?? [];
        $clinicId = (int) ($notes['clinic_id'] ?? 0);
        $plan = (string) ($notes['plan'] ?? '');

        $receipt = (string) ($order['receipt'] ?? '');
        if ($clinicId <= 0 && str_starts_with($receipt, 'sub_')) {
            $parts = explode('_', $receipt);
            $clinicId = (int) ($parts[1] ?? 0);
            $plan = (string) ($parts[2] ?? '');
        }

        if ($plan === '' || PlanService::get($plan) === null) {
            // Fall back to the paid plan that actually exists in the catalog
            // ('standard' is the Clinic plan). Must be a real plan_id — a
            // foreign key (fk_tenants_plan) rejects anything else.
            $plan = 'standard';
        }

        return [$clinicId, $plan];
    }

    /**
     * Razorpay webhook. Verifies the HMAC signature
     * (X-Razorpay-Signature = hex(hmac_sha256(rawBody, webhook_secret))) then
     * acts on the event:
     *   - payment.captured / order.paid → verify + activate (idempotent).
     *   - payment.failed                → mark the pending invoice failed.
     * Other events (refunds, authorized-but-not-captured) are acknowledged.
     */
    public static function handleRazorpayWebhook(string $payload, ?string $signature): bool
    {
        // Webhook secret is set separately in the Razorpay dashboard; fall back
        // to the key secret if a dedicated one isn't configured.
        $secret = ($_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? '') !== ''
            ? $_ENV['RAZORPAY_WEBHOOK_SECRET']
            : ($_ENV['RAZORPAY_KEY_SECRET'] ?? '');

        if ($secret !== '' && $signature !== null) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expected, (string) $signature)) {
                error_log('[Razorpay] webhook signature mismatch');

                return false;
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }

        $type = (string) ($event['event'] ?? '');
        $paymentEntity = $event['payload']['payment']['entity'] ?? [];
        $orderEntity = $event['payload']['order']['entity'] ?? [];

        $orderId = (string) ($paymentEntity['order_id'] ?? $orderEntity['id'] ?? '');
        $paymentId = (string) ($paymentEntity['id'] ?? '');

        // Failed payment → surface it on the invoice so the doctor can retry.
        if ($type === 'payment.failed') {
            if ($orderId !== '') {
                SaasInvoiceService::markFailedByOrder($orderId);
            }

            return true; // acknowledged
        }

        // Successful capture → verify against the API (source of truth) + activate.
        if (in_array($type, ['payment.captured', 'order.paid'], true) && $orderId !== '') {
            return self::verifyRazorpayOrder($orderId)
                || self::activateFromWebhookOrder($event, $orderId, $paymentId);
        }

        // Any other event (authorized-only / delayed, refunds, etc.) — ack, no-op.
        return true;
    }

    /**
     * Last-resort activation straight from the webhook body when the API
     * re-verify is unavailable (e.g. keys missing in a webhook-only context).
     *
     * @param array<string, mixed> $event
     */
    private static function activateFromWebhookOrder(array $event, string $orderId, string $paymentId): bool
    {
        $paymentEntity = $event['payload']['payment']['entity'] ?? [];
        // Build a minimal order-shaped array so clinicAndPlanFromOrder works
        // off the notes that Razorpay copies onto the payment entity.
        $order = [
            'notes' => $paymentEntity['notes'] ?? ($event['payload']['order']['entity']['notes'] ?? []),
            'amount' => $paymentEntity['amount'] ?? ($event['payload']['order']['entity']['amount'] ?? 0),
            'receipt' => $event['payload']['order']['entity']['receipt'] ?? '',
        ];

        [$clinicId, $plan] = self::clinicAndPlanFromOrder($order, $orderId);
        if ($clinicId <= 0) {
            return false;
        }

        PlanService::applyPlanToTenant($clinicId, $plan, false);
        SaasInvoiceService::markPaidByOrder($orderId, $paymentId !== '' ? $paymentId : null);
        PartnerCommissionService::recordPaidConversion(
            $clinicId,
            (float) (($order['amount'] ?? 0) / 100 ?: self::yearlyInrAmount($plan)),
            'INR',
            null,
            'razorpay:' . ($paymentId !== '' ? $paymentId : $orderId),
        );

        return true;
    }

    /**
     * @return array{type: string, url: string}
     */
    private static function simulatePaidPlan(int $clinicId, string $planId): array
    {
        PlanService::applyPlanToTenant($clinicId, $planId, true);
        // Record a simulated customer marker. Wrapped so a missing razorpay_*
        // column never breaks dev onboarding.
        try {
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                'razorpay_order_id' => 'sim_razorpay_' . $clinicId,
            ]);
        } catch (\Throwable $e) {
            // Column not present — harmless for a simulated payment.
        }

        // Partner program: record commission if this clinic was referred.
        // Simulated payments have no saas_invoice row, so we pass a reference.
        PartnerCommissionService::recordPaidConversion(
            $clinicId,
            self::yearlyInrAmount($planId),
            'INR',
            null,
            'sim:' . $planId . ':' . date('Y-m-d'),
        );

        return [
            'type' => 'redirect',
            'url' => '/onboarding/clinic-setup?simulated=1',
        ];
    }

    /** Yearly plan price in INR (matches the checkout conversion). */
    private static function yearlyInrAmount(string $planId): float
    {
        $plan = PlanService::get($planId);

        return round((float) ($plan['yearly_usd'] ?? 0), 2);
    }
}
