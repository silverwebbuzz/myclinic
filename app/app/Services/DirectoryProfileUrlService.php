<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

/**
 * Public directory profile URLs for tenant clinics (marketing site).
 */
final class DirectoryProfileUrlService
{
    /** Patient-facing booking entry point: profile sidebar widget. */
    public static function publicBookingUrlForTenant(int $tenantId, string $tenantSlug): string
    {
        $site = self::siteBase();
        $path = self::profilePathForTenant($tenantId);

        if ($path !== null) {
            return $site . $path . '#book';
        }

        return $site . '/book/' . rawurlencode($tenantSlug);
    }

    public static function publicBookingUrlForTenantSlug(string $tenantSlug): string
    {
        $tenantSlug = strtolower(trim($tenantSlug));
        if ($tenantSlug === '') {
            return self::siteBase() . '/find-a-doctor';
        }

        $clinic = PublicBookingService::clinicBySlug($tenantSlug);

        return self::publicBookingUrlForTenant(
            $clinic !== null ? (int) $clinic['id'] : 0,
            $tenantSlug,
        );
    }

    public static function profilePathForTenant(int $tenantId): ?string
    {
        if ($tenantId <= 0 || !Database::ping()) {
            return null;
        }

        $row = QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $tenantId)
            ->where('is_claimed', '=', 1)
            ->where('is_active', '=', 1)
            ->first();

        if ($row === null) {
            $tenant = QueryBuilder::table('tenants')->where('id', '=', $tenantId)->first();
            $dirId = (int) ($tenant['directory_doctor_id'] ?? 0);
            if ($dirId > 0) {
                $row = QueryBuilder::table('directory_doctors')
                    ->where('id', '=', $dirId)
                    ->where('is_active', '=', 1)
                    ->first();
            }
        }

        if ($row === null) {
            return null;
        }

        return self::profilePathFromRow($row);
    }

    /** @param array<string, mixed> $row */
    public static function profilePathFromRow(array $row): ?string
    {
        $city = trim((string) ($row['city'] ?? ''));
        if ($city === '') {
            return null;
        }

        $entityType = self::entityType($row);
        $listingSlug = self::listingSlug($row, $entityType);

        if ($listingSlug === '') {
            return null;
        }

        return '/' . self::slug($city) . '/' . $entityType . '/' . $listingSlug;
    }

    /** @param array<string, mixed> $row */
    private static function entityType(array $row): string
    {
        $stored = strtolower(trim((string) ($row['entity_type'] ?? '')));
        if ($stored === 'clinic' || $stored === 'doctor') {
            return $stored;
        }

        $name = strtolower((string) ($row['name'] ?? ''));
        foreach (['clinic', 'hospital', 'care', 'centre', 'center', 'polyclinic', 'nursing home', 'dispensary'] as $kw) {
            if (str_contains($name, $kw)) {
                return 'clinic';
            }
        }

        $types = json_decode((string) ($row['types'] ?? '[]'), true);

        return is_array($types) && in_array('doctor', $types, true) ? 'doctor' : 'clinic';
    }

    /** @param array<string, mixed> $row */
    private static function listingSlug(array $row, string $entityType): string
    {
        $stored = strtolower(trim((string) ($row['listing_slug'] ?? '')));
        if ($stored !== '') {
            return $stored;
        }

        if ($entityType === 'clinic') {
            $base = preg_replace('/\s*[-–|]\s*Dr\.?\s.*$/i', '', (string) ($row['name'] ?? '')) ?: (string) ($row['name'] ?? '');
            $area = trim((string) ($row['area'] ?? ''));

            return self::slug($area !== '' ? trim($base) . ' ' . $area : trim($base));
        }

        $doctorName = trim((string) ($row['doctor_name'] ?? ''));

        return self::slug($doctorName !== '' ? $doctorName : (string) ($row['name'] ?? ''));
    }

    private static function slug(string $raw): string
    {
        $s = strtolower(trim($raw));
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== false) {
                $s = $t;
            }
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? $s;

        return trim($s, '-');
    }

    private static function siteBase(): string
    {
        return rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/');
    }
}
