<?php

declare(strict_types=1);

namespace App\Services;

final class RoleAccessService
{
    /** @param array<string, mixed> $user */
    public static function isClinicAdmin(array $user): bool
    {
        if (!empty($user['is_owner'])) {
            return true;
        }

        return in_array((string) ($user['role'] ?? ''), ['admin', 'superadmin'], true);
    }

    /** @param array<string, mixed> $user */
    public static function panelRoleLabel(array $user): string
    {
        if (self::isClinicAdmin($user)) {
            return 'Clinic admin';
        }

        return match ((string) ($user['role'] ?? '')) {
            'doctor' => 'Clinic doctor',
            'receptionist' => 'Receptionist',
            'nurse' => 'Nurse',
            'labtech' => 'Lab technician',
            default => 'Staff',
        };
    }

    /** Admin and receptionist can book and manage appointments for any doctor. */
    public static function canBookAppointmentsForAllDoctors(array $user): bool
    {
        if (self::isClinicAdmin($user)) {
            return true;
        }

        return ($user['role'] ?? '') === 'receptionist';
    }

    /** Admin, receptionist, or a logged-in doctor (own patients only). */
    public static function canBookAppointments(array $user): bool
    {
        return self::canBookAppointmentsForAllDoctors($user)
            || self::appointmentDoctorScope($user) !== null;
    }

    /** Logged-in doctor id when appointments must be scoped to self; null otherwise. */
    public static function appointmentDoctorScope(array $user): ?int
    {
        if (($user['role'] ?? '') !== 'doctor') {
            return null;
        }

        $id = (int) ($user['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    public static function resolveAppointmentDoctorId(array $user, ?int $requested): ?int
    {
        $scope = self::appointmentDoctorScope($user);
        if ($scope !== null) {
            return $scope;
        }

        return $requested;
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $appointment */
    public static function canAccessAppointment(array $user, array $appointment): bool
    {
        if (self::canBookAppointmentsForAllDoctors($user)) {
            return true;
        }

        $scope = self::appointmentDoctorScope($user);
        if ($scope !== null) {
            return (int) ($appointment['doctor_id'] ?? 0) === $scope;
        }

        return true;
    }

    /** Admin and receptionist can manage any appointment; doctors may book/edit their own only. */
    public static function canManageAppointment(array $user, array $appointment): bool
    {
        if (self::canBookAppointmentsForAllDoctors($user)) {
            return true;
        }

        $scope = self::appointmentDoctorScope($user);

        return $scope !== null && (int) ($appointment['doctor_id'] ?? 0) === $scope;
    }

    /** @param array<string, mixed> $user */
    public static function canManageOwnSchedule(array $user): bool
    {
        return self::appointmentDoctorScope($user) !== null;
    }

    /** @param array<string, mixed> $user */
    public static function canAccessPath(array $user, string $method, string $path): bool
    {
        if (self::isClinicAdmin($user)) {
            return true;
        }

        if (self::isSelfServicePath($path)) {
            return true;
        }

        $role = (string) ($user['role'] ?? 'receptionist');

        return match ($role) {
            'doctor' => self::pathIn($path, self::doctorPrefixes()),
            'nurse' => self::pathIn($path, self::nursePrefixes()),
            'receptionist' => self::pathIn($path, self::receptionistPrefixes()),
            'labtech' => self::pathIn($path, self::labtechPrefixes()),
            default => false,
        };
    }

    /** @param array<string, mixed> $user */
    public static function canSeeNavHref(array $user, string $href): bool
    {
        if (self::isClinicAdmin($user)) {
            return true;
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        $role = (string) ($user['role'] ?? 'receptionist');

        return match ($role) {
            'doctor' => self::pathIn($path, [
                '/dashboard', '/patients', '/visits', '/prescriptions', '/vitals',
                '/appointments', '/queue', '/billing', '/follow-ups', '/help', '/staff/attendance',
                '/doctor/schedule',
            ]),
            'nurse' => self::pathIn($path, [
                '/dashboard', '/patients', '/visits', '/vitals',
                '/appointments', '/queue', '/help', '/staff/attendance',
            ]),
            'receptionist' => self::pathIn($path, [
                '/dashboard', '/patients', '/appointments', '/queue', '/billing',
                '/follow-ups', '/help', '/staff/attendance',
            ]),
            'labtech' => self::pathIn($path, ['/dashboard', '/help', '/staff/attendance']),
            default => $path === '/help',
        };
    }

    private static function isSelfServicePath(string $path): bool
    {
        return $path === '/change-password'
            || $path === '/settings/password'
            || $path === '/settings/sessions'
            || str_starts_with($path, '/settings/sessions/');
    }

    /** @param list<string> $prefixes */
    private static function pathIn(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function receptionistPrefixes(): array
    {
        return [
            '/dashboard',
            '/patients',
            '/appointments',
            '/queue',
            '/billing',
            '/follow-ups',
            '/help',
            '/staff/attendance',
            '/api/v1/dashboard',
            '/api/v1/patients',
            '/api/v1/appointments',
            '/api/v1/slots',
            '/api/v1/queue',
            '/api/v1/billing',
            '/api/v1/follow-ups',
            '/api/v1/ping',
        ];
    }

    /** @return list<string> */
    private static function doctorPrefixes(): array
    {
        return array_merge(self::receptionistPrefixes(), [
            '/visits',
            '/prescriptions',
            '/vitals',
            '/staff/leaves',
            '/doctor/schedule',
            '/api/v1/visits',
            '/api/v1/symptoms',
            '/api/v1/prescriptions',
            '/api/v1/drugs',
            '/api/v1/remedies',
            '/api/v1/icd10',
            '/api/v1/diet-templates',
        ]);
    }

    /** @return list<string> */
    private static function nursePrefixes(): array
    {
        return [
            '/dashboard',
            '/patients',
            '/visits',
            '/vitals',
            '/appointments',
            '/queue',
            '/help',
            '/staff/attendance',
            '/api/v1/dashboard',
            '/api/v1/patients',
            '/api/v1/appointments',
            '/api/v1/slots',
            '/api/v1/queue',
            '/api/v1/visits',
            '/api/v1/symptoms',
            '/api/v1/ping',
        ];
    }

    /** @return list<string> */
    private static function labtechPrefixes(): array
    {
        return [
            '/dashboard',
            '/help',
            '/staff/attendance',
            '/api/v1/ping',
        ];
    }
}
