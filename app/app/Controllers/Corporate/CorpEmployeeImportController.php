<?php

declare(strict_types=1);

namespace App\Controllers\Corporate;

use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CorpAuth;
use App\Services\Corporate\CorpContext;
use App\Services\Corporate\EmployeeImportService;
use App\Services\Corporate\StructureService;
use App\Services\CsrfService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * /company/employees/import — template, upload, column mapping, dry-run
 * results, confirm (in batches, with a progress bar) and the error file.
 */
final class CorpEmployeeImportController
{
    private const PERM = 'employees.import';

    public function index(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();

        return $this->render([
            'stage' => 'upload',
            'recent' => EmployeeImportService::recent($cid),
            'hasBranches' => StructureService::branches($cid, true) !== [],
        ]);
    }

    public function template(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }

        return new Response(EmployeeImportService::templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="employees-template.csv"',
        ]);
    }

    public function upload(Request $request): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = EmployeeImportService::upload((int) CorpContext::companyId(), (int) CorpContext::userId(), $_FILES['file'] ?? []);
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Upload failed.');

            return Response::redirect('/company/employees/import');
        }

        return Response::redirect('/company/employees/import/' . (int) $res['id']);
    }

    public function show(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $import = EmployeeImportService::find($cid, (int) $id);
        if ($import === null) {
            SessionFlash::put('corp_err', 'Import not found.');

            return Response::redirect('/company/employees/import');
        }
        $data = ['import' => $import, 'stage' => (string) $import['status']];
        if ($import['status'] === 'uploaded') {
            $preview = EmployeeImportService::preview($import);
            if (!$preview['ok']) {
                SessionFlash::put('corp_err', $preview['error'] ?? 'Could not read the file.');

                return Response::redirect('/company/employees/import');
            }
            $data += [
                'stage' => 'map',
                'header' => $preview['header'],
                'samples' => $preview['samples'],
                'mapping' => json_decode((string) $import['mapping_json'], true) ?: [],
            ];
        } elseif (in_array($import['status'], ['checked', 'committing', 'committed'], true)) {
            $data += [
                'errors' => EmployeeImportService::rows($cid, (int) $id, ['error'], 200),
                'changes' => $import['status'] === 'checked' ? EmployeeImportService::rows($cid, (int) $id, ['create', 'update'], 50) : [],
                'mapping' => json_decode((string) $import['mapping_json'], true) ?: [],
            ];
        }

        return $this->render($data);
    }

    /** Re-open the mapping step (only before anything was imported). */
    public function remap(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $import = EmployeeImportService::find($cid, (int) $id);
        if ($import !== null && $import['status'] === 'checked') {
            EmployeeImportService::reopen($cid, (int) $id);
        }

        return Response::redirect('/company/employees/import/' . (int) $id);
    }

    public function check(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = EmployeeImportService::check((int) CorpContext::companyId(), (int) $id, $request->post, CorpAuth::branchScope(self::PERM));
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not check the file.');
        }

        return Response::redirect('/company/employees/import/' . (int) $id);
    }

    /** One batch. JSON for the progress bar; a plain form post (no JS) redirects back. */
    public function commit(Request $request, string $id): Response
    {
        $json = str_contains((string) $request->header('Accept', ''), 'application/json');
        if (!CorpAuth::can(self::PERM)) {
            return $json ? Response::json(['ok' => false, 'error' => 'Not allowed.'], 403) : (CorpAuth::guard(self::PERM) ?? Response::redirect('/company/employees'));
        }
        $res = EmployeeImportService::commitBatch((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id, CorpAuth::branchScope(self::PERM));
        if ($json) {
            return Response::json($res, $res['ok'] ? 200 : 409);
        }
        if (!$res['ok']) {
            SessionFlash::put('corp_err', $res['error'] ?? 'Could not import.');
        }

        return Response::redirect('/company/employees/import/' . (int) $id);
    }

    public function cancel(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $res = EmployeeImportService::cancel((int) CorpContext::companyId(), (int) CorpContext::userId(), (int) $id);
        SessionFlash::put($res['ok'] ? 'corp_ok' : 'corp_err', $res['ok'] ? 'Import cancelled. The file has been deleted.' : ($res['error'] ?? 'Could not cancel.'));

        return Response::redirect('/company/employees/import');
    }

    public function errors(Request $request, string $id): Response
    {
        if ($deny = CorpAuth::guard(self::PERM)) {
            return $deny;
        }
        $cid = (int) CorpContext::companyId();
        $import = EmployeeImportService::find($cid, (int) $id);
        if ($import === null) {
            return Response::redirect('/company/employees/import');
        }
        $base = (string) preg_replace('/[^A-Za-z0-9_\-]+/', '-', pathinfo((string) $import['file_name'], PATHINFO_FILENAME));

        return new Response(EmployeeImportService::errorsCsv($cid, (int) $id), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . trim($base, '-') . '-problems.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @param array<string, mixed> $data */
    private function render(array $data): Response
    {
        return Response::html(View::render('corporate/employee_import', $data + [
            'csrf' => CsrfService::token(),
            'company' => CorpContext::company() ?? [],
            'user' => CorpContext::user() ?? [],
            'loc' => CorpContext::locale(),
            'fields' => EmployeeImportService::FIELDS,
            'flashOk' => SessionFlash::pull('corp_ok'),
            'flashErr' => SessionFlash::pull('corp_err'),
        ]));
    }
}
