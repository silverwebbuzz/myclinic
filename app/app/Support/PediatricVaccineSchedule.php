<?php

declare(strict_types=1);

namespace App\Support;

/**
 * IAP-style pediatric immunization schedule — definitions and due-date math.
 * Patient rows live in patient_immunizations (see PatientImmunizationService).
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

    /**
     * Expand schedule into concrete doses for a patient (incl. yearly influenza).
     *
     * @return list<array{key: string, age: string, vaccine: string, due_date: string}>
     */
    public static function dosesForPatient(string $dob): array
    {
        $birth = self::parseDate($dob);
        if ($birth === null) {
            return [];
        }

        $out = [];
        foreach (self::schedule() as $row) {
            if (strcasecmp($row['age'], 'Every Year') === 0 && strcasecmp($row['vaccine'], 'Influenza') === 0) {
                for ($y = 1; $y < self::MAX_CHILD_AGE_YEARS; $y++) {
                    $due = $birth->modify('+' . $y . ' years');
                    if ($due > $birth->modify('+' . self::MAX_CHILD_AGE_YEARS . ' years')) {
                        break;
                    }
                    $out[] = [
                        'key'       => $row['key'] . '_y' . $y,
                        'age'       => 'Year ' . $y,
                        'vaccine'   => $row['vaccine'],
                        'due_date'  => $due->format('Y-m-d'),
                    ];
                }
                continue;
            }

            $due = self::dueDateFromBirth($birth, $row['age']);
            if ($due === null) {
                continue;
            }
            if ($due > $birth->modify('+' . self::MAX_CHILD_AGE_YEARS . ' years')) {
                continue;
            }

            $out[] = [
                'key'      => $row['key'],
                'age'      => $row['age'],
                'vaccine'  => $row['vaccine'],
                'due_date' => $due->format('Y-m-d'),
            ];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['due_date'], $b['due_date']));

        return $out;
    }

    public static function dueDateFromBirth(\DateTimeImmutable $birth, string $ageLabel): ?\DateTimeImmutable
    {
        $age = trim($ageLabel);
        if ($age === '' || strcasecmp($age, 'Every Year') === 0) {
            return null;
        }
        if (strcasecmp($age, 'Birth') === 0) {
            return $birth;
        }
        if (preg_match('/^(\d+)\s*Weeks?$/i', $age, $m)) {
            return $birth->modify('+' . (int) $m[1] . ' weeks');
        }
        if (preg_match('/^(\d+)\s*Months?$/i', $age, $m)) {
            return $birth->modify('+' . (int) $m[1] . ' months');
        }
        if (preg_match('/^(\d+)\s*[–-]\s*(\d+)\s*Months?$/i', $age, $m)) {
            $mid = (int) floor(((int) $m[1] + (int) $m[2]) / 2);

            return $birth->modify('+' . $mid . ' months');
        }
        if (preg_match('/^(\d+)\s*Years?$/i', $age, $m)) {
            return $birth->modify('+' . (int) $m[1] . ' years');
        }
        if (preg_match('/^(\d+)\s*[–-]\s*(\d+)\s*Years?$/i', $age, $m)) {
            return $birth->modify('+' . (int) $m[1] . ' years');
        }

        return null;
    }

    /** @param array<string, mixed> $patient */
    public static function patientAgeYears(array $patient): ?int
    {
        $dob = trim((string) ($patient['dob'] ?? ''));
        if ($dob === '') {
            return null;
        }

        $birth = self::parseDate($dob);

        return $birth?->diff(new \DateTimeImmutable('today'))->y;
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

    public static function shouldManageImmunizations(
        string $clinicSpecialty,
        ?string $doctorSpecialization,
        ?int $patientAgeYears,
    ): bool {
        if (!self::isPediatricSpecialty($clinicSpecialty, $doctorSpecialization)) {
            return false;
        }

        return $patientAgeYears !== null && $patientAgeYears < self::MAX_CHILD_AGE_YEARS;
    }

    public static function initialStatusForDueDate(string $dueDate, ?string $givenDate): string
    {
        if ($givenDate !== null && $givenDate !== '') {
            return 'given';
        }

        $due = self::parseDate($dueDate);
        $today = new \DateTimeImmutable('today');
        if ($due === null) {
            return 'due';
        }
        if ($due < $today) {
            return 'unknown';
        }

        return 'due';
    }

    public static function displayStatus(string $storedStatus, string $dueDate, ?string $givenDate): string
    {
        if ($givenDate !== null && $givenDate !== '') {
            return 'given';
        }
        if ($storedStatus === 'skipped') {
            return 'skipped';
        }
        if ($storedStatus === 'not_given') {
            return 'not_given';
        }

        $due = self::parseDate($dueDate);
        $today = new \DateTimeImmutable('today');
        if ($due !== null && $due < $today) {
            return in_array($storedStatus, ['unknown', 'overdue'], true) ? $storedStatus : 'overdue';
        }

        return $storedStatus === 'unknown' ? 'unknown' : 'due';
    }

    private static function parseDate(string $date): ?\DateTimeImmutable
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return null;
        }
    }
}
