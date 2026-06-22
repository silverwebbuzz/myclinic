<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\RequestContext;
use App\Http\Request;
use App\Http\Response;
use App\Services\CsrfService;
use App\Services\EmailTemplateService;
use App\Services\MailService;
use App\Support\View;

/**
 * EmailTemplateAdminController — /admin/email-templates (super-admin only).
 *
 * Lets an admin edit the CONTENT of transactional emails (subject, greeting,
 * paragraphs, bullets, CTA button, sign-off) without code changes. The branded
 * HTML wrapper is applied by MailService; an empty row falls back to the code
 * default. Mirrors the messaging admin page's shape.
 */
final class EmailTemplateAdminController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('admin/email-templates', [
            'admin' => RequestContext::superAdmin(),
            'csrf' => CsrfService::token(),
            'registry' => EmailTemplateService::registry(),
            'rows' => EmailTemplateService::allRows(),
            'message' => $request->query['message'] ?? null,
        ]));
    }

    /** POST /admin/email-templates/{key} — save one template's content. */
    public function save(Request $request, string $key): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/email-templates');
        }
        if (!EmailTemplateService::isKnown($key)) {
            return Response::redirect('/admin/email-templates?message=unknown_template');
        }

        $adminEmail = (string) (RequestContext::superAdmin()['email'] ?? '') ?: null;

        $ok = EmailTemplateService::save($key, [
            'subject' => $request->post['subject'] ?? '',
            'greeting' => $request->post['greeting'] ?? '',
            'body' => $request->post['body'] ?? '',
            'bullets' => $request->post['bullets'] ?? '',
            'cta_label' => $request->post['cta_label'] ?? '',
            'cta_url' => $request->post['cta_url'] ?? '',
            'sign_off' => $request->post['sign_off'] ?? '',
            'is_active' => isset($request->post['is_active']),
        ], $adminEmail);

        return Response::redirect('/admin/email-templates?message=' . ($ok ? 'saved' : 'save_failed') . '#' . $key);
    }

    /** POST /admin/email-templates/{key}/reset — drop override, use code default. */
    public function reset(Request $request, string $key): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/email-templates');
        }
        EmailTemplateService::reset($key);

        return Response::redirect('/admin/email-templates?message=reset#' . $key);
    }

    /** POST /admin/email-templates/test — send a test of one template. */
    public function test(Request $request): Response
    {
        if (!CsrfService::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/admin/email-templates');
        }
        $to = trim((string) ($request->post['test_to'] ?? ''));
        $key = trim((string) ($request->post['test_template'] ?? 'welcome'));
        if ($to === '' || !EmailTemplateService::isKnown($key)) {
            return Response::redirect('/admin/email-templates?message=test_invalid');
        }
        $result = MailService::sendTest($to, $key);

        return Response::redirect('/admin/email-templates?message='
            . (!empty($result['ok']) ? 'test_sent' : 'test_failed') . '#' . $key);
    }
}
