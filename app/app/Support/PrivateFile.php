<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Patient documents (prescriptions, invoices, diet plans, patient photos) are
 * stored OUTSIDE the public webroot, under app/storage/, and streamed by PHP
 * after an access check. Database columns hold a path relative to the app
 * root ("storage/invoices/12/inv-345.pdf").
 *
 * Older rows still hold public paths ("/uploads/invoices/12/..."); abs()
 * resolves both, so nothing breaks before the move script has been run.
 */
final class PrivateFile
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Absolute path on disk for a stored path (new "storage/..." or legacy "/uploads/..."). */
    public static function abs(string $stored): string
    {
        $stored = trim($stored);
        if (str_starts_with($stored, 'storage/')) {
            return self::root() . '/' . $stored;
        }

        return self::root() . '/public/' . ltrim($stored, '/');
    }

    /** Directory under app/storage, created if missing. Returns [absoluteDir, relativeDir]. */
    public static function dir(string $relativeUnderStorage): array
    {
        $rel = 'storage/' . trim($relativeUnderStorage, '/');
        $abs = self::root() . '/' . $rel;
        if (!is_dir($abs) && !@mkdir($abs, 0755, true) && !is_dir($abs)) {
            throw new \RuntimeException('Cannot create directory ' . $rel);
        }

        return [$abs, $rel];
    }

    /** Delete a stored file (either kind). Silent when missing. */
    public static function delete(?string $stored): void
    {
        if ($stored === null || trim($stored) === '') {
            return;
        }
        $abs = self::abs($stored);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
