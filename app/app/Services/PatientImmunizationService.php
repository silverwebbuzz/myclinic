<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Support\PediatricVaccineSchedule;

final class PatientImmunizationService
{
    private const STATUSES = ['unknown', 'due', 'overdue', 'given', 'skipped', 'not_given'];

    /**
     * Build or refresh the immunization register when DOB is set (pediatric only).
     */
    public static function syncScheduleIfEligible(int $clinicId, array $patient, ?string $clinicSpecialty = null): void
    {
        $dob = trim((string) ($patient['dob'] ?? ''));
        $patientId = (int) ($patient['id'] ?? 0);
        if ($clinicId <= 0 || $patientId <= 0 || $dob === '') {
            return;
        }

        if ($clinicSpecialty === null) {
            $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
            $clinicSpecialty = (string) ($clinic['specialty'] ?? '');
        }

        $age = PediatricVaccineSchedule::patientAgeYears($patient);
        if (!PediatricVaccineSchedule::shouldManageImmunizations($clinicSpecialty, null, $age)) {
            return;
        }

        $doses = PediatricVaccineSchedule::dosesForPatient($dob);
        if ($doses === []) {
            return;
        }

        $existing = [];
        foreach (QueryBuilder::table('patient_immunizations')
            ->where('patient_id', '=', $patientId)
            ->get() as $row) {
            $existing[(string) $row['vaccine_key']] = $row;
        }

        foreach ($doses as $dose) {
            $key = (string) $dose['key'];
            $prev = $existing[$key] ?? null;
            if ($prev !== null) {
                $updates = [
                    'age_label'    => (string) $dose['age'],
                    'vaccine_name' => (string) $dose['vaccine'],
                    'due_date'     => (string) $dose['due_date'],
                ];
                if (empty($prev['given_date']) && !in_array((string) ($prev['status'] ?? ''), ['not_given', 'skipped'], true)) {
                    $updates['status'] = PediatricVaccineSchedule::initialStatusForDueDate(
                        (string) $dose['due_date'],
                        null,
                    );
                }
                QueryBuilder::table('patient_immunizations')
                    ->where('id', '=', (int) $prev['id'])
                    ->update($updates);
                continue;
            }

            $status = PediatricVaccineSchedule::initialStatusForDueDate((string) $dose['due_date'], null);
            QueryBuilder::table('patient_immunizations')->insert([
                'clinic_id'    => $clinicId,
                'patient_id'   => $patientId,
                'vaccine_key'  => $key,
                'age_label'    => (string) $dose['age'],
                'vaccine_name' => (string) $dose['vaccine'],
                'due_date'     => (string) $dose['due_date'],
                'status'       => $status,
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function forPatient(int $clinicId, int $patientId): array
    {
        $rows = QueryBuilder::table('patient_immunizations')
            ->where('clinic_id', '=', $clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('due_date', 'ASC')
            ->get();

        return array_map([self::class, 'hydrateRow'], $rows);
    }

    /**
     * @param list<array<string, mixed>> $updates
     * @return list<array<string, mixed>>
     */
    public static function saveBatch(int $clinicId, int $patientId, array $updates): array
    {
        foreach ($updates as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $row = QueryBuilder::table('patient_immunizations')
                ->where('id', '=', $id)
                ->where('clinic_id', '=', $clinicId)
                ->where('patient_id', '=', $patientId)
                ->first();
            if ($row === null) {
                continue;
            }

            $givenDate = !empty($item['given_date']) ? (string) $item['given_date'] : null;
            $status = (string) ($item['status'] ?? $row['status']);
            if (!in_array($status, self::STATUSES, true)) {
                $status = (string) $row['status'];
            }
            if ($givenDate !== null) {
                $status = 'given';
            } elseif ($status === 'not_given') {
                $givenDate = null;
            } elseif ($status === 'given') {
                $givenDate = (string) ($row['given_date'] ?? date('Y-m-d'));
            }

            $notes = array_key_exists('notes', $item)
                ? (trim((string) $item['notes']) ?: null)
                : ($row['notes'] ?? null);

            $visitLink = $status === 'given'
                ? ($row['last_visit_id'] ?? null)
                : null;

            QueryBuilder::table('patient_immunizations')
                ->where('id', '=', $id)
                ->update([
                    'given_date'    => $givenDate,
                    'status'        => $status,
                    'notes'         => $notes,
                    'last_visit_id' => $visitLink,
                ]);
        }

        return self::forPatient($clinicId, $patientId);
    }

    public static function markGiven(
        int $clinicId,
        int $patientId,
        int $immunizationId,
        ?int $visitId = null,
        ?string $givenDate = null,
        ?string $notes = null,
    ): ?array {
        $row = QueryBuilder::table('patient_immunizations')
            ->where('id', '=', $immunizationId)
            ->where('clinic_id', '=', $clinicId)
            ->where('patient_id', '=', $patientId)
            ->first();
        if ($row === null) {
            return null;
        }

        $date = $givenDate !== null && $givenDate !== ''
            ? $givenDate
            : date('Y-m-d');

        $noteValue = $notes !== null
            ? (trim($notes) !== '' ? trim($notes) : null)
            : ($row['notes'] ?? null);

        QueryBuilder::table('patient_immunizations')
            ->where('id', '=', $immunizationId)
            ->update([
                'given_date'    => $date,
                'status'        => 'given',
                'last_visit_id' => $visitId,
                'notes'         => $noteValue,
            ]);

        return self::hydrateRow(
            QueryBuilder::table('patient_immunizations')->where('id', '=', $immunizationId)->first() ?? [],
        );
    }

    /**
     * @return array{overdue: list<array>, due_today: list<array>, due_soon: list<array>, upcoming: list<array>}
     */
    public static function visitSummary(int $clinicId, int $patientId, int $dueSoonDays = 30): array
    {
        $all = self::forPatient($clinicId, $patientId);
        $today = new \DateTimeImmutable('today');
        $soonEnd = $today->modify('+' . $dueSoonDays . ' days');

        $dueToday = [];
        $dueSoon = [];
        $overdue = [];
        $upcoming = [];

        foreach ($all as $row) {
            if (in_array(($row['display_status'] ?? ''), ['given', 'skipped', 'not_given'], true)) {
                continue;
            }
            $due = new \DateTimeImmutable((string) $row['due_date']);
            if ($due < $today) {
                $overdue[] = $row;
            } elseif ($due == $today) {
                $dueToday[] = $row;
            } elseif ($due <= $soonEnd) {
                $dueSoon[] = $row;
            } else {
                $upcoming[] = $row;
            }
        }

        return [
            'overdue'   => $overdue,
            'due_today' => $dueToday,
            'due_soon'  => $dueSoon,
            'upcoming'  => $upcoming,
        ];
    }

    /**
     * Given doses grouped by the visit they were recorded in (patient chart when no visit).
     *
     * @return list<array{
     *   visit_id: ?int,
     *   visit_number: ?int,
     *   visited_at: ?string,
     *   label: string,
     *   is_current: bool,
     *   items: list<array<string, mixed>>
     * }>
     */
    public static function givenGroupedByVisit(int $clinicId, int $patientId, ?int $currentVisitId = null): array
    {
        $rows = [];
        foreach (QueryBuilder::table('patient_immunizations')
            ->where('clinic_id', '=', $clinicId)
            ->where('patient_id', '=', $patientId)
            ->where('status', '=', 'given')
            ->orderBy('given_date', 'DESC')
            ->orderBy('due_date', 'ASC')
            ->get() as $row) {
            if (empty($row['given_date'])) {
                continue;
            }
            $rows[] = self::hydrateRow($row);
        }

        if ($rows === []) {
            return [];
        }

        $visitIds = [];
        foreach ($rows as $row) {
            $vid = (int) ($row['last_visit_id'] ?? 0);
            if ($vid > 0) {
                $visitIds[$vid] = true;
            }
        }

        $visits = [];
        foreach (array_keys($visitIds) as $vid) {
            $visit = QueryBuilder::table('visits')
                ->forClinic($clinicId)
                ->where('id', '=', $vid)
                ->first();
            if ($visit !== null) {
                $visits[$vid] = $visit;
            }
        }

        $groups = [];
        foreach ($rows as $row) {
            $vid = (int) ($row['last_visit_id'] ?? 0);
            if ($vid > 0 && isset($visits[$vid])) {
                $key = 'visit:' . $vid;
            } else {
                $key = 'chart:' . (string) $row['given_date'];
                $vid = 0;
            }

            if (!isset($groups[$key])) {
                $visit = $vid > 0 ? $visits[$vid] : null;
                $groups[$key] = [
                    'visit_id'     => $vid > 0 ? $vid : null,
                    'visit_number' => $visit !== null ? (int) ($visit['visit_number'] ?? 0) : null,
                    'visited_at'   => $visit !== null ? (string) ($visit['visited_at'] ?? '') : null,
                    'given_date'   => (string) $row['given_date'],
                    'label'        => self::givenGroupLabel($vid, $visit, (string) $row['given_date']),
                    'is_current'   => $vid > 0 && $currentVisitId !== null && $vid === $currentVisitId,
                    'items'        => [],
                ];
            }
            $groups[$key]['items'][] = $row;
        }

        $out = array_values($groups);
        usort($out, static function (array $a, array $b): int {
            if ($a['is_current'] !== $b['is_current']) {
                return $a['is_current'] ? -1 : 1;
            }
            $aTs = strtotime((string) ($a['visited_at'] ?: $a['given_date']));
            $bTs = strtotime((string) ($b['visited_at'] ?: $b['given_date']));

            return $bTs <=> $aTs;
        });

        return $out;
    }

    /** @param array<string, mixed>|null $visit */
    private static function givenGroupLabel(int $visitId, ?array $visit, string $givenDate): string
    {
        if ($visitId > 0 && $visit !== null) {
            $num = (int) ($visit['visit_number'] ?? 0);
            $when = trim((string) ($visit['visited_at'] ?? ''));
            try {
                $when = $when !== '' ? (new \DateTimeImmutable($when))->format('d M Y') : '';
            } catch (\Throwable) {
                // keep raw
            }

            return $num > 0
                ? 'Visit #' . $num . ($when !== '' ? ' · ' . $when : '')
                : ($when !== '' ? 'Visit · ' . $when : 'Visit');
        }

        try {
            $when = (new \DateTimeImmutable($givenDate))->format('d M Y');
        } catch (\Throwable) {
            $when = $givenDate;
        }

        return 'Patient chart · ' . $when;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function hydrateRow(array $row): array
    {
        $given = !empty($row['given_date']) ? (string) $row['given_date'] : null;
        $row['display_status'] = PediatricVaccineSchedule::displayStatus(
            (string) ($row['status'] ?? 'due'),
            (string) ($row['due_date'] ?? ''),
            $given,
        );

        return $row;
    }
}
