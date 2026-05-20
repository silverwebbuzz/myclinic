<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

final class PasswordResetService
{
    public static function request(string $email): void
    {
        $user = AuthService::findUserByEmail($email);
        if ($user === null) {
            return;
        }

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        QueryBuilder::table('password_reset_tokens')
            ->where('email', '=', $email)
            ->where('used_at', 'IS', null)
            ->delete();

        QueryBuilder::table('password_reset_tokens')->insert([
            'email' => $email,
            'token_hash' => $hash,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        MailService::send($email, 'password_reset', [
            'reset_url' => "{$base}/reset-password/{$raw}",
        ], (int) ($user['clinic_id'] ?? 0));
    }

    public static function findValidEmail(string $rawToken): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);
        $row = QueryBuilder::table('password_reset_tokens')
            ->where('token_hash', '=', $hash)
            ->where('used_at', 'IS', null)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        return $row['email'] ?? null;
    }

    public static function reset(string $rawToken, string $newPassword): bool
    {
        $email = self::findValidEmail($rawToken);
        if ($email === null) {
            return false;
        }

        $user = AuthService::findUserByEmail($email);
        if ($user === null) {
            return false;
        }

        $hash = hash('sha256', $rawToken);
        QueryBuilder::table('password_reset_tokens')
            ->where('token_hash', '=', $hash)
            ->update(['used_at' => date('Y-m-d H:i:s')]);

        QueryBuilder::table('users')->where('id', '=', (int) $user['id'])->update([
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'remember_token' => null,
        ]);

        SessionService::revokeAllForUser((int) $user['id']);

        return true;
    }
}
