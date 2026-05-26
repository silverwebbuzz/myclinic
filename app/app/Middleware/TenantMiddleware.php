<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\JwtService;
use App\Services\RedisClient;

final class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if ($this->isExemptPath($request->uri)) {
            return $next();
        }

        if (preg_match('#^/book/([a-z0-9-]+)(?:/(?:slots|lookup))?$#', $request->uri, $m)) {
            $clinic = $this->loadClinic($m[1]);
            if ($clinic !== null && (int) ($clinic['is_active'] ?? 0)) {
                RequestContext::setClinic($clinic);

                return $next();
            }

            return Response::html('Clinic not found', 404);
        }

        if (str_starts_with($request->uri, '/queue/display')) {
            $slug = $request->query['clinic'] ?? null;
            if ($slug !== null && $slug !== '') {
                $clinic = $this->loadClinic((string) $slug);
                if ($clinic !== null && (int) ($clinic['is_active'] ?? 0)) {
                    RequestContext::setClinic($clinic);

                    return $next();
                }
            }

            return Response::html('Clinic not found. Add ?clinic=your-slug on localhost.', 404);
        }

        if (preg_match('#^/lab/report/([a-f0-9]+)$#', $request->uri, $m)) {
            $order = \App\Services\LabOrderService::findByShareToken($m[1]);
            if ($order !== null) {
                $clinic = QueryBuilder::table('tenants')->where('id', '=', (int) $order['clinic_id'])->first();
                if ($clinic !== null) {
                    RequestContext::setClinic($clinic);

                    return $next();
                }
            }

            return Response::html('Report link expired or invalid', 404);
        }

        if (preg_match('#^/portal/discharge/([a-f0-9]+)$#', $request->uri, $m)) {
            $summary = \App\Services\DischargeService::findByShareToken($m[1]);
            if ($summary !== null) {
                $clinic = QueryBuilder::table('tenants')->where('id', '=', (int) $summary['clinic_id'])->first();
                if ($clinic !== null) {
                    RequestContext::setClinic($clinic);

                    return $next();
                }
            }

            return Response::html('Discharge summary not found', 404);
        }

        if (preg_match('#^/qr/([a-f0-9]{64})$#', $request->uri, $m)) {
            $patient = \App\Services\PatientService::findByQrToken($m[1]);
            if ($patient !== null) {
                $clinic = QueryBuilder::table('tenants')->where('id', '=', (int) $patient['clinic_id'])->first();
                if ($clinic !== null) {
                    RequestContext::setClinic($clinic);

                    return $next();
                }
            }
        }

        $authClinic = $this->resolveFromAuth($request);
        if ($authClinic !== null) {
            if (!(int) ($authClinic['is_active'] ?? 0)) {
                return Response::json([
                    'error' => 'Your clinic account is inactive. Contact support.',
                    'clinic_id' => $authClinic['id'] ?? null,
                ], 403);
            }
            RequestContext::setClinic($authClinic);

            return $next();
        }

        $slug = $this->resolveSlug($request);
        if ($slug === null) {
            // We couldn't figure out which clinic this is. The most common
            // cause is that the user is logged out (or their JWT expired),
            // so resolveFromAuth() returned null AND we're on the bare
            // app.eclinicpro.com domain with no slug subdomain.
            //
            // For HTML requests, redirect to /login — much better UX than
            // a JSON 404. AJAX/JSON callers still get the 404 so they can
            // handle it programmatically.
            if ($this->wantsJson($request)) {
                return Response::json(['error' => 'Clinic not found', 'code' => 'auth_required'], 401);
            }
            return Response::redirect('/login?next=' . urlencode($request->uri));
        }

        $clinic = $this->loadClinic($slug);
        if ($clinic === null || !(int) ($clinic['is_active'] ?? 0)) {
            if ($this->wantsJson($request)) {
                return Response::json(['error' => 'Clinic not found'], 404);
            }
            return Response::redirect('/login?next=' . urlencode($request->uri));
        }

        RequestContext::setClinic($clinic);

        return $next();
    }

    private function wantsJson(Request $request): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json')
            || strcasecmp($xrw, 'XMLHttpRequest') === 0
            || str_starts_with($request->uri, '/api/');
    }

    private function resolveFromAuth(Request $request): ?array
    {
        $token = $_COOKIE['mc_token'] ?? null;
        if ($token === null) {
            return null;
        }
        $payload = JwtService::decode($token);
        if (empty($payload['clinic_id'])) {
            return null;
        }
        $clinic = QueryBuilder::table('tenants')
            ->where('id', '=', (int) $payload['clinic_id'])
            ->first();

        return $clinic ?: null;
    }

    private function isExemptPath(string $uri): bool
    {
        return $uri === '/'                                // public landing page
            || $uri === '/health'
            || str_starts_with($uri, '/admin')
            || str_starts_with($uri, '/api/v1/rest')
            || str_starts_with($uri, '/api/v1/public')
            || str_starts_with($uri, '/doctor/login')      // doctor OTP login (no tenant ctx yet)
            || str_starts_with($uri, '/doctors')
            || str_starts_with($uri, '/docs')
            || preg_match('#^/impersonate/[a-f0-9]{64}$#', $uri) === 1
            || in_array($uri, ['/login', '/register', '/forgot-password'], true)
            || str_starts_with($uri, '/accept-invite/')
            || str_starts_with($uri, '/reset-password')
            || str_starts_with($uri, '/auth/google')
            || $uri === '/api/check-slug'
            || str_starts_with($uri, '/webhooks/');
    }

    private function resolveSlug(Request $request): ?string
    {
        $host = strtolower($request->host());
        $baseDomain = $_ENV['APP_BASE_DOMAIN'] ?? 'app.eclinicpro.com';

        if (str_ends_with($host, '.' . $baseDomain)) {
            $slug = substr($host, 0, -strlen('.' . $baseDomain));

            return $slug !== '' && $slug !== 'www' ? $slug : null;
        }

        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $token = $_COOKIE['mc_token'] ?? null;
            if ($token !== null) {
                $payload = JwtService::decode($token);
                if (!empty($payload['clinic_id'])) {
                    $row = QueryBuilder::table('tenants')
                        ->where('id', '=', (int) $payload['clinic_id'])
                        ->first();

                    return $row['slug'] ?? null;
                }
            }

            return $_ENV['DEV_CLINIC_SLUG'] ?? 'demo';
        }

        $row = QueryBuilder::table('tenants')
            ->where('custom_domain', '=', $host)
            ->first();

        return $row['slug'] ?? null;
    }

    private function loadClinic(string $slug): ?array
    {
        $cacheKey = "tenant:slug:{$slug}";
        $cached = RedisClient::get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true) ?: null;
        }

        $clinic = QueryBuilder::table('tenants')->where('slug', '=', $slug)->first();
        if ($clinic !== null) {
            RedisClient::setex($cacheKey, 600, json_encode($clinic));
        }

        return $clinic;
    }
}
