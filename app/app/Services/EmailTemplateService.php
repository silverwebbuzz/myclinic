<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * EmailTemplateService — admin-editable email content.
 *
 * MailService owns the built-in default content (structuredContent()). This
 * service lets a super-admin OVERRIDE that content per template_key via the
 * email_templates table. If no active row exists, MailService uses its code
 * default, so the table can be empty with no ill effect.
 *
 * Editable fields map 1:1 to the structured layout MailService renders:
 *   greeting, body (paragraphs), bullets, cta (label+url), sign_off.
 * The branded HTML wrapper (logo header, footer) is applied by MailService
 * and is NOT editable here.
 */
final class EmailTemplateService
{
    /**
     * Registry of templates that are exposed in the admin UI, with the
     * placeholder variables each one understands. Keep this in sync with the
     * payloads passed to MailService::send() at the various call sites.
     *
     * @var array<string, array{label: string, vars: list<string>}>
     */
    private const REGISTRY = [
        'welcome' => [
            'label' => 'Welcome (clinic created)',
            'vars' => ['clinic_name', 'login_url'],
        ],
        'doctor_approved' => [
            'label' => 'Doctor approved (listing request)',
            'vars' => ['doctor_name', 'clinic_name', 'phone', 'login_url'],
        ],
        'password_reset' => [
            'label' => 'Password reset',
            'vars' => ['reset_url'],
        ],
        'register_verify' => [
            'label' => 'Register email verification',
            'vars' => ['verify_url'],
        ],
        'staff_invite' => [
            'label' => 'Staff invite',
            'vars' => ['name', 'clinic_name', 'role', 'accept_url'],
        ],
    ];

    /** @return array<string, array{label: string, vars: list<string>}> */
    public static function registry(): array
    {
        return self::REGISTRY;
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::REGISTRY[$key]);
    }

    /**
     * Active DB override for a template, or null if none (=> use code default).
     *
     * @return array<string, mixed>|null
     */
    public static function override(string $key): ?array
    {
        if (!self::isKnown($key) || !Database::ping()) {
            return null;
        }
        try {
            $stmt = Database::connection()->prepare(
                'SELECT * FROM email_templates WHERE template_key = :k AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build the structured content array MailService expects, from a DB row,
     * filling {{placeholders}} from the payload.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function toStructured(array $row, array $payload): array
    {
        $fill = static function (?string $s) use ($payload): string {
            $s = (string) $s;
            return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($payload) {
                return (string) ($payload[$m[1]] ?? '');
            }, $s) ?? $s;
        };

        // body: blank-line separated blocks => paragraphs.
        $paragraphs = [];
        foreach (preg_split('/\n\s*\n/', (string) ($row['body'] ?? '')) ?: [] as $block) {
            $block = trim($fill($block));
            if ($block !== '') {
                $paragraphs[] = $block;
            }
        }

        // bullets: one per line.
        $bullets = [];
        foreach (preg_split('/\r?\n/', (string) ($row['bullets'] ?? '')) ?: [] as $line) {
            $line = trim($fill($line));
            if ($line !== '') {
                $bullets[] = $line;
            }
        }

        $content = [
            'greeting' => $fill($row['greeting'] ?? ''),
            'paragraphs' => $paragraphs,
            'bullets' => $bullets,
            'sign_off' => $fill($row['sign_off'] ?? ''),
        ];

        $ctaLabel = trim($fill($row['cta_label'] ?? ''));
        $ctaUrl = trim($fill($row['cta_url'] ?? ''));
        if ($ctaLabel !== '' && $ctaUrl !== '') {
            $content['cta'] = ['label' => $ctaLabel, 'url' => $ctaUrl];
        }

        return $content;
    }

    /**
     * Subject override (with placeholders filled), or null to keep code subject.
     *
     * @param array<string, mixed> $payload
     */
    public static function subject(string $key, array $payload): ?string
    {
        $row = self::override($key);
        if ($row === null || trim((string) ($row['subject'] ?? '')) === '') {
            return null;
        }
        $subject = (string) $row['subject'];

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', static function ($m) use ($payload) {
            return (string) ($payload[$m[1]] ?? '');
        }, $subject) ?? $subject;
    }

    /** All saved rows keyed by template_key (for the admin list). @return array<string, array<string,mixed>> */
    public static function allRows(): array
    {
        if (!Database::ping()) {
            return [];
        }
        try {
            $rows = Database::connection()
                ->query('SELECT * FROM email_templates')
                ->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r['template_key']] = $r;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Insert or update a template's editable content.
     *
     * @param array<string, mixed> $data
     */
    public static function save(string $key, array $data, ?string $adminEmail = null): bool
    {
        if (!self::isKnown($key) || !Database::ping()) {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO email_templates
                    (template_key, subject, greeting, body, bullets, cta_label, cta_url, sign_off, is_active, updated_by)
                 VALUES
                    (:k, :subj, :greet, :body, :bul, :cl, :cu, :so, :act, :by)
                 ON DUPLICATE KEY UPDATE
                    subject = :subj, greeting = :greet, body = :body, bullets = :bul,
                    cta_label = :cl, cta_url = :cu, sign_off = :so, is_active = :act, updated_by = :by'
            );

            return $stmt->execute([
                ':k' => $key,
                ':subj' => trim((string) ($data['subject'] ?? '')),
                ':greet' => trim((string) ($data['greeting'] ?? '')),
                ':body' => (string) ($data['body'] ?? ''),
                ':bul' => trim((string) ($data['bullets'] ?? '')) ?: null,
                ':cl' => trim((string) ($data['cta_label'] ?? '')) ?: null,
                ':cu' => trim((string) ($data['cta_url'] ?? '')) ?: null,
                ':so' => (string) ($data['sign_off'] ?? '') ?: null,
                ':act' => !empty($data['is_active']) ? 1 : 0,
                ':by' => $adminEmail,
            ]);
        } catch (\Throwable $e) {
            error_log('[EmailTemplateService::save] ' . $e->getMessage());

            return false;
        }
    }

    /** Reset a template to code default by removing its override row. */
    public static function reset(string $key): bool
    {
        if (!Database::ping()) {
            return false;
        }
        try {
            $stmt = Database::connection()->prepare('DELETE FROM email_templates WHERE template_key = :k');

            return $stmt->execute([':k' => $key]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
