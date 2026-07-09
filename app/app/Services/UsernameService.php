<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

/**
 * Clinic login usernames — unique, optional at registration (auto from phone).
 */
final class UsernameService
{
    /** 10-digit local mobile from a normalized E.164 phone. */
    public static function defaultFromPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', DoctorOtpService::normalizePhone($phone)) ?? '';
        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : '';
    }

    public static function isAvailable(string $username): bool
    {
        $username = strtolower(trim($username));
        if ($username === '') {
            return false;
        }

        return QueryBuilder::table('users')->where('username', '=', $username)->count() === 0;
    }

    /**
     * Normalize user input. Returns null when invalid.
     */
    public static function normalize(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (self::looksLikePhone($raw)) {
            $local = self::defaultFromPhone($raw);
            return $local !== '' ? $local : null;
        }

        $username = strtolower($raw);
        $username = preg_replace('/[^a-z0-9_]/', '_', $username) ?? '';
        $username = preg_replace('/_+/', '_', $username) ?? '';
        $username = trim($username, '_');

        if ($username === '' || strlen($username) < 3) {
            return null;
        }
        if (strlen($username) > 30) {
            $username = substr($username, 0, 30);
        }
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $username)) {
            return null;
        }

        return $username;
    }

    /**
     * Resolve the username to store at registration.
     * Blank input → phone digits (unique); explicit input must be available.
     */
    public static function resolveForRegistration(string $rawInput, string $phone, string $ownerName): string
    {
        $rawInput = trim($rawInput);
        if ($rawInput === '') {
            $base = self::defaultFromPhone($phone);
            if ($base === '') {
                $base = self::suggestFromName($ownerName);
            }

            return self::unique($base);
        }

        $normalized = self::normalize($rawInput);
        if ($normalized === null) {
            throw new \InvalidArgumentException('invalid_username');
        }
        if (!self::isAvailable($normalized)) {
            throw new \InvalidArgumentException('username_taken');
        }

        return $normalized;
    }

  /** @return array{available: bool, reason?: string, username?: string} */
    public static function check(string $raw): array
    {
        $normalized = self::normalize($raw);
        if ($normalized === null) {
            return ['available' => false, 'reason' => 'invalid'];
        }

        return [
            'available' => self::isAvailable($normalized),
            'username' => $normalized,
        ];
    }

    public static function unique(string $base): string
    {
        $base = strtolower(trim($base));
        if ($base === '') {
            $base = 'user' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        if (strlen($base) > 30) {
            $base = substr($base, 0, 30);
        }
        if (self::isAvailable($base)) {
            return $base;
        }

        for ($i = 2; $i <= 99; $i++) {
            $suffix = (string) $i;
            $candidate = substr($base, 0, 30 - strlen($suffix)) . $suffix;
            if (self::isAvailable($candidate)) {
                return $candidate;
            }
        }

        return substr($base, 0, 24) . bin2hex(random_bytes(3));
    }

    public static function suggestFromName(string $name): string
    {
        $parts = preg_split('/\s+/', strtolower(trim($name)), -1, PREG_SPLIT_NO_EMPTY);
        $base = $parts[0] ?? 'clinic';
        $base = preg_replace('/[^a-z0-9]/', '', $base) ?: 'clinic';
        if (strlen($base) < 3) {
            $base = 'dr' . substr(bin2hex(random_bytes(2)), 0, 3);
        }

        return substr($base, 0, 26);
    }

    private static function looksLikePhone(string $raw): bool
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return false;
        }

        return strlen($digits) >= 10 && preg_match('/^[\d\s\+\-\(\)]+$/', $raw) === 1;
    }
}
