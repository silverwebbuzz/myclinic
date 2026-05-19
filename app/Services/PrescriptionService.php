<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Support\SpecialtyAdapter;

final class PrescriptionService
{
    public const PER_PAGE = 30;

    /**
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

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM prescriptions rx
             JOIN patients p ON p.id = rx.patient_id
             JOIN visits v ON v.id = rx.visit_id
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE {$whereSql}",
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT rx.*, p.name AS patient_name, p.uhid, v.visited_at,
                    d.name AS drug_name, r.name AS remedy_name
             FROM prescriptions rx
             JOIN patients p ON p.id = rx.patient_id
             JOIN visits v ON v.id = rx.visit_id
             LEFT JOIN drugs d ON d.id = rx.drug_id
             LEFT JOIN remedies r ON r.id = rx.remedy_id
             WHERE {$whereSql}
             ORDER BY v.visited_at DESC, rx.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
        );
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll() ?: [],
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
        foreach ($lines as $line) {
            if (empty($line['drug_id']) && empty($line['remedy_id']) && empty($line['dosage'])) {
                continue;
            }
            QueryBuilder::table('prescriptions')->insert([
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
            ]);
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
