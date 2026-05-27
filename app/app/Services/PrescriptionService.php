<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\SpecialtyAdapter;

final class PrescriptionService
{
    public const PER_PAGE = 20;

    /**
     * Returns prescriptions grouped by visit, paginated by visit count (not line count).
     *
     * @param array<string, mixed> $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function listForClinic(int $clinicId, array $filters = [], int $page = 1): array
    {
        if (!Database::ping()) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => self::PER_PAGE];
        }
        $page = max(1, $page);
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;
        $params = ['clinic_id' => $clinicId];
        $where = ['rx.clinic_id = :clinic_id'];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.uhid LIKE :q2 OR d.name LIKE :q3 OR r.name LIKE :q4)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        if (!empty($filters['mode'])) {
            $where[] = 'rx.mode = :mode';
            $params['mode'] = $filters['mode'];
        }
        if (!empty($filters['patient_id'])) {
            $where[] = 'rx.patient_id = :pid';
            $params['pid'] = (int) $filters['patient_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'v.visited_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'v.visited_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::connection();

        // Total = distinct visits that have at least one matching rx line.
        $countStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT rx.visit_id) AS c
             FROM prescriptions rx
             JOIN patients p ON p.id = rx.patient_id
             JOIN visits v ON v.id = rx.visit_id
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE {$whereSql}",
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        // Page-window of visits, with summary fields.
        $visitsStmt = $pdo->prepare(
            "SELECT v.id AS visit_id, v.visited_at, v.patient_id, v.doctor_id,
                    p.name AS patient_name, p.uhid, p.phone AS patient_phone,
                    u.name AS doctor_name,
                    COUNT(rx.id) AS line_count
             FROM prescriptions rx
             JOIN patients p ON p.id = rx.patient_id
             JOIN visits v ON v.id = rx.visit_id
             JOIN users u ON u.id = v.doctor_id
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE {$whereSql}
             GROUP BY v.id, v.visited_at, v.patient_id, v.doctor_id, p.name, p.uhid, p.phone, u.name
             ORDER BY v.visited_at DESC, v.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
        );
        $visitsStmt->execute($params);
        $visits = $visitsStmt->fetchAll() ?: [];

        if ($visits === []) {
            return ['rows' => [], 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        }

        $visitIds = array_map(static fn (array $r) => (int) $r['visit_id'], $visits);
        $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
        $linesStmt = $pdo->prepare(
            "SELECT rx.*, d.name AS drug_name, r.name AS remedy_name
             FROM prescriptions rx
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE rx.clinic_id = ? AND rx.visit_id IN ({$placeholders})
             ORDER BY rx.visit_id DESC, rx.sort_order ASC, rx.id ASC",
        );
        $linesStmt->execute(array_merge([$clinicId], $visitIds));
        $lines = $linesStmt->fetchAll() ?: [];

        $linesByVisit = [];
        foreach ($lines as $line) {
            $linesByVisit[(int) $line['visit_id']][] = $line;
        }

        foreach ($visits as &$v) {
            $v['lines'] = $linesByVisit[(int) $v['visit_id']] ?? [];
        }
        unset($v);

        return [
            'rows' => $visits,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function forVisit(int $clinicId, int $visitId): array
    {
        $rows = QueryBuilder::table('prescriptions')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->orderBy('sort_order', 'ASC')
            ->get();

        return array_map(static function (array $row) {
            if (!empty($row['drug_id'])) {
                $row['drug'] = DrugService::find((int) $row['drug_id']);
            }
            if (!empty($row['remedy_id'])) {
                $row['remedy'] = RemedyService::find((int) $row['remedy_id']);
            }

            return $row;
        }, $rows);
    }

    /** @param list<array<string, mixed>> $lines */
    public static function syncForVisit(int $clinicId, int $visitId, int $patientId, array $lines): void
    {
        QueryBuilder::table('prescriptions')
            ->forClinic($clinicId)
            ->where('visit_id', '=', $visitId)
            ->delete();

        $mode = SpecialtyAdapter::usesHomeopathicRx() ? 'homeopathic' : 'allopathic';
        $order = 0;
        $drugIdsUsed = [];
        $remedyIdsUsed = [];

        foreach ($lines as $line) {
            if (empty($line['drug_id']) && empty($line['remedy_id']) && empty($line['dosage'])) {
                continue;
            }

            $row = [
                'clinic_id' => $clinicId,
                'visit_id' => $visitId,
                'patient_id' => $patientId,
                'mode' => $line['mode'] ?? $mode,
                'drug_id' => !empty($line['drug_id']) ? (int) $line['drug_id'] : null,
                'remedy_id' => !empty($line['remedy_id']) ? (int) $line['remedy_id'] : null,
                'potency' => $line['potency'] ?? null,
                'form' => $line['form'] ?? null,
                'dosage' => $line['dosage'] ?? null,
                'frequency' => $line['frequency'] ?? 'BD',
                'duration_days' => !empty($line['duration_days']) ? (int) $line['duration_days'] : null,
                'instructions' => $line['instructions'] ?? null,
                'sort_order' => $order++,
            ];

            // Phase 2/3 columns — wrapped because they don't exist until
            // phase2_migrations.sql Block 2 has been run.
            $optional = [
                'frequency_preset' => $line['frequency_preset'] ?? null,
                'tapering_steps' => isset($line['tapering_steps']) && is_array($line['tapering_steps']) && $line['tapering_steps'] !== []
                                    ? json_encode($line['tapering_steps']) : null,
                'dose_unit' => $line['dose_unit'] ?? null,
                'dose_amount' => isset($line['dose_amount']) && $line['dose_amount'] !== '' ? (float) $line['dose_amount'] : null,
                'food_timing' => in_array($line['food_timing'] ?? 'any', ['before','after','with','empty','bedtime','any'], true)
                                  ? ($line['food_timing'] ?? 'any') : 'any',
                'mix_with' => $line['mix_with'] ?? null,
            ];

            try {
                QueryBuilder::table('prescriptions')->insert(array_merge($row, $optional));
            } catch (\Throwable $e) {
                // Pre-Phase-2 schema — retry with only the legacy columns.
                QueryBuilder::table('prescriptions')->insert($row);
            }

            if (!empty($line['drug_id'])) $drugIdsUsed[] = (int) $line['drug_id'];
            if (!empty($line['remedy_id'])) $remedyIdsUsed[] = (int) $line['remedy_id'];
        }

        // Phase 3: bump usage_count for ranked autocomplete. Wrapped so a
        // missing column never breaks the visit save.
        self::bumpUsageCounts($drugIdsUsed, $remedyIdsUsed);
    }

    /**
     * Bump drugs.usage_count and remedies.usage_count for every id used
     * in this save. Best-effort — silent if columns don't exist.
     *
     * @param list<int> $drugIds
     * @param list<int> $remedyIds
     */
    private static function bumpUsageCounts(array $drugIds, array $remedyIds): void
    {
        try {
            $pdo = \App\Core\Database::connection();
            if ($drugIds !== []) {
                $placeholders = implode(',', array_fill(0, count($drugIds), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE drugs SET usage_count = usage_count + 1 WHERE id IN ($placeholders)"
                );
                $stmt->execute($drugIds);
            }
            if ($remedyIds !== []) {
                $placeholders = implode(',', array_fill(0, count($remedyIds), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE remedies SET usage_count = usage_count + 1 WHERE id IN ($placeholders)"
                );
                $stmt->execute($remedyIds);
            }
        } catch (\Throwable $e) {
            // usage_count column doesn't exist yet — skip.
        }
    }

    /** @param list<array<string, mixed>> $lines @param list<string> $allergies */
    public static function validateLines(array $lines, array $allergies): array
    {
        $warnings = [];
        $selectedDrugs = [];
        foreach ($lines as $line) {
            if (!empty($line['drug_id'])) {
                $drug = DrugService::find((int) $line['drug_id']);
                if ($drug === null) {
                    continue;
                }
                $selectedDrugs[] = $drug;
                foreach (DrugService::allergyWarnings($drug, $allergies) as $w) {
                    $warnings[] = $w;
                }
            }
        }
        foreach ($lines as $line) {
            if (!empty($line['drug_id'])) {
                $drug = DrugService::find((int) $line['drug_id']);
                if ($drug === null) {
                    continue;
                }
                foreach (DrugService::interactionWarnings($drug, $selectedDrugs) as $w) {
                    $warnings[] = $w;
                }
            }
            if (!empty($line['remedy_id'])) {
                $remedy = RemedyService::find((int) $line['remedy_id']);
                if ($remedy !== null) {
                    foreach (RemedyService::dietaryWarnings($remedy) as $w) {
                        $warnings[] = $w;
                    }
                }
            }
        }

        return array_values(array_unique($warnings));
    }
}
