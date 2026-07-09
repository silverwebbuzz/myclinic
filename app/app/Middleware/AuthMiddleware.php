<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\JwtService;

final class AuthMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $publicPaths = [
        '/login',
        '/register',
        '/forgot-password',
        '/forgot-username',
        '/health',
        '/api/check-slug',
        '/api/check-username',
        '/api/refresh-token',
        '/auth/google',
        '/admin',
        '/doctors',
        '/docs',
    ];

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isPublic($request->uri)) {
            return $next();
        }

        // If RefreshTokenMiddleware just minted a token this request, the cookie
        // captured at boot is still the expired one — trust the fresh identity.
        $refreshed = RequestContext::refreshedAuth();
        if ($refreshed !== null && (int) ($refreshed['user']['is_active'] ?? 0)) {
            RequestContext::setUser($refreshed['user']);
            $path = parse_url($request->uri, PHP_URL_PATH) ?: $request->uri;
            if (!empty($refreshed['user']['must_change_password'])
                && $path !== '/change-password'
                && !($path === '/logout' && $request->method === 'POST')) {
                return Response::redirect('/change-password');
            }

            return $next();
        }

        // $_COOKIE (not $request->cookies) so an in-request refresh is visible.
        $token = $_COOKIE['mc_token'] ?? $request->cookies['mc_token'] ?? null;
        if ($token === null) {
            return $this->unauthorized($request);
        }

        $payload = JwtService::decode($token);
        if ($payload === null || empty($payload['sub'])) {
            return $this->unauthorized($request);
        }

        $user = QueryBuilder::table('users')->where('id', '=', (int) $payload['sub'])->first();
        if ($user === null || !(int) ($user['is_active'] ?? 0)) {
            return $this->unauthorized($request);
        }

        RequestContext::setUser($user);

        $path = parse_url($request->uri, PHP_URL_PATH) ?: $request->uri;
        if (!empty($user['must_change_password'])
            && $path !== '/change-password'
            && !($path === '/logout' && $request->method === 'POST')) {
            return Response::redirect('/change-password');
        }

        return $next();
    }

    private function isPublic(string $uri): bool
    {
        foreach ($this->publicPaths as $path) {
            if ($uri === $path || str_starts_with($uri, $path . '/')) {
                return true;
            }
        }

        return str_starts_with($uri, '/reset-password')
            || str_starts_with($uri, '/queue/display')
            || str_starts_with($uri, '/portal/')
            || str_starts_with($uri, '/accept-invite/')
            || $uri === '/impersonate/exit'
            || preg_match('#^/impersonate/[a-f0-9]{64}$#', $uri) === 1;
    }

    private function unauthorized(Request $request): Response
    {
        if ($request->isJson() || str_starts_with($request->uri, '/api/')) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return Response::redirect('/login');
    }
}
