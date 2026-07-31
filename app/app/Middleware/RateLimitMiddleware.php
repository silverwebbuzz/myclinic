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
        // Loading the forgot-password form must never burn the submit quota.
        if ($request->uri === '/forgot-password' && $request->method === 'GET') {
            return $next($request);
        }
        if ($request->uri === '/forgot-username' && $request->method === 'GET') {
            return $next($request);
        }

        [$limit, $window] = $this->limitsFor($request);
        $subject = $this->rateSubject($request);
        $key = 'rate:' . md5($subject . ':' . $request->method . ':' . $request->uri);
        $client = RedisClient::connection();

        if ($client !== null) {
            $count = (int) $client->incr($key);
            if ($count === 1) {
                $client->expire($key, $window);
            }
            if ($count > $limit) {
                return $this->tooMany($request, $window);
            }
        }

        return $next($request);
    }

    private function tooMany(Request $request, int $window): Response
    {
        $retry = (string) $window;

        if ($request->isJson() || str_starts_with($request->uri, '/api/')) {
            return Response::json(['error' => 'Too many requests'], 429)
                ->withHeader('Retry-After', $retry);
        }

        if (str_starts_with($request->uri, '/forgot-password')) {
            return Response::redirect('/forgot-password?rate_limited=1');
        }
        if (str_starts_with($request->uri, '/forgot-username')) {
            return Response::redirect('/forgot-username?rate_limited=1');
        }

        if ($request->uri === '/login') {
            return Response::redirect('/login?error=' . urlencode('Too many login attempts. Please wait a minute and try again.'));
        }

        return Response::html(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Too many requests</title></head>'
            . '<body style="font-family:sans-serif;padding:2rem;text-align:center">'
            . '<h1>Too many requests</h1><p>Please wait a few minutes and try again.</p>'
            . '<p><a href="/login">Back to login</a></p></body></html>',
            429,
        )->withHeader('Retry-After', $retry);
    }

    /** @return array{0: int, 1: int} */
    private function limitsFor(Request $request): array
    {
        if ($request->uri === '/login') {
            return [10, 60];
        }
        if (str_starts_with($request->uri, '/forgot-password')) {
            return [5, 3600];
        }
        if (str_starts_with($request->uri, '/forgot-username')) {
            return [5, 3600];
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
