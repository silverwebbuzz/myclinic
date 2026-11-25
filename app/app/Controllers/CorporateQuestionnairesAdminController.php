<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\PlatformAdminScope;
use App\Services\CsrfService;
use App\Services\OccHealth\QuestionnaireAdminService;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Super admin › Workforce Health › Questionnaires (/admin/corporate/questionnaires).
 * One questionnaire is shared by every company and clinic, so only super admins edit;
 * other admin roles can look.
 */
final class CorporateQuestionnairesAdminController
{
    public function index(Request $request): Response
    {
        return $this->render('admin/corporate_questionnaires', ['rows' => QuestionnaireAdminService::all()]);
    }

    public function show(Request $request, string $id): Response
    {
        $q = QuestionnaireAdminService::find((int) $id);

        return $q === null ? Response::html('Not found', 404) : $this->render('admin/corporate_questionnaire', ['q' => $q]);
    }

    public function save(Request $request, string $id): Response
    {
        return $this->act((int) $id, static fn () => QuestionnaireAdminService::saveDraft((int) $id, $request->post), 'Saved.');
    }

    public function saveItem(Request $request, string $id): Response
    {
        return $this->act((int) $id, static fn () => QuestionnaireAdminService::saveItem((int) $id, $request->post), 'Question saved.', '#questions');
    }

    public function removeItem(Request $request, string $id, string $itemId): Response
    {
        return $this->act((int) $id, static fn () => QuestionnaireAdminService::removeItem((int) $id, (int) $itemId), 'Question removed.', '#questions');
    }

    public function publish(Request $request, string $id): Response
    {
        return $this->act((int) $id, static fn () => QuestionnaireAdminService::publish((int) $id), 'Published. Clinics and employees now see this version.');
    }

    public function newVersion(Request $request, string $id): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }
        $res = QuestionnaireAdminService::newVersion((int) $id);
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok'] ? 'Draft ready. Edit it, then publish.' : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/admin/corporate/questionnaires/' . (int) ($res['id'] ?? $id));
    }

    public function deleteDraft(Request $request, string $id): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }
        $res = QuestionnaireAdminService::deleteDraft((int) $id);
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok'] ? 'Draft deleted.' : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/admin/corporate/questionnaires');
    }

    /** @param callable(): array{ok: bool, error?: string} $fn */
    private function act(int $id, callable $fn, string $ok, string $anchor = ''): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }
        $res = $fn();
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok'] ? $ok : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect('/admin/corporate/questionnaires/' . $id . $anchor);
    }

    private function superOnly(): ?Response
    {
        return PlatformAdminScope::isSuper(RequestContext::superAdmin() ?? []) ? null : Response::html('Only super admins can change the questionnaire.', 403);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'canEdit' => PlatformAdminScope::isSuper(RequestContext::superAdmin() ?? []),
            'flashOk' => SessionFlash::pull('corp_admin_ok'),
            'flashErr' => SessionFlash::pull('corp_admin_err'),
            'adminRole' => PlatformAdminScope::role(RequestContext::superAdmin() ?? []),
        ]));
    }
}
