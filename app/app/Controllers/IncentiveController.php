<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\QueryBuilder;
use App\Core\RequestContext;
use App\Gates\ModuleGate;
use App\Http\Request;
use App\Http\Response;
use App\Services\DoctorIncentiveService;
use App\Services\IncentivePayslipService;
use App\Support\Layout;

final class IncentiveController
{
    public function index(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $period = $request->query['period'] ?? date('Y-m', strtotime('first day of last month'));

        return Response::html(Layout::page('billing/incentives', [
            'doctors' => DoctorIncentiveService::doctorsWithConfig($clinicId),
            'incentives' => DoctorIncentiveService::listForPeriod($clinicId, $period),
            'period' => $period,
            'message' => $request->query['message'] ?? null,
        ], 'Doctor incentives'));
    }

    public function saveConfig(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $doctorIds = $request->post['doctor_id'] ?? [];
        $percents = $request->post['incentive_percent'] ?? [];
        $flats = $request->post['incentive_flat_fee'] ?? [];
        if (is_array($doctorIds)) {
            foreach ($doctorIds as $i => $docId) {
                DoctorIncentiveService::saveDoctorConfig(
                    $clinicId,
                    (int) $docId,
                    (float) ($percents[$i] ?? 0),
                    (float) ($flats[$i] ?? 0),
                );
            }
        }

        return Response::redirect('/billing/incentives?message=config_saved');
    }

    public function calculate(Request $request): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $period = $request->post['period'] ?? date('Y-m', strtotime('first day of last month'));
        DoctorIncentiveService::calculateMonth((int) RequestContext::clinicId(), $period);

        return Response::redirect('/billing/incentives?period=' . urlencode($period) . '&message=calculated');
    }

    public function payslip(Request $request, string $id): Response
    {
        if ($denied = $this->requireModule()) {
            return $denied;
        }

        $clinicId = (int) RequestContext::clinicId();
        $incentive = DoctorIncentiveService::find($clinicId, (int) $id);
        if ($incentive === null) {
            return Response::html('Not found', 404);
        }

        $clinic = QueryBuilder::table('tenants')->where('id', '=', $clinicId)->first() ?? [];
        $path = IncentivePayslipService::generate($incentive, $clinic);

        return Response::redirect($path);
    }

    private function requireModule(): ?Response
    {
        if (!ModuleGate::check('incentives')) {
            return Response::html(Layout::page('errors/module', ['module' => 'incentives', 'label' => 'Incentives'], 'Module inactive'), 402);
        }

        return null;
    }
}
