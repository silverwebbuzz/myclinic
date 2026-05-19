<?php

declare(strict_types=1);

namespace App\Services;

final class TwilioSmsService
{
    /** @return array{ok: bool, message: string} */
    public static function send(string $toNumber, string $body): array
    {
        $sid = $_ENV['TWILIO_ACCOUNT_SID'] ?? '';
        $token = $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        $from = $_ENV['TWILIO_FROM_NUMBER'] ?? '';

        if ($sid === '' || $token === '' || $from === '') {
            $dir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($dir . '/sms.log', date('c') . " | {$toNumber} | {$body}\n", FILE_APPEND);

            return ['ok' => true, 'message' => 'Dev stub logged'];
        }

        $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_POSTFIELDS => http_build_query([
                'From' => $from,
                'To' => $toNumber,
                'Body' => $body,
            ]),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['ok' => $code >= 200 && $code < 300, 'message' => (string) $response];
    }
}
