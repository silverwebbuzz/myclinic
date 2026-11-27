<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\PatientIdentityAuthService;
use App\Services\Wellness\CreatorService;
use App\Support\SessionFlash;

/**
 * Guards Creator Studio (/creator/*). A creator logs in with the SAME patient
 * OTP login as eclinicpro.com/patient (ecp_pid cookie on .eclinicpro.com),
 * so one login opens both panels.
 *
 *  - no patient session       → /creator/login
 *  - patient, not a creator   → /creator/join
 *  - suspended creator        → can look, can't change anything
 *
 * Sets RequestContext::creator() / creatorIdentity().
 */
final class CreatorAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($request->uri === '/creator/login') {
            return $next();
        }

        $identity = PatientIdentityAuthService::current();
        if ($identity === null) {
            return Response::redirect('/creator/login');
        }

        $creator = CreatorService::forIdentity((int) $identity['id']);
        $path = $request->uri;
        if ($creator === null && !in_array($path, ['/creator/join', '/creator/logout'], true)) {
            return Response::redirect('/creator/join');
        }
        if ($creator !== null && $path === '/creator/join') {
            return Response::redirect('/creator/dashboard');
        }

        if ($creator !== null && $creator['status'] === 'suspended'
            && $request->method === 'POST' && $path !== '/creator/logout'
        ) {
            SessionFlash::put('creator_err', 'Your creator account is paused, so changes can’t be saved. Please contact us.');

            return Response::redirect('/creator/dashboard');
        }

        RequestContext::setCreator($creator, $identity);

        return $next();
    }
}
