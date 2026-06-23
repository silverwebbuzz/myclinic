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
                '/appointments', '/billing', '/follow-ups', '/help', '/staff/attendance',
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
