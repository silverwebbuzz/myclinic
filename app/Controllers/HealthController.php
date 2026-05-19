<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\RedisClient;

final class HealthController
{
    public function index(Request $request): Response
    {
        $dbOk = Database::ping();
        $redisOk = RedisClient::ping();
        $storageOk = is_writable(dirname(__DIR__, 2) . '/storage');
        $r2Configured = !empty($_ENV['R2_BUCKET']) && !empty($_ENV['R2_ACCESS_KEY']);

        $checks = [
            'db' => $dbOk ? 'ok' : 'fail',
            'redis' => $redisOk ? 'ok' : 'degraded',
            'storage' => $storageOk ? 'ok' : 'fail',
            'r2' => $r2Configured ? 'configured' : 'not_configured',
        ];

        $criticalOk = $dbOk && $storageOk;
        $status = $criticalOk ? ($redisOk ? 'ok' : 'degraded') : 'unhealthy';

        return Response::json([
            'status' => $status,
            'checks' => $checks,
            'version' => self::appVersion(),
            'environment' => $_ENV['APP_ENV'] ?? 'unknown',
            'timestamp' => gmdate('c'),
        ], $criticalOk ? 200 : 503);
    }

    private static function appVersion(): string
    {
        $composer = dirname(__DIR__, 2) . '/composer.json';
        if (!is_file($composer)) {
            return '0.0.0';
        }
        $data = json_decode((string) file_get_contents($composer), true);

        return is_array($data) ? (string) ($data['version'] ?? '1.0.0') : '1.0.0';
    }
}
