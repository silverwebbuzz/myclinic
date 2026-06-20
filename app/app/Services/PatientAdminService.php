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
    /** Default rows per page for the admin patient lists. */
    public const PER_PAGE = 25;

    /**
     * Onboarded clinic-patient list across every clinic, newest first, with
     * the clinic name and a quick visit/appointment count per patient.
     *
     * Search matches name / phone / UHID / email. The total reflects the
     * SAME filter as the page, so pagination is correct while searching.
     *
     * @return array{patients: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function patientsList(string $search = '', int $page = 1, int $perPage = self::PER_PAGE): array
    {
        if (!Database::ping()) {
            return ['patients' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];
        }

        $pdo = Database::connection();
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE p.name LIKE :q OR p.phone LIKE :q OR p.uhid LIKE :q OR p.email LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        // Filtered total — drives the page count and must use the same WHERE.
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM patients p {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        [$page, $perPage, $offset, $pages] = self::paginate($page, $perPage, $total);

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
             LIMIT {$perPage} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'patients' => $patients,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    /**
     * Front-side signups — the global `patient_identities` table. These are
     * people who created an account / booked from the public site, NOT clinic
     * charts. `clinic_count` = how many clinic charts link back to them.
     *
     * @return array{signups: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function signupsList(string $search = '', int $page = 1, int $perPage = self::PER_PAGE): array
    {
        if (!Database::ping()) {
            return ['signups' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];
        }

        $pdo = Database::connection();
        try {
            $where = '';
            $params = [];
            if ($search !== '') {
                $where = 'WHERE pi.name LIKE :q OR pi.phone LIKE :q OR pi.email LIKE :q';
                $params[':q'] = '%' . $search . '%';
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM patient_identities pi {$where}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            [$page, $perPage, $offset, $pages] = self::paginate($page, $perPage, $total);

            $sql = "
                SELECT pi.id, pi.name, pi.phone, pi.email, pi.gender, pi.dob,
                       pi.source, pi.is_active, pi.phone_verified_at, pi.created_at,
                       (SELECT COUNT(*) FROM patients p WHERE p.identity_id = pi.id) AS clinic_count,
                       (SELECT COUNT(*) FROM patient_sessions s WHERE s.identity_id = pi.id) AS session_count,
                       (SELECT MAX(s.last_seen_at) FROM patient_sessions s WHERE s.identity_id = pi.id) AS last_seen_at
                  FROM patient_identities pi
                  {$where}
              ORDER BY pi.created_at DESC
                 LIMIT {$perPage} OFFSET {$offset}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $signups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['signups' => $signups, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => $pages];
        } catch (\Throwable $e) {
            // patient_identities migration not applied on this install.
            return ['signups' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];
        }
    }

    /**
     * One front-side signup with their cross-clinic activity: linked clinic
     * charts, every appointment they generated, login sessions, and saved
     * doctors (wishlist).
     *
     * @return array<string, mixed>|null
     */
    public static function signupDetail(int $identityId): ?array
    {
        if ($identityId < 1 || !Database::ping()) {
            return null;
        }

        $pdo = Database::connection();
        try {
            $stmt = $pdo->prepare('SELECT * FROM patient_identities WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $identityId]);
            $identity = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$identity) {
                return null;
            }

            // Clinic charts linked to this person.
            $stmt = $pdo->prepare(
                'SELECT p.id, p.uhid, p.source, p.created_at, t.name AS clinic_name
                   FROM patients p
              LEFT JOIN tenants t ON t.id = p.clinic_id
                  WHERE p.identity_id = :id
               ORDER BY p.created_at DESC'
            );
            $stmt->execute([':id' => $identityId]);
            $charts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Appointments across all their charts (the bookings they made).
            $appointments = [];
            $chartIds = array_column($charts, 'id');
            if ($chartIds !== []) {
                $in = implode(',', array_map('intval', $chartIds));
                $appointments = $pdo->query(
                    "SELECT a.id, a.scheduled_at, a.type, a.source, a.status,
                            t.name AS clinic_name, u.name AS doctor_name
                       FROM appointments a
                  LEFT JOIN tenants t ON t.id = a.clinic_id
                  LEFT JOIN users u ON u.id = a.doctor_id
                      WHERE a.patient_id IN ({$in})
                   ORDER BY a.scheduled_at DESC LIMIT 100"
                )->fetchAll(PDO::FETCH_ASSOC);
            }

            // Login sessions (front-side panel auth).
            $stmt = $pdo->prepare(
                'SELECT created_at, last_seen_at, ip, user_agent, expires_at
                   FROM patient_sessions
                  WHERE identity_id = :id
               ORDER BY last_seen_at DESC LIMIT 100'
            );
            $stmt->execute([':id' => $identityId]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Saved doctors (wishlist) — optional table.
            $wishlist = [];
            try {
                $stmt = $pdo->prepare(
                    'SELECT w.added_at, d.full_name AS doctor_name
                       FROM patient_wishlist w
                  LEFT JOIN directory_doctors d ON d.id = w.doctor_id
                      WHERE w.identity_id = :id
                   ORDER BY w.added_at DESC LIMIT 100'
                );
                $stmt->execute([':id' => $identityId]);
                $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                // wishlist table absent — skip.
            }

            return [
                'identity' => $identity,
                'charts' => $charts,
                'appointments' => $appointments,
                'sessions' => $sessions,
                'wishlist' => $wishlist,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Normalise paging inputs against a known total.
     *
     * @return array{0:int,1:int,2:int,3:int} [page, perPage, offset, pages]
     */
    private static function paginate(int $page, int $perPage, int $total): array
    {
        $perPage = max(1, min(100, $perPage));
        $pages = (int) max(1, ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        return [$page, $perPage, $offset, $pages];
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
