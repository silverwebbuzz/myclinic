<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\SessionService;

final class RefreshTokenMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $exempt = [
        '/health',
        '/login',
        '/register',
        '/forgot-password',
        '/api/check-slug',
        '/webhooks/stripe',
        '/webhooks/razorpay',
    ];

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isExempt($request->uri)) {
            return $next();
        }

        $access = $request->cookies['mc_token'] ?? null;
        if ($access !== null && JwtService::decode($access) !== null) {
            return $next();
        }

        $refresh = $request->cookies['mc_refresh'] ?? null;
        if ($refresh === null || $refresh === '') {
            return $next();
        }

        $session = SessionService::findByRefreshToken($refresh);
        if ($session === null) {
            // Do NOT clear cookies here: when several requests race at token
            // expiry (page nav + queue poll + autosave), the first one rotates
            // the refresh token and the laggards arrive with the stale one.
            // Clearing would wipe the freshly-rotated cookies and log the
            // doctor out. A stale token simply doesn't authenticate.
            return $next();
        }

        $user = QueryBuilder::table('users')->where('id', '=', (int) $session['user_id'])->first();
        if ($user === null || !(int) ($user['is_active'] ?? 0)) {
            JwtService::clearAuthCookies();

            return $next();
        }

        $newRefresh = AuthService::generateRefreshToken();
        SessionService::rotateRefreshToken((int) $session['id'], $newRefresh);
        SessionService::touch((int) $session['id']);

        $jwt = JwtService::issue($user, (int) $user['clinic_id']);
        JwtService::setAuthCookies($jwt, $newRefresh);

        // setcookie() only takes effect on the NEXT request — the tenant/auth
        // middleware that run AFTER this one would still read the expired
        // cookie token and fail with "Clinic not found" / 401. Hand them the
        // fresh identity in-request via RequestContext + the live superglobal.
        $payload = JwtService::decode($jwt);
        if ($payload !== null) {
            RequestContext::setRefreshedAuth($user, $payload);
            $_COOKIE['mc_token'] = $jwt;
        }

        return $next();
    }

    private function isExempt(string $uri): bool
    {
        foreach ($this->exempt as $path) {
            if ($uri === $path || str_starts_with($uri, $path . '/')) {
                return true;
            }
        }

        return str_starts_with($uri, '/reset-password')
            || str_starts_with($uri, '/auth/google');
    }
}
