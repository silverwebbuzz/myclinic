<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Services\PartnerCommissionService;

/**
 * Razorpay checkout — uses live API when keys exist, simulates in local dev.
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

        // Razorpay is the only gateway. India-first; other countries simulate
        // until multi-currency lands (deferred — see phase1 doc).
        return self::razorpayCheckout($clinicId, $planId, $billingCycle);
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
            'razorpay_customer_id' => 'sim_razorpay_' . $clinicId,
        ]);

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
