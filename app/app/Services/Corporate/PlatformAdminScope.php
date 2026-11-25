<?php

declare(strict_types=1);

namespace App\Services\Corporate;

/**
 * What an eClinicPro team member (platform_admins row) may see in the
 * Workforce Health admin.
 *
 *   super          everything (default — every admin that existed before the
 *                  role column keeps full access)
 *   country_admin  Workforce Health screens only, for companies/countries in
 *                  platform_admins.country_codes (JSON list)
 *   billing        Workforce Health screens only (all countries); plan/trial
 *   support        Workforce Health screens only (all countries); read + resets
 */
final class PlatformAdminScope
{
    public const ROLES = [
        'super' => 'Super admin (everything)',
        'country_admin' => 'Country admin (Workforce Health, own countries)',
        'billing' => 'Billing (Workforce Health plans & trials)',
        'support' => 'Support (Workforce Health, read + resets)',
    ];

    /** @param array<string, mixed>|null $admin */
    public static function role(?array $admin): string
    {
        $role = (string) ($admin['role'] ?? 'super');

        return array_key_exists($role, self::ROLES) ? $role : 'super';
    }

    /** @param array<string, mixed>|null $admin */
    public static function isSuper(?array $admin): bool
    {
        return self::role($admin) === 'super';
    }

    /**
     * Country codes this admin is limited to, or null for all countries.
     *
     * @param array<string, mixed>|null $admin
     * @return list<string>|null
     */
    public static function countries(?array $admin): ?array
    {
        if (self::role($admin) !== 'country_admin') {
            return null;
        }
        $list = json_decode((string) ($admin['country_codes'] ?? '[]'), true);

        return is_array($list) ? array_values(array_filter(array_map(static fn ($c) => strtoupper((string) $c), $list),
            static fn (string $c): bool => preg_match('/^[A-Z]{2}$/', $c) === 1)) : [];
    }

    /** @param array<string, mixed>|null $admin */
    public static function canSeeCountry(?array $admin, string $countryCode): bool
    {
        $list = self::countries($admin);

        return $list === null || in_array(strtoupper($countryCode), $list, true);
    }

    /**
     * Can this admin do $action on a company? Actions: view, review (approve /
     * reject / documents), status (suspend / reactivate), plan (trial / plan
     * dates), reset (user MFA).
     *
     * @param array<string, mixed>|null $admin
     */
    public static function can(?array $admin, string $action): bool
    {
        return match (self::role($admin)) {
            'super', 'country_admin' => true,
            'billing' => in_array($action, ['view', 'plan'], true),
            'support' => in_array($action, ['view', 'reset'], true),
        };
    }

    /** Paths outside /admin/corporate that non-super admins may still open. */
    public static function allowsPath(?array $admin, string $path): bool
    {
        if (self::isSuper($admin)) {
            return true;
        }

        return $path === '/admin/logout' || $path === '/admin/corporate' || str_starts_with($path, '/admin/corporate/');
    }
}
