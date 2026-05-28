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
        // Editable visit date/time — for late catch-up entry. Only accept a
        // parseable datetime; ignore blanks so a normal save never clears it.
        if (!empty($payload['visited_at'])) {
            $ts = strtotime((string) $payload['visited_at']);
            if ($ts !== false) {
                $update['visited_at'] = date('Y-m-d H:i:s', $ts);
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

        // Phase 2: stash the full client-side form blob for crash recovery.
        // The columns may not exist yet during phased rollout — wrap so a
        // missing column never breaks autosave.
        if (isset($payload['_form_blob']) && is_array($payload['_form_blob'])) {
            try {
                QueryBuilder::table('visits')
                    ->forClinic($clinicId)
                    ->where('id', '=', $visitId)
                    ->update([
                        'auto_save_data' => json_encode($payload['_form_blob']),
                        'last_autosave_at' => date('Y-m-d H:i:s'),
                    ]);
            } catch (\Throwable $e) {
                // auto_save_data / last_autosave_at column doesn't exist yet — skip.
            }
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

        // Phase 4: sync the canonical follow_ups row from the follow-up date
        // captured in the visit form. Wrapped — follow_ups may not exist yet.
        if (array_key_exists('follow_up_date', $payload)) {
            try {
                \App\Services\FollowUpService::upsertForVisit(
                    $clinicId,
                    (int) $visit['patient_id'],
                    $visitId,
                    (int) ($visit['doctor_id'] ?? 0) ?: null,
                    (string) ($payload['follow_up_date'] ?? ''),
                    $payload['follow_up_reason'] ?? null,
                    $payload['follow_up_notes'] ?? null
                );
            } catch (\Throwable $e) {
                // follow_ups table doesn't exist yet (pre-Phase-4 migration).
            }
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

        // PDF generation + Rx WhatsApp delivery are OPTIONAL side-effects.
        // The visit is already marked completed above, so a failure here
        // (PDF lib, file perms, messaging config) must NOT 500 the request.
        $rxPath = null;
        if ($prescriptions !== [] && $patient !== null && $clinic !== null) {
            try {
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
            } catch (\Throwable $e) {
                error_log('[visit.complete] PDF/notify failed for visit ' . $visitId . ': ' . $e->getMessage());
            }
        }

        try {
            EventBus::fire('visit.completed', [
                'visit_id' => $visitId,
                'patient_id' => (int) $visit['patient_id'],
                'appointment_id' => $visit['appointment_id'] ?? null,
                'clinic_id' => $clinicId,
            ], 'visits', $visitId);
            DashboardService::invalidateStats($clinicId);
        } catch (\Throwable $e) {
            error_log('[visit.complete] post-complete hook failed for visit ' . $visitId . ': ' . $e->getMessage());
        }

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
        // Show all prior visits (in-progress, completed, cancelled) — NOT just
        // completed. Doctors review past consultations regardless of status;
        // only blank 'draft' shells are excluded.
        $query = QueryBuilder::table('visits')
            ->forClinic($clinicId)
            ->where('patient_id', '=', $patientId)
            ->where('status', '!=', 'draft')
            ->orderBy('visited_at', 'DESC')
            ->limit($limit + 1);

        $rows = $query->get();
        if ($excludeVisitId !== null) {
            $rows = array_filter($rows, static fn ($r) => (int) $r['id'] !== $excludeVisitId);
        }

        $visits = array_slice(array_map([self::class, 'hydrate'], array_values($rows)), 0, $limit);

        return self::attachHistoryMeta($clinicId, $visits);
    }

    /**
     * Batch-attach a medicines summary + linked invoice to each visit row,
     * for the visit-history list. Two queries total (no N+1).
     * @param list<array<string,mixed>> $visits
     * @return list<array<string,mixed>>
     */
    private static function attachHistoryMeta(int $clinicId, array $visits): array
    {
        if ($visits === [] || !Database::ping()) {
            return $visits;
        }

        $ids = array_values(array_unique(array_map(static fn ($v) => (int) $v['id'], $visits)));
        if ($ids === []) {
            return $visits;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::connection();

        // Medicine names per visit (drug or remedy), ordered by sort_order.
        $meds = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT p.visit_id, COALESCE(d.name, r.name) AS name
                   FROM prescriptions p
                   LEFT JOIN drugs d ON d.id = p.drug_id
                   LEFT JOIN remedies r ON r.id = p.remedy_id
                  WHERE p.clinic_id = ? AND p.visit_id IN ($placeholders)
                  ORDER BY p.visit_id, p.sort_order, p.id"
            );
            $stmt->execute([$clinicId, ...$ids]);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $meds[(int) $row['visit_id']][] = $name;
            }
        } catch (\Throwable $e) { /* table/columns missing → no summary */ }

        // Latest invoice per visit (total + status), if billing is in use.
        $invoices = [];
        try {
            $stmt = $pdo->prepare(
                "SELECT i.visit_id, i.id, i.total, i.status
                   FROM invoices i
                  WHERE i.clinic_id = ? AND i.visit_id IN ($placeholders)
                  ORDER BY i.visit_id, i.id DESC"
            );
            $stmt->execute([$clinicId, ...$ids]);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $vid = (int) $row['visit_id'];
                if (isset($invoices[$vid])) {
                    continue; // keep the newest (first seen due to ORDER BY id DESC)
                }
                $invoices[$vid] = [
                    'id' => (int) $row['id'],
                    'total' => (float) $row['total'],
                    'status' => (string) $row['status'],
                ];
            }
        } catch (\Throwable $e) { /* no invoices table → no amount */ }

        foreach ($visits as &$v) {
            $vid = (int) $v['id'];
            $names = $meds[$vid] ?? [];
            $shown = array_slice($names, 0, 3);
            $summary = implode(', ', $shown);
            if (count($names) > 3) {
                $summary .= ' +' . (count($names) - 3);
            }
            $v['medicines_summary'] = $summary;
            $v['invoice'] = $invoices[$vid] ?? null;
        }
        unset($v);

        return $visits;
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
