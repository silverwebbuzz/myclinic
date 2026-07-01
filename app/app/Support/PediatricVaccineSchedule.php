<?php

declare(strict_types=1);

namespace App\Support;

/**
 * IAP-style pediatric immunization schedule for the visit screen.
 */
final class PediatricVaccineSchedule
{
    public const MAX_CHILD_AGE_YEARS = 15;

    /** @return list<array{key: string, age: string, vaccine: string}> */
    public static function schedule(): array
    {
        static $rows = null;
        if ($rows !== null) {
            return $rows;
        }

        $raw = [
            ['Birth', 'BCG'],
            ['Birth', 'OPV-0'],
            ['Birth', 'Hepatitis B-1'],
            ['6 Weeks', 'DTP/DTaP-1'],
            ['6 Weeks', 'IPV-1'],
            ['6 Weeks', 'Hib-1'],
            ['6 Weeks', 'Hepatitis B-2'],
            ['6 Weeks', 'Rotavirus-1'],
            ['6 Weeks', 'PCV-1'],
            ['10 Weeks', 'DTP/DTaP-2'],
            ['10 Weeks', 'IPV-2'],
            ['10 Weeks', 'Hib-2'],
            ['10 Weeks', 'Rotavirus-2'],
            ['10 Weeks', 'PCV-2'],
            ['14 Weeks', 'DTP/DTaP-3'],
            ['14 Weeks', 'IPV-3'],
            ['14 Weeks', 'Hib-3'],
            ['14 Weeks', 'Rotavirus-3'],
            ['14 Weeks', 'PCV-3'],
            ['6 Months', 'Influenza'],
            ['6 Months', 'Typhoid Conjugate (optional timing varies)'],
            ['9 Months', 'MMR-1'],
            ['9 Months', 'Measles-Rubella'],
            ['9 Months', 'OPV Booster'],
            ['9 Months', 'JE (endemic areas)'],
            ['9 Months', 'PCV Booster'],
            ['12 Months', 'Hepatitis A'],
            ['12 Months', 'Varicella-1'],
            ['15 Months', 'MMR-2'],
            ['15 Months', 'Varicella-2'],
            ['15 Months', 'PCV Booster (if applicable)'],
            ['16–18 Months', 'DTP Booster-1'],
            ['16–18 Months', 'IPV Booster'],
            ['16–18 Months', 'Hib Booster'],
            ['16–18 Months', 'OPV Booster'],
            ['18 Months', 'Hepatitis A-2'],
            ['2 Years', 'Typhoid Booster (depending on vaccine used)'],
            ['Every Year', 'Influenza'],
            ['4–6 Years', 'DTP Booster-2'],
            ['4–6 Years', 'OPV/IPV Booster'],
            ['4–6 Years', 'MMR Booster'],
            ['10 Years', 'Tdap/Td'],
            ['10 Years', 'HPV (girls and boys, depending on recommendations)'],
            ['15 Years', 'Td Booster'],
        ];

        $rows = [];
        $seen = [];
        foreach ($raw as [$age, $vaccine]) {
            $base = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $age . '_' . $vaccine) ?? '');
            $base = trim($base, '_');
            $key = $base;
            $n = 2;
            while (isset($seen[$key])) {
                $key = $base . '_' . $n++;
            }
            $seen[$key] = true;
            $rows[] = ['key' => $key, 'age' => $age, 'vaccine' => $vaccine];
        }

        return $rows;
    }

    /** @param array<string, mixed> $patient */
    public static function patientAgeYears(array $patient): ?int
    {
        $dob = trim((string) ($patient['dob'] ?? ''));
        if ($dob === '') {
            return null;
        }

        try {
            $birth = new \DateTimeImmutable($dob);
            $today = new \DateTimeImmutable('today');

            return (int) $birth->diff($today)->y;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isPediatricSpecialty(string $clinicSpecialty, ?string $doctorSpecialization = null): bool
    {
        if (strtolower(trim($clinicSpecialty)) === 'peds') {
            return true;
        }

        $spec = strtolower(trim((string) $doctorSpecialization));
        if ($spec === '') {
            return false;
        }

        return str_contains($spec, 'pediatric')
            || str_contains($spec, 'paediatric')
            || str_contains($spec, 'pediatrician')
            || str_contains($spec, 'paediatrician')
            || preg_match('/\bpeds?\b/', $spec) === 1;
    }

    public static function shouldShow(string $clinicSpecialty, ?string $doctorSpecialization, ?int $patientAgeYears): bool
    {
        if (!self::isPediatricSpecialty($clinicSpecialty, $doctorSpecialization)) {
            return false;
        }

        return $patientAgeYears !== null && $patientAgeYears < self::MAX_CHILD_AGE_YEARS;
    }

    /**
     * @param mixed $stored
     * @return list<string>
     */
    public static function normalizeSelected(mixed $stored): array
    {
        if (!is_array($stored)) {
            return [];
        }

        $valid = array_flip(array_column(self::schedule(), 'key'));
        $out = [];
        foreach ($stored as $key) {
            $k = (string) $key;
            if ($k !== '' && isset($valid[$k]) && !in_array($k, $out, true)) {
                $out[] = $k;
            }
        }

        return $out;
    }
}
