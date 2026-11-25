<?php

declare(strict_types=1);

namespace App\Services\Corporate;

/**
 * Password rules for company-portal logins (they will hold workforce health
 * administration, so stricter than the 8-char seller rule).
 */
final class CorpPasswordPolicy
{
    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 128;

    /** A short list of passwords that are always rejected (lower-case compare). */
    private const COMMON = [
        'password', 'password1', 'password12', 'password123', 'password1234', 'passw0rd', 'p@ssw0rd', 'p@ssword1',
        '1234567890', '12345678910', '0123456789', '1111111111', '0000000000', 'qwertyuiop', 'qwerty1234',
        'qwerty12345', 'asdfghjkl1', 'iloveyou12', 'welcome123', 'welcome@123', 'admin12345', 'admin@1234',
        'letmein123', 'abc1234567', 'abcd123456', 'changeme12', 'eclinicpro', 'eclinicpro1', 'eclinicpro123',
        'india12345', 'dubai12345', 'company123', 'company@123',
    ];

    /** Error message for the first rule broken, or null when the password is fine. */
    public static function check(string $password, string $confirm, string $email = '', string $name = ''): ?string
    {
        $len = mb_strlen($password);
        if ($len < self::MIN_LENGTH) {
            return 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
        }
        if ($len > self::MAX_LENGTH) {
            return 'Password must be at most ' . self::MAX_LENGTH . ' characters.';
        }
        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }
        $lower = mb_strtolower($password);
        if (in_array($lower, self::COMMON, true)) {
            return 'That password is too common. Please choose another.';
        }
        if (count(array_unique(mb_str_split($password))) < 4) {
            return 'Password needs more variety (at least 4 different characters).';
        }
        $local = mb_strtolower((string) strstr($email, '@', true));
        if ($local !== '' && mb_strlen($local) >= 4 && str_contains($lower, $local)) {
            return 'Password must not contain your email address.';
        }
        $nameLower = mb_strtolower(trim($name));
        if ($nameLower !== '' && mb_strlen($nameLower) >= 4 && $lower === str_replace(' ', '', $nameLower)) {
            return 'Password must not be your name.';
        }

        return null;
    }

    public static function hash(string $password): string
    {
        return defined('PASSWORD_ARGON2ID')
            ? password_hash($password, PASSWORD_ARGON2ID)
            : password_hash($password, PASSWORD_BCRYPT);
    }
}
