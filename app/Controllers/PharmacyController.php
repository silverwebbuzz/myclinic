<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\DrugService;
use App\Services\PharmacyInventoryService;
use App\Services\PharmacyPosService;
use App\Support\Layout;

final class PharmacyController
{
    public function pos(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        return Response::html(Layout::page('pharmacy/pos', [
            'stock' => PharmacyInventoryService::list($clinicId),
        ], 'Pharmacy POS'));
    }

    public function inventory(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();

        return Response::html(Layout::page('pharmacy/inventory', [
            'batches' => PharmacyInventoryService::list($clinicId),
            'lowStock' => PharmacyInventoryService::lowStock($clinicId),
            'expiring' => PharmacyInventoryService::expiringSoon($clinicId),
            'drugs' => DrugService::search('', 30),
        ], 'Pharmacy inventory'));
    }

    public function narcotic(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        return Response::html(Layout::page('pharmacy/narcotic', [
            'entries' => PharmacyPosService::narcoticRegister((int) RequestContext::clinicId()),
        ], 'Narcotic register'));
    }

    public function addBatch(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        PharmacyInventoryService::addBatch((int) RequestContext::clinicId(), $request->post);

        return Response::redirect('/pharmacy/inventory?added=1');
    }

    public function checkout(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $cart = [];
        $drugIds = $request->post['drug_id'] ?? [];
        $qtys = $request->post['qty'] ?? [];
        if (is_array($drugIds)) {
            foreach ($drugIds as $i => $drugId) {
                if ((int) $drugId < 1) {
                    continue;
                }
                $cart[] = ['drug_id' => (int) $drugId, 'qty' => (int) ($qtys[$i] ?? 1)];
            }
        }

        $sale = PharmacyPosService::checkout(
            (int) RequestContext::clinicId(),
            $cart,
            $request->post['payment_mode'] ?? 'cash',
            !empty($request->post['patient_id']) ? (int) $request->post['patient_id'] : null,
        );
        AuditService::log($request, 'INSERT', 'pharmacy_sales', (int) ($sale['id'] ?? 0));

        return Response::redirect('/pharmacy/pos?sale=1&total=' . urlencode((string) ($sale['total'] ?? 0)));
    }

    public function searchApi(Request $request): Response
    {
        if ($denied = ModuleGate::require('pharmacy')) {
            return $denied;
        }

        return Response::json([
            'items' => PharmacyPosService::searchStock((int) RequestContext::clinicId(), $request->query['q'] ?? ''),
        ]);
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('pharmacy')) {
            return Response::html(Layout::page('errors/module', ['module' => 'pharmacy', 'label' => 'Pharmacy'], 'Module inactive'), 402);
        }

        return null;
    }
}
