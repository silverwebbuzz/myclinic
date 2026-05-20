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

    /** @var array{clinic_id: int, scopes: list<string>, key_id: int}|null */
    private static ?array $apiAuth = null;

    private static ?array $impersonation = null;

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

    public static function reset(): void
    {
        self::$clinic = null;
        self::$user = null;
        self::$portalPatient = null;
        self::$superAdmin = null;
        self::$apiAuth = null;
        self::$impersonation = null;
    }
}
