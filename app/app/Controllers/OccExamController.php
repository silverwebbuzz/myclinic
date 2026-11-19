<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\OccHealth\CertificatePdfService;
use App\Services\OccHealth\OccExamService;
use App\Services\VisitService;
use App\Support\SessionFlash;

/**
 * Clinic app: the "Occupational exam" panel actions on a corporate visit
 * (/visits/{id}/occupational/*). Signing needs the signed-in user's own
 * verified registration (checked in OccExamService).
 */
final class OccExamController
{
    public function sign(Request $request, string $id): Response
    {
        [$clinicId, $visit] = $this->visit((int) $id);
        if ($visit === null) {
            return Response::html('Visit not found', 404);
        }
        $res = OccExamService::sign($clinicId, (int) (RequestContext::user()['id'] ?? 0), $visit, $request->post);
        SessionFlash::put($res['ok'] ? 'occ_ok' : 'occ_err', $res['ok']
            ? 'Certificate issued. The employee can download it; the company sees the outcome only if the employee agreed.'
            : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/visits/' . (int) $id . '#occupational');
    }

    public function revoke(Request $request, string $id, string $certId): Response
    {
        [$clinicId, $visit] = $this->visit((int) $id);
        $cert = $this->certOfVisit($clinicId, $visit, (int) $certId);
        if ($cert === null) {
            return Response::html('Not found', 404);
        }
        $res = OccExamService::revoke($clinicId, RequestContext::user() ?? [], (int) $certId, (string) ($request->post['reason'] ?? ''));
        SessionFlash::put($res['ok'] ? 'occ_ok' : 'occ_err', $res['ok'] ? 'Certificate revoked.' : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/visits/' . (int) $id . '#occupational');
    }

    public function pdf(Request $request, string $id, string $certId): Response
    {
        [$clinicId, $visit] = $this->visit((int) $id);
        $cert = $this->certOfVisit($clinicId, $visit, (int) $certId);

        return $cert === null ? Response::html('Not found', 404) : CertificatePdfService::response($cert);
    }

    /** The certificate, only if it belongs to the corporate booking behind this visit. @return array<string, mixed>|null */
    private function certOfVisit(int $clinicId, ?array $visit, int $certId): ?array
    {
        if ($visit === null) {
            return null;
        }
        $panel = OccExamService::forVisit($clinicId, $visit, (int) (RequestContext::user()['id'] ?? 0));
        $cert = OccExamService::certificate($clinicId, $certId);

        return $panel !== null && $cert !== null && (int) $cert['booking_id'] === (int) $panel['booking']['id'] ? $cert : null;
    }

    /** @return array{0:int, 1:?array<string, mixed>} */
    private function visit(int $id): array
    {
        $clinicId = (int) RequestContext::clinicId();
        $visit = $clinicId > 0 ? VisitService::find($clinicId, $id) : null;

        return [$clinicId, $visit ?: null];
    }
}
