<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class StaffAttendanceService
{
    public static function clockIn(int $clinicId, int $userId): array
    {
        $today = date('Y-m-d');
        $row = self::forDate($clinicId, $userId, $today);
        if ($row !== null && !empty($row['clock_in'])) {
            return $row;
        }

        if ($row !== null) {
            QueryBuilder::table('staff_attendance')
                ->where('id', '=', (int) $row['id'])
                ->update(['clock_in' => date('H:i:s'), 'status' => 'present']);
        } else {
            QueryBuilder::table('staff_attendance')->insert([
                'clinic_id' => $clinicId,
                'user_id' => $userId,
                'date' => $today,
                'clock_in' => date('H:i:s'),
                'status' => 'present',
            ]);
        }

        return self::forDate($clinicId, $userId, $today) ?? [];
    }

    public static function clockOut(int $clinicId, int $userId): array
    {
        $today = date('Y-m-d');
        $row = self::forDate($clinicId, $userId, $today);
        if ($row === null) {
            throw new \RuntimeException('No clock-in found for today');
        }

        QueryBuilder::table('staff_attendance')
            ->where('id', '=', (int) $row['id'])
            ->update(['clock_out' => date('H:i:s')]);

        return self::forDate($clinicId, $userId, $today) ?? [];
    }

    public static function forDate(int $clinicId, int $userId, string $date): ?array
    {
        return QueryBuilder::table('staff_attendance')
            ->forClinic($clinicId)
            ->where('user_id', '=', $userId)
            ->where('date', '=', $date)
            ->first() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function monthlyReport(int $clinicId, int $year, int $month): array
    {
        if (!Database::ping()) {
            return [];
        }

        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));
        $stmt = Database::connection()->prepare(
            'SELECT sa.*, u.name AS staff_name
             FROM staff_attendance sa
             INNER JOIN users u ON u.id = sa.user_id
             WHERE sa.clinic_id = ? AND sa.date BETWEEN ? AND ?
             ORDER BY sa.date DESC, u.name',
        );
        $stmt->execute([$clinicId, $from, $to]);

        return $stmt->fetchAll() ?: [];
    }

    public static function todayForUser(?int $userId = null): ?array
    {
        $clinicId = RequestContext::clinicId();
        $user = RequestContext::user();
        if ($clinicId === null || $user === null) {
            return null;
        }
        $uid = $userId ?? (int) $user['id'];

        return self::forDate((int) $clinicId, $uid, date('Y-m-d'));
    }
}
