<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class VisitService
{
    public static function find(int $clinicId, int $id): ?array
    {
        $row = QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->first();

        return $row ? self::hydrate($row) : null;
    }

    /** @return array<string, mixed> */
    private static function hydrate(array $row): array
    {
        if (is_string($row['specialty_data'] ?? null)) {
            $row['specialty_data'] = json_decode($row['specialty_data'], true) ?: [];
        }
        if (!is_array($row['specialty_data'])) {
            $row['specialty_data'] = [];
        }

        return $row;
    }

    public static function isEditable(array $visit): bool
    {
        $status = $visit['status'] ?? 'in_progress';

        return $status === 'in_progress' || !empty($visit['unlocked_at']);
    }

    /** @return array<string, mixed> */
    public static function startFromAppointment(int $clinicId, int $appointmentId): array
    {
        $appt = AppointmentService::findDetailed($clinicId, $appointmentId);
        if ($appt === null) {
            throw new \RuntimeException('Appointment not found');
        }

        $existing = QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('appointment_id', '=', $appointmentId)
            ->where('status', '=', 'in_progress')
            ->first();

        if ($existing !== null) {
            AppointmentService::updateStatus($clinicId, $appointmentId, 'in_progress');

            return self::find($clinicId, (int) $existing['id']) ?? [];
        }

        return self::create($clinicId, [
            'patient_id' => (int) $appt['patient_id'],
            'doctor_id' => (int) $appt['doctor_id'],
            'appointment_id' => $appointmentId,
            'chief_complaint' => $appt['chief_complaint'] ?? '',
            'is_followup' => !empty($appt['is_followup']),
        ]);
    }

    /** @return array<string, mixed> */
    public static function startForPatient(int $clinicId, int $patientId, ?int $doctorId = null): array
    {
        $user = RequestContext::user();
        $doctorId = $doctorId ?? (int) ($user['id'] ?? 0);

        return self::create($clinicId, [
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function create(int $clinicId, array $data): array
    {
        $patientId = (int) $data['patient_id'];
        $doctorId = (int) $data['doctor_id'];
        $visitNumber = self::nextVisitNumber($clinicId, $patientId);

        $specialtyData = [];
        if (!empty($data['is_followup'])) {
            $specialtyData['is_followup'] = true;
        }

        $id = QueryBuilder::table('visits')->insert([
            'clinic_id' => $clinicId,
            'appointment_id' => $data['appointment_id'] ?? null,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'visit_number' => $visitNumber,
            'status' => 'in_progress',
            'chief_complaint' => trim((string) ($data['chief_complaint'] ?? '')) ?: null,
            'specialty_data' => $specialtyData !== [] ? json_encode($specialtyData) : null,
        ]);

        if (!empty($data['appointment_id'])) {
            AppointmentService::updateStatus($clinicId, (int) $data['appointment_id'], 'in_progress');
        }

        DashboardService::invalidateStats($clinicId);

        return self::find($clinicId, $id) ?? [];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function autosave(int $clinicId, int $visitId, array $payload): array
    {
        $visit = self::find($clinicId, $visitId);
        if ($visit === null) {
            throw new \RuntimeException('Visit not found');
        }
        if (!self::isEditable($visit)) {
            throw new \RuntimeException('Visit is read-only');
        }

        $update = [];
        foreach (['chief_complaint', 'history', 'examination', 'diagnosis', 'icd10_code', 'clinical_notes', 'follow_up_notes'] as $field) {
            if (array_key_exists($field, $payload)) {
                $update[$field] = $payload[$field] === '' ? null : $payload[$field];
            }
        }
        if (array_key_exists('condition_score', $payload)) {
            $update['condition_score'] = $payload['condition_score'] === '' ? null : (int) $payload['condition_score'];
        }
        if (array_key_exists('follow_up_date', $payload)) {
            $update['follow_up_date'] = $payload['follow_up_date'] === '' ? null : $payload['follow_up_date'];
        }

        if (isset($payload['specialty_data']) && is_array($payload['specialty_data'])) {
            $merged = array_merge($visit['specialty_data'] ?? [], $payload['specialty_data']);
            $update['specialty_data'] = json_encode($merged);
        }

        if ($update !== []) {
            QueryBuilder::table('visits')
                ->forClinic($clinicId)
                ->where('id', '=', $visitId)
                ->update($update);
        }

        if (isset($payload['vitals']) && is_array($payload['vitals'])) {
            VitalsService::saveForVisit($clinicId, $visitId, (int) $visit['patient_id'], $payload['vitals']);
        }

        if (isset($payload['prescriptions']) && is_array($payload['prescriptions'])) {
            PrescriptionService::syncForVisit($clinicId, $visitId, (int) $visit['patient_id'], $payload['prescriptions']);
        }

        return self::find($clinicId, $visitId) ?? [];
    }

    /** @return array<string, mixed> */
    public static function complete(int $clinicId, int $visitId): array
    {
        $visit = self::findDetailed($clinicId, $visitId);
        if ($visit === null) {
            throw new \RuntimeException('Visit not found');
        }
        if (!self::isEditable($visit)) {
            throw new \RuntimeException('Visit already completed');
        }

        QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('id', '=', $visitId)
            ->update(['status' => 'completed']);

        if (!empty($visit['appointment_id'])) {
            AppointmentService::updateStatus($clinicId, (int) $visit['appointment_id'], 'completed');
        }

        $patient = PatientService::find($clinicId, (int) $visit['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        $prescriptions = PrescriptionService::forVisit($clinicId, $visitId);

        $rxPath = null;
        if ($prescriptions !== [] && $patient !== null && $clinic !== null) {
            $visit['diagnosis'] = $visit['diagnosis'] ?? '';
            $rxPath = RxPdfService::generate($visit, $patient, $clinic, $prescriptions);
            QueryBuilder::table('visits')
                ->forClinic($clinicId)
                ->where('id', '=', $visitId)
                ->update(['rx_pdf_path' => $rxPath]);

            $config = OnboardingService::specialtyConfig($clinicId) ?? [];
            $prefs = $config['notification_prefs'] ?? null;
            if (is_string($prefs)) {
                $prefs = json_decode($prefs, true);
            }
            if (is_array($prefs) && ($prefs['rx_delivery'] ?? true)) {
                NotificationService::queueWhatsApp(
                    $clinicId,
                    (int) $patient['id'],
                    (string) $patient['phone'],
                    'rx_delivery',
                    [
                        'patient_name' => $patient['name'],
                        'clinic_name' => $clinic['name'],
                        'rx_url' => $rxPath,
                    ],
                    date('Y-m-d H:i:s', time() + 120),
                );
            }
        }

        EventBus::fire('visit.completed', [
            'visit_id' => $visitId,
            'patient_id' => (int) $visit['patient_id'],
            'appointment_id' => $visit['appointment_id'] ?? null,
            'clinic_id' => $clinicId,
        ], 'visits', $visitId);

        DashboardService::invalidateStats($clinicId);

        return self::findDetailed($clinicId, $visitId) ?? [];
    }

    public static function unlock(int $clinicId, int $visitId): array
    {
        $user = RequestContext::user();
        if (empty($user['is_owner']) && ($user['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Only clinic admin can unlock completed visits');
        }

        QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('id', '=', $visitId)
            ->update([
                'unlocked_by' => $user['id'],
                'unlocked_at' => date('Y-m-d H:i:s'),
            ]);

        return self::find($clinicId, $visitId) ?? [];
    }

    /** @return array<string, mixed>|null */
    public static function findDetailed(int $clinicId, int $id): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT v.*, p.name AS patient_name, p.uhid, p.phone AS patient_phone, p.allergies,
                    u.name AS doctor_name
             FROM visits v
             INNER JOIN patients p ON p.id = v.patient_id
             INNER JOIN users u ON u.id = v.doctor_id
             WHERE v.clinic_id = ? AND v.id = ?',
        );
        $stmt->execute([$clinicId, $id]);
        $row = $stmt->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public static function recentForPatient(int $clinicId, int $patientId, int $limit = 3, ?int $excludeVisitId = null): array
    {
        $query = QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->where('status', '=', 'completed')
            ->orderBy('visited_at', 'DESC')
            ->limit($limit + 1);

        $rows = $query->get();
        if ($excludeVisitId !== null) {
            $rows = array_filter($rows, static fn ($r) => (int) $r['id'] !== $excludeVisitId);
        }

        return array_slice(array_map([self::class, 'hydrate'], array_values($rows)), 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    public static function listRecent(int $clinicId, int $limit = 30): array
    {
        if (!Database::ping()) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT v.*, p.name AS patient_name, p.uhid, u.name AS doctor_name
             FROM visits v
             INNER JOIN patients p ON p.id = v.patient_id
             INNER JOIN users u ON u.id = v.doctor_id
             WHERE v.clinic_id = ?
             ORDER BY v.visited_at DESC
             LIMIT ?',
        );
        $stmt->bindValue(1, $clinicId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([self::class, 'hydrate'], $stmt->fetchAll() ?: []);
    }

    private static function nextVisitNumber(int $clinicId, int $patientId): int
    {
        $count = QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->count();

        return $count + 1;
    }
}
