<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The ONE way to show a date to a person (Indian format). Accepts a DB value
 * ("2026-10-06", "2026-10-06 15:45:00"), a timestamp, or null/empty.
 *
 *   IndianDate::date($v)       06 Oct 2026
 *   IndianDate::dateTime($v)   06 Oct 2026, 03:45 PM
 *   IndianDate::short($v)      06 Oct
 *   IndianDate::shortTime($v)  06 Oct, 03:45 PM
 *   IndianDate::time($v)       03:45 PM
 *
 * Empty / unparseable → $empty ('—' by default). Store and submit dates as
 * Y-m-d / Y-m-d H:i:s; only DISPLAY goes through here. In JS use 'en-IN'.
 */
final class IndianDate
{
    public const DATE = 'd M Y';
    public const DATE_TIME = 'd M Y, h:i A';
    public const SHORT = 'd M';
    public const SHORT_TIME = 'd M, h:i A';
    public const TIME = 'h:i A';

    public static function date(string|int|null $v, string $empty = '—'): string
    {
        return self::fmt($v, self::DATE, $empty);
    }

    public static function dateTime(string|int|null $v, string $empty = '—'): string
    {
        return self::fmt($v, self::DATE_TIME, $empty);
    }

    public static function short(string|int|null $v, string $empty = '—'): string
    {
        return self::fmt($v, self::SHORT, $empty);
    }

    public static function shortTime(string|int|null $v, string $empty = '—'): string
    {
        return self::fmt($v, self::SHORT_TIME, $empty);
    }

    public static function time(string|int|null $v, string $empty = '—'): string
    {
        return self::fmt($v, self::TIME, $empty);
    }

    private static function fmt(string|int|null $v, string $format, string $empty): string
    {
        if ($v === null || $v === '' || $v === 0 || str_starts_with((string) $v, '0000-00-00')) {
            return $empty;
        }
        $ts = is_int($v) ? $v : strtotime((string) $v);

        return $ts === false ? $empty : date($format, $ts);
    }
}
