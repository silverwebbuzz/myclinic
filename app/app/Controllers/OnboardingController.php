<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\BillingGatewayService;
use App\Services\CsrfService;
use App\Services\DoctorScheduleService;
use App\Services\OnboardingService;
use App\Services\PlanService;
use App\Services\StorageService;
use App\Support\View;

final class OnboardingController
{
    /**
     * Was a 4-tier plan picker. After Phase 1 there is only one plan
     * ('standard'). This endpoint now auto-applies the standard plan
     * and advances onboarding directly to clinic-setup. Kept as a route
     * because existing tenants and bookmarks may still hit it.
     */
    public function planSelection(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId !== null) {
            // Auto-apply the standard plan, start trial. Idempotent — calling
            // twice is safe; PlanService::applyPlanToTenant guards on its own.
            PlanService::applyPlanToTenant($clinicId, 'standard', false);
            OnboardingService::advanceTo($clinicId, 2);
        }

        return Response::redirect('/onboarding/clinic-setup');
    }

    /**
     * POST endpoint preserved for any in-flight requests; same behavior
     * as planSelection() now — no plan choice exists to make.
     */
    public function selectPlan(Request $request): Response
    {
        return $this->planSelection($request);
    }

    public function billingSuccess(Request $request): Response
    {
        $planId = $request->query['plan'] ?? 'clinic';
        $clinicId = RequestContext::clinicId();
        PlanService::applyPlanToTenant($clinicId, $planId, true);

        return Response::redirect('/onboarding/clinic-setup');
    }

    public function clinicSetup(Request $request): Response
    {
        if ($redirect = $this->guardStep(2)) {
            return $redirect;
        }

        $clinic = RequestContext::clinic();
        $config = OnboardingService::specialtyConfig((int) $clinic['id']) ?? [];
        $specialties = \App\Support\SpecialtyCatalog::all();

        $workingHours = $config['working_hours'] ?? null;
        if (is_string($workingHours)) {
            $workingHours = json_decode($workingHours, true);
        }
        if (!is_array($workingHours)) {
            $workingHours = OnboardingService::defaultWorkingHours();
        }

        return $this->page('onboarding/clinic-setup', [
            'csrf' => CsrfService::token(),
            'clinic' => $clinic,
            'config' => $config,
            'specialties' => $specialties,
            'workingHours' => $workingHours,
            'countries' => $this->countries(),
            'step' => 2,
        ]);
    }

    public function saveClinicSetup(Request $request): Response
    {
        if (!$this->verifyCsrf($request)) {
            return Response::redirect('/onboarding/clinic-setup');
        }
        if ($redirect = $this->guardStep(2)) {
            return $redirect;
        }

        $clinicId = RequestContext::clinicId();
        $specialty = $request->post['specialty'] ?? 'gp';
        $specialties = array_keys(\App\Support\SpecialtyCatalog::all(true));
        if (!in_array($specialty, $specialties, true)) {
            $specialty = 'gp';
        }

        $uhidPrefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($request->post['uhid_prefix'] ?? 'MC')), 0, 6));
        if ($uhidPrefix === '') {
            $uhidPrefix = 'MC';
        }

        $country = strtoupper($request->post['country_code'] ?? 'IN');
        $currency = $request->post['currency'] ?? OnboardingService::currencyForCountry($country);
        $taxLabel = $request->post['invoice_tax_label'] ?? OnboardingService::taxLabelForCountry($country);
        $taxPercent = (float) ($request->post['invoice_tax_percent'] ?? 0);
        $consultationFee = (float) ($request->post['consultation_fee'] ?? 0);

        $workingHours = $this->parseWorkingHours($request->post);

        $logoPath = null;
        if (!empty($_FILES['logo']['tmp_name'])) {
            $logoPath = StorageService::storeLogo($clinicId, $_FILES['logo']);
        }

        $tenantUpdate = [
            'name' => trim($request->post['clinic_name'] ?? RequestContext::clinic()['name']),
            'address' => trim($request->post['address'] ?? ''),
            'phone' => trim($request->post['phone'] ?? ''),
            'email' => trim($request->post['email'] ?? ''),
            'specialty' => $specialty,
            'country_code' => $country,
            'currency' => $currency,
        ];
        if ($logoPath !== null) {
            $tenantUpdate['logo_path'] = $logoPath;
        }

        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update($tenantUpdate);

        $specConfig = \App\Support\SpecialtyCatalog::all();
        $prescriptionMode = $specConfig[$specialty]['prescription_mode'] ?? 'allopathic';

        $configData = [
            'prescription_mode' => $prescriptionMode,
            'uhid_prefix' => $uhidPrefix,
            'invoice_tax_label' => $taxLabel,
            'invoice_tax_percent' => $taxPercent,
            'consultation_fee' => $consultationFee,
            'working_hours' => json_encode($workingHours),
        ];

        $existing = OnboardingService::specialtyConfig($clinicId);
        if ($existing !== null) {
            QueryBuilder::table('specialty_configs')
                ->where('clinic_id', '=', $clinicId)
                ->update($configData);
        } else {
            QueryBuilder::table('specialty_configs')->insert(array_merge(
                ['clinic_id' => $clinicId],
                $configData,
            ));
        }

        OnboardingService::advanceTo($clinicId, 3);
        OnboardingService::refreshClinicContext($clinicId);

        return Response::redirect('/onboarding/specialty-config');
    }

    public function specialtyConfig(Request $request): Response
    {
        if ($redirect = $this->guardStep(3)) {
            return $redirect;
        }

        $clinic = RequestContext::clinic();
        $config = OnboardingService::specialtyConfig((int) $clinic['id']) ?? [];
        $specialty = $clinic['specialty'] ?? 'gp';
        $options = $config['specialty_options'] ?? null;
        if (is_string($options)) {
            $options = json_decode($options, true) ?: [];
        }

        return $this->page('onboarding/specialty-config', [
            'csrf' => CsrfService::token(),
            'clinic' => $clinic,
            'specialty' => $specialty,
            'options' => is_array($options) ? $options : [],
            'step' => 3,
        ]);
    }

    public function saveSpecialtyConfig(Request $request): Response
    {
        if (!$this->verifyCsrf($request)) {
            return Response::redirect('/onboarding/specialty-config');
        }
        if ($redirect = $this->guardStep(3)) {
            return $redirect;
        }

        $clinicId = RequestContext::clinicId();
        $clinic = RequestContext::clinic();
        $specialty = $clinic['specialty'] ?? 'gp';

        $options = $this->parseSpecialtyOptions($specialty, $request->post);

        QueryBuilder::table('specialty_configs')
            ->where('clinic_id', '=', $clinicId)
            ->update([
                'specialty_options' => json_encode($options),
            ]);

        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $workingHours = $config['working_hours'] ?? null;
        if (is_string($workingHours)) {
            $workingHours = json_decode($workingHours, true) ?: OnboardingService::defaultWorkingHours();
        }
        $slotDuration = (int) ($options['slot_duration'] ?? $config['slot_duration_min'] ?? 15);

        $doctorIds = DoctorScheduleService::doctorIdsForClinic($clinicId);
        if (is_array($workingHours)) {
            DoctorScheduleService::syncFromWorkingHours($clinicId, $workingHours, $doctorIds, $slotDuration);
        }

        OnboardingService::advanceTo($clinicId, 4);

        return Response::redirect('/onboarding/notifications');
    }

    public function notifications(Request $request): Response
    {
        if ($redirect = $this->guardStep(4)) {
            return $redirect;
        }

        $clinicId = RequestContext::clinicId();
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $prefs = $config['notification_prefs'] ?? null;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }

        return $this->page('onboarding/notifications', [
            'csrf' => CsrfService::token(),
            'clinic' => RequestContext::clinic(),
            'config' => $config,
            'prefs' => is_array($prefs) ? $prefs : $this->defaultNotificationPrefs(),
            'step' => 4,
        ]);
    }

    public function saveNotifications(Request $request): Response
    {
        if (!$this->verifyCsrf($request)) {
            return Response::redirect('/onboarding/notifications');
        }
        if ($redirect = $this->guardStep(4)) {
            return $redirect;
        }

        $clinicId = RequestContext::clinicId();

        $prefs = [
            'appointment_reminder_24h' => !empty($request->post['appointment_reminder_24h']),
            'appointment_reminder_1h' => !empty($request->post['appointment_reminder_1h']),
            'rx_delivery' => !empty($request->post['rx_delivery']),
            'lab_report_ready' => !empty($request->post['lab_report_ready']),
            'follow_up_reminder' => !empty($request->post['follow_up_reminder']),
            'whatsapp_mode' => $request->post['whatsapp_mode'] ?? 'shared',
        ];

        $update = [
            'notification_prefs' => json_encode($prefs),
            'whatsapp_number' => trim($request->post['whatsapp_number'] ?? '') ?: null,
            'whatsapp_token' => trim($request->post['whatsapp_token'] ?? '') ?: null,
        ];

        if (!empty($request->post['razorpay_key']) && !empty($request->post['razorpay_secret'])) {
            $update['razorpay_key'] = trim($request->post['razorpay_key']);
            $update['razorpay_secret'] = trim($request->post['razorpay_secret']);
        }

        QueryBuilder::table('specialty_configs')
            ->where('clinic_id', '=', $clinicId)
            ->update($update);

        OnboardingService::advanceTo($clinicId, 5);

        return Response::redirect('/onboarding/complete');
    }

    public function complete(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        $step = OnboardingService::currentStep();

        if ($step < 5 && $request->method === 'GET') {
            return Response::redirect($this->routeForStep($step));
        }

        if ($request->method === 'POST') {
            if (!$this->verifyCsrf($request)) {
                return Response::redirect('/onboarding/complete');
            }
            OnboardingService::complete($clinicId);

            return Response::redirect('/dashboard');
        }

        $clinic = RequestContext::clinic();
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $plans = PlanService::all();
        // After Phase 1 there is only one plan ('standard'). The plan-picker
        // step is removed in Phase 1 UI step 10; until then, fall back to
        // 'standard' / first available so this page never crashes.
        $planKey = $clinic['plan'] ?? 'standard';
        $plan = $plans[$planKey] ?? ($plans['standard'] ?? reset($plans));

        $specialties = \App\Support\SpecialtyCatalog::all();

        return $this->page('onboarding/complete', [
            'csrf' => CsrfService::token(),
            'clinic' => $clinic,
            'config' => $config,
            'plan' => $plan,
            'specialties' => $specialties,
            'step' => 5,
        ]);
    }

    private function guardStep(int $expectedStep): ?Response
    {
        $user = RequestContext::user();
        if ($user === null || !in_array($user['role'] ?? '', ['admin'], true)) {
            return Response::redirect('/login');
        }

        $step = OnboardingService::currentStep();
        if ($step >= 5) {
            return Response::redirect('/dashboard');
        }
        if ($step !== $expectedStep) {
            return Response::redirect($this->routeForStep($step));
        }

        return null;
    }

    private function routeForStep(int $step): string
    {
        return match ($step) {
            // Step 1 (plan selection) is auto-handled in planSelection() —
            // it always advances to step 2 (clinic-setup). Kept in the map
            // for any tenant still stuck on step 1 from before Phase 1.
            1 => '/onboarding/plan-selection',
            2 => '/onboarding/clinic-setup',
            3 => '/onboarding/specialty-config',
            4 => '/onboarding/notifications',
            default => '/onboarding/complete',
        };
    }

    private function verifyCsrf(Request $request): bool
    {
        return CsrfService::verify($request->post['_csrf'] ?? null);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data));
    }

    /** @return array<string, string> */
    private function countries(): array
    {
        return [
            'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom',
            'AE' => 'UAE', 'SG' => 'Singapore', 'MY' => 'Malaysia', 'CA' => 'Canada',
        ];
    }

    /** @return array<string, mixed> */
    private function defaultNotificationPrefs(): array
    {
        return [
            'appointment_reminder_24h' => true,
            'appointment_reminder_1h' => true,
            'rx_delivery' => true,
            'lab_report_ready' => true,
            'follow_up_reminder' => true,
            'whatsapp_mode' => 'shared',
        ];
    }

    /** @param array<string, mixed> $post @return array<string, mixed> */
    private function parseWorkingHours(array $post): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $result = [];
        foreach ($days as $day) {
            $enabled = !empty($post["{$day}_enabled"]);
            $result[$day] = [
                'enabled' => $enabled,
                'sessions' => [],
            ];
            if (!$enabled) {
                continue;
            }
            $morningStart = $post["{$day}_morning_start"] ?? null;
            $morningEnd = $post["{$day}_morning_end"] ?? null;
            $eveningStart = $post["{$day}_evening_start"] ?? null;
            $eveningEnd = $post["{$day}_evening_end"] ?? null;
            if ($morningStart && $morningEnd) {
                $result[$day]['sessions'][] = ['start' => $morningStart, 'end' => $morningEnd];
            }
            if ($eveningStart && $eveningEnd) {
                $result[$day]['sessions'][] = ['start' => $eveningStart, 'end' => $eveningEnd];
            }
            if ($result[$day]['sessions'] === []) {
                $result[$day]['sessions'][] = ['start' => '09:00', 'end' => '18:00'];
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $post @return array<string, mixed> */
    private function parseSpecialtyOptions(string $specialty, array $post): array
    {
        $base = ['slot_duration' => (int) ($post['slot_duration'] ?? 15)];

        return match ($specialty) {
            'gp' => array_merge($base, [
                'icd10_enabled' => !empty($post['icd10_enabled']),
                'drug_db' => $post['drug_db'] ?? 'global',
                'default_frequencies' => $post['default_frequencies'] ?? ['OD', 'BD', 'TDS', 'QID', 'SOS'],
            ]),
            'homeopathy' => array_merge($base, [
                'case_fields' => [
                    'mental_generals' => !empty($post['mental_generals']),
                    'physical_generals' => !empty($post['physical_generals']),
                    'peculiar_symptoms' => !empty($post['peculiar_symptoms']),
                    'modalities' => !empty($post['modalities']),
                    'miasmatic_analysis' => !empty($post['miasmatic_analysis']),
                ],
                'potency_system' => $post['potency_system'] ?? 'centesimal',
                'dietary_antidote_warnings' => !empty($post['dietary_antidote_warnings']),
            ]),
            'dental' => array_merge($base, [
                'tooth_numbering' => $post['tooth_numbering'] ?? 'FDI',
                'procedures' => array_filter(array_map('trim', explode(',', $post['procedures'] ?? ''))),
            ]),
            'derma' => array_merge($base, [
                'skin_score_enabled' => !empty($post['skin_score_enabled']),
                'photo_tracking' => !empty($post['photo_tracking']),
                'body_map' => !empty($post['body_map']),
            ]),
            'peds' => array_merge($base, [
                'growth_chart_region' => $post['growth_chart_region'] ?? 'global',
                'vaccine_schedule' => $post['vaccine_schedule'] ?? 'iap',
                'growth_params' => ['weight', 'height', 'head_circumference'],
            ]),
            'physio' => array_merge($base, [
                'rom_joints' => !empty($post['rom_joints']),
                'pain_scale' => $post['pain_scale'] ?? 'nrs',
                'default_session_duration' => (int) ($post['default_session_duration'] ?? 45),
            ]),
            default => $base,
        };
    }
}
