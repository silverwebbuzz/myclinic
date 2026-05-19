<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\QueryBuilder;

final class DirectoryService
{
    /** @return list<array<string, mixed>> */
    public static function featured(): array
    {
        return QueryBuilder::table('doctor_profiles')
            ->where('is_public', '=', 1)
            ->where('is_featured', '=', 1)
            ->orderBy('avg_rating', 'DESC')
            ->limit(6)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function cities(): array
    {
        return QueryBuilder::table('directory_cities')
            ->orderBy('doctor_count', 'DESC')
            ->limit(50)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function byCitySpecialty(string $citySlug, string $specialtySlug): array
    {
        if (!Database::ping()) {
            return [];
        }

        $specialty = str_replace('-', ' ', $specialtySlug);
        $sql = 'SELECT dp.*, dl.city, dl.address
                FROM doctor_profiles dp
                INNER JOIN doctor_locations dl ON dl.doctor_id = dp.id AND dl.is_primary = 1
                WHERE dp.is_public = 1
                  AND dl.city LIKE ?
                  AND (dp.specialty_primary LIKE ? OR dp.slug LIKE ?)
                ORDER BY dp.is_featured DESC, dp.avg_rating DESC
                LIMIT 50';
        $stmt = Database::connection()->prepare($sql);
        $cityLike = '%' . str_replace('-', ' ', $citySlug) . '%';
        $specLike = '%' . $specialty . '%';
        $stmt->execute([$cityLike, $specLike, '%' . $specialtySlug . '%']);

        return $stmt->fetchAll() ?: [];
    }

    public static function findBySlug(string $slug): ?array
    {
        $profile = QueryBuilder::table('doctor_profiles')
            ->where('slug', '=', $slug)
            ->where('is_public', '=', 1)
            ->first();

        if ($profile === null) {
            return null;
        }

        QueryBuilder::table('doctor_profiles')
            ->where('id', '=', (int) $profile['id'])
            ->update(['profile_views' => (int) ($profile['profile_views'] ?? 0) + 1]);

        $locations = QueryBuilder::table('doctor_locations')
            ->where('doctor_id', '=', (int) $profile['id'])
            ->get();

        $reviews = QueryBuilder::table('doctor_reviews')
            ->where('doctor_id', '=', (int) $profile['id'])
            ->where('is_approved', '=', 1)
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();

        return [
            'profile' => $profile,
            'locations' => $locations,
            'reviews' => $reviews,
        ];
    }

    /** Stripe featured listing stub */
    public static function purchaseFeatured(int $profileId): bool
    {
        $log = dirname(__DIR__, 2) . '/storage/logs/stripe_directory.log';
        $line = date('c') . " featured_listing profile_id={$profileId} status=stub\n";
        @file_put_contents($log, $line, FILE_APPEND);

        QueryBuilder::table('doctor_profiles')
            ->where('id', '=', $profileId)
            ->update([
                'is_featured' => 1,
                'featured_until' => date('Y-m-d', strtotime('+30 days')),
            ]);

        return true;
    }
}
