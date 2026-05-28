<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * FollowUpService — Phase 4 follow-up state machine.
 *
 * follow_ups is the canonical store (separate from the legacy
 * visits.follow_up_date free-text column, which stays for back-compat).
 *
 * State machine: pending → done | missed | rescheduled | cancelled
 */
final class FollowUpService
{
    /**
     * Create or update the pending follow-up for a visit. One pending
     * follow-up per visit — re-saving the visit updates it in place.
     */
    public static function upsertForVisit(
        int $clinicId,
        int $patientId,
        int $visitId,
        ?int $doctorId,
        string $dueDate,
        ?string $reason,
        ?string $reasonOther
    ): void {
        if ($dueDate === '') {
            // Empty date = doctor cleared the follow-up. Cancel any pending one.
            self::cancelPendingForVisit($clinicId, $visitId);
            return;
        }

        $pdo = Database::connection();
        $existing = $pdo->prepare(
            "SELECT id FROM follow_ups
              WHERE visit_id = :v AND status = 'pending' LIMIT 1"
        );
        $existing->execute([':v' => $visitId]);
        $id = $existing->fetchColumn();

        if ($id) {
            $pdo->prepare(
                'UPDATE follow_ups
                    SET due_date = :d, reason = :r, reason_other = :ro, doctor_id = :doc
                  WHERE id = :id'
            )->execute([
                ':d' => $dueDate,
                ':r' => $reason,
                ':ro' => $reasonOther,
                ':doc' => $doctorId,
                ':id' => $id,
            ]);
            return;
        }

        $pdo->prepare(
            'INSERT INTO follow_ups
                (clinic_id, patient_id, visit_id, doctor_id, due_date, reason, reason_other, status)
             VALUES (:c, :p, :v, :doc, :d, :r, :ro, "pending")'
        )->execute([
            ':c' => $clinicId,
            ':p' => $patientId,
            ':v' => $visitId,
            ':doc' => $doctorId,
            ':d' => $dueDate,
            ':r' => $reason,
            ':ro' => $reasonOther,
        ]);
    }

    public static function cancelPendingForVisit(int $clinicId, int $visitId): void
    {
        Database::connection()->prepare(
            "UPDATE follow_ups SET status = 'cancelled'
              WHERE clinic_id = :c AND visit_id = :v AND status = 'pending'"
        )->execute([':c' => $clinicId, ':v' => $visitId]);
    }

    public static function markDone(int $clinicId, int $followUpId, ?int $completedVisitId = null): void
    {
        Database::connection()->prepare(
            "UPDATE follow_ups
                SET status = 'done', completed_visit_id = :cv
              WHERE id = :id AND clinic_id = :c"
        )->execute([':cv' => $completedVisitId, ':id' => $followUpId, ':c' => $clinicId]);
    }

    public static function cancel(int $clinicId, int $followUpId): void
    {
        Database::connection()->prepare(
            "UPDATE follow_ups SET status = 'cancelled'
              WHERE id = :id AND clinic_id = :c"
        )->execute([':id' => $followUpId, ':c' => $clinicId]);
    }

    /**
     * Reschedule: mark old as 'rescheduled', create a fresh pending row,
     * link old → new via rescheduled_to_id.
     */
    public static function reschedule(int $clinicId, int $followUpId, string $newDate): ?int
    {
        $pdo = Database::connection();
        $old = $pdo->prepare('SELECT * FROM follow_ups WHERE id = :id AND clinic_id = :c LIMIT 1');
        $old->execute([':id' => $followUpId, ':c' => $clinicId]);
        $row = $old->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $pdo->prepare(
            'INSERT INTO follow_ups
                (clinic_id, patient_id, visit_id, doctor_id, due_date, reason, reason_other, status)
             VALUES (:c, :p, :v, :doc, :d, :r, :ro, "pending")'
        )->execute([
            ':c' => $clinicId,
            ':p' => $row['patient_id'],
            ':v' => $row['visit_id'],
            ':doc' => $row['doctor_id'],
            ':d' => $newDate,
            ':r' => $row['reason'],
            ':ro' => $row['reason_other'],
        ]);
        $newId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            "UPDATE follow_ups SET status = 'rescheduled', rescheduled_to_id = :new
              WHERE id = :id"
        )->execute([':new' => $newId, ':id' => $followUpId]);

        return $newId;
    }

    /**
     * Dashboard widget data: overdue list + counts.
     * @return array{overdue: list<array<string,mixed>>, overdue_count: int, due_week: int, done_month: int}
     */
    public static function dashboardData(int $clinicId): array
    {
        $pdo = Database::connection();

        $overdue = $pdo->prepare(
            "SELECT f.id, f.due_date, f.reason, f.reason_other, f.patient_id,
                    p.name AS patient_name, p.phone AS patient_phone,
                    DATEDIFF(CURDATE(), f.due_date) AS days_overdue
               FROM follow_ups f
               JOIN patients p ON p.id = f.patient_id
              WHERE f.clinic_id = :c AND f.status = 'pending' AND f.due_date < CURDATE()
              ORDER BY f.due_date ASC
              LIMIT 5"
        );
        $overdue->execute([':c' => $clinicId]);
        $overdueRows = $overdue->fetchAll(PDO::FETCH_ASSOC);

        $count = static function (PDO $pdo, string $sql, int $clinicId): int {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':c' => $clinicId]);
            return (int) $stmt->fetchColumn();
        };

        return [
            'overdue' => $overdueRows,
            'overdue_count' => $count(
                $pdo,
                "SELECT COUNT(*) FROM follow_ups WHERE clinic_id = :c AND status = 'pending' AND due_date < CURDATE()",
                $clinicId
            ),
            'due_week' => $count(
                $pdo,
                "SELECT COUNT(*) FROM follow_ups
                  WHERE clinic_id = :c AND status = 'pending'
                    AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
                $clinicId
            ),
            'done_month' => $count(
                $pdo,
                "SELECT COUNT(*) FROM follow_ups
                  WHERE clinic_id = :c AND status = 'done'
                    AND updated_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
                $clinicId
            ),
        ];
    }

    /**
     * Reception queue flag: which of these patients have a pending follow-up
     * due within the next 7 days (or already overdue)?
     *
     * @param list<int> $patientIds
     * @return array<int, array{due_date: string, reason: string, overdue: bool}>
     */
    public static function pendingForPatients(int $clinicId, array $patientIds): array
    {
        $patientIds = array_values(array_filter(array_map('intval', $patientIds)));
        if ($patientIds === []) return [];

        $placeholders = implode(',', array_fill(0, count($patientIds), '?'));
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            "SELECT patient_id, due_date, reason,
                    (due_date < CURDATE()) AS overdue
               FROM follow_ups
              WHERE clinic_id = ? AND status = 'pending'
                AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND patient_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$clinicId], $patientIds));

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // First/earliest pending per patient wins.
            $pid = (int) $row['patient_id'];
            if (!isset($out[$pid])) {
                $out[$pid] = [
                    'due_date' => (string) $row['due_date'],
                    'reason' => (string) ($row['reason'] ?? ''),
                    'overdue' => (bool) $row['overdue'],
                ];
            }
        }
        return $out;
    }

    /** Pending follow-up for a single patient (used on the visit screen). */
    public static function pendingForPatient(int $clinicId, int $patientId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, due_date, reason, reason_other, visit_id
               FROM follow_ups
              WHERE clinic_id = :c AND patient_id = :p AND status = 'pending'
              ORDER BY due_date ASC LIMIT 1"
        );
        $stmt->execute([':c' => $clinicId, ':p' => $patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array{reason_key: string, label: string}> */
    public static function reasons(int $clinicId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT reason_key, label FROM follow_up_reasons
              WHERE is_active = 1 AND (clinic_id IS NULL OR clinic_id = :c)
              ORDER BY sort_order ASC'
        );
        $stmt->execute([':c' => $clinicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =====================================================
    // Cron jobs
    // =====================================================

    /**
     * Queue WhatsApp reminders for follow-ups due today/tomorrow.
     * Only fires for clinics with the patient_connect add-on active.
     * Returns count queued.
     */
    public static function runReminders(): int
    {
        $pdo = Database::connection();
        $rows = $pdo->query(
            "SELECT f.id, f.clinic_id, f.patient_id, f.due_date, f.reason,
                    p.name AS patient_name, p.phone AS patient_phone
               FROM follow_ups f
               JOIN patients p ON p.id = f.patient_id
              WHERE f.status = 'pending'
                AND f.due_date IN (CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 DAY))
                AND f.reminder_count < 3
                AND p.phone IS NOT NULL AND p.phone <> ''"
        )->fetchAll(PDO::FETCH_ASSOC);

        $queued = 0;
        foreach ($rows as $row) {
            $clinicId = (int) $row['clinic_id'];
            // Gate on patient_connect add-on. No add-on → no automated reminder.
            if (!\App\Support\Plan::hasAddon($clinicId, 'patient_connect')) {
                continue;
            }

            NotificationService::queueWhatsApp(
                $clinicId,
                (int) $row['patient_id'],
                (string) $row['patient_phone'],
                'follow_up_reminder',
                [
                    'patient_name' => $row['patient_name'],
                    'due_date' => $row['due_date'],
                    'reason' => $row['reason'],
                ],
                date('Y-m-d H:i:s')
            );

            $pdo->prepare(
                'UPDATE follow_ups
                    SET reminder_count = reminder_count + 1, reminder_sent_at = NOW()
                  WHERE id = :id'
            )->execute([':id' => $row['id']]);
            $queued++;
        }
        return $queued;
    }

    /** Mark follow-ups overdue by >30 days as 'missed'. Returns count. */
    public static function runMarkMissed(): int
    {
        $stmt = Database::connection()->prepare(
            "UPDATE follow_ups
                SET status = 'missed'
              WHERE status = 'pending'
                AND due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
