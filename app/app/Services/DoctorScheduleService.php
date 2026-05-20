<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class DoctorScheduleService
{
    /**
     * @param array<string, mixed> $workingHours JSON structure from onboarding
     * @param list<int> $doctorIds
     */
    public static function syncFromWorkingHours(int $clinicId, array $workingHours, array $doctorIds, int $slotDuration = 15): void
    {
        $dayMap = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

        foreach ($doctorIds as $doctorId) {
            foreach ($workingHours as $dayKey => $config) {
                if (!is_array($config) || empty($config['enabled'])) {
                    continue;
                }

                $dayOfWeek = $dayMap[strtolower($dayKey)] ?? null;
                if ($dayOfWeek === null) {
                    continue;
                }

                $sessions = $config['sessions'] ?? [[
                    'start' => $config['open'] ?? '09:00',
                    'end' => $config['close'] ?? '18:00',
                ]];

                foreach ($sessions as $session) {
                    $start = $session['start'] ?? null;
                    $end = $session['end'] ?? null;
                    if ($start === null || $end === null) {
                        continue;
                    }
                    $extendedEnd = !empty($session['extended_end'])
                        ? (string) $session['extended_end'] . ':00'
                        : null;

                    $existing = QueryBuilder::table('doctor_schedules')
                        ->forClinic($clinicId)
                        ->where('doctor_id', '=', $doctorId)
                        ->where('day_of_week', '=', $dayOfWeek)
                        ->where('start_time', '=', $start . ':00')
                        ->first();

                    if ($existing !== null) {
                        QueryBuilder::table('doctor_schedules')
                            ->forClinic($clinicId)
                            ->where('id', '=', $existing['id'])
                            ->update([
                                'end_time' => $end . ':00',
                                'extended_end_time' => $extendedEnd,
                                'slot_duration' => $slotDuration,
                                'is_active' => 1,
                            ]);
                    } else {
                        QueryBuilder::table('doctor_schedules')->insert([
                            'clinic_id' => $clinicId,
                            'doctor_id' => $doctorId,
                            'day_of_week' => $dayOfWeek,
                            'start_time' => $start . ':00',
                            'end_time' => $end . ':00',
                            'extended_end_time' => $extendedEnd,
                            'slot_duration' => $slotDuration,
                            'max_patients' => 30,
                            'is_active' => 1,
                        ]);
                    }
                }
            }
        }
    }

    /** @return list<int> */
    public static function doctorIdsForClinic(int $clinicId): array
    {
        $rows = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '=', 'doctor')
            ->where('is_active', '=', 1)
            ->get();

        if ($rows !== []) {
            return array_map(static fn (array $r) => (int) $r['id'], $rows);
        }

        $owner = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_owner', '=', 1)
            ->first();

        return $owner !== null ? [(int) $owner['id']] : [];
    }
}
