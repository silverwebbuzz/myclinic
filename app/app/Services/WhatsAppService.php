<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MessagingSettings;

/**
 * WhatsAppService — sends via Meta WhatsApp Cloud API (direct, platform-owned).
 *
 * Phase WhatsApp rewrite:
 *   - Credentials from platform_settings (admin-editable), not env.
 *   - Sends APPROVED template messages (Meta requires this for
 *     business-initiated messages). Falls back to a plain text send only when
 *     a template isn't approved yet (best-effort during rollout / inside a
 *     service window).
 *   - Captures the Meta message id (wamid) so the status webhook can match it.
 *   - Dev stub: when not configured, logs to storage/logs/whatsapp.log and
 *     reports ok so the queue keeps flowing in dev.
 *
 * @return array{ok: bool, message: string, wamid?: ?string, configured: bool}
 */
final class WhatsAppService
{
    /** @param array<string, mixed> $payload */
    public static function send(string $toNumber, string $template, array $payload): array
    {
        $to = preg_replace('/\D/', '', $toNumber) ?? '';

        // Not configured → dev stub (log + pretend success so queue advances).
        if (!MessagingSettings::whatsappConfigured()) {
            self::logDev($to, $template, WaTemplateService::renderPlain($template, $payload));
            return ['ok' => true, 'message' => 'dev-stub: logged', 'wamid' => null, 'configured' => false];
        }

        $token = (string) MessagingSettings::waAccessToken();
        $phoneId = (string) MessagingSettings::waPhoneNumberId();

        // Business-initiated WhatsApp messages MUST use an approved template.
        // Meta forbids free-form business-initiated text outside the 24h service
        // window; sending it is an Acceptable-Use breach (the cause of the WABA
        // ban). If no approved template exists, DO NOT fall back to plain text —
        // fail so the caller keeps the message queued/flagged instead of sending.
        if (!WaTemplateService::isApproved($template)) {
            return [
                'ok' => false,
                'message' => "no approved WhatsApp template for '{$template}' — send held (approve template in Meta first)",
                'wamid' => null,
                'configured' => true,
            ];
        }

        $body = self::templatePayload($to, $template, $payload);

        $ch = curl_init("https://graph.facebook.com/v18.0/{$phoneId}/messages");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string) $response, true);

        if ($code >= 200 && $code < 300) {
            return [
                'ok' => true,
                'message' => 'sent',
                'wamid' => $data['messages'][0]['id'] ?? null,
                'configured' => true,
            ];
        }

        // Meta has no matching template (marked approved locally but not in BM).
        // Do NOT fall back to plain text — surface the error so the template gets
        // synced in Meta. Business-initiated plain text is a policy breach.
        if (self::isMissingMetaTemplate($data)) {
            return [
                'ok' => false,
                'message' => "WhatsApp template '{$template}' not found in Meta — sync/approve it in Business Manager",
                'wamid' => null,
                'configured' => true,
            ];
        }

        return [
            'ok' => false,
            'message' => $data['error']['message'] ?? ('HTTP ' . $code . ': ' . (string) $response),
            'wamid' => null,
            'configured' => true,
        ];
    }

    /**
     * Meta template-message payload.
     *
     * Utility/marketing: positional body {{n}} parameters.
     * Authentication (copy_code): OTP in body + button (sub_type url, index 0).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function templatePayload(string $to, string $template, array $payload): array
    {
        $components = WaTemplateService::isAuthentication($template)
            ? self::authenticationComponents($template, $payload)
            : self::utilityComponents($template, $payload);

        return [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => WaTemplateService::metaName($template) ?? $template,
                'language' => ['code' => WaTemplateService::language($template)],
                'components' => $components,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private static function utilityComponents(string $template, array $payload): array
    {
        $params = array_map(
            static fn ($v) => ['type' => 'text', 'text' => (string) $v],
            WaTemplateService::params($template, $payload),
        );

        return $params === [] ? [] : [['type' => 'body', 'parameters' => $params]];
    }

    /**
     * Authentication copy_code templates require the OTP in both body and button.
     *
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private static function authenticationComponents(string $template, array $payload): array
    {
        $params = WaTemplateService::params($template, $payload);
        $code = (string) ($params[0] ?? $payload['code'] ?? '');
        if ($code === '') {
            return [];
        }

        $otp = ['type' => 'text', 'text' => $code];

        return [
            ['type' => 'body', 'parameters' => [$otp]],
            [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [$otp],
            ],
        ];
    }

    /** @param array<string, mixed>|null $data */
    private static function isMissingMetaTemplate(?array $data): bool
    {
        $code = (int) ($data['error']['code'] ?? 0);
        if ($code === 132001 || $code === 132000) {
            return true;
        }
        $msg = strtolower((string) ($data['error']['message'] ?? ''));

        return str_contains($msg, 'does not exist')
            || str_contains($msg, 'template name')
            || str_contains($msg, 'not found');
    }

    private static function logDev(string $to, string $template, string $body): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . '/whatsapp.log',
            date('c') . " | {$to} | {$template} | {$body}\n",
            FILE_APPEND,
        );
    }
}
