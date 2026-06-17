<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\AuditService;
use App\Services\BillingGatewayService;
use App\Services\CsrfService;
use App\Services\PlanService;

/**
 * Subscription checkout — turns a plan choice into a real gateway payment.
 * This is the entry point that was previously missing: it calls
 * BillingGatewayService::startCheckout() and redirects the doctor to Cashfree
 * (or the configured fallback). Reachable from both onboarding and Settings.
 */
final class SubscriptionController
{
    public function checkout(Request $request): Response
    {
        $clinicId = RequestContext::clinicId();
        if ($clinicId === null) {
            return Response::redirect('/login');
        }
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/settings?tab=subscription&error=' . urlencode('Security token expired, please try again.'));
        }

        $planId = (string) ($request->post['plan'] ?? 'standard');
        $cycle = ($request->post['billing_cycle'] ?? 'yearly') === 'monthly' ? 'monthly' : 'yearly';

        // 'free' = no payment; just continue (used by the onboarding screen).
        if ($planId === 'free') {
            PlanService::applyPlanToTenant($clinicId, 'free', false);

            return Response::redirect('/onboarding/clinic-setup');
        }

        if (PlanService::get($planId) === null) {
            return Response::redirect('/settings?tab=subscription&error=' . urlencode('Unknown plan.'));
        }

        $clinic = RequestContext::clinic() ?? [];
        $country = (string) ($clinic['country_code'] ?? 'IN');

        $result = BillingGatewayService::startCheckout($clinicId, $planId, $cycle, $country);
        AuditService::log($request, 'INSERT', 'saas_invoices', $clinicId);

        if (($result['type'] ?? '') === 'redirect' && !empty($result['url'])) {
            return Response::redirect($result['url']);
        }

        // Gateway returned an error (no plan, etc.). Send back with a message.
        $msg = $result['message'] ?? 'Could not start checkout. Please try again.';

        return Response::redirect('/settings?tab=subscription&error=' . urlencode($msg));
    }
}
