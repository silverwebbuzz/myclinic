<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class PublicBookingService
{
    public static function clinicBySlug(string $slug): ?array
    {
        return QueryBuilder::table('tenants')->where('slug', '=', $slug)->where('is_active', '=', 1)->first() ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function doctors(int $clinicId): array
    {
        // Must match AppointmentService::doctorsForClinic() — the same IDs
        // DoctorScheduleService uses when syncing working hours to doctor_schedules.
        return AppointmentService::doctorsForClinic($clinicId);
    }

    /** @return list<array{time: string, datetime: string, available: bool, blocked?: bool, past?: bool, extended?: bool}> */
    public static function slots(int $clinicId, int $doctorId, string $date): array
    {
        if ($doctorId <= 0) {
            $docs = self::doctors($clinicId);
            $doctorId = (int) ($docs[0]['id'] ?? 0);
        }
        if ($doctorId <= 0) {
            return [];
        }
        if (!self::isWithinBookingWindow($clinicId, $date)) {
            return [];
        }

        return SlotService::available($clinicId, $doctorId, $date);
    }

    public static function bookingWindowDays(int $clinicId): int
    {
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        return (int) ($config['booking_window_days'] ?? 30);
    }

    /**
     * Privacy-safe phone lookup. Returns only the patient's name when matched,
     * never the full record — public booking page is unauthenticated.
     * @return array{found: bool, name?: string}
     */
    public static function findByPhonePublic(int $clinicId, string $phone): array
    {
        $normalized = PatientService::normalizePhone($phone);
        if ($normalized === '' || strlen($normalized) < 6) {
            return ['found' => false];
        }

        // 1) Existing chart at THIS clinic — strongest match.
        $patient = PatientService::findByPhone($clinicId, $normalized);
        if ($patient !== null) {
            return [
                'found'  => true,
                'name'   => (string) ($patient['name'] ?? ''),
                'source' => 'this_clinic',
            ];
        }

        // 2) Global identity — patient signed up on eclinicpro.com/patient.
        $identity = \App\Core\QueryBuilder::table('patient_identities')
            ->where('phone', '=', $normalized)
            ->first();
        if ($identity !== null) {
            return [
                'found'  => true,
                'name'   => (string) ($identity['name'] ?? ''),
                'source' => 'eclinicpro_identity',
            ];
        }

        // 3) Patient at another clinic — they're in the system somewhere.
        $other = \App\Core\QueryBuilder::table('patients')
            ->where('phone', '=', $normalized)
            ->where('is_active', '=', 1)
            ->first();
        if ($other !== null) {
            return [
                'found'  => true,
                'name'   => (string) ($other['name'] ?? ''),
                'source' => 'other_clinic',
            ];
        }

        return ['found' => false];
    }

    public static function isWithinBookingWindow(int $clinicId, string $date): bool
    {
        $days = self::bookingWindowDays($clinicId);

        // Accept Y-m-d (slots API) or Y-m-d H:i:s (booking form posts scheduled_at).
        $date = trim($date);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $date, $m) === 1) {
            $date = $m[1];
        } else {
            return false;
        }

        // Compute the day difference in the CLINIC's timezone so the window
        // boundary matches SlotService (which also works in clinic-local time).
        // Using server-local date() here caused the boundary day to be wrong
        // near midnight when the server and clinic are in different zones.
        $tz = SlotService::clinicTimezone($clinicId);
        try {
            $zone = new \DateTimeZone($tz);
            $target = \DateTime::createFromFormat('Y-m-d', trim($date), $zone);
            if ($target === false) {
                return false;
            }
            $target->setTime(0, 0, 0);
            $today = (new \DateTime('now', $zone))->setTime(0, 0, 0);
        } catch (\Throwable) {
            return false;
        }

        // Whole-day difference (DST-safe: both anchored to local midnight).
        $diff = (int) floor(($target->getTimestamp() - $today->getTimestamp()) / 86400);

        // "N days" means a window of N calendar days starting today, i.e. today
        // plus the next N-1 days are bookable. So day 0..N-1 are allowed; day N
        // is the first day OUTSIDE the window. (Previously `<= $days` wrongly
        // allowed N+1 days.)
        return $diff >= 0 && $diff < $days;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function book(int $clinicId, array $data): array
    {
        $scheduledAt = (string) ($data['scheduled_at'] ?? '');
        if ($scheduledAt !== '' && !self::isWithinBookingWindow($clinicId, $scheduledAt)) {
            throw new \RuntimeException('That date is outside the online booking window. Please contact the clinic to book directly.');
        }

        $phone = PatientService::normalizePhone((string) ($data['phone'] ?? ''));
        $bookerName = trim((string) ($data['name'] ?? ''));

        // ----- Identity resolution (front-side person) -----
        // Priority:
        //  1) Logged-in /patient session — trust their identity & phone directly.
        //  2) Phone match against an existing identity.
        //  3) No identity yet → REGISTER one now (we already collected a verified
        //     name + phone), so the booker gets a /patient account and their
        //     future booking history is linked from the start.
        $loggedIn = PatientIdentityAuthService::current();
        if ($loggedIn !== null) {
            $identityId = (int) $loggedIn['id'];
            // Trust the account's own phone/name over anything posted.
            if (!empty($loggedIn['phone'])) {
                $phone = PatientService::normalizePhone((string) $loggedIn['phone']);
            }
            if ($bookerName === '' && !empty($loggedIn['name'])) {
                $bookerName = trim((string) $loggedIn['name']);
            }
        } else {
            $identityId = self::resolveIdentityByPhone($phone);
            if ($identityId === null && $phone !== '' && $bookerName !== '') {
                $identityId = self::registerIdentity($phone, $bookerName);
            }
        }

        // Find the per-clinic chart (one patients row per clinic).
        $patient = $phone !== '' ? PatientService::findByPhone($clinicId, $phone) : null;
        if ($patient === null) {
            $patient = PatientService::create($clinicId, [
                'name'        => $bookerName !== '' ? $bookerName : 'Patient',
                'phone'       => $phone !== '' ? $phone : (string) ($data['phone'] ?? ''),
                'source'      => 'online',
                'identity_id' => $identityId,    // may be null — that's fine
            ]);
        } elseif ($identityId !== null && empty($patient['identity_id'])) {
            // Existing clinic chart, but no identity link yet — backfill it.
            \App\Core\QueryBuilder::table('patients')
                ->where('id', '=', (int) $patient['id'])
                ->update(['identity_id' => $identityId]);
            $patient['identity_id'] = $identityId;
        }

        $appointment = AppointmentService::create($clinicId, [
            'patient_id' => (int) $patient['id'],
            'doctor_id' => (int) $data['doctor_id'],
            'scheduled_at' => $scheduledAt,
            'type' => 'online',
            'source' => 'website',
            'chief_complaint' => trim((string) ($data['chief_complaint'] ?? '')),
            'is_followup' => !empty($data['is_followup']),
        ]);

        // Online booking is a "request received", not a confirmed slot. Notify
        // the doctor (so they can call) and acknowledge to the patient.
        $doctor = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('id', '=', (int) $data['doctor_id'])
            ->first();
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($doctor !== null && $clinic !== null) {
            // doctor.phone is nullable. Fall back to the clinic owner's phone
            // (same canonical contact MessagingPolicy uses) so the booking
            // alert is never silently lost.
            if (empty($doctor['phone'])) {
                $doctor['phone'] = self::clinicContactPhone($clinicId);
            }
            NotificationService::queueOnlineBooking($appointment, $patient, $doctor, $clinic);
        }

        return ['patient' => $patient, 'appointment' => $appointment];
    }

    /**
     * Canonical clinic contact phone for fallback alerts: the clinic owner's
     * phone (mirrors MessagingPolicy's owner lookup). Returns '' if none set.
     */
    private static function clinicContactPhone(int $clinicId): string
    {
        $owner = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_owner', '=', 1)
            ->first();
        return (string) ($owner['phone'] ?? '');
    }

    /**
     * Resolve the front-side logged-in patient from the `ecp_pid` session cookie.
     *
     * @return array<string,mixed>|null
     */
    public static function currentPatientIdentity(): ?array
    {
        return PatientIdentityAuthService::current();
    }

    /**
     * Register a new front-side identity for a booker who has no account yet.
     * Stores the phone in the SAME E.164 form the front-end uses (ecp_normalize_phone)
     * so a later /patient OTP login on the same number finds this very identity.
     * source='self_signup' — they actively gave name + phone to book.
     * Returns the new identity id, or null if it could not be created.
     */
    private static function registerIdentity(string $phone, string $name): ?int
    {
        $e164 = self::toE164($phone);
        if ($e164 === '') {
            return null;
        }
        $name = trim($name) !== '' ? trim($name) : 'Patient';

        try {
            // Guard against a race / pre-existing row on the canonical phone:
            // re-check by last-10 first, then insert.
            $existing = self::resolveIdentityByPhone($e164);
            if ($existing !== null) {
                return $existing;
            }

            return (int) \App\Core\QueryBuilder::table('patient_identities')->insert([
                'phone'  => $e164,
                'name'   => $name,
                'source' => 'self_signup',
            ]);
        } catch (\Throwable $e) {
            // Unique-key collision or DB hiccup — fall back to a fresh lookup so
            // booking still links if the row now exists; never break the booking.
            error_log('[PublicBookingService::registerIdentity] ' . $e->getMessage());
            return self::resolveIdentityByPhone($e164);
        }
    }

    /**
     * Canonical E.164 form matching the front-end's ecp_normalize_phone():
     * 10-digit → +91XXXXXXXXXX (India default); already-+ kept; 0/91 prefixes
     * handled. Returns '' if no usable digits.
     */
    private static function toE164(string $raw): string
    {
        $s = preg_replace('/[\s\-()]/', '', trim($raw)) ?? '';
        if ($s === '') {
            return '';
        }
        if ($s[0] === '+') {
            $rest = preg_replace('/\D/', '', substr($s, 1)) ?? '';
            return $rest === '' ? '' : '+' . $rest;
        }
        $digits = preg_replace('/\D/', '', $s) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }
        if (strlen($digits) === 11 && $digits[0] === '0') {
            return '+91' . substr($digits, 1);
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+' . $digits;
        }
        return '+' . $digits;
    }

    /**
     * Look up the patient_identities row that owns this phone, if any.
     * Returns the identity id or null.
     */
    private static function resolveIdentityByPhone(string $normalizedPhone): ?int
    {
        if ($normalizedPhone === '') return null;

        // patient_identities stores E.164 (+91…) while booking normalizes to a
        // looser form, so an exact match misses the same person. Match on the
        // last 10 digits — the only key both layers reliably share.
        $digits = preg_replace('/\D/', '', $normalizedPhone) ?? '';
        if (strlen($digits) < 10) {
            return null;
        }
        $last10 = substr($digits, -10);

        $pdo = \App\Core\Database::connection();
        // COLLATE pins the bound literal's collation to the column's so a mixed
        // server/column default never raises "#1267 Illegal mix of collations".
        $stmt = $pdo->prepare(
            'SELECT id FROM patient_identities
             WHERE RIGHT(
                 REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "+", ""), "(", ""), ")", ""),
                 10
             ) COLLATE utf8mb4_unicode_ci
             = CAST(:l10 AS CHAR(10) CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['l10' => $last10]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }
}
