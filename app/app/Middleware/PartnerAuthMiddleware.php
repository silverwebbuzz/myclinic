<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\PartnerAuthService;
use App\Services\PartnerJwtService;

/**
 * Guards the partner dashboard. Public partner pages (register/login and the
 * marketing page) are listed below and pass through unauthenticated.
 */
final class PartnerAuthMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $publicPaths = [
        '/partner/login',
        '/partner/register',
    ];

    public function handle(Request $request, callable $next): Response
    {
        // Exact-match public paths (not prefix — /partner/registrations must still guard).
        if (in_array($request->uri, $this->publicPaths, true)) {
            return $next();
        }

        $token = $request->cookies['mc_partner_token'] ?? null;
        if ($token === null) {
            return Response::redirect('/partner/login');
        }

        $payload = PartnerJwtService::decode($token);
        if ($payload === null || empty($payload['sub'])) {
            PartnerJwtService::clearCookie();

            return Response::redirect('/partner/login');
        }

        $partner = PartnerAuthService::find((int) $payload['sub']);
        if ($partner === null || !(int) ($partner['is_active'] ?? 0) || ($partner['status'] ?? '') === 'rejected') {
            PartnerJwtService::clearCookie();

            return Response::redirect('/partner/login');
        }

        RequestContext::setPartner($partner);

        return $next();
    }
}
