<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
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
        $doctorId = (int) ($request->query['doctor_id'] ?? ($doctors[0]['id'] ?? 0));
        $date = $request->query['date'] ?? date('Y-m-d', strtotime('+1 day'));
        $slots = $doctorId > 0 ? PublicBookingService::slots($clinicId, $doctorId, $date) : [];

        return Response::html(View::render('book/index', [
            'clinic' => $clinic,
            'doctors' => $doctors,
            'doctorId' => $doctorId,
            'date' => $date,
            'slots' => $slots,
            'slug' => $slug,
            'booked' => $request->query['booked'] ?? null,
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
            PublicBookingService::book((int) $clinic['id'], $request->post);

            return Response::redirect('/book/' . $slug . '?booked=1');
        } catch (\Throwable $e) {
            return Response::html($e->getMessage(), 422);
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
}
