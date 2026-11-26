<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\PrivateFile;
use PDO;

/**
 * Signed, expiring links that let a patient open their invoice or diet plan
 * PDF from WhatsApp/email without logging in:
 *
 *   {APP_URL}/d/{type}/{clinic}-{id}-{expiresUnix}-{hmac}
 *
 * The HMAC (JWT_SECRET) covers type, clinic, id and expiry, so a link can't
 * be guessed, altered to another document, or extended. The file itself lives
 * in private storage and is streamed by DocumentLinkController.
 * (Prescriptions use their own revocable links: RxWhatsAppService.)
 */
final class DocumentLinkService
{
    public const TTL_DAYS = 30;

    /** type => [table, column holding the stored path] */
    private const TYPES = [
        'invoice' => ['invoices', 'pdf_path'],
        'diet' => ['diet_plans', 'pdf_path'],
    ];

    public static function url(string $type, int $clinicId, int $id, int $days = self::TTL_DAYS): string
    {
        $exp = time() + $days * 86400;
        $token = $clinicId . '-' . $id . '-' . $exp . '-' . self::sign($type, $clinicId, $id, $exp);

        return rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/') . '/d/' . $type . '/' . $token;
    }

    /**
     * Absolute PDF path for a valid token, or null (bad/expired/forged/missing).
     */
    public static function resolve(string $type, string $token): ?string
    {
        if (!isset(self::TYPES[$type]) || !preg_match('/^(\d{1,19})-(\d{1,19})-(\d{9,11})-([a-f0-9]{40})$/', $token, $m)) {
            return null;
        }
        [$all, $clinicId, $id, $exp, $sig] = $m;
        if ((int) $exp < time()) {
            return null;
        }
        try {
            if (!hash_equals(self::sign($type, (int) $clinicId, (int) $id, (int) $exp), $sig)) {
                return null;
            }
            [$table, $col] = self::TYPES[$type];
            $stmt = Database::connection()->prepare("SELECT {$col} FROM {$table} WHERE id = ? AND clinic_id = ? LIMIT 1");
            $stmt->execute([(int) $id, (int) $clinicId]);
            $stored = (string) ($stmt->fetch(PDO::FETCH_COLUMN) ?: '');
        } catch (\Throwable $e) {
            error_log('[DocumentLink] ' . $e->getMessage());

            return null;
        }
        if ($stored === '') {
            return null;
        }
        $abs = PrivateFile::abs($stored);

        return is_file($abs) ? $abs : null;
    }

    private static function sign(string $type, int $clinicId, int $id, int $exp): string
    {
        $secret = (string) ($_ENV['JWT_SECRET'] ?? '');
        if ($secret === '' || str_starts_with($secret, 'change-me')) {
            throw new \RuntimeException('JWT_SECRET is not set — cannot sign document links.');
        }

        return substr(hash_hmac('sha256', 'doc|' . $type . '|' . $clinicId . '|' . $id . '|' . $exp, $secret), 0, 40);
    }
}
