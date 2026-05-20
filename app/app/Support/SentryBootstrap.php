<?php

declare(strict_types=1);

namespace App\Support;

final class SentryBootstrap
{
    public static function register(): void
    {
        $dsn = $_ENV['SENTRY_DSN'] ?? '';
        if ($dsn === '') {
            return;
        }

        set_exception_handler(static function (\Throwable $e): void {
            self::capture($e);
            if (filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw $e;
            }
            http_response_code(500);
            echo 'Internal Server Error';
        });
    }

    public static function capture(\Throwable $e): void
    {
        $dsn = $_ENV['SENTRY_DSN'] ?? '';
        if ($dsn === '') {
            self::logLocal($e);

            return;
        }

        $payload = [
            'event_id' => bin2hex(random_bytes(16)),
            'timestamp' => gmdate('c'),
            'platform' => 'php',
            'level' => 'error',
            'message' => $e->getMessage(),
            'exception' => [
                'values' => [[
                    'type' => $e::class,
                    'value' => $e->getMessage(),
                    'stacktrace' => ['frames' => self::frames($e)],
                ]],
            ],
            'environment' => $_ENV['APP_ENV'] ?? 'production',
        ];

        $projectId = self::projectIdFromDsn($dsn);
        if ($projectId === null) {
            self::logLocal($e);

            return;
        }

        $url = "https://sentry.io/api/{$projectId}/store/";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Sentry-Auth: Sentry sentry_version=7, sentry_key=' . self::keyFromDsn($dsn),
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /** @return list<array<string, mixed>> */
    private static function frames(\Throwable $e): array
    {
        $frames = [];
        foreach ($e->getTrace() as $frame) {
            $frames[] = [
                'filename' => $frame['file'] ?? '',
                'lineno' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
            ];
        }

        return array_reverse($frames);
    }

    private static function keyFromDsn(string $dsn): string
    {
        $parts = parse_url($dsn);

        return $parts['user'] ?? '';
    }

    private static function projectIdFromDsn(string $dsn): ?string
    {
        $path = parse_url($dsn, PHP_URL_PATH);

        return $path !== null ? trim($path, '/') : null;
    }

    private static function logLocal(\Throwable $e): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir . '/sentry.log',
            date('c') . ' ' . $e::class . ': ' . $e->getMessage() . "\n",
            FILE_APPEND,
        );
    }
}
