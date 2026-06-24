<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\DirectoryProfileUrlService;
use App\Services\PublicBookingService;

/**
 * Public booking API — POST confirm + slots JSON only.
 * Patient UI lives on the marketing-site profile page (#book widget).
 */
final class BookController
{
    /** @param array<string, string> $bookConfig */
    public function bookWithExtras(Request $request, string $slug, array $bookConfig): Response
    {
        $profileUrl = DirectoryProfileUrlService::publicBookingUrlForTenantSlug($slug);
        $returnTo = trim((string) ($request->post['return_to'] ?? ''));
        if ($returnTo !== '' && str_starts_with($returnTo, '/') && str_contains($returnTo, '#book')) {
            $redirectTo = $returnTo;
        } else {
            $redirectTo = $profileUrl;
        }

        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::html('Clinic not found', 404);
        }

        $clinicId = (int) $clinic['id'];

        try {
            $result = PublicBookingService::book($clinicId, $request->post);

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['ecp_book_flash'] = [
                'patient_name' => $result['patient']['name'] ?? '',
                'date' => date('D, j M Y', strtotime((string) $result['appointment']['scheduled_at'])),
                'time' => date('g:i A', strtotime((string) $result['appointment']['scheduled_at'])),
                'token' => $result['appointment']['token_number'] ?? null,
                'appointment_id' => $result['appointment']['id'] ?? null,
            ];

            return Response::redirect($redirectTo);
        } catch (\Throwable $e) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['ecp_book_error'] = $e->getMessage();

            return Response::redirect($redirectTo, 303);
        }
    }

    public function slotsApi(Request $request, string $slug): Response
    {
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
        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::json(['error' => 'Not found'], 404);
        }

        $phone = (string) ($request->query['phone'] ?? '');

        return Response::json(PublicBookingService::findByPhonePublic((int) $clinic['id'], $phone));
    }
}
