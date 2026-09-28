<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\Store\VendorJwtService;
use App\Services\Store\VendorService;

/**
 * Guards the store seller portal (/vendor/*). Login and registration pages pass
 * through; everything else needs a valid mc_vendor_token for an active login
 * whose seller account isn't closed. Sets RequestContext::vendor()/vendorUser().
 */
final class VendorAuthMiddleware implements MiddlewareInterface
{
    /** @var list<string> exact matches */
    private array $publicPaths = ['/vendor/login', '/vendor/register'];

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->uri, $this->publicPaths, true)) {
            return $next();
        }

        $token = $request->cookies[VendorJwtService::COOKIE] ?? null;
        $payload = is_string($token) ? VendorJwtService::decode($token) : null;
        if ($payload === null || empty($payload['sub'])) {
            if ($token !== null) {
                VendorJwtService::clearCookie();
            }

            return Response::redirect('/vendor/login');
        }

        $user = VendorService::findUser((int) $payload['sub']);
        $vendor = $user !== null ? VendorService::find((int) $user['vendor_id']) : null;
        if ($user === null || $vendor === null
            || ($user['status'] ?? '') !== 'active'
            || ($vendor['status'] ?? '') === 'closed'
            || (int) $vendor['id'] !== (int) ($payload['vid'] ?? 0)
        ) {
            VendorJwtService::clearCookie();

            return Response::redirect('/vendor/login');
        }

        RequestContext::setVendor($vendor, $user);

        return $next();
    }
}
