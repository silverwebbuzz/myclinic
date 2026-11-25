<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Services\OccHealth\OccExamService;
use App\Services\SlotService;
use App\Support\Locale\LocalDate;
use App\Support\View;

/**
 * Public certificate check (design §4, C13): /verify/{token}, from the QR on
 * the PDF. No login; rate-limited. Shows only what is printed on the
 * certificate, its status (valid / expired / revoked / replaced) and whether
 * the record still matches its fingerprint. Nothing clinical.
 */
final class CertificateVerifyController
{
    public function show(Request $request, string $token): Response
    {
        $cert = null;
        if (preg_match('/^[a-f0-9]{48}$/', $token) === 1) {
            try {
                $st = Database::connection()->prepare(
                    'SELECT * FROM corp_fitness_certificates WHERE verify_token = :t LIMIT 1 /* cross-company: public verify by secret token */'
                );
                $st->execute(['t' => $token]);
                $cert = $st->fetch() ?: null;
            } catch (\Throwable $e) {
                $cert = null;
            }
        }
        if ($cert === null) {
            return Response::html(View::render('verify/certificate', ['cert' => null]), 404)->withHeader('X-Robots-Tag', 'noindex');
        }
        $today = LocalDate::today(SlotService::clinicTimezone((int) $cert['clinic_id']));
        $state = match (true) {
            $cert['status'] === 'revoked' => 'revoked',
            $cert['status'] === 'replaced' => 'replaced',
            !empty($cert['valid_to']) && (string) $cert['valid_to'] < $today => 'expired',
            default => 'valid',
        };

        return Response::html(View::render('verify/certificate', [
            'cert' => $cert,
            'state' => $state,
            'intact' => hash_equals((string) $cert['sha256'], OccExamService::hash($cert)),
            'snap' => json_decode((string) $cert['checks_json'], true) ?: [],
        ]))->withHeader('X-Robots-Tag', 'noindex')->withHeader('Cache-Control', 'no-store');
    }
}
