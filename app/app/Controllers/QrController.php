<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\PatientService;
use App\Support\View;

final class QrController
{
    public function resolve(Request $request, string $token): Response
    {
        $patient = PatientService::findByQrToken($token);
        if ($patient === null) {
            return Response::html(View::render('qr/not-found', []), 404);
        }

        $clinic = RequestContext::clinic();
        if ($clinic === null || (int) $clinic['id'] !== (int) $patient['clinic_id']) {
            return Response::html(View::render('qr/wrong-clinic', [
                'patientClinicId' => $patient['clinic_id'],
            ]), 403);
        }

        return Response::redirect('/patients/' . $patient['id']);
    }
}
