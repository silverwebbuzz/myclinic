<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Gates\ModuleGate;

final class DashboardService
{
    private const CACHE_TTL = 300;

    /** @return array<string, int|float> */
    public static function stats(int $clinicId): array
    {
        $cacheKey = "dashboard:stats:{$clinicId}";
        $cached = RedisClient::get($cacheKey);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);

            return is_array($decoded) ? $decoded : self::computeStats($clinicId);
        }

        $stats = self::computeStats($clinicId);
        RedisClient::setex($cacheKey, self::CACHE_TTL, json_encode($stats));

        return $stats;
    }

    public static function invalidateStats(int $clinicId): void
    {
        RedisClient::del("dashboard:stats:{$clinicId}");
    }

    /** @return list<array<string, mixed>> */
    public static function todayQueue(int $clinicId): array
    {
        // Single source of truth for queue contents/ordering (tokens first,
        // then slot time) — the dashboard widget just hides no-shows.
        $queue = array_values(array_filter(
            AppointmentService::todayQueue($clinicId),
            static fn (array $row): bool => ($row['status'] ?? '') !== 'no_show',
        ));

        return array_slice($queue, 0, 50);
    }

    /** @return list<array<string, mixed>> */
    public static function lowStockItems(int $clinicId, int $limit = 8): array
    {
        if (!ModuleGate::check('pharmacy') || !Database::ping()) {
            return [];
        }

        $rows = QueryBuilder::table('pharmacy_inventory')
            ->forClinic($clinicId)
            ->get();

        $low = [];
        foreach ($rows as $row) {
            if ((int) $row['quantity'] <= (int) ($row['low_stock_threshold'] ?? 10)) {
                $drug = QueryBuilder::table('drugs')->where('id', '=', (int) $row['drug_id'])->first();
                $low[] = array_merge($row, ['drug_name' => $drug['name'] ?? 'Unknown']);
            }
        }

        usort($low, static fn ($a, $b) => (int) $a['quantity'] <=> (int) $b['quantity']);

        return array_slice($low, 0, $limit);
    }

    /** @return array<string, int|float> */
    private static function computeStats(int $clinicId): array
    {
        if (!Database::ping()) {
            return [
                'patients_today' => 0,
                'appointments_pending' => 0,
                'revenue_today' => 0.0,
                'follow_ups_due' => 0,
            ];
        }

        // Half-open day range so the (clinic_id, scheduled_at) indexes are
        // usable — DATE(col) = ? forces a full scan.
        $dayStart = date('Y-m-d') . ' 00:00:00';
        $dayEnd = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';
        $pdo = Database::connection();

        $patientsStmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT patient_id) AS c FROM visits
             WHERE clinic_id = ? AND visited_at >= ? AND visited_at < ?',
        );
        $patientsStmt->execute([$clinicId, $dayStart, $dayEnd]);
        $patientsToday = (int) ($patientsStmt->fetch()['c'] ?? 0);

        $pendingStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM appointments
             WHERE clinic_id = ? AND scheduled_at >= ? AND scheduled_at < ?
             AND status IN ('scheduled', 'confirmed', 'in_progress')",
        );
        $pendingStmt->execute([$clinicId, $dayStart, $dayEnd]);
        $pending = (int) ($pendingStmt->fetch()['c'] ?? 0);

        $revenueStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total), 0) AS s FROM invoices
             WHERE clinic_id = ? AND status IN ('paid', 'partial')
             AND COALESCE(paid_at, created_at) >= ? AND COALESCE(paid_at, created_at) < ?",
        );
        $revenueStmt->execute([$clinicId, $dayStart, $dayEnd]);
        $revenue = (float) ($revenueStmt->fetch()['s'] ?? 0);

        $followStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM appointments
             WHERE clinic_id = ? AND is_followup = 1
             AND status IN ('scheduled', 'confirmed')
             AND scheduled_at >= ?",
        );
        $followStmt->execute([$clinicId, $dayStart]);
        $followUps = (int) ($followStmt->fetch()['c'] ?? 0);

        return [
            'patients_today' => $patientsToday,
            'appointments_pending' => $pending,
            'revenue_today' => $revenue,
            'follow_ups_due' => $followUps,
        ];
    }
}
