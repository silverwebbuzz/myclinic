<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * Plan — single source of truth for "what can this clinic access?"
 *
 * Replaces scattered `if ($tenant['plan'] === 'enterprise')` checks
 * across the codebase. Reads:
 *   - tenants.plan_expires_at / trial_ends_at — is the clinic billable?
 *   - clinic_modules                          — paid add-ons (Patient Connect, Clinic Network)
 *   - feature_flags                           — Bucket-3 features (Lab, Pharmacy, AI, etc.)
 *
 * Pricing model after Phase 1 is one plan ('standard'). The legacy
 * 'free/clinic/practice/enterprise' enum is collapsed; this class
 * never branches on plan tier — only on active/inactive + add-ons + flags.
 */
final class Plan
{
    /** Cache for hot-path lookups inside a single request. */
    private static array $addonCache = [];
    private static array $flagCache = [];

    /**
     * Is this clinic currently allowed to use paid features?
     * True when paid OR within trial OR within admin-granted extension.
     */
    public static function isActive(array $tenant): bool
    {
        $today = date('Y-m-d');

        if (!empty($tenant['plan_expires_at']) && $tenant['plan_expires_at'] >= $today) {
            return true;
        }
        if (!empty($tenant['trial_ends_at']) && $tenant['trial_ends_at'] >= $today) {
            return true;
        }

        return false;
    }

    public static function isInTrial(array $tenant): bool
    {
        if (empty($tenant['trial_ends_at'])) {
            return false;
        }
        // Trial counts when there's no paid expiry, or paid expiry hasn't been set yet.
        $paidExpiry = $tenant['plan_expires_at'] ?? null;
        if ($paidExpiry && $paidExpiry >= date('Y-m-d')) {
            return false;
        }

        return $tenant['trial_ends_at'] >= date('Y-m-d');
    }

    public static function trialDaysLeft(array $tenant): int
    {
        if (empty($tenant['trial_ends_at'])) {
            return 0;
        }
        $diff = (strtotime($tenant['trial_ends_at']) - strtotime(date('Y-m-d'))) / 86400;

        return max(0, (int) ceil($diff));
    }

    public static function isFoundingClinic(array $tenant): bool
    {
        if (empty($tenant['is_founding_clinic'])) {
            return false;
        }
        // Founding price locks for 24 months; after that, standard price applies.
        $until = $tenant['founding_clinic_locked_until'] ?? null;

        return $until === null || $until >= date('Y-m-d');
    }

    /**
     * Does this clinic currently have an active add-on?
     * Reads clinic_modules: is_active=1 AND not expired.
     */
    public static function hasAddon(int $clinicId, string $moduleId): bool
    {
        $key = $clinicId . ':' . $moduleId;
        if (array_key_exists($key, self::$addonCache)) {
            return self::$addonCache[$key];
        }

        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM clinic_modules
              WHERE clinic_id = :cid
                AND module_id = :mid
                AND is_active = 1
                AND (expires_at IS NULL OR expires_at >= CURDATE())
              LIMIT 1'
        );
        $stmt->execute([':cid' => $clinicId, ':mid' => $moduleId]);

        return self::$addonCache[$key] = (bool) $stmt->fetchColumn();
    }

    /**
     * Is a Bucket-3 feature enabled for this clinic?
     * Scope rules:
     *   'all'    — enabled for everyone if is_enabled=1
     *   'beta'   — enabled only if clinic_id is in beta_tenant_ids JSON list
     *   'tenant' — reserved for future per-tenant overrides
     */
    public static function hasFeatureFlag(int $clinicId, string $flagKey): bool
    {
        $key = $clinicId . ':' . $flagKey;
        if (array_key_exists($key, self::$flagCache)) {
            return self::$flagCache[$key];
        }

        $stmt = Database::connection()->prepare(
            'SELECT is_enabled, scope, beta_tenant_ids
               FROM feature_flags
              WHERE flag_key = :k
              LIMIT 1'
        );
        $stmt->execute([':k' => $flagKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['is_enabled']) {
            return self::$flagCache[$key] = false;
        }

        $scope = $row['scope'];
        if ($scope === 'all') {
            return self::$flagCache[$key] = true;
        }
        if ($scope === 'beta') {
            $betaIds = $row['beta_tenant_ids'] ? json_decode((string) $row['beta_tenant_ids'], true) : [];

            return self::$flagCache[$key] = is_array($betaIds) && in_array($clinicId, $betaIds, true);
        }
        // 'tenant' scope — not yet implemented; treat as off.
        return self::$flagCache[$key] = false;
    }

    /**
     * Test seam — clears caches. Used in tests; don't call from prod code.
     */
    public static function flushCache(): void
    {
        self::$addonCache = [];
        self::$flagCache = [];
    }
}
