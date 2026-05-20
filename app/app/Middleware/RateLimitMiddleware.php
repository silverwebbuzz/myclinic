<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;
use App\Services\JwtService;
use App\Services\RedisClient;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        [$limit, $window] = $this->limitsFor($request);
        $subject = $this->rateSubject($request);
        $key = 'rate:' . md5($subject . ':' . $request->uri);
        $client = RedisClient::connection();

        if ($client !== null) {
            $count = (int) $client->incr($key);
            if ($count === 1) {
                $client->expire($key, $window);
            }
            if ($count > $limit) {
                $retry = (string) $window;

                return Response::json(['error' => 'Too many requests'], 429)
                    ->withHeader('Retry-After', $retry);
            }
        }

        return $next();
    }

    /** @return array{0: int, 1: int} */
    private function limitsFor(Request $request): array
    {
        if ($request->uri === '/login') {
            return [10, 60];
        }
        if (str_starts_with($request->uri, '/forgot-password')) {
            return [3, 3600];
        }
        if (str_starts_with($request->uri, '/api/')) {
            return [60, 60];
        }

        return [120, 60];
    }

    private function rateSubject(Request $request): string
    {
        if (str_starts_with($request->uri, '/api/')) {
            $token = $request->cookies['mc_token'] ?? '';
            $payload = $token !== '' ? JwtService::decode($token) : null;

            return 'jwt:' . ($payload['sub'] ?? $request->ip());
        }

        return $request->ip();
    }
}
