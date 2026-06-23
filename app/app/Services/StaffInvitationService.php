<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Core\RequestContext;

final class StaffInvitationService
{
    /** @return list<array<string, mixed>> */
    public static function listForClinic(int $clinicId): array
    {
        return QueryBuilder::table('staff_invitations')
            ->forClinic($clinicId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function staffList(int $clinicId): array
    {
        return QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '!=', 'patient')
            ->orderBy('name', 'ASC')
            ->get();
    }

    public static function invite(int $clinicId, string $name, string $email, string $role): array
    {
        if (!SeatService::canAddStaff($clinicId)) {
            throw new \RuntimeException('Seat limit reached. Upgrade your plan or purchase extra seats.');
        }

        $existing = QueryBuilder::table('users')->where('email', '=', $email)->first();
        if ($existing !== null) {
            throw new \RuntimeException('A user with this email already exists.');
        }

        $token = bin2hex(random_bytes(32));
        $user = RequestContext::user();

        $id = QueryBuilder::table('staff_invitations')->insert([
            'clinic_id' => $clinicId,
            'invited_by' => $user['id'] ?? 1,
            'name' => $name,
            'email' => $email,
            'role' => in_array($role, ['doctor', 'nurse', 'receptionist', 'labtech'], true) ? $role : 'receptionist',
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'status' => 'pending',
        ]);

        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        $acceptUrl = $appUrl . '/accept-invite/' . $token;

        MailService::send($email, 'staff_invite', [
            'name' => $name,
            'clinic_name' => $clinic['name'] ?? 'Clinic',
            'accept_url' => $acceptUrl,
            'role' => $role,
        ], $clinicId);

        return QueryBuilder::table('staff_invitations')->where('id', '=', $id)->first() ?? [];
    }

    public static function findByToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $row = QueryBuilder::table('staff_invitations')
            ->where('token_hash', '=', $hash)
            ->where('status', '=', 'pending')
            ->first();

        if ($row === null) {
            return null;
        }

        if (strtotime($row['expires_at']) < time()) {
            QueryBuilder::table('staff_invitations')
                ->where('id', '=', (int) $row['id'])
                ->update(['status' => 'expired']);

            return null;
        }

        $row['clinic'] = QueryBuilder::table('tenants')->where('id', '=', (int) $row['clinic_id'])->first();

        return $row;
    }

    public static function accept(string $token, string $password): array
    {
        $invite = self::findByToken($token);
        if ($invite === null) {
            throw new \RuntimeException('Invalid or expired invitation');
        }

        $clinicId = (int) $invite['clinic_id'];
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $userId = QueryBuilder::table('users')->insert([
            'clinic_id' => $clinicId,
            'name' => $invite['name'],
            'email' => $invite['email'],
            'password_hash' => $passwordHash,
            'role' => $invite['role'],
            'is_active' => 1,
        ]);

        QueryBuilder::table('staff_invitations')
            ->where('id', '=', (int) $invite['id'])
            ->update([
                'status' => 'accepted',
                'accepted_at' => date('Y-m-d H:i:s'),
                'created_user_id' => $userId,
            ]);

        return QueryBuilder::table('users')->where('id', '=', $userId)->first() ?? [];
    }

    public static function revoke(int $clinicId, int $inviteId): void
    {
        QueryBuilder::table('staff_invitations')
            ->forClinic($clinicId)
            ->where('id', '=', $inviteId)
            ->update(['status' => 'revoked']);
    }

    public static function updateStaff(int $clinicId, int $userId, array $data): void
    {
        $update = [];
        if (isset($data['role'])) {
            $update['role'] = $data['role'];
        }
        if (array_key_exists('custom_permissions', $data)) {
            $update['custom_permissions'] = is_array($data['custom_permissions'])
                ? json_encode($data['custom_permissions'])
                : $data['custom_permissions'];
        }
        if (isset($data['is_active'])) {
            $update['is_active'] = (int) $data['is_active'];
        }

        if ($update !== []) {
            QueryBuilder::table('users')
                ->forClinic($clinicId)
                ->where('id', '=', $userId)
                ->where('is_owner', '=', 0)
                ->update($update);
        }
    }

    /** @return array{user_id: int, username: string, password: string, name: string} */
    public static function createAccount(int $clinicId, string $name, ?string $username, string $role): array
    {
        if (!SeatService::canAddStaff($clinicId)) {
            throw new \RuntimeException('Seat limit reached. Upgrade your plan or purchase extra seats.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Name is required.');
        }

        $role = in_array($role, ['doctor', 'nurse', 'receptionist', 'labtech'], true) ? $role : 'receptionist';
        $baseUsername = $username !== null && trim($username) !== ''
            ? self::normalizeUsername($username)
            : self::suggestUsername($name);
        $finalUsername = self::uniqueUsername($baseUsername);
        $password = self::generateTempPassword();

        $userId = QueryBuilder::table('users')->insert([
            'clinic_id' => $clinicId,
            'name' => $name,
            'email' => null,
            'username' => $finalUsername,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'is_active' => 1,
            'must_change_password' => 1,
        ]);

        return [
            'user_id' => $userId,
            'username' => $finalUsername,
            'password' => $password,
            'name' => $name,
        ];
    }

    /** @return array{username: ?string, email: ?string, password: string, name: string} */
    public static function resetStaffPassword(int $clinicId, int $userId): array
    {
        $user = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('id', '=', $userId)
            ->where('is_owner', '=', 0)
            ->first();

        if ($user === null) {
            throw new \RuntimeException('Staff member not found.');
        }

        $password = self::generateTempPassword();
        QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('id', '=', $userId)
            ->update([
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'must_change_password' => 1,
            ]);

        return [
            'username' => $user['username'] ?? null,
            'email' => !empty($user['email']) ? (string) $user['email'] : null,
            'password' => $password,
            'name' => (string) $user['name'],
        ];
    }

    public static function suggestUsername(string $name): string
    {
        $parts = preg_split('/\s+/', strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
        $base = $parts[0] ?? 'staff';
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'staff';
        if (strlen($base) < 3) {
            $base = 'staff' . substr(bin2hex(random_bytes(2)), 0, 3);
        }

        return substr($base, 0, 26);
    }

    private static function normalizeUsername(string $username): string
    {
        $username = strtolower(trim($username));
        $username = preg_replace('/[^a-z0-9_]/', '_', $username);
        $username = preg_replace('/_+/', '_', $username);
        $username = trim($username, '_');

        if ($username === '' || strlen($username) < 3) {
            throw new \RuntimeException('Username must be at least 3 characters (letters, numbers, underscore).');
        }
        if (strlen($username) > 30) {
            $username = substr($username, 0, 30);
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $username)) {
            throw new \RuntimeException('Username must start with a letter and use only lowercase letters, numbers, and underscores.');
        }

        return $username;
    }

    private static function uniqueUsername(string $base): string
    {
        if (QueryBuilder::table('users')->where('username', '=', $base)->first() === null) {
            return $base;
        }

        for ($i = 2; $i <= 99; $i++) {
            $candidate = substr($base, 0, 28) . $i;
            if (QueryBuilder::table('users')->where('username', '=', $candidate)->first() === null) {
                return $candidate;
            }
        }

        return substr($base, 0, 24) . bin2hex(random_bytes(3));
    }

    private static function generateTempPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $all = $upper . $lower . $digits;
        $password = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)];

        for ($i = 0; $i < 7; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }
}
