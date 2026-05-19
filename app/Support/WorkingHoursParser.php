<?php

declare(strict_types=1);

namespace App\Support;

final class WorkingHoursParser
{
    /** @param array<string, mixed> $post @return array<string, mixed> */
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
}
