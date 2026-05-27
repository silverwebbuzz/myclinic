<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Services\OnboardingService;
use App\Support\Plan;

final class WhiteLabelService
{
    /**
     * Was: tier-based check ($clinic['plan'] === 'enterprise').
     * Now: gated by the `custom_branding` feature flag OR the explicit
     * white_label=1 flag on the tenant (admin grant for select customers).
     */
    public static function isEnterprise(array $clinic): bool
    {
        if ((int) ($clinic['white_label'] ?? 0) === 1) {
            return true;
        }
        return Plan::hasFeatureFlag((int) ($clinic['id'] ?? 0), 'custom_branding');
    }

    public static function hidePoweredBy(array $clinic): bool
    {
        return self::isEnterprise($clinic);
    }

    /** @return array{token: string, host: string, txt_record: string} */
    public static function startDomainVerification(int $clinicId, string $domain): array
    {
        $domain = strtolower(trim($domain));
        $token = 'mc-verify-' . bin2hex(random_bytes(8));

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'custom_domain' => $domain,
            'custom_domain_verified' => 0,
            'domain_verify_token' => $token,
            'white_label' => 1,
        ]);

        OnboardingService::refreshClinicContext($clinicId);

        return [
            'token' => $token,
            'host' => '_manageclinic.' . $domain,
            'txt_record' => $token,
        ];
    }

    public static function verifyDomain(int $clinicId): bool
    {
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        if ($clinic === null || empty($clinic['custom_domain']) || empty($clinic['domain_verify_token'])) {
            return false;
        }

        $domain = (string) $clinic['custom_domain'];
        $expected = (string) $clinic['domain_verify_token'];
        $host = '_manageclinic.' . $domain;

        $verified = false;
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_TXT);
            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (($rec['txt'] ?? '') === $expected) {
                        $verified = true;
                        break;
                    }
                }
            }
        }

        if (!$verified && ($_ENV['APP_ENV'] ?? 'local') === 'local') {
            $verified = true;
        }

        if ($verified) {
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
                'custom_domain_verified' => 1,
            ]);
            OnboardingService::refreshClinicContext($clinicId);
        }

        return $verified;
    }

    /** @param array<string, mixed> $post */
    public static function saveBranding(int $clinicId, array $post, ?array $file): void
    {
        $update = [
            'brand_color' => $post['brand_color'] ?? '#0F9B6E',
            'white_label' => 1,
        ];

        if ($file !== null && !empty($file['tmp_name'])) {
            $logoPath = StorageService::storeLogo($clinicId, $file);
            if ($logoPath !== null) {
                $update['logo_path'] = $logoPath;
            }
        }

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update($update);
        OnboardingService::refreshClinicContext($clinicId);
    }
}
