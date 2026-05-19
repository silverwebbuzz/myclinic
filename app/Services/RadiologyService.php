<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class RadiologyService
{
    public const PER_PAGE = 25;

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
        $where = ['r.clinic_id = :clinic_id'];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.uhid LIKE :q2 OR r.body_part LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        if (!empty($filters['modality'])) {
            $where[] = 'r.modality = :modality';
            $params['modality'] = $filters['modality'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'r.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'r.ordered_at >= :from';
            $params['from'] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $where[] = 'r.ordered_at <= :to';
            $params['to'] = $filters['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $pdo = Database::connection();

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM radiology_orders r
             JOIN patients p ON p.id = r.patient_id
             WHERE {$whereSql}",
        );
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        $stmt = $pdo->prepare(
            "SELECT r.*, p.name AS patient_name, p.uhid,
                    u.name AS ordered_by_name
             FROM radiology_orders r
             JOIN patients p ON p.id = r.patient_id
             LEFT JOIN users u ON u.id = r.ordered_by
             WHERE {$whereSql}
             ORDER BY r.ordered_at DESC
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

    public static function find(int $clinicId, int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.*, p.name AS patient_name, p.uhid
             FROM radiology_orders r
             JOIN patients p ON p.id = r.patient_id
             WHERE r.clinic_id = :clinic_id AND r.id = :id',
        );
        $stmt->execute(['clinic_id' => $clinicId, 'id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
