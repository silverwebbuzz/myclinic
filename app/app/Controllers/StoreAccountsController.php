<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\AccountsService;
use App\Services\Store\SellerInvoiceService;
use App\Services\Store\TaxDocumentService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Accounts for the CA (/admin/store/accounts) and eClinicPro's monthly GST invoices
 * to sellers for commission + courier + charges (admin + the seller's own copies).
 */
final class StoreAccountsController
{
    public function index(Request $request): Response
    {
        $period = (string) ($request->query['month'] ?? date('Y-m'));
        if (!SellerInvoiceService::validPeriod($period)) {
            $period = date('Y-m');
        }
        try {
            $data = AccountsService::month($period);
            $missing = false;
        } catch (\Throwable $e) {
            error_log('[StoreAccounts::index] ' . $e->getMessage());
            $data = ['period' => $period, 'sections' => [], 'sellers' => []];
            $missing = true;
        }

        return Response::html(View::render('admin/store_accounts', $data + [
            'invoices' => SellerInvoiceService::listing($period, null),
            'invoiceTableMissing' => !$this->invoiceTableExists(),
            'platformReady' => TaxDocumentService::platformReady(),
            'monthEnded' => $period < date('Y-m'),
            'tableMissing' => $missing,
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function export(Request $request): Response
    {
        $period = (string) ($request->query['month'] ?? date('Y-m'));

        return StorePayoutAdminController::csv(AccountsService::csv($period), 'store-accounts-' . $period . '.csv');
    }

    public function ledgerExport(Request $request): Response
    {
        $period = (string) ($request->query['month'] ?? date('Y-m'));

        return StorePayoutAdminController::csv(AccountsService::ledgerCsv($period), 'store-seller-ledger-' . $period . '.csv');
    }

    public function invoicesExport(Request $request): Response
    {
        $period = (string) ($request->query['month'] ?? date('Y-m'));

        return StorePayoutAdminController::csv(SellerInvoiceService::csv($period), 'store-invoices-to-sellers-' . $period . '.csv');
    }

    /** Issue (or finish issuing) every seller's invoice for a finished month. */
    public function issueInvoices(Request $request): Response
    {
        $period = (string) ($request->post['month'] ?? '');
        try {
            $res = SellerInvoiceService::issueMonth($period);
        } catch (\Throwable $e) {
            error_log('[StoreAccounts::issueInvoices] ' . $e->getMessage());
            $res = ['issued' => 0, 'errors' => ['Could not issue. Import 2026_10_05_store_seller_invoices.sql first.']];
        }
        if ($res['errors']) {
            SessionFlash::put('store_err', implode(' ', array_slice($res['errors'], 0, 3)));
        } else {
            SessionFlash::put('store_ok', $res['issued'] > 0 ? $res['issued'] . ' document(s) issued.' : 'All invoices for this month were already issued (or there was nothing to invoice).');
        }
        \App\Services\Store\StoreAudit::log('seller_invoice.issue_month', 'setting', null, null, ['period' => $period, 'by' => (int) (RequestContext::superAdmin()['id'] ?? 0)]);

        return Response::redirect('/admin/store/accounts?month=' . rawurlencode($period));
    }

    public function adminInvoice(Request $request, string $id): Response
    {
        $doc = SellerInvoiceService::load((int) $id);

        return $doc === null ? Response::html('Not found', 404)
            : Response::html(View::render('components/store_tax_document', ['doc' => $doc, 'backUrl' => '/admin/store/accounts?month=' . rawurlencode((string) $doc['period'])]));
    }

    /** A seller's own copy of eClinicPro's invoice to them. */
    public function vendorInvoice(Request $request, string $id): Response
    {
        $doc = SellerInvoiceService::load((int) $id);
        if ($doc === null || (int) $doc['vendor_id'] !== (int) (RequestContext::vendor()['id'] ?? 0)) {
            return Response::html('Not found', 404);
        }

        return Response::html(View::render('components/store_tax_document', ['doc' => $doc, 'backUrl' => '/vendor/gst']));
    }

    private function invoiceTableExists(): bool
    {
        try {
            \App\Core\Database::connection()->query('SELECT 1 FROM store_seller_invoices LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
