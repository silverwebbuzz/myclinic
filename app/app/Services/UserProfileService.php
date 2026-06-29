<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class UserProfileService
{
    /**
     * Update the logged-in staff member's display name (users.name).
     * If they are the clinic's public listing doctor, directory_doctors stays in sync.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public static function updateName(int $userId, int $clinicId, string $name): array
    {
        $name = mb_substr(trim($name), 0, 120);
        if ($name === '') {
            return ['ok' => false, 'error' => 'Please enter your name.'];
        }

        $user = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('id', '=', $userId)
            ->where('is_active', '=', 1)
            ->first();

        if ($user === null) {
            return ['ok' => false, 'error' => 'Account not found.'];
        }

        QueryBuilder::table('users')
            ->where('id', '=', $userId)
            ->update(['name' => $name]);

        ClinicSettingsService::syncListingDoctorNameIfOwner($clinicId, $userId, $name);
        self::refreshSessionUser($userId, $name);

        return ['ok' => true];
    }

    /** Read the current display name from users.name. */
    public static function displayName(int $userId): string
    {
        $row = QueryBuilder::table('users')
            ->where('id', '=', $userId)
            ->first();

        return trim((string) ($row['name'] ?? ''));
    }

    private static function refreshSessionUser(int $userId, string $name): void
    {
        $sessionUser = RequestContext::user();
        if ($sessionUser === null || (int) ($sessionUser['id'] ?? 0) !== $userId) {
            return;
        }
        $sessionUser['name'] = $name;
        RequestContext::setUser($sessionUser);
    }
}
