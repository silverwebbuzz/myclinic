<?php

declare(strict_types=1);

namespace App\Services\OccHealth;

use App\Core\Database;
use App\Services\AppointmentService;
use App\Services\PatientService;
use App\Services\SlotService;
use App\Support\Locale\LocalDate;

/**
 * The clinic-side half of a corporate booking (design §4, C4/C5). Company code
 * (Services/Corporate) never touches clinic charts directly: it asks this
 * bridge for doctors and free slots, and to create or cancel the appointment.
 * The appointment is a normal one (source = 'corporate'), so it shows in the
 * clinic's own list, queue and reminders like any other.
 */
final class CorporateBookingBridge
{
    /**
     * Doctors who can do the exam AND sign the certificate: active, with a
     * verified registration that hasn't expired.
     *
     * @return list<array{id:int, name:string, qualification:?string}>
     */
    public static function doctors(int $clinicId): array
    {
        $st = Database::connection()->prepare(
            "SELECT u.id, u.name, u.qualification
               FROM users u JOIN occ_doctor_credentials c ON c.clinic_id = u.clinic_id AND c.user_id = u.id
              WHERE u.clinic_id = :clinic AND u.is_active = 1 AND u.role IN ('doctor', 'admin')
                AND c.status = 'verified' AND (c.valid_to IS NULL OR c.valid_to >= :today)
              ORDER BY u.name"
        );
        $st->execute(['clinic' => $clinicId, 'today' => LocalDate::today(SlotService::clinicTimezone($clinicId))]);

        return array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'qualification' => $r['qualification']], $st->fetchAll());
    }

    /** @return list<array{time:string, datetime:string}> free slots in the doctor's normal hours (clinic local time) */
    public static function slots(int $clinicId, int $doctorId, string $date): array
    {
        $out = [];
        foreach (SlotService::available($clinicId, $doctorId, $date, false) as $s) {
            if (!empty($s['available']) && empty($s['extended'])) {
                $out[] = ['time' => (string) $s['time'], 'datetime' => (string) $s['datetime']];
            }
        }

        return $out;
    }

    public static function today(int $clinicId): string
    {
        return LocalDate::today(SlotService::clinicTimezone($clinicId));
    }

    /**
     * Find or create the employee's chart at this clinic, then book the appointment.
     * Throws \RuntimeException with a user-facing message (slot taken, etc.).
     *
     * @param array{name:string, phone:?string, email:?string, dob:?string, gender:?string, identity_id:?int} $person
     * @return array{patient_id:int, appointment_id:int}
     */
    public static function book(int $clinicId, int $doctorId, string $scheduledAt, array $person, string $companyName, array $checkNames): array
    {
        $patient = self::chart($clinicId, $person);
        $appointment = AppointmentService::create($clinicId, [
            'patient_id' => (int) $patient['id'],
            'doctor_id' => $doctorId,
            'scheduled_at' => $scheduledAt,
            'type' => 'prebooked',
            'source' => 'corporate',
            'chief_complaint' => mb_substr('Occupational health check for ' . $companyName . ': ' . implode(', ', $checkNames), 0, 1000),
            'notes' => 'Booked by ' . $companyName . ' on eClinicPro Workforce Health. Record the fitness outcome in the visit.',
        ]);
        if (empty($appointment['id'])) {
            throw new \RuntimeException('The clinic could not take the booking. Please try another time.');
        }

        return ['patient_id' => (int) $patient['id'], 'appointment_id' => (int) $appointment['id']];
    }

    /** Cancel the clinic appointment (no-op if it's already finished or cancelled). */
    public static function cancel(int $clinicId, int $appointmentId): void
    {
        $a = AppointmentService::find($clinicId, $appointmentId);
        if ($a !== null && !in_array((string) $a['status'], ['completed', 'cancelled', 'no_show'], true)) {
            AppointmentService::cancel($clinicId, $appointmentId);
        }
    }

    /**
     * Linked identity first, then the phone as stored (international or national form), else a new chart.
     *
     * @param array{name:string, phone:?string, email:?string, dob:?string, gender:?string, identity_id:?int} $person
     * @return array<string, mixed>
     */
    private static function chart(int $clinicId, array $person): array
    {
        $pdo = Database::connection();
        if (!empty($person['identity_id'])) {
            $st = $pdo->prepare('SELECT * FROM patients WHERE clinic_id = :clinic AND identity_id = :iid AND is_active = 1 ORDER BY id LIMIT 1');
            $st->execute(['clinic' => $clinicId, 'iid' => (int) $person['identity_id']]);
            if ($row = $st->fetch()) {
                return $row;
            }
        }
        $phone = (string) ($person['phone'] ?? '');
        $national = self::nationalForm($clinicId, $phone);
        foreach (array_unique(array_filter([$phone, $national])) as $p) {
            $row = PatientService::findAccountHolderByPhone($clinicId, $p);
            if ($row !== null && mb_strtolower(trim((string) $row['name'])) === mb_strtolower(trim($person['name']))) {
                return $row;   // same phone AND same name: the same person
            }
        }

        return PatientService::create($clinicId, [
            'name' => $person['name'],
            'phone' => $national ?? $phone,
            'email' => $person['email'] ?? null,
            'dob' => $person['dob'] ?? null,
            'gender' => $person['gender'] ?? null,
            'source' => 'other',
            'referred_by' => 'Employer (Workforce Health)',
            'identity_id' => $person['identity_id'] ?? null,
        ]);
    }

    /** +91 98765 43210 → 9876543210 when the clinic is in that country (how reception usually types it). */
    private static function nationalForm(int $clinicId, string $e164): ?string
    {
        if (!str_starts_with($e164, '+')) {
            return null;
        }
        $st = Database::connection()->prepare('SELECT g.phone_code FROM tenants t JOIN geo_countries g ON g.code = t.country_code WHERE t.id = :id');
        $st->execute(['id' => $clinicId]);
        $code = (string) ($st->fetchColumn() ?: '');

        return $code !== '' && str_starts_with($e164, '+' . $code) ? substr($e164, strlen($code) + 1) : null;
    }
}
