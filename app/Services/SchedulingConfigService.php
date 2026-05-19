<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class SchedulingConfigService
{
    /** @return list<array<string, mixed>> */
    public static function schedulesForDoctor(int $clinicId, int $doctorId): array
    {
        return QueryBuilder::table('doctor_schedules')
            ->forClinic($clinicId)
            ->where('doctor_id', '=', $doctorId)
            ->orderBy('day_of_week', 'ASC')
            ->get();
    }

    public static function saveSchedule(int $clinicId, int $doctorId, array $data): int
    {
        return QueryBuilder::table('doctor_schedules')->insert([
            'clinic_id' => $clinicId,
            'doctor_id' => $doctorId,
            'day_of_week' => (int) ($data['day_of_week'] ?? 1),
            'start_time' => self::toTime((string) ($data['start_time'] ?? '09:00')),
            'end_time' => self::toTime((string) ($data['end_time'] ?? '18:00')),
            'slot_duration' => (int) ($data['slot_duration'] ?? 15),
            'max_patients' => (int) ($data['max_patients'] ?? 30),
            'session_name' => $data['session_name'] ?? null,
            'is_active' => 1,
        ]);
    }

    public static function googleCalendarStub(): array
    {
        return [
            'connected' => false,
            'message' => 'Google Calendar sync — OAuth integration TODO (Sprint 9 stub).',
        ];
    }

    private static function toTime(string $t): string
    {
        return strlen($t) === 5 ? $t . ':00' : $t;
    }
}
