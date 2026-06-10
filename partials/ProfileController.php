<?php
declare(strict_types=1);

require_once __DIR__ . '/directory_profile.php';

final class ProfileController
{
    /** @return array{ok: bool, profile?: array<string, mixed>} */
    public static function show(string $citySlug, string $entityType, string $slug): array
    {
        $profile = ecp_profile_find($citySlug, $entityType, $slug);

        return $profile === null ? ['ok' => false] : ['ok' => true, 'profile' => $profile];
    }
}
