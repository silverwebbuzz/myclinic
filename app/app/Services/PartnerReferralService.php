<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;

/**
 * Referral attribution. A visitor arriving via ?ref=CODE has the code stored
 * in a cookie for `cookie_window_days`. At registration we resolve the
 * partner (typed code wins over cookie) and record a first-touch referral
 * linking the partner to the new clinic.
 */
final class PartnerReferralService
{
    private const COOKIE = 'mc_ref';

    /**
     * Capture a ?ref= code into the referral cookie. Call early in the public
     * request lifecycle (or from the public landing pages).
     */
    public static function captureFromQuery(?string $refCode): void
    {
        $code = strtoupper(trim((string) $refCode));
        if ($code === '' || strlen($code) > 20) {
            return;
        }

        // Only set if it resolves to an active partner — avoids junk cookies.
        $partner = PartnerService::findByCode($code);
        if ($partner === null || ($partner['status'] ?? '') !== 'active') {
            return;
        }

        $days = PartnerSettingsService::cookieWindowDays();
        $secure = ($_ENV['APP_ENV'] ?? 'local') !== 'local';
        setcookie(self::COOKIE, $code, [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => $secure,
            'httponly' => false, // readable by the registration form JS if needed
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Resolve the attributing partner at registration time.
     * Typed code wins; otherwise fall back to the cookie.
     *
     * @return array{partner: array<string,mixed>, code: string, via: string}|null
     */
    public static function resolveForRegistration(?string $typedCode, array $cookies): ?array
    {
        $typed = strtoupper(trim((string) $typedCode));
        if ($typed !== '') {
            $partner = PartnerService::findByCode($typed);
            if ($partner !== null && ($partner['status'] ?? '') === 'active') {
                return ['partner' => $partner, 'code' => $typed, 'via' => 'code'];
            }
        }

        $cookieCode = strtoupper(trim((string) ($cookies[self::COOKIE] ?? '')));
        if ($cookieCode !== '') {
            $partner = PartnerService::findByCode($cookieCode);
            if ($partner !== null && ($partner['status'] ?? '') === 'active') {
                return ['partner' => $partner, 'code' => $cookieCode, 'via' => 'link'];
            }
        }

        return null;
    }

    /**
     * Record a first-touch referral. No-op if the clinic is already attributed
     * (unique key on tenant_id), so calling twice is safe.
     */
    public static function attribute(int $partnerId, int $tenantId, string $code, string $via): void
    {
        $existing = QueryBuilder::table('partner_referrals')
            ->where('tenant_id', '=', $tenantId)
            ->first();
        if ($existing !== null) {
            return; // first-touch wins
        }

        QueryBuilder::table('partner_referrals')->insert([
            'partner_id' => $partnerId,
            'tenant_id' => $tenantId,
            'referral_code_used' => $code,
            'attributed_via' => in_array($via, ['link', 'code'], true) ? $via : 'link',
            'status' => 'pending',
        ]);
    }

    /** The active referral for a clinic, or null if it wasn't referred. */
    public static function forTenant(int $tenantId): ?array
    {
        return QueryBuilder::table('partner_referrals')
            ->where('tenant_id', '=', $tenantId)
            ->first();
    }
}
