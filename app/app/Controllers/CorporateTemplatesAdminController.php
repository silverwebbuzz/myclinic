<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\Corporate\CompanyService;
use App\Services\Corporate\PlatformAdminScope;
use App\Services\Corporate\ProgrammeService;
use App\Services\Corporate\TemplateAdminService;
use App\Services\CsrfService;
use App\Support\Locale\CountryRepo;
use App\Support\SessionFlash;
use App\Support\View;

/**
 * Super admin › Workforce Health › Screening library (/admin/corporate/exam-types,
 * /admin/corporate/templates). Super admins: everything. Country admins: the
 * templates of their countries (the exam catalogue is shared, so super only).
 * Billing / support: read-only.
 */
final class CorporateTemplatesAdminController
{
    // ------------------------------------------------------------------
    // Exam catalogue
    // ------------------------------------------------------------------

    public function examTypes(Request $request): Response
    {
        return $this->render('admin/corporate_exam_types', [
            'examTypes' => TemplateAdminService::examTypes(),
            'canEdit' => PlatformAdminScope::isSuper($this->admin()),
        ]);
    }

    public function saveExamType(Request $request): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }

        return $this->back('/admin/corporate/exam-types', TemplateAdminService::saveExamType((int) ($request->post['id'] ?? 0), $request->post), 'Saved.');
    }

    public function toggleExamType(Request $request, string $id): Response
    {
        if ($deny = $this->superOnly()) {
            return $deny;
        }

        return $this->back('/admin/corporate/exam-types', TemplateAdminService::toggleExamType((int) $id), 'Updated.');
    }

    // ------------------------------------------------------------------
    // Templates
    // ------------------------------------------------------------------

    public function templates(Request $request): Response
    {
        $countries = PlatformAdminScope::countries($this->admin());

        return $this->render('admin/corporate_templates', [
            'templates' => TemplateAdminService::templates($countries),
            'countries' => $this->countryList(),
            'industries' => CompanyService::industries(),
            'canEdit' => $this->canEditAny(),
        ]);
    }

    public function createTemplate(Request $request): Response
    {
        if (!$this->canEditCountry((string) ($request->post['country_code'] ?? ''))) {
            return $this->back('/admin/corporate/templates', ['ok' => false, 'error' => 'Your role can\'t add templates for that country.'], '');
        }
        $res = TemplateAdminService::createDraft($this->adminId(), $request->post);

        return $this->back($res['ok'] ? '/admin/corporate/templates/' . (int) $res['id'] : '/admin/corporate/templates', $res, 'Draft created. Add its checks, then publish.');
    }

    public function template(Request $request, string $id): Response
    {
        $t = TemplateAdminService::find((int) $id);
        if ($t === null || !PlatformAdminScope::canSeeCountry($this->admin(), (string) $t['country_code'])) {
            return Response::html('Not found', 404);
        }

        return $this->render('admin/corporate_template', [
            't' => $t,
            'versions' => array_values(array_filter(TemplateAdminService::templates(null), static fn ($v) => $v['code'] === $t['code'])),
            'examTypes' => ProgrammeService::examTypes(),
            'industries' => CompanyService::industries(),
            'canEdit' => $this->canEditCountry((string) $t['country_code']),
        ]);
    }

    public function saveTemplate(Request $request, string $id): Response
    {
        return $this->onTemplate((int) $id, static fn () => TemplateAdminService::saveDraft((int) $id, $request->post), 'Saved.');
    }

    public function saveItem(Request $request, string $id): Response
    {
        return $this->onTemplate((int) $id, static fn () => TemplateAdminService::saveItem((int) $id, $request->post), 'Check saved.', '#items');
    }

    public function removeItem(Request $request, string $id, string $itemId): Response
    {
        return $this->onTemplate((int) $id, static fn () => TemplateAdminService::removeItem((int) $id, (int) $itemId), 'Check removed.', '#items');
    }

    public function publish(Request $request, string $id): Response
    {
        $adminId = $this->adminId();

        return $this->onTemplate((int) $id, static fn () => TemplateAdminService::publish((int) $id, $adminId),
            'Published. Companies using an older version will see "Update available" and review it themselves.');
    }

    public function newVersion(Request $request, string $id): Response
    {
        $t = TemplateAdminService::find((int) $id);
        if ($t === null || !$this->canEditCountry((string) $t['country_code'])) {
            return Response::html('Not found', 404);
        }
        $res = TemplateAdminService::newVersion((int) $id, $this->adminId());

        return $this->back('/admin/corporate/templates/' . (int) ($res['id'] ?? $id), $res, 'New draft version. Edit it, then publish.');
    }

    public function retire(Request $request, string $id): Response
    {
        return $this->onTemplate((int) $id, static fn () => TemplateAdminService::retire((int) $id), 'Retired. It no longer appears in the company library.');
    }

    public function deleteDraft(Request $request, string $id): Response
    {
        $t = TemplateAdminService::find((int) $id);
        if ($t === null || !$this->canEditCountry((string) $t['country_code'])) {
            return Response::html('Not found', 404);
        }

        return $this->back('/admin/corporate/templates', TemplateAdminService::deleteDraft((int) $id), 'Draft deleted.');
    }

    // ------------------------------------------------------------------

    /** @param callable(): array{ok: bool, error?: string} $fn */
    private function onTemplate(int $id, callable $fn, string $ok, string $anchor = ''): Response
    {
        $t = TemplateAdminService::find($id);
        if ($t === null || !$this->canEditCountry((string) $t['country_code'])) {
            return Response::html('Not found', 404);
        }

        return $this->back('/admin/corporate/templates/' . $id . $anchor, $fn(), $ok);
    }

    private function canEditAny(): bool
    {
        return in_array(PlatformAdminScope::role($this->admin()), ['super', 'country_admin'], true);
    }

    private function canEditCountry(string $code): bool
    {
        return $this->canEditAny() && PlatformAdminScope::canSeeCountry($this->admin(), $code);
    }

    /** @return list<array<string, mixed>> countries this admin may use */
    private function countryList(): array
    {
        return array_values(array_filter(CountryRepo::allCountries(), fn ($c) => PlatformAdminScope::canSeeCountry($this->admin(), (string) $c['code'])));
    }

    /** @return array<string, mixed> */
    private function admin(): array
    {
        return RequestContext::superAdmin() ?? [];
    }

    private function adminId(): int
    {
        return (int) ($this->admin()['id'] ?? 0);
    }

    private function superOnly(): ?Response
    {
        return PlatformAdminScope::isSuper($this->admin()) ? null : Response::html('Only super admins can do this.', 403);
    }

    /** @param array{ok: bool, error?: string} $res */
    private function back(string $url, array $res, string $okMessage): Response
    {
        SessionFlash::put($res['ok'] ? 'corp_admin_ok' : 'corp_admin_err', $res['ok'] ? $okMessage : ($res['error'] ?? 'Something went wrong.'));

        return Response::redirect($url);
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): Response
    {
        return Response::html(View::render($view, $data + [
            'csrf' => CsrfService::token(),
            'flashOk' => SessionFlash::pull('corp_admin_ok'),
            'flashErr' => SessionFlash::pull('corp_admin_err'),
            'adminRole' => PlatformAdminScope::role($this->admin()),
        ]));
    }
}
