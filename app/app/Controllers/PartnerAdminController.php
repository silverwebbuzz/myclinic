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
 * Platform-admin management of the partner program. Mounted under the existing
 * /admin (superadmin) middleware group.
 */
final class PartnerAdminController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/partners', [
            'admin' => RequestContext::superAdmin(),
            'partners' => PartnerService::all($request->query['status'] ?? null),
            'settings' => PartnerSettingsService::get(),
            'csrf' => CsrfService::token(),
            'filterStatus' => $request->query['status'] ?? null,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    public function show(Request $request, string $id): Response
    {
        $partnerId = (int) $id;
        $partner = PartnerService::find($partnerId);
        if ($partner === null) {
            return Response::redirect('/admin/partners?message=' . urlencode('Partner not found.'));
        }

        return Response::html(View::render('admin/partner_detail', [
            'admin' => RequestContext::superAdmin(),
            'partner' => $partner,
            'documents' => PartnerDocumentService::forPartner($partnerId),
            'clinics' => PartnerService::referredClinics($partnerId),
            'summary' => PartnerService::earningsSummary($partnerId),
            'ledger' => PartnerCommissionService::ledgerForPartner($partnerId),
            'effectivePercent' => PartnerService::effectivePercent($partner),
            'defaultPercent' => PartnerSettingsService::defaultPercent(),
            'csrf' => CsrfService::token(),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    public function approve(Request $request, string $id): Response
    {
        $admin = RequestContext::superAdmin();
        PartnerService::approve((int) $id, (int) $admin['id']);

        return Response::redirect('/admin/partners/' . (int) $id . '?message=' . urlencode('Partner approved.'));
    }

    public function setStatus(Request $request, string $id): Response
    {
        PartnerService::setStatus((int) $id, $request->post['status'] ?? '');

        return Response::redirect('/admin/partners/' . (int) $id . '?message=' . urlencode('Status updated.'));
    }

    public function setOverride(Request $request, string $id): Response
    {
        $raw = trim($request->post['commission_percent_override'] ?? '');
        $percent = $raw === '' ? null : (float) $raw;
        PartnerService::setCommissionOverride((int) $id, $percent);

        return Response::redirect('/admin/partners/' . (int) $id . '?message=' . urlencode('Commission override saved.'));
    }

    public function reviewDocument(Request $request, string $id): Response
    {
        $admin = RequestContext::superAdmin();
        $docId = (int) ($request->post['doc_id'] ?? 0);
        $status = $request->post['status'] ?? '';
        PartnerDocumentService::review($docId, $status, (int) $admin['id']);

        return Response::redirect('/admin/partners/' . (int) $id . '?message=' . urlencode('Document reviewed.'));
    }

    public function saveSettings(Request $request): Response
    {
        PartnerSettingsService::update([
            'default_commission_percent' => (float) ($request->post['default_commission_percent'] ?? 10),
            'commission_on_renewals' => isset($request->post['commission_on_renewals']) ? 1 : 0,
            'clearance_days' => (int) ($request->post['clearance_days'] ?? 15),
            'min_payout_amount' => (float) ($request->post['min_payout_amount'] ?? 1000),
            'cookie_window_days' => (int) ($request->post['cookie_window_days'] ?? 30),
        ]);

        return Response::redirect('/admin/partners?message=' . urlencode('Settings saved.'));
    }

    public function payouts(Request $request): Response
    {
        return Response::html(View::render('admin/partner_payouts', [
            'admin' => RequestContext::superAdmin(),
            'requests' => PartnerPayoutService::queue($request->query['status'] ?? null),
            'csrf' => CsrfService::token(),
            'filterStatus' => $request->query['status'] ?? null,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    public function processPayout(Request $request, string $id): Response
    {
        $admin = RequestContext::superAdmin();
        $ok = PartnerPayoutService::updateStatus(
            (int) $id,
            $request->post['status'] ?? '',
            (int) $admin['id'],
            trim($request->post['payment_reference'] ?? '') ?: null,
            trim($request->post['admin_note'] ?? '') ?: null,
        );

        $msg = $ok ? 'Payout updated.' : 'Could not update payout.';

        return Response::redirect('/admin/partner-payouts?message=' . urlencode($msg));
    }
}
