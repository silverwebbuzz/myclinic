<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\BillingGatewayService;
use App\Services\CsrfService;
use App\Services\DiscountService;
use App\Services\OnboardingService;
use App\Services\PlanService;
use App\Support\View;

/**
 * Subscription checkout — turns a plan choice into a real gateway payment.
 * This is the entry point that was previously missing: it calls
 * BillingGatewayService::startCheckout() and opens Razorpay Checkout for the
 * doctor (or simulates in dev). Reachable from both onboarding and Settings.
 */
final class SubscriptionController
{
    /** Discount code applied on /subscription, re-validated when paying. */
    private const SESSION_CODE = 'subscription_discount_code';

    /**
     * The hard-block "Plan expired" screen. SubscriptionMiddleware redirects
     * every panel page here once the trial/plan is expired. From here the only
     * ways forward are renew (→ /subscription), contact support,
     * or sign out — so an expired clinic can't keep using the panel for free.
     */
    public function expired(Request $request): Response
    {
        // If they renewed (no longer expired), don't trap them here.
        if (!\App\Services\SubscriptionStatus::isExpired()) {
            return Response::redirect('/dashboard');
        }

        $status = \App\Services\SubscriptionStatus::forClinic();

        return Response::html(View::render('subscription/expired', [
            'csrf'    => CsrfService::token(),
            'reason'  => $status['reason'],
            'endsOn'  => $status['ends_on'],
        ]));
    }

    public function checkout(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $onboarded = OnboardingService::currentStep() >= 5;
        $returnUrl = static function (string $msg) use ($onboarded): Response {
            if ($onboarded) {
                return Response::redirect('/subscription?error=' . urlencode($msg));
            }

            return Response::redirect('/onboarding/clinic-setup?error=' . urlencode($msg));
        };

        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return $returnUrl('Security token expired, please try again.');
        }

        $planId = (string) ($request->post['plan'] ?? 'standard');
        $cycle = 'monthly'; // single plan: ₹999/month + GST

        // Free plan is admin-assigned only — not self-serve.
        if ($planId === 'free') {
            return $returnUrl('Free plan is assigned by support only. Please contact us if you need it.');
        }

        if (PlanService::get($planId) === null) {
            return $returnUrl('Unknown plan.');
        }

        $clinic = RequestContext::clinic() ?? [];
        $country = (string) ($clinic['country_code'] ?? 'IN');

        // Re-check the applied code at the moment of paying (it may have
        // expired or hit its limit since it was applied).
        [$discount, $codeError] = self::sessionDiscount($clinicId);
        if ($codeError !== null) {
            return Response::redirect('/subscription?code_error=' . urlencode($codeError));
        }

        $result = BillingGatewayService::startCheckout($clinicId, $planId, $cycle, $country, $discount);
        AuditService::log($request, 'INSERT', 'saas_invoices', $clinicId);

        $cancelUrl = $onboarded ? '/subscription' : '/onboarding/clinic-setup';

        if (($result['type'] ?? '') === 'razorpay' && !empty($result['order_id'])) {
            return Response::html(View::render('subscription/razorpay-checkout', [
                'key_id' => $result['key_id'] ?? '',
                'order_id' => $result['order_id'],
                'amount' => $result['amount'] ?? 0,
                'currency' => $result['currency'] ?? 'INR',
                'name' => $result['name'] ?? 'Subscription',
                'prefill' => $result['prefill'] ?? [],
                'mode' => $result['mode'] ?? 'sandbox',
                'return_url' => '/onboarding/billing/razorpay-return',
                'cancel_url' => $cancelUrl,
            ]));
        }

        if (($result['type'] ?? '') === 'redirect' && !empty($result['url'])) {
            self::ensureSession();
            unset($_SESSION[self::SESSION_CODE]);

            return Response::redirect($result['url']);
        }

        $msg = $result['message'] ?? 'Could not start checkout. Please try again.';

        return $returnUrl($msg);
    }

    /** POST /subscription/code — apply or remove a discount code on the renew card. */
    public function code(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/subscription?error=' . urlencode('Security token expired, please try again.'));
        }

        self::ensureSession();
        if (($request->post['action'] ?? '') === 'remove') {
            unset($_SESSION[self::SESSION_CODE]);

            return Response::redirect('/subscription');
        }

        $check = DiscountService::validate((string) ($request->post['code'] ?? ''), $clinicId);
        if (!$check['ok']) {
            unset($_SESSION[self::SESSION_CODE]);

            return Response::redirect('/subscription?code_error=' . urlencode((string) $check['error']));
        }

        $_SESSION[self::SESSION_CODE] = (string) $check['code']['code'];

        return Response::redirect('/subscription?code=applied');
    }

    /**
     * The code saved in the session, re-validated. A code that no longer
     * validates (e.g. just redeemed by a payment) is dropped.
     *
     * @return array{0: ?array<string, mixed>, 1: ?string}  [code row, error]
     */
    public static function sessionDiscount(int $clinicId): array
    {
        self::ensureSession();
        $saved = (string) ($_SESSION[self::SESSION_CODE] ?? '');
        if ($saved === '') {
            return [null, null];
        }

        $check = DiscountService::validate($saved, $clinicId);
        if (!$check['ok']) {
            unset($_SESSION[self::SESSION_CODE]);

            return [null, (string) $check['error']];
        }

        return [$check['code'], null];
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}
