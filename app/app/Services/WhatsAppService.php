<?php

declare(strict_types=1);

namespace App\Services;

final class WhatsAppService
{
    /** @param array<string, mixed> $payload @return array{ok: bool, message: string} */
    public static function send(string $toNumber, string $template, array $payload): array
    {
        $token = $_ENV['WHATSAPP_API_TOKEN'] ?? '';
        $phoneId = $_ENV['WHATSAPP_PHONE_ID'] ?? '';

        $body = NotificationTemplateService::render($template, $payload);

        if ($token === '' || $phoneId === '') {
            self::logDev($toNumber, $template, $body);

            return ['ok' => true, 'message' => 'Dev stub: logged to storage/logs/whatsapp.log'];
        }

        $ch = curl_init("https://graph.facebook.com/v18.0/{$phoneId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D/', '', $toNumber),
                'type' => 'text',
                'text' => ['body' => $body],
            ]),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'message' => 'sent'];
        }

        return ['ok' => false, 'message' => (string) $response];
    }

    private static function logDev(string $to, string $template, string $body): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir . '/whatsapp.log',
            date('c') . " | {$to} | {$template} | {$body}\n",
            FILE_APPEND,
        );
    }
}
