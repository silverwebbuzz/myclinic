<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Gates\ModuleGate;
use App\Services\ClinicSettingsService;
use App\Services\ConsentTemplateService;
use App\Services\LeaveService;
use App\Services\OnboardingService;
use App\Services\PlanService;
use App\Services\SeatService;
use App\Services\ApiKeyService;
use App\Services\StaffInvitationService;
use App\Services\WhiteLabelService;
use App\Support\Layout;
use App\Support\View;
use App\Support\VisitView;

final class ClinicSettingsController
{
    private const TABS = ['general', 'hours', 'specialty', 'leaves', 'notifications', 'subscription', 'team', 'api', 'branding', 'consent-forms'];

    public function index(Request $request): Response
    {
        $clinic = RequestContext::clinic() ?? [];
        $tabs = self::TABS;
        if (!ModuleGate::check('consent')) {
            $tabs = array_values(array_filter($tabs, static fn (string $t) => $t !== 'consent-forms'));
        }
        if (!WhiteLabelService::isEnterprise($clinic)) {
            $tabs = array_values(array_filter($tabs, static fn (string $t) => $t !== 'branding'));
        }

        $tab = $request->query['tab'] ?? 'general';
        if (!in_array($tab, $tabs, true)) {
            $tab = 'general';
        }
        $clinicId = (int) $clinic['id'];
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
            'countries' => $this->countries(),
            'plans' => PlanService::all(),
            'modules' => ClinicSettingsService::activeModulesDetail($clinicId),
            'invoices' => ClinicSettingsService::saasInvoices($clinicId),
            'staffCount' => QueryBuilder::table('users')->forClinic($clinicId)->where('is_active', '=', 1)->count(),
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'leaveMonth' => $leaveMonth,
            'leaves' => $leaves,
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
            'warning' => $request->query['warning'] ?? null,
            'staff' => StaffInvitationService::staffList($clinicId),
            'invitations' => StaffInvitationService::listForClinic($clinicId),
            'seatUsage' => SeatService::getSeatUsage($clinicId),
            'consentTemplates' => ModuleGate::check('consent') ? ConsentTemplateService::list($clinicId) : [],
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

    public function saveGeneral(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        ClinicSettingsService::saveGeneral($clinicId, $request->post, $_FILES['logo'] ?? null);

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

            return Response::redirect('/settings?tab=leaves&doctor_id=' . $doctorId . '&month=' . substr($date, 0, 7)
                . '&warning=' . urlencode('Conflicting appointments: ' . implode(', ', array_slice($names, 0, 5))));
        }

        LeaveService::add($clinicId, $doctorId, $date, $session, $reason);
        AuditService::log($request, 'INSERT', 'doctor_leaves', $doctorId);

        return Response::redirect('/settings?tab=leaves&doctor_id=' . $doctorId . '&month=' . substr($date, 0, 7) . '&message=saved');
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

        return Response::redirect('/settings?tab=leaves&doctor_id=' . $doctorId . '&message=removed');
    }

    public function inviteStaff(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        try {
            StaffInvitationService::invite(
                $clinicId,
                trim((string) ($request->post['name'] ?? '')),
                strtolower(trim((string) ($request->post['email'] ?? ''))),
                (string) ($request->post['role'] ?? 'receptionist'),
            );
            AuditService::log($request, 'INSERT', 'staff_invitations', 0);

            return Response::redirect('/settings?tab=team&message=invited');
        } catch (\Throwable $e) {
            return Response::redirect('/settings?tab=team&error=' . urlencode($e->getMessage()));
        }
    }

    public function revokeInvite(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId !== null) {
            StaffInvitationService::revoke($clinicId, (int) $id);
        }

        return Response::redirect('/settings?tab=team&message=revoked');
    }

    public function updateStaff(Request $request, string $id): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }

        $perms = [];
        if (!empty($request->post['permissions']) && is_array($request->post['permissions'])) {
            $perms = $request->post['permissions'];
        }

        StaffInvitationService::updateStaff($clinicId, (int) $id, [
            'role' => $request->post['role'] ?? null,
            'custom_permissions' => $perms,
            'is_active' => isset($request->post['is_active']) ? 1 : 0,
        ]);
        AuditService::log($request, 'UPDATE', 'users', (int) $id);

        return Response::redirect('/settings?tab=team&message=updated');
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

    public function saveConsentForm(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!ModuleGate::check('consent')) {
            return Response::redirect('/settings?tab=general&error=' . urlencode('Consent module not active'));
        }

        $templateId = !empty($request->post['template_id']) ? (int) $request->post['template_id'] : null;
        ConsentTemplateService::save($clinicId, $templateId, $request->post);
        AuditService::log($request, $templateId ? 'UPDATE' : 'INSERT', 'consent_templates', $templateId ?? 0);

        return Response::redirect('/settings?tab=consent-forms&message=saved');
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

    /** @return array<string, string> */
    private function countries(): array
    {
        return [
            'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom',
            'AE' => 'UAE', 'SG' => 'Singapore', 'MY' => 'Malaysia', 'CA' => 'Canada',
        ];
    }
}
