<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\SuperAdminAuthService;
use App\Services\SuperAdminJwtService;

final class SuperAdminAuthMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private array $publicPaths = [
        '/admin/login',
    ];

    public function handle(Request $request, callable $next): Response
    {
        foreach ($this->publicPaths as $path) {
            if ($request->uri === $path || str_starts_with($request->uri, $path)) {
                return $next();
            }
        }

        $token = $request->cookies['mc_sa_token'] ?? null;
        if ($token === null) {
            return Response::redirect('/admin/login');
        }

        $payload = SuperAdminJwtService::decode($token);
        if ($payload === null || empty($payload['sub'])) {
            SuperAdminJwtService::clearCookie();

            return Response::redirect('/admin/login');
        }

        $admin = SuperAdminAuthService::find((int) $payload['sub']);
        if ($admin === null || !(int) ($admin['is_active'] ?? 0)) {
            return Response::redirect('/admin/login');
        }

        RequestContext::setSuperAdmin($admin);

        return $next();
    }
}
