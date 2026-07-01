<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\BillingGatewayService;
use App\Services\CsrfService;
use App\Services\OnboardingService;
use App\Services\PlanService;
use App\Support\View;

/**
 * Subscription checkout — turns a plan choice into a real gateway payment.
 * This is the entry point that was previously missing: it calls
 * BillingGatewayService::startCheckout() and redirects the doctor to Cashfree
 * (or the configured fallback). Reachable from both onboarding and Settings.
 */
final class SubscriptionController
{
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
        $cycle = ($request->post['billing_cycle'] ?? 'yearly') === 'monthly' ? 'monthly' : 'yearly';

        // Free plan is admin-assigned only — not self-serve.
        if ($planId === 'free') {
            return $returnUrl('Free plan is assigned by support only. Please contact us if you need it.');
        }

        if (PlanService::get($planId) === null) {
            return $returnUrl('Unknown plan.');
        }

        $clinic = RequestContext::clinic() ?? [];
        $country = (string) ($clinic['country_code'] ?? 'IN');

        $result = BillingGatewayService::startCheckout($clinicId, $planId, $cycle, $country);
        AuditService::log($request, 'INSERT', 'saas_invoices', $clinicId);

        $cancelUrl = $onboarded ? '/subscription' : '/onboarding/clinic-setup';

        if (($result['type'] ?? '') === 'cashfree' && !empty($result['payment_session_id'])) {
            return Response::html(View::render('subscription/cashfree-checkout', [
                'payment_session_id' => $result['payment_session_id'],
                'mode' => $result['mode'] ?? 'sandbox',
                'cancel_url' => $cancelUrl,
            ]));
        }

        if (($result['type'] ?? '') === 'redirect' && !empty($result['url'])) {
            return Response::redirect($result['url']);
        }

        $msg = $result['message'] ?? 'Could not start checkout. Please try again.';

        return $returnUrl($msg);
    }
}
