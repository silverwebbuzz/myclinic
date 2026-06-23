<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\RoleAccessService;

final class RbacMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $user = RequestContext::user();
        if ($user === null) {
            return $next();
        }

        $path = parse_url($request->uri, PHP_URL_PATH) ?: $request->uri;
        if ($path === '/logout') {
            return $next();
        }

        if (RoleAccessService::canAccessPath($user, $request->method, $path)) {
            return $next();
        }

        if ($request->isJson() || str_starts_with($path, '/api/')) {
            return Response::json([
                'error' => 'You do not have permission to perform this action.',
                'code' => 'FORBIDDEN',
            ], 403);
        }

        return Response::redirect('/dashboard?error=' . urlencode('You do not have permission to access that page.'));
    }
}
