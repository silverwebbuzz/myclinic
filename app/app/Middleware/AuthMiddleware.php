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
        '/health',
        '/api/check-slug',
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

        $token = $request->cookies['mc_token'] ?? null;
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
            || str_starts_with($uri, '/qr/')
            || str_starts_with($uri, '/queue/display')
            || str_starts_with($uri, '/lab/report/')
            || str_starts_with($uri, '/portal/discharge/')
            || str_starts_with($uri, '/book/')
            || str_starts_with($uri, '/portal/')
            || str_starts_with($uri, '/accept-invite/')
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
