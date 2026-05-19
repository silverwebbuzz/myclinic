<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Gates\ModuleGate;

final class PatientService
{
    public const PER_PAGE = 20;

    public static function find(int $clinicId, int $patientId): ?array
    {
        return QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('id', '=', $patientId)
            ->where('is_active', '=', 1)
            ->first();
    }

    public static function findByQrToken(string $token): ?array
    {
        return QueryBuilder::table('patients')
            ->where('qr_token', '=', $token)
            ->where('is_active', '=', 1)
            ->first();
    }

    public static function findByPhone(int $clinicId, string $phone): ?array
    {
        $normalized = self::normalizePhone($phone);
        if ($normalized === '') {
            return null;
        }

        return QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('phone', '=', $normalized)
            ->where('is_active', '=', 1)
            ->first();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public static function search(int $clinicId, array $filters, int $page = 1, string $sort = 'name', string $dir = 'asc'): array
    {
        if (!Database::ping()) {
            return ['rows' => [], 'total' => 0, 'page' => $page, 'per_page' => self::PER_PAGE];
        }

        $page = max(1, $page);
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;
        $params = ['clinic_id' => $clinicId];
        $where = ['p.clinic_id = :clinic_id', 'p.is_active = 1'];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.name LIKE :q_name OR p.phone LIKE :q_phone OR p.uhid LIKE :q_uhid)';
            $params['q_name'] = '%' . $q . '%';
            $params['q_phone'] = '%' . $q . '%';
            $params['q_uhid'] = '%' . $q . '%';
        }
        if (!empty($filters['gender'])) {
            $where[] = 'p.gender = :gender';
            $params['gender'] = $filters['gender'];
        }
        if (!empty($filters['blood_group'])) {
            $where[] = 'p.blood_group = :blood';
            $params['blood'] = $filters['blood_group'];
        }
        if (!empty($filters['veg_type'])) {
            $where[] = 'p.veg_type = :veg';
            $params['veg'] = $filters['veg_type'];
        }
        if (!empty($filters['source'])) {
            $where[] = 'p.source = :source';
            $params['source'] = $filters['source'];
        }
        if (!empty($filters['referred_by'])) {
            $where[] = 'p.referred_by LIKE :ref';
            $params['ref'] = '%' . $filters['referred_by'] . '%';
        }
        if (!empty($filters['last_visit'])) {
            match ($filters['last_visit']) {
                '7d' => $where[] = 'lv.last_visit >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
                '30d' => $where[] = 'lv.last_visit >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
                '90d' => $where[] = 'lv.last_visit >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)',
                'never' => $where[] = 'lv.last_visit IS NULL',
                default => null,
            };
        }

        $orderCol = match ($sort) {
            'created_at' => 'p.created_at',
            'uhid' => 'p.uhid',
            'last_visit' => 'lv.last_visit',
            default => 'p.name',
        };
        $orderDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $whereSql = implode(' AND ', $where);
        $pdo = Database::connection();
        $params['clinic_id_lv'] = $clinicId;

        $countSql = "SELECT COUNT(*) AS c FROM patients p
            LEFT JOIN (SELECT patient_id, MAX(visited_at) AS last_visit FROM visits WHERE clinic_id = :clinic_id_lv GROUP BY patient_id) lv
            ON lv.patient_id = p.id
            WHERE {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        $sql = "SELECT p.*, lv.last_visit FROM patients p
            LEFT JOIN (SELECT patient_id, MAX(visited_at) AS last_visit FROM visits WHERE clinic_id = :clinic_id_lv GROUP BY patient_id) lv
            ON lv.patient_id = p.id
            WHERE {$whereSql}
            ORDER BY {$orderCol} {$orderDir}
            LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll() ?: [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function create(int $clinicId, array $payload, ?array $photoFile = null): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $config = OnboardingService::specialtyConfig($clinicId) ?? [];
            $prefix = strtoupper($config['uhid_prefix'] ?? 'MC');
            [$uhid, $seq] = self::allocateUhid($clinicId, $prefix, $pdo);

            $qrToken = bin2hex(random_bytes(32));
            $data = self::mapPayload($payload);
            $patientId = QueryBuilder::table('patients')->insert(array_merge($data, [
                'clinic_id' => $clinicId,
                'uhid' => $uhid,
                'uhid_seq' => $seq,
                'qr_token' => $qrToken,
            ]));

            if ($photoFile !== null) {
                $path = StorageService::storePatientPhoto($clinicId, $patientId, $photoFile);
                if ($path !== null) {
                    QueryBuilder::table('patients')->where('id', '=', $patientId)->update(['photo_path' => $path]);
                }
            }

            $patient = QueryBuilder::table('patients')->where('id', '=', $patientId)->first();
            $pdo->commit();

            $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
            if ($patient !== null && $clinic !== null) {
                QrCardService::generateForPatient($patient, $clinic);
            }

            ModuleGate::invalidateCache($clinicId);
            DashboardService::invalidateStats($clinicId);

            return $patient ?? [];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $payload */
    public static function update(int $clinicId, int $patientId, array $payload, ?array $photoFile = null): array
    {
        $existing = self::find($clinicId, $patientId);
        if ($existing === null) {
            throw new \RuntimeException('Patient not found');
        }

        $data = self::mapPayload($payload);
        if ($photoFile !== null) {
            $path = StorageService::storePatientPhoto($clinicId, $patientId, $photoFile);
            if ($path !== null) {
                $data['photo_path'] = $path;
            }
        }

        QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('id', '=', $patientId)
            ->update($data);

        return QueryBuilder::table('patients')->where('id', '=', $patientId)->first() ?? [];
    }

    public static function regenerateQrToken(int $clinicId, int $patientId): array
    {
        $patient = self::find($clinicId, $patientId);
        if ($patient === null) {
            throw new \RuntimeException('Patient not found');
        }

        $newToken = bin2hex(random_bytes(32));
        QueryBuilder::table('patients')
            ->forClinic($clinicId)
            ->where('id', '=', $patientId)
            ->update([
                'qr_token' => $newToken,
                'qr_card_path' => null,
            ]);

        $patient = QueryBuilder::table('patients')->where('id', '=', $patientId)->first();
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient !== null && $clinic !== null) {
            try {
                QrCardService::generateForPatient($patient, $clinic);
            } catch (\Throwable $e) {
                error_log('[QrCardService] regen failed for patient ' . $patientId . ': ' . $e->getMessage());
            }
        }

        return $patient ?? [];
    }

    /** @return list<array<string, mixed>> */
    public static function visits(int $clinicId, int $patientId, int $limit = 20): array
    {
        return QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('visited_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function vitals(int $clinicId, int $patientId, int $limit = 50): array
    {
        return QueryBuilder::table('vitals')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('recorded_at', 'ASC')
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function prescriptions(int $clinicId, int $patientId, int $limit = 30): array
    {
        return QueryBuilder::table('prescriptions')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function invoices(int $clinicId, int $patientId, int $limit = 20): array
    {
        return QueryBuilder::table('invoices')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function documents(int $clinicId, int $patientId): array
    {
        return QueryBuilder::table('patient_documents')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private static function mapPayload(array $payload): array
    {
        $allergies = $payload['allergies'] ?? [];
        $chronic = $payload['chronic_conditions'] ?? [];
        if (is_string($allergies)) {
            $allergies = array_filter(array_map('trim', explode(',', $allergies)));
        }
        if (is_string($chronic)) {
            $chronic = array_filter(array_map('trim', explode(',', $chronic)));
        }

        $specialtyData = $payload['specialty_data'] ?? [];
        if (is_string($specialtyData)) {
            $specialtyData = json_decode($specialtyData, true) ?: [];
        }
        if (!empty($payload['surgeries']) || !empty($payload['family_history'])) {
            $specialtyData['medical_history'] = [
                'surgeries' => $payload['surgeries'] ?? '',
                'family_history' => $payload['family_history'] ?? '',
            ];
        }

        return [
            'name' => trim((string) ($payload['name'] ?? '')),
            'phone' => self::normalizePhone((string) ($payload['phone'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')) ?: null,
            'dob' => !empty($payload['dob']) ? $payload['dob'] : null,
            'gender' => in_array($payload['gender'] ?? '', ['M', 'F', 'Other'], true) ? $payload['gender'] : null,
            'address' => trim((string) ($payload['address'] ?? '')) ?: null,
            'blood_group' => !empty($payload['blood_group']) ? $payload['blood_group'] : null,
            'veg_type' => $payload['veg_type'] ?? 'veg',
            'allergies' => json_encode(is_array($allergies) ? $allergies : []),
            'chronic_conditions' => json_encode(is_array($chronic) ? $chronic : []),
            'specialty_data' => json_encode($specialtyData),
            'insurance_provider' => trim((string) ($payload['insurance_provider'] ?? '')) ?: null,
            'insurance_id' => trim((string) ($payload['insurance_id'] ?? '')) ?: null,
            'referred_by' => trim((string) ($payload['referred_by'] ?? '')) ?: null,
            'source' => in_array($payload['source'] ?? 'walk_in', ['walk_in', 'referral', 'online', 'camp', 'other'], true)
                ? $payload['source'] : 'walk_in',
        ];
    }

    /** @return array{0: string, 1: int} */
    private static function allocateUhid(int $clinicId, string $prefix, \PDO $pdo): array
    {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(uhid_seq), 0) AS max_seq FROM patients WHERE clinic_id = ? FOR UPDATE',
        );
        $stmt->execute([$clinicId]);
        $next = (int) ($stmt->fetch()['max_seq'] ?? 0) + 1;

        return [sprintf('%s-%05d', $prefix, $next), $next];
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    }

    /** @return list<string> */
    public static function decodeTags(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }
}
