<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\QueryBuilder;
use App\Gates\ModuleGate;
use App\Support\SpecialtyOptionsParser;
use App\Support\WorkingHoursParser;

final class ClinicSettingsService
{
    /** @param array<string, mixed> $post */
    public static function saveGeneral(int $clinicId, array $post, ?array $file): void
    {
        $uhidPrefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($post['uhid_prefix'] ?? 'MC')), 0, 6));
        if ($uhidPrefix === '') {
            $uhidPrefix = 'MC';
        }

        $clinicName = trim((string) ($post['clinic_name'] ?? ''));
        $country = strtoupper($post['country_code'] ?? 'IN');
        $tenantUpdate = [
            'name' => $clinicName,
            'phone' => trim($post['phone'] ?? ''),
            'email' => trim($post['email'] ?? ''),
            'gstin' => trim($post['gstin'] ?? '') ?: null,
            'country_code' => $country,
            'currency' => $post['currency'] ?? OnboardingService::currencyForCountry($country),
            'timezone' => $post['timezone'] ?? 'Asia/Kolkata',
            'brand_color' => $post['brand_color'] ?? '#0F766E',
        ];

        if ($file !== null && !empty($file['tmp_name'])) {
            $logoPath = StorageService::storeLogo($clinicId, $file);
            if ($logoPath !== null) {
                $tenantUpdate['logo_path'] = $logoPath;
            }
        }

        try {
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update(array_merge($tenantUpdate, [
                'registration_number' => trim($post['registration_number'] ?? '') ?: null,
            ]));
        } catch (\Throwable $e) {
            // registration_number column missing (migration 024 not applied).
            QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update($tenantUpdate);
        }

        // Keep the public directory row in sync with Settings → General clinic name.
        // No-op when this clinic has no claimed listing yet.
        QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->update([
                'name' => $clinicName,
                'phone' => trim($post['phone'] ?? '') ?: null,
            ]);

        self::ensureSpecialtyConfigRow($clinicId);
        $configUpdate = [
            'uhid_prefix' => $uhidPrefix,
            'invoice_prefix' => strtoupper(substr($post['invoice_prefix'] ?? 'INV', 0, 10)),
            'invoice_tax_label' => $post['invoice_tax_label'] ?? OnboardingService::taxLabelForCountry($country),
            'invoice_tax_percent' => (float) ($post['invoice_tax_percent'] ?? 0),
        ];

        try {
            QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update(array_merge($configUpdate, [
                'rx_header_text' => trim((string) ($post['rx_header_text'] ?? '')) ?: null,
                'rx_footer_text' => trim((string) ($post['rx_footer_text'] ?? '')) ?: null,
            ]));
        } catch (\Throwable $e) {
            // rx_* columns missing (migration 024 not applied) — save the rest.
            QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update($configUpdate);
        }

        OnboardingService::refreshClinicContext($clinicId);
        DashboardService::invalidateStats($clinicId);
    }

    /** @param array<string, mixed> $post */
    public static function saveHours(int $clinicId, array $post): void
    {
        // Prefer grouped form (new UI). Fall back to per-day if those keys are present.
        $isGrouped = array_key_exists('weekday_morning_enabled', $post)
            || array_key_exists('weekday_evening_enabled', $post)
            || array_key_exists('sunday_open', $post);
        $workingHours = $isGrouped
            ? WorkingHoursParser::fromGroupedPost($post)
            : WorkingHoursParser::fromPost($post);

        self::ensureSpecialtyConfigRow($clinicId);

        $slotDuration = (int) ($post['slot_duration_min'] ?? 15);
        if (!in_array($slotDuration, [15, 30], true)) {
            $slotDuration = 15;
        }
        $bookingWindow = (int) ($post['booking_window_days'] ?? 30);
        if (!in_array($bookingWindow, [7, 15, 30, 60, 90], true)) {
            $bookingWindow = 30;
        }

        QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update([
            'working_hours' => json_encode($workingHours),
            'slot_duration_min' => $slotDuration,
            'booking_window_days' => $bookingWindow,
        ]);

        try {
            $doctorIds = array_map(
                static fn (array $d) => (int) $d['id'],
                AppointmentService::doctorsForClinic($clinicId),
            );
            DoctorScheduleService::syncFromWorkingHours($clinicId, $workingHours, $doctorIds, $slotDuration);
        } catch (\Throwable $e) {
            error_log('[saveHours] doctor schedule sync failed: ' . $e->getMessage());
        }
        OnboardingService::refreshClinicContext($clinicId);
    }

    private static function ensureSpecialtyConfigRow(int $clinicId): void
    {
        $existing = QueryBuilder::table('specialty_configs')
            ->where('clinic_id', '=', $clinicId)
            ->first();
        if ($existing !== null) {
            return;
        }
        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first();
        $specialty = $clinic['specialty'] ?? 'gp';
        $specConfig = \App\Support\SpecialtyCatalog::all();
        QueryBuilder::table('specialty_configs')->insert([
            'clinic_id' => $clinicId,
            'specialty' => $specialty,
            'prescription_mode' => $specConfig[$specialty]['prescription_mode'] ?? 'allopathic',
            'specialty_options' => json_encode([]),
            'working_hours' => json_encode(OnboardingService::defaultWorkingHours()),
            'uhid_prefix' => 'MC',
            'invoice_prefix' => 'INV',
            'consultation_fee' => 0,
            'invoice_tax_label' => 'Tax',
            'invoice_tax_percent' => 0,
            'notification_prefs' => json_encode([]),
            'booking_window_days' => 30,
        ]);
    }

    /** @param array<string, mixed> $post */
    public static function saveSpecialty(int $clinicId, string $specialty, array $post, bool $changeSpecialty): void
    {
        if ($changeSpecialty) {
            $specialties = array_keys(\App\Support\SpecialtyCatalog::all(true));
            if (in_array($post['specialty'] ?? '', $specialties, true)) {
                $specialty = $post['specialty'];
                QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update(['specialty' => $specialty]);
                $specConfig = \App\Support\SpecialtyCatalog::all();
                QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update([
                    'prescription_mode' => $specConfig[$specialty]['prescription_mode'] ?? 'allopathic',
                    'specialty_options' => json_encode([]),
                ]);

                // Keep the public directory listing in sync. The front-end
                // profile reads specialty from directory_doctors, so without
                // this the panel and the public page diverge. Only the claimed
                // clinic's own row is touched.
                QueryBuilder::table('directory_doctors')
                    ->where('claimed_tenant_id', '=', $clinicId)
                    ->update(['specialty' => $specialty]);
            }
        }

        // Slot duration is owned by Working hours (saveHours). Don't touch
        // slot_duration_min here, or saving specialty options would silently
        // reset the doctor's configured slot length back to the default.
        $options = SpecialtyOptionsParser::fromPost($specialty, $post);
        QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update([
            'specialty_options' => json_encode($options),
        ]);

        OnboardingService::refreshClinicContext($clinicId);
    }

    /** @param array<string, mixed> $post */
    public static function saveNotifications(int $clinicId, array $post): void
    {
        $prefs = [
            'appointment_reminder_24h' => !empty($post['appointment_reminder_24h']),
            'appointment_reminder_1h' => !empty($post['appointment_reminder_1h']),
            'rx_delivery' => !empty($post['rx_delivery']),
            'follow_up_reminder' => !empty($post['follow_up_reminder']),
        ];

        QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update([
            'notification_prefs' => json_encode($prefs),
        ]);
    }

    /**
     * Services this clinic offers — shown on its public directory profile.
     * Stored as a JSON array on the clinic's claimed directory_doctors row.
     * Returns [] when the clinic has no claimed listing or none are set.
     *
     * @return list<string>
     */
    public static function servicesForClinic(int $clinicId): array
    {
        $listing = QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->first();

        if ($listing === null || empty($listing['services'])) {
            return [];
        }

        $decoded = json_decode((string) $listing['services'], true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($s): string => trim((string) $s),
            $decoded,
        ), static fn (string $s): bool => $s !== ''));
    }

    /**
     * Save the clinic's services list to its claimed directory listing.
     * Accepts either a newline-separated textarea ('services_text') or an
     * array ('services[]'). No-ops silently when the clinic hasn't claimed
     * a directory listing yet (nothing to attach the services to).
     */
    public static function saveServices(int $clinicId, array $post): void
    {
        $raw = $post['services'] ?? $post['services_text'] ?? '';
        if (is_string($raw)) {
            $items = preg_split('/[\r\n]+/', $raw) ?: [];
        } else {
            $items = is_array($raw) ? $raw : [];
        }

        $clean = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item !== '' && !in_array($item, $clean, true)) {
                $clean[] = mb_substr($item, 0, 80);
            }
            if (count($clean) >= 24) {
                break;
            }
        }

        QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->update(['services' => $clean === [] ? null : json_encode(array_values($clean))]);
    }

    /**
     * The clinic's claimed public directory row, or null if not listed yet.
     * Backs the Settings "Listed on eClinicPro" editor.
     *
     * Doctor name shown in the editor comes from the clinic owner's users.name
     * (source of truth), falling back to directory_doctors.doctor_name.
     *
     * @return array<string, mixed>|null
     */
    public static function publicListing(int $clinicId): ?array
    {
        $listing = QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->first();

        if ($listing === null) {
            return null;
        }

        $owner = self::listingOwnerDoctor($clinicId);
        $ownerName = trim((string) ($owner['name'] ?? ''));
        if ($ownerName !== '') {
            $listing['doctor_name'] = $ownerName;
        }

        return $listing;
    }

    /** Consultation fee from directory_doctors (source of truth for public + billing). */
    public static function consultationFeeForClinic(int $clinicId): float
    {
        $listing = QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->first();
        if ($listing === null || $listing['consultation_fee'] === null) {
            return 0.0;
        }

        return (float) $listing['consultation_fee'];
    }

    public static function consultationFeeCurrencyForClinic(int $clinicId): string
    {
        $listing = QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->first();

        return (string) ($listing['consultation_fee_currency'] ?? 'INR');
    }

    /** Persist fee on the claimed directory listing; no-op if not listed yet. */
    public static function saveConsultationFee(int $clinicId, ?float $fee, ?string $currency = null): void
    {
        $listing = QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->first();
        if ($listing === null) {
            return;
        }

        $update = [
            'consultation_fee' => $fee !== null && $fee > 0 ? $fee : null,
        ];
        if ($currency !== null && $currency !== '') {
            $update['consultation_fee_currency'] = strtoupper($currency);
        } elseif ($update['consultation_fee'] !== null && empty($listing['consultation_fee_currency'])) {
            $update['consultation_fee_currency'] = 'INR';
        }

        QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->update($update);
    }

    /**
     * Save the doctor-editable fields of the public directory listing. Changes
     * go live immediately — an approved clinic owns its verified listing, so no
     * re-review. No-op when the clinic has no claimed listing.
     *
     * @param array<string, mixed> $post
     */
    public static function saveListing(int $clinicId, array $post): void
    {
        $listing = self::publicListing($clinicId);
        if ($listing === null) {
            return; // not listed yet — nothing to edit
        }

        $fee = trim((string) ($post['consultation_fee'] ?? ''));
        $parsedFee = $fee !== '' && is_numeric($fee) ? (float) $fee : null;
        $doctorName = mb_substr(trim((string) ($post['doctor_name'] ?? '')), 0, 160);
        $update = [
            'doctor_name' => $doctorName !== '' ? $doctorName : null,
            'bio'         => trim((string) ($post['bio'] ?? '')) !== '' ? mb_substr(trim((string) $post['bio']), 0, 2000) : null,
            'address'     => trim((string) ($post['address'] ?? '')) !== '' ? mb_substr(trim((string) $post['address']), 0, 500) : null,
            'area'        => mb_substr(trim((string) ($post['area'] ?? '')), 0, 120) ?: null,
            'website'     => mb_substr(trim((string) ($post['website'] ?? '')), 0, 500) ?: null,
        ];
        if ($parsedFee !== null) {
            $update['consultation_fee'] = $parsedFee;
            if (empty($listing['consultation_fee_currency'])) {
                $update['consultation_fee_currency'] = 'INR';
            }
        } elseif (array_key_exists('consultation_fee', $post)) {
            $update['consultation_fee'] = null;
        }

        // Services list (newline-separated textarea) → JSON, same as the old
        // standalone services form. Only touched when the field is submitted.
        if (array_key_exists('services_text', $post) || array_key_exists('services', $post)) {
            $raw = $post['services_text'] ?? $post['services'] ?? '';
            $items = is_string($raw) ? (preg_split('/[\r\n]+/', $raw) ?: []) : (is_array($raw) ? $raw : []);
            $clean = [];
            foreach ($items as $item) {
                $item = trim((string) $item);
                if ($item !== '' && !in_array($item, $clean, true)) {
                    $clean[] = mb_substr($item, 0, 80);
                }
                if (count($clean) >= 24) {
                    break;
                }
            }
            $update['services'] = $clean === [] ? null : json_encode(array_values($clean));
        }

        // Languages spoken (newline-separated textarea) -> JSON array.
        if (array_key_exists('languages_text', $post) || array_key_exists('languages', $post)) {
            $raw = $post['languages_text'] ?? $post['languages'] ?? '';
            $items = is_string($raw) ? (preg_split('/[\r\n,]+/', $raw) ?: []) : (is_array($raw) ? $raw : []);
            $clean = [];
            foreach ($items as $item) {
                $item = trim((string) $item);
                if ($item !== '' && !in_array($item, $clean, true)) {
                    $clean[] = mb_substr($item, 0, 40);
                }
                if (count($clean) >= 12) {
                    break;
                }
            }
            $update['languages'] = $clean === [] ? null : json_encode(array_values($clean));
        }

        QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->update($update);

        if ($doctorName !== '') {
            $owner = self::listingOwnerDoctor($clinicId);
            if ($owner !== null) {
                UserProfileService::updateName((int) $owner['id'], $clinicId, $doctorName);
            }
        }
    }

    /**
     * When the listing owner changes their users.name, keep the public profile in sync.
     */
    public static function syncListingDoctorNameIfOwner(int $clinicId, int $userId, string $name): void
    {
        $owner = self::listingOwnerDoctor($clinicId);
        if ($owner === null || (int) ($owner['id'] ?? 0) !== $userId) {
            return;
        }

        QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->update(['doctor_name' => mb_substr(trim($name), 0, 160)]);
    }

    /** Clinic owner used as the public-facing doctor on the directory listing. */
    private static function listingOwnerDoctor(int $clinicId): ?array
    {
        $owner = QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('is_owner', '=', 1)
            ->where('is_active', '=', 1)
            ->first();

        if ($owner !== null) {
            return $owner;
        }

        return QueryBuilder::table('users')
            ->forClinic($clinicId)
            ->where('role', '=', 'doctor')
            ->where('is_active', '=', 1)
            ->orderBy('id', 'asc')
            ->first();
    }

    /** @return array{ok: bool, message: string} */
    public static function testWhatsApp(int $clinicId): array
    {
        $config = OnboardingService::specialtyConfig($clinicId);
        if (empty($config['whatsapp_number'])) {
            return ['ok' => false, 'message' => 'WhatsApp number not configured.'];
        }

        return ['ok' => true, 'message' => 'WhatsApp configured for ' . $config['whatsapp_number'] . ' (Meta API integration pending).'];
    }

    /** @return array{ok: bool, message: string} */
    public static function testRazorpay(int $clinicId): array
    {
        $config = OnboardingService::specialtyConfig($clinicId);
        $key = $config['razorpay_key'] ?? '';
        $secret = $config['razorpay_secret'] ?? '';
        if ($key === '' || $secret === '') {
            return ['ok' => false, 'message' => 'Razorpay keys not configured.'];
        }

        if (str_starts_with($key, 'rzp_test_') || str_starts_with($key, 'rzp_live_')) {
            return ['ok' => true, 'message' => 'Razorpay keys saved (live API test skipped in dev).'];
        }

        return ['ok' => false, 'message' => 'Invalid Razorpay key format.'];
    }

    /** @return list<array<string, mixed>> */
    public static function activeModulesDetail(int $clinicId): array
    {
        $rows = QueryBuilder::table('clinic_modules')
            ->forClinic($clinicId)
            ->where('is_active', '=', 1)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $catalog = QueryBuilder::table('module_catalog')
                ->where('id', '=', $row['module_id'])
                ->first();
            $out[] = array_merge($row, [
                'name' => $catalog['name'] ?? $row['module_id'],
                'price_monthly_usd' => $catalog['price_monthly_usd'] ?? 0,
            ]);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public static function saasInvoices(int $clinicId, int $limit = 10): array
    {
        return QueryBuilder::table('saas_invoices')
            ->forClinic($clinicId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }
}
