<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\LabOrderService;
use App\Support\View;

final class LabReportController
{
    public function show(Request $request, string $token): Response
    {
        $order = LabOrderService::findByShareToken($token);
        if ($order === null) {
            return Response::html('Report link expired or invalid', 404);
        }

        if (!empty($order['report_path'])) {
            return Response::redirect($order['report_path']);
        }

        return Response::html(View::render('lab/report', ['order' => $order]));
    }
}
