<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Database;
use App\Services\Corporate\CorpAudit;
use App\Services\Corporate\ScreeningEngine;
use App\Services\SlotService;
use App\Support\Locale\LocalDate;

/**
 * The clinic doctor's "Occupational exam" panel on a corporate visit (design
 * §4, C7–C12): tick the checks done, record the fitness outcome, work
 * restrictions, validity and the employee's consent to share, then sign. That
 * issues the certificate (PDF + verify token + SHA-256), completes the checks
 * (the engine then starts the next cycle) and marks the booking attended.
 *
 * Only what is printed on the certificate leaves the clinic: no history,
 * examination findings, vitals or notes (those stay in the visit).
 */
final class OccExamService
{
    public const OUTCOMES = [
        'fit' => 'Fit',
        'fit_restricted' => 'Fit with restrictions',
        'temp_unfit' => 'Temporarily unfit',
        'unfit' => 'Unfit',
        'further_assessment' => 'Further assessment required',
    ];

    /** Work-term restrictions (C8). Free text goes in the note. */
    public const RESTRICTIONS = [
        'no_height' => 'No work at height',
        'no_driving' => 'No driving, crane or heavy-plant operation',
        'no_heavy_lifting' => 'No lifting over 20 kg',
        'no_night' => 'No night shifts',
        'no_confined' => 'No work in confined spaces',
        'noise_ppe' => 'Hearing protection required in noise areas',
        'no_hot_work' => 'No hot work or welding',
        'no_food' => 'No food handling',
    ];

    private const MIN_REASON = 5;
    private const MAX_VALID_YEARS = 5;

    /**
     * Panel data for a visit, or null when the visit isn't a corporate booking.
     *
     * @param array<string, mixed> $visit
     * @return array<string, mixed>|null
     */
    public static function forVisit(int $clinicId, array $visit, int $userId): ?array
    {
        $appointmentId = (int) ($visit['appointment_id'] ?? 0);
        if ($appointmentId <= 0) {
            return null;
        }
        try {
            $booking = self::booking($clinicId, $appointmentId);
        } catch (\Throwable $e) {
            return null;   // 1C patch not run yet
        }
        if ($booking === null) {
            return null;
        }
        $cases = self::cases((int) $booking['company_id'], (int) $booking['id']);
        $certs = self::certificates((int) $booking['company_id'], $clinicId, (int) $booking['id']);
        $valid = array_values(array_filter($certs, static fn ($c) => $c['status'] === 'valid'))[0] ?? null;
        $today = self::today($clinicId);
        $months = array_filter(array_map(static fn ($c) => $c['category'] === 'periodic' ? (int) $c['frequency_months'] : 0, $cases));

        return [
            'booking' => $booking,
            'cases' => $cases,
            'certificates' => $certs,
            'current' => $valid,
            'today' => $today,
            'default_valid_to' => LocalDate::addDays(\App\Services\Corporate\ScreeningPlanner::addMonths($today, $months !== [] ? min($months) : 12), -1),
            'default_review_by' => LocalDate::addDays($today, 30),
            'can_sign' => ClinicCorporateService::canSign($clinicId, $userId, $today),
            'visit_id' => (int) $visit['id'],
            // 2D: pre-exam questionnaire (published one) + this booking's answers (clinical)
            'questionnaire' => QuestionnaireService::active(),
            'q_response' => QuestionnaireService::forBooking((int) $booking['company_id'], (int) $booking['id']),
            // 1D-lite: test reports for the lab / X-ray checks, grouped by case id
            'reports' => CaseReportService::forCases((int) $booking['company_id'],
                array_map(static fn ($c) => (int) $c['id'], array_filter($cases, static fn ($c) => in_array($c['kind'], ['lab_test', 'imaging'], true)))),
        ];
    }

    /**
     * Sign and issue. $post: done[caseId]=1, not_done_reason, outcome, restrictions[], restrictions_note,
     * valid_to, review_by, consent (yes|no). With $replaceReason the current certificate is replaced
     * (correction): checks are taken from it and not touched again.
     *
     * @param array<string, mixed> $post
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function sign(int $clinicId, int $userId, array $visit, array $post): array
    {
        $panel = self::forVisit($clinicId, $visit, $userId);
        if ($panel === null) {
            return ['ok' => false, 'error' => 'This visit is not an employer-booked health check.'];
        }
        if (!$panel['can_sign']['ok']) {
            return ['ok' => false, 'error' => $panel['can_sign']['error']];
        }
        $credential = $panel['can_sign']['credential'];
        $booking = $panel['booking'];
        $current = $panel['current'];
        $correcting = $current !== null;
        $replaceReason = mb_substr(trim((string) ($post['replace_reason'] ?? '')), 0, 500);
        if ($correcting && mb_strlen($replaceReason) < self::MIN_REASON) {
            return ['ok' => false, 'error' => 'A certificate is already issued. To correct it, write what was wrong; the old one will show as replaced.'];
        }
        if (!$correcting && !in_array($booking['status'], ['booked', 'attended'], true)) {
            return ['ok' => false, 'error' => 'This booking was ' . ($booking['status'] === 'cancelled' ? 'cancelled' : 'closed') . '. Ask the company to book again.'];
        }
        $today = $panel['today'];

        // Checks done (first issue only).
        $done = [];
        $notDone = [];
        if (!$correcting) {
            $ticked = is_array($post['done'] ?? null) ? array_map('intval', array_keys(array_filter($post['done']))) : [];
            foreach ($panel['cases'] as $c) {
                if ($c['status'] !== 'scheduled') {
                    continue;
                }
                if (in_array((int) $c['id'], $ticked, true)) {
                    $done[] = $c;
                } else {
                    $notDone[] = $c;
                }
            }
            if ($done === []) {
                return ['ok' => false, 'error' => 'Tick at least one check that was done today.'];
            }
            $notDoneReason = mb_substr(trim((string) ($post['not_done_reason'] ?? '')), 0, 255);
            if ($notDone !== [] && mb_strlen($notDoneReason) < self::MIN_REASON) {
                return ['ok' => false, 'error' => 'Some checks are not ticked. Write why they weren\'t done (they go back to the company\'s due list).'];
            }
            $checkNames = array_map(static fn ($c) => (string) $c['exam_name'], $done);
        } else {
            $snap = json_decode((string) $current['checks_json'], true) ?: [];
            $checkNames = $snap['checks'] ?? [];
            $notDoneReason = '';
        }

        // Outcome.
        $outcome = (string) ($post['outcome'] ?? '');
        if (!isset(self::OUTCOMES[$outcome])) {
            return ['ok' => false, 'error' => 'Choose the fitness outcome.'];
        }
        $restrictions = array_values(array_intersect(array_keys(self::RESTRICTIONS), is_array($post['restrictions'] ?? null) ? $post['restrictions'] : []));
        $note = mb_substr(trim((string) ($post['restrictions_note'] ?? '')), 0, 500);
        if ($outcome === 'fit_restricted' && $restrictions === [] && $note === '') {
            return ['ok' => false, 'error' => 'Choose or write at least one restriction (in work terms, e.g. "No work at height").'];
        }
        if (in_array($outcome, ['fit', 'unfit'], true)) {
            $restrictions = [];
            $note = $outcome === 'unfit' ? $note : '';
        }
        $validTo = trim((string) ($post['valid_to'] ?? ''));
        $reviewBy = trim((string) ($post['review_by'] ?? ''));
        if (in_array($outcome, ['fit', 'fit_restricted'], true)) {
            if (!self::isYmd($validTo) || $validTo <= $today || $validTo > LocalDate::addDays($today, 366 * self::MAX_VALID_YEARS)) {
                return ['ok' => false, 'error' => 'Set "valid until" to a date after today (at most ' . self::MAX_VALID_YEARS . ' years).'];
            }
            $reviewBy = '';
        } elseif (in_array($outcome, ['temp_unfit', 'further_assessment'], true)) {
            if (!self::isYmd($reviewBy) || $reviewBy <= $today || $reviewBy > LocalDate::addDays($today, 366)) {
                return ['ok' => false, 'error' => 'Set the "review by" date (within a year).'];
            }
            $validTo = '';
        } else {
            $validTo = '';
            $reviewBy = '';
        }
        $consent = (string) ($post['consent'] ?? '');
        if (!in_array($consent, ['yes', 'no'], true)) {
            return ['ok' => false, 'error' => 'Record whether the employee agrees to share the outcome with the employer.'];
        }

        // Snapshot of everything printed: the SHA-256 is over this (C11).
        $doctor = self::doctor($clinicId, $userId);
        $now = LocalDate::nowUtc();
        $snapshot = [
            'checks' => array_values($checkNames),
            'person' => ['name' => (string) $booking['full_name'], 'employee_no' => (string) $booking['employee_no']],
            'company' => (string) $booking['company_name'],
            'issuer' => ['clinic' => (string) $booking['clinic_name'], 'clinic_address' => (string) ($booking['clinic_address'] ?? ''),
                'doctor' => (string) ($doctor['name'] ?? ''), 'qualification' => (string) ($doctor['qualification'] ?? '')],
        ];
        $doctorReg = $credential['reg_number'] . ' · ' . $credential['authority'];
        $token = bin2hex(random_bytes(24));
        $pdo = Database::connection();
        $companyId = (int) $booking['company_id'];
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO corp_fitness_certificates (company_id, employment_id, booking_id, clinic_id, doctor_id, visit_id, cert_no, exam_date, outcome,
                        restrictions_json, restrictions_note, valid_from, valid_to, review_by, checks_json, consent_shared, consent_by, consent_at,
                        status, verify_token, sha256, doctor_reg, signed_at, created_at, updated_at)
                 VALUES (:cid, :eid, :bid, :clinic, :doc, :visit, :tmp, :exam, :outcome, :rj, :rn, :vf, :vt, :rb, :snap, :cs, :cby, :cat,
                        'valid', :tok, :sha, :reg, :signed, :cr, :up)"
            )->execute([
                'cid' => $companyId, 'eid' => (int) $booking['employment_id'], 'bid' => (int) $booking['id'], 'clinic' => $clinicId, 'doc' => $userId,
                'visit' => (int) $visit['id'], 'tmp' => 'TMP-' . substr($token, 0, 20), 'exam' => $today, 'outcome' => $outcome,
                'rj' => $restrictions !== [] ? json_encode($restrictions) : null, 'rn' => $note !== '' ? $note : null,
                'vf' => $today, 'vt' => $validTo ?: null, 'rb' => $reviewBy ?: null, 'snap' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'cs' => $consent === 'yes' ? 1 : 0, 'cby' => $userId, 'cat' => $now, 'tok' => $token, 'sha' => str_repeat('0', 64),
                'reg' => mb_substr($doctorReg, 0, 220), 'signed' => $now, 'cr' => $now, 'up' => $now,
            ]);
            $certId = (int) $pdo->lastInsertId();
            $certNo = 'FC-' . strtoupper((string) ($booking['clinic_country'] ?: 'XX')) . '-' . date('ym') . '-' . str_pad((string) $certId, 6, '0', STR_PAD_LEFT);
            $row = self::certificate($clinicId, $certId);
            $row['cert_no'] = $certNo;
            $sha = self::hash($row);
            $pdo->prepare('UPDATE corp_fitness_certificates SET cert_no = :no, sha256 = :sha WHERE id = :id AND company_id = :cid')
                ->execute(['no' => $certNo, 'sha' => $sha, 'id' => $certId, 'cid' => $companyId]);

            if ($correcting) {
                $pdo->prepare("UPDATE corp_fitness_certificates SET status = 'replaced', replaced_by = :new, revoked_reason = :r, updated_at = :up
                                WHERE id = :id AND company_id = :cid AND status = 'valid'")
                    ->execute(['new' => $certId, 'r' => $replaceReason, 'up' => $now, 'id' => (int) $current['id'], 'cid' => $companyId]);
            } else {
                $complete = $pdo->prepare(
                    "UPDATE corp_screening_cases SET status = 'completed', completed_on = :d, completion_source = 'eclinicpro', updated_at = :up
                      WHERE id = :id AND company_id = :cid AND status = 'scheduled' AND booking_id = :b"
                );
                foreach ($done as $c) {
                    $complete->execute(['d' => $today, 'up' => $now, 'id' => (int) $c['id'], 'cid' => $companyId, 'b' => (int) $booking['id']]);
                    self::event($companyId, (int) $c['id'], 'completed', ['certificate' => $certNo], $userId, $now);
                }
                $back = $pdo->prepare(
                    "UPDATE corp_screening_cases SET status = 'not_scheduled', booking_id = 0, updated_at = :up
                      WHERE id = :id AND company_id = :cid AND status = 'scheduled' AND booking_id = :b"
                );
                foreach ($notDone as $c) {
                    $back->execute(['up' => $now, 'id' => (int) $c['id'], 'cid' => $companyId, 'b' => (int) $booking['id']]);
                    self::event($companyId, (int) $c['id'], 'not_done', ['reason' => $notDoneReason], $userId, $now);
                }
                $pdo->prepare("UPDATE corp_bookings SET status = 'attended', updated_at = :up WHERE id = :id AND company_id = :cid AND status IN ('booked', 'attended')")
                    ->execute(['up' => $now, 'id' => (int) $booking['id'], 'cid' => $companyId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[OccExamService] sign: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not issue the certificate. Please try again.'];
        }

        $cert = self::certificate($clinicId, $certId);
        if ($cert !== null) {
            $path = CertificatePdfService::generate($cert);
            if ($path !== null) {
                $pdo->prepare('UPDATE corp_fitness_certificates SET pdf_path = :p WHERE id = :id AND company_id = :cid')->execute(['p' => $path, 'id' => $certId, 'cid' => $companyId]);
            }
        }
        if (!$correcting) {
            ScreeningEngine::safeSyncEmployee($companyId, (int) $booking['employment_id']);   // next cycle from today
        }
        // No outcome in the company log: without consent the employer must not learn it from here (C9).
        CorpAudit::log($companyId, 'provider', $userId, $correcting ? 'certificate_replaced' : 'certificate_issued', 'corp_fitness_certificate', $certId,
            ['clinic' => $booking['clinic_name'], 'employee_no' => $booking['employee_no'], 'certificate' => $certNo] + ($correcting ? ['reason' => $replaceReason] : []));

        return ['ok' => true, 'id' => $certId];
    }

    /** Withdraw a certificate without a replacement (signing doctor or clinic admin). @return array{ok: bool, error?: string} */
    public static function revoke(int $clinicId, array $user, int $certId, string $reason): array
    {
        $c = self::certificate($clinicId, $certId);
        if ($c === null || $c['status'] !== 'valid') {
            return ['ok' => false, 'error' => 'That certificate is not valid any more.'];
        }
        if ((int) $c['doctor_id'] !== (int) ($user['id'] ?? 0) && !\App\Services\RoleAccessService::isClinicAdmin($user)) {
            return ['ok' => false, 'error' => 'Only the signing doctor or the clinic admin can revoke it.'];
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if (mb_strlen($reason) < self::MIN_REASON) {
            return ['ok' => false, 'error' => 'Write why it is revoked (shown on the verify page as "revoked", reason kept on record).'];
        }
        Database::connection()->prepare(
            "UPDATE corp_fitness_certificates SET status = 'revoked', revoked_reason = :r, updated_at = :up WHERE id = :id AND clinic_id = :clinic AND company_id = :cid AND status = 'valid'"
        )->execute(['r' => $reason, 'up' => LocalDate::nowUtc(), 'id' => $certId, 'clinic' => $clinicId, 'cid' => (int) $c['company_id']]);
        CorpAudit::log((int) $c['company_id'], 'provider', (int) ($user['id'] ?? 0), 'certificate_revoked', 'corp_fitness_certificate', $certId,
            ['clinic' => json_decode((string) $c['checks_json'], true)['issuer']['clinic'] ?? '', 'certificate' => $c['cert_no'], 'reason' => $reason]);

        return ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    public static function certificate(int $clinicId, int $certId): ?array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_fitness_certificates WHERE id = :id AND clinic_id = :clinic LIMIT 1');
        $st->execute(['id' => $certId, 'clinic' => $clinicId]);

        return $st->fetch() ?: null;
    }

    /**
     * SHA-256 over the printed fields (stable order). Re-computed by the verify page (1C-4).
     *
     * @param array<string, mixed> $c certificate row
     */
    public static function hash(array $c): string
    {
        $snap = json_decode((string) $c['checks_json'], true) ?: [];
        $data = [
            'cert_no' => (string) $c['cert_no'], 'exam_date' => (string) $c['exam_date'], 'outcome' => (string) $c['outcome'],
            'restrictions' => json_decode((string) ($c['restrictions_json'] ?? '[]'), true) ?: [], 'restrictions_note' => (string) ($c['restrictions_note'] ?? ''),
            'valid_from' => (string) $c['valid_from'], 'valid_to' => (string) ($c['valid_to'] ?? ''), 'review_by' => (string) ($c['review_by'] ?? ''),
            'doctor_reg' => (string) ($c['doctor_reg'] ?? ''), 'signed_at' => (string) $c['signed_at'], 'snapshot' => $snap,
        ];

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null the corporate booking behind this appointment */
    private static function booking(int $clinicId, int $appointmentId): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT b.*, e.full_name, e.employee_no, COALESCE(NULLIF(co.trade_name, ''), co.legal_name) AS company_name,
                    t.name AS clinic_name, t.address AS clinic_address, t.country_code AS clinic_country
               FROM corp_bookings b
               JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
               JOIN corp_companies co ON co.id = b.company_id
               JOIN tenants t ON t.id = b.clinic_id
              WHERE b.clinic_id = :clinic AND b.appointment_id = :appt /* cross-company: clinic sees its own bookings */
              ORDER BY b.id DESC LIMIT 1"
        );
        $st->execute(['clinic' => $clinicId, 'appt' => $appointmentId]);

        return $st->fetch() ?: null;
    }

    /** @return list<array<string, mixed>> */
    private static function cases(int $companyId, int $bookingId): array
    {
        $st = Database::connection()->prepare(
            'SELECT c.id, c.status, c.category, c.due_on, c.completed_on, et.name AS exam_name, et.kind, pi.frequency_months
               FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
               LEFT JOIN corp_programme_items pi ON pi.id = c.programme_item_id AND pi.company_id = c.company_id
              WHERE c.company_id = :cid AND c.booking_id = :b ORDER BY et.sort_order'
        );
        $st->execute(['cid' => $companyId, 'b' => $bookingId]);

        return $st->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    private static function certificates(int $companyId, int $clinicId, int $bookingId): array
    {
        $st = Database::connection()->prepare('SELECT * FROM corp_fitness_certificates WHERE company_id = :cid AND clinic_id = :clinic AND booking_id = :b ORDER BY id DESC');
        $st->execute(['cid' => $companyId, 'clinic' => $clinicId, 'b' => $bookingId]);

        return $st->fetchAll();
    }

    /** @return array<string, mixed> */
    private static function doctor(int $clinicId, int $userId): array
    {
        $st = Database::connection()->prepare('SELECT name, qualification FROM users WHERE id = :id AND clinic_id = :clinic');
        $st->execute(['id' => $userId, 'clinic' => $clinicId]);

        return $st->fetch() ?: [];
    }

    private static function event(int $companyId, int $caseId, string $event, array $detail, int $userId, string $now): void
    {
        Database::connection()->prepare(
            "INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, :ev, :d, 'provider', :uid, :at)"
        )->execute(['cid' => $companyId, 'case' => $caseId, 'ev' => $event, 'd' => json_encode($detail, JSON_UNESCAPED_UNICODE), 'uid' => $userId, 'at' => $now]);
    }

    private static function today(int $clinicId): string
    {
        return LocalDate::today(SlotService::clinicTimezone($clinicId));
    }

    private static function isYmd(string $d): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
    }
}
