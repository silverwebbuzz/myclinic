<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\DocumentLinkService;

/**
 * GET /d/{type}/{token} — patient opens an invoice / diet plan PDF from a
 * WhatsApp or email link (no login; signed + expiring, see DocumentLinkService).
 */
final class DocumentLinkController
{
    public function show(Request $request, string $type, string $token): Response
    {
        $abs = DocumentLinkService::resolve($type, $token);
        if ($abs === null) {
            return Response::html(
                '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<meta name="robots" content="noindex,nofollow"><title>Link expired</title></head>'
                . '<body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:28rem;margin:4rem auto;padding:0 1rem;color:#334155;text-align:center;">'
                . '<h1 style="font-size:1.25rem;color:#0f172a;">This link has expired</h1>'
                . '<p>For your privacy, document links work for 30 days. Please ask your clinic to send it again.</p>'
                . '</body></html>',
                404,
            )->withHeader('X-Robots-Tag', 'noindex, nofollow')
             ->withHeader('Cache-Control', 'no-store');
        }

        $name = $type === 'diet' ? 'Diet-plan.pdf' : 'Invoice.pdf';

        return Response::download($abs, $name)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $name . '"')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }
}
