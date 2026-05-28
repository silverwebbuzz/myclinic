<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Support\Layout;
use App\Support\VisitView;
use App\Http\Request;
use App\Http\Response;

/**
 * HelpController — Phase 4 in-portal user guide.
 *
 * Renders role-aware + module-aware help. A homeopath at reception sees a
 * different page than a diabetologist doctor — same file, conditional
 * sections driven by visible_modules + role.
 */
final class HelpController
{
    public function index(Request $request): Response
    {
        $clinicId = (int) RequestContext::clinicId();
        $user = RequestContext::user() ?? [];
        $clinic = RequestContext::clinic() ?? [];
        $role = (string) ($user['role'] ?? 'receptionist');

        $visibleModules = VisitView::visibleModules($clinicId, (string) ($clinic['specialty'] ?? ''));

        // Reception gets the operational subset (no clinical sections).
        $isClinical = in_array($role, ['doctor', 'asst_doctor', 'admin', 'superadmin'], true);

        return Response::html(Layout::page('help/index', [
            'role' => $role,
            'isClinical' => $isClinical,
            'visibleModules' => $visibleModules,
            'clinic' => $clinic,
        ], 'Help & Guide'));
    }
}
