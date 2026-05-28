<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * MessagingSettings — reads platform-wide messaging credentials/config from
 * the `platform_settings` key/value table (admin-editable, no redeploy).
 *
 * One DB read per request, memoized. Used by WhatsAppService, SmsService,
 * NotificationProcessor, and the webhook handlers.
 *
 * Keys: messaging_enabled, wa_access_token, wa_phone_number_id,
 *       wa_business_id, wa_webhook_verify_token, wa_app_secret,
 *       sms_provider, sms_auth_key, sms_sender_id.
 */
final class MessagingSettings
{
    /** @var array<string,?string>|null */
    private static ?array $cache = null;

    /** Load (once) the full settings map. */
    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $map = [];
        try {
            $rows = Database::connection()
                ->query('SELECT setting_key, setting_value FROM platform_settings')
                ->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $map[$r['setting_key']] = $r['setting_value'];
            }
        } catch (\Throwable $e) {
            // platform_settings doesn't exist yet (pre-migration) — empty map.
        }
        return self::$cache = $map;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = self::all()[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    /** Master toggle. Messaging is off unless explicitly enabled AND configured. */
    public static function enabled(): bool
    {
        return self::get('messaging_enabled', '0') === '1';
    }

    // ---- WhatsApp creds ----
    public static function waAccessToken(): ?string   { return self::get('wa_access_token'); }
    public static function waPhoneNumberId(): ?string { return self::get('wa_phone_number_id'); }
    public static function waBusinessId(): ?string    { return self::get('wa_business_id'); }
    public static function waVerifyToken(): ?string   { return self::get('wa_webhook_verify_token'); }
    public static function waAppSecret(): ?string     { return self::get('wa_app_secret'); }

    /** WhatsApp is usable only when enabled + token + phone id are all present. */
    public static function whatsappConfigured(): bool
    {
        return self::enabled()
            && self::waAccessToken() !== null
            && self::waPhoneNumberId() !== null;
    }

    // ---- SMS creds ----
    public static function smsProvider(): string { return self::get('sms_provider', 'msg91') ?? 'msg91'; }
    public static function smsAuthKey(): ?string { return self::get('sms_auth_key'); }
    public static function smsSenderId(): ?string { return self::get('sms_sender_id'); }

    public static function smsConfigured(): bool
    {
        return self::smsAuthKey() !== null;
    }

    /** Persist one setting (admin UI). Clears the cache. */
    public static function set(string $key, ?string $value): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->execute([':k' => $key, ':v' => $value === '' ? null : $value]);
        self::$cache = null;
    }

    /** Test seam. */
    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
