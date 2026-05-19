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
        if (!Database::ping()) {
            return [];
        }

        $today = date('Y-m-d');
        $pdo = Database::connection();
        $sql = <<<SQL
            SELECT a.id, a.scheduled_at, a.status, a.token_number, a.type,
                   p.name AS patient_name, p.uhid, u.name AS doctor_name
            FROM appointments a
            INNER JOIN patients p ON p.id = a.patient_id
            INNER JOIN users u ON u.id = a.doctor_id
            WHERE a.clinic_id = :clinic_id
              AND DATE(a.scheduled_at) = :today
              AND a.status NOT IN ('cancelled', 'no_show')
            ORDER BY a.scheduled_at ASC
            LIMIT 50
        SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['clinic_id' => $clinicId, 'today' => $today]);

        return $stmt->fetchAll() ?: [];
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

        $today = date('Y-m-d');
        $pdo = Database::connection();

        $patientsStmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT patient_id) AS c FROM visits
             WHERE clinic_id = ? AND DATE(visited_at) = ?',
        );
        $patientsStmt->execute([$clinicId, $today]);
        $patientsToday = (int) ($patientsStmt->fetch()['c'] ?? 0);

        $pendingStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM appointments
             WHERE clinic_id = ? AND DATE(scheduled_at) = ?
             AND status IN ('scheduled', 'confirmed', 'in_progress')",
        );
        $pendingStmt->execute([$clinicId, $today]);
        $pending = (int) ($pendingStmt->fetch()['c'] ?? 0);

        $revenueStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total), 0) AS s FROM invoices
             WHERE clinic_id = ? AND status IN ('paid', 'partial')
             AND DATE(COALESCE(paid_at, created_at)) = ?",
        );
        $revenueStmt->execute([$clinicId, $today]);
        $revenue = (float) ($revenueStmt->fetch()['s'] ?? 0);

        $followStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM appointments
             WHERE clinic_id = ? AND is_followup = 1
             AND status IN ('scheduled', 'confirmed')
             AND DATE(scheduled_at) >= ?",
        );
        $followStmt->execute([$clinicId, $today]);
        $followUps = (int) ($followStmt->fetch()['c'] ?? 0);

        return [
            'patients_today' => $patientsToday,
            'appointments_pending' => $pending,
            'revenue_today' => $revenue,
            'follow_ups_due' => $followUps,
        ];
    }
}
