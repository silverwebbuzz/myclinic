<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Services\SmtpMailService;

/**
 * Emails for the company portal, sent from the noreply@ mailbox over the
 * existing SMTP setup. Only sends when an address is present and valid.
 * Never throws; failures go to the PHP error log.
 */
final class CorpMailer
{
    public static function appUrl(): string
    {
        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/');
    }

    /** Where "new company registered" alerts go (ops / sales inbox). */
    public static function opsEmail(): string
    {
        return (string) ($_ENV['CORP_ALERT_EMAIL'] ?? 'hello@eclinicpro.com');
    }

    /**
     * @param list<string> $paragraphs plain text; escaped here
     */
    public static function send(string $to, string $subject, string $heading, array $paragraphs, ?string $ctaUrl = null, ?string $ctaLabel = null): bool
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        try {
            $res = SmtpMailService::send(
                $to,
                $subject,
                self::html($heading, $paragraphs, $ctaUrl, $ctaLabel),
                (string) ($_ENV['NOREPLY_FROM'] ?? 'noreply@eclinicpro.com'),
                'eClinicPro Workforce Health',
                null,
                true,
            );
            if (!$res['ok']) {
                error_log('[CorpMailer] "' . $subject . '" to ' . $to . ' failed: ' . ($res['error'] ?? ''));
            }

            return $res['ok'];
        } catch (\Throwable $e) {
            error_log('[CorpMailer] ' . $e->getMessage());

            return false;
        }
    }

    /** @param list<string> $paragraphs */
    private static function html(string $heading, array $paragraphs, ?string $ctaUrl, ?string $ctaLabel): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $body = '';
        foreach ($paragraphs as $p) {
            $body .= '<p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#334155">' . nl2br($e($p)) . '</p>';
        }
        $cta = '';
        if ($ctaUrl !== null && $ctaLabel !== null) {
            $cta = '<p style="margin:22px 0 18px"><a href="' . $e($ctaUrl) . '" style="display:inline-block;background:#1d4ed8;color:#ffffff;'
                . 'text-decoration:none;font-weight:600;font-size:14px;padding:11px 20px;border-radius:7px">' . $e($ctaLabel) . '</a></p>'
                . '<p style="margin:0 0 14px;font-size:12px;color:#64748b">Or paste this link into your browser:<br>'
                . '<span style="word-break:break-all">' . $e($ctaUrl) . '</span></p>';
        }

        return '<!DOCTYPE html><html><body style="margin:0;background:#f5f6f8;font-family:Inter,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f6f8;padding:28px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e4e7ec;border-radius:10px">'
            . '<tr><td style="padding:22px 28px 6px;font-size:13px;font-weight:600;color:#1d4ed8">eClinicPro Workforce Health</td></tr>'
            . '<tr><td style="padding:6px 28px 24px">'
            . '<h1 style="margin:0 0 16px;font-size:19px;color:#0f172a">' . $e($heading) . '</h1>'
            . $body . $cta
            . '</td></tr></table>'
            . '<p style="font-size:11.5px;color:#94a3b8;margin:14px 0 0">This is an automated message from eClinicPro. Please do not reply.</p>'
            . '</td></tr></table></body></html>';
    }
}
