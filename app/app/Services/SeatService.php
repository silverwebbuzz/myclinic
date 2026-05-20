<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class SeatService
{
    /** @return array{used: int, limit: int, pending: int, available: int} */
    public static function getSeatUsage(int $clinicId): array
    {
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        $limit = (int) ($clinic['seat_limit'] ?? 2) + (int) ($clinic['extra_seats_purchased'] ?? 0);

        $activeStaff = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_active', '=', 1)
            ->where('role', '!=', 'patient')
            ->count();

        $pending = QueryBuilder::table('staff_invitations')
            ->forClinic($clinicId)
            ->where('status', '=', 'pending')
            ->count();

        $used = $activeStaff + $pending;

        return [
            'used' => $used,
            'limit' => $limit,
            'pending' => $pending,
            'available' => max(0, $limit - $used),
        ];
    }

    public static function canAddStaff(int $clinicId): bool
    {
        $usage = self::getSeatUsage($clinicId);

        return $usage['used'] < $usage['limit'];
    }

    public static function addExtraSeat(int $clinicId, int $count = 1): void
    {
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($clinic === null) {
            return;
        }

        QueryBuilder::table('tenants')
            ->where('id', '=', $clinicId)
            ->update([
                'extra_seats_purchased' => (int) ($clinic['extra_seats_purchased'] ?? 0) + $count,
            ]);
    }
}
