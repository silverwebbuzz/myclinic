<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

use App\Controllers\BookController;
use App\Core\Application;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;

/**
 * Bootstrap the portal app kernel for public booking on eclinicpro.com.
 * Marketing pages call this instead of routing through app.eclinicpro.com.
 */
function ecp_book_bootstrap(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    $base = dirname(__DIR__) . '/app';
    $autoload = $base . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Booking is temporarily unavailable (app dependencies missing).\n";
        exit;
    }

    require_once $autoload;
    Application::boot($base);
    $booted = true;
}

/** @return array<string, string> */
function ecp_book_view_config(string $slug): array
{
    return [
        'authMe' => '/api/patient_auth.php?action=me',
        'authSendOtp' => '/api/patient_auth.php?action=send_otp',
        'authVerifyOtp' => '/api/patient_auth.php?action=verify_otp',
        'authLogout' => '/api/patient_auth.php?action=logout',
        'slotsUrl' => '/book/' . rawurlencode($slug) . '/slots',
        'formAction' => '/book/' . rawurlencode($slug),
        'siteHomeUrl' => ecp_site_url('/'),
        'findDoctorUrl' => ecp_site_url('/find-a-doctor'),
        'patientPanelUrl' => ecp_site_url('/patient'),
    ];
}

function ecp_book_profile_url(string $slug): string
{
    ecp_book_bootstrap();

    return \App\Services\DirectoryProfileUrlService::publicBookingUrlForTenantSlug($slug);
}

function ecp_book_dispatch(string $action, string $slug): Response
{
    ecp_book_bootstrap();

    $slug = strtolower(trim($slug));
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        return Response::html('Clinic not found', 404);
    }

    $request = Request::capture();
    $controller = new BookController();
    $extras = ecp_book_view_config($slug);

    if ($action === 'show') {
        return Response::redirect(ecp_book_profile_url($slug), 301);
    }

    if ($action === 'book') {
        $token = (string) ($request->post['_csrf'] ?? '');
        if (!CsrfService::verify($token !== '' ? $token : null)) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['ecp_book_error'] = 'Invalid session — please try again.';

            return Response::redirect(ecp_book_profile_url($slug));
        }

        return $controller->bookWithExtras($request, $slug, $extras);
    }

    return Response::redirect(ecp_book_profile_url($slug), 301);
}

function ecp_book_slots(string $slug): Response
{
    ecp_book_bootstrap();

    $slug = strtolower(trim($slug));
    if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
        return Response::json(['error' => 'Not found'], 404);
    }

    $request = Request::capture();

    return (new BookController())->slotsApi($request, $slug);
}

/** @return list<array<string, mixed>> */
function ecp_book_build_week_days(int $windowDays): array
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

/**
 * Booking context for an embedded widget on directory profile pages.
 *
 * @param array<string, mixed> $profile Row from ecp_profile_build()
 * @return array<string, mixed>
 */
function ecp_profile_booking_context(array $profile): array
{
    require_once __DIR__ . '/patient_auth.php';

    $me = ecp_patient_current();
    $patientName = (string) ($me['name'] ?? '');
    $patientPhone = (string) ($me['phone'] ?? '');
    $patientLoggedIn = $me !== null;
    $profilePath = (string) ($profile['canonical'] ?? '');

    $leadDoctor = [
        'id' => (int) ($profile['id'] ?? 0),
        'name' => (string) ($profile['display_name'] ?? ''),
        'city' => (string) ($profile['city'] ?? ''),
        'area' => (string) ($profile['area'] ?? ''),
    ];

    $base = [
        'patientName' => $patientName,
        'patientPhone' => $patientPhone,
        'patientLoggedIn' => $patientLoggedIn,
        'profileUrl' => $profilePath,
        'leadDoctor' => $leadDoctor,
    ];

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $confirmation = $_SESSION['ecp_book_flash'] ?? null;
    unset($_SESSION['ecp_book_flash']);
    $bookingError = $_SESSION['ecp_book_error'] ?? null;
    unset($_SESSION['ecp_book_error']);

    if (empty($profile['is_claimed']) || empty($profile['tenant_slug'])) {
        return array_merge($base, [
            'mode' => 'lead',
            'confirmation' => null,
            'bookingError' => is_string($bookingError) ? $bookingError : null,
        ]);
    }

    try {
        ecp_book_bootstrap();
        $slug = (string) $profile['tenant_slug'];
        $clinic = \App\Services\PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return array_merge($base, ['mode' => 'lead', 'confirmation' => null]);
        }

        $clinicId = (int) $clinic['id'];
        $windowDays = \App\Services\PublicBookingService::bookingWindowDays($clinicId);
        $doctors = \App\Services\PublicBookingService::doctors($clinicId);
        $bookConfig = ecp_book_view_config($slug);
        $bookConfig['returnTo'] = $profilePath . '#book';

        return array_merge($base, [
            'mode' => 'claimed',
            'slug' => $slug,
            'clinic' => $clinic,
            'doctors' => $doctors,
            'doctorId' => $doctors[0]['id'] ?? 0,
            'days' => ecp_book_build_week_days($windowDays),
            'windowDays' => $windowDays,
            'bookConfig' => $bookConfig,
            'confirmation' => is_array($confirmation) ? $confirmation : null,
            'csrf' => CsrfService::token(),
            'brandColor' => $clinic['brand_color'] ?? '#0F9B6E',
            'bookingError' => is_string($bookingError) ? $bookingError : null,
            'embedMode' => true,
        ]);
    } catch (\Throwable $e) {
        error_log('[ecp_profile_booking_context] ' . $e->getMessage());

        return array_merge($base, ['mode' => 'lead', 'confirmation' => null]);
    }
}
