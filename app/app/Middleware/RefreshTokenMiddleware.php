<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\QueryBuilder;
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
            JwtService::clearAuthCookies();

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
