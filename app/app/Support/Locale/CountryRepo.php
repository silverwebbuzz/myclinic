<?php

declare(strict_types=1);

namespace App\Support\Locale;

use App\Core\Database;

/**
 * Read-only access to the geo_* master tables (countries, currencies, regions).
 *
 * The tables are tiny (tens of rows), so the first lookup loads ALL countries
 * and currencies in one query each and keeps them for the rest of the request.
 * Tests (and CLI scripts) can call prime() to inject rows and skip the DB.
 *
 * This is the only place new Workforce Health code reads country facts from.
 * Never hardcode 'INR', '₹', '+91', 'GST' or 'Asia/Kolkata' in new code.
 */
final class CountryRepo
{
    /** @var array<string, array<string, mixed>>|null code => row */
    private static ?array $countries = null;

    /** @var array<string, array<string, mixed>>|null code => row */
    private static ?array $currencies = null;

    /** @var array<string, list<array<string, mixed>>> country => regions */
    private static array $regions = [];

    /** @return array<string, mixed>|null */
    public static function country(string $code): ?array
    {
        self::load();

        return self::$countries[strtoupper(trim($code))] ?? null;
    }

    /** @return array<string, mixed>|null */
    public static function currency(string $code): ?array
    {
        self::load();

        return self::$currencies[strtoupper(trim($code))] ?? null;
    }

    /**
     * Countries offered at sign-up (is_active = 1), in display order.
     *
     * @return list<array<string, mixed>>
     */
    public static function activeCountries(): array
    {
        self::load();
        $rows = array_values(array_filter(
            self::$countries ?? [],
            static fn (array $c): bool => (int) ($c['is_active'] ?? 0) === 1,
        ));
        usort($rows, static fn (array $a, array $b): int =>
            [(int) ($a['sort_order'] ?? 100), (string) $a['name']] <=> [(int) ($b['sort_order'] ?? 100), (string) $b['name']]);

        return $rows;
    }

    /**
     * All countries (active or not), keyed by code. For admin screens.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allCountries(): array
    {
        self::load();

        return self::$countries ?? [];
    }

    /**
     * Active regions (states / emirates / counties) of one country.
     *
     * @return list<array<string, mixed>>
     */
    public static function regions(string $countryCode): array
    {
        $cc = strtoupper(trim($countryCode));
        if (!array_key_exists($cc, self::$regions)) {
            $stmt = Database::connection()->prepare(
                'SELECT id, country_code, code, name, timezone
                   FROM geo_regions
                  WHERE country_code = :cc AND is_active = 1
                  ORDER BY sort_order, name'
            );
            $stmt->execute(['cc' => $cc]);
            self::$regions[$cc] = $stmt->fetchAll();
        }

        return self::$regions[$cc];
    }

    /** @return array<string, mixed>|null */
    public static function region(string $countryCode, int $regionId): ?array
    {
        foreach (self::regions($countryCode) as $r) {
            if ((int) $r['id'] === $regionId) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Inject rows instead of reading the DB (tests, CLI). Replaces any cache.
     *
     * @param list<array<string, mixed>> $countries
     * @param list<array<string, mixed>> $currencies
     * @param array<string, list<array<string, mixed>>> $regions
     */
    public static function prime(array $countries, array $currencies, array $regions = []): void
    {
        self::$countries = [];
        foreach ($countries as $c) {
            self::$countries[strtoupper((string) $c['code'])] = $c;
        }
        self::$currencies = [];
        foreach ($currencies as $c) {
            self::$currencies[strtoupper((string) $c['code'])] = $c;
        }
        self::$regions = [];
        foreach ($regions as $cc => $rows) {
            self::$regions[strtoupper((string) $cc)] = $rows;
        }
    }

    /** Forget cached rows (after an admin edit, or between tests). */
    public static function reset(): void
    {
        self::$countries = null;
        self::$currencies = null;
        self::$regions = [];
    }

    private static function load(): void
    {
        if (self::$countries !== null && self::$currencies !== null) {
            return;
        }
        $pdo = Database::connection();

        self::$countries = [];
        foreach ($pdo->query('SELECT * FROM geo_countries')->fetchAll() as $row) {
            self::$countries[(string) $row['code']] = $row;
        }

        self::$currencies = [];
        foreach ($pdo->query('SELECT * FROM geo_currencies')->fetchAll() as $row) {
            self::$currencies[(string) $row['code']] = $row;
        }
    }
}
