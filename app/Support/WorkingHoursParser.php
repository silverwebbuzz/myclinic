<?php

declare(strict_types=1);

namespace App\Support;

final class WorkingHoursParser
{
    /**
     * Per-day form input (legacy): each day has its own enabled + morning/evening fields.
     * @param array<string, mixed> $post @return array<string, mixed>
     */
    public static function fromPost(array $post): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $result = [];
        foreach ($days as $day) {
            $enabled = !empty($post["{$day}_enabled"]);
            $result[$day] = ['enabled' => $enabled, 'sessions' => []];
            if (!$enabled) {
                continue;
            }
            $morningStart = $post["{$day}_morning_start"] ?? null;
            $morningEnd = $post["{$day}_morning_end"] ?? null;
            $eveningStart = $post["{$day}_evening_start"] ?? null;
            $eveningEnd = $post["{$day}_evening_end"] ?? null;
            if ($morningStart && $morningEnd) {
                $result[$day]['sessions'][] = ['start' => $morningStart, 'end' => $morningEnd];
            }
            if ($eveningStart && $eveningEnd) {
                $result[$day]['sessions'][] = ['start' => $eveningStart, 'end' => $eveningEnd];
            }
            if ($result[$day]['sessions'] === []) {
                $result[$day]['sessions'][] = ['start' => '09:00', 'end' => '18:00'];
            }
        }

        return $result;
    }

    /**
     * Grouped form input: one Mon-Sat block + Sunday + per-session extended end time.
     * Fans the Mon-Sat hours to each weekday. Sunday is independent.
     * @param array<string, mixed> $post @return array<string, mixed>
     */
    public static function fromGroupedPost(array $post): array
    {
        $morningEnabled = !empty($post['weekday_morning_enabled']);
        $eveningEnabled = !empty($post['weekday_evening_enabled']);
        $weekdaySessions = [];
        if ($morningEnabled && !empty($post['weekday_morning_start']) && !empty($post['weekday_morning_end'])) {
            $weekdaySessions[] = [
                'start' => (string) $post['weekday_morning_start'],
                'end' => (string) $post['weekday_morning_end'],
                'extended_end' => !empty($post['weekday_morning_extended_end']) ? (string) $post['weekday_morning_extended_end'] : null,
            ];
        }
        if ($eveningEnabled && !empty($post['weekday_evening_start']) && !empty($post['weekday_evening_end'])) {
            $weekdaySessions[] = [
                'start' => (string) $post['weekday_evening_start'],
                'end' => (string) $post['weekday_evening_end'],
                'extended_end' => !empty($post['weekday_evening_extended_end']) ? (string) $post['weekday_evening_extended_end'] : null,
            ];
        }
        $weekdayEnabled = $weekdaySessions !== [];

        $sundayOpen = !empty($post['sunday_open']);
        $sundaySessions = [];
        if ($sundayOpen && !empty($post['sunday_start']) && !empty($post['sunday_end'])) {
            $sundaySessions[] = [
                'start' => (string) $post['sunday_start'],
                'end' => (string) $post['sunday_end'],
                'extended_end' => !empty($post['sunday_extended_end']) ? (string) $post['sunday_extended_end'] : null,
            ];
        }

        return [
            'mon' => ['enabled' => $weekdayEnabled, 'sessions' => $weekdaySessions],
            'tue' => ['enabled' => $weekdayEnabled, 'sessions' => $weekdaySessions],
            'wed' => ['enabled' => $weekdayEnabled, 'sessions' => $weekdaySessions],
            'thu' => ['enabled' => $weekdayEnabled, 'sessions' => $weekdaySessions],
            'fri' => ['enabled' => $weekdayEnabled, 'sessions' => $weekdaySessions],
            'sat' => ['enabled' => $weekdayEnabled, 'sessions' => $weekdaySessions],
            'sun' => ['enabled' => $sundayOpen && $sundaySessions !== [], 'sessions' => $sundaySessions],
        ];
    }
}
