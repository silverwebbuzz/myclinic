<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\RxWhatsAppService;

/**
 * GET /rx/{token} — the patient's secure prescription link (no login).
 *
 * Opened by the patient from WhatsApp/email, and fetched by Meta when it
 * attaches the PDF to the WhatsApp message. The token is signed and expires
 * after 30 days; anything invalid gets the same plain "expired" page, so the
 * response doesn't reveal whether a visit exists.
 */
final class RxShareController
{
    public function show(Request $request, string $token): Response
    {
        $file = RxWhatsAppService::resolveToken($token);
        if ($file === null) {
            return Response::html(
                '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<meta name="robots" content="noindex,nofollow"><title>Link expired</title></head>'
                . '<body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:28rem;margin:4rem auto;padding:0 1rem;color:#334155;text-align:center;">'
                . '<h1 style="font-size:1.25rem;color:#0f172a;">This prescription link has expired</h1>'
                . '<p>For your safety, prescription links work for 30 days. Please ask your clinic to send it again.</p>'
                . '</body></html>',
                404,
            )->withHeader('X-Robots-Tag', 'noindex, nofollow')
             ->withHeader('Cache-Control', 'no-store');
        }

        return Response::download($file['abs_path'], 'Prescription.pdf')
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="Prescription.pdf"')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    /**
     * GET /rx-sample.pdf — a SAMPLE prescription (made-up data only). Used by
     * the admin "Send test" when the PDF is attached, and handy as the sample
     * PDF Meta asks for when creating the template.
     */
    public function sample(Request $request): Response
    {
        try {
            $abs = \App\Services\PrescriptionPdfService::generate(
                ['id' => 0, 'doctor_id' => 0, 'doctor_name' => 'A. Sharma', 'visited_at' => date('Y-m-d H:i:s'),
                 'diagnosis' => 'Sample: acute pharyngitis', 'examination' => 'Sample prescription for testing WhatsApp delivery.'],
                ['name' => 'Riya (sample)', 'uhid' => 'SAMPLE', 'gender' => 'female'],
                ['id' => 0, 'name' => 'Sharma Clinic (sample)', 'phone' => '9820012345'],
                [],
                'A5',
            );
        } catch (\Throwable $e) {
            error_log('[rx-sample] ' . $e->getMessage());

            return Response::html('Sample unavailable', 500);
        }

        return Response::download($abs, 'Prescription-sample.pdf')
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="Prescription-sample.pdf"')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
