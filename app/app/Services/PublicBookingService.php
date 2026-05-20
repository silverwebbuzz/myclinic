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
        return QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '=', 'doctor')
            ->where('is_active', '=', 1)
            ->get();
    }

    /** @return list<array{time: string, datetime: string, available: bool}> */
    public static function slots(int $clinicId, int $doctorId, string $date): array
    {
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
        $patient = PatientService::findByPhone($clinicId, $normalized);
        if ($patient === null) {
            return ['found' => false];
        }
        return ['found' => true, 'name' => (string) ($patient['name'] ?? '')];
    }

    public static function isWithinBookingWindow(int $clinicId, string $date): bool
    {
        $days = self::bookingWindowDays($clinicId);
        $ts = strtotime($date);
        if ($ts === false) return false;
        $today = strtotime(date('Y-m-d'));
        $diff = (int) (($ts - $today) / 86400);
        return $diff >= 0 && $diff <= $days;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function book(int $clinicId, array $data): array
    {
        $scheduledAt = (string) ($data['scheduled_at'] ?? '');
        if ($scheduledAt !== '' && !self::isWithinBookingWindow($clinicId, $scheduledAt)) {
            throw new \RuntimeException('That date is outside the online booking window. Please contact the clinic to book directly.');
        }

        $phone = PatientService::normalizePhone((string) ($data['phone'] ?? ''));
        $patient = $phone !== '' ? PatientService::findByPhone($clinicId, $phone) : null;
        if ($patient === null) {
            $patient = PatientService::create($clinicId, [
                'name' => $data['name'] ?? 'Patient',
                'phone' => $data['phone'] ?? '',
                'source' => 'online',
            ]);
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

        return ['patient' => $patient, 'appointment' => $appointment];
    }
}
