<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\PublicBookingService;
use App\Support\View;

final class BookController
{
    public function show(Request $request, string $slug): Response
    {
        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::html('Clinic not found', 404);
        }

        $clinicId = (int) $clinic['id'];
        $doctors = PublicBookingService::doctors($clinicId);
        $windowDays = PublicBookingService::bookingWindowDays($clinicId);

        // Build the 7-day week strip starting today.
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $ts = strtotime('+' . $i . ' day');
            $days[] = [
                'date' => date('Y-m-d', $ts),
                'weekday' => strtoupper(date('D', $ts)),
                'day' => (int) date('d', $ts),
                'month' => date('M', $ts),
                'is_today' => $i === 0,
                // Matches PublicBookingService::isWithinBookingWindow: "N days"
                // means day offsets 0..N-1 are bookable (day N is outside).
                'within_window' => $i < $windowDays,
            ];
        }

        // If the visitor is logged in on /patient, prefill name+phone so we don't
        // ask again (and the booking form can lock those fields).
        $me = PublicBookingService::currentPatientIdentity();

        return Response::html(View::render('book/index', [
            'clinic' => $clinic,
            'doctors' => $doctors,
            'doctorId' => $doctors[0]['id'] ?? 0,
            'slug' => $slug,
            'days' => $days,
            'windowDays' => $windowDays,
            'confirmation' => null,
            'patientName' => $me['name'] ?? '',
            'patientPhone' => $me['phone'] ?? '',
            'patientLoggedIn' => $me !== null,
            'csrf' => CsrfService::token(),
        ]));
    }

    public function book(Request $request, string $slug): Response
    {
        $clinic = PublicBookingService::clinicBySlug($slug);
        if ($clinic === null) {
            return Response::html('Clinic not found', 404);
        }

        try {
            $result = PublicBookingService::book((int) $clinic['id'], $request->post);

            return Response::html(View::render('book/index', [
                'clinic' => $clinic,
                'doctors' => PublicBookingService::doctors((int) $clinic['id']),
                'doctorId' => (int) ($request->post['doctor_id'] ?? 0),
                'slug' => $slug,
                'days' => [],
                'windowDays' => PublicBookingService::bookingWindowDays((int) $clinic['id']),
                'confirmation' => [
                    'patient_name' => $result['patient']['name'] ?? '',
                    'date' => date('D, j M Y', strtotime((string) $result['appointment']['scheduled_at'])),
                    'time' => date('g:i A', strtotime((string) $result['appointment']['scheduled_at'])),
                    'token' => $result['appointment']['token_number'] ?? null,
                    'appointment_id' => $result['appointment']['id'] ?? null,
                ],
                'csrf' => CsrfService::token(),
            ]));
        } catch (\Throwable $e) {
            return Response::html(View::render('book/index', [
                'clinic' => $clinic,
                'doctors' => PublicBookingService::doctors((int) $clinic['id']),
                'doctorId' => (int) ($request->post['doctor_id'] ?? 0),
                'slug' => $slug,
                'days' => [],
                'windowDays' => PublicBookingService::bookingWindowDays((int) $clinic['id']),
                'confirmation' => null,
                'error' => $e->getMessage(),
                'csrf' => CsrfService::token(),
            ]), 422);
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
