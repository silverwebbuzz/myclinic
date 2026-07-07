<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Pushes a completed visit's prescription into the patient's panel
 * (patient_prescriptions table, source = 'clinic').
 *
 * Design note — this deliberately writes into the SAME storage tree the
 * public patient panel serves from (repo-root /storage/patient_rx), NOT the
 * portal's own app/storage. Both apps share one database and one .env, so the
 * panel reads the row and streams the file straight away.
 *
 * The stored row carries doctor_name / clinic_name as TEXT SNAPSHOTS, so the
 * patient keeps a readable record even if the doctor or clinic later leaves.
 */
final class PatientPrescriptionShareService
{
    /**
     * @param array<string,mixed>       $visit   findDetailed() row (needs id, visited_at, doctor_name)
     * @param array<string,mixed>       $patient patients row (needs identity_id, name)
     * @param array<string,mixed>       $clinic  tenants row (needs id, name)
     * @param list<array<string,mixed>> $lines   prescription lines for the visit
     * @return bool true when a row was created (or already existed)
     */
    public static function share(array $visit, array $patient, array $clinic, array $lines): bool
    {
        $identityId = (int) ($patient['identity_id'] ?? 0);
        $visitId    = (int) ($visit['id'] ?? 0);
        $clinicId   = (int) ($clinic['id'] ?? 0);

        // Guard: only share when the patient has a panel account, the visit has
        // an Rx, and the DB is reachable.
        if ($identityId <= 0 || $visitId <= 0 || $lines === [] || !Database::ping()) {
            return false;
        }

        $pdo = Database::connection();

        // Idempotent: never create a second row for the same visit+patient.
        // (Matches uq_pp_clinic_share.) A re-complete just refreshes the PDF.
        $existing = $pdo->prepare(
            'SELECT id, file_path FROM patient_prescriptions
             WHERE owner_identity_id = :o AND source_visit_id = :v AND source_clinic_id = :c
             LIMIT 1'
        );
        $existing->execute(['o' => $identityId, 'v' => $visitId, 'c' => $clinicId]);
        $row = $existing->fetch(PDO::FETCH_ASSOC) ?: null;

        // Generate the PDF with the existing pad renderer, then copy it into the
        // patient's own storage folder (independent of the clinic's copy).
        $copyRel = null;
        $copyMime = null;
        try {
            $absSource = PrescriptionPdfService::generate($visit, $patient, $clinic, $lines, 'A5');
            if (is_file($absSource)) {
                $copy = self::copyIntoPatientStore($identityId, $visitId, $absSource);
                if ($copy !== null) {
                    $copyRel  = $copy;
                    $copyMime = 'application/pdf';
                }
            }
        } catch (\Throwable $e) {
            error_log('[rx share] PDF generation failed: ' . $e->getMessage());
            // Still record the share (label + doctor); the panel just shows no file.
        }

        $label       = self::buildLabel($visit);
        $doctorName  = self::clip((string) ($visit['doctor_name'] ?? ''), 160);
        $clinicName  = self::clip((string) ($clinic['name'] ?? ''), 160);
        $issuedOn    = !empty($visit['visited_at'])
            ? date('Y-m-d', strtotime((string) $visit['visited_at']))
            : null;

        if ($row !== null) {
            // Refresh the existing share. Drop the stale copy if we made a new one.
            if ($copyRel !== null && !empty($row['file_path']) && $row['file_path'] !== $copyRel) {
                self::deleteCopy((string) $row['file_path']);
            }
            $upd = $pdo->prepare(
                'UPDATE patient_prescriptions
                    SET label = :label, doctor_name = :doc, clinic_name = :clinic,
                        issued_on = :issued,
                        file_path = COALESCE(:fp, file_path),
                        file_mime = COALESCE(:mime, file_mime),
                        is_active = 1
                  WHERE id = :id'
            );
            $upd->execute([
                'label'  => $label,
                'doc'    => $doctorName,
                'clinic' => $clinicName,
                'issued' => $issuedOn,
                'fp'     => $copyRel,
                'mime'   => $copyMime,
                'id'     => (int) $row['id'],
            ]);
            return true;
        }

        $ins = $pdo->prepare(
            'INSERT INTO patient_prescriptions
                (owner_identity_id, family_member_id, label, doctor_name, clinic_name,
                 issued_on, file_path, file_mime, source, source_visit_id, source_clinic_id)
             VALUES
                (:o, :fm, :label, :doc, :clinic, :issued, :fp, :mime, "clinic", :v, :c)'
        );
        $ins->execute([
            'o'      => $identityId,
            'fm'     => self::resolveFamilyMember($visit),
            'label'  => $label,
            'doc'    => $doctorName,
            'clinic' => $clinicName,
            'issued' => $issuedOn,
            'fp'     => $copyRel,
            'mime'   => $copyMime,
            'v'      => $visitId,
            'c'      => $clinicId,
        ]);

        return true;
    }

    /** Auto label, e.g. "Dr. Jayesh — 20 May 2026". */
    private static function buildLabel(array $visit): string
    {
        $doctor = trim((string) ($visit['doctor_name'] ?? ''));
        $date   = !empty($visit['visited_at']) ? date('d M Y', strtotime((string) $visit['visited_at'])) : '';

        $parts = [];
        if ($doctor !== '') {
            $parts[] = str_starts_with(strtolower($doctor), 'dr') ? $doctor : ('Dr. ' . $doctor);
        }
        if ($date !== '') {
            $parts[] = $date;
        }
        $label = implode(' — ', $parts);
        return $label !== '' ? self::clip($label, 160) : 'Prescription';
    }

    /**
     * The visit may be booked for a family member; carry that link if present
     * so the panel files it under the right person. NULL = self.
     */
    private static function resolveFamilyMember(array $visit): ?int
    {
        $id = (int) ($visit['family_member_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * Copy the generated PDF into repo-root /storage/patient_rx/{identity}/.
     *
     * @return string|null relative path (from repo root) or null on failure
     */
    private static function copyIntoPatientStore(int $identityId, int $visitId, string $absSource): ?string
    {
        $repoRoot = dirname(__DIR__, 3); // app/app/Services → repo root
        $dir = $repoRoot . '/storage/patient_rx/' . $identityId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            error_log('[rx share] cannot create patient store: ' . $dir);
            return null;
        }
        // Stable name per visit so re-completing overwrites rather than piling up.
        $name = 'visit-' . $visitId . '.pdf';
        $dest = $dir . '/' . $name;
        if (!@copy($absSource, $dest)) {
            error_log('[rx share] copy failed: ' . $absSource . ' → ' . $dest);
            return null;
        }
        return 'storage/patient_rx/' . $identityId . '/' . $name;
    }

    /** Remove a stale patient copy when a newer one supersedes it. */
    private static function deleteCopy(string $relPath): void
    {
        if (!str_contains($relPath, 'storage/patient_rx/')) {
            return;
        }
        $abs = dirname(__DIR__, 3) . '/' . ltrim($relPath, '/');
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private static function clip(string $value, int $max): ?string
    {
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
