<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\StoreCrypto;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Store seller portal (/vendor/*) — onboarding for chunk 1: dashboard checklist,
 * business profile, addresses, bank account, KYC documents, submit for review.
 *
 * The vendor id ALWAYS comes from RequestContext (set by VendorAuthMiddleware),
 * never from the URL or form — a seller can only ever touch their own rows.
 */
final class VendorPortalController
{
    public function dashboard(Request $request): Response
    {
        $vendor = $this->vendor();

        return $this->render('store_vendor/dashboard', [
            'checklist' => VendorService::checklist($vendor),
            'welcome' => !empty($request->query['welcome']),
        ]);
    }

    public function submit(Request $request): Response
    {
        $result = VendorService::submitForReview($this->vendor());
        $this->flash($result, 'Submitted! Our team will review your account, usually within 2 working days.');

        return Response::redirect('/vendor/dashboard');
    }

    // ---- Profile ----------------------------------------------------------

    public function profile(Request $request): Response
    {
        $vendor = $this->vendor();

        return $this->render('store_vendor/profile', [
            'locked' => VendorService::legalFieldsLocked($vendor),
            'cryptoReady' => StoreCrypto::isConfigured(),
            'businessTypes' => VendorService::BUSINESS_TYPES,
        ]);
    }

    public function saveProfile(Request $request): Response
    {
        $logo = $_FILES['logo'] ?? null;
        $result = VendorService::updateProfile($this->vendor(), $request->post, is_array($logo) ? $logo : null);
        $this->flash($result, 'Profile saved.');

        return Response::redirect('/vendor/profile');
    }

    // ---- Addresses --------------------------------------------------------

    public function addresses(Request $request): Response
    {
        return $this->render('store_vendor/addresses', [
            'addresses' => VendorService::addresses((int) $this->vendor()['id']),
        ]);
    }

    public function addAddress(Request $request): Response
    {
        $this->flash(VendorService::addAddress((int) $this->vendor()['id'], $request->post), 'Address saved.');

        return Response::redirect('/vendor/addresses');
    }

    public function removeAddress(Request $request, string $id): Response
    {
        $ok = VendorService::deactivateAddress((int) $this->vendor()['id'], (int) $id);
        $this->flash(['ok' => $ok, 'error' => 'Address not found.'], 'Address removed.');

        return Response::redirect('/vendor/addresses');
    }

    // ---- Bank ---------------------------------------------------------------

    public function bank(Request $request): Response
    {
        return $this->render('store_vendor/bank', [
            'bank' => VendorService::primaryBank((int) $this->vendor()['id']),
            'cryptoReady' => StoreCrypto::isConfigured(),
        ]);
    }

    public function saveBank(Request $request): Response
    {
        $this->flash(VendorService::saveBank((int) $this->vendor()['id'], $request->post),
            'Bank details saved. We\'ll verify them before your first payout.');

        return Response::redirect('/vendor/bank');
    }

    // ---- Documents ----------------------------------------------------------

    public function documents(Request $request): Response
    {
        return $this->render('store_vendor/documents', [
            'documents' => VendorService::documents((int) $this->vendor()['id']),
            'docTypes' => VendorService::DOC_TYPES,
        ]);
    }

    public function uploadDocument(Request $request): Response
    {
        $file = $_FILES['document'] ?? [];
        $result = VendorService::storeDocument(
            (int) $this->vendor()['id'],
            (string) ($request->post['doc_type'] ?? ''),
            is_array($file) ? $file : [],
            (string) ($request->post['doc_number'] ?? ''),
            (string) ($request->post['valid_until'] ?? ''),
        );
        $this->flash($result, 'Document uploaded.');

        return Response::redirect('/vendor/documents');
    }

    public function documentFile(Request $request, string $id): Response
    {
        // Scoped by vendor_id: another seller's document id simply isn't found.
        $doc = QueryBuilder::table('store_vendor_documents')
            ->where('id', '=', (int) $id)
            ->where('vendor_id', '=', (int) $this->vendor()['id'])
            ->first();

        return $doc === null ? Response::html('Not found', 404) : VendorService::documentResponse($doc);
    }

    // ---- Helpers ------------------------------------------------------------

    /** @return array<string, mixed> */
    private function vendor(): array
    {
        return RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.');
    }

    /** @param array{ok: bool, error?: string} $result */
    private function flash(array $result, string $success): void
    {
        SessionFlash::put($result['ok'] ? 'store_ok' : 'store_err', $result['ok'] ? $success : ($result['error'] ?? 'Something went wrong.'));
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data = []): Response
    {
        // Re-read so pages reflect a save made in this same request cycle.
        $vendor = VendorService::find((int) $this->vendor()['id']) ?? $this->vendor();

        return Response::html(View::render($view, $data + [
            'vendor' => $vendor,
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
