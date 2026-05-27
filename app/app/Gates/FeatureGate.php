<?php

declare(strict_types=1);

namespace App\Gates;

use App\Core\RequestContext;
use App\Http\Response;
use App\Support\Plan;

/**
 * FeatureGate — Bucket-3 features (Lab, Pharmacy, Radiology, CRM,
 * Incentive, AdvancedAnalytics, AI transcription, Custom branding,
 * Docs vault) are coded but not on the pricing page at launch.
 *
 * Controllers call FeatureGate::require('lab_module') at the top of
 * any action that exposes a hidden feature. Returns a 402/403 Response
 * when off — caller short-circuits with `return $denied`.
 *
 * Matches the existing ModuleGate pattern. Different concerns:
 *   ModuleGate  → paid module activated in clinic_modules
 *                 (Patient Connect, Clinic Network, legacy add-ons)
 *   FeatureGate → global feature flag in feature_flags
 *                 (Bucket-3 staged rollout)
 *
 * Some features have BOTH a flag AND a clinic_modules entry. In that
 * case the flag controls discoverability ("can this clinic even see
 * the feature exists?") and the module controls activation ("is this
 * clinic paying for it?"). Today these are 1:1 for Bucket-3 — flag
 * off → feature invisible. Once promoted to add-on, flag is forced on
 * for all and the module entry takes over billing.
 */
final class FeatureGate
{
    public static function check(string $flagKey): bool
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return false;
        }

        return Plan::hasFeatureFlag($clinicId, $flagKey);
    }

    public static function require(string $flagKey): ?Response
    {
        if (!self::check($flagKey)) {
            return Response::json([
                'error' => 'Feature not available on your plan',
                'feature' => $flagKey,
                'code' => 'FEATURE_DISABLED',
            ], 403);
        }

        return null;
    }
}
