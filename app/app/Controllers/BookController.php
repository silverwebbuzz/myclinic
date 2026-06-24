<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\DirectoryProfileUrlService;
use App\Services\PatientIdentityAuthService;
use App\Services\PublicBookingService;
use App\Support\View;

final class BookController
{
    public function show(Request $request, string $slug): Response
    {
        if ($redirect = $this->marketingRedirect($slug)) {
            return $redirect;
        }

        return $this->showWithExtras($request, $slug, $this->defaultViewConfig($slug));
    }

    /** @param array<string, string> $bookConfig */
    public function showWithExtras(Request $request, string $slug, array $bookConfig, ?string $error = null): Response
    {
        if ($redirect = $this->profileBookingRedirect($slug)) {
            return $redirect;
        }

        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::html('Clinic not found', 404);
        }

        $clinicId = (int) $clinic['id'];
        $doctors = PublicBookingService::doctors($clinicId);
        $windowDays = PublicBookingService::bookingWindowDays($clinicId);
        $me = PatientIdentityAuthService::current();

        return Response::html(View::render('book/index', $this->viewData($slug, $bookConfig, [
            'clinic' => $clinic,
            'doctors' => $doctors,
            'doctorId' => $doctors[0]['id'] ?? 0,
            'days' => $this->buildWeekDays($windowDays),
            'windowDays' => $windowDays,
            'confirmation' => null,
            'error' => $error,
            'patientName' => $me['name'] ?? '',
            'patientPhone' => $me['phone'] ?? '',
            'patientLoggedIn' => $me !== null,
            'csrf' => CsrfService::token(),
        ])));
    }

    public function book(Request $request, string $slug): Response
    {
        if ($redirect = $this->marketingRedirect($slug)) {
            return $redirect;
        }

        return $this->bookWithExtras($request, $slug, $this->defaultViewConfig($slug));
    }

    /** @param array<string, string> $bookConfig */
    public function bookWithExtras(Request $request, string $slug, array $bookConfig): Response
    {
        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::html('Clinic not found', 404);
        }

        $clinicId = (int) $clinic['id'];
        $windowDays = PublicBookingService::bookingWindowDays($clinicId);
        $me = PatientIdentityAuthService::current();

        try {
            $result = PublicBookingService::book($clinicId, $request->post);

            $confirmation = [
                'patient_name' => $result['patient']['name'] ?? '',
                'date' => date('D, j M Y', strtotime((string) $result['appointment']['scheduled_at'])),
                'time' => date('g:i A', strtotime((string) $result['appointment']['scheduled_at'])),
                'token' => $result['appointment']['token_number'] ?? null,
                'appointment_id' => $result['appointment']['id'] ?? null,
            ];

            $returnTo = trim((string) ($request->post['return_to'] ?? ''));
            if ($returnTo !== '' && str_starts_with($returnTo, '/') && str_contains($returnTo, '#book')) {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                $_SESSION['ecp_book_flash'] = $confirmation;

                return Response::redirect($returnTo);
            }

            return Response::html(View::render('book/index', $this->viewData($slug, $bookConfig, [
                'clinic' => $clinic,
                'doctors' => PublicBookingService::doctors($clinicId),
                'doctorId' => (int) ($request->post['doctor_id'] ?? 0),
                'days' => [],
                'windowDays' => $windowDays,
                'confirmation' => $confirmation,
                'patientName' => $me['name'] ?? '',
                'patientPhone' => $me['phone'] ?? '',
                'patientLoggedIn' => $me !== null,
                'csrf' => CsrfService::token(),
            ])));
        } catch (\Throwable $e) {
            return Response::html(View::render('book/index', $this->viewData($slug, $bookConfig, [
                'clinic' => $clinic,
                'doctors' => PublicBookingService::doctors($clinicId),
                'doctorId' => (int) ($request->post['doctor_id'] ?? 0),
                'days' => $this->buildWeekDays($windowDays),
                'windowDays' => $windowDays,
                'confirmation' => null,
                'error' => $e->getMessage(),
                'patientName' => $me['name'] ?? '',
                'patientPhone' => $me['phone'] ?? '',
                'patientLoggedIn' => $me !== null,
                'csrf' => CsrfService::token(),
            ])), 422);
        }
    }

    public function slotsApi(Request $request, string $slug): Response
    {
        if ($redirect = $this->marketingApiRedirect($slug, 'slots')) {
            return $redirect;
        }

        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $doctorId = (int) ($request->query['doctor_id'] ?? 0);
        $dateRaw = (string) ($request->query['date'] ?? date('Y-m-d'));
        $ts = strtotime($dateRaw);
        $date = $ts !== false ? date('Y-m-d', $ts) : date('Y-m-d');
        $clinicId = (int) $clinic['id'];
        $slots = PublicBookingService::slots($clinicId, $doctorId, $date);

        return Response::json([
            'slots' => $slots,
            'meta' => [
                'clinic_id' => $clinicId,
                'doctor_id' => $doctorId,
                'date' => $date,
                'count' => count($slots),
            ],
        ]);
    }

    public function lookupApi(Request $request, string $slug): Response
    {
        if ($redirect = $this->marketingApiRedirect($slug, 'lookup')) {
            return $redirect;
        }

        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $phone = (string) ($request->query['phone'] ?? '');

        return Response::json(PublicBookingService::findByPhonePublic((int) $clinic['id'], $phone));
    }

  /** @return list<array<string, mixed>> */
    private function buildWeekDays(int $windowDays): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $ts = strtotime('+' . $i . ' day');
            $days[] = [
                'date' => date('Y-m-d', $ts),
                'weekday' => strtoupper(date('D', $ts)),
                'day' => (int) date('d', $ts),
                'month' => date('M', $ts),
                'is_today' => $i === 0,
                'within_window' => $i < $windowDays,
            ];
        }

        return $days;
    }

    /** @return array<string, string> */
    private function defaultViewConfig(string $slug): array
    {
        $site = rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/');

        return [
            'authMe' => '/api/patient-auth/me',
            'authSendOtp' => '/api/patient-auth/send-otp',
            'authVerifyOtp' => '/api/patient-auth/verify-otp',
            'authLogout' => '/api/patient-auth/logout',
            'slotsUrl' => '/book/' . rawurlencode($slug) . '/slots',
            'formAction' => '/book/' . rawurlencode($slug),
            'siteHomeUrl' => $site,
            'findDoctorUrl' => $site . '/find-a-doctor',
            'patientPanelUrl' => $site . '/patient',
        ];
    }

    /**
     * @param array<string, string> $bookConfig
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function viewData(string $slug, array $bookConfig, array $data): array
    {
        return array_merge($data, [
            'slug' => $slug,
            'bookConfig' => $bookConfig,
        ]);
    }

    private function marketingRedirect(string $slug): ?Response
    {
        if (!$this->isAppBookingHost()) {
            return null;
        }

        return Response::redirect(
            DirectoryProfileUrlService::publicBookingUrlForTenantSlug($slug),
            301,
        );
    }

    private function marketingApiRedirect(string $slug, string $suffix): ?Response
    {
        if (!$this->isAppBookingHost()) {
            return null;
        }

        $site = rtrim((string) ($_ENV['SITE_URL'] ?? 'https://eclinicpro.com'), '/');
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $url = $site . '/book/' . rawurlencode($slug) . '/' . $suffix;
        if ($qs !== '') {
            $url .= '?' . $qs;
        }

        return Response::redirect($url, 301);
    }

    /** Redirect standalone /book/{slug} on the marketing site to the profile widget. */
    private function profileBookingRedirect(string $slug): ?Response
    {
        if ($this->isAppBookingHost()) {
            return null;
        }

        $target = DirectoryProfileUrlService::publicBookingUrlForTenantSlug($slug);
        if (!str_contains($target, '#book')) {
            return null;
        }

        return Response::redirect($target, 301);
    }

    private function isAppBookingHost(): bool
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $baseDomain = strtolower((string) ($_ENV['APP_BASE_DOMAIN'] ?? 'app.eclinicpro.com'));

        return $host === $baseDomain || str_ends_with($host, '.' . $baseDomain);
    }
}
