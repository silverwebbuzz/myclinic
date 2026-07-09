<?php

declare(strict_types=1);

namespace App\Support;

final class SpecialtyOptionsParser
{
    /** @param array<string, mixed> $post @return array<string, mixed> */
    public static function fromPost(string $specialty, array $post): array
    {
        return ['slot_duration' => (int) ($post['slot_duration'] ?? 15)];
    }
}
