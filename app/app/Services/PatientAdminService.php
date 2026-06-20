<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Platform super-admin views over patient data, spanning all clinics
 * (tenants). Unlike PatientService — which is tenant-scoped for the doctor
 * panel — these queries are deliberately cross-clinic so the platform admin
 * can see who is on-board and drill into one patient's history + activity.
 */
final class PatientAdminService
{
    /**
     * Onboarded-patient list across every clinic, newest first, with the
     * clinic name and a quick visit/appointment count per patient.
     *
     * @return array{patients: list<array<string, mixed>>, total: int}
     */
    public static function patientsList(string $search = '', int $limit = 200): array
    {
        if (!Database::ping()) {
            return ['patients' => [], 'total' => 0];
        }

        $pdo = Database::connection();
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE p.name LIKE :q OR p.phone LIKE :q OR p.uhid LIKE :q OR p.email LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        $total = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();

        $sql = "
            SELECT p.id, p.uhid, p.name, p.phone, p.email, p.gender, p.dob,
                   p.source, p.is_active, p.created_at,
                   t.name AS clinic_name, t.slug AS clinic_slug,
                   (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.id) AS visit_count,
                   (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS appointment_count,
                   (SELECT MAX(v.visited_at) FROM visits v WHERE v.patient_id = p.id) AS last_visit_at
              FROM patients p
         LEFT JOIN tenants t ON t.id = p.clinic_id
              {$where}
          ORDER BY p.created_at DESC
             LIMIT {$limit}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['patients' => $patients, 'total' => $total];
    }

    /**
     * Full record for one patient: profile, every visit, every appointment,
     * and the raw audit activity touching this patient row.
     *
     * @return array<string, mixed>|null  null when the patient doesn't exist
     */
    public static function patientDetail(int $patientId): ?array
    {
        if ($patientId < 1 || !Database::ping()) {
            return null;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'SELECT p.*, t.name AS clinic_name, t.slug AS clinic_slug
               FROM patients p
          LEFT JOIN tenants t ON t.id = p.clinic_id
              WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patient) {
            return null;
        }

        // Clinical history — visits with the attending doctor's name.
        $stmt = $pdo->prepare(
            'SELECT v.id, v.visit_number, v.chief_complaint, v.diagnosis,
                    v.follow_up_date, v.visited_at, u.name AS doctor_name
               FROM visits v
          LEFT JOIN users u ON u.id = v.doctor_id
              WHERE v.patient_id = :id
           ORDER BY v.visited_at DESC LIMIT 100'
        );
        $stmt->execute([':id' => $patientId]);
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Appointment history.
        $stmt = $pdo->prepare(
            'SELECT a.id, a.scheduled_at, a.type, a.source, a.status,
                    a.chief_complaint, u.name AS doctor_name
               FROM appointments a
          LEFT JOIN users u ON u.id = a.doctor_id
              WHERE a.patient_id = :id
           ORDER BY a.scheduled_at DESC LIMIT 100'
        );
        $stmt->execute([':id' => $patientId]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Activity log — audit rows that touch this patient record.
        $activity = [];
        try {
            $stmt = $pdo->prepare(
                'SELECT al.action, al.created_at, al.ip_address, u.name AS user_name
                   FROM audit_log al
              LEFT JOIN users u ON u.id = al.user_id
                  WHERE al.table_name = :tbl AND al.record_id = :id
               ORDER BY al.created_at DESC LIMIT 100'
            );
            $stmt->execute([':tbl' => 'patients', ':id' => $patientId]);
            $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // audit_log table may not exist on older installs — skip silently.
        }

        return [
            'patient' => $patient,
            'visits' => $visits,
            'appointments' => $appointments,
            'activity' => $activity,
        ];
    }
}
