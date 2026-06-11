<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
final class SlotService
{
    private const CACHE_TTL = 300;

    /**
     * @param bool $includeExtended Set true ONLY in staff/admin contexts (walk-in form).
     *                              Public booking must pass false (default).
     * @return list<array{time: string, datetime: string, available: bool, extended?: bool}>
     */
    public static function available(int $clinicId, int $doctorId, string $date, bool $includeExtended = false): array
    {
        $date = self::normalizeDate($date);

        // Today's slot list changes minute-by-minute (past slots drop off), so skip Redis cache for today.
        $tz = self::clinicTimezone($clinicId);
        try {
            $todayLocal = (new \DateTime('now', new \DateTimeZone($tz)))->format('Y-m-d');
        } catch (\Throwable $e) {
            $todayLocal = date('Y-m-d');
        }
        $isToday = $date === $todayLocal;

        if ($isToday) {
            return self::compute($clinicId, $doctorId, $date, $includeExtended);
        }

        $cacheKey = "slots:{$clinicId}:{$doctorId}:{$date}" . ($includeExtended ? ':ext' : '');
        $cached = RedisClient::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);

            return is_array($decoded) ? $decoded : [];
        }

        $slots = self::compute($clinicId, $doctorId, $date, $includeExtended);
        RedisClient::setex($cacheKey, self::CACHE_TTL, json_encode($slots));

        return $slots;
    }

    public static function invalidate(int $clinicId, int $doctorId, string $date): void
    {
        $date = self::normalizeDate($date);
        RedisClient::del("slots:{$clinicId}:{$doctorId}:{$date}");
        RedisClient::del("slots:{$clinicId}:{$doctorId}:{$date}:ext");
    }

    /**
     * Clear cached slot lists after working hours / schedule changes.
     *
     * @param list<int> $doctorIds
     */
    public static function invalidateForClinic(int $clinicId, array $doctorIds, ?int $daysAhead = null): void
    {
        $daysAhead ??= max(30, PublicBookingService::bookingWindowDays($clinicId));
        foreach ($doctorIds as $doctorId) {
            for ($i = 0; $i <= $daysAhead; $i++) {
                $date = date('Y-m-d', strtotime("+{$i} days"));
                self::invalidate($clinicId, (int) $doctorId, $date);
            }
        }
        error_log(sprintf(
            '[SlotService] invalidated slot cache clinic=%d doctors=%s days=%d',
            $clinicId,
            implode(',', array_map('strval', $doctorIds)),
            $daysAhead,
        ));
    }

    public static function invalidateForAppointment(int $clinicId, int $doctorId, string $scheduledAt): void
    {
        self::invalidate($clinicId, $doctorId, date('Y-m-d', strtotime($scheduledAt)));
    }

    /** @return list<array{time: string, datetime: string, available: bool, extended?: bool}> */
    private static function compute(int $clinicId, int $doctorId, string $date, bool $includeExtended = false): array
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

        // Clinic-local "now" for hiding past slots on today.
        $tz = self::clinicTimezone($clinicId);
        try {
            $nowLocal = new \DateTime('now', new \DateTimeZone($tz));
            $todayLocal = $nowLocal->format('Y-m-d');
            $nowTs = $nowLocal->getTimestamp();
        } catch (\Throwable $e) {
            $nowTs = time();
            $todayLocal = date('Y-m-d');
        }
        $isToday = $date === $todayLocal;

        $slots = [];
        foreach ($schedules as $sched) {
            $duration = (int) ($sched['slot_duration'] ?? 15);
            if (!in_array($duration, [15, 30], true)) {
                $duration = 15;
            }

            $start = self::timeOnDate($date, (string) ($sched['start_time'] ?? ''));
            $normalEnd = self::timeOnDate($date, (string) ($sched['end_time'] ?? ''));
            if ($start === null || $normalEnd === null || $normalEnd <= $start) {
                continue;
            }

            $hardEnd = $normalEnd;
            if ($includeExtended && !empty($sched['extended_end_time'])) {
                $extTs = self::timeOnDate($date, (string) $sched['extended_end_time']);
                if ($extTs !== null && $extTs > $normalEnd) {
                    $hardEnd = $extTs;
                }
            }

            for ($t = $start; $t + ($duration * 60) <= $hardEnd; $t += $duration * 60) {
                $time = date('H:i', $t);
                $datetime = date('Y-m-d H:i:s', $t);

                // Skip slots that have already started on today.
                if ($isToday && $t <= $nowTs) {
                    continue;
                }

                // Leave-blocked slots used to be silently dropped, which made
                // "doctor on leave" look identical to "no schedule". Keep them
                // in the list (never available) so UIs can render them as blocked.
                $blocked = self::isBlockedByLeave($time, $leaveBlocks);

                $slots[] = [
                    'time' => $time,
                    'datetime' => $datetime,
                    'available' => !$blocked && !isset($booked[$datetime]),
                    'blocked' => $blocked,
                    'extended' => $t >= $normalEnd,
                ];
            }
        }

        usort($slots, static fn (array $a, array $b) => strcmp($a['datetime'], $b['datetime']));

        self::logSlotGeneration($clinicId, $doctorId, $date, count($schedules), $slots);

        return $slots;
    }

    private static function timeOnDate(string $date, string $time): ?int
    {
        $normalized = DoctorScheduleService::normalizeTime($time);
        if ($normalized === null) {
            $normalized = DoctorScheduleService::normalizeTime(substr($time, 0, 5));
        }
        if ($normalized === null) {
            return null;
        }

        $ts = strtotime($date . ' ' . substr($normalized, 0, 5));

        return $ts !== false ? $ts : null;
    }

    /**
     * @param list<array<string, mixed>> $schedules
     * @param list<array{time: string, datetime: string, available: bool, extended?: bool}> $slots
     */
    private static function logSlotGeneration(int $clinicId, int $doctorId, string $date, int $scheduleCount, array $slots): void
    {
        $first = $slots[0]['time'] ?? null;
        $last = $slots !== [] ? $slots[array_key_last($slots)]['time'] : null;
        error_log(sprintf(
            '[SlotService] clinic=%d doctor=%d date=%s schedules=%d slots=%d range=%s-%s',
            $clinicId,
            $doctorId,
            $date,
            $scheduleCount,
            count($slots),
            (string) $first,
            (string) $last,
        ));
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

    private static function clinicTimezone(int $clinicId): string
    {
        $row = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        $tz = $row['timezone'] ?? 'Asia/Kolkata';
        return is_string($tz) && $tz !== '' ? $tz : 'Asia/Kolkata';
    }

    private static function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        if ($date !== '') {
            $ts = strtotime($date);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }

        return date('Y-m-d');
    }

    /** @return array<string, true> */
    private static function bookedTimes(int $clinicId, int $doctorId, string $date): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT scheduled_at FROM appointments
             WHERE clinic_id = ? AND doctor_id = ?
             AND scheduled_at >= ? AND scheduled_at < ?
             AND status NOT IN ('cancelled', 'no_show')",
        );
        $stmt->execute([
            $clinicId,
            $doctorId,
            $date . ' 00:00:00',
            date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00',
        ]);
        $booked = [];
        while ($row = $stmt->fetch()) {
            $booked[date('Y-m-d H:i:s', strtotime($row['scheduled_at']))] = true;
        }

        return $booked;
    }
}
