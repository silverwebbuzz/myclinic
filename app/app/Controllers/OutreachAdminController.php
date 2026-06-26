<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\OutreachService;
use App\Support\View;

/**
 * /admin/outreach — doctor-acquisition worklist.
 *
 * Segments non-joined directory doctors by lead volume this month so the sales
 * team can reach out and convert them. Paginated + filterable (scales to
 * thousands), with per-clinic status tracking + CSV export. Bulk SEND is a
 * later phase; this controller handles browse / filter / export / mark-status.
 */
final class OutreachAdminController
{
    /** GET /admin/outreach */
    public function index(Request $request): Response
    {
        $filters = self::filtersFromRequest($request);
        $page = max(1, (int) ($request->query['page'] ?? 1));

        $total = OutreachService::count($filters);
        $rows = OutreachService::segment($filters, $page);
        $options = OutreachService::filterOptions();

        $perPage = OutreachService::PER_PAGE;
        $pages = (int) max(1, ceil($total / $perPage));

        return Response::html(View::render('admin/outreach', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'rows' => $rows,
            'filters' => $filters,
            'options' => $options,
            'statuses' => OutreachService::STATUSES,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'perPage' => $perPage,
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/outreach/status — upsert one clinic's status/notes. */
    public function saveStatus(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/outreach');
        }
        $admin = RequestContext::superAdmin();
        OutreachService::setStatus(
            (int) ($request->post['directory_doctor_id'] ?? 0),
            (string) ($request->post['status'] ?? ''),
            isset($request->post['notes']) ? (string) $request->post['notes'] : null,
            isset($admin['id']) ? (int) $admin['id'] : null,
        );

        // Preserve the current filter/page when bouncing back.
        $qs = $request->post['return_qs'] ?? '';
        $qs = is_string($qs) && $qs !== '' ? '?' . ltrim($qs, '?') . '&message=status_saved' : '?message=status_saved';
        return Response::redirect('/admin/outreach' . $qs);
    }

    /** GET /admin/outreach/export.csv — current filtered segment as CSV. */
    public function exportCsv(Request $request): Response
    {
        $filters = self::filtersFromRequest($request);
        $rows = OutreachService::exportRows($filters);

        $cols = [
            'id' => 'Clinic ID',
            'name' => 'Clinic',
            'doctor_name' => 'Doctor',
            'phone' => 'Phone',
            'intl_phone' => 'Intl Phone',
            'city' => 'City',
            'state' => 'State',
            'area' => 'Area',
            'specialty' => 'Specialty',
            'leads_this_month' => 'Leads (this month)',
            'leads_total' => 'Leads (total)',
            'last_lead_at' => 'Last lead at',
            'status' => 'Outreach status',
            'contacted_count' => 'Times contacted',
            'last_contacted_at' => 'Last contacted at',
        ];

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, array_values($cols));
        foreach ($rows as $r) {
            $line = [];
            foreach (array_keys($cols) as $k) {
                $line[] = (string) ($r[$k] ?? '');
            }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        $filename = 'outreach_' . date('Y-m-d') . '.csv';
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** @return array<string,mixed> */
    private static function filtersFromRequest(Request $request): array
    {
        $q = $request->query;
        return [
            'min_leads' => isset($q['min_leads']) && $q['min_leads'] !== '' ? (int) $q['min_leads'] : 0,
            'city' => trim((string) ($q['city'] ?? '')),
            'specialty' => trim((string) ($q['specialty'] ?? '')),
            'status' => trim((string) ($q['status'] ?? '')),
            'q' => trim((string) ($q['q'] ?? '')),
        ];
    }
}
