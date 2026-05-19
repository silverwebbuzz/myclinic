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
        return SlotService::available($clinicId, $doctorId, $date);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public static function book(int $clinicId, array $data): array
    {
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
            'scheduled_at' => $data['scheduled_at'],
            'type' => 'online',
            'source' => 'website',
        ]);

        return ['patient' => $patient, 'appointment' => $appointment];
    }
}
