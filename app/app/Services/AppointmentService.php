<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class AppointmentService
{
    public static function find(int $clinicId, int $id): ?array
    {
        $row = QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->first();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function findDetailed(int $clinicId, int $id): ?array
    {
        if (!Database::ping()) {
            return null;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT a.*, p.name AS patient_name, p.phone AS patient_phone, p.uhid,
                    u.name AS doctor_name
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             INNER JOIN users u ON u.id = a.doctor_id
             WHERE a.clinic_id = ? AND a.id = ?',
        );
        $stmt->execute([$clinicId, $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function create(int $clinicId, array $data): array
    {
        $doctorId = (int) $data['doctor_id'];
        $scheduledAt = (string) $data['scheduled_at'];
        $type = (string) ($data['type'] ?? 'prebooked');

        $scheduledTs = strtotime($scheduledAt);
        if ($scheduledTs === false) {
            throw new \RuntimeException('Invalid appointment date or time.');
        }

        // Reject appointments in the past, measured in the clinic's local time
        // (IST by default). The slot picker already hides past slots for today,
        // but walk-ins, manual time entry, no-working-hours days, and direct
        // POSTs bypass that — so this is the authoritative server-side guard.
        self::assertNotInPast($clinicId, $scheduledAt);

        $scheduledDate = date('Y-m-d', $scheduledTs);

        $source = (string) ($data['source'] ?? 'reception');

        // Serialize check + insert per slot: without the lock, two concurrent
        // bookings both pass the availability check and double-book (TOCTOU).
        // Migration 022's unique slot_key is the DB-level backstop.
        $pdo = Database::connection();
        $lockName = null;
        if ($type !== 'walkin') {
            $lockName = "slot:{$clinicId}:{$doctorId}:{$scheduledAt}";
            $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 3) AS got');
            $lockStmt->execute([$lockName]);
            if ((int) ($lockStmt->fetch()['got'] ?? 0) !== 1) {
                throw new \RuntimeException('This slot is being booked by someone else right now — please try again.');
            }
        }

        try {
            if ($type !== 'walkin') {
                // Staff may book extended hours; public website booking may not.
                $slots = SlotService::available($clinicId, $doctorId, $scheduledDate, $source !== 'website');

                if ($slots === [] && $source !== 'website') {
                    // No working hours configured for this day — the form falls
                    // back to a manual time input; don't block reception.
                } else {
                    $slotOk = false;
                    foreach ($slots as $slot) {
                        if ($slot['datetime'] === $scheduledAt && $slot['available']) {
                            $slotOk = true;
                            break;
                        }
                    }
                    if (!$slotOk) {
                        throw new \RuntimeException('Selected slot is no longer available.');
                    }
                }
            }

            $tokenNumber = null;
            if ($type === 'walkin' && $scheduledDate === date('Y-m-d')) {
                $tokenNumber = self::nextTokenNumber($clinicId);
            }

            $user = RequestContext::user();
            try {
                $id = QueryBuilder::table('appointments')->insert([
                    'clinic_id' => $clinicId,
                    'patient_id' => (int) $data['patient_id'],
                    'doctor_id' => $doctorId,
                    'scheduled_at' => $scheduledAt,
                    'slot_duration' => (int) ($data['slot_duration'] ?? 15),
                    'type' => in_array($type, ['walkin', 'prebooked', 'online', 'followup'], true) ? $type : 'prebooked',
                    'source' => $source,
                    'status' => $data['status'] ?? 'scheduled',
                    'chief_complaint' => trim((string) ($data['chief_complaint'] ?? '')) ?: null,
                    'token_number' => $tokenNumber,
                    'is_followup' => !empty($data['is_followup']) ? 1 : 0,
                    'parent_visit_id' => !empty($data['parent_visit_id']) ? (int) $data['parent_visit_id'] : null,
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                    'created_by' => $user['id'] ?? null,
                ]);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'uq_appt_slot')) {
                    throw new \RuntimeException('Selected slot was just booked by someone else. Please pick another slot.');
                }
                throw $e;
            }
        } finally {
            if ($lockName !== null) {
                $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
            }
        }

        SlotService::invalidateForAppointment($clinicId, $doctorId, $scheduledAt);
        DashboardService::invalidateStats($clinicId);

        $appointment = self::findDetailed($clinicId, $id);
        if ($appointment !== null) {
            $patient = PatientService::find($clinicId, (int) $appointment['patient_id']);
            $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
            if ($patient !== null && $clinic !== null) {
                NotificationService::queueAppointmentReminder($appointment, $patient, $clinic, 24);
                NotificationService::queueAppointmentReminder($appointment, $patient, $clinic, 1);
            }
            EventBus::fire('appointment.booked', [
                'appointment_id' => $id,
                'patient_id' => (int) $data['patient_id'],
            ], 'appointments', $id);

            TelemedicineService::applyToAppointment($clinicId, $appointment);
        }

        return $appointment ?? [];
    }

    /** @param array<string, mixed> $data */
    public static function update(int $clinicId, int $id, array $data): array
    {
        $existing = self::find($clinicId, $id);
        if ($existing === null) {
            throw new \RuntimeException('Appointment not found');
        }

        $update = [];
        foreach (['scheduled_at', 'doctor_id', 'chief_complaint', 'notes', 'type', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        // Reschedules / doctor moves never re-checked the target slot, so an
        // edit could double-book even without a race. Walk-ins may share times.
        if (isset($update['scheduled_at']) || isset($update['doctor_id'])) {
            $targetDoctor = (int) ($update['doctor_id'] ?? $existing['doctor_id']);
            $targetAt = (string) ($update['scheduled_at'] ?? $existing['scheduled_at']);
            $targetType = (string) ($update['type'] ?? $existing['type'] ?? 'prebooked');
            $changed = $targetAt !== (string) $existing['scheduled_at']
                || $targetDoctor !== (int) $existing['doctor_id'];

            // Block rescheduling to a past date/time (clinic-local). Only when the
            // time actually moves — editing notes on a past appointment is fine.
            if (isset($update['scheduled_at']) && $targetAt !== (string) $existing['scheduled_at']) {
                if (strtotime($targetAt) === false) {
                    throw new \RuntimeException('Invalid appointment date or time.');
                }
                self::assertNotInPast($clinicId, $targetAt);
            }

            if ($changed && $targetType !== 'walkin') {
                $stmt = Database::connection()->prepare(
                    "SELECT id FROM appointments
                     WHERE clinic_id = ? AND doctor_id = ? AND scheduled_at = ?
                     AND status NOT IN ('cancelled', 'no_show') AND id != ?
                     LIMIT 1",
                );
                $stmt->execute([$clinicId, $targetDoctor, $targetAt, $id]);
                if ($stmt->fetch()) {
                    throw new \RuntimeException('That slot is already booked for the selected doctor. Please pick another time.');
                }
            }
        }

        if ($update !== []) {
            try {
                QueryBuilder::table('appointments')
                    ->forClinic($clinicId)
                    ->where('id', '=', $id)
                    ->update($update);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'uq_appt_slot')) {
                    throw new \RuntimeException('That slot is already booked for the selected doctor. Please pick another time.');
                }
                throw $e;
            }

            if (($update['status'] ?? '') === 'confirmed') {
                TelemedicineService::onConfirmed($clinicId, $id);
            }

            SlotService::invalidateForAppointment($clinicId, (int) $existing['doctor_id'], $existing['scheduled_at']);
            if (isset($update['doctor_id']) || isset($update['scheduled_at'])) {
                $doc = (int) ($update['doctor_id'] ?? $existing['doctor_id']);
                $at = (string) ($update['scheduled_at'] ?? $existing['scheduled_at']);
                SlotService::invalidateForAppointment($clinicId, $doc, $at);
            }
        }

        return self::findDetailed($clinicId, $id) ?? [];
    }

    public static function cancel(int $clinicId, int $id): array
    {
        $existing = self::findDetailed($clinicId, $id);
        if ($existing === null) {
            throw new \RuntimeException('Appointment not found');
        }

        QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->update(['status' => 'cancelled']);

        SlotService::invalidateForAppointment($clinicId, (int) $existing['doctor_id'], $existing['scheduled_at']);
        DashboardService::invalidateStats($clinicId);

        $patient = PatientService::find($clinicId, (int) $existing['patient_id']);
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($patient !== null && $clinic !== null) {
            NotificationService::queueCancellationNotice($existing, $patient, $clinic);
        }

        EventBus::fire('appointment.cancelled', ['appointment_id' => $id], 'appointments', $id);

        return self::findDetailed($clinicId, $id) ?? [];
    }

    public static function updateStatus(int $clinicId, int $id, string $status): array
    {
        $allowed = ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $existing = self::find($clinicId, $id);
        if ($existing === null) {
            throw new \RuntimeException('Appointment not found');
        }

        $update = ['status' => $status];

        // Stamp the flow timestamps the FIRST time each milestone is reached
        // (don't overwrite on a re-entry). These feed the In/Out columns on the
        // appointments listing — confirmed → "In Clinic" (arrived_at),
        // in_progress → "In" (consult_started_at), completed → "Out". See
        // migration 041_appointment_flow_times.sql.
        //
        // GUARD: only write a column if it actually exists on the row (i.e. the
        // migration has run). array_key_exists is true even when the value is
        // NULL, but false when the column is absent — so the status change keeps
        // working on a DB where 041 hasn't been applied yet (no "unknown column"
        // error, which previously surfaced as a JSON 500 on Arrived/etc.).
        $now = date('Y-m-d H:i:s');
        if ($status === 'confirmed' && array_key_exists('arrived_at', $existing) && empty($existing['arrived_at'])) {
            $update['arrived_at'] = $now;
        }
        if ($status === 'in_progress' && array_key_exists('consult_started_at', $existing) && empty($existing['consult_started_at'])) {
            $update['consult_started_at'] = $now;
        }
        if ($status === 'completed' && array_key_exists('completed_at', $existing) && empty($existing['completed_at'])) {
            $update['completed_at'] = $now;
        }

        // Pre-booked patients have no token until they reach the chair. Assign
        // one when the consultation starts so the waiting-room display stays
        // coherent for every appointment type, not just walk-ins.
        if ($status === 'in_progress'
            && empty($existing['token_number'])
            && date('Y-m-d', strtotime((string) $existing['scheduled_at'])) === date('Y-m-d')) {
            $update['token_number'] = self::nextTokenNumber($clinicId);
        }

        QueryBuilder::table('appointments')
            ->forClinic($clinicId)
            ->where('id', '=', $id)
            ->update($update);

        DashboardService::invalidateStats($clinicId);

        return self::findDetailed($clinicId, $id) ?? [];
    }

    /**
     * One-click "call next patient": completes the doctor's current
     * in-consultation appointment (if any) and moves the next waiting
     * patient — tokens first, then earliest slot — to in_progress.
     *
     * @return array<string, mixed>|null the appointment now being served
     */
    public static function callNext(int $clinicId, ?int $doctorId = null): ?array
    {
        $queue = self::todayQueue($clinicId, $doctorId);

        $next = null;
        foreach ($queue as $row) {
            if (in_array($row['status'] ?? '', ['scheduled', 'confirmed'], true)) {
                $next = $row;
                break;
            }
        }
        if ($next === null) {
            return null;
        }

        // Close out only the called doctor's current consultation. With "All
        // doctors" selected, another doctor's in-progress patient is left alone.
        $targetDoctor = (int) $next['doctor_id'];
        foreach ($queue as $row) {
            if (($row['status'] ?? '') === 'in_progress' && (int) $row['doctor_id'] === $targetDoctor) {
                self::updateStatus($clinicId, (int) $row['id'], 'completed');
            }
        }

        return self::updateStatus($clinicId, (int) $next['id'], 'in_progress');
    }

    /** @return list<array<string, mixed>> */
    /**
     * Fetch all appointments scheduled on a specific date (any status).
     * @return list<array<string, mixed>>
     */
    public static function forDate(int $clinicId, string $date, ?int $doctorId = null): array
    {
        if (!Database::ping()) {
            return [];
        }

        $sql = 'SELECT a.*, p.name AS patient_name, p.uhid, p.phone AS patient_phone,
                       u.name AS doctor_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN users u ON u.id = a.doctor_id
                WHERE a.clinic_id = ? AND a.scheduled_at >= ? AND a.scheduled_at < ?';
        $params = [$clinicId, $date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00'];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        $sql .= ' ORDER BY a.scheduled_at ASC, a.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Appointments across a date range (inclusive), for the week view.
     * @return list<array<string, mixed>>
     */
    public static function forRange(int $clinicId, string $startDate, string $endDate, ?int $doctorId = null): array
    {
        if (!Database::ping()) {
            return [];
        }

        $sql = 'SELECT a.*, p.name AS patient_name, p.uhid, p.phone AS patient_phone,
                       u.name AS doctor_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN users u ON u.id = a.doctor_id
                WHERE a.clinic_id = ? AND a.scheduled_at >= ? AND a.scheduled_at < ?';
        $params = [
            $clinicId,
            $startDate . ' 00:00:00',
            date('Y-m-d', strtotime($endDate . ' +1 day')) . ' 00:00:00',
        ];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        $sql .= ' ORDER BY a.scheduled_at ASC, a.id ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    public static function todayQueue(int $clinicId, ?int $doctorId = null): array
    {
        if (!Database::ping()) {
            return [];
        }

        $today = date('Y-m-d');
        $sql = 'SELECT a.*, p.name AS patient_name, p.uhid, p.phone AS patient_phone,
                       u.name AS doctor_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN users u ON u.id = a.doctor_id
                WHERE a.clinic_id = ? AND a.scheduled_at >= ? AND a.scheduled_at < ?
                AND a.status NOT IN (\'cancelled\')';
        $params = [$clinicId, $today . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00'];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }
        $sql .= ' ORDER BY a.token_number IS NULL, a.token_number ASC, a.scheduled_at ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function calendarEvents(int $clinicId, string $start, string $end, ?int $doctorId = null): array
    {
        if (!Database::ping()) {
            return [];
        }

        $sql = 'SELECT a.id, a.scheduled_at, a.status, a.type, p.name AS patient_name, u.name AS doctor_name
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                INNER JOIN users u ON u.id = a.doctor_id
                WHERE a.clinic_id = ? AND a.scheduled_at >= ? AND a.scheduled_at < ?
                AND a.status != \'cancelled\'';
        $params = [$clinicId, $start, $end];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $colors = [
            'scheduled' => '#94a3b8',
            'confirmed' => '#3b82f6',
            'in_progress' => '#f59e0b',
            'completed' => '#10b981',
            'no_show' => '#ef4444',
        ];

        return array_map(static function (array $row) use ($colors) {
            $end = date('Y-m-d\TH:i:s', strtotime($row['scheduled_at'] . ' +15 minutes'));

            return [
                'id' => $row['id'],
                'title' => $row['patient_name'] . ' — ' . $row['doctor_name'],
                'start' => date('Y-m-d\TH:i:s', strtotime($row['scheduled_at'])),
                'end' => $end,
                'backgroundColor' => $colors[$row['status']] ?? '#64748b',
                'url' => '/appointments/' . $row['id'] . '/edit',
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    public static function doctorsForClinic(int $clinicId): array
    {
        $doctors = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '=', 'doctor')
            ->where('is_active', '=', 1)
            ->get();

        if ($doctors !== []) {
            return $doctors;
        }

        $owner = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_owner', '=', 1)
            ->where('is_active', '=', 1)
            ->first();

        return $owner !== null ? [$owner] : [];
    }

    private static function nextTokenNumber(int $clinicId): int
    {
        $pdo = Database::connection();

        // Atomic claim via the per-clinic per-day counter row: two concurrent
        // bookings can never receive the same token. LAST_INSERT_ID(expr)
        // makes the claimed value readable on this connection.
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO appointment_token_counters (clinic_id, token_date, last_token)
                 VALUES (?, CURDATE(), LAST_INSERT_ID(1))
                 ON DUPLICATE KEY UPDATE last_token = LAST_INSERT_ID(last_token + 1)',
            );
            $stmt->execute([$clinicId]);

            return (int) $pdo->lastInsertId();
        } catch (\Throwable $e) {
            // Counter table not migrated yet (migration 020) — legacy MAX+1.
            $stmt = $pdo->prepare(
                'SELECT COALESCE(MAX(token_number), 0) + 1 AS n FROM appointments
                 WHERE clinic_id = ? AND scheduled_at >= CURDATE()
                 AND scheduled_at < CURDATE() + INTERVAL 1 DAY AND token_number IS NOT NULL',
            );
            $stmt->execute([$clinicId]);

            return (int) ($stmt->fetch()['n'] ?? 1);
        }
    }

    /**
     * Throw if the appointment's wall-clock time is before the current
     * clinic-local minute.
     *
     * Both the scheduled time and "now" are interpreted in the clinic's
     * timezone (Asia/Kolkata by default). `scheduled_at` is a naive wall-clock
     * string (e.g. "2026-06-19 16:15:00") with no offset, so we attach the
     * clinic tz when parsing it — this keeps the comparison correct regardless
     * of the server's php.ini default timezone.
     *
     * Comparison is against the start of the current minute, so a "book now"
     * walk-in isn't rejected just because a few seconds elapsed since the form
     * rendered. So at 4:15 PM IST, the 4:15 slot and later are allowed; 4:14
     * and earlier (and any past date) are rejected.
     */
    private static function assertNotInPast(int $clinicId, string $scheduledAt): void
    {
        $tz = 'Asia/Kolkata';
        try {
            $tz = SlotService::clinicTimezone($clinicId);
        } catch (\Throwable $e) {
            // Fall back to IST if the tenant row/timezone can't be read.
        }

        try {
            $zone = new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            $zone = new \DateTimeZone('Asia/Kolkata');
        }

        $scheduled = \DateTime::createFromFormat('Y-m-d H:i:s', $scheduledAt, $zone);
        if ($scheduled === false) {
            $scheduled = \DateTime::createFromFormat('Y-m-d H:i', substr($scheduledAt, 0, 16), $zone);
        }
        if ($scheduled === false) {
            throw new \RuntimeException('Invalid appointment date or time.');
        }

        $now = new \DateTime('now', $zone);
        // Start of the current minute, clinic-local.
        $nowMinuteTs = $now->getTimestamp() - (int) $now->format('s');

        if ($scheduled->getTimestamp() < $nowMinuteTs) {
            throw new \RuntimeException('Appointments cannot be booked in the past. Please choose the current time or a later slot.');
        }
    }
}
