<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\PartnerCommissionService;
use App\Services\PartnerDocumentService;
use App\Services\PartnerPayoutService;
use App\Services\PartnerService;
use App\Services\PartnerSettingsService;
use App\Support\View;

/**
 * The partner's own dashboard: referral link/code, referred clinics (their
 * "accounts"), earnings ledger, payout requests, and KYC document uploads.
 */
final class PartnerDashboardController
{
    public function dashboard(Request $request): Response
    {
        $partner = RequestContext::partner();
        $pid = (int) $partner['id'];

        return Response::html(View::render('partner/dashboard', [
            'partner' => $partner,
            'summary' => PartnerService::earningsSummary($pid),
            'clinics' => PartnerService::referredClinics($pid),
            'effectivePercent' => PartnerService::effectivePercent($partner),
            'referralUrl' => $this->referralUrl((string) $partner['referral_code']),
            'minPayout' => PartnerSettingsService::minPayoutAmount(),
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    public function referrals(Request $request): Response
    {
        $partner = RequestContext::partner();

        return Response::html(View::render('partner/referrals', [
            'partner' => $partner,
            'clinics' => PartnerService::referredClinics((int) $partner['id']),
            'referralUrl' => $this->referralUrl((string) $partner['referral_code']),
        ]));
    }

    public function earnings(Request $request): Response
    {
        $partner = RequestContext::partner();
        $pid = (int) $partner['id'];

        return Response::html(View::render('partner/earnings', [
            'partner' => $partner,
            'summary' => PartnerService::earningsSummary($pid),
            'ledger' => PartnerCommissionService::ledgerForPartner($pid),
            'payouts' => PartnerPayoutService::forPartner($pid),
            'minPayout' => PartnerSettingsService::minPayoutAmount(),
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function requestPayout(Request $request): Response
    {
        $partner = RequestContext::partner();
        $result = PartnerPayoutService::requestPayout((int) $partner['id']);

        if (!$result['ok']) {
            return Response::redirect('/partner/earnings?error=' . urlencode($result['error'] ?? 'failed'));
        }

        return Response::redirect('/partner/earnings?message=' . urlencode('Payout requested. We process within 7 days.'));
    }

    public function documents(Request $request): Response
    {
        $partner = RequestContext::partner();

        return Response::html(View::render('partner/documents', [
            'partner' => $partner,
            'documents' => PartnerDocumentService::forPartner((int) $partner['id']),
            'csrf' => CsrfService::token(),
            'welcome' => isset($request->query['welcome']),
            'message' => $request->query['message'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function uploadDocument(Request $request): Response
    {
        $partner = RequestContext::partner();
        $docType = $request->post['doc_type'] ?? '';
        $file = $_FILES['document'] ?? [];

        $result = PartnerDocumentService::store((int) $partner['id'], $docType, $file);
        if (!$result['ok']) {
            return Response::redirect('/partner/documents?error=' . urlencode($result['error'] ?? 'failed'));
        }

        return Response::redirect('/partner/documents?message=' . urlencode('Document uploaded.'));
    }

    public function savePayoutDetails(Request $request): Response
    {
        $partner = RequestContext::partner();
        PartnerService::updatePayoutDetails((int) $partner['id'], [
            'payout_method' => in_array($request->post['payout_method'] ?? '', ['upi', 'bank'], true) ? $request->post['payout_method'] : null,
            'upi_id' => trim($request->post['upi_id'] ?? '') ?: null,
            'bank_account_name' => trim($request->post['bank_account_name'] ?? '') ?: null,
            'bank_account_no' => trim($request->post['bank_account_no'] ?? '') ?: null,
            'bank_ifsc' => trim($request->post['bank_ifsc'] ?? '') ?: null,
            'pan_number' => trim($request->post['pan_number'] ?? '') ?: null,
        ]);

        return Response::redirect('/partner/earnings?message=' . urlencode('Payout details saved.'));
    }

    private function referralUrl(string $code): string
    {
        $base = rtrim($_ENV['MARKETING_URL'] ?? $_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

        return $base . '/?ref=' . urlencode($code);
    }
}
