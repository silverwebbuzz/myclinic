<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\SettlementService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/** Seller earnings & payouts (/vendor/payouts) — own data only. */
final class VendorPayoutController
{
    public function index(Request $request): Response
    {
        $vendorId = (int) $this->vendor()['id'];
        try {
            SettlementService::releaseMatured();
            $st = Database::connection()->prepare('SELECT * FROM store_payouts WHERE vendor_id = :v ORDER BY id DESC LIMIT 50');
            $st->execute(['v' => $vendorId]);
            $payouts = $st->fetchAll();
            foreach ($payouts as &$po) {
                // Declined requests: show the reason the seller was emailed.
                $po['decline_reason'] = $po['status'] === 'cancelled' ? (SettlementService::requestFor((int) $po['id'])['decline_reason'] ?? null) : null;
            }
            unset($po);
            $data = ['balances' => SettlementService::balances($vendorId), 'ledger' => SettlementService::ledger($vendorId, 100), 'payouts' => $payouts];
        } catch (\Throwable $e) {
            error_log('[VendorPayout::index] ' . $e->getMessage());
            SessionFlash::put('store_err', 'Payouts are temporarily unavailable. Please try again shortly.');
            $data = ['balances' => ['pending' => 0, 'available' => 0, 'in_payout' => 0, 'paid' => 0], 'ledger' => [], 'payouts' => []];
        }

        return Response::html(View::render('store_vendor/payouts', $data + [
            'minPayout' => \App\Services\Store\StoreSettings::int('store_min_payout_paise', 10000),
            'bank' => VendorService::primaryBank($vendorId),
            'vendor' => VendorService::find($vendorId) ?? $this->vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    /** POST /vendor/payouts/request — seller asks to be paid their available balance. */
    public function request(Request $request): Response
    {
        $user = RequestContext::vendorUser();
        $res = SettlementService::requestPayout((int) $this->vendor()['id'], (int) ($user['id'] ?? 0), (string) ($request->post['note'] ?? ''));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok']
            ? 'Payout requested. We\'ve emailed you a confirmation and will email again when it\'s sent.'
            : ($res['error'] ?? 'Could not request the payout.'));

        return Response::redirect('/vendor/payouts');
    }

    public function statement(Request $request, string $id): Response
    {
        $p = QueryBuilder::table('store_payouts')->where('id', '=', (int) $id)->where('vendor_id', '=', (int) $this->vendor()['id'])->first();

        return $p === null ? Response::html('Not found', 404) : StorePayoutAdminController::csv(SettlementService::statementCsv($p), $p['payout_no'] . '.csv');
    }

    /** @return array<string, mixed> */
    private function vendor(): array
    {
        return RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.');
    }
}
