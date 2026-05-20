<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DischargeService;
use App\Services\PortalAuthService;
use App\Services\PortalDashboardService;
use App\Services\SignedDownloadService;
use App\Support\Layout;

final class PortalController
{
    public function home(Request $request): Response
    {
        return Response::redirect('/portal/login');
    }

    public function login(Request $request): Response
    {
        if (!$this->portalModuleActive()) {
            return Response::html('Patient portal is not available for this clinic.', 402);
        }

        $clinicId = (int) RequestContext::clinicId();
        if (PortalAuthService::patientFromCookie($clinicId) !== null) {
            return Response::redirect('/portal/dashboard');
        }

        return Response::html(Layout::portal('portal/login', [
            'clinic' => RequestContext::clinic(),
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
            'dev_otp' => $request->query['dev_otp'] ?? null,
        ], 'Patient login'));
    }

    public function sendOtp(Request $request): Response
    {
        if (!$this->portalModuleActive()) {
            return Response::redirect('/portal/login?message=unavailable');
        }

        $result = PortalAuthService::sendOtp((int) RequestContext::clinicId(), (string) ($request->post['phone'] ?? ''));
        if (!$result['ok']) {
            return Response::redirect('/portal/login?message=' . urlencode($result['message']));
        }

        $q = 'otp_sent=1&phone=' . urlencode($request->post['phone'] ?? '');
        if (!empty($result['dev_otp'])) {
            $q .= '&dev_otp=' . urlencode($result['dev_otp']);
        }

        return Response::redirect('/portal/login?' . $q);
    }

    public function verifyOtp(Request $request): Response
    {
        if (!$this->portalModuleActive()) {
            return Response::redirect('/portal/login');
        }

        $patient = PortalAuthService::verifyOtp(
            (int) RequestContext::clinicId(),
            (string) ($request->post['phone'] ?? ''),
            (string) ($request->post['otp'] ?? ''),
        );

        if ($patient === null) {
            return Response::redirect('/portal/login?message=' . urlencode('Invalid or expired OTP'));
        }

        return Response::redirect('/portal/dashboard');
    }

    public function dashboard(Request $request): Response
    {
        if ($denied = $this->requirePatient()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $patientId = (int) RequestContext::portalPatientId();

        return Response::html(Layout::portal('portal/dashboard', array_merge(
            PortalDashboardService::data($clinicId, $patientId),
            ['clinic' => RequestContext::clinic(), 'csrf' => CsrfService::token()],
        ), 'My health'));
    }

    public function download(Request $request, string $token): Response
    {
        if ($denied = $this->requirePatient()) {
            return $denied;
        }

        $file = SignedDownloadService::resolve(
            $token,
            (int) RequestContext::clinicId(),
            (int) RequestContext::portalPatientId(),
        );

        if ($file === null) {
            return Response::html('Download link expired or invalid', 404);
        }

        $path = dirname(__DIR__, 2) . '/public' . $file['path'];
        if (!is_file($path)) {
            return Response::html('File not found', 404);
        }

        return Response::download($path, basename($path));
    }

    public function logout(Request $request): Response
    {
        PortalAuthService::clearPortalCookie();

        return Response::redirect('/portal/login?message=logged_out');
    }

    public function discharge(Request $request, string $token): Response
    {
        $summary = DischargeService::findByShareToken($token);
        if ($summary === null) {
            return Response::html('Discharge summary not found', 404);
        }

        if (!empty($summary['pdf_path'])) {
            return Response::redirect($summary['pdf_path']);
        }

        return Response::html(Layout::portal('portal/discharge', [
            'summary' => $summary,
            'clinic' => RequestContext::clinic(),
        ], 'Discharge summary'));
    }

    private function portalModuleActive(): bool
    {
        return ModuleGate::check('patient_portal');
    }

    private function requirePatient(): ?Response
    {
        if (!$this->portalModuleActive()) {
            return Response::html('Patient portal is not active.', 402);
        }

        $clinicId = (int) RequestContext::clinicId();
        $patient = PortalAuthService::patientFromCookie($clinicId);
        if ($patient === null) {
            return Response::redirect('/portal/login');
        }

        RequestContext::setPortalPatient($patient);

        return null;
    }
}
