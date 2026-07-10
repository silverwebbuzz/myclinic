<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class DoctorScheduleService
{
    /** Allowed appointment slot lengths (minutes) — shared with onboarding + settings. */
    public const ALLOWED_SLOT_DURATIONS = [10, 15, 20, 30, 45, 60];

    public static function normalizeSlotDuration(int $slotDuration): int
    {
        return in_array($slotDuration, self::ALLOWED_SLOT_DURATIONS, true) ? $slotDuration : 15;
    }

    /**
     * Rebuild doctor_schedules from clinic working_hours JSON for every bookable doctor.
     * Replaces all prior rows for each doctor so changed start times (e.g. 09:30) are not orphaned.
     *
     * @param array<string, mixed> $workingHours JSON structure from settings / onboarding
     * @param list<int> $doctorIds
     */
    public static function syncFromWorkingHours(int $clinicId, array $workingHours, array $doctorIds, int $slotDuration = 15): void
    {
        if ($doctorIds === []) {
            error_log("[DoctorScheduleService] sync skipped: no doctors for clinic {$clinicId}");

            return;
        }

        $slotDuration = self::normalizeSlotDuration($slotDuration);
        $dayMap = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
        $rowsInserted = 0;

        foreach ($doctorIds as $doctorId) {
            QueryBuilder::table('doctor_schedules')
                ->forClinic($clinicId)
                ->where('doctor_id', '=', $doctorId)
                ->delete();

            foreach ($workingHours as $dayKey => $config) {
                if (!is_array($config) || empty($config['enabled'])) {
                    continue;
                }

                $dayOfWeek = $dayMap[strtolower((string) $dayKey)] ?? null;
                if ($dayOfWeek === null) {
                    continue;
                }

                $sessions = $config['sessions'] ?? [];
                if (!is_array($sessions) || $sessions === []) {
                    continue;
                }

                foreach ($sessions as $idx => $session) {
                    if (!is_array($session)) {
                        continue;
                    }
                    $start = self::normalizeTime((string) ($session['start'] ?? ''));
                    $end = self::normalizeTime((string) ($session['end'] ?? ''));
                    if ($start === null || $end === null) {
                        continue;
                    }

                    $extendedEnd = !empty($session['extended_end'])
                        ? self::normalizeTime((string) $session['extended_end'])
                        : null;

                    QueryBuilder::table('doctor_schedules')->insert([
                        'clinic_id' => $clinicId,
                        'doctor_id' => $doctorId,
                        'day_of_week' => $dayOfWeek,
                        'start_time' => $start,
                        'end_time' => $end,
                        'extended_end_time' => $extendedEnd,
                        'slot_duration' => $slotDuration,
                        'max_patients' => 30,
                        'session_name' => $idx === 0 ? 'morning' : ($idx === 1 ? 'evening' : 'session_' . ($idx + 1)),
                        'is_active' => 1,
                    ]);
                    $rowsInserted++;
                }
            }
        }

        error_log(sprintf(
            '[DoctorScheduleService] clinic=%d doctors=%s slot_duration=%d schedule_rows=%d',
            $clinicId,
            implode(',', array_map('strval', $doctorIds)),
            $slotDuration,
            $rowsInserted,
        ));

        SlotService::invalidateForClinic($clinicId, $doctorIds);
    }

    /** Apply working hours to a single doctor (does not touch other doctors). */
    public static function syncForDoctor(int $clinicId, int $doctorId, array $workingHours, int $slotDuration = 15): void
    {
        self::syncFromWorkingHours($clinicId, $workingHours, [$doctorId], $slotDuration);
    }

    /** @return array<string, mixed> */
    public static function workingHoursForDoctor(int $clinicId, int $doctorId): array
    {
        $rows = QueryBuilder::table('doctor_schedules')
            ->forClinic($clinicId)
            ->where('doctor_id', '=', $doctorId)
            ->where('is_active', '=', 1)
            ->orderBy('day_of_week', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->get();

        if ($rows === []) {
            $config = OnboardingService::specialtyConfig($clinicId) ?? [];
            $wh = $config['working_hours'] ?? null;
            if (is_string($wh)) {
                $wh = json_decode($wh, true);
            }

            return is_array($wh) ? $wh : OnboardingService::defaultWorkingHours();
        }

        $dayKeys = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat'];
        $hours = OnboardingService::defaultWorkingHours();
        foreach ($hours as $key => $cfg) {
            $hours[$key] = ['enabled' => false, 'sessions' => []];
        }

        foreach ($rows as $row) {
            $dow = (int) ($row['day_of_week'] ?? -1);
            $key = $dayKeys[$dow] ?? null;
            if ($key === null) {
                continue;
            }
            $hours[$key]['enabled'] = true;
            $session = [
                'start' => substr((string) ($row['start_time'] ?? '09:00'), 0, 5),
                'end' => substr((string) ($row['end_time'] ?? '17:00'), 0, 5),
            ];
            if (!empty($row['extended_end_time'])) {
                $session['extended_end'] = substr((string) $row['extended_end_time'], 0, 5);
            }
            $hours[$key]['sessions'][] = $session;
        }

        return $hours;
    }

    public static function slotDurationForClinic(int $clinicId): int
    {
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $slot = (int) ($config['slot_duration_min'] ?? 15);

        return self::normalizeSlotDuration($slot);
    }

    /** Normalize HH:MM or HH:MM:SS to HH:MM:SS for MySQL TIME columns. */
    public static function normalizeTime(string $time): ?string
    {
        $time = trim($time);
        if ($time === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $m) !== 1) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    /** @return list<int> */
    public static function doctorIdsForClinic(int $clinicId): array
    {
        $doctors = AppointmentService::doctorsForClinic($clinicId);

        return $doctors !== []
            ? array_map(static fn (array $r) => (int) $r['id'], $doctors)
            : [];
    }
}
