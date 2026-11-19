<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Core\Database;
use App\Services\OccHealth\CorporateBookingBridge;
use App\Support\Locale\LocalDate;

/**
 * Employer-initiated bookings (design §4, C4–C6). HR picks an employee's due
 * checks, a clinic from the network that serves the employee's branch, a
 * doctor and a free slot; the clinic gets a normal appointment and the checks
 * become "scheduled". The company side reads only the appointment's date and
 * status (attendance), never anything clinical: the chart and appointment are
 * handled by OccHealth\CorporateBookingBridge.
 */
final class BookingService
{
    public const STATUSES = ['booked' => 'Booked', 'attended' => 'Attended', 'cancelled' => 'Cancelled', 'no_show' => 'Did not attend'];
    private const MAX_DAYS_AHEAD = 90;
    private const MIN_REASON = 5;

    /** Open, not-yet-booked checks for one employee. @return list<array<string, mixed>> */
    public static function openCases(int $companyId, int $employmentId): array
    {
        $st = Database::connection()->prepare(
            "SELECT c.id, c.exam_type_id, c.category, c.due_on, et.name AS exam_name, et.kind
               FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
              WHERE c.company_id = :cid AND c.employment_id = :eid AND c.status = 'not_scheduled'
              ORDER BY c.due_on, et.sort_order"
        );
        $st->execute(['cid' => $companyId, 'eid' => $employmentId]);

        return $st->fetchAll();
    }

    /**
     * Active clinics in the network that serve this branch, each with its bookable doctors and agreed prices.
     *
     * @return list<array<string, mixed>>
     */
    public static function clinicsFor(int $companyId, ?int $branchId): array
    {
        $out = [];
        foreach (ProviderService::links($companyId, 'active') as $l) {
            if ((int) $l['corporate_enabled'] !== 1) {
                continue;
            }
            if ($l['branch_list'] !== null && ($branchId === null || !in_array($branchId, $l['branch_list'], true))) {
                continue;
            }
            $prices = [];
            foreach (ProviderService::rates($companyId, (int) $l['id']) as $r) {
                $prices[(int) $r['exam_type_id']] = (float) $r['price'];
            }
            $out[] = [
                'link_id' => (int) $l['id'], 'clinic_id' => (int) $l['clinic_id'], 'name' => $l['name'], 'address' => $l['address'],
                'phone' => $l['phone'], 'doctors' => CorporateBookingBridge::doctors((int) $l['clinic_id']), 'prices' => $prices,
            ];
        }

        return $out;
    }

    /**
     * Free slots for the booking form (JSON). Validates the clinic is in the network.
     *
     * @return array{ok: bool, error?: string, slots?: list<array{time:string, datetime:string}>}
     */
    public static function slots(int $companyId, int $clinicId, int $doctorId, string $date): array
    {
        if (self::activeLink($companyId, $clinicId) === null) {
            return ['ok' => false, 'error' => 'That clinic is not in your network.'];
        }
        if (!in_array($doctorId, array_column(CorporateBookingBridge::doctors($clinicId), 'id'), true)) {
            return ['ok' => false, 'error' => 'Choose a doctor from the list.'];
        }
        $today = CorporateBookingBridge::today($clinicId);
        if (!EmployeeService::isYmd($date) || $date < $today || $date > LocalDate::addDays($today, self::MAX_DAYS_AHEAD)) {
            return ['ok' => false, 'error' => 'Choose a date in the next ' . self::MAX_DAYS_AHEAD . ' days.'];
        }

        return ['ok' => true, 'slots' => CorporateBookingBridge::slots($clinicId, $doctorId, $date)];
    }

    /**
     * Book (or re-book, when $replaceId > 0) checks for one employee.
     *
     * @param list<int> $caseIds
     * @param list<int>|null $scope branch scope of the user
     * @return array{ok: bool, error?: string, id?: int}
     */
    public static function book(int $companyId, int $userId, int $employmentId, array $caseIds, int $clinicId, int $doctorId, string $datetime, ?array $scope, int $replaceId = 0): array
    {
        $emp = EmployeeService::find($companyId, $employmentId, $scope);
        if ($emp === null) {
            return ['ok' => false, 'error' => 'Employee not found.'];
        }
        if ($emp['status'] === 'terminated') {
            return ['ok' => false, 'error' => 'This person has left the company.'];
        }
        $old = null;
        if ($replaceId > 0) {
            $old = self::find($companyId, $replaceId, $scope);
            if ($old === null || $old['status'] !== 'booked' || (int) $old['employment_id'] !== $employmentId) {
                return ['ok' => false, 'error' => 'That booking can no longer be changed.'];
            }
        }
        // The checks: open ones for this person (+ the ones on the booking being moved).
        $caseIds = array_values(array_unique(array_filter(array_map('intval', $caseIds))));
        $allowed = [];
        foreach (self::openCases($companyId, $employmentId) as $c) {
            $allowed[(int) $c['id']] = $c['exam_name'];
        }
        if ($old !== null) {
            foreach (self::casesOf($companyId, $replaceId) as $c) {
                $allowed[(int) $c['id']] = $c['exam_name'];
            }
        }
        if ($caseIds === []) {
            return ['ok' => false, 'error' => 'Tick at least one check to book.'];
        }
        foreach ($caseIds as $id) {
            if (!isset($allowed[$id])) {
                return ['ok' => false, 'error' => 'One of the checks was booked or closed meanwhile. Please reload.'];
            }
        }
        $link = self::activeLink($companyId, $clinicId);
        if ($link === null) {
            return ['ok' => false, 'error' => 'That clinic is not in your network.'];
        }
        $branches = ProviderService::branchIds($link);
        if ($branches !== null && !in_array((int) $emp['branch_id'], $branches, true)) {
            return ['ok' => false, 'error' => 'That clinic doesn\'t serve this person\'s branch.'];
        }
        if (!in_array($doctorId, array_column(CorporateBookingBridge::doctors($clinicId), 'id'), true)) {
            return ['ok' => false, 'error' => 'That doctor can\'t take corporate checks at the moment. Choose another.'];
        }
        $date = substr($datetime, 0, 10);
        $free = self::slots($companyId, $clinicId, $doctorId, $date);
        if (!$free['ok'] || !in_array($datetime, array_column($free['slots'] ?? [], 'datetime'), true)) {
            return ['ok' => false, 'error' => $free['error'] ?? 'That time was just taken. Please pick another slot.'];
        }

        $names = array_map(static fn (int $id) => $allowed[$id], $caseIds);
        try {
            $made = CorporateBookingBridge::book($clinicId, $doctorId, $datetime, [
                'name' => (string) $emp['full_name'], 'phone' => $emp['work_phone'], 'email' => $emp['work_email'],
                'dob' => $emp['dob'], 'gender' => $emp['gender'], 'identity_id' => $emp['identity_id'] !== null ? (int) $emp['identity_id'] : null,
            ], self::companyName($companyId), $names);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            error_log('[BookingService] book: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'The clinic could not take the booking just now. Please try again.'];
        }

        $pdo = Database::connection();
        $now = LocalDate::nowUtc();
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO corp_bookings (company_id, employment_id, clinic_id, doctor_id, patient_id, appointment_id, scheduled_at, status, booked_by, created_at, updated_at)
                 VALUES (:cid, :eid, :clinic, :doc, :pat, :appt, :at, 'booked', :by, :cr, :up)"
            )->execute(['cid' => $companyId, 'eid' => $employmentId, 'clinic' => $clinicId, 'doc' => $doctorId, 'pat' => $made['patient_id'],
                'appt' => $made['appointment_id'], 'at' => $datetime, 'by' => $userId, 'cr' => $now, 'up' => $now]);
            $bookingId = (int) $pdo->lastInsertId();
            if ($old !== null) {
                self::release($companyId, $replaceId, 'cancelled', 'Moved to a new time', $userId, $now);
            }
            $upd = $pdo->prepare(
                "UPDATE corp_screening_cases SET status = 'scheduled', booking_id = :b, updated_at = :up
                  WHERE id = :id AND company_id = :cid AND status = 'not_scheduled'"
            );
            foreach ($caseIds as $id) {
                $upd->execute(['b' => $bookingId, 'up' => $now, 'id' => $id, 'cid' => $companyId]);
                if ($upd->rowCount() !== 1) {
                    throw new \RuntimeException('case ' . $id . ' changed');
                }
                self::event($companyId, $id, 'scheduled', ['booking_id' => $bookingId, 'clinic' => $link['name'], 'at' => $datetime], 'corp_user', $userId, $now);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[BookingService] book (records): ' . $e->getMessage());
            self::safeCancelAppointment($clinicId, $made['appointment_id']);

            return ['ok' => false, 'error' => 'One of the checks was booked or closed meanwhile. Please reload and try again.'];
        }
        if ($old !== null) {
            self::safeCancelAppointment((int) $old['clinic_id'], (int) $old['appointment_id']);
        }
        CorpAudit::log($companyId, 'corp_user', $userId, $old !== null ? 'booking_moved' : 'booking_created', 'corp_booking', $bookingId,
            ['employee_no' => $emp['employee_no'], 'clinic' => $link['name'], 'at' => $datetime, 'checks' => implode(', ', $names)]);
        self::emailEmployee($emp, $link, $datetime, $names, $old !== null);

        return ['ok' => true, 'id' => $bookingId];
    }

    /**
     * HR cancels. The clinic appointment is cancelled too; the checks go back to "not booked".
     *
     * @param list<int>|null $scope
     * @return array{ok: bool, error?: string}
     */
    public static function cancel(int $companyId, int $userId, int $bookingId, string $reason, ?array $scope): array
    {
        $b = self::find($companyId, $bookingId, $scope);
        if ($b === null || $b['status'] !== 'booked') {
            return ['ok' => false, 'error' => 'That booking can no longer be cancelled.'];
        }
        $reason = mb_substr(trim($reason), 0, 500);
        if (mb_strlen($reason) < self::MIN_REASON) {
            return ['ok' => false, 'error' => 'Write why it is cancelled (the clinic sees the cancellation).'];
        }
        $pdo = Database::connection();
        $now = LocalDate::nowUtc();
        $pdo->beginTransaction();
        try {
            self::release($companyId, $bookingId, 'cancelled', $reason, $userId, $now);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[BookingService] cancel: ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Could not cancel just now. Please try again.'];
        }
        self::safeCancelAppointment((int) $b['clinic_id'], (int) $b['appointment_id']);
        CorpAudit::log($companyId, 'corp_user', $userId, 'booking_cancelled', 'corp_booking', $bookingId,
            ['employee_no' => $b['employee_no'], 'clinic' => $b['clinic_name'], 'reason' => $reason]);

        return ['ok' => true];
    }

    // ------------------------------------------------------------------
    // Following the clinic (C6): cancelled / no-show / completed at the clinic
    // ------------------------------------------------------------------

    /** Hook from AppointmentService after a status change. Never throws. */
    public static function safeSyncFromAppointment(int $appointmentId): void
    {
        try {
            $st = Database::connection()->prepare("SELECT company_id, id FROM corp_bookings WHERE appointment_id = :aid AND status = 'booked'");
            $st->execute(['aid' => $appointmentId]);
            foreach ($st->fetchAll() as $b) {
                self::syncCompany((int) $b['company_id'], (int) $b['id']);
            }
        } catch (\Throwable $e) {
            error_log('[BookingService] sync appointment ' . $appointmentId . ': ' . $e->getMessage());
        }
    }

    /**
     * Apply clinic-side outcomes to open bookings (nightly safety net + hook).
     *
     * Also copies a time / doctor change made at the clinic.
     *
     * @return array{cancelled:int, no_show:int, attended:int}
     */
    public static function syncCompany(int $companyId, int $onlyBookingId = 0): array
    {
        $count = ['cancelled' => 0, 'no_show' => 0, 'attended' => 0];
        // The clinic moved the time or changed the doctor: copy it over.
        $moved = "UPDATE corp_bookings b JOIN appointments a ON a.id = b.appointment_id AND a.clinic_id = b.clinic_id
                     SET b.scheduled_at = a.scheduled_at, b.doctor_id = a.doctor_id, b.updated_at = :up
                   WHERE b.company_id = :cid AND b.status = 'booked' AND a.status NOT IN ('cancelled', 'no_show')
                     AND (a.scheduled_at <> b.scheduled_at OR a.doctor_id <> b.doctor_id)";
        $mp = ['up' => LocalDate::nowUtc(), 'cid' => $companyId];
        if ($onlyBookingId > 0) {
            $moved .= ' AND b.id = :bid';
            $mp['bid'] = $onlyBookingId;
        }
        Database::connection()->prepare($moved)->execute($mp);
        $sql = "SELECT b.id, b.employment_id, a.status AS appt_status
                  FROM corp_bookings b JOIN appointments a ON a.id = b.appointment_id AND a.clinic_id = b.clinic_id
                 WHERE b.company_id = :cid AND b.status = 'booked' AND a.status IN ('cancelled', 'no_show', 'completed')";
        $params = ['cid' => $companyId];
        if ($onlyBookingId > 0) {
            $sql .= ' AND b.id = :bid';
            $params['bid'] = $onlyBookingId;
        }
        $st = Database::connection()->prepare($sql);
        $st->execute($params);
        $pdo = Database::connection();
        foreach ($st->fetchAll() as $r) {
            $now = LocalDate::nowUtc();
            $pdo->beginTransaction();
            try {
                if ($r['appt_status'] === 'completed') {
                    $pdo->prepare("UPDATE corp_bookings SET status = 'attended', updated_at = :up WHERE id = :id AND company_id = :cid AND status = 'booked'")
                        ->execute(['up' => $now, 'id' => (int) $r['id'], 'cid' => $companyId]);
                    $count['attended']++;
                } else {
                    $status = $r['appt_status'] === 'no_show' ? 'no_show' : 'cancelled';
                    self::release($companyId, (int) $r['id'], $status, $status === 'no_show' ? 'Did not attend (marked by the clinic)' : 'Cancelled by the clinic', 0, $now);
                    $count[$status]++;
                    CorpAudit::log($companyId, 'provider', null, $status === 'no_show' ? 'booking_no_show' : 'booking_cancelled_by_clinic', 'corp_booking', (int) $r['id'], null);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                error_log('[BookingService] sync booking ' . $r['id'] . ': ' . $e->getMessage());
            }
        }

        return $count;
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * @param array{view?: string, q?: string} $f view = upcoming|past|cancelled
     * @param list<int>|null $scope
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public static function search(int $companyId, array $f, ?array $scope, int $page = 1): array
    {
        $where = ['b.company_id = :cid'];
        $params = ['cid' => $companyId];
        $view = (string) ($f['view'] ?? 'upcoming');
        $where[] = match ($view) {
            'past' => "b.status IN ('attended', 'no_show')",
            'cancelled' => "b.status = 'cancelled'",
            default => "b.status = 'booked'",
        };
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 80)) . '%';
            $where[] = '(e.full_name LIKE :q1 OR e.employee_no LIKE :q2 OR t.name LIKE :q3)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if ($scope !== null) {
            if ($scope === []) {
                return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
            }
            $in = [];
            foreach (array_values($scope) as $i => $bid) {
                $in[] = ':sb' . $i;
                $params['sb' . $i] = $bid;
            }
            $where[] = 'e.branch_id IN (' . implode(',', $in) . ')';
        }
        $from = ' FROM corp_bookings b JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
                  JOIN tenants t ON t.id = b.clinic_id LEFT JOIN users u ON u.id = b.doctor_id AND u.clinic_id = b.clinic_id
                 WHERE ' . implode(' AND ', $where);
        $pdo = Database::connection();
        $c = $pdo->prepare('SELECT COUNT(*)' . $from);
        $c->execute($params);
        $total = (int) $c->fetchColumn();
        $per = 50;
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($page, $pages));
        $st = $pdo->prepare(
            'SELECT b.*, e.full_name, e.employee_no, e.branch_id, t.name AS clinic_name, t.address AS clinic_address, u.name AS doctor_name,
                    (SELECT GROUP_CONCAT(et.name ORDER BY et.sort_order SEPARATOR \', \') FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
                      WHERE c.company_id = b.company_id AND c.booking_id = b.id) AS checks'
            . $from . ' ORDER BY b.scheduled_at ' . ($view === 'upcoming' ? 'ASC' : 'DESC') . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)
        );
        $st->execute($params);

        return ['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array<string, mixed>> all bookings of one employee, newest first */
    public static function forEmployee(int $companyId, int $employmentId): array
    {
        $st = Database::connection()->prepare(
            "SELECT b.id, b.status, b.scheduled_at, b.cancelled_reason, t.name AS clinic_name, u.name AS doctor_name,
                    (SELECT GROUP_CONCAT(et.name ORDER BY et.sort_order SEPARATOR ', ') FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
                      WHERE c.company_id = b.company_id AND c.booking_id = b.id) AS checks
               FROM corp_bookings b JOIN tenants t ON t.id = b.clinic_id LEFT JOIN users u ON u.id = b.doctor_id AND u.clinic_id = b.clinic_id
              WHERE b.company_id = :cid AND b.employment_id = :eid ORDER BY b.scheduled_at DESC LIMIT 20"
        );
        $st->execute(['cid' => $companyId, 'eid' => $employmentId]);

        return $st->fetchAll();
    }

    /**
     * @param list<int>|null $scope
     * @return array<string, mixed>|null
     */
    public static function find(int $companyId, int $bookingId, ?array $scope): ?array
    {
        $st = Database::connection()->prepare(
            'SELECT b.*, e.full_name, e.employee_no, e.branch_id, t.name AS clinic_name, t.address AS clinic_address, u.name AS doctor_name
               FROM corp_bookings b JOIN corp_employments e ON e.id = b.employment_id AND e.company_id = b.company_id
               JOIN tenants t ON t.id = b.clinic_id LEFT JOIN users u ON u.id = b.doctor_id AND u.clinic_id = b.clinic_id
              WHERE b.id = :id AND b.company_id = :cid LIMIT 1'
        );
        $st->execute(['id' => $bookingId, 'cid' => $companyId]);
        $b = $st->fetch();
        if (!$b || !EmployeeService::inScope($b['branch_id'] !== null ? (int) $b['branch_id'] : null, $scope)) {
            return null;
        }

        return $b;
    }

    /** @return list<array<string, mixed>> the checks on one booking */
    public static function casesOf(int $companyId, int $bookingId): array
    {
        $st = Database::connection()->prepare(
            'SELECT c.id, c.exam_type_id, c.category, c.due_on, c.status, et.name AS exam_name, et.kind
               FROM corp_screening_cases c JOIN oh_exam_types et ON et.id = c.exam_type_id
              WHERE c.company_id = :cid AND c.booking_id = :b ORDER BY et.sort_order'
        );
        $st->execute(['cid' => $companyId, 'b' => $bookingId]);

        return $st->fetchAll();
    }

    // ------------------------------------------------------------------

    /** Close a booking and put its still-scheduled checks back to "not booked". Caller holds the transaction. */
    private static function release(int $companyId, int $bookingId, string $status, string $reason, int $userId, string $now): void
    {
        $pdo = Database::connection();
        $pdo->prepare("UPDATE corp_bookings SET status = :st, cancelled_reason = :r, updated_at = :up WHERE id = :id AND company_id = :cid AND status = 'booked'")
            ->execute(['st' => $status, 'r' => $reason, 'up' => $now, 'id' => $bookingId, 'cid' => $companyId]);
        $ids = $pdo->prepare("SELECT id FROM corp_screening_cases WHERE company_id = :cid AND booking_id = :b AND status = 'scheduled'");
        $ids->execute(['cid' => $companyId, 'b' => $bookingId]);
        $back = $pdo->prepare(
            "UPDATE corp_screening_cases SET status = 'not_scheduled', booking_id = 0, updated_at = :up WHERE id = :id AND company_id = :cid AND status = 'scheduled'"
        );
        foreach (array_map('intval', $ids->fetchAll(\PDO::FETCH_COLUMN)) as $caseId) {
            $back->execute(['up' => $now, 'id' => $caseId, 'cid' => $companyId]);
            self::event($companyId, $caseId, $status === 'no_show' ? 'no_show' : 'booking_cancelled', ['booking_id' => $bookingId, 'reason' => $reason],
                $userId > 0 ? 'corp_user' : 'provider', $userId > 0 ? $userId : null, $now);
        }
    }

    /** @return array<string, mixed>|null */
    private static function activeLink(int $companyId, int $clinicId): ?array
    {
        $st = Database::connection()->prepare(
            "SELECT l.*, t.name, t.address, t.phone FROM corp_provider_links l JOIN tenants t ON t.id = l.clinic_id
              WHERE l.company_id = :cid AND l.clinic_id = :clinic AND l.status = 'active' AND t.corporate_enabled = 1 AND t.is_active = 1 LIMIT 1"
        );
        $st->execute(['cid' => $companyId, 'clinic' => $clinicId]);

        return $st->fetch() ?: null;
    }

    private static function safeCancelAppointment(int $clinicId, int $appointmentId): void
    {
        try {
            CorporateBookingBridge::cancel($clinicId, $appointmentId);
        } catch (\Throwable $e) {
            error_log('[BookingService] cancel appointment ' . $appointmentId . ': ' . $e->getMessage());
        }
    }

    private static function companyName(int $companyId): string
    {
        $st = Database::connection()->prepare('SELECT legal_name, trade_name FROM corp_companies WHERE id = :cid');
        $st->execute(['cid' => $companyId]);
        $c = $st->fetch() ?: [];

        return (string) (($c['trade_name'] ?? '') ?: ($c['legal_name'] ?? 'Employer'));
    }

    private static function event(int $companyId, int $caseId, string $event, array $detail, string $actorType, ?int $actorId, string $now): void
    {
        Database::connection()->prepare(
            'INSERT INTO corp_case_events (company_id, case_id, event, detail_json, actor_type, actor_id, created_at)
             VALUES (:cid, :case, :ev, :d, :at_type, :aid, :at)'
        )->execute(['cid' => $companyId, 'case' => $caseId, 'ev' => $event, 'd' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'at_type' => $actorType, 'aid' => $actorId, 'at' => $now]);
    }

    /** @param array<string, mixed> $emp @param array<string, mixed> $link @param list<string> $checks */
    private static function emailEmployee(array $emp, array $link, string $datetime, array $checks, bool $moved): void
    {
        if (empty($emp['work_email'])) {
            return;
        }
        $when = date('l, d M Y \a\t h:i A', strtotime($datetime));
        CorpMailer::send((string) $emp['work_email'], ($moved ? 'Your health check has moved: ' : 'Your health check is booked: ') . date('d M Y', strtotime($datetime)),
            $moved ? 'New time for your health check' : 'Health check booked',
            [
                'Dear ' . $emp['full_name'] . ', your employer ' . self::companyName((int) $emp['company_id']) . ' has booked a health check for you.',
                'When: ' . $when . ' (clinic\'s local time)',
                'Where: ' . $link['name'] . (!empty($link['address']) ? ', ' . $link['address'] : '') . (!empty($link['phone']) ? ' · ' . $link['phone'] : ''),
                'Checks: ' . implode(', ', $checks),
                'Please bring a photo ID and arrive 10 minutes early. Your medical details stay with the clinic; your employer only receives the fitness outcome.',
            ]);
    }
}
