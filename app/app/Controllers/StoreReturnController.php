<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\Store\ReturnService;
use App\Services\Store\VendorService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Returns for sellers (/vendor/returns, own packages only) and admin
 * (/admin/store/returns, everything + override). One controller, scoped by route.
 */
final class StoreReturnController
{
    // ---- Seller ---------------------------------------------------------------

    public function vendorIndex(Request $request): Response
    {
        $tab = (string) ($request->query['tab'] ?? 'open');

        return $this->vendorRender('store_vendor/returns', [
            'rows' => ReturnService::list($this->vendorId(), $tab === 'all' ? '' : 'open'), 'tab' => $tab,
        ]);
    }

    public function vendorShow(Request $request, string $id): Response
    {
        $r = ReturnService::find((int) $id, $this->vendorId());

        return $r === null ? Response::html('Not found', 404) : $this->vendorRender('store_vendor/return_detail', ['r' => $r]);
    }

    public function vendorAction(Request $request, string $id, string $action): Response
    {
        $res = $this->act((int) $id, $action, $request, $this->vendorId(), 'vendor_user', (int) (RequestContext::vendorUser()['id'] ?? 0));
        $this->flash($res);

        return Response::redirect('/vendor/returns/' . (int) $id);
    }

    public function vendorPhoto(Request $request, string $id, string $n): Response
    {
        $r = ReturnService::find((int) $id, $this->vendorId());

        return $r === null ? Response::html('Not found', 404) : ReturnService::photoResponse($r, (int) $n);
    }

    // ---- Admin ----------------------------------------------------------------

    public function adminIndex(Request $request): Response
    {
        $status = (string) ($request->query['status'] ?? 'open');
        try {
            $rows = ReturnService::list(null, $status);
            $missing = false;
        } catch (\Throwable $e) {
            error_log('[StoreReturn::adminIndex] ' . $e->getMessage());
            $rows = [];
            $missing = true;
        }

        return $this->adminRender('admin/store_returns', ['rows' => $rows, 'status' => $status, 'tableMissing' => $missing]);
    }

    public function adminShow(Request $request, string $id): Response
    {
        $r = ReturnService::find((int) $id, null);

        return $r === null ? Response::html('Not found', 404) : $this->adminRender('admin/store_return_detail', ['r' => $r]);
    }

    public function adminAction(Request $request, string $id, string $action): Response
    {
        $res = $action === 'refund'
            ? ReturnService::refund((int) $id, 'admin', (int) (RequestContext::superAdmin()['id'] ?? 0), !empty($request->post['restock']))
            : $this->act((int) $id, $action, $request, null, 'admin', (int) (RequestContext::superAdmin()['id'] ?? 0));
        $this->flash($res);

        return Response::redirect('/admin/store/returns/' . (int) $id);
    }

    public function adminPhoto(Request $request, string $id, string $n): Response
    {
        $r = ReturnService::find((int) $id, null);

        return $r === null ? Response::html('Not found', 404) : ReturnService::photoResponse($r, (int) $n);
    }

    // ---- shared ---------------------------------------------------------------

    /** @return array{ok: bool, error?: string} */
    private function act(int $id, string $action, Request $request, ?int $scopeVendorId, string $actorType, int $actorId): array
    {
        $note = (string) ($request->post['note'] ?? '');

        return match ($action) {
            'approve' => ReturnService::approve($id, $scopeVendorId, 'pickup', $note, $actorType, $actorId),
            'approve-no-pickup' => ReturnService::approve($id, $scopeVendorId, 'no_pickup', $note, $actorType, $actorId),
            'reject' => ReturnService::reject($id, $scopeVendorId, $note, $actorType, $actorId),
            'receive-ok' => ReturnService::receive($id, $scopeVendorId, true, !empty($request->post['restock']), $note, $actorType, $actorId),
            'receive-fail' => ReturnService::receive($id, $scopeVendorId, false, false, $note, $actorType, $actorId),
            'rebook' => ReturnService::bookReversePickup($id),
            default => ['ok' => false, 'error' => 'Unknown action.'],
        };
    }

    /** @param array{ok: bool, error?: string} $res */
    private function flash(array $res): void
    {
        SessionFlash::put($res['ok'] ? 'store_ok' : 'store_err', $res['ok'] ? 'Return updated.' : ($res['error'] ?? 'Something went wrong.'));
    }

    private function vendorId(): int
    {
        return (int) ((RequestContext::vendor() ?? throw new \RuntimeException('Vendor context missing.'))['id']);
    }

    /** @param array<string, mixed> $data */
    private function vendorRender(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'vendor' => VendorService::find($this->vendorId()),
            'vendorUser' => RequestContext::vendorUser(),
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }

    /** @param array<string, mixed> $data */
    private function adminRender(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('store_ok'),
            'flashErr' => SessionFlash::pull('store_err'),
        ]));
    }
}
