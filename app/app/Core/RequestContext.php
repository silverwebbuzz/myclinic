<?php

declare(strict_types=1);

namespace App\Core;

/** @phpstan-type ClinicRow array<string, mixed> */
final class RequestContext
{
    private static ?array $clinic = null;

    private static ?array $user = null;

    /** @var array<string, mixed>|null */
    private static ?array $portalPatient = null;

    private static ?array $superAdmin = null;
    private static ?array $partner = null;

    /** @var array{clinic_id: int, scopes: list<string>, key_id: int}|null */
    private static ?array $apiAuth = null;

    private static ?array $impersonation = null;

    /**
     * A JWT that RefreshTokenMiddleware minted DURING this request (because
     * the cookie one had expired). setcookie() only affects the NEXT request,
     * so later middleware (tenant, auth) can't see the new token via $_COOKIE.
     * They read this payload instead so a mid-request refresh doesn't surface
     * as "Clinic not found" / 401 on the very request that refreshed.
     *
     * @var array{user: array<string, mixed>, payload: array<string, mixed>}|null
     */
    private static ?array $refreshedAuth = null;

    /** @param ClinicRow $clinic */
    public static function setClinic(array $clinic): void
    {
        self::$clinic = $clinic;
    }

    public static function clinic(): ?array
    {
        return self::$clinic;
    }

    public static function clinicId(): ?int
    {
        return isset(self::$clinic['id']) ? (int) self::$clinic['id'] : null;
    }

    public static function setUser(?array $user): void
    {
        self::$user = $user;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    /** @param array<string, mixed> $patient */
    public static function setPortalPatient(array $patient): void
    {
        self::$portalPatient = $patient;
    }

    /** @return array<string, mixed>|null */
    public static function portalPatient(): ?array
    {
        return self::$portalPatient;
    }

    public static function portalPatientId(): ?int
    {
        return isset(self::$portalPatient['id']) ? (int) self::$portalPatient['id'] : null;
    }

    public static function setSuperAdmin(array $admin): void
    {
        self::$superAdmin = $admin;
    }

    public static function superAdmin(): ?array
    {
        return self::$superAdmin;
    }

    public static function setPartner(array $partner): void
    {
        self::$partner = $partner;
    }

    public static function partner(): ?array
    {
        return self::$partner;
    }

    public static function partnerId(): ?int
    {
        return isset(self::$partner['id']) ? (int) self::$partner['id'] : null;
    }

    /** @param array{clinic_id: int, scopes: list<string>, key_id: int} $auth */
    public static function setApiAuth(array $auth): void
    {
        self::$apiAuth = $auth;
    }

    /** @return array{clinic_id: int, scopes: list<string>, key_id: int}|null */
    public static function apiAuth(): ?array
    {
        return self::$apiAuth;
    }

    /** @param array<string, mixed> $info */
    public static function setImpersonation(array $info): void
    {
        self::$impersonation = $info;
    }

    public static function impersonation(): ?array
    {
        return self::$impersonation;
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $payload decoded JWT claims (sub, clinic_id, role)
     */
    public static function setRefreshedAuth(array $user, array $payload): void
    {
        self::$refreshedAuth = ['user' => $user, 'payload' => $payload];
    }

    /** @return array{user: array<string, mixed>, payload: array<string, mixed>}|null */
    public static function refreshedAuth(): ?array
    {
        return self::$refreshedAuth;
    }

    public static function reset(): void
    {
        self::$clinic = null;
        self::$user = null;
        self::$portalPatient = null;
        self::$superAdmin = null;
        self::$apiAuth = null;
        self::$impersonation = null;
        self::$refreshedAuth = null;
    }
}
