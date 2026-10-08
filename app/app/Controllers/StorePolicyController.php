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
            $tokens = StorePolicyService::tokens();
            $preview = StorePolicyService::render($page['body']);
        } catch (\Throwable $e) {
            error_log('[StorePolicy::adminEdit] ' . $e->getMessage());

            return Response::html('<p style="font:15px system-ui;padding:24px">The seller terms tables are missing. Import <code>app/database/patches/2026_10_01_store_tax_documents.sql</code> in phpMyAdmin, then reload.'
                . '<br><small style="color:#64748b">Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>', 500);
        }

        return Response::html(View::render('admin/store_policy', [
            'slug' => $slug, 'page' => $page, 'stats' => $stats, 'versions' => $versions,
            'tokens' => $tokens, 'preview' => $preview, 'defaultBody' => StorePolicyService::defaultBody($slug),
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    public function adminSave(Request $request, string $slug): Response
    {
        $res = StorePolicyService::save($slug, (string) ($request->post['title'] ?? ''), (string) ($request->post['body'] ?? ''),
            (int) (RequestContext::superAdmin()['id'] ?? 0), !empty($request->post['urgent']));
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok']
            ? 'Saved as version ' . $res['version'] . ', taking effect ' . \App\Support\IndianDate::dateTime($res['effective_at']) . '. Sellers have been emailed to accept it.'
            : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/admin/store/policies/' . rawurlencode($slug));
    }

    public function vendorShow(Request $request): Response
    {
        $vendorId = (int) (RequestContext::vendor()['id'] ?? 0);
        try {
            $page = StorePolicyService::get('seller_terms');
            $html = StorePolicyService::render($page['body']);
            $accepted = StorePolicyService::acceptedVersion($vendorId, 'seller_terms');
        } catch (\Throwable $e) {
            error_log('[StorePolicy::vendorShow] ' . $e->getMessage());
            // Tables not imported yet: still show the default rules (read-only, nothing to accept).
            $page = ['title' => StorePolicyService::PAGES['seller_terms'], 'body' => '', 'version' => 0, 'updated_at' => null];
            try {
                $html = StorePolicyService::render(StorePolicyService::defaultBody('seller_terms'));
            } catch (\Throwable) {
                $html = '<p>The seller rules are temporarily unavailable. Please try again shortly.</p>';
            }
            $accepted = 0;
        }

        return Response::html(View::render('store_vendor/terms', [
            'page' => $page, 'html' => $html,
            'acceptedVersion' => $accepted,
            'vendor' => VendorService::find($vendorId) ?? RequestContext::vendor(),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(), 'flashOk' => SessionFlash::pull('store_ok'), 'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    /** Seller's printable copy of the terms they accepted (browser "Save as PDF"). */
    public function vendorCertificate(Request $request): Response
    {
        return $this->certificate((int) (RequestContext::vendor()['id'] ?? 0), '/vendor/terms');
    }

    /** Same certificate for admin, from the seller's page. */
    public function adminCertificate(Request $request, string $id): Response
    {
        return $this->certificate((int) $id, '/admin/store/vendors/' . (int) $id);
    }

    private function certificate(int $vendorId, string $backUrl): Response
    {
        try {
            $cert = StorePolicyService::certificate($vendorId);
        } catch (\Throwable $e) {
            error_log('[StorePolicy::certificate] ' . $e->getMessage());
            $cert = null;
        }
        $vendor = VendorService::find($vendorId);
        if ($cert === null || $vendor === null) {
            return Response::html('<p style="font:15px system-ui;padding:24px">No accepted seller terms on record yet. <a href="' . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') . '">Back</a></p>', 404);
        }
        $pickup = array_values(array_filter(VendorService::addresses($vendorId), static fn ($a) => $a['type'] === 'pickup'))[0] ?? null;

        return Response::html(View::render('store_vendor/terms_certificate', [
            'cert' => $cert, 'vendor' => $vendor, 'pickup' => $pickup, 'backUrl' => $backUrl,
            'platform' => [
                'name' => \App\Services\Store\StoreSettings::get('store_platform_legal_name') ?: 'eClinicPro',
                'gstin' => \App\Services\Store\StoreSettings::get('store_platform_gstin'),
                'address' => \App\Services\Store\StoreSettings::get('store_platform_address'),
            ],
        ]));
    }

    public function vendorAccept(Request $request): Response
    {
        if (empty($request->post['agree'])) {
            SessionFlash::put('store_err', 'Tick the box to confirm you have read the rules.');

            return Response::redirect('/vendor/terms');
        }
        try {
            $res = StorePolicyService::accept((int) (RequestContext::vendor()['id'] ?? 0), (int) (RequestContext::vendorUser()['id'] ?? 0),
                'seller_terms', (int) ($request->post['version'] ?? 0), $request->ip());
        } catch (\Throwable $e) {
            error_log('[StorePolicy::vendorAccept] ' . $e->getMessage());
            $res = ['ok' => false, 'error' => 'Could not save your acceptance right now. Please try again shortly.'];
        }
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Thank you, you have accepted the seller rules & terms.' : ($res['error'] ?? 'Could not save.'));

        return Response::redirect('/vendor/terms');
    }
}
