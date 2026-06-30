<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\BillingGatewayService;
use App\Services\ClinicSettingsService;
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
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $step = OnboardingService::currentStep();
        if ($step >= 5) {
            return Response::redirect('/dashboard');
        }

        OnboardingService::ensureStandardTrialStarted($clinicId);

        return Response::redirect('/onboarding/clinic-setup');
    }

    /**
     * Legacy POST from the old plan picker — always continues with Standard trial.
     */
    public function selectPlan(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        OnboardingService::ensureStandardTrialStarted($clinicId);

        return Response::redirect('/onboarding/clinic-setup');
    }

    public function billingSuccess(Request $request): Response
    {
        $planId = $request->query['plan'] ?? 'clinic';
        $clinicId = RequestContext::clinicId();
        PlanService::applyPlanToTenant($clinicId, $planId, true);

        return Response::redirect('/onboarding/clinic-setup');
    }

    /**
     * Cashfree redirects the doctor here after payment (?order_id=...). We
     * verify the order with Cashfree's API and activate the plan — this is the
     * safety net for a missed/late webhook (verify is idempotent).
     */
    public function cashfreeReturn(Request $request): Response
    {
        $orderId = (string) ($request->query['order_id'] ?? '');
        $paid = $orderId !== '' && BillingGatewayService::verifyCashfreeOrder($orderId);

        // A doctor who has already finished onboarding (step >= 5) is paying a
        // RENEWAL/UPGRADE from Settings — they must NOT be dropped back into the
        // first-time clinic-setup wizard. Only a first-time payer continues into
        // onboarding. (Cashfree's return_url is shared for both flows, so we
        // branch here on onboarding state.)
        $onboarded = OnboardingService::currentStep() >= 5;

        if ($onboarded) {
            $flag = $paid ? 'paid=1' : 'payment=pending';
            return Response::redirect('/settings?tab=subscription&' . $flag);
        }

        if ($paid) {
            return Response::redirect('/onboarding/clinic-setup?paid=1');
        }

        // Not (yet) confirmed — the webhook may still arrive. Send them on with
        // a soft notice rather than blocking onboarding.
        return Response::redirect('/onboarding/clinic-setup?payment=pending');
    }

    public function clinicSetup(Request $request): Response
    {
        if ($redirect = $this->guardOnboardingStep(2)) {
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
            'consultationFee' => ClinicSettingsService::consultationFeeForClinic((int) $clinic['id']),
            'consultationFeeCurrency' => ClinicSettingsService::consultationFeeCurrencyForClinic((int) $clinic['id']),
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
        if ($redirect = $this->guardOnboardingStep(2)) {
            return $redirect;
        }

        $clinicId = (int) RequestContext::clinicId();
        $this->writeClinicSetup($request, $clinicId);
        $this->advanceIfBehind($clinicId, 3);
        OnboardingService::refreshClinicContext($clinicId);

        return Response::redirect('/onboarding/specialty-config');
    }

    public function draftClinicSetup(Request $request): Response
    {
        if (!$this->verifyCsrf($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expired — refresh the page.'], 419);
        }
        if ($redirect = $this->guardOnboardingStep(2, true)) {
            return $redirect;
        }

        $clinicId = (int) RequestContext::clinicId();
        $this->writeClinicSetup($request, $clinicId);
        OnboardingService::refreshClinicContext($clinicId);

        return Response::json(['ok' => true, 'saved_at' => date('c')]);
    }

    public function specialtyConfig(Request $request): Response
    {
        if ($redirect = $this->guardOnboardingStep(3)) {
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
        if ($redirect = $this->guardOnboardingStep(3)) {
            return $redirect;
        }

        $clinicId = (int) RequestContext::clinicId();
        $this->writeSpecialtyConfig($request, $clinicId, true);
        $this->advanceIfBehind($clinicId, 4);

        return Response::redirect('/onboarding/notifications');
    }

    public function draftSpecialtyConfig(Request $request): Response
    {
        if (!$this->verifyCsrf($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expired — refresh the page.'], 419);
        }
        if ($redirect = $this->guardOnboardingStep(3, true)) {
            return $redirect;
        }

        $this->writeSpecialtyConfig($request, (int) RequestContext::clinicId(), false);

        return Response::json(['ok' => true, 'saved_at' => date('c')]);
    }

    public function notifications(Request $request): Response
    {
        if ($redirect = $this->guardOnboardingStep(4)) {
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
        if ($redirect = $this->guardOnboardingStep(4)) {
            return $redirect;
        }

        $clinicId = (int) RequestContext::clinicId();
        $this->writeNotifications($request, $clinicId);
        $this->advanceIfBehind($clinicId, 5);

        return Response::redirect('/onboarding/complete');
    }

    public function draftNotifications(Request $request): Response
    {
        if (!$this->verifyCsrf($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expired — refresh the page.'], 419);
        }
        if ($redirect = $this->guardOnboardingStep(4, true)) {
            return $redirect;
        }

        $this->writeNotifications($request, (int) RequestContext::clinicId());

        return Response::json(['ok' => true, 'saved_at' => date('c')]);
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

    private function guardOnboardingStep(int $minStep, bool $json = false): ?Response
    {
        $user = RequestContext::user();
        if ($user === null || !in_array($user['role'] ?? '', ['admin'], true)) {
            return $json
                ? Response::json(['ok' => false, 'error' => 'Unauthorized'], 401)
                : Response::redirect('/login');
        }

        $clinicId = RequestContext::clinicId();
        if ($clinicId !== null && $minStep === 2) {
            OnboardingService::ensureStandardTrialStarted($clinicId);
        }

        $step = OnboardingService::currentStep();
        if ($step >= 5) {
            return $json
                ? Response::json(['ok' => false, 'error' => 'Onboarding already complete'], 400)
                : Response::redirect('/dashboard');
        }

        // Block skipping ahead; allow revisiting earlier steps to edit saved data.
        if ($step < $minStep) {
            $url = $this->routeForStep($step);

            return $json
                ? Response::json(['ok' => false, 'error' => 'Complete earlier steps first', 'redirect' => $url], 400)
                : Response::redirect($url);
        }

        return null;
    }

    private function advanceIfBehind(int $clinicId, int $targetStep): void
    {
        if (OnboardingService::currentStep() < $targetStep) {
            OnboardingService::advanceTo($clinicId, $targetStep);
        }
    }

    private function writeClinicSetup(Request $request, int $clinicId): void
    {
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
            'working_hours' => json_encode($workingHours),
        ];

        $existing = OnboardingService::specialtyConfig($clinicId);
        if ($existing !== null) {
            QueryBuilder::table('specialty_configs')
                ->where('clinic_id', '=', $clinicId)
                ->update($configData);
        } else {
            QueryBuilder::table('specialty_configs')->insert(array_merge(
                ['clinic_id' => $clinicId, 'booking_window_days' => 30],
                $configData,
            ));
        }

        ClinicSettingsService::saveConsultationFee(
            $clinicId,
            $consultationFee > 0 ? $consultationFee : null,
            $currency,
        );

        try {
            $doctorIds = DoctorScheduleService::doctorIdsForClinic($clinicId);
            $slotDuration = DoctorScheduleService::slotDurationForClinic($clinicId);
            DoctorScheduleService::syncFromWorkingHours($clinicId, $workingHours, $doctorIds, $slotDuration);
        } catch (\Throwable $e) {
            error_log('[writeClinicSetup] doctor schedule sync failed: ' . $e->getMessage());
        }
    }

    private function writeSpecialtyConfig(Request $request, int $clinicId, bool $syncSchedules): void
    {
        $clinic = RequestContext::clinic();
        $specialty = $clinic['specialty'] ?? 'gp';
        $options = $this->parseSpecialtyOptions($specialty, $request->post);

        QueryBuilder::table('specialty_configs')
            ->where('clinic_id', '=', $clinicId)
            ->update([
                'specialty_options' => json_encode($options),
            ]);

        if (!$syncSchedules) {
            return;
        }

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
    }

    private function writeNotifications(Request $request, int $clinicId): void
    {
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
        ];

        $token = trim($request->post['whatsapp_token'] ?? '');
        if ($token !== '') {
            $update['whatsapp_token'] = $token;
        }

        if (!empty($request->post['razorpay_key'])) {
            $update['razorpay_key'] = trim((string) $request->post['razorpay_key']);
        }
        if (!empty($request->post['razorpay_secret'])) {
            $update['razorpay_secret'] = trim((string) $request->post['razorpay_secret']);
        }

        QueryBuilder::table('specialty_configs')
            ->where('clinic_id', '=', $clinicId)
            ->update($update);
    }

    private function guardStep(int $expectedStep): ?Response
    {
        return $this->guardOnboardingStep($expectedStep);
    }

    private function routeForStep(int $step): string
    {
        return match ($step) {
            1 => '/onboarding/clinic-setup',
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
        $data['onboardingStep'] = OnboardingService::currentStep();
        $data['onboardingResumed'] = \App\Support\SessionFlash::pull('onboarding_resume') === true;

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
