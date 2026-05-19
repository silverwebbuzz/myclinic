<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

/**
 * Stripe / Razorpay checkout — uses live APIs when keys exist, simulates in local dev.
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

        if (PlanService::usesRazorpay($countryCode)) {
            return self::razorpayCheckout($clinicId, $planId, $billingCycle);
        }

        return self::stripeCheckout($clinicId, $planId, $billingCycle);
    }

    /** @return array{type: string, url?: string, message?: string} */
    private static function stripeCheckout(int $clinicId, string $planId, string $billingCycle): array
    {
        $secret = $_ENV['STRIPE_SECRET_KEY'] ?? '';
        if ($secret === '' || str_starts_with($secret, 'sk_test_xxx')) {
            return self::simulatePaidPlan($clinicId, $planId, 'stripe');
        }

        $plan = PlanService::get($planId);
        $amount = $billingCycle === 'yearly'
            ? (int) (($plan['yearly_usd'] ?? 0) * 100)
            : (int) (($plan['monthly_usd'] ?? 0) * 100);

        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

        $payload = [
            'mode' => 'subscription',
            'success_url' => $appUrl . '/onboarding/billing/success?session_id={CHECKOUT_SESSION_ID}&plan=' . $planId,
            'cancel_url' => $appUrl . '/onboarding/plan-selection?cancelled=1',
            'metadata' => ['clinic_id' => (string) $clinicId, 'plan' => $planId],
            'subscription_data' => [
                'trial_period_days' => $plan['trial_days'] ?? 14,
                'metadata' => ['clinic_id' => (string) $clinicId, 'plan' => $planId],
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => ['name' => 'ManageClinic ' . $plan['name'] . ' Plan'],
                    'unit_amount' => $amount,
                    'recurring' => ['interval' => $billingCycle === 'yearly' ? 'year' : 'month'],
                ],
                'quantity' => 1,
            ]],
        ];

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $secret . ':',
            CURLOPT_POSTFIELDS => http_build_query(self::flattenStripeParams($payload)),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        if (!empty($data['url'])) {
            return ['type' => 'redirect', 'url' => $data['url']];
        }

        return ['type' => 'error', 'message' => $data['error']['message'] ?? 'Stripe checkout failed'];
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
        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'stripe_customer_id' => $gateway === 'stripe' ? 'sim_stripe_' . $clinicId : null,
            'razorpay_customer_id' => $gateway === 'razorpay' ? 'sim_razorpay_' . $clinicId : null,
        ]);

        return [
            'type' => 'redirect',
            'url' => '/onboarding/clinic-setup?simulated=1',
        ];
    }

    /** @param array<string, mixed> $params @return array<string, mixed> */
    private static function flattenStripeParams(array $params, string $prefix = ''): array
    {
        $result = [];
        foreach ($params as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $result = array_merge($result, self::flattenStripeParams($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    public static function handleStripeWebhook(string $payload, ?string $signature): bool
    {
        $secret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
        if ($secret !== '' && $signature !== null) {
            $parts = [];
            foreach (explode(',', $signature) as $part) {
                [$k, $v] = array_map('trim', explode('=', $part, 2) + [null, null]);
                if ($k === 't') {
                    $parts['t'] = $v;
                }
                if ($k === 'v1') {
                    $parts['v1'] = $v;
                }
            }
            $signed = hash_hmac('sha256', ($parts['t'] ?? '') . '.' . $payload, $secret);
            if (!hash_equals($signed, $parts['v1'] ?? '')) {
                return false;
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }

        $type = $event['type'] ?? '';
        if (in_array($type, ['checkout.session.completed', 'customer.subscription.created', 'invoice.paid'], true)) {
            $meta = $event['data']['object']['metadata'] ?? [];
            $clinicId = (int) ($meta['clinic_id'] ?? 0);
            $plan = (string) ($meta['plan'] ?? 'clinic');
            if ($clinicId > 0) {
                PlanService::applyPlanToTenant($clinicId, $plan, false);
            }
        }

        if ($type === 'customer.subscription.updated' || $type === 'invoice.paid') {
            $object = $event['data']['object'] ?? [];
            $items = $object['items']['data'] ?? $object['lines']['data'] ?? [];
            foreach ($items as $item) {
                $meta = $item['metadata'] ?? $object['metadata'] ?? [];
                if (($meta['type'] ?? '') === 'extra_seat') {
                    $clinicId = (int) ($meta['clinic_id'] ?? 0);
                    if ($clinicId > 0) {
                        SeatService::addExtraSeat($clinicId, (int) ($meta['quantity'] ?? 1));
                    }
                }
            }
        }

        return true;
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
        }

        return true;
    }
}
