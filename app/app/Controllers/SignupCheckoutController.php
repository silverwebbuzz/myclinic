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
use App\Services\SubscriptionStatus;
use App\Support\View;

/**
 * The last two registration steps: 4 Checkout → 5 Payment.
 *
 * One plan only: Standard, monthly, price from /admin/plans + GST. A clinic
 * created via /register is flagged payment_pending and SubscriptionMiddleware
 * keeps it here until Razorpay confirms the first payment (return URL or
 * webhook). A discount code from /admin/discounts can be applied on the
 * checkout page; it's re-validated when paying and only counted as used once
 * the payment is captured.
 */
final class SignupCheckoutController
{
    private const SESSION_CODE = 'signup_discount_code';

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

        [$discount, $codeError] = $this->sessionDiscount($clinicId);

        $notice = match ((string) ($request->query['payment'] ?? '')) {
            'failed', 'pending' => 'We could not confirm your payment yet. If money was deducted it will be confirmed within a few minutes — refresh this page. Otherwise, please try again.',
            default => null,
        };

        return Response::html(View::render('auth/checkout', [
            'csrf' => CsrfService::token(),
            'clinic' => RequestContext::clinic() ?? [],
            'price' => BillingGatewayService::monthlyBreakdown($discount),
            'discount' => $discount,
            'discountLabel' => $discount !== null ? DiscountService::label($discount) : null,
            'codeError' => $codeError ?? (isset($request->query['code_error']) ? (string) $request->query['code_error'] : null),
            'codeApplied' => ($request->query['code'] ?? '') === 'applied',
            'error' => isset($request->query['error']) ? (string) $request->query['error'] : null,
            'notice' => $notice,
        ]));
    }

    /** POST /register/checkout/code — apply or remove a discount code. */
    public function code(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/register/checkout?error=' . urlencode('Security token expired, please try again.'));
        }

        self::ensureSession();
        if (($request->post['action'] ?? '') === 'remove') {
            unset($_SESSION[self::SESSION_CODE]);

            return Response::redirect('/register/checkout');
        }

        $check = DiscountService::validate((string) ($request->post['code'] ?? ''), $clinicId);
        if (!$check['ok']) {
            unset($_SESSION[self::SESSION_CODE]);

            return Response::redirect('/register/checkout?code_error=' . urlencode((string) $check['error']));
        }

        $_SESSION[self::SESSION_CODE] = (string) $check['code']['code'];

        return Response::redirect('/register/checkout?code=applied');
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

        // Re-check the code at the moment of paying (it may have expired or
        // hit its limit since it was applied).
        [$discount, $codeError] = $this->sessionDiscount($clinicId);
        if ($codeError !== null) {
            return Response::redirect('/register/checkout?code_error=' . urlencode($codeError));
        }

        $clinic = RequestContext::clinic() ?? [];
        $result = BillingGatewayService::startCheckout($clinicId, 'standard', 'monthly', (string) ($clinic['country_code'] ?? 'IN'), $discount);
        AuditService::log($request, 'INSERT', 'saas_invoices', $clinicId);

        if (($result['type'] ?? '') === 'razorpay' && !empty($result['order_id'])) {
            return Response::html(View::render('auth/payment', [
                'clinic' => $clinic,
                'price' => BillingGatewayService::monthlyBreakdown($discount),
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
            self::ensureSession();
            unset($_SESSION[self::SESSION_CODE]);
            OnboardingService::refreshClinicContext($clinicId);

            return Response::redirect($result['url']);
        }

        return $back($result['message'] ?? 'Could not start the payment. Please try again.');
    }

    /**
     * The code saved in the session, re-validated.
     *
     * @return array{0: ?array<string, mixed>, 1: ?string}  [code row, error]
     */
    private function sessionDiscount(int $clinicId): array
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
