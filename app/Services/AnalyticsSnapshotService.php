<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class AnalyticsSnapshotService
{
    public static function buildAll(?string $date = null): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $date = $date ?? date('Y-m-d', strtotime('-1 day'));
        $rows = QueryBuilder::table('clinic_modules')
            ->where('module_id', '=', 'analytics')
            ->where('is_active', '=', 1)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            self::buildForClinic((int) $row['clinic_id'], $date);
            $count++;
        }

        return $count;
    }

    public static function buildForClinic(int $clinicId, string $date): void
    {
        if (!Database::ping()) {
            return;
        }

        $pdo = Database::connection();

        $rev = $pdo->prepare(
            "SELECT COALESCE(SUM(total), 0) FROM invoices
             WHERE clinic_id = ? AND status IN ('paid','partial')
             AND DATE(COALESCE(paid_at, created_at)) = ?",
        );
        $rev->execute([$clinicId, $date]);
        self::upsert($clinicId, $date, 'revenue', (float) $rev->fetchColumn());

        $exp = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE clinic_id = ? AND expense_date = ?');
        $exp->execute([$clinicId, $date]);
        self::upsert($clinicId, $date, 'expenses', (float) $exp->fetchColumn());

        $visits = $pdo->prepare('SELECT COUNT(*) FROM visits WHERE clinic_id = ? AND DATE(visited_at) = ?');
        $visits->execute([$clinicId, $date]);
        self::upsert($clinicId, $date, 'visits', (float) $visits->fetchColumn());

        $appts = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND DATE(scheduled_at) = ?');
        $appts->execute([$clinicId, $date]);
        self::upsert($clinicId, $date, 'appointments', (float) $appts->fetchColumn());

        $noShow = $pdo->prepare(
            "SELECT COUNT(*) FROM appointments WHERE clinic_id = ? AND status = 'no_show' AND DATE(scheduled_at) = ?",
        );
        $noShow->execute([$clinicId, $date]);
        self::upsert($clinicId, $date, 'no_shows', (float) $noShow->fetchColumn());

        $patients = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE clinic_id = ? AND DATE(created_at) = ?');
        $patients->execute([$clinicId, $date]);
        self::upsert($clinicId, $date, 'new_patients', (float) $patients->fetchColumn());
    }

    private static function upsert(int $clinicId, string $date, string $key, float $value): void
    {
        $existing = QueryBuilder::table('analytics_snapshots')
            ->forClinic($clinicId)
            ->where('date', '=', $date)
            ->where('metric_key', '=', $key)
            ->first();

        if ($existing !== null) {
            QueryBuilder::table('analytics_snapshots')
                ->where('id', '=', (int) $existing['id'])
                ->update(['metric_value' => $value]);
        } else {
            QueryBuilder::table('analytics_snapshots')->insert([
                'clinic_id' => $clinicId,
                'date' => $date,
                'metric_key' => $key,
                'metric_value' => $value,
            ]);
        }
    }
}
