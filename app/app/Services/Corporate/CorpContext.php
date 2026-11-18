<?php

declare(strict_types=1);

namespace App\Services\Corporate;

use App\Support\Locale\Locale;

/**
 * The signed-in company user for this request (/company/*), set by
 * CorpAuthMiddleware. Kept apart from RequestContext (clinic users) on purpose:
 * company users and clinic users are different people with different rights.
 */
final class CorpContext
{
    /** @var array<string, mixed>|null */
    private static ?array $company = null;

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /** @var array<string, mixed>|null */
    private static ?array $session = null;

    private static ?Locale $locale = null;

    /**
     * @param array<string, mixed> $company
     * @param array<string, mixed> $user
     * @param array<string, mixed> $session
     */
    public static function set(array $company, array $user, array $session): void
    {
        self::$company = $company;
        self::$user = $user;
        self::$session = $session;
        self::$locale = null;
    }

    /** @return array<string, mixed>|null */
    public static function company(): ?array
    {
        return self::$company;
    }

    public static function companyId(): ?int
    {
        return self::$company !== null ? (int) self::$company['id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        return self::$user;
    }

    public static function userId(): ?int
    {
        return self::$user !== null ? (int) self::$user['id'] : null;
    }

    /** @return array<string, mixed>|null */
    public static function session(): ?array
    {
        return self::$session;
    }

    /** Display context (timezone, currency, formats) for the signed-in user. */
    public static function locale(): Locale
    {
        if (self::$locale === null) {
            self::$locale = Locale::forCompany(self::$company ?? [], self::$user);
        }

        return self::$locale;
    }

    public static function reset(): void
    {
        self::$company = null;
        self::$user = null;
        self::$session = null;
        self::$locale = null;
    }
}
