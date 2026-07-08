<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Services\ClinicSettingsService;
use App\Services\LeaveService;
use App\Services\OnboardingService;
use App\Services\PlanService;
use App\Services\ApiKeyService;
use App\Services\DoctorOtpService;
use App\Services\TwilioSmsService;
use App\Services\WhiteLabelService;
use App\Support\Layout;
use App\Support\View;
use App\Support\VisitView;

final class ClinicSettingsController
{
    private const TABS = ['general', 'hours', 'specialty', 'notifications', 'branding'];

    public function index(Request $request): Response
    {
        $clinic = RequestContext::clinic() ?? [];
        $tabs = self::TABS;
        if (!WhiteLabelService::isEnterprise($clinic)) {
            $tabs = array_values(array_filter($tabs, static fn (string $t) => $t !== 'branding'));
        }

        $tab = $request->query['tab'] ?? 'general';
        if ($tab === 'team') {
            return Response::redirect('/settings/team');
        }
        // Leaves & Billing were promoted to their own pages — keep old
        // ?tab= deep-links working by redirecting to the new locations.
        if ($tab === 'leaves') {
            $qs = $request->query;
            unset($qs['tab']);
            return Response::redirect('/leaves' . ($qs ? '?' . http_build_query($qs) : ''));
        }
        if ($tab === 'subscription') {
            return Response::redirect('/subscription');
        }
        if (!in_array($tab, $tabs, true)) {
            $tab = 'general';
        }
        $clinicId = (int) $clinic['id'];
        $listing = ClinicSettingsService::publicListing($clinicId);
        if (!empty($listing['name'])) {
            // General tab should show the listing-side clinic name.
            $clinic['name'] = (string) $listing['name'];
        }
        $config = OnboardingService::specialtyConfig($clinicId) ?? [];
        $specialties = \App\Support\SpecialtyCatalog::all();

        $workingHours = $config['working_hours'] ?? null;
        if (is_string($workingHours)) {
            $workingHours = json_decode($workingHours, true);
        }
        if (!is_array($workingHours)) {
            $workingHours = OnboardingService::defaultWorkingHours();
        }

        $options = $config['specialty_options'] ?? null;
        if (is_string($options)) {
            $options = json_decode($options, true) ?: [];
        }

        $prefs = $config['notification_prefs'] ?? null;
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }
        if (!is_array($prefs)) {
            $prefs = $this->defaultNotificationPrefs();
        }

        $doctorId = isset($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;
        $doctors = AppointmentService::doctorsForClinic($clinicId);
        if ($doctorId === null && $doctors !== []) {
            $doctorId = (int) $doctors[0]['id'];
        }
        $leaveMonth = $request->query['month'] ?? date('Y-m');
        $leaves = $doctorId !== null ? LeaveService::forDoctor($clinicId, $doctorId, $leaveMonth) : [];

        // Shared data for every tab partial — the page now renders ALL
        // sections stacked on a single scrollable page (not one tab at a time).
        $tabData = [
            'clinic' => $clinic,
            'config' => $config,
            'csrf' => \App\Services\CsrfService::token(),
            'specialties' => $specialties,
            'workingHours' => $workingHours,
            'options' => is_array($options) ? $options : [],
            'prefs' => $prefs,
            'services' => ClinicSettingsService::servicesForClinic($clinicId),
            'countries' => $this->countries(),
            'plans' => PlanService::all(),
            'modules' => ClinicSettingsService::activeModulesDetail($clinicId),
            'invoices' => \App\Services\SaasInvoiceService::forClinic($clinicId),
            'staffCount' => QueryBuilder::table('users')->forClinic($clinicId)->where('is_active', '=', 1)->count(),
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'leaveMonth' => $leaveMonth,
            'leaves' => $leaves,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
            'warning' => $request->query['warning'] ?? null,
            'phoneStep' => (string) ($request->query['phone_step'] ?? 'none'),
            'pendingPhone' => (string) ($request->query['phone'] ?? ''),
            'phoneDevCode' => $request->query['phone_dev_code'] ?? null,
            'apiKeys' => ApiKeyService::listForClinic($clinicId),
            'apiScopes' => ApiKeyService::SCOPES,
            'newApiKey' => $request->query['new_key'] ?? null,
            'domainVerify' => !empty($clinic['custom_domain'])
                ? [
                    'host' => '_manageclinic.' . $clinic['custom_domain'],
                    'token' => $clinic['domain_verify_token'] ?? '',
                    'verified' => (int) ($clinic['custom_domain_verified'] ?? 0) === 1,
                ]
                : null,
        ];

        // Render each section partial up-front so the view can stack them.
        $sections = [];
        foreach ($tabs as $t) {
            $sections[$t] = View::render('settings/tabs/' . $t, $tabData);
        }

        return Response::html(Layout::page('settings/index', [
            'tab' => $tab,
            'tabs' => $tabs,
            'sections' => $sections,
            'message' => $request->query['message'] ?? null,
        ], 'Settings'));
    }

    /** Standalone "Leaves & holidays" page (promoted out of Settings). */
    public function leaves(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $doctors  = AppointmentService::doctorsForClinic($clinicId);
        $doctorId = isset($request->query['doctor_id']) ? (int) $request->query['doctor_id'] : null;
        if ($doctorId === null && $doctors !== []) {
            $doctorId = (int) $doctors[0]['id'];
        }
        $leaveMonth = $request->query['month'] ?? date('Y-m');
        $leaves = $doctorId !== null ? LeaveService::forDoctor($clinicId, $doctorId, $leaveMonth) : [];

        $section = View::render('settings/tabs/leaves', [
            'doctors'    => $doctors,
            'doctorId'   => $doctorId,
            'leaveMonth' => $leaveMonth,
            'leaves'     => $leaves,
            'csrf'       => \App\Services\CsrfService::token(),
            'warning'    => $request->query['warning'] ?? null,
        ]);

        return Response::html(Layout::page('settings/standalone', [
            'pageHeading' => 'Leaves & holidays',
            'pageSub'     => 'Block dates when the clinic or a doctor is unavailable.',
            'section'     => $section,
            'message'     => $request->query['message'] ?? null,
        ], 'Leaves'));
    }

    /** Standalone "Subscription & billing" page (promoted out of Settings). */
    public function billing(Request $request): Response
    {
        $clinic = RequestContext::clinic() ?? [];
        $clinicId = (int) ($clinic['id'] ?? 0);
        if ($clinicId <= 0) {
            return Response::redirect('/login');
        }

        $section = View::render('settings/tabs/subscription', [
            'clinic'     => $clinic,
            'plans'      => PlanService::all(),
            'modules'    => ClinicSettingsService::activeModulesDetail($clinicId),
            'invoices'   => \App\Services\SaasInvoiceService::forClinic($clinicId),
            'staffCount' => QueryBuilder::table('users')->forClinic($clinicId)->where('is_active', '=', 1)->count(),
            'csrf'       => \App\Services\CsrfService::token(),
        ]);

        return Response::html(Layout::page('settings/standalone', [
            'pageHeading' => 'Subscription & billing',
            'pageSub'     => 'Your plan, seats, invoices, and billing details.',
            'section'     => $section,
            'message'     => $request->query['message'] ?? null,
        ], 'Billing'));
    }

    public function saveGeneral(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $clinic = RequestContext::clinic() ?? [];
        $currentPhone = DoctorOtpService::normalizePhone((string) ($clinic['phone'] ?? ''));
        $newPhone = DoctorOtpService::normalizePhone((string) ($request->post['phone'] ?? ''));

        if ($newPhone !== '' && $newPhone !== $currentPhone) {
            if (!$this->isPhoneChangeVerified($clinicId, $newPhone)) {
                return Response::redirect('/settings?tab=general&error=' . urlencode('Please verify the new phone number with OTP before saving.'));
            }
            if ($this->phoneBelongsToAnotherClinic($clinicId, $newPhone)) {
                return Response::redirect('/settings?tab=general&error=' . urlencode('This phone number is already used by another account.'));
            }
        }

        ClinicSettingsService::saveGeneral($clinicId, $request->post, $_FILES['logo'] ?? null);
        $this->clearVerifiedPhoneChange($clinicId);

        return Response::redirect('/settings?tab=general&message=saved');
    }

    public function sendGeneralPhoneOtp(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $raw = (string) ($request->post['new_phone'] ?? '');
        $phone = DoctorOtpService::normalizePhone($raw);
        if ($phone === '' || strlen($phone) < 10) {
            return Response::redirect('/settings?tab=general&error=' . urlencode('Enter a valid mobile number for OTP verification.'));
        }

        $clinic = RequestContext::clinic() ?? [];
        $currentPhone = DoctorOtpService::normalizePhone((string) ($clinic['phone'] ?? ''));
        if ($phone === $currentPhone) {
            return Response::redirect('/settings?tab=general&message=' . urlencode('This is already your current phone number.'));
        }

        if ($this->phoneBelongsToAnotherClinic($clinicId, $phone)) {
            return Response::redirect('/settings?tab=general&error=' . urlencode('This phone number is already used by another account.'));
        }

        $retry = $this->retryAfterForPhoneOtp($phone);
        if ($retry > 0) {
            return Response::redirect('/settings?tab=general&error=' . urlencode("Please wait {$retry}s before requesting another OTP.") . '&phone=' . rawurlencode($phone) . '&phone_step=code');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        QueryBuilder::table('doctor_otp_codes')->insert([
            'phone' => $phone,
            'purpose' => 'register',
            'code_hash' => hash('sha256', $code),
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        $body = "Your eClinicPro phone-change code is: {$code}\nValid for 10 minutes.";
        $sent = TwilioSmsService::send($phone, $body);
        $devMode = (($_ENV['TWILIO_ACCOUNT_SID'] ?? '') === ''
            || ($_ENV['TWILIO_AUTH_TOKEN'] ?? '') === ''
            || ($_ENV['TWILIO_FROM_NUMBER'] ?? '') === '');

        if (!$sent['ok']) {
            return Response::redirect('/settings?tab=general&error=' . urlencode('Could not send OTP right now. Please try again.'));
        }

        $url = '/settings?tab=general&phone_step=code&phone=' . rawurlencode($phone)
            . '&message=' . urlencode('OTP sent to your new phone number.');
        if ($devMode) {
            $url .= '&phone_dev_code=' . rawurlencode($code);
        }
        return Response::redirect($url);
    }

    public function verifyGeneralPhoneOtp(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $phone = DoctorOtpService::normalizePhone((string) ($request->post['new_phone'] ?? ''));
        $code = preg_replace('/\D/', '', (string) ($request->post['otp_code'] ?? '')) ?? '';
        if ($phone === '' || strlen($code) !== 6) {
            return Response::redirect('/settings?tab=general&phone_step=code&phone=' . rawurlencode($phone) . '&error=' . urlencode('Enter a valid 6-digit OTP.'));
        }

        // Disallow taking another clinic's phone.
        if ($this->phoneBelongsToAnotherClinic($clinicId, $phone)) {
            return Response::redirect('/settings?tab=general&error=' . urlencode('This phone number is already used by another account.'));
        }

        $row = QueryBuilder::table('doctor_otp_codes')
            ->where('phone', '=', $phone)
            ->where('purpose', '=', 'register')
            ->where('consumed_at', 'IS', null)
            ->orderBy('id', 'DESC')
            ->first();

        if ($row === null) {
            return Response::redirect('/settings?tab=general&phone_step=code&phone=' . rawurlencode($phone) . '&error=' . urlencode('No active OTP found. Send OTP again.'));
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return Response::redirect('/settings?tab=general&phone_step=code&phone=' . rawurlencode($phone) . '&error=' . urlencode('OTP expired. Send a new OTP.'));
        }
        if ((int) ($row['attempts'] ?? 0) >= 5) {
            return Response::redirect('/settings?tab=general&phone_step=code&phone=' . rawurlencode($phone) . '&error=' . urlencode('Too many failed attempts. Send a new OTP.'));
        }

        QueryBuilder::table('doctor_otp_codes')->where('id', '=', (int) $row['id'])->update([
            'attempts' => (int) ($row['attempts'] ?? 0) + 1,
        ]);

        if (!hash_equals((string) $row['code_hash'], hash('sha256', $code))) {
            return Response::redirect('/settings?tab=general&phone_step=code&phone=' . rawurlencode($phone) . '&error=' . urlencode('Invalid OTP.'));
        }

        QueryBuilder::table('doctor_otp_codes')->where('id', '=', (int) $row['id'])->update([
            'consumed_at' => date('Y-m-d H:i:s'),
        ]);

        // Apply immediately: update tenant + listing + owner user phone.
        QueryBuilder::table('tenants')->where('id', '=', $clinicId)->update([
            'phone' => $phone,
        ]);
        QueryBuilder::table('users')
            ->where('clinic_id', '=', $clinicId)
            ->where('is_owner', '=', 1)
            ->update(['phone' => $phone]);
        QueryBuilder::table('directory_doctors')
            ->where('claimed_tenant_id', '=', $clinicId)
            ->where('is_active', '=', 1)
            ->update(['phone' => $phone]);

        $this->clearVerifiedPhoneChange($clinicId);

        return Response::redirect('/settings?tab=general&message=' . urlencode('Phone number updated.'));
    }

    public function saveServices(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        ClinicSettingsService::saveServices($clinicId, $request->post);

        return Response::redirect('/settings?tab=general&message=saved');
    }

    public function saveHours(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        try {
            ClinicSettingsService::saveHours($clinicId, $request->post);
        } catch (\Throwable $e) {
            error_log('[saveHours] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return Response::redirect('/settings?tab=hours&error=' . urlencode('Could not save: ' . $e->getMessage()));
        }

        return Response::redirect('/settings?tab=hours&message=saved');
    }

    public function saveSpecialty(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $clinic = RequestContext::clinic();
        $specialty = (string) ($clinic['specialty'] ?? 'gp');
        $change = !empty($request->post['change_specialty']);

        ClinicSettingsService::saveSpecialty($clinicId, $specialty, $request->post, $change);

        return Response::redirect('/settings?tab=specialty&message=saved');
    }

    public function saveNotifications(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        ClinicSettingsService::saveNotifications($clinicId, $request->post);

        return Response::redirect('/settings?tab=notifications&message=saved');
    }

    public function saveLeave(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $doctorId = (int) ($request->post['doctor_id'] ?? 0);
        $date = (string) ($request->post['leave_date'] ?? '');
        $session = (string) ($request->post['session'] ?? 'full');
        $reason = trim((string) ($request->post['reason'] ?? '')) ?: null;

        $conflicts = LeaveService::conflictingAppointments($clinicId, $doctorId, $date, $session);
        if ($conflicts !== []) {
            $names = array_map(static fn ($r) => $r['patient_name'] ?? 'Patient', $conflicts);

            return Response::redirect('/leaves?doctor_id=' . $doctorId . '&month=' . substr($date, 0, 7)
                . '&warning=' . urlencode('Conflicting appointments: ' . implode(', ', array_slice($names, 0, 5))));
        }

        LeaveService::add($clinicId, $doctorId, $date, $session, $reason);
        AuditService::log($request, 'INSERT', 'doctor_leaves', $doctorId);

        return Response::redirect('/leaves?doctor_id=' . $doctorId . '&month=' . substr($date, 0, 7) . '&message=saved');
    }

    public function removeLeave(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $doctorId = (int) ($request->query['doctor_id'] ?? 0);
        LeaveService::remove($clinicId, (int) $id);
        AuditService::log($request, 'DELETE', 'doctor_leaves', (int) $id);

        return Response::redirect('/leaves?doctor_id=' . $doctorId . '&message=removed');
    }

    public function createApiKey(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $scopes = is_array($request->post['scopes'] ?? null) ? $request->post['scopes'] : [];
        $result = ApiKeyService::create($clinicId, (string) ($request->post['name'] ?? ''), $scopes);
        if ($result === null) {
            return Response::redirect('/settings?tab=api&error=create_failed');
        }

        return Response::redirect('/settings?tab=api&new_key=' . urlencode($result['key']));
    }

    public function revokeApiKey(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId !== null) {
            ApiKeyService::revoke($clinicId, (int) $id);
        }

        return Response::redirect('/settings?tab=api&message=revoked');
    }

    public function saveBranding(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        WhiteLabelService::saveBranding($clinicId, $request->post, $_FILES['logo'] ?? null);

        return Response::redirect('/settings?tab=branding&message=saved');
    }

    public function startDomainVerify(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        WhiteLabelService::startDomainVerification($clinicId, (string) ($request->post['custom_domain'] ?? ''));

        return Response::redirect('/settings?tab=branding&message=domain_started');
    }

    public function checkDomainVerify(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $ok = WhiteLabelService::verifyDomain($clinicId);

        return Response::redirect('/settings?tab=branding&message=' . ($ok ? 'domain_verified' : 'domain_pending'));
    }

    public function testWhatsApp(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        $result = $clinicId !== null
            ? ClinicSettingsService::testWhatsApp($clinicId)
            : ['ok' => false, 'message' => 'Unauthorized'];

        $q = $result['ok'] ? 'message' : 'error';

        return Response::redirect('/settings?tab=notifications&' . $q . '=' . urlencode($result['message']));
    }

    public function testRazorpay(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        $result = $clinicId !== null
            ? ClinicSettingsService::testRazorpay($clinicId)
            : ['ok' => false, 'message' => 'Unauthorized'];

        $q = $result['ok'] ? 'message' : 'error';

        return Response::redirect('/settings?tab=notifications&' . $q . '=' . urlencode($result['message']));
    }

    /**
     * POST /api/clinic-settings/modules/{moduleKey}
     * Body: { state: "show" | "hide" }
     * Phase 2 — toggle a single optional module's visibility.
     */
    public function toggleModule(Request $request, string $moduleKey): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $body = json_decode($request->rawBody ?? '{}', true);
        $state = is_array($body) ? ($body['state'] ?? null) : null;
        if ($state !== 'show' && $state !== 'hide') {
            return Response::json(['error' => 'state must be show|hide'], 422);
        }

        VisitView::toggleModule($clinicId, $moduleKey, $state === 'show');

        return Response::json([
            'ok' => true,
            'module' => $moduleKey,
            'state' => $state,
            'visible_modules' => VisitView::visibleModules($clinicId),
        ]);
    }

    /**
     * POST /api/clinic-settings/section-state
     * Body: { section: "vitals", state: "expanded"|"collapsed" }
     * Phase 2 — records ghost-link reveals + collapses. After 3
     * ghost-link reveals VisitView auto-promotes the section into
     * visible_modules.
     */
    public function recordSectionState(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $body = json_decode($request->rawBody ?? '{}', true);
        $section = is_array($body) ? ($body['section'] ?? null) : null;
        $state = is_array($body) ? ($body['state'] ?? null) : null;

        if (!is_string($section) || $section === '') {
            return Response::json(['error' => 'section required'], 422);
        }
        if ($state !== 'expanded' && $state !== 'collapsed') {
            return Response::json(['error' => 'state must be expanded|collapsed'], 422);
        }

        if ($state === 'expanded') {
            VisitView::recordSectionExpand($clinicId, $section);
        } else {
            VisitView::recordSectionCollapse($clinicId, $section);
        }

        return Response::json([
            'ok' => true,
            'section' => $section,
            'state' => $state,
            'visible_modules' => VisitView::visibleModules($clinicId),
        ]);
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

    /**
     * Download a SaaS (subscription) invoice PDF — clinic-scoped, streamed
     * from private storage. Generates on first access if not yet rendered.
     */
    public function downloadSaasInvoice(Request $request, string $id): Response
    {
        $clinicId = (int) RequestContext::clinicId();
        $invoice = QueryBuilder::table('saas_invoices')
            ->forClinic($clinicId)
            ->where('id', '=', (int) $id)
            ->first();

        if ($invoice === null) {
            return Response::html('Invoice not found', 404);
        }

        $abs = $invoice['pdf_path'] ?? null;
        if (empty($abs) || !is_file((string) $abs)) {
            // Render on demand (also persists pdf_path).
            \App\Services\SaasInvoiceService::generateAndEmail((int) $invoice['id']);
            $invoice = QueryBuilder::table('saas_invoices')->where('id', '=', (int) $id)->first();
            $abs = $invoice['pdf_path'] ?? null;
        }

        if (empty($abs) || !is_file((string) $abs)) {
            return Response::redirect('/subscription?error=' . urlencode('Invoice PDF unavailable'));
        }

        return Response::download((string) $abs, 'eclinicpro-invoice-' . ($invoice['invoice_no'] ?? $id) . '.pdf')
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="eclinicpro-invoice-' . ($invoice['invoice_no'] ?? $id) . '.pdf"');
    }

    /** @return array<string, string> */
    private function countries(): array
    {
        return [
            'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom',
            'AE' => 'UAE', 'SG' => 'Singapore', 'MY' => 'Malaysia', 'CA' => 'Canada',
        ];
    }

    private function retryAfterForPhoneOtp(string $phone): int
    {
        $last = QueryBuilder::table('doctor_otp_codes')
            ->where('phone', '=', $phone)
            ->where('purpose', '=', 'register')
            ->where('consumed_at', 'IS', null)
            ->orderBy('id', 'DESC')
            ->first();
        if ($last === null || empty($last['created_at'])) {
            return 0;
        }
        $age = time() - strtotime((string) $last['created_at']);
        return $age < 30 ? 30 - $age : 0;
    }

    private function phoneBelongsToAnotherClinic(int $clinicId, string $phone): bool
    {
        $existing = QueryBuilder::table('users')
            ->where('phone', '=', $phone)
            ->where('clinic_id', '!=', $clinicId)
            ->first();
        return $existing !== null;
    }

    private function markPhoneChangeVerified(int $clinicId, string $phone): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['settings_phone_verify'] = [
            'clinic_id' => $clinicId,
            'phone' => $phone,
            'at' => time(),
        ];
    }

    private function isPhoneChangeVerified(int $clinicId, string $phone): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $d = $_SESSION['settings_phone_verify'] ?? null;
        if (!is_array($d)) {
            return false;
        }
        if ((int) ($d['clinic_id'] ?? 0) !== $clinicId) {
            return false;
        }
        if ((string) ($d['phone'] ?? '') !== $phone) {
            return false;
        }
        if (time() - (int) ($d['at'] ?? 0) > 900) {
            unset($_SESSION['settings_phone_verify']);
            return false;
        }
        return true;
    }

    private function clearVerifiedPhoneChange(int $clinicId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $d = $_SESSION['settings_phone_verify'] ?? null;
        if (is_array($d) && (int) ($d['clinic_id'] ?? 0) === $clinicId) {
            unset($_SESSION['settings_phone_verify']);
        }
    }
}
