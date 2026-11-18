<?php

declare(strict_types=1);

namespace App\Support\Locale;

/**
 * Dates and times for Workforce Health (corp_* tables).
 *
 * RULES
 *  - DATETIME columns in corp_* tables hold UTC. Write them with nowUtc() —
 *    never NOW()/CURRENT_TIMESTAMP (the DB session runs in IST).
 *  - DATE columns (trial_ends_at, licence_expiry, due dates) are calendar
 *    dates in the company's own timezone and are never shifted.
 *  - Display = country date_format / time_format (default "d M Y" / "h:i A",
 *    the house Indian style), in the viewer's timezone.
 *
 *   LocalDate::nowUtc()                                        2026-11-18 09:30:00
 *   LocalDate::dateTime('2026-11-18 09:30:00', 'Asia/Dubai')   18 Nov 2026, 01:30 PM
 *   LocalDate::date('2026-12-18')                              18 Dec 2026
 *   LocalDate::today('Asia/Kolkata')                           2026-11-18
 */
final class LocalDate
{
    public const EMPTY = '—';
    public const DATE_FORMAT = 'd M Y';
    public const TIME_FORMAT = 'h:i A';

    /** Current UTC time as a DATETIME string, for corp_* columns. */
    public static function nowUtc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** UTC DATETIME string $seconds from now (token expiry etc.). */
    public static function utcPlusSeconds(int $seconds): string
    {
        return gmdate('Y-m-d H:i:s', time() + $seconds);
    }

    /** Today's calendar date (Y-m-d) in a timezone, e.g. for starting a trial. */
    public static function today(string $timezone): string
    {
        return (new \DateTimeImmutable('now', self::zone($timezone)))->format('Y-m-d');
    }

    /** Y-m-d + N days (calendar arithmetic, no timezone involved). */
    public static function addDays(string $ymd, int $days): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, new \DateTimeZone('UTC'));
        if ($d === false) {
            throw new \InvalidArgumentException('Expected Y-m-d, got: ' . $ymd);
        }

        return $d->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    /** Show a DATE column (or the date part of a value) without shifting it. */
    public static function date(?string $value, string $format = self::DATE_FORMAT, string $empty = self::EMPTY): string
    {
        if (self::isEmpty($value)) {
            return $empty;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10), new \DateTimeZone('UTC'));

        return $d === false ? $empty : $d->format($format);
    }

    /** Show a UTC DATETIME in the viewer's timezone. */
    public static function dateTime(
        ?string $utc,
        string $timezone,
        string $dateFormat = self::DATE_FORMAT,
        string $timeFormat = self::TIME_FORMAT,
        string $empty = self::EMPTY,
    ): string {
        $d = self::toZone($utc, $timezone);

        return $d === null ? $empty : $d->format($dateFormat . ', ' . $timeFormat);
    }

    /** Only the time part of a UTC DATETIME, in the viewer's timezone. */
    public static function time(?string $utc, string $timezone, string $timeFormat = self::TIME_FORMAT, string $empty = self::EMPTY): string
    {
        $d = self::toZone($utc, $timezone);

        return $d === null ? $empty : $d->format($timeFormat);
    }

    /** Whole days from today (in $timezone) until a Y-m-d date; negative once past. */
    public static function daysUntil(string $ymd, string $timezone): int
    {
        $today = new \DateTimeImmutable(self::today($timezone), new \DateTimeZone('UTC'));
        $end = new \DateTimeImmutable(substr($ymd, 0, 10), new \DateTimeZone('UTC'));

        return (int) $today->diff($end)->format('%r%a');
    }

    /** A valid DateTimeZone, falling back to UTC for empty / unknown names. */
    public static function zone(?string $timezone): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezone !== null && $timezone !== '' ? $timezone : 'UTC');
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }

    public static function isValidZone(?string $timezone): bool
    {
        return $timezone !== null && $timezone !== ''
            && in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }

    private static function toZone(?string $utc, string $timezone): ?\DateTimeImmutable
    {
        if (self::isEmpty($utc)) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $utc, new \DateTimeZone('UTC'));
        if ($d === false) {
            return null;
        }

        return $d->setTimezone(self::zone($timezone));
    }

    private static function isEmpty(?string $v): bool
    {
        return $v === null || $v === '' || str_starts_with($v, '0000-00-00');
    }
}
