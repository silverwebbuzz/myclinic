<?php

declare(strict_types=1);

namespace App\Support\Locale;

/**
 * Money display and input normalisation for any currency.
 *
 *   Money::format('1234.5', 'INR')    ₹1,234.50
 *   Money::format('123456789', 'INR') ₹12,34,56,789.00   (Indian grouping, INR only)
 *   Money::format('1234.5', 'AED')    AED 1,234.50
 *   Money::format('1234.5', 'KWD')    KWD 1,234.500       (3 decimals)
 *   Money::format('-20', 'GBP')       -£20.00
 *   Money::normalise('1,234.567', 'AED') '1234.57'        (for DB DECIMAL columns)
 *
 * UI is English in every country, so separators are always "," and "."
 * (no European 1.234,50). Symbols and decimals come from geo_currencies.
 * Amounts are stored as DECIMAL(15,3) + a CHAR(3) currency code; pass the DB
 * string straight in. 15 significant digits are exact in a float, which
 * covers DECIMAL(15,3).
 */
final class Money
{
    public const EMPTY = '—';

    public static function format(string|int|float|null $amount, string $currency, bool $withSymbol = true): string
    {
        if ($amount === null || $amount === '') {
            return self::EMPTY;
        }
        if (is_string($amount) && !is_numeric(trim($amount))) {
            return self::EMPTY;
        }

        $decimals = self::decimals($currency);
        $value = round((float) $amount, $decimals);
        $negative = $value < 0;
        $abs = abs($value);

        $number = strtoupper($currency) === 'INR'
            ? self::indianGrouping($abs, $decimals)
            : number_format($abs, $decimals, '.', ',');

        if (!$withSymbol) {
            return ($negative ? '-' : '') . $number;
        }

        $symbol = self::symbol($currency);
        // Letter symbols (AED, KSh, R) need a space; sign symbols (₹ £ € $ ₦) don't.
        $sep = preg_match('/^\p{L}+$/u', $symbol) === 1 ? ' ' : '';

        return ($negative ? '-' : '') . $symbol . $sep . $number;
    }

    /**
     * Parse user / CSV input into a plain decimal string rounded to the
     * currency's decimals, or null when it isn't a number.
     * Accepts "1,234.50", " 1234.5 ", "AED 1,234", "₹1,23,456".
     */
    public static function normalise(string|int|float|null $input, string $currency): ?string
    {
        if ($input === null) {
            return null;
        }
        $s = trim((string) $input);
        $symbol = self::symbol($currency);
        $s = str_ireplace([strtoupper($currency), $symbol], '', $s);
        $s = str_replace([',', ' ', "\u{00A0}"], '', $s);
        if ($s === '' || preg_match('/^-?\d+(\.\d+)?$/', $s) !== 1) {
            return null;
        }
        $decimals = self::decimals($currency);

        return number_format(round((float) $s, $decimals), $decimals, '.', '');
    }

    public static function decimals(string $currency): int
    {
        $row = CountryRepo::currency($currency);

        return $row !== null ? (int) $row['minor_units'] : 2;
    }

    public static function symbol(string $currency): string
    {
        $row = CountryRepo::currency($currency);
        $symbol = $row !== null ? trim((string) ($row['symbol'] ?? '')) : '';

        return $symbol !== '' ? $symbol : strtoupper($currency);
    }

    /** 1234567.5 → "12,34,567.50" (last three digits, then pairs). */
    private static function indianGrouping(float $abs, int $decimals): string
    {
        $plain = number_format($abs, $decimals, '.', '');
        [$int, $frac] = array_pad(explode('.', $plain, 2), 2, '');

        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $rest = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest . ',' . $last3;
        }

        return $frac === '' ? $int : $int . '.' . $frac;
    }
}
