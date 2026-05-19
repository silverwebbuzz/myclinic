<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
final class SlotService
{
    private const CACHE_TTL = 300;

    /** @return list<array{time: string, datetime: string, available: bool}> */
    public static function available(int $clinicId, int $doctorId, string $date): array
    {
        $cacheKey = "slots:{$clinicId}:{$doctorId}:{$date}";
        $cached = RedisClient::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);

            return is_array($decoded) ? $decoded : [];
        }

        $slots = self::compute($clinicId, $doctorId, $date);
        RedisClient::setex($cacheKey, self::CACHE_TTL, json_encode($slots));

        return $slots;
    }

    public static function invalidate(int $clinicId, int $doctorId, string $date): void
    {
        RedisClient::del("slots:{$clinicId}:{$doctorId}:{$date}");
    }

    public static function invalidateForAppointment(int $clinicId, int $doctorId, string $scheduledAt): void
    {
        self::invalidate($clinicId, $doctorId, date('Y-m-d', strtotime($scheduledAt)));
    }

    /** @return list<array{time: string, datetime: string, available: bool}> */
    private static function compute(int $clinicId, int $doctorId, string $date): array
    {
        if (!Database::ping()) {
            return [];
        }

        $dayOfWeek = (int) date('w', strtotime($date));
        $schedules = QueryBuilder::table('doctor_schedules')
            ->forClinic($clinicId)
            ->where('doctor_id', '=', $doctorId)
            ->where('day_of_week', '=', $dayOfWeek)
            ->where('is_active', '=', 1)
            ->get();

        if ($schedules === []) {
            return [];
        }

        $leaves = QueryBuilder::table('doctor_leaves')
            ->forClinic($clinicId)
            ->where('doctor_id', '=', $doctorId)
            ->where('leave_date', '=', $date)
            ->get();

        $leaveBlocks = self::leaveBlocks($leaves);

        $booked = self::bookedTimes($clinicId, $doctorId, $date);

        $slots = [];
        foreach ($schedules as $sched) {
            $duration = (int) ($sched['slot_duration'] ?? 15);
            $start = strtotime($date . ' ' . substr((string) $sched['start_time'], 0, 5));
            $end = strtotime($date . ' ' . substr((string) $sched['end_time'], 0, 5));

            for ($t = $start; $t + ($duration * 60) <= $end; $t += $duration * 60) {
                $time = date('H:i', $t);
                $datetime = date('Y-m-d H:i:s', $t);

                if (self::isBlockedByLeave($time, $leaveBlocks)) {
                    continue;
                }

                $available = !isset($booked[$datetime]);
                $slots[] = [
                    'time' => $time,
                    'datetime' => $datetime,
                    'available' => $available,
                ];
            }
        }

        return $slots;
    }

    /**
     * @param list<array<string, mixed>> $leaves
     * @return list<string>
     */
    private static function leaveBlocks(array $leaves): array
    {
        $blocks = [];
        foreach ($leaves as $leave) {
            $blocks[] = $leave['session'] ?? 'full';
        }

        return $blocks;
    }

    /** @param list<string> $blocks */
    private static function isBlockedByLeave(string $time, array $blocks): bool
    {
        foreach ($blocks as $session) {
            if ($session === 'full') {
                return true;
            }
            if ($session === 'morning' && $time < '13:00') {
                return true;
            }
            if ($session === 'evening' && $time >= '13:00') {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    private static function bookedTimes(int $clinicId, int $doctorId, string $date): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT scheduled_at FROM appointments
             WHERE clinic_id = ? AND doctor_id = ?
             AND DATE(scheduled_at) = ?
             AND status NOT IN ('cancelled', 'no_show')",
        );
        $stmt->execute([$clinicId, $doctorId, $date]);
        $booked = [];
        while ($row = $stmt->fetch()) {
            $booked[date('Y-m-d H:i:s', strtotime($row['scheduled_at']))] = true;
        }

        return $booked;
    }
}
