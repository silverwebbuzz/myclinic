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
        SettlementService::releaseMatured();
        $st = Database::connection()->prepare('SELECT * FROM store_payouts WHERE vendor_id = :v ORDER BY id DESC LIMIT 50');
        $st->execute(['v' => $vendorId]);

        return Response::html(View::render('store_vendor/payouts', [
            'balances' => SettlementService::balances($vendorId),
            'ledger' => SettlementService::ledger($vendorId, 100),
            'payouts' => $st->fetchAll(),
            'bank' => VendorService::primaryBank($vendorId),
            'vendor' => VendorService::find($vendorId) ?? $this->vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
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
