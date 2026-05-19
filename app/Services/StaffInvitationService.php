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
}
