<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\StorePolicyService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/** Seller rules & terms: admin edits (/admin/store/policies/*), sellers read + accept (/vendor/terms). */
final class StorePolicyController
{
    public function adminEdit(Request $request, string $slug): Response
    {
        if (!isset(StorePolicyService::PAGES[$slug])) {
            return Response::html('Not found', 404);
        }
        try {
            $page = StorePolicyService::get($slug);
            $stats = StorePolicyService::stats($slug);
            $versions = StorePolicyService::versions($slug);
        } catch (\Throwable $e) {
            error_log('[StorePolicy::adminEdit] ' . $e->getMessage());

            return Response::html('Import 2026_10_01_store_tax_documents.sql first.', 500);
        }

        return Response::html(View::render('admin/store_policy', [
            'slug' => $slug, 'page' => $page, 'stats' => $stats, 'versions' => $versions,
            'tokens' => StorePolicyService::tokens(), 'preview' => StorePolicyService::render($page['body']),
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function adminSave(Request $request, string $slug): Response
    {
        $res = StorePolicyService::save($slug, (string) ($request->post['title'] ?? ''), (string) ($request->post['body'] ?? ''),
            (int) (RequestContext::superAdmin()['id'] ?? 0));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok']
            ? 'Saved as version ' . $res['version'] . '. Sellers will be asked to accept it.'
            : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/admin/store/policies/' . rawurlencode($slug));
    }

    public function vendorShow(Request $request): Response
    {
        $vendorId = (int) (RequestContext::vendor()['id'] ?? 0);
        $page = StorePolicyService::get('seller_terms');

        return Response::html(View::render('store_vendor/terms', [
            'page' => $page, 'html' => StorePolicyService::render($page['body']),
            'acceptedVersion' => StorePolicyService::acceptedVersion($vendorId, 'seller_terms'),
            'vendor' => VendorService::find($vendorId) ?? RequestContext::vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function vendorAccept(Request $request): Response
    {
        if (empty($request->post['agree'])) {
            SessionFlash::put('store_err', 'Tick the box to confirm you have read the rules.');

            return Response::redirect('/vendor/terms');
        }
        $res = StorePolicyService::accept((int) (RequestContext::vendor()['id'] ?? 0), (int) (RequestContext::vendorUser()['id'] ?? 0),
            'seller_terms', (int) ($request->post['version'] ?? 0), $request->ip());
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Thank you, you have accepted the seller rules & terms.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/vendor/terms');
    }
}
