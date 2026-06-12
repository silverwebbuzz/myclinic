<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Exemptions: the bearer-token REST API (no cookies involved, so CSRF
        // doesn't apply) and signed webhooks. Session-cookie /api/v1 routes ARE
        // protected — the base layout injects X-CSRF-Token into every fetch().
        if (in_array($request->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && !str_starts_with($request->uri, '/api/v1/rest/')
            && !str_starts_with($request->uri, '/webhooks/')
        ) {
            $token = $request->post['_csrf'] ?? $request->header('X-CSRF-Token');
            if (!CsrfService::verify(is_string($token) ? $token : null)) {
                if ($request->isJson()) {
                    return Response::json(['error' => 'Invalid CSRF token'], 419);
                }

                return Response::html(
                    '<!DOCTYPE html><html><body class="p-8 font-sans"><h1>Invalid request</h1>'
                    . '<p>Your security token expired. Please go back and try again.</p></body></html>',
                    419,
                );
            }
        }

        return $next();
    }
}
