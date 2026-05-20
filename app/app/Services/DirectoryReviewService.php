<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class DirectoryReviewService
{
    /** @return list<array<string, mixed>> */
    public static function pending(): array
    {
        return QueryBuilder::table('doctor_reviews')
            ->where('is_approved', '=', 0)
            ->orderBy('created_at', 'DESC')
            ->limit(100)
            ->get();
    }

    public static function approve(int $reviewId): void
    {
        $review = QueryBuilder::table('doctor_reviews')->where('id', '=', $reviewId)->first();
        if ($review === null) {
            return;
        }

        QueryBuilder::table('doctor_reviews')
            ->where('id', '=', $reviewId)
            ->update(['is_approved' => 1]);

        self::recalculateRating((int) $review['doctor_id']);
    }

    public static function reject(int $reviewId): void
    {
        QueryBuilder::table('doctor_reviews')->where('id', '=', $reviewId)->delete();
    }

    private static function recalculateRating(int $doctorId): void
    {
        $rows = QueryBuilder::table('doctor_reviews')
            ->where('doctor_id', '=', $doctorId)
            ->where('is_approved', '=', 1)
            ->get();

        $count = count($rows);
        $avg = 0.0;
        if ($count > 0) {
            $sum = array_sum(array_map(static fn (array $r) => (int) $r['rating'], $rows));
            $avg = round($sum / $count, 2);
        }

        QueryBuilder::table('doctor_profiles')
            ->where('id', '=', $doctorId)
            ->update([
                'avg_rating' => $avg,
                'total_reviews' => $count,
            ]);
    }
}
