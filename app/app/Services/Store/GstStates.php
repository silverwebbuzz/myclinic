<?php

declare(strict_types=1);

namespace App\Services\Store;

/**
 * GST state codes (first two digits of a GSTIN). Used to decide place of supply:
 * seller state = buyer (delivery) state → CGST + SGST, otherwise IGST.
 */
final class GstStates
{
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
        '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
        '10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
        '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
        '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];

    private const ALIASES = [
        'jammu kashmir' => '01', 'j and k' => '01', 'jk' => '01', 'uttaranchal' => '05', 'new delhi' => '07',
        'nct of delhi' => '07', 'delhi ncr' => '07', 'up' => '09', 'orissa' => '21', 'mp' => '23',
        'daman and diu' => '26', 'dadra and nagar haveli' => '26', 'daman' => '26', 'diu' => '26', 'dnh' => '26',
        'pondicherry' => '34', 'andaman' => '35', 'andaman nicobar' => '35', 'tamilnadu' => '33', 'tn' => '33',
        'ap' => '37', 'wb' => '19', 'bengal' => '19', 'maharastra' => '27', 'karnatak' => '29',
    ];

    /** State name (any common spelling) → 2-digit code, or null if unknown. */
    public static function codeFor(?string $name): ?string
    {
        $n = strtolower(trim((string) $name));
        $n = trim((string) preg_replace('/\s+/', ' ', str_replace(['&', '-', '.', ','], [' and ', ' ', '', ' '], $n)));
        if ($n === '') {
            return null;
        }
        static $byName = null;
        $byName ??= array_flip(array_map('strtolower', self::STATES));
        if (isset($byName[$n])) {
            return $byName[$n];
        }
        $compact = str_replace(' and ', ' ', $n);

        return self::ALIASES[$n] ?? self::ALIASES[$compact] ?? (preg_match('/^\d{2}$/', $n) && isset(self::STATES[$n]) ? $n : null);
    }

    public static function fromGstin(?string $gstin): ?string
    {
        $code = substr(strtoupper(trim((string) $gstin)), 0, 2);

        return isset(self::STATES[$code]) ? $code : null;
    }

    public static function name(?string $code): string
    {
        return self::STATES[(string) $code] ?? '';
    }

    /** Loose GSTIN format check (15 chars: 2-digit state, PAN, entity, Z, checksum). */
    public static function validGstin(string $gstin): bool
    {
        return (bool) preg_match('/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', strtoupper($gstin))
            && self::fromGstin($gstin) !== null;
    }
}
