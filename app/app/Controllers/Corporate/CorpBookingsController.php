<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\BookingService;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\EmployeeService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/** /company/bookings — book employees' due checks at network clinics (design §4, C4–C6). */
final class CorpBookingsController
{
    private const PERM = 'bookings.manage';

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $view = in_array($request->query['view'] ?? '', ['past', 'cancelled'], true) ? (string) $request->query['view'] : 'upcoming';
        $q = trim((string) ($request->query['q'] ?? ''));

        return $this->render('corporate/bookings', [
            'view' => $view,
            'q' => $q,
            'result' => BookingService::search($this->cid(), ['view' => $view, 'q' => $q], $this->scope(), (int) ($request->query['page'] ?? 1)),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = $this->cid();
        $emp = EmployeeService::find($cid, (int) ($request->query['employee'] ?? 0), $this->scope());
        if ($emp === null) {
            return Response::html('Not found', 404);
        }
        $replace = null;
        $cases = BookingService::openCases($cid, (int) $emp['id']);
        $preselect = array_map('intval', array_column($cases, 'id'));
        if (($rid = (int) ($request->query['replace'] ?? 0)) > 0) {
            $replace = BookingService::find($cid, $rid, $this->scope());
            if ($replace === null || $replace['status'] !== 'booked' || (int) $replace['employment_id'] !== (int) $emp['id']) {
                return $this->back('/company/bookings', ['ok' => false, 'error' => 'That booking can no longer be changed.'], '');
            }
            $moving = BookingService::casesOf($cid, $rid);
            $cases = array_merge($moving, $cases);
            $preselect = array_map('intval', array_column($moving, 'id'));
        }

        return $this->render('corporate/booking_new', [
            'emp' => $emp,
            'cases' => $cases,
            'preselect' => $preselect,
            'clinics' => BookingService::clinicsFor($cid, $emp['branch_id'] !== null ? (int) $emp['branch_id'] : null),
            'replace' => $replace,
            'currency' => \App\Services\Corporate\ProviderService::companyCurrency($cid),
        ]);
    }

    /** JSON: free slots for clinic + doctor + date. */
    public function slots(Request $request): Response
    {
        if (!CorpAuth::can(self::PERM)) {
            return Response::json(['ok' => false, 'error' => 'Not allowed.'], 403);
        }
        $res = BookingService::slots($this->cid(), (int) ($request->query['clinic'] ?? 0), (int) ($request->query['doctor'] ?? 0), (string) ($request->query['date'] ?? ''));

        return Response::json($res, $res['ok'] ? 200 : 422);
    }

    public function store(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $empId = (int) ($request->post['employment_id'] ?? 0);
        $replace = (int) ($request->post['replace_id'] ?? 0);
        $cases = $request->post['case_ids'] ?? [];
        $res = BookingService::book($this->cid(), $this->uid(), $empId, is_array($cases) ? array_map('intval', $cases) : [],
            (int) ($request->post['clinic_id'] ?? 0), (int) ($request->post['doctor_id'] ?? 0), (string) ($request->post['slot'] ?? ''), $this->scope(), $replace);

        return $this->back($res['ok'] ? '/company/bookings' : '/company/bookings/new?employee=' . $empId . ($replace > 0 ? '&replace=' . $replace : ''), $res,
            $replace > 0 ? 'Moved. The clinic has the new time.' : 'Booked. The clinic sees it in its appointments' . ' and the employee gets an email if we have their work email.');
    }

    public function cancel(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = BookingService::cancel($this->cid(), $this->uid(), (int) $id, (string) ($request->post['reason'] ?? ''), $this->scope());

        return $this->back('/company/bookings', $res, 'Cancelled. The checks are back on the due list.');
    }

    // ------------------------------------------------------------------

    /** @return list<int>|null */
    private function scope(): ?array
    {
        return CorpAuth::branchScope(self::PERM);
    }

    private function cid(): int
    {
        return (int) (CorpContext::company()['id'] ?? 0);
    }

    private function uid(): int
    {
        return (int) (CorpContext::user()['id'] ?? 0);
    }

    /** @param array{ok: bool, error?: string} $res */
    private function back(string $url, array $res, string $okMessage): Response
    {
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? $okMessage : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect($url);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }
}
