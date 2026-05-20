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
                'within_window' => $i <= $windowDays,
            ];
        }

        return Response::html(View::render('book/index', [
            'clinic' => $clinic,
            'doctors' => $doctors,
            'doctorId' => $doctors[0]['id'] ?? 0,
            'slug' => $slug,
            'days' => $days,
            'windowDays' => $windowDays,
            'confirmation' => null,
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
        $date = $request->query['date'] ?? date('Y-m-d');

        return Response::json([
            'slots' => PublicBookingService::slots((int) $clinic['id'], $doctorId, $date),
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
