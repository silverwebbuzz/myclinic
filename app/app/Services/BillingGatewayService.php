<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Services\PartnerCommissionService;

/**
 * Subscription checkout for clinic/doctor plan purchases.
 *
 * Gateway priority (India-first):
 *   1. Cashfree   — primary, when CASHFREE_APP_ID + CASHFREE_SECRET_KEY set.
 *   2. Razorpay   — fallback, when RAZORPAY_KEY_ID + RAZORPAY_KEY_SECRET set.
 *   3. Simulate   — local/dev when no live keys exist (activates the plan).
 *
 * Phase 4: Stripe removed entirely (India-only product).
 */
final class BillingGatewayService
{
    /** @return array{type: string, url?: string, message?: string} */
    public static function startCheckout(int $clinicId, string $planId, string $billingCycle, string $countryCode): array
    {
        $plan = PlanService::get($planId);
        if ($plan === null || $planId === 'free') {
            return ['type' => 'error', 'message' => 'Invalid plan'];
        }

        // Cashfree is the primary gateway; fall back to Razorpay, then simulate.
        if (self::cashfreeConfigured()) {
            return self::cashfreeCheckout($clinicId, $planId, $billingCycle);
        }
        if (($_ENV['RAZORPAY_KEY_ID'] ?? '') !== '' && ($_ENV['RAZORPAY_KEY_SECRET'] ?? '') !== '') {
            return self::razorpayCheckout($clinicId, $planId, $billingCycle);
        }

        return self::simulatePaidPlan($clinicId, $planId, 'cashfree');
    }

    private static function cashfreeConfigured(): bool
    {
        return ($_ENV['CASHFREE_APP_ID'] ?? '') !== '' && ($_ENV['CASHFREE_SECRET_KEY'] ?? '') !== '';
    }

    /**
     * Read-only gateway status for the admin Payment Gateway page. Never returns
     * secret values — only whether each key is set, plus the resolved mode and
     * which gateway checkout() would actually use. Keys live in .env.
     *
     * @return array<string, mixed>
     */
    public static function status(): array
    {
        $cashfree = self::cashfreeConfigured();
        $razorpay = ($_ENV['RAZORPAY_KEY_ID'] ?? '') !== '' && ($_ENV['RAZORPAY_KEY_SECRET'] ?? '') !== '';

        // Mirrors the fallback order in startCheckout().
        $active = $cashfree ? 'cashfree' : ($razorpay ? 'razorpay' : 'simulate');

        return [
            'active'            => $active,
            'cashfree_set'      => $cashfree,
            'razorpay_set'      => $razorpay,
            'cashfree_mode'     => strtolower($_ENV['CASHFREE_ENV'] ?? 'sandbox') === 'production' ? 'production' : 'sandbox',
            'cashfree_api_base' => self::cashfreeApiBase(),
            'app_base_url'      => self::appBaseUrl(),
            'cashfree_webhook'  => self::appBaseUrl() . '/webhooks/cashfree',
            'razorpay_webhook'  => self::appBaseUrl() . '/webhooks/razorpay',
        ];
    }

    private static function cashfreeApiBase(): string
    {
        // sandbox for testing, api for production.
        return strtolower($_ENV['CASHFREE_ENV'] ?? 'sandbox') === 'production'
            ? 'https://api.cashfree.com/pg'
            : 'https://sandbox.cashfree.com/pg';
    }

    private static function appBaseUrl(): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
    }

    /**
     * Plan price in INR (rupees). Config *_usd fields actually hold INR for the
     * India product (see config/plans.php note). Yearly uses the discounted rate.
     */
    /** GST rate applied on the plan base price (CGST 9% + SGST 9%). */
    private const GST_PERCENT = 18.0;

    /** Base (pre-tax) plan price in INR. */
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
     * Cashfree PG (Orders API + hosted checkout). Creates an order, returns the
     * hosted payment link. Plan is activated on webhook (PAYMENT_SUCCESS) and/or
     * the return-URL verify, so a missed webhook still activates on redirect.
     *
     * @return array{type: string, url?: string, message?: string}
     */
    private static function cashfreeCheckout(int $clinicId, string $planId, string $billingCycle): array
    {
        $price = self::priceBreakdown($planId, $billingCycle);
        $amount = $price['gross']; // GST-inclusive total actually charged
        if ($amount <= 0) {
            return self::simulatePaidPlan($clinicId, $planId, 'cashfree');
        }

        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
        // order_id must be unique per attempt; carries clinic/plan via tags+id.
        $orderId = 'sub_' . $clinicId . '_' . $planId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $returnUrl = self::appBaseUrl() . '/onboarding/billing/cashfree-return?order_id={order_id}';

        // Cashfree validates customer_phone/email. Normalise: strip +91/spaces to
        // a bare 10-digit number; fall back to a valid placeholder if missing.
        $rawPhone = preg_replace('/\D+/', '', (string) ($clinic['phone'] ?? '')) ?? '';
        if (strlen($rawPhone) > 10) {
            $rawPhone = substr($rawPhone, -10); // drop country code (e.g. 91…)
        }
        $phone = strlen($rawPhone) === 10 ? $rawPhone : '9999999999';
        $email = (string) ($clinic['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'billing@eclinicpro.com';
        }

        $payload = json_encode([
            'order_id' => $orderId,
            'order_amount' => $amount,
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => 'clinic_' . $clinicId,
                'customer_name' => (string) ($clinic['name'] ?? 'Clinic'),
                'customer_email' => $email,
                'customer_phone' => $phone,
            ],
            'order_meta' => [
                'return_url' => $returnUrl,
                'notify_url' => self::appBaseUrl() . '/webhooks/cashfree',
            ],
            'order_tags' => [
                'clinic_id' => (string) $clinicId,
                'plan' => $planId,
                'billing_cycle' => $billingCycle,
            ],
            'order_note' => 'eClinicPro ' . ucfirst($planId) . ' plan (' . $billingCycle . ')',
        ]);

        $ch = curl_init(self::cashfreeApiBase() . '/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-version: 2023-08-01',
                'x-client-id: ' . ($_ENV['CASHFREE_APP_ID'] ?? ''),
                'x-client-secret: ' . ($_ENV['CASHFREE_SECRET_KEY'] ?? ''),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        $sessionId = $data['payment_session_id'] ?? null;

        if ($httpCode >= 200 && $httpCode < 300 && $sessionId) {
            // Remember the pending order so the return-URL can verify + activate.
            try {
                QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                    'cashfree_order_id' => $orderId,
                ]);
            } catch (\Throwable $e) {
                // Column missing (migration not run) — webhook/return still work
                // because order_id encodes clinic+plan.
            }

            // Create the pending SaaS invoice so the doctor sees it in their
            // billing timeline while payment is in progress. Marked paid +
            // emailed on success (webhook / return-URL verify).
            SaasInvoiceService::createPending($clinicId, $planId, $billingCycle, $price, 'cashfree', $orderId);

            // Cashfree's hosted page for this session. The JS SDK is the official
            // path, but the order's payment_link (when enabled) or this hosted
            // URL lets us redirect server-side without embedding the SDK.
            $hosted = $data['payment_link']
                ?? (self::cashfreeApiBase() === 'https://api.cashfree.com/pg'
                    ? 'https://payments.cashfree.com/order/#' . $sessionId
                    : 'https://payments-test.cashfree.com/order/#' . $sessionId);

            return ['type' => 'redirect', 'url' => $hosted];
        }

        // Log the real Cashfree reason for us (server log only) — never shown
        // to the customer, as it's internal infra detail (e.g. IP allowlist,
        // auth, key/mode mismatch).
        $cfMessage = is_array($data) ? ($data['message'] ?? $data['error']['message'] ?? null) : null;
        error_log('[Cashfree] order create failed (HTTP ' . $httpCode . '): '
            . ($cfMessage ?: (string) $response));

        // Fall back to Razorpay if configured.
        if (($_ENV['RAZORPAY_KEY_ID'] ?? '') !== '' && ($_ENV['RAZORPAY_KEY_SECRET'] ?? '') !== '') {
            return self::razorpayCheckout($clinicId, $planId, $billingCycle);
        }

        // Cashfree IS configured but the order failed. Do NOT silently simulate
        // (that would mark the clinic paid without payment). Show the customer a
        // friendly, generic message; the technical reason is in the log above.
        return ['type' => 'error', 'message' => 'We couldn\'t start the payment right now. Please try again in a few minutes, or contact support if it keeps happening.'];
    }

    /**
     * Verify an order with Cashfree by id and activate the plan if PAID.
     * Used by the return URL (and is idempotent — safe if the webhook already
     * activated). Returns true when the plan is now active.
     */
    public static function verifyCashfreeOrder(string $orderId): bool
    {
        if (!self::cashfreeConfigured() || $orderId === '') {
            return false;
        }

        $ch = curl_init(self::cashfreeApiBase() . '/orders/' . rawurlencode($orderId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-api-version: 2023-08-01',
                'x-client-id: ' . ($_ENV['CASHFREE_APP_ID'] ?? ''),
                'x-client-secret: ' . ($_ENV['CASHFREE_SECRET_KEY'] ?? ''),
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        if (!is_array($data) || ($data['order_status'] ?? '') !== 'PAID') {
            return false;
        }

        [$clinicId, $plan] = self::clinicAndPlanFromOrder($data, $orderId);
        if ($clinicId <= 0) {
            return false;
        }

        PlanService::applyPlanToTenant($clinicId, $plan, false);

        // Mark the pending SaaS invoice paid, generate the PDF + email it.
        // Idempotent — safe if the webhook already did this.
        SaasInvoiceService::markPaidByOrder($orderId, null);

        PartnerCommissionService::recordPaidConversion(
            $clinicId,
            (float) ($data['order_amount'] ?? self::yearlyInrAmount($plan)),
            'INR',
            null,
            'cashfree:' . $orderId,
        );

        return true;
    }

    /**
     * Resolve clinic id + plan from a Cashfree order. Prefers order_tags;
     * falls back to parsing the "sub_{clinic}_{plan}_{rand}" order_id.
     *
     * @param array<string, mixed> $order
     * @return array{0: int, 1: string}
     */
    private static function clinicAndPlanFromOrder(array $order, string $orderId): array
    {
        $tags = $order['order_tags'] ?? [];
        $clinicId = (int) ($tags['clinic_id'] ?? 0);
        $plan = (string) ($tags['plan'] ?? '');

        if ($clinicId <= 0 && str_starts_with($orderId, 'sub_')) {
            $parts = explode('_', $orderId);
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
     * Cashfree webhook (PAYMENT_SUCCESS_WEBHOOK). Verifies the HMAC signature
     * (x-webhook-signature = base64(hmac_sha256(timestamp + rawBody, secret)))
     * then activates the plan. Idempotent with the return-URL verify.
     */
    public static function handleCashfreeWebhook(string $payload, ?string $signature, ?string $timestamp): bool
    {
        $secret = $_ENV['CASHFREE_SECRET_KEY'] ?? '';
        if ($secret !== '' && $signature !== null && $timestamp !== null) {
            $expected = base64_encode(hash_hmac('sha256', $timestamp . $payload, $secret, true));
            if (!hash_equals($expected, $signature)) {
                error_log('[Cashfree] webhook signature mismatch');

                return false;
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }

        $type = (string) ($event['type'] ?? '');
        $order = $event['data']['order'] ?? [];
        $orderStatus = (string) ($event['data']['payment']['payment_status'] ?? $order['order_status'] ?? '');
        $orderId = (string) ($order['order_id'] ?? '');

        $isPaid = $type === 'PAYMENT_SUCCESS_WEBHOOK' || $orderStatus === 'SUCCESS' || $orderStatus === 'PAID';
        if (!$isPaid || $orderId === '') {
            // Acknowledge non-payment events (refunds, drops) without acting.
            return true;
        }

        // Re-verify against the API as the source of truth, then activate.
        return self::verifyCashfreeOrder($orderId) || self::activateFromWebhookOrder($event, $orderId);
    }

    /**
     * Last-resort activation straight from the webhook body when the API
     * re-verify is unavailable (e.g. keys missing in a webhook-only context).
     *
     * @param array<string, mixed> $event
     */
    private static function activateFromWebhookOrder(array $event, string $orderId): bool
    {
        $order = $event['data']['order'] ?? [];
        [$clinicId, $plan] = self::clinicAndPlanFromOrder(
            is_array($order) ? $order : [],
            $orderId,
        );
        if ($clinicId <= 0) {
            return false;
        }

        PlanService::applyPlanToTenant($clinicId, $plan, false);
        SaasInvoiceService::markPaidByOrder($orderId, null);
        PartnerCommissionService::recordPaidConversion(
            $clinicId,
            (float) ($order['order_amount'] ?? self::yearlyInrAmount($plan)),
            'INR',
            null,
            'cashfree:' . $orderId,
        );

        return true;
    }

    /** @return array{type: string, url?: string, message?: string} */
    private static function razorpayCheckout(int $clinicId, string $planId, string $billingCycle): array
    {
        $key = $_ENV['RAZORPAY_KEY_ID'] ?? '';
        $secret = $_ENV['RAZORPAY_KEY_SECRET'] ?? '';

        if ($key === '' || $secret === '') {
            return self::simulatePaidPlan($clinicId, $planId, 'razorpay');
        }

        $plan = PlanService::get($planId);
        $amount = $billingCycle === 'yearly'
            ? (int) (($plan['yearly_usd'] ?? 0) * 100 * 83)
            : (int) (($plan['monthly_usd'] ?? 0) * 100 * 83);

        $payload = json_encode([
            'plan_id' => 'plan_' . $planId,
            'customer_notify' => 1,
            'total_count' => 12,
            'notes' => ['clinic_id' => $clinicId, 'plan' => $planId],
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/subscriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $key . ':' . $secret,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        if (!empty($data['short_url'])) {
            return ['type' => 'redirect', 'url' => $data['short_url']];
        }

        return self::simulatePaidPlan($clinicId, $planId, 'razorpay');
    }

    /** @return array{type: string, url: string} */
    private static function simulatePaidPlan(int $clinicId, string $planId, string $gateway): array
    {
        PlanService::applyPlanToTenant($clinicId, $planId, true);
        // Record a per-gateway simulated customer marker. Wrapped so a missing
        // cashfree_* column never breaks dev onboarding.
        try {
            $col = $gateway === 'cashfree' ? 'cashfree_order_id' : 'razorpay_customer_id';
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                $col => 'sim_' . $gateway . '_' . $clinicId,
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

    /** Yearly plan price in INR (matches the checkout conversion: yearly_usd × 83). */
    private static function yearlyInrAmount(string $planId): float
    {
        $plan = PlanService::get($planId);

        return round((float) (($plan['yearly_usd'] ?? 0)) * 83, 2);
    }

    public static function handleRazorpayWebhook(string $payload, ?string $signature): bool
    {
        $secret = $_ENV['RAZORPAY_KEY_SECRET'] ?? '';
        if ($secret !== '' && $signature !== null) {
            $expected = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expected, $signature)) {
                return false;
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }

        $notes = $event['payload']['subscription']['entity']['notes']
            ?? $event['payload']['payment']['entity']['notes']
            ?? [];

        $clinicId = (int) ($notes['clinic_id'] ?? 0);
        $plan = (string) ($notes['plan'] ?? 'clinic');
        if ($clinicId > 0 && in_array($event['event'] ?? '', ['subscription.activated', 'subscription.charged'], true)) {
            PlanService::applyPlanToTenant($clinicId, $plan, false);

            // Partner program: each activation/charge is a paid conversion.
            // The first one is the initial sale; later charges are renewals
            // (Razorpay fires subscription.charged on every billing cycle).
            // Prefer the actual paid amount from the payment entity when present.
            $paidPaise = (int) ($event['payload']['payment']['entity']['amount'] ?? 0);
            $baseAmount = $paidPaise > 0 ? round($paidPaise / 100, 2) : self::yearlyInrAmount($plan);
            $paymentId = $event['payload']['payment']['entity']['id']
                ?? ($event['payload']['subscription']['entity']['id'] ?? null);

            PartnerCommissionService::recordPaidConversion(
                $clinicId,
                $baseAmount,
                'INR',
                null,
                $paymentId !== null ? (string) $paymentId : null,
            );
        }

        return true;
    }
}
