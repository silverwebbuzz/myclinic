<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\TaxDocumentService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * GST documents: monthly register (admin: all sellers + eClinicPro's delivery
 * invoices; seller: only their own), CSV export, and the printable documents.
 */
final class StoreTaxController
{
    // ---- Admin ------------------------------------------------------------------

    public function adminRegister(Request $request): Response
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));
        $vendorId = (int) ($request->query['vendor'] ?? 0) ?: null;
        $issuer = ($request->query['issuer'] ?? '') === 'platform' ? 'platform' : 'vendor';
        try {
            $reg = TaxDocumentService::register($month, $vendorId, $issuer);
            $missing = false;
        } catch (\Throwable $e) {
            error_log('[StoreTax::adminRegister] ' . $e->getMessage());
            $reg = ['docs' => [], 'hsn' => [], 'totals' => [], 'month' => $month];
            $missing = true;
        }
        $noHsn = 0;
        try {
            $noHsn = (int) \App\Core\Database::connection()
                ->query("SELECT COUNT(*) FROM store_products WHERE status IN ('live','pending_review') AND (hsn_code IS NULL OR hsn_code = '')")->fetchColumn();
        } catch (\Throwable) {
        }

        return Response::html(View::render('admin/store_gst', $reg + [
            'vendorId' => $vendorId, 'issuer' => $issuer, 'tableMissing' => $missing, 'noHsn' => $noHsn,
            'platformReady' => TaxDocumentService::platformReady(),
            'vendors' => QueryBuilder::table('store_vendors')->orderBy('display_name')->get(),
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function adminCsv(Request $request): Response
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));
        $vendorId = (int) ($request->query['vendor'] ?? 0) ?: null;
        $issuer = ($request->query['issuer'] ?? '') === 'platform' ? 'platform' : 'vendor';

        return StorePayoutAdminController::csv(TaxDocumentService::registerCsv($month, $vendorId, $issuer),
            'gst-' . $issuer . ($vendorId ? '-seller' . $vendorId : '') . '-' . $month . '.csv');
    }

    public function adminDocument(Request $request, string $id): Response
    {
        $doc = TaxDocumentService::load((int) $id);
        if ($doc === null) {
            return Response::html('Not found', 404);
        }

        return Response::html(View::render('components/store_tax_document', ['doc' => $doc, 'backUrl' => '/admin/store/orders/' . (int) $doc['order_id']]));
    }

    // ---- Seller -----------------------------------------------------------------

    public function vendorRegister(Request $request): Response
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));
        try {
            $reg = TaxDocumentService::register($month, $this->vendorId(), 'vendor');
        } catch (\Throwable $e) {
            error_log('[StoreTax::vendorRegister] ' . $e->getMessage());
            $reg = ['docs' => [], 'hsn' => [], 'totals' => [], 'month' => $month];
        }

        return Response::html(View::render('store_vendor/gst', $reg + [
            'ecpInvoices' => \App\Services\Store\SellerInvoiceService::listing(null, $this->vendorId(), 24),
            'vendor' => VendorService::find($this->vendorId()) ?? RequestContext::vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function vendorCsv(Request $request): Response
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));

        return StorePayoutAdminController::csv(TaxDocumentService::registerCsv($month, $this->vendorId(), 'vendor'), 'gst-register-' . $month . '.csv');
    }

    /** A seller sees only their OWN documents (not eClinicPro's delivery invoices). */
    public function vendorDocument(Request $request, string $id): Response
    {
        $doc = TaxDocumentService::load((int) $id);
        if ($doc === null || $doc['issuer'] !== 'vendor' || (int) $doc['vendor_id'] !== $this->vendorId()) {
            return Response::html('Not found', 404);
        }

        return Response::html(View::render('components/store_tax_document', ['doc' => $doc, 'backUrl' => '/vendor/orders/' . (int) $doc['vendor_order_id']]));
    }

    /** "Print invoice" before pickup: issues it now (packed or later). */
    public function vendorIssueInvoice(Request $request, string $voId): Response
    {
        $vo = QueryBuilder::table('store_vendor_orders')->where('id', '=', (int) $voId)->first();
        if ($vo === null || (int) $vo['vendor_id'] !== $this->vendorId()) {
            return Response::html('Not found', 404);
        }
        try {
            $id = TaxDocumentService::issueInvoice((int) $voId);
        } catch (\Throwable $e) {
            error_log('[StoreTax::vendorIssueInvoice] ' . $e->getMessage());
            $id = null;
        }
        if ($id === null) {
            SessionFlash::put('store_err', 'The invoice is available once the package is packed.');

            return Response::redirect('/vendor/orders/' . (int) $voId);
        }

        return Response::redirect('/vendor/gst/documents/' . $id);
    }

    private function vendorId(): int
    {
        return (int) (RequestContext::vendor()['id'] ?? 0);
    }
}
