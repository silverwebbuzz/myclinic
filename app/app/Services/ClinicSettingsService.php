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

        $country = strtoupper($post['country_code'] ?? 'IN');
        $tenantUpdate = [
            'name' => trim($post['clinic_name'] ?? ''),
            'address' => trim($post['address'] ?? ''),
            'phone' => trim($post['phone'] ?? ''),
            'email' => trim($post['email'] ?? ''),
            'gstin' => trim($post['gstin'] ?? '') ?: null,
            'country_code' => $country,
            'currency' => $post['currency'] ?? OnboardingService::currencyForCountry($country),
            'timezone' => $post['timezone'] ?? 'Asia/Kolkata',
            'brand_color' => $post['brand_color'] ?? '#0F9B6E',
        ];

        if ($file !== null && !empty($file['tmp_name'])) {
            $logoPath = StorageService::storeLogo($clinicId, $file);
            if ($logoPath !== null) {
                $tenantUpdate['logo_path'] = $logoPath;
            }
        }

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update($tenantUpdate);

        self::ensureSpecialtyConfigRow($clinicId);
        QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update([
            'uhid_prefix' => $uhidPrefix,
            'invoice_prefix' => strtoupper(substr($post['invoice_prefix'] ?? 'INV', 0, 10)),
            'consultation_fee' => (float) ($post['consultation_fee'] ?? 0),
            'invoice_tax_label' => $post['invoice_tax_label'] ?? OnboardingService::taxLabelForCountry($country),
            'invoice_tax_percent' => (float) ($post['invoice_tax_percent'] ?? 0),
        ]);

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
            $doctorIds = DoctorScheduleService::doctorIdsForClinic($clinicId);
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
            }
        }

        $options = SpecialtyOptionsParser::fromPost($specialty, $post);
        QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update([
            'specialty_options' => json_encode($options),
            'slot_duration_min' => (int) ($options['slot_duration'] ?? 15),
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
            'lab_report_ready' => !empty($post['lab_report_ready']),
            'follow_up_reminder' => !empty($post['follow_up_reminder']),
            'whatsapp_mode' => $post['whatsapp_mode'] ?? 'shared',
        ];

        $update = [
            'notification_prefs' => json_encode($prefs),
            'whatsapp_number' => trim($post['whatsapp_number'] ?? '') ?: null,
        ];

        if (!empty($post['whatsapp_token'])) {
            $update['whatsapp_token'] = trim($post['whatsapp_token']);
        }
        if (!empty($post['razorpay_key']) && !empty($post['razorpay_secret'])) {
            $update['razorpay_key'] = trim($post['razorpay_key']);
            $update['razorpay_secret'] = trim($post['razorpay_secret']);
        }

        QueryBuilder::table('specialty_configs')->where('clinic_id', '=', $clinicId)->update($update);
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
