<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\ApiKeyService;
use App\Services\RedisClient;

final class ApiBearerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $header = $request->header('Authorization', '') ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return Response::json(['error' => 'Missing Bearer token'], 401);
        }

        $token = trim(substr($header, 7));
        $auth = ApiKeyService::validate($token);
        if ($auth === null) {
            return Response::json(['error' => 'Invalid API key'], 401);
        }

        $clinic = $this->loadClinic($auth['clinic_id']);
        if ($clinic === null || !(int) ($clinic['is_active'] ?? 0)) {
            return Response::json(['error' => 'Clinic inactive'], 403);
        }

        RequestContext::setClinic($clinic);
        RequestContext::setApiAuth($auth);

        return $next();
    }

    private function loadClinic(int $clinicId): ?array
    {
        $cacheKey = "tenant:id:{$clinicId}";
        $cached = RedisClient::get($cacheKey);
        if ($cached !== null) {
            return json_decode($cached, true) ?: null;
        }

        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($clinic !== null) {
            RedisClient::setex($cacheKey, 600, json_encode($clinic));
        }

        return $clinic;
    }
}
