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

        if (preg_match('#^/book/([a-z0-9-]+)(?:/slots)?$#', $request->uri, $m)) {
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

        $slug = $this->resolveSlug($request);
        if ($slug === null) {
            return Response::json(['error' => 'Clinic not found'], 404);
        }

        $clinic = $this->loadClinic($slug);
        if ($clinic === null || !(int) ($clinic['is_active'] ?? 0)) {
            return Response::json(['error' => 'Clinic not found'], 404);
        }

        RequestContext::setClinic($clinic);

        return $next();
    }

    private function isExemptPath(string $uri): bool
    {
        return $uri === '/health'
            || str_starts_with($uri, '/admin')
            || str_starts_with($uri, '/api/v1/rest')
            || str_starts_with($uri, '/api/v1/public')
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
        $baseDomain = $_ENV['APP_BASE_DOMAIN'] ?? 'app.manageclinic.com';

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
