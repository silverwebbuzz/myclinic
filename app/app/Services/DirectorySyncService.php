<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class DirectorySyncService
{
    /** @return int profiles upserted */
    public static function syncAll(): int
    {
        if (!Database::ping()) {
            return 0;
        }

        $sql = 'SELECT u.id AS user_id, u.clinic_id, u.name, u.specialization, u.qualification,
                       t.name AS clinic_name, t.specialty, t.address, t.phone, t.country_code
                FROM users u
                INNER JOIN tenants t ON t.id = u.clinic_id
                WHERE u.role = ? AND u.is_active = 1 AND t.is_active = 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['doctor']);
        $doctors = $stmt->fetchAll() ?: [];

        $count = 0;
        foreach ($doctors as $doc) {
            if (self::upsertProfile($doc)) {
                $count++;
            }
        }

        self::refreshCityCounts();

        return $count;
    }

    /** @param array<string, mixed> $doc */
    private static function upsertProfile(array $doc): bool
    {
        $userId = (int) $doc['user_id'];
        $clinicId = (int) $doc['clinic_id'];
        $slug = self::uniqueSlug($doc['name'] ?? 'doctor', $userId);
        $specialty = self::mapSpecialty($doc['specialization'] ?? $doc['specialty'] ?? 'gp');

        $existing = QueryBuilder::table('doctor_profiles')
            ->where('user_id', '=', $userId)
            ->first();

        $degrees = [];
        if (!empty($doc['qualification'])) {
            $degrees = [['title' => (string) $doc['qualification']]];
        }

        $payload = [
            'user_id' => $userId,
            'clinic_id' => $clinicId,
            'slug' => $slug,
            'full_name' => (string) ($doc['name'] ?? 'Doctor'),
            'specialty_primary' => $specialty,
            'specialties' => json_encode([$specialty]),
            'degrees' => json_encode($degrees),
            'is_public' => 1,
            'is_verified' => 1,
        ];

        if ($existing !== null) {
            unset($payload['slug']);
            QueryBuilder::table('doctor_profiles')
                ->where('id', '=', (int) $existing['id'])
                ->update($payload);

            $profileId = (int) $existing['id'];
        } else {
            QueryBuilder::table('doctor_profiles')->insert($payload);
            $profileId = (int) Database::connection()->lastInsertId();
        }

        self::upsertLocation($profileId, $doc);

        return true;
    }

    /** @param array<string, mixed> $doc */
    private static function upsertLocation(int $profileId, array $doc): void
    {
        $city = self::extractCity((string) ($doc['address'] ?? ''));
        $existing = QueryBuilder::table('doctor_locations')
            ->where('doctor_id', '=', $profileId)
            ->where('is_primary', '=', 1)
            ->first();

        $payload = [
            'clinic_name' => $doc['clinic_name'] ?? null,
            'address' => substr((string) ($doc['address'] ?? ''), 0, 250) ?: null,
            'city' => $city,
            'country_code' => $doc['country_code'] ?? 'IN',
            'phone' => $doc['phone'] ?? null,
            'is_primary' => 1,
        ];

        if ($existing !== null) {
            QueryBuilder::table('doctor_locations')
                ->where('id', '=', (int) $existing['id'])
                ->update($payload);
        } else {
            $payload['doctor_id'] = $profileId;
            QueryBuilder::table('doctor_locations')->insert($payload);
        }
    }

    private static function uniqueSlug(string $name, int $userId): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? 'doctor');
        $base = trim($base, '-') ?: 'doctor';

        return $base . '-' . $userId;
    }

    private static function mapSpecialty(string $raw): string
    {
        $map = [
            'gp' => 'General Physician',
            'homeopathy' => 'Homeopathy',
            'dental' => 'Dentist',
            'derma' => 'Dermatologist',
            'peds' => 'Pediatrician',
            'physio' => 'Physiotherapist',
        ];
        $key = strtolower($raw);

        return $map[$key] ?? ucfirst($raw);
    }

    private static function extractCity(string $address): string
    {
        if ($address === '') {
            return 'Mumbai';
        }
        $parts = array_map('trim', explode(',', $address));
        $city = $parts[count($parts) - 1] ?? 'Mumbai';

        return $city !== '' ? $city : 'Mumbai';
    }

    private static function refreshCityCounts(): void
    {
        $sql = 'SELECT dl.city, dl.country_code, COUNT(DISTINCT dl.doctor_id) AS cnt
                FROM doctor_locations dl
                INNER JOIN doctor_profiles dp ON dp.id = dl.doctor_id AND dp.is_public = 1
                GROUP BY dl.city, dl.country_code';
        $rows = Database::connection()->query($sql)->fetchAll() ?: [];

        foreach ($rows as $row) {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['city'])) ?? 'city');
            $existing = QueryBuilder::table('directory_cities')
                ->where('slug', '=', $slug)
                ->where('country_code', '=', $row['country_code'])
                ->first();

            if ($existing !== null) {
                QueryBuilder::table('directory_cities')
                    ->where('id', '=', (int) $existing['id'])
                    ->update(['doctor_count' => (int) $row['cnt']]);
            } else {
                QueryBuilder::table('directory_cities')->insert([
                    'name' => $row['city'],
                    'slug' => $slug,
                    'country_code' => $row['country_code'],
                    'doctor_count' => (int) $row['cnt'],
                ]);
            }
        }
    }
}
