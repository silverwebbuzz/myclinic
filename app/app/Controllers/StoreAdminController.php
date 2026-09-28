<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\StoreAudit;
use App\Services\Store\StoreCrypto;
use App\Services\Store\StoreSettings;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /admin/store/* (super-admin only) — marketplace administration.
 * Chunk 1: seller review (profile, addresses, bank, KYC documents), status
 * decisions, password reset, featured flag, and store settings (on/off + preview).
 */
final class StoreAdminController
{
    private const STATUSES = ['draft', 'pending_review', 'approved', 'rejected', 'suspended', 'closed'];

    public function vendors(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'pending_review');
        if ($status !== '' && !in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $q = trim((string) ($request->query['q'] ?? ''));
        try {
            $data = VendorService::adminList($status, $q);
            $tableMissing = false;
        } catch (\Throwable $e) {
            error_log('[StoreAdmin::vendors] ' . $e->getMessage());
            $data = ['rows' => [], 'counts' => []];
            $tableMissing = true;
        }

        return $this->render('admin/store_vendors', [
            'rows' => $data['rows'],
            'counts' => $data['counts'],
            'status' => $status,
            'q' => $q,
            'tableMissing' => $tableMissing,
        ]);
    }

    public function vendorDetail(Request $request, string $id): Response
    {
        $vendor = VendorService::find((int) $id);
        if ($vendor === null) {
            return Response::html('Seller not found', 404);
        }
        $vendorId = (int) $vendor['id'];

        return $this->render('admin/store_vendor_detail', [
            'vendor' => $vendor,
            'owner' => QueryBuilder::table('store_vendor_users')->where('vendor_id', '=', $vendorId)->where('role', '=', 'owner')->first(),
            'addresses' => VendorService::addresses($vendorId, false),
            'bank' => VendorService::primaryBank($vendorId),
            'documents' => VendorService::documents($vendorId),
            'docTypes' => VendorService::DOC_TYPES,
            'checklist' => VendorService::checklist($vendor),
            'audit' => VendorService::adminAuditTrail($vendorId),
            'businessTypes' => VendorService::BUSINESS_TYPES,
            'tempPassword' => SessionFlash::pull('store_temp_password'),
        ]);
    }

    public function vendorStatus(Request $request, string $id): Response
    {
        $result = VendorService::adminSetStatus(
            (int) $id,
            (string) ($request->post['action'] ?? ''),
            (string) ($request->post['reason'] ?? ''),
            $this->adminId(),
        );
        $this->flash($result, 'Seller status updated.');

        return Response::redirect('/admin/store/vendors/' . (int) $id);
    }

    public function resetPassword(Request $request, string $id): Response
    {
        $result = VendorService::adminResetPassword((int) $id);
        if ($result['ok']) {
            // Shown once on the next page load, never stored in plain text.
            SessionFlash::put('store_temp_password', $result['password']);
        }
        $this->flash($result, 'Temporary password generated. Share it with the seller securely.');

        return Response::redirect('/admin/store/vendors/' . (int) $id);
    }

    public function toggleFeatured(Request $request, string $id): Response
    {
        VendorService::adminToggleFeatured((int) $id);

        return Response::redirect('/admin/store/vendors/' . (int) $id);
    }

    public function reviewDocument(Request $request, string $id): Response
    {
        $doc = QueryBuilder::table('store_vendor_documents')->where('id', '=', (int) $id)->first();
        $result = VendorService::adminReviewDocument(
            (int) $id,
            (string) ($request->post['decision'] ?? ''),
            (string) ($request->post['reason'] ?? ''),
            $this->adminId(),
        );
        $this->flash($result, 'Document updated.');

        return Response::redirect('/admin/store/vendors/' . (int) ($doc['vendor_id'] ?? 0));
    }

    public function documentFile(Request $request, string $id): Response
    {
        $doc = QueryBuilder::table('store_vendor_documents')->where('id', '=', (int) $id)->first();
        if ($doc === null) {
            return Response::html('Not found', 404);
        }
        StoreAudit::log('vendor_document.view', 'vendor_document', (int) $doc['id']);

        return VendorService::documentResponse($doc);
    }

    public function verifyBank(Request $request, string $id): Response
    {
        $bank = QueryBuilder::table('store_vendor_bank_accounts')->where('id', '=', (int) $id)->first();
        $result = VendorService::adminVerifyBank((int) $id, (string) ($request->post['decision'] ?? ''));
        $this->flash($result + ['error' => 'Could not update bank status.'], 'Bank account updated.');

        return Response::redirect('/admin/store/vendors/' . (int) ($bank['vendor_id'] ?? 0));
    }

    /** JSON: full account number, only on explicit request (audited). */
    public function revealBank(Request $request, string $id): Response
    {
        $acct = VendorService::adminRevealAccount((int) $id);

        return $acct === null
            ? Response::json(['error' => 'Unavailable (missing record or encryption key).'], 404)
            : Response::json(['account' => $acct]);
    }

    // ---- Settings -------------------------------------------------------------

    public function settings(Request $request): Response
    {
        $previewKey = StoreSettings::get('store_preview_key');

        return $this->render('admin/store_settings', [
            'enabled' => StoreSettings::enabled(),
            'previewUrl' => $previewKey !== '' ? 'https://eclinicpro.com/store/?preview=' . $previewKey : null,
            'cryptoReady' => StoreCrypto::isConfigured(),
            'settings' => [
                'store_require_product_approval' => StoreSettings::get('store_require_product_approval', '1'),
                'store_reviews_auto_publish' => StoreSettings::get('store_reviews_auto_publish', '0'),
                'store_default_return_window_days' => StoreSettings::get('store_default_return_window_days', '7'),
                'store_payment_window_minutes' => StoreSettings::get('store_payment_window_minutes', '30'),
                'store_default_commission_bp' => StoreSettings::get('store_default_commission_bp', '1000'),
            ],
            'shipping' => $this->defaultShippingRule(),
            'shiprocket' => [
                'enabled' => StoreSettings::get('store_shiprocket_enabled', '0') === '1',
                'email' => StoreSettings::get('store_shiprocket_email'),
                'has_password' => StoreSettings::get('store_shiprocket_password') !== '',
                'webhook_key' => StoreSettings::get('store_shiprocket_webhook_key'),
                'webhook_url' => rtrim((string) ($_ENV['APP_URL'] ?? 'https://app.eclinicpro.com'), '/') . '/webhooks/store-tracking',
            ],
            'invoicing' => [
                'legal_name' => StoreSettings::get('store_platform_legal_name'),
                'gstin' => StoreSettings::get('store_platform_gstin'),
                'address' => StoreSettings::get('store_platform_address'),
                'sac' => StoreSettings::get('store_delivery_sac', '996812'),
                'gst_bp' => StoreSettings::int('store_delivery_gst_bp', 1800),
                'require_gstin' => StoreSettings::get('store_require_gstin', '1') === '1',
                'ready' => \App\Services\Store\TaxDocumentService::platformReady(),
            ],
        ]);
    }

    /** Platform default shipping rule (vendor_id NULL), or null if the orders patch isn't imported yet. */
    private function defaultShippingRule(): ?array
    {
        try {
            return QueryBuilder::table('store_shipping_rules')->where('vendor_id', 'IS')->where('is_active', '=', 1)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    public function saveSettings(Request $request): Response
    {
        $action = (string) ($request->post['action'] ?? '');
        try {
            if ($action === 'toggle_enabled') {
                $on = !StoreSettings::enabled();
                StoreSettings::set('store_enabled', $on ? '1' : '0');
                StoreAudit::log('store.' . ($on ? 'enable' : 'disable'), 'setting', null);
                SessionFlash::put('store_ok', $on ? 'Store is now LIVE for customers.' : 'Store is now hidden from customers.');
            } elseif ($action === 'shiprocket_save') {
                $email = trim((string) ($request->post['sr_email'] ?? ''));
                $password = (string) ($request->post['sr_password'] ?? '');
                if ($password !== '' && !StoreCrypto::isConfigured()) {
                    throw new \RuntimeException('STORE_DATA_KEY missing: cannot store the Shiprocket password securely.');
                }
                \App\Services\Store\ShiprocketClient::saveCredentials($email, $password);
                StoreSettings::set('store_shiprocket_enabled', !empty($request->post['sr_enabled']) ? '1' : '0');
                StoreAudit::log('store.shiprocket_save', 'setting', null, null, ['email' => $email, 'password_changed' => $password !== '']);
                SessionFlash::put('store_ok', 'Shiprocket settings saved. Use "Test connection" to check the login.');
            } elseif ($action === 'shiprocket_test') {
                $res = \App\Services\Store\ShiprocketClient::testLogin();
                SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Connected to Shiprocket ✓' : 'Shiprocket login failed: ' . ($res['error'] ?? ''));
            } elseif ($action === 'shiprocket_webhook_key') {
                StoreSettings::set('store_shiprocket_webhook_key', bin2hex(random_bytes(20)), true);
                StoreAudit::log('store.shiprocket_webhook_key_rotate', 'setting', null);
                SessionFlash::put('store_ok', 'New webhook token generated. Paste it into Shiprocket (the old one stops working).');
            } elseif ($action === 'invoicing_save') {
                $gstin = strtoupper(preg_replace('/\s+/', '', (string) ($request->post['platform_gstin'] ?? '')) ?? '');
                if ($gstin !== '' && !\App\Services\Store\GstStates::validGstin($gstin)) {
                    throw new \InvalidArgumentException('That GSTIN doesn\'t look right (15 characters, starting with the state code).');
                }
                $sac = preg_replace('/\D/', '', (string) ($request->post['delivery_sac'] ?? '')) ?? '';
                $bp = (int) ($request->post['delivery_gst_bp'] ?? 1800);
                if (!array_key_exists($bp, \App\Services\Store\CatalogService::GST_RATES_BP)) {
                    throw new \InvalidArgumentException('Choose a valid GST rate for delivery charges.');
                }
                StoreSettings::set('store_platform_legal_name', mb_substr(trim((string) ($request->post['platform_legal_name'] ?? '')), 0, 190));
                StoreSettings::set('store_platform_gstin', $gstin);
                StoreSettings::set('store_platform_address', mb_substr(trim((string) ($request->post['platform_address'] ?? '')), 0, 400));
                StoreSettings::set('store_delivery_sac', $sac !== '' ? mb_substr($sac, 0, 8) : '996812');
                StoreSettings::set('store_delivery_gst_bp', (string) $bp);
                StoreSettings::set('store_require_gstin', !empty($request->post['require_gstin']) ? '1' : '0');
                StoreAudit::log('store.invoicing_save', 'setting', null, null, ['gstin' => $gstin, 'sac' => $sac, 'gst_bp' => $bp]);
                SessionFlash::put('store_ok', 'Invoicing details saved.');
            } elseif ($action === 'new_preview_key') {
                StoreSettings::set('store_preview_key', bin2hex(random_bytes(16)), true);
                StoreAudit::log('store.preview_key_rotate', 'setting', null);
                SessionFlash::put('store_ok', 'New preview link generated. Old preview links stop working.');
            } elseif ($action === 'save') {
                StoreSettings::set('store_require_product_approval', !empty($request->post['store_require_product_approval']) ? '1' : '0');
                StoreSettings::set('store_reviews_auto_publish', !empty($request->post['store_reviews_auto_publish']) ? '1' : '0');
                StoreSettings::set('store_default_return_window_days', (string) max(0, min(30, (int) ($request->post['store_default_return_window_days'] ?? 7))));
                StoreSettings::set('store_payment_window_minutes', (string) max(10, min(120, (int) ($request->post['store_payment_window_minutes'] ?? 30))));
                $commissionPct = (float) ($request->post['store_default_commission_pct'] ?? 10);
                $commissionBp = (int) round(max(0, min(50, $commissionPct)) * 100);
                StoreSettings::set('store_default_commission_bp', (string) $commissionBp);
                // Keep the 'default' commission rule row in step with the setting.
                \App\Core\Database::connection()->prepare(
                    "UPDATE store_commission_rules SET rate_bp = :bp, type = 'percent' WHERE scope = 'default'"
                )->execute(['bp' => $commissionBp]);
                $flat = \App\Services\Store\ProductService::toPaise((string) ($request->post['ship_flat'] ?? ''));
                $freeRaw = trim((string) ($request->post['ship_free_above'] ?? ''));
                $free = $freeRaw === '' ? null : \App\Services\Store\ProductService::toPaise($freeRaw);
                if ($flat !== null) {
                    $rule = $this->defaultShippingRule();
                    $row = ['flat_fee_paise' => $flat, 'free_above_paise' => $free];
                    $rule !== null
                        ? QueryBuilder::table('store_shipping_rules')->where('id', '=', (int) $rule['id'])->update($row)
                        : QueryBuilder::table('store_shipping_rules')->insert($row + ['vendor_id' => null, 'is_active' => 1]);
                }
                StoreAudit::log('store.settings_save', 'setting', null);
                SessionFlash::put('store_ok', 'Settings saved.');
            }
        } catch (\InvalidArgumentException $e) {
            SessionFlash::put('store_err', $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[StoreAdmin::saveSettings] ' . $e->getMessage());
            SessionFlash::put('store_err', 'Could not save. Have the store database patches been run?');
        }

        return Response::redirect('/admin/store/settings');
    }

    // ---- Helpers --------------------------------------------------------------

    private function adminId(): int
    {
        return (int) (RequestContext::superAdmin()['id'] ?? 0);
    }

    /** @param array{ok: bool, error?: string} $result */
    private function flash(array $result, string $success): void
    {
        SessionFlash::put($result['ok'] ? 'store_ok' : 'store_err', $result['ok'] ? $success : ($result['error'] ?? 'Something went wrong.'));
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
