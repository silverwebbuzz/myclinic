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
use App\Services\SubscriptionStatus;
use App\Support\View;

/**
 * The last two registration steps: 4 Checkout → 5 Payment.
 *
 * One plan only: Standard at ₹999/month + GST. A clinic created via /register
 * is flagged payment_pending and SubscriptionMiddleware keeps it here until
 * Razorpay confirms the first payment (return URL or webhook). After that the
 * doctor lands in onboarding and can use the panel.
 */
final class SignupCheckoutController
{
    /** GET /register/checkout — step 4: review the plan and total. */
    public function show(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!SubscriptionStatus::paymentPending()) {
            return Response::redirect(OnboardingService::resumeUrl($clinicId));
        }
        // Move onboarding past step 1 now, so the first onboarding visit after
        // payment doesn't re-run the trial bootstrap over the paid month.
        OnboardingService::ensureStandardTrialStarted($clinicId);

        $notice = match ((string) ($request->query['payment'] ?? '')) {
            'failed', 'pending' => 'We could not confirm your payment yet. If money was deducted it will be confirmed within a few minutes — refresh this page. Otherwise, please try again.',
            default => null,
        };

        return Response::html(View::render('auth/checkout', [
            'csrf' => CsrfService::token(),
            'clinic' => RequestContext::clinic() ?? [],
            'price' => BillingGatewayService::monthlyBreakdown(),
            'error' => isset($request->query['error']) ? (string) $request->query['error'] : null,
            'notice' => $notice,
        ]));
    }

    /** POST /register/payment — step 5: create the Razorpay order and pay. */
    public function pay(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!SubscriptionStatus::paymentPending()) {
            return Response::redirect(OnboardingService::resumeUrl($clinicId));
        }
        OnboardingService::ensureStandardTrialStarted($clinicId);

        $back = static fn (string $msg): Response => Response::redirect('/register/checkout?error=' . urlencode($msg));

        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return $back('Security token expired, please try again.');
        }

        $clinic = RequestContext::clinic() ?? [];
        $result = BillingGatewayService::startCheckout($clinicId, 'standard', 'monthly', (string) ($clinic['country_code'] ?? 'IN'));
        AuditService::log($request, 'INSERT', 'saas_invoices', $clinicId);

        if (($result['type'] ?? '') === 'razorpay' && !empty($result['order_id'])) {
            return Response::html(View::render('auth/payment', [
                'clinic' => $clinic,
                'price' => BillingGatewayService::monthlyBreakdown(),
                'key_id' => $result['key_id'] ?? '',
                'order_id' => $result['order_id'],
                'amount' => $result['amount'] ?? 0,
                'currency' => $result['currency'] ?? 'INR',
                'name' => $result['name'] ?? 'eClinicPro subscription',
                'prefill' => $result['prefill'] ?? [],
                'return_url' => '/onboarding/billing/razorpay-return',
                'back_url' => '/register/checkout',
            ]));
        }

        if (($result['type'] ?? '') === 'redirect' && !empty($result['url'])) {
            OnboardingService::refreshClinicContext($clinicId);

            return Response::redirect($result['url']);
        }

        return $back($result['message'] ?? 'Could not start the payment. Please try again.');
    }
}
