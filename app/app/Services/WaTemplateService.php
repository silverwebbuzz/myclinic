<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * WaTemplateService — resolves messaging templates from the admin-managed
 * `wa_templates` registry (Phase WhatsApp).
 *
 * Each template defines:
 *   - body_text with positional {{1}} {{2}} placeholders
 *   - variables: ordered list of payload keys → which {{n}} they fill
 *   - sms_fallback_text: plain version for SMS fallback
 *   - meta_name / language / category for the Meta Cloud API call
 *
 * Falls back to the legacy NotificationTemplateService::render() for old
 * template keys not present in wa_templates, so existing callers keep working.
 */
final class WaTemplateService
{
    /** @var array<string,?array>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed>|null */
    public static function find(string $templateKey): ?array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                $rows = Database::connection()
                    ->query('SELECT * FROM wa_templates WHERE is_active = 1')
                    ->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    self::$cache[$r['template_key']] = $r;
                }
            } catch (\Throwable $e) {
                // table missing pre-migration
            }
        }
        return self::$cache[$templateKey] ?? null;
    }

    /**
     * Ordered positional parameters [{{1}}, {{2}}, ...] from the payload,
     * per the template's `variables` mapping.
     *
     * @param array<string,mixed> $payload
     * @return list<string>
     */
    public static function params(string $templateKey, array $payload): array
    {
        $tpl = self::find($templateKey);
        if (!$tpl || empty($tpl['variables'])) {
            return [];
        }
        $vars = json_decode((string) $tpl['variables'], true);
        if (!is_array($vars)) {
            return [];
        }
        $out = [];
        foreach ($vars as $key) {
            $out[] = (string) ($payload[$key] ?? '');
        }
        return $out;
    }

    /**
     * Render the body to a plain string (for SMS fallback + dev log + preview).
     * Uses sms_fallback_text if present, else body_text, substituting {{n}}.
     *
     * @param array<string,mixed> $payload
     */
    public static function renderPlain(string $templateKey, array $payload): string
    {
        $tpl = self::find($templateKey);
        if (!$tpl) {
            // Legacy templates still handled by the old service.
            return NotificationTemplateService::render($templateKey, $payload);
        }
        $text = !empty($tpl['sms_fallback_text']) ? $tpl['sms_fallback_text'] : $tpl['body_text'];
        $params = self::params($templateKey, $payload);
        foreach ($params as $i => $val) {
            $text = str_replace('{{' . ($i + 1) . '}}', $val, $text);
        }
        return $text;
    }

    public static function metaName(string $templateKey): ?string
    {
        $tpl = self::find($templateKey);
        return $tpl['meta_name'] ?? null;
    }

    public static function language(string $templateKey): string
    {
        $tpl = self::find($templateKey);
        return $tpl['language'] ?? 'en';
    }

    /** Only 'approved' templates may be sent as WhatsApp template messages. */
    public static function isApproved(string $templateKey): bool
    {
        $tpl = self::find($templateKey);
        return $tpl !== null && ($tpl['status'] ?? '') === 'approved';
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
