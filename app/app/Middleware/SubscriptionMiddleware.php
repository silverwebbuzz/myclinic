<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\SubscriptionStatus;

/**
 * Hard-blocks the doctor panel once the clinic's trial / plan has expired.
 *
 * Runs AFTER 'tenant' + 'auth' so RequestContext::clinic() is populated. When
 * the subscription is expired, every panel request is redirected to the
 * "Plan expired" screen — EXCEPT an allowlist of paths that must stay
 * reachable so the user can actually recover (renew/pay, log out, let the
 * payment webhook/return through).
 *
 * No grace period: a date that exists and is in the past blocks immediately.
 * A clinic with no dates set is never expired (see SubscriptionStatus).
 */
final class SubscriptionMiddleware implements MiddlewareInterface
{
    /**
     * Path prefixes always allowed even when expired, so the user can pay or
     * leave. Keep this tight — anything here is reachable on an expired plan.
     */
    private const ALLOW_PREFIXES = [
        '/subscription-expired',               // the block screen itself
        '/settings',                           // general settings (partial access)
        '/subscription',                       // billing / renew page (promoted from settings)
        '/subscription/checkout',              // the renew/pay POST (Razorpay)
        '/onboarding',                         // never gate onboarding (avoids a
                                               // redirect loop before setup is done)
        '/logout',
        '/impersonate/exit',                   // super-admin can always step out
        '/help',
    ];

    /**
     * Paths reachable by a clinic that registered but hasn't paid yet
     * (tenants.payment_pending). Everything else goes to /register/checkout.
     */
    private const PAYMENT_PENDING_ALLOW = [
        '/register/checkout',
        '/register/payment',
        '/onboarding/billing/razorpay-return', // Razorpay hands back here after paying
        '/logout',
        '/impersonate/exit',
        '/help',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $path = parse_url($request->uri, PHP_URL_PATH) ?: $request->uri;

        if (SubscriptionStatus::paymentPending()) {
            return self::allowed($path, self::PAYMENT_PENDING_ALLOW)
                ? $next()
                : Response::redirect('/register/checkout');
        }

        if (!SubscriptionStatus::isExpired()) {
            return $next();
        }

        if (self::allowed($path, self::ALLOW_PREFIXES)) {
            return $next();
        }

        return Response::redirect('/subscription-expired');
    }

    /** @param list<string> $prefixes */
    private static function allowed(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/') || str_starts_with($path, $allowed . '?')) {
                return true;
            }
        }

        return false;
    }
}
