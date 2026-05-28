<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MessagingSettings;

/**
 * SmsService — single entry point for outbound SMS across the whole system
 * (portal + marketing). Provider chosen by platform_settings.sms_provider:
 *   - 'msg91'  → MSG91 transactional API (India, DLT-registered)
 *   - 'twilio' → delegates to TwilioSmsService
 *
 * Used as the WhatsApp delivery-failure fallback and for any direct SMS.
 * OTP keeps its own fast path in partials/sms.php (latency-critical) — this
 * service is for queued/transactional messages.
 *
 * @return array{ok: bool, message: string, provider_id?: ?string}
 */
final class SmsService
{
    public static function send(string $toNumber, string $body): array
    {
        $provider = MessagingSettings::smsProvider();

        if ($provider === 'twilio') {
            $r = TwilioSmsService::send($toNumber, $body);
            return ['ok' => $r['ok'], 'message' => $r['message'], 'provider_id' => null];
        }

        // Default: MSG91
        return self::sendViaMsg91($toNumber, $body);
    }

    /** @return array{ok: bool, message: string, provider_id?: ?string} */
    private static function sendViaMsg91(string $toNumber, string $body): array
    {
        $authKey = MessagingSettings::smsAuthKey();
        $sender = MessagingSettings::smsSenderId();

        if ($authKey === null || $sender === null) {
            self::logDev($toNumber, $body);
            return ['ok' => true, 'message' => 'dev-stub: logged', 'provider_id' => null];
        }

        $to = preg_replace('/\D/', '', $toNumber) ?? '';
        // MSG91 flow/v5: simple transactional send.
        $ch = curl_init('https://control.msg91.com/api/v5/flow/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'authkey: ' . $authKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'sender' => $sender,
                'short_url' => '0',
                'mobiles' => $to,
                'message' => $body,
            ]),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);
        $ok = $code >= 200 && $code < 300;

        return [
            'ok' => $ok,
            'message' => $ok ? 'sent' : ('HTTP ' . $code . ': ' . (string) $response),
            'provider_id' => $data['request_id'] ?? null,
        ];
    }

    private static function logDev(string $to, string $body): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/sms.log', date('c') . " | {$to} | {$body}\n", FILE_APPEND);
    }
}
